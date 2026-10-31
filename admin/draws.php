<?php
// admin/draws.php
// Draw list. Edit / End / Cancel now live on admin/draw-manage.php (Details page).
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../lib/draw_trigger.php';
require_once __DIR__ . '/../lib/draw_form.php';
$auth = requireAdmin(); $adminId = $auth['id'];
$db = getDB(); $msg = $err = '';
$failed = null;   // 'create' when validation failed: the modal reopens with the admin's input kept

$TYPES = ['timed' => 'Timed draw', 'entries' => 'Entry-triggered'];

// ── POST actions ───────────────────────────────────────────
if (isPost() && verifyCsrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
    $action = $_POST['action'] ?? '';
    $did    = (int)($_POST['draw_id'] ?? 0);

    // ── Create ─────────────────────────────────────────────
    if ($action === 'create') {
        $type = isset($TYPES[$_POST['draw_type'] ?? '']) ? $_POST['draw_type'] : 'timed';
        [$err, $v] = validateDrawForm($type);
        if ($err) $failed = 'create';
        if (!$err) {
            $banner = uploadDrawBanner();
            $db->prepare(
                "INSERT INTO draws
                   (title,description,rules,prize_details,category,banner_image,
                    status,start_date,end_date,draw_type,target_entries,created_by)
                 VALUES (?,?,?,?,?,?,'pending',?,?,?,?,?)"
            )->execute([$v['title'],$v['desc'],$v['rules'],$v['prize'],$v['cat'],$banner,$v['start'],$v['end'],$type,$v['target'],$adminId]);
            $newId = (int)$db->lastInsertId();
            auditLog('admin',$adminId,'create_draw',"Draw '{$v['title']}' created ($type" . ($v['target'] ? ", target {$v['target']} entries" : '') . ")",'draw',$newId);
            $msg = "Draw <strong>".e($v['title'])."</strong> created as Pending.";
        }

    // ── Activate ───────────────────────────────────────────
    } elseif ($action === 'activate' && $did) {
        $db->prepare(
            "UPDATE draws SET status='active',activated_by=?,activated_at=NOW(),updated_at=NOW()
             WHERE id=? AND status='pending'"
        )->execute([$adminId,$did]);
        auditLog('admin',$adminId,'activate_draw',"Draw #$did manually activated",'draw',$did);
        $msg = 'Draw activated. Users can now enter.';

    // ── Delete (pending only) ──────────────────────────────
    } elseif ($action === 'delete' && $did) {
        $db->prepare("DELETE FROM draws WHERE id=? AND status='pending'")->execute([$did]);
        auditLog('admin',$adminId,'delete_draw',"Draw #$did deleted",'draw',$did);
        $msg = 'Draw deleted.';
    }
}

// ── Safety net: end any entry draw that already reached its target ──
$autoEnded = sweepEntryTargetDraws($db);
if ($autoEnded > 0 && !$msg) {
    $msg = "<strong>$autoEnded</strong> draw" . ($autoEnded > 1 ? 's' : '') . ' reached the entry target and ' . ($autoEnded > 1 ? 'are' : 'is') . ' waiting for you to select a winner.';
}

// ── Load draws list ────────────────────────────────────────
$tab = $_GET['tab'] ?? 'all';
if (!in_array($tab, ['all','pending','active','ended','completed','cancelled'])) $tab = 'all';
$typeF = isset($TYPES[$_GET['type'] ?? '']) ? $_GET['type'] : '';

$conds = []; $params = [];
if ($tab !== 'all') { $conds[] = "status=?";    $params[] = $tab; }
if ($typeF !== '')  { $conds[] = "draw_type=?"; $params[] = $typeF; }
$where = $conds ? implode(' AND ', $conds) : '1=1';

$draws = $db->prepare(
    "SELECT d.*,
            (SELECT COUNT(*) FROM draw_entries WHERE draw_id=d.id) AS entries
     FROM draws d
     WHERE $where
     ORDER BY created_at DESC"
);
$draws->execute($params);
$draws = $draws->fetchAll();

$tabCounts = [];
foreach (['pending','active','ended','completed','cancelled'] as $t)
    $tabCounts[$t] = (int)$db->query("SELECT COUNT(*) FROM draws WHERE status='$t'")->fetchColumn();
$awaiting = (int)$db->query("SELECT COUNT(*) FROM draws WHERE status='ended' AND winner_user_id IS NULL")->fetchColumn();

// URL builder that keeps the current tab + type filter
$u = function (array $over = []) use ($tab, $typeF) {
    $q = array_filter(array_merge(['tab' => $tab === 'all' ? '' : $tab, 'type' => $typeF], $over), fn($x) => $x !== '' && $x !== null);
    return '?' . http_build_query($q);
};

$aPage = 'draws';
/** Value to show in a form field: what the admin just typed if that form failed validation, else the default. */
$old = fn(string $form, string $key, $default = '') => ($failed === $form && isset($_POST[$key])) ? $_POST[$key] : $default;
$categories = drawCategories();
$tabMeta = ['pending'=>'#facc15','active'=>'#4ade80','ended'=>'#fb923c','completed'=>'#94a3b8','cancelled'=>'#f87171'];
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="csrf-token" content="<?= generateCsrf() ?>">
  <title>Draws — <?= APP_NAME ?> Admin</title>
  <script src="<?= APP_URL ?>/assets/js/tailwind.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
</head>
<body class="bg-[#0a0f1a] text-white antialiased">
<?php include __DIR__ . '/../components/saas-ui.php'; ?>
<?php include __DIR__ . '/../components/admin-sidebar.php'; ?>

<div class="main-content">
  <div class="topbar">
    <button onclick="toggleSidebar()" class="md:hidden text-gray-400 text-2xl mr-3" aria-label="Menu">☰</button>
    <div class="min-w-0">
      <h1 class="text-lg font-semibold leading-tight">Draws</h1>
      <p class="text-xs text-slate-400 hidden sm:block">Create, schedule and monitor your draws</p>
    </div>
    <button onclick="Modal.open('create-modal')" class="s-btn s-btn-primary ml-auto"><?= ico('plus') ?> Create draw</button>
  </div>

  <div class="p-4 md:p-8 max-w-6xl mx-auto space-y-6">

    <!-- Flash messages -->
    <?php if ($msg): ?>
    <div class="s-alert s-alert-success" role="status"><?= ico('check-circle', 'w-5 h-5 flex-shrink-0 mt-px') ?><div><?= $msg ?></div></div>
    <?php endif; ?>
    <?php if ($err && !$failed): ?>
    <div class="s-alert s-alert-error" role="alert"><?= ico('alert', 'w-5 h-5 flex-shrink-0 mt-px') ?><div><?= e($err) ?></div></div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 md:gap-4">
      <div class="s-card p-4 md:p-5 flex items-center gap-3.5">
        <div class="s-stat-icon bg-green-500/10 text-green-400"><?= ico('bolt', 'w-[18px] h-[18px]') ?></div>
        <div><div class="s-eyebrow">Live now</div><div class="text-2xl font-semibold leading-tight"><?= number_format($tabCounts['active']) ?></div></div>
      </div>
      <div class="s-card p-4 md:p-5 flex items-center gap-3.5">
        <div class="s-stat-icon bg-yellow-500/10 text-yellow-400"><?= ico('clock', 'w-[18px] h-[18px]') ?></div>
        <div><div class="s-eyebrow">Pending</div><div class="text-2xl font-semibold leading-tight"><?= number_format($tabCounts['pending']) ?></div></div>
      </div>
      <a href="<?= e($u(['tab'=>'ended'])) ?>" class="s-card s-card-hover p-4 md:p-5 flex items-center gap-3.5 <?= $awaiting > 0 ? '!border-orange-400/40' : '' ?>">
        <div class="s-stat-icon bg-orange-500/10 text-orange-400"><?= ico('trophy', 'w-[18px] h-[18px]') ?></div>
        <div><div class="s-eyebrow">Awaiting winner</div><div class="text-2xl font-semibold leading-tight <?= $awaiting > 0 ? 'text-orange-400' : '' ?>"><?= number_format($awaiting) ?></div></div>
      </a>
      <div class="s-card p-4 md:p-5 flex items-center gap-3.5">
        <div class="s-stat-icon bg-slate-400/10 text-slate-300"><?= ico('check-circle', 'w-[18px] h-[18px]') ?></div>
        <div><div class="s-eyebrow">Completed</div><div class="text-2xl font-semibold leading-tight"><?= number_format($tabCounts['completed']) ?></div></div>
      </div>
    </div>

    <!-- How the two types work -->
    <details class="s-card group">
      <summary class="cursor-pointer select-none list-none px-5 py-3.5 text-sm font-medium flex items-center justify-between">
        <span class="flex items-center gap-2 text-slate-200"><?= ico('info', 'w-4 h-4 text-slate-400') ?> How the two draw types work</span>
        <span class="text-slate-500 group-open:rotate-180 transition"><?= ico('chevron') ?></span>
      </summary>
      <div class="px-5 pb-5 grid md:grid-cols-2 gap-3 text-sm">
        <div class="rounded-xl bg-white/[0.03] border border-white/10 p-4">
          <div class="font-medium mb-1 flex items-center gap-2"><span class="text-cyan-300"><?= ico('clock') ?></span> Timed draw</div>
          <p class="text-slate-400 text-xs leading-relaxed">Runs between a start and end date. It ends at the end date (automatically, or when you press End Draw on its Details page). Then you select the winner.</p>
        </div>
        <div class="rounded-xl bg-white/[0.03] border border-white/10 p-4">
          <div class="font-medium mb-1 flex items-center gap-2"><span class="text-purple-300"><?= ico('target') ?></span> Entry-triggered draw</div>
          <p class="text-slate-400 text-xs leading-relaxed">You set a number of entries, for example 20. The moment the draw reaches it, it closes by itself and shows as <span class="text-orange-400 font-medium">Awaiting winner</span>. A deadline is optional.</p>
        </div>
        <div class="md:col-span-2 text-xs text-slate-500 flex flex-wrap items-center gap-x-2 gap-y-1">
          <span class="font-medium text-slate-300">Flow:</span>
          <?= sPill('pending') ?> <span>→</span> <?= sPill('active') ?> <span>→</span> <?= sPill('ended') ?> <span>(awaiting winner) →</span> <?= sPill('completed') ?>
        </div>
      </div>
    </details>

    <!-- Filters -->
    <div class="flex flex-col lg:flex-row lg:items-end lg:justify-between gap-3">
      <nav class="s-tabs flex-1" aria-label="Draw status">
        <a href="<?= e($u(['tab'=>''])) ?>" class="s-tab <?= $tab==='all'?'on':'' ?>">All <span class="s-count"><?= array_sum($tabCounts) ?></span></a>
        <?php foreach ($tabMeta as $t => $col): ?>
        <a href="<?= e($u(['tab'=>$t])) ?>" class="s-tab <?= $tab===$t?'on':'' ?>">
          <span class="w-2 h-2 rounded-full" style="background:<?= $col ?>"></span><?= ucfirst($t) ?>
          <?php if (($tabCounts[$t] ?? 0) > 0): ?><span class="s-count"><?= $tabCounts[$t] ?></span><?php endif; ?>
        </a>
        <?php endforeach; ?>
      </nav>
      <div class="s-seg self-start lg:self-auto">
        <?php foreach (['' => 'All types', 'timed' => 'Timed', 'entries' => 'Entry-triggered'] as $k => $l): ?>
        <a href="<?= e($u(['type'=>$k])) ?>" class="<?= $typeF===$k ? 'on' : '' ?>"><?= $l ?></a>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Draws list -->
    <div class="space-y-3">
      <?php if (!$draws): ?>
      <div class="s-card p-12 text-center">
        <div class="w-12 h-12 mx-auto mb-4 rounded-full bg-white/5 flex items-center justify-center text-slate-400"><?= ico('inbox', 'w-6 h-6') ?></div>
        <div class="font-medium mb-1">No draws found</div>
        <div class="text-sm text-slate-400 mb-5">Try another filter, or create a new draw.</div>
        <button onclick="Modal.open('create-modal')" class="s-btn s-btn-primary"><?= ico('plus') ?> Create draw</button>
      </div>
      <?php endif; ?>

      <?php foreach ($draws as $d):
        $isEntries = ($d['draw_type'] ?? 'timed') === 'entries';
        $target    = (int)($d['target_entries'] ?? 0);
        $ents      = (int)$d['entries'];
        $pct       = $target > 0 ? min(100, (int)floor($ents / $target * 100)) : 0;
        $reached   = $isEntries && $target > 0 && $ents >= $target;
        $deadline  = drawHasDeadline($d['end_date']);
        $detailUrl = APP_URL . '/admin/draw-manage.php?id=' . $d['id'];
      ?>
      <article class="s-card s-card-hover p-4 md:p-5">
        <div class="flex items-start gap-4">

          <!-- Banner thumb -->
          <div class="s-thumb">
            <?php if ($d['banner_image'] && file_exists(UPLOAD_PATH.$d['banner_image'])): ?>
            <img src="<?= APP_URL ?>/uploads/<?= e($d['banner_image']) ?>" class="w-full h-full object-cover" alt="">
            <?php else: ?><?= ico($isEntries ? 'target' : 'clock', 'w-7 h-7') ?><?php endif; ?>
          </div>

          <div class="flex-1 min-w-0">
            <!-- Title + pills -->
            <div class="flex items-center gap-x-2 gap-y-1.5 flex-wrap mb-2">
              <h3 class="font-semibold text-[15px] leading-snug"><a href="<?= $detailUrl ?>" class="hover:text-orange-300 transition"><?= e($d['title']) ?></a></h3>
              <?= sPill($d['status']) ?>
              <span class="s-pill <?= $isEntries ? 's-pill-purple' : 's-pill-cyan' ?>">
                <?= ico($isEntries ? 'target' : 'clock', 'w-3 h-3') ?> <?= $isEntries ? 'Entry-triggered' : 'Timed' ?>
              </span>
              <?php if ($d['category']): ?><span class="s-pill s-pill-blue"><?= e($d['category']) ?></span><?php endif; ?>
              <?php if ($d['status']==='ended' && !$d['winner_user_id']): ?>
              <span class="inline-flex items-center gap-1 text-xs font-medium text-orange-400"><?= ico('bolt', 'w-3.5 h-3.5') ?> Awaiting winner</span>
              <?php endif; ?>
            </div>

            <!-- Meta -->
            <div class="s-meta mb-3">
              <span class="s-meta-item"><?= ico('hash', 'w-3.5 h-3.5') ?> <?= number_format($ents) ?> entries</span>
              <span class="s-meta-item"><?= ico('calendar', 'w-3.5 h-3.5') ?> Start: <?= date('M j, Y g:i A', strtotime($d['start_date'])) ?></span>
              <?php if ($deadline): ?>
              <span class="s-meta-item"><?= ico('clock', 'w-3.5 h-3.5') ?> <?= $isEntries ? 'Deadline' : 'End' ?>: <?= date('M j, Y g:i A', strtotime($d['end_date'])) ?></span>
              <?php elseif ($isEntries): ?>
              <span class="s-meta-item"><?= ico('clock', 'w-3.5 h-3.5') ?> No deadline</span>
              <?php endif; ?>
              <?php if ($d['winner_user_id']): ?><span class="s-meta-item text-yellow-400"><?= ico('trophy', 'w-3.5 h-3.5') ?> Winner selected</span><?php endif; ?>
              <?php if ($d['status']==='active' && $deadline):
                $diff = strtotime($d['end_date']) - time();
                if ($diff > 0) { $dd=floor($diff/86400);$hh=floor(($diff%86400)/3600);$mm=floor(($diff%3600)/60);
                  echo '<span class="s-meta-item text-cyan-400">'.ico('clock','w-3.5 h-3.5').' '.($dd>0?"{$dd}d ":"")."{$hh}h {$mm}m remaining</span>"; }
              endif; ?>
              <?php if ($d['status']==='ended' && ($d['ended_reason'] ?? '') === 'target_reached'): ?>
              <span class="s-meta-item text-green-400"><?= ico('check', 'w-3.5 h-3.5') ?> Ended automatically: entry target reached</span>
              <?php endif; ?>
            </div>

            <!-- Entry progress (entry-triggered only) -->
            <?php if ($isEntries && $target > 0): ?>
            <div class="mb-1 max-w-md">
              <div class="flex items-center justify-between text-xs mb-1.5">
                <span class="font-medium <?= $reached ? 'text-green-400' : 'text-slate-300' ?>"><?= number_format($ents) ?> / <?= number_format($target) ?> entries</span>
                <span class="<?= $reached ? 'text-green-400 font-medium' : 'text-slate-500' ?>"><?= $reached ? 'Target reached' : $pct . '%' ?></span>
              </div>
              <div class="s-progress"><div class="<?= $reached ? 'done' : '' ?>" style="width:<?= $pct ?>%"></div></div>
              <?php if (!$reached && in_array($d['status'], ['active','pending'])): ?>
              <div class="text-[11.5px] text-slate-500 mt-1.5">Closes automatically after <?= number_format($target - $ents) ?> more <?= ($target - $ents) === 1 ? 'entry' : 'entries' ?>.</div>
              <?php endif; ?>
            </div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Actions (Edit / End / Cancel moved to Details) -->
        <div class="flex items-center gap-2 flex-wrap mt-4 pt-4 border-t border-white/[0.06]">
          <a href="<?= $detailUrl ?>" class="s-btn s-btn-sm s-btn-ghost"><?= ico('eye', 'w-3.5 h-3.5') ?> Details</a>

          <?php if ($d['status'] === 'pending'): ?>
            <form method="POST" class="inline" <?= confirmAttrs('Activate this draw?', '“'.$d['title'].'” will go live and users will be able to enter immediately.', 'Activate draw', 'success') ?>>
              <?= csrfField() ?><input type="hidden" name="action" value="activate"><input type="hidden" name="draw_id" value="<?= $d['id'] ?>">
              <button class="s-btn s-btn-sm s-btn-success"><?= ico('play', 'w-3.5 h-3.5') ?> Activate</button>
            </form>
            <form method="POST" class="inline" <?= confirmAttrs('Delete this draw?', '“'.$d['title'].'” will be permanently deleted. This cannot be undone.', 'Delete permanently', 'danger') ?>>
              <?= csrfField() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="draw_id" value="<?= $d['id'] ?>">
              <button class="s-btn s-btn-sm s-btn-danger"><?= ico('trash', 'w-3.5 h-3.5') ?> Delete</button>
            </form>
            <span class="text-xs text-slate-500 ml-auto hidden sm:inline">Edit this draw from Details</span>

          <?php elseif ($d['status'] === 'active'): ?>
            <span class="text-xs text-slate-500 ml-auto hidden sm:inline">Edit, end or cancel from Details</span>

          <?php elseif ($d['status'] === 'ended'): ?>
            <?php if (!$d['winner_user_id']): ?>
            <a href="<?= APP_URL ?>/admin/select-winner.php?id=<?= $d['id'] ?>" class="s-btn s-btn-sm s-btn-violet"><?= ico('trophy', 'w-3.5 h-3.5') ?> Select winner</a>
            <?php else: ?>
            <a href="<?= APP_URL ?>/admin/winners.php?draw=<?= $d['id'] ?>" class="s-btn s-btn-sm s-btn-ghost text-yellow-400"><?= ico('trophy', 'w-3.5 h-3.5') ?> View winner</a>
            <?php endif; ?>

          <?php elseif ($d['status'] === 'completed'): ?>
            <a href="<?= APP_URL ?>/admin/winners.php?draw=<?= $d['id'] ?>" class="s-btn s-btn-sm s-btn-ghost text-yellow-400"><?= ico('trophy', 'w-3.5 h-3.5') ?> View result</a>
          <?php endif; ?>
        </div>
      </article>
      <?php endforeach; ?>
    </div>

  </div>
</div>


<!-- ══════════════════════════════════════════════════════════
     CREATE DRAW MODAL
══════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="create-modal">
  <div class="modal-box s-modal" style="max-width:640px;max-height:92vh;overflow-y:auto">
    <div class="flex items-center justify-between mb-1">
      <h3 class="text-lg font-semibold">Create new draw</h3>
      <button data-close-modal="create-modal" class="text-slate-400 hover:text-white" aria-label="Close"><?= ico('x', 'w-5 h-5') ?></button>
    </div>
    <p class="text-slate-400 text-sm mb-5">The draw starts as <?= sPill('pending') ?>. Activate it when you're ready.</p>

    <?php if ($failed === 'create' && $err): ?>
    <div class="s-alert s-alert-error mb-4" role="alert"><?= ico('alert', 'w-5 h-5 flex-shrink-0') ?><div><?= e($err) ?></div></div>
    <?php endif; ?>
    <form method="POST" enctype="multipart/form-data" id="create-form">
      <?= csrfField() ?><input type="hidden" name="action" value="create">

      <!-- Type -->
      <div class="mb-5">
        <div class="s-label">Draw type</div>
        <div class="grid grid-cols-2 gap-3">
          <label class="s-type cursor-pointer">
            <input type="radio" name="draw_type" value="timed" class="sr-only" <?= $old('create','draw_type','timed') !== 'entries' ? 'checked' : '' ?>>
            <div class="h-full rounded-xl border border-white/10 bg-white/[0.03] p-3.5 transition">
              <div class="font-medium text-sm flex items-center gap-2"><span class="text-cyan-300"><?= ico('clock') ?></span> Timed draw</div>
              <div class="text-xs text-slate-400 mt-1">Ends on a date you choose.</div>
            </div>
          </label>
          <label class="s-type cursor-pointer">
            <input type="radio" name="draw_type" value="entries" class="sr-only" <?= $old('create','draw_type','timed') === 'entries' ? 'checked' : '' ?>>
            <div class="h-full rounded-xl border border-white/10 bg-white/[0.03] p-3.5 transition">
              <div class="font-medium text-sm flex items-center gap-2"><span class="text-purple-300"><?= ico('target') ?></span> Entry-triggered</div>
              <div class="text-xs text-slate-400 mt-1">Ends when it reaches a number of entries.</div>
            </div>
          </label>
        </div>
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div class="col-span-2">
          <label class="s-label">Title <span class="text-red-400">*</span></label>
          <input type="text" name="title" class="s-input" value="<?= e($old('create','title')) ?>" required autofocus>
        </div>

        <!-- Entry target (entry-triggered only) -->
        <div class="col-span-2 hidden" id="target-wrap">
          <label class="s-label">Entries needed to trigger the draw <span class="text-red-400">*</span></label>
          <input type="number" name="target_entries" id="target-input" class="s-input" min="2" max="<?= MAX_TARGET_ENTRIES ?>" step="1" placeholder="e.g. 20" value="<?= e($old('create','target_entries')) ?>">
          <div class="s-hint">When the draw reaches this many entries it closes automatically and waits for you to select the winner.</div>
        </div>

        <div>
          <label class="s-label">Category</label>
          <select name="category" class="s-input">
            <option value="">Select…</option>
            <?php foreach($categories as $c): ?><option value="<?= e($c) ?>" <?= $old('create','category') === $c ? 'selected' : '' ?>><?= e($c) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="s-label">Banner image</label>
          <input type="file" name="banner_image" class="s-input" accept="image/*">
        </div>
        <div>
          <label class="s-label">Start date &amp; time <span class="text-red-400">*</span></label>
          <input type="datetime-local" name="start_date" class="s-input" value="<?= e($old('create','start_date')) ?>" required>
          <div class="s-hint">Cron auto-activates at this time if still pending</div>
        </div>
        <div>
          <label class="s-label"><span id="end-label">End date &amp; time</span> <span class="text-red-400" id="end-star">*</span></label>
          <input type="datetime-local" name="end_date" id="end-input" class="s-input" value="<?= e($old('create','end_date')) ?>" required>
          <div class="s-hint" id="end-help">Cron auto-ends at this time if still active</div>
        </div>
        <div class="col-span-2"><label class="s-label">Description</label><textarea name="description" class="s-input" rows="2"><?= e($old('create','description')) ?></textarea></div>
        <div class="col-span-2"><label class="s-label">Prize details</label><textarea name="prize_details" class="s-input" rows="2"><?= e($old('create','prize_details')) ?></textarea></div>
        <div class="col-span-2"><label class="s-label">Rules</label><textarea name="rules" class="s-input" rows="2"><?= e($old('create','rules')) ?></textarea></div>
      </div>
      <div class="flex gap-3 mt-5">
        <button type="button" data-close-modal="create-modal" class="s-btn s-btn-ghost flex-1 py-2.5">Cancel</button>
        <button type="submit" class="s-btn s-btn-primary flex-1 py-2.5"><?= ico('plus') ?> Create draw</button>
      </div>
    </form>
  </div>
</div>


<script>window.APP_URL = '<?= APP_URL ?>';</script>
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
<script>
(function () {
  // Create form: show the entry target + make the end date an optional deadline for entry-triggered draws
  const form = document.getElementById('create-form');
  function syncType() {
    const entries = form.querySelector('input[name=draw_type]:checked').value === 'entries';
    document.getElementById('target-wrap').classList.toggle('hidden', !entries);
    document.getElementById('target-input').required = entries;
    document.getElementById('end-input').required = !entries;
    document.getElementById('end-star').classList.toggle('hidden', entries);
    document.getElementById('end-label').textContent = entries ? 'Deadline (optional)' : 'End date & time';
    document.getElementById('end-help').textContent  = entries
      ? 'Leave blank: only the entry target ends this draw'
      : 'Cron auto-ends at this time if still active';
  }
  form.querySelectorAll('input[name=draw_type]').forEach(r => r.addEventListener('change', syncType));
  syncType();

  // Reopen the create modal after a failed save so nothing the admin typed is lost
  const reopen = <?= $failed === 'create' ? "'create-modal'" : 'null' ?>;
  if (reopen) {
    setTimeout(function () {
      if (typeof Modal !== 'undefined' && Modal.open) Modal.open(reopen);
      else { var el = document.getElementById(reopen); if (el) el.style.display = 'flex'; }
    }, 80);
  }
})();
</script>
</body>
</html>
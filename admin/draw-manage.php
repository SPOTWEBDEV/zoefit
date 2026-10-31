<?php
// admin/draw-manage.php
// Draw details + results, and the draw controls: Edit, End draw, Cancel draw.
// Activate / Delete / Select winner stay on admin/draws.php and select-winner.php.
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../lib/draw_trigger.php';   // drawHasDeadline()
require_once __DIR__ . '/../lib/draw_form.php';      // validateDrawForm(), uploadDrawBanner()
$auth = requireAdmin(); $adminId = $auth['id'];
$db   = getDB();
$msg = $err = '';
$failed = null;   // 'edit' when validation failed: the modal reopens with the admin's input kept

$drawId = (int)($_GET['id'] ?? 0);
if (!$drawId) redirect(APP_URL . '/admin/draws.php');

$exists = $db->prepare("SELECT COUNT(*) FROM draws WHERE id=?");
$exists->execute([$drawId]);
if (!$exists->fetchColumn()) redirect(APP_URL . '/admin/draws.php');

// ── POST actions: edit / end / cancel ─────────────────────
if (isPost() && verifyCsrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
    $action = $_POST['action'] ?? '';

    $chk = $db->prepare("SELECT title, status, draw_type, (SELECT COUNT(*) FROM draw_entries WHERE draw_id=draws.id) AS entries FROM draws WHERE id=?");
    $chk->execute([$drawId]); $row = $chk->fetch();

    if (!$row) {
        $err = 'Draw not found.';

    // ── Edit ───────────────────────────────────────────────
    } elseif ($action === 'edit') {
        if (in_array($row['status'], ['completed','cancelled','ended'])) {
            $err = 'Ended, completed or cancelled draws cannot be edited.';
        } else {
            $type = $row['draw_type'] ?: 'timed';           // the draw type is fixed once created
            [$err, $v] = validateDrawForm($type, (int)$row['entries']);
            if ($err) $failed = 'edit';
            if (!$err) {
                $banner = uploadDrawBanner();
                $sets = "title=?,description=?,rules=?,prize_details=?,category=?,start_date=?,end_date=?,target_entries=?,updated_at=NOW()";
                $args = [$v['title'],$v['desc'],$v['rules'],$v['prize'],$v['cat'],$v['start'],$v['end'],$v['target']];
                if ($banner) { $sets = "banner_image=?," . $sets; array_unshift($args, $banner); }
                $args[] = $drawId;
                $db->prepare("UPDATE draws SET $sets WHERE id=?")->execute($args);
                auditLog('admin',$adminId,'edit_draw',"Draw #$drawId updated",'draw',$drawId);
                $msg = 'Draw updated successfully.';
            }
        }

    // ── End draw ───────────────────────────────────────────
    } elseif ($action === 'end') {
        $st = $db->prepare(
            "UPDATE draws SET status='ended',ended_by=?,ended_at=NOW(),ended_reason='manual',updated_at=NOW()
             WHERE id=? AND status='active'"
        );
        $st->execute([$adminId,$drawId]);
        if ($st->rowCount()) {
            auditLog('admin',$adminId,'end_draw',"Draw #$drawId manually ended",'draw',$drawId);
            $msg = 'Draw ended. <a href="'.APP_URL.'/admin/select-winner.php?id='.$drawId.'">Select the winner now →</a>';
        } else {
            $err = 'Only an active draw can be ended.';
        }

    // ── Cancel ─────────────────────────────────────────────
    } elseif ($action === 'cancel') {
        $st = $db->prepare(
            "UPDATE draws SET status='cancelled',updated_at=NOW()
             WHERE id=? AND status NOT IN('completed','cancelled')"
        );
        $st->execute([$drawId]);
        if ($st->rowCount()) {
            auditLog('admin',$adminId,'cancel_draw',"Draw #$drawId cancelled",'draw',$drawId);
            $msg = 'Draw cancelled.';
        } else {
            $err = 'This draw can no longer be cancelled.';
        }
    }
}

// ── Load the draw (after POST so it shows the new state) ───
$draw = $db->prepare("SELECT * FROM draws WHERE id=?");
$draw->execute([$drawId]); $draw = $draw->fetch();
if (!$draw) redirect(APP_URL . '/admin/draws.php');

// ── Draw type + dates ──────────────────────────────────────
$isEntries = ($draw['draw_type'] ?? 'timed') === 'entries';
$target    = (int)($draw['target_entries'] ?? 0);
$hasDeadline = drawHasDeadline($draw['end_date']);   // entry draws without a deadline store a placeholder date: never show it
$startTime = strtotime($draw['start_date']);
$endTime   = $hasDeadline ? strtotime($draw['end_date']) : null;

// ── Stats ──────────────────────────────────────────────────
$s = $db->prepare("SELECT COUNT(*) FROM draw_entries WHERE draw_id=?");
$s->execute([$drawId]); $totalEntries = (int)$s->fetchColumn();

$s = $db->prepare("SELECT COUNT(DISTINCT user_id) FROM draw_entries WHERE draw_id=?");
$s->execute([$drawId]); $uniqueUsers = (int)$s->fetchColumn();

$pct     = ($isEntries && $target > 0) ? min(100, (int)floor($totalEntries / $target * 100)) : 0;
$reached = $isEntries && $target > 0 && $totalEntries >= $target;

// Entry breakdown: how many codes each user entered with
$topEntrants = $db->prepare(
    "SELECT u.full_name, u.phone, COUNT(de.id) AS code_count
     FROM draw_entries de JOIN users u ON de.user_id=u.id
     WHERE de.draw_id=?
     GROUP BY de.user_id ORDER BY code_count DESC LIMIT 10"
);
$topEntrants->execute([$drawId]); $topEntrants = $topEntrants->fetchAll();

// Existing winner (if already selected)
$existingWinner = $db->prepare(
    "SELECT dw.*, u.full_name, u.phone
     FROM draw_winners dw JOIN users u ON dw.user_id=u.id
     WHERE dw.draw_id=?"
);
$existingWinner->execute([$drawId]); $existingWinner = $existingWinner->fetch();

// Official top 3 from draw_rankings
$top3Official = null;
if ($existingWinner) {
    $rk = $db->prepare(
        "SELECT dr.*, u.full_name, u.phone
         FROM draw_rankings dr JOIN users u ON dr.user_id=u.id
         WHERE dr.draw_id=? ORDER BY dr.rank_position ASC"
    );
    $rk->execute([$drawId]);
    $top3Official = $rk->fetchAll();
}

// Recent entries
$recentEntries = $db->prepare(
    "SELECT u.full_name, u.phone, c.code, de.entered_at
     FROM draw_entries de
     JOIN users u ON de.user_id = u.id
     JOIN codes c ON de.code_id = c.id
     WHERE de.draw_id=? ORDER BY de.entered_at DESC LIMIT 10"
);
$recentEntries->execute([$drawId]); $recentEntries = $recentEntries->fetchAll();

$awaitingWinner = $draw['status'] === 'ended' && !$existingWinner;
$initial = fn(?string $n) => strtoupper(mb_substr(trim((string)$n) ?: '?', 0, 1));

// Which controls are available (same rules the list page used to apply)
$canEdit   = in_array($draw['status'], ['pending','active']);
$canEnd    = $draw['status'] === 'active';
$canCancel = $draw['status'] === 'active';

$aPage = 'draws';
$categories = drawCategories();
/** Value to show in the edit form: what the admin just typed if it failed validation, else the stored value. */
$old = fn(string $key, $default = '') => ($failed === 'edit' && isset($_POST[$key])) ? $_POST[$key] : $default;
$eDeadline = $hasDeadline;
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="csrf-token" content="<?= generateCsrf() ?>">
  <title>Draw Details — <?= APP_NAME ?> Admin</title>
  <script src="<?= APP_URL ?>/assets/js/tailwind.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
  <style>
    .digit-slot {
      display: inline-flex; align-items: center; justify-content: center;
      width: 34px; height: 42px; border-radius: 10px;
      font-size: 18px; font-weight: 700; font-family: ui-monospace, Menlo, Consolas, monospace;
      border: 1px solid rgba(255,255,255,.1); background: rgba(255,255,255,.04);
    }
    .digit-slot.match    { background: rgba(34,197,94,.16); border-color: #22c55e; color: #4ade80; }
    .digit-slot.no-match { background: rgba(239,68,68,.06); border-color: rgba(239,68,68,.22); color: #94a3b8; }
  </style>
</head>
<body class="bg-[#0a0f1a] text-white antialiased">
<?php include __DIR__ . '/../components/saas-ui.php'; ?>
<?php include __DIR__ . '/../components/admin-sidebar.php'; ?>

<div class="main-content">
  <div class="topbar">
    <button onclick="toggleSidebar()" class="md:hidden text-gray-400 text-2xl mr-3" aria-label="Menu">☰</button>
    <div class="min-w-0">
      <a href="<?= APP_URL ?>/admin/draws.php" class="inline-flex items-center gap-1 text-xs text-slate-400 hover:text-orange-300 transition"><?= ico('arrow-left', 'w-3.5 h-3.5') ?> All draws</a>
      <h1 class="text-lg font-semibold truncate leading-tight"><?= e($draw['title']) ?></h1>
    </div>
    <?php if ($draw['status'] === 'active' && $hasDeadline): ?>
    <div class="ml-auto s-pill s-pill-cyan" id="countdown-display">…</div>
    <?php endif; ?>
  </div>

  <div class="p-4 md:p-8 max-w-5xl mx-auto space-y-5">

    <!-- Flash messages -->
    <?php if ($msg): ?>
    <div class="s-alert s-alert-success" role="status"><?= ico('check-circle', 'w-5 h-5 flex-shrink-0 mt-px') ?><div><?= $msg ?></div></div>
    <?php endif; ?>
    <?php if ($err && !$failed): ?>
    <div class="s-alert s-alert-error" role="alert"><?= ico('alert', 'w-5 h-5 flex-shrink-0 mt-px') ?><div><?= e($err) ?></div></div>
    <?php endif; ?>

    <!-- Summary + controls -->
    <div class="s-card p-5 md:p-6">
      <div class="flex flex-col md:flex-row md:items-start md:justify-between gap-4">
        <div class="min-w-0">
          <div class="flex flex-wrap items-center gap-2">
            <?= sPill($draw['status']) ?>
            <span class="s-pill <?= $isEntries ? 's-pill-purple' : 's-pill-cyan' ?>">
              <?= ico($isEntries ? 'target' : 'clock', 'w-3 h-3') ?> <?= $isEntries ? 'Entry-triggered' : 'Timed' ?>
            </span>
            <?php if ($draw['category']): ?><span class="s-pill s-pill-blue"><?= e($draw['category']) ?></span><?php endif; ?>
            <?php if ($draw['status'] === 'ended' && ($draw['ended_reason'] ?? '') === 'target_reached'): ?>
            <span class="inline-flex items-center gap-1 text-xs text-green-400"><?= ico('check', 'w-3.5 h-3.5') ?> Closed automatically at the entry target</span>
            <?php endif; ?>
          </div>
          <?php if (!empty($draw['prize_details'])): ?>
          <div class="text-sm text-orange-400 font-medium mt-3 flex items-start gap-2"><?= ico('trophy', 'w-4 h-4 mt-0.5 flex-shrink-0') ?><span><?= e($draw['prize_details']) ?></span></div>
          <?php endif; ?>
          <?php if (!empty($draw['description'])): ?>
          <p class="text-sm text-slate-400 mt-2 leading-relaxed"><?= nl2br(e($draw['description'])) ?></p>
          <?php endif; ?>
        </div>

        <!-- Draw controls -->
        <?php if ($canEdit || $canEnd || $canCancel): ?>
        <div class="flex flex-wrap gap-2 md:justify-end flex-shrink-0">
          <?php if ($canEdit): ?>
          <button type="button" onclick="Modal.open('edit-modal')" class="s-btn s-btn-ghost"><?= ico('edit') ?> Edit</button>
          <?php endif; ?>
          <?php if ($canEnd): ?>
          <form method="POST" class="inline" <?= confirmAttrs('End this draw now?', 'Users will no longer be able to enter “'.$draw['title'].'”. You will then select the winner on the next page.', 'End draw', 'warn') ?>>
            <?= csrfField() ?><input type="hidden" name="action" value="end">
            <button class="s-btn s-btn-warn"><?= ico('stop') ?> End draw</button>
          </form>
          <?php endif; ?>
          <?php if ($canCancel): ?>
          <form method="POST" class="inline" <?= confirmAttrs('Cancel this draw?', '“'.$draw['title'].'” will be cancelled, entries will stop and no winner can be selected. This cannot be undone.', 'Cancel draw', 'danger') ?>>
            <?= csrfField() ?><input type="hidden" name="action" value="cancel">
            <button class="s-btn s-btn-danger"><?= ico('x-circle') ?> Cancel draw</button>
          </form>
          <?php endif; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Awaiting winner call to action -->
    <?php if ($awaitingWinner): ?>
    <div class="rounded-2xl p-5 md:p-6 flex flex-col sm:flex-row sm:items-center gap-4 border border-orange-400/30"
         style="background:linear-gradient(120deg,rgba(249,115,22,.12),rgba(124,58,237,.09))">
      <div class="w-11 h-11 rounded-xl bg-orange-500/15 text-orange-400 flex items-center justify-center flex-shrink-0"><?= ico('bolt', 'w-5 h-5') ?></div>
      <div class="flex-1">
        <div class="font-semibold text-base">Awaiting winner</div>
        <div class="text-sm text-slate-300">This draw is closed with <?= number_format($totalEntries) ?> entries. Select the winner to finish it.</div>
      </div>
      <a href="<?= APP_URL ?>/admin/select-winner.php?id=<?= $draw['id'] ?>" class="s-btn s-btn-violet px-5 py-2.5"><?= ico('trophy') ?> Select winner</a>
    </div>
    <?php endif; ?>

    <!-- Entry progress (entry-triggered draws) -->
    <?php if ($isEntries && $target > 0): ?>
    <div class="s-card p-5 md:p-6">
      <div class="flex items-end justify-between gap-3 mb-3">
        <div>
          <div class="s-eyebrow">Entry progress</div>
          <div class="text-2xl font-semibold mt-1 <?= $reached ? 'text-green-400' : '' ?>">
            <?= number_format($totalEntries) ?> <span class="text-slate-500 font-medium text-lg">/ <?= number_format($target) ?> entries</span>
          </div>
        </div>
        <div class="text-sm font-semibold <?= $reached ? 'text-green-400' : 'text-orange-400' ?>"><?= $reached ? 'Target reached' : $pct . '%' ?></div>
      </div>
      <div class="s-progress" style="height:8px"><div class="<?= $reached ? 'done' : '' ?>" style="width:<?= $pct ?>%"></div></div>
      <?php if (!$reached && in_array($draw['status'], ['active','pending'])): ?>
      <div class="text-xs text-slate-500 mt-2.5">
        <?= number_format($target - $totalEntries) ?> more <?= ($target - $totalEntries) === 1 ? 'entry' : 'entries' ?> to trigger the draw.
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Stats -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 md:gap-4">
      <div class="s-card p-4 md:p-5">
        <div class="s-eyebrow">Total entries</div>
        <div class="text-2xl font-semibold mt-1 text-orange-400"><?= number_format($totalEntries) ?></div>
      </div>
      <div class="s-card p-4 md:p-5">
        <div class="s-eyebrow">Participants</div>
        <div class="text-2xl font-semibold mt-1 text-blue-400"><?= number_format($uniqueUsers) ?></div>
      </div>
      <div class="s-card p-4 md:p-5">
        <div class="s-eyebrow">Starts</div>
        <div class="text-sm font-semibold mt-2"><?= date('M j, Y', $startTime) ?></div>
        <div class="text-xs text-slate-500"><?= date('g:i A', $startTime) ?></div>
      </div>
      <?php if ($hasDeadline): ?>
      <div class="s-card p-4 md:p-5">
        <div class="s-eyebrow"><?= $isEntries ? 'Deadline' : 'Ends' ?></div>
        <div class="text-sm font-semibold mt-2"><?= date('M j, Y', $endTime) ?></div>
        <div class="text-xs text-slate-500"><?= date('g:i A', $endTime) ?></div>
      </div>
      <?php elseif ($isEntries): ?>
      <div class="s-card p-4 md:p-5">
        <div class="s-eyebrow">Ends when</div>
        <div class="text-sm font-semibold mt-2">Entry target is reached</div>
        <div class="text-xs text-slate-500"><?= number_format($target) ?> entries</div>
      </div>
      <?php endif; ?>
    </div>

    <!-- Winner -->
    <?php if ($existingWinner): $uc = (string)($existingWinner['user_code'] ?? ''); $wc = (string)($existingWinner['winning_code'] ?? ''); ?>
    <div class="s-card p-5 md:p-6" style="border-color:rgba(234,179,8,.35);background:linear-gradient(135deg,rgba(234,179,8,.06),rgba(0,0,0,0))">
      <div class="flex items-center gap-3 mb-5">
        <div class="w-10 h-10 rounded-xl bg-yellow-500/15 text-yellow-400 flex items-center justify-center"><?= ico('trophy', 'w-5 h-5') ?></div>
        <div>
          <h2 class="font-semibold text-lg text-yellow-400 leading-tight">Winner</h2>
          <div class="text-xs text-slate-400">Announced <?= date('M j, Y g:i A', strtotime($existingWinner['announced_at'])) ?></div>
        </div>
      </div>

      <div class="p-4 rounded-xl flex items-center gap-4 mb-6" style="background:rgba(234,179,8,.07);border:1px solid rgba(234,179,8,.18)">
        <div class="w-11 h-11 bg-yellow-500/20 rounded-full flex items-center justify-center text-lg font-bold text-yellow-400 flex-shrink-0"><?= e($initial($existingWinner['full_name'])) ?></div>
        <div class="flex-1 min-w-0">
          <div class="font-semibold text-yellow-400 truncate"><?= e($existingWinner['full_name']) ?></div>
          <div class="text-sm text-slate-400"><?= e(formatPhone($existingWinner['phone'])) ?></div>
          <?php if (!empty($existingWinner['tiebreaker_used'])): ?>
          <div class="text-xs text-orange-300 mt-1">Tiebreaker: <?= e(str_replace('_',' ', $existingWinner['tiebreaker_used'])) ?></div>
          <?php endif; ?>
        </div>
        <div class="text-right flex-shrink-0">
          <div class="text-xs text-slate-500">User ID</div>
          <div class="font-mono text-sm">#<?= (int)$existingWinner['user_id'] ?></div>
        </div>
      </div>

      <div class="mb-5">
        <div class="s-eyebrow mb-2">Winning code</div>
        <div class="flex flex-wrap gap-1.5">
          <?php for ($i = 0; $i < 15; $i++): $w = $wc[$i] ?? ''; ?>
          <div class="digit-slot <?= ($uc[$i] ?? null) !== null && ($uc[$i] ?? '') === $w ? 'match' : 'no-match' ?>"><?= e($w) ?></div>
          <?php endfor; ?>
        </div>
      </div>

      <?php if ($uc !== ''): ?>
      <div>
        <div class="s-eyebrow mb-2">
          Winner's best code <span class="text-yellow-400 font-semibold ml-1">(<?= (int)$existingWinner['matched_digits'] ?>/15 matched)</span>
        </div>
        <div class="flex flex-wrap gap-1.5">
          <?php for ($i = 0; $i < 15; $i++): ?>
          <div class="digit-slot <?= ($uc[$i] ?? null) !== null && ($uc[$i] ?? '') === ($wc[$i] ?? '') ? 'match' : 'no-match' ?>"><?= e($uc[$i] ?? '?') ?></div>
          <?php endfor; ?>
        </div>
        <div class="text-xs text-slate-500 mt-2"><span class="text-green-400">●</span> matched &nbsp; <span class="text-red-400">●</span> no match</div>
      </div>
      <?php endif; ?>
    </div>

    <!-- Top 3 podium -->
    <?php if ($top3Official): ?>
    <div class="s-card overflow-hidden">
      <div class="s-section-title"><span class="text-slate-400"><?= ico('medal') ?></span> Top 3 finishers</div>
      <div class="grid sm:grid-cols-3 divide-y sm:divide-y-0 sm:divide-x divide-white/5">
        <?php $rs = [1=>['1st','text-yellow-400','bg-yellow-500/15'], 2=>['2nd','text-slate-300','bg-slate-400/15'], 3=>['3rd','text-orange-300','bg-orange-500/15']]; ?>
        <?php foreach ($top3Official as $r): $rsi = $rs[$r['rank_position']] ?? $rs[3]; ?>
        <div class="p-5 text-center">
          <div class="inline-flex px-2.5 py-0.5 rounded-full text-[11px] font-semibold mb-3 <?= $rsi[1] ?> <?= $rsi[2] ?>"><?= $rsi[0] ?></div>
          <div class="font-semibold <?= $rsi[1] ?> text-sm truncate"><?= e($r['full_name']) ?></div>
          <div class="text-xs text-slate-500 mb-2"><?= e(formatPhone($r['phone'])) ?></div>
          <code class="text-xs text-orange-400 block mb-1"><?= e($r['user_code']) ?></code>
          <div class="text-sm font-semibold <?= $r['matched_digits']>=10?'text-green-400':($r['matched_digits']>=5?'text-yellow-400':'text-slate-400') ?>"><?= (int)$r['matched_digits'] ?>/15</div>
          <div class="text-xs text-slate-600 mt-1"><?= e((string)($r['entries_count'] ?? '?')) ?> entries</div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <!-- People + activity -->
    <?php if ($topEntrants || ($recentEntries && $draw['status'] !== 'completed')): ?>
    <div class="grid lg:grid-cols-2 gap-5 items-start">

      <?php if ($topEntrants): ?>
      <div class="s-card overflow-hidden">
        <div class="s-section-title"><span class="text-slate-400"><?= ico('users') ?></span> Top participants</div>
        <div class="divide-y divide-white/5">
          <?php foreach ($topEntrants as $i => $te): ?>
          <div class="flex items-center gap-3 px-5 py-3">
            <div class="w-7 h-7 rounded-lg flex items-center justify-center text-xs font-bold flex-shrink-0
                 <?= $i===0?'bg-yellow-500/15 text-yellow-400':($i===1?'bg-slate-400/15 text-slate-300':'bg-white/5 text-slate-500') ?>"><?= $i+1 ?></div>
            <div class="flex-1 min-w-0">
              <div class="text-sm font-medium truncate"><?= e($te['full_name']) ?></div>
              <div class="text-xs text-slate-500"><?= e(formatPhone($te['phone'])) ?></div>
            </div>
            <div class="text-right flex-shrink-0">
              <div class="font-semibold text-orange-400 text-sm"><?= (int)$te['code_count'] ?></div>
              <div class="text-xs text-slate-600">entries</div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <?php if ($recentEntries && $draw['status'] !== 'completed'): ?>
      <div class="s-card overflow-hidden">
        <div class="s-section-title"><span class="text-slate-400"><?= ico('clock') ?></span> Recent entries</div>
        <div class="divide-y divide-white/5">
          <?php foreach ($recentEntries as $re): ?>
          <div class="flex items-center gap-3 px-5 py-3">
            <div class="w-8 h-8 bg-orange-500/10 rounded-lg flex items-center justify-center font-semibold text-orange-400 text-sm flex-shrink-0"><?= e($initial($re['full_name'])) ?></div>
            <div class="flex-1 min-w-0">
              <div class="text-sm font-medium truncate"><?= e($re['full_name']) ?></div>
              <code class="text-xs text-orange-400"><?= e($re['code']) ?></code>
            </div>
            <div class="text-xs text-slate-500 flex-shrink-0"><?= date('M j, g:ia', strtotime($re['entered_at'])) ?></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

    </div>
    <?php elseif ($totalEntries === 0): ?>
    <div class="s-card p-10 text-center">
      <div class="w-12 h-12 mx-auto mb-3 rounded-full bg-white/5 flex items-center justify-center text-slate-400"><?= ico('inbox', 'w-6 h-6') ?></div>
      <div class="text-sm text-slate-400">No entries yet</div>
    </div>
    <?php endif; ?>

  </div>
</div>


<?php if ($canEdit): ?>
<!-- ══════════════════════════════════════════════════════════
     EDIT DRAW MODAL
══════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="edit-modal">
  <div class="modal-box s-modal" style="max-width:640px;max-height:92vh;overflow-y:auto">
    <div class="flex items-center justify-between mb-4">
      <h3 class="text-lg font-semibold">Edit draw</h3>
      <button type="button" data-close-modal="edit-modal" class="text-slate-400 hover:text-white" aria-label="Close"><?= ico('x', 'w-5 h-5') ?></button>
    </div>

    <div class="flex items-center gap-2 mb-4 flex-wrap">
      <span class="s-pill <?= $isEntries ? 's-pill-purple' : 's-pill-cyan' ?>">
        <?= ico($isEntries ? 'target' : 'clock', 'w-3 h-3') ?> <?= $isEntries ? 'Entry-triggered' : 'Timed' ?>
      </span>
      <span class="text-xs text-slate-500">The draw type can't be changed after creation. Currently <?= number_format($totalEntries) ?> entries.</span>
    </div>

    <?php if ($failed === 'edit' && $err): ?>
    <div class="s-alert s-alert-error mb-4" role="alert"><?= ico('alert', 'w-5 h-5 flex-shrink-0') ?><div><?= e($err) ?></div></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" class="space-y-4">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="edit">

      <div>
        <label class="s-label">Draw title <span class="text-red-400">*</span></label>
        <input type="text" name="title" class="s-input" value="<?= e($old('title', $draw['title'])) ?>" required>
      </div>

      <?php if ($isEntries): ?>
      <div>
        <label class="s-label">Entries needed to trigger the draw <span class="text-red-400">*</span></label>
        <input type="number" name="target_entries" class="s-input" min="2" max="<?= MAX_TARGET_ENTRIES ?>" step="1"
               value="<?= e($old('target_entries', (int)$draw['target_entries'])) ?>" required>
        <div class="s-hint">Must be higher than the current <?= number_format($totalEntries) ?> entries.</div>
      </div>
      <?php endif; ?>

      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="s-label">Category</label>
          <select name="category" class="s-input">
            <option value="">Select…</option>
            <?php foreach ($categories as $c): ?>
            <option value="<?= e($c) ?>" <?= $old('category', $draw['category'] ?? '') === $c ? 'selected' : '' ?>><?= e($c) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="s-label">New banner <span class="text-slate-500 font-normal text-xs">(leave blank to keep)</span></label>
          <input type="file" name="banner_image" class="s-input" accept="image/*">
          <?php if ($draw['banner_image']): ?><div class="s-hint">Current: <?= e(basename($draw['banner_image'])) ?></div><?php endif; ?>
        </div>
      </div>

      <div class="grid grid-cols-2 gap-4">
        <div>
          <label class="s-label">Start date &amp; time <span class="text-red-400">*</span></label>
          <input type="datetime-local" name="start_date" class="s-input" value="<?= e($old('start_date', date('Y-m-d\TH:i', $startTime))) ?>" required>
        </div>
        <div>
          <label class="s-label"><?= $isEntries ? 'Deadline (optional)' : 'End date &amp; time <span class="text-red-400">*</span>' ?></label>
          <input type="datetime-local" name="end_date" class="s-input"
                 value="<?= e($old('end_date', $eDeadline ? date('Y-m-d\TH:i', $endTime) : '')) ?>" <?= $isEntries ? '' : 'required' ?>>
          <?php if ($isEntries): ?><div class="s-hint">Leave blank: only the entry target ends this draw.</div><?php endif; ?>
        </div>
      </div>

      <div><label class="s-label">Description</label><textarea name="description" class="s-input" rows="2"><?= e($old('description', $draw['description'] ?? '')) ?></textarea></div>
      <div><label class="s-label">Prize details</label><textarea name="prize_details" class="s-input" rows="2"><?= e($old('prize_details', $draw['prize_details'] ?? '')) ?></textarea></div>
      <div><label class="s-label">Rules</label><textarea name="rules" class="s-input" rows="2"><?= e($old('rules', $draw['rules'] ?? '')) ?></textarea></div>

      <div class="flex gap-3 pt-1">
        <button type="button" data-close-modal="edit-modal" class="s-btn s-btn-ghost flex-1 py-2.5">Cancel</button>
        <button type="submit" class="s-btn s-btn-primary flex-1 py-2.5"><?= ico('check') ?> Save changes</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<script>window.APP_URL = '<?= APP_URL ?>';</script>
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
<?php if ($failed === 'edit' && $canEdit): ?>
<script>
// Reopen the edit modal after a failed save so nothing the admin typed is lost
setTimeout(function () {
  if (typeof Modal !== 'undefined' && Modal.open) Modal.open('edit-modal');
  else { var el = document.getElementById('edit-modal'); if (el) el.style.display = 'flex'; }
}, 80);
</script>
<?php endif; ?>
<?php if ($draw['status'] === 'active' && $hasDeadline): ?>
<script>
// Live countdown, only for draws that actually have an end date / deadline
(function () {
  const endTime = <?= (int)$endTime * 1000 ?>;
  const el = document.getElementById('countdown-display');
  function tick() {
    const diff = Math.max(0, Math.floor((endTime - Date.now()) / 1000));
    if (diff === 0) { el.textContent = 'Ending now'; return; }
    const d = Math.floor(diff / 86400), h = Math.floor((diff % 86400) / 3600), m = Math.floor((diff % 3600) / 60), s = diff % 60;
    el.textContent = (d > 0 ? d + 'd ' : '') + String(h).padStart(2, '0') + 'h ' + String(m).padStart(2, '0') + 'm ' + String(s).padStart(2, '0') + 's left';
  }
  tick(); setInterval(tick, 1000);
})();
</script>
<?php endif; ?>
</body>
</html>
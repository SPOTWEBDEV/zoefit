<?php
// admin/draws.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
$auth = requireAdmin(); $adminId = $auth['id'];
$db = getDB(); $msg = $err = '';

// ── Pre-load edit draw BEFORE any POST so the modal data
//    is always available regardless of form submissions
$editDraw = null;
if (isset($_GET['edit']) && ($eid = (int)$_GET['edit'])) {
    $s = $db->prepare("SELECT * FROM draws WHERE id=?");
    $s->execute([$eid]);
    $editDraw = $s->fetch() ?: null;
}

// ── POST actions ───────────────────────────────────────────
if (isPost() && verifyCsrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
    $action = $_POST['action'] ?? '';
    $did    = (int)($_POST['draw_id'] ?? 0);

    // ── Create ─────────────────────────────────────────────
    if ($action === 'create') {
        $title = trim($_POST['title']         ?? '');
        $desc  = trim($_POST['description']   ?? '');
        $rules = trim($_POST['rules']         ?? '');
        $prize = trim($_POST['prize_details'] ?? '');
        $cat   = trim($_POST['category']      ?? '');
        $start = $_POST['start_date']         ?? '';
        $end   = $_POST['end_date']            ?? '';

        if (!$title || !$start || !$end) {
            $err = 'Title, start date and end date are required.';
        } elseif (strtotime($end) <= strtotime($start)) {
            $err = 'End date must be after start date.';
        } else {
            $banner = _uploadBanner();
            $db->prepare(
                "INSERT INTO draws
                   (title,description,rules,prize_details,category,banner_image,
                    status,start_date,end_date,created_by)
                 VALUES (?,?,?,?,?,?,'pending',?,?,?)"
            )->execute([$title,$desc,$rules,$prize,$cat,$banner,$start,$end,$adminId]);
            $newId = (int)$db->lastInsertId();
            auditLog('admin',$adminId,'create_draw',"Draw '$title' created",'draw',$newId);
            $msg = "Draw <strong>".e($title)."</strong> created as Pending.";
        }

    // ── Edit ───────────────────────────────────────────────
    } elseif ($action === 'edit' && $did) {
        $chk = $db->prepare("SELECT status FROM draws WHERE id=?");
        $chk->execute([$did]); $status = $chk->fetchColumn();

        if (in_array($status, ['completed','cancelled'])) {
            $err = 'Completed or cancelled draws cannot be edited.';
        } else {
            $title = trim($_POST['title']         ?? '');
            $desc  = trim($_POST['description']   ?? '');
            $rules = trim($_POST['rules']         ?? '');
            $prize = trim($_POST['prize_details'] ?? '');
            $cat   = trim($_POST['category']      ?? '');
            $start = $_POST['start_date']         ?? '';
            $end   = $_POST['end_date']            ?? '';

            if (!$title || !$start || !$end) {
                $err = 'Title, start date and end date are required.';
            } elseif (strtotime($end) <= strtotime($start)) {
                $err = 'End date must be after start date.';
            } else {
                $banner = _uploadBanner();
                if ($banner) {
                    $db->prepare(
                        "UPDATE draws SET title=?,description=?,rules=?,prize_details=?,
                         category=?,banner_image=?,start_date=?,end_date=?,updated_at=NOW()
                         WHERE id=?"
                    )->execute([$title,$desc,$rules,$prize,$cat,$banner,$start,$end,$did]);
                } else {
                    $db->prepare(
                        "UPDATE draws SET title=?,description=?,rules=?,prize_details=?,
                         category=?,start_date=?,end_date=?,updated_at=NOW()
                         WHERE id=?"
                    )->execute([$title,$desc,$rules,$prize,$cat,$start,$end,$did]);
                }
                auditLog('admin',$adminId,'edit_draw',"Draw #$did updated",'draw',$did);
                $msg     = 'Draw updated successfully.';
                $editDraw = null; // clear so modal does not re-open
            }
        }

    // ── Activate ───────────────────────────────────────────
    } elseif ($action === 'activate' && $did) {
        $db->prepare(
            "UPDATE draws SET status='active',activated_by=?,activated_at=NOW(),updated_at=NOW()
             WHERE id=? AND status='pending'"
        )->execute([$adminId,$did]);
        auditLog('admin',$adminId,'activate_draw',"Draw #$did manually activated",'draw',$did);
        $msg = 'Draw activated. Users can now enter.';

    // ── End draw ───────────────────────────────────────────
    } elseif ($action === 'end' && $did) {
        $db->prepare(
            "UPDATE draws SET status='ended',ended_by=?,ended_at=NOW(),updated_at=NOW()
             WHERE id=? AND status='active'"
        )->execute([$adminId,$did]);
        auditLog('admin',$adminId,'end_draw',"Draw #$did manually ended",'draw',$did);
        $msg = 'Draw ended. <a href="'.APP_URL.'/admin/select-winner.php?id='.$did.'" class="underline font-semibold">→ Select Winner Now</a>';

    // ── Cancel ─────────────────────────────────────────────
    } elseif ($action === 'cancel' && $did) {
        $db->prepare(
            "UPDATE draws SET status='cancelled',updated_at=NOW()
             WHERE id=? AND status NOT IN('completed','cancelled')"
        )->execute([$did]);
        auditLog('admin',$adminId,'cancel_draw',"Draw #$did cancelled",'draw',$did);
        $msg = 'Draw cancelled.';

    // ── Delete (pending only) ──────────────────────────────
    } elseif ($action === 'delete' && $did) {
        $db->prepare("DELETE FROM draws WHERE id=? AND status='pending'")->execute([$did]);
        auditLog('admin',$adminId,'delete_draw',"Draw #$did deleted",'draw',$did);
        $msg = 'Draw deleted.';
    }
}

// ── Load draws list ────────────────────────────────────────
$tab = $_GET['tab'] ?? 'all';
if (!in_array($tab, ['all','pending','active','ended','completed','cancelled'])) $tab = 'all';
$where  = $tab !== 'all' ? "status=?" : "1=1";
$params = $tab !== 'all' ? [$tab]     : [];

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

// ── Banner upload helper ───────────────────────────────────
function _uploadBanner(): ?string {
    if (empty($_FILES['banner_image']['name'])) return null;
    $ext = strtolower(pathinfo($_FILES['banner_image']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','webp','gif'])) return null;
    $name = 'banner-'.uniqid().'.'.$ext;
    move_uploaded_file($_FILES['banner_image']['tmp_name'], UPLOAD_PATH.$name);
    return $name;
}

$aPage = 'draws';
$categories = ['Daily Patronage','Government Ticket','Transport','Dashboard Loyalty','Vendor Campaign','Grand Prize'];
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="csrf-token" content="<?= generateCsrf() ?>">
  <title>Draws — <?= APP_NAME ?> Admin</title>
  <script src="<?= APP_URL ?>/assets/js/tailwind.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
  <style>*{font-family:'Poppins',sans-serif!important}</style>
</head>
<body class="bg-[#0a0f1a] text-white">
<?php include __DIR__ . '/../components/admin-sidebar.php'; ?>

<div class="main-content">
  <div class="topbar">
    <button onclick="toggleSidebar()" class="md:hidden text-gray-400 text-2xl mr-3">☰</button>
    <h1 class="text-xl font-bold">Draw Management</h1>
    <button onclick="Modal.open('create-modal')" class="btn btn-primary btn-sm">+ Create Draw</button>
  </div>

  <div class="p-4 md:p-6">

    <!-- Flash messages -->
    <?php if ($msg): ?>
    <div class="bg-green-500/10 border border-green-500/30 text-green-400 rounded-xl p-4 mb-5 text-sm">
      ✅ <?= $msg ?>
    </div>
    <?php endif; ?>
    <?php if ($err): ?>
    <div class="bg-red-500/10 border border-red-500/30 text-red-400 rounded-xl p-4 mb-5 text-sm">
      ❌ <?= e($err) ?>
    </div>
    <?php endif; ?>

    <!-- Status flow guide -->
    <div class="rounded-xl p-4 mb-5 text-xs leading-relaxed"
         style="background:rgba(6,182,212,.05);border:1px solid rgba(6,182,212,.12)">
      <strong class="text-cyan-400">Draw Flow:</strong>
      <span class="text-yellow-400">Pending</span> →
      <span class="text-green-400">Active</span>
      <span class="text-gray-500">(admin activates or cron auto-starts)</span> →
      <span class="text-orange-400">Ended</span>
      <span class="text-gray-500">(admin ends or cron auto-ends)</span> →
      <span class="text-purple-400">Winner Selected</span>
      <span class="text-gray-500">(admin enters machine number)</span> →
      <span class="text-gray-400">Completed</span>
    </div>

    <!-- Tabs -->
    <div class="flex gap-2 mb-5 flex-wrap">
      <a href="?tab=all" class="btn btn-sm <?= $tab==='all'?'btn-primary':'btn-secondary' ?>">
        All <span class="text-xs ml-1 text-gray-400">(<?= array_sum($tabCounts) ?>)</span>
      </a>
      <?php $tabMeta=['pending'=>['⏳','text-yellow-400'],'active'=>['🟢','text-green-400'],'ended'=>['🔴','text-orange-400'],'completed'=>['✅','text-gray-400'],'cancelled'=>['❌','text-red-400']]; ?>
      <?php foreach ($tabMeta as $t=>[$icon,$col]): ?>
      <a href="?tab=<?= $t ?>" class="btn btn-sm <?= $tab===$t?'btn-primary':'btn-secondary' ?> flex items-center gap-1.5">
        <?= $icon ?> <span class="<?= $col ?>"><?= ucfirst($t) ?></span>
        <?php if (($tabCounts[$t]??0) > 0): ?>
        <span class="<?= $tab===$t?'bg-white/25':'bg-orange-500' ?> text-white text-xs rounded-full w-5 h-5 flex items-center justify-center font-bold">
          <?= $tabCounts[$t] ?>
        </span>
        <?php endif; ?>
      </a>
      <?php endforeach; ?>
    </div>

    <!-- Draws list -->
    <div class="space-y-4">
      <?php if (!$draws): ?>
      <div class="card p-12 text-center">
        <div class="text-5xl mb-4">🎯</div>
        <div class="text-gray-500 mb-3">No draws found.</div>
        <button onclick="Modal.open('create-modal')" class="btn btn-primary">+ Create First Draw</button>
      </div>
      <?php endif; ?>

      <?php foreach ($draws as $d): ?>
      <div class="card p-5">
        <div class="flex items-start gap-4">

          <!-- Banner thumb -->
          <div class="w-20 h-16 rounded-xl flex-shrink-0 overflow-hidden flex items-center justify-center text-3xl flex-shrink-0"
               style="background:linear-gradient(135deg,#1a2235,#0d1118)">
            <?php if ($d['banner_image'] && file_exists(UPLOAD_PATH.$d['banner_image'])): ?>
            <img src="<?= APP_URL ?>/uploads/<?= e($d['banner_image']) ?>"
                 class="w-full h-full object-cover" alt="">
            <?php else: ?>🎯<?php endif; ?>
          </div>

          <div class="flex-1 min-w-0">
            <!-- Title + badges -->
            <div class="flex items-center gap-2 flex-wrap mb-1">
              <h3 class="font-bold text-base"><?= e($d['title']) ?></h3>
              <span class="badge <?= match($d['status']) {
                'active'    => 'badge-success',
                'pending'   => 'badge-warning',
                'ended'     => 'badge-danger',
                'completed' => 'badge-muted',
                'cancelled' => 'badge-danger',
                default     => 'badge-muted'
              } ?>"><?= ucfirst($d['status']) ?></span>
              <?php if ($d['category']): ?>
              <span class="badge badge-info text-xs"><?= e($d['category']) ?></span>
              <?php endif; ?>
              <?php if ($d['status']==='active'): ?>
              <span class="flex items-center gap-1 text-xs text-green-400">
                <span class="pulse-dot w-2 h-2"></span> Live
              </span>
              <?php endif; ?>
              <?php if ($d['status']==='ended' && !$d['winner_user_id']): ?>
              <span class="text-xs text-orange-400 font-semibold animate-pulse">⚡ Awaiting winner</span>
              <?php endif; ?>
            </div>

            <!-- Meta -->
            <div class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-400 mb-3">
              <span>📝 <?= number_format((int)$d['entries']) ?> entries</span>
              <span>📅 Start: <?= date('M j, Y g:i A', strtotime($d['start_date'])) ?></span>
              <span>⏰ End: <?= date('M j, Y g:i A', strtotime($d['end_date'])) ?></span>
              <?php if ($d['winner_user_id']): ?>
              <span class="text-yellow-400">🏆 Winner selected</span>
              <?php endif; ?>
              <?php if ($d['status']==='active'):
                $diff = strtotime($d['end_date']) - time();
                if ($diff > 0) { $dd=floor($diff/86400);$hh=floor(($diff%86400)/3600);$mm=floor(($diff%3600)/60);
                echo '<span class="text-cyan-400">⏱ '.($dd>0?"{$dd}d ":"")."{$hh}h {$mm}m remaining</span>"; }
              endif; ?>
            </div>

            <!-- Actions -->
            <div class="flex gap-2 flex-wrap">

              <!-- View details — always -->
              <a href="<?= APP_URL ?>/admin/draw-manage.php?id=<?= $d['id'] ?>"
                 class="btn btn-sm btn-secondary text-xs">📊 Details</a>

              <?php if ($d['status'] === 'pending'): ?>
                <!-- Activate -->
                <form method="POST" style="display:inline"
                      onsubmit="return confirm('Activate this draw now? Users will be able to enter immediately.')">
                  <?= csrfField() ?>
                  <input type="hidden" name="action"  value="activate">
                  <input type="hidden" name="draw_id" value="<?= $d['id'] ?>">
                  <button class="btn btn-sm text-xs text-white" style="background:#22c55e">▶ Activate</button>
                </form>
                <!-- Edit -->
                <a href="?tab=<?= urlencode($tab) ?>&edit=<?= $d['id'] ?>"
                   class="btn btn-sm btn-secondary text-xs">✏️ Edit</a>
                <!-- Delete -->
                <form method="POST" style="display:inline"
                      onsubmit="return confirm('Delete this draw permanently? This cannot be undone.')">
                  <?= csrfField() ?>
                  <input type="hidden" name="action"  value="delete">
                  <input type="hidden" name="draw_id" value="<?= $d['id'] ?>">
                  <button class="btn btn-sm btn-secondary text-xs text-red-400">🗑 Delete</button>
                </form>

              <?php elseif ($d['status'] === 'active'): ?>
                <!-- Edit while active -->
                <a href="?tab=<?= urlencode($tab) ?>&edit=<?= $d['id'] ?>"
                   class="btn btn-sm btn-secondary text-xs">✏️ Edit</a>
                <!-- End draw -->
                <form method="POST" style="display:inline"
                      onsubmit="return confirm('End this draw now?\n\nUsers will no longer be able to enter. You will then need to select the winner on the next page.')">
                  <?= csrfField() ?>
                  <input type="hidden" name="action"  value="end">
                  <input type="hidden" name="draw_id" value="<?= $d['id'] ?>">
                  <button class="btn btn-sm btn-secondary text-xs text-orange-400">⏹ End Draw</button>
                </form>
                <!-- Cancel -->
                <form method="POST" style="display:inline"
                      onsubmit="return confirm('Cancel this draw? This cannot be undone.')">
                  <?= csrfField() ?>
                  <input type="hidden" name="action"  value="cancel">
                  <input type="hidden" name="draw_id" value="<?= $d['id'] ?>">
                  <button class="btn btn-sm btn-secondary text-xs text-red-400">✕ Cancel</button>
                </form>

              <?php elseif ($d['status'] === 'ended'): ?>
                <?php if (!$d['winner_user_id']): ?>
                <a href="<?= APP_URL ?>/admin/select-winner.php?id=<?= $d['id'] ?>"
                   class="btn btn-sm text-xs text-white font-bold"
                   style="background:linear-gradient(135deg,#7c3aed,#6d28d9)">
                  🏆 Select Winner
                </a>
                <?php else: ?>
                <a href="<?= APP_URL ?>/admin/winners.php?draw=<?= $d['id'] ?>"
                   class="btn btn-sm btn-secondary text-xs text-yellow-400">
                  🏆 View Winner
                </a>
                <?php endif; ?>

              <?php elseif ($d['status'] === 'completed'): ?>
                <a href="<?= APP_URL ?>/admin/winners.php?draw=<?= $d['id'] ?>"
                   class="btn btn-sm btn-secondary text-xs text-yellow-400">
                  🏆 View Result
                </a>
              <?php endif; ?>

            </div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

  </div><!-- /p-4 -->
</div><!-- /main-content -->


<!-- ══════════════════════════════════════════════════════════
     CREATE DRAW MODAL
══════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="create-modal">
  <div class="modal-box" style="max-width:620px;max-height:92vh;overflow-y:auto">
    <div class="flex items-center justify-between mb-4">
      <h3 class="text-xl font-bold">Create New Draw</h3>
      <button data-close-modal="create-modal" class="text-gray-400 hover:text-white text-xl">✕</button>
    </div>
    <p class="text-gray-400 text-sm mb-5">
      Draw starts as <span class="text-yellow-400 font-semibold">Pending</span>.
      Activate it manually or let the cron auto-activate at the start date.
    </p>
    <form method="POST" enctype="multipart/form-data">
      <?= csrfField() ?><input type="hidden" name="action" value="create">
      <div class="grid grid-cols-2 gap-4">
        <div class="col-span-2 form-group">
          <label class="form-label">Title <span class="text-red-400">*</span></label>
          <input type="text" name="title" class="form-control" required autofocus>
        </div>
        <div class="form-group">
          <label class="form-label">Category</label>
          <select name="category" class="form-control">
            <option value="">Select…</option>
            <?php foreach(['Daily Patronage','Government Ticket','Transport','Dashboard Loyalty','Vendor Campaign','Grand Prize'] as $c): ?>
            <option value="<?= $c ?>"><?= $c ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Banner Image</label>
          <input type="file" name="banner_image" class="form-control" accept="image/*">
        </div>
        <div class="form-group">
          <label class="form-label">Start Date &amp; Time <span class="text-red-400">*</span></label>
          <input type="datetime-local" name="start_date" class="form-control" required>
          <div class="text-xs text-gray-500 mt-1">Cron auto-activates at this time if still pending</div>
        </div>
        <div class="form-group">
          <label class="form-label">End Date &amp; Time <span class="text-red-400">*</span></label>
          <input type="datetime-local" name="end_date" class="form-control" required>
          <div class="text-xs text-gray-500 mt-1">Cron auto-ends at this time if still active</div>
        </div>
        <div class="col-span-2 form-group">
          <label class="form-label">Description</label>
          <textarea name="description" class="form-control" rows="2"></textarea>
        </div>
        <div class="col-span-2 form-group">
          <label class="form-label">Prize Details</label>
          <textarea name="prize_details" class="form-control" rows="2"></textarea>
        </div>
        <div class="col-span-2 form-group">
          <label class="form-label">Rules</label>
          <textarea name="rules" class="form-control" rows="2"></textarea>
        </div>
      </div>
      <div class="flex gap-3 mt-2">
        <button type="button" data-close-modal="create-draw-modal" class="btn btn-secondary flex-1">Cancel</button>
        <button type="submit" class="btn btn-primary flex-1">🎯 Create Draw</button>
      </div>
    </form>
  </div>
</div>


<!-- ══════════════════════════════════════════════════════════
     EDIT DRAW MODAL
     Always rendered — JS opens it when ?edit= is present.
══════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="edit-modal">
  <div class="modal-box" style="max-width:620px;max-height:92vh;overflow-y:auto">
    <div class="flex items-center justify-between mb-4">
      <h3 class="text-xl font-bold">Edit Draw</h3>
      <a href="?tab=<?= urlencode($tab) ?>" class="text-gray-400 hover:text-white text-xl">✕</a>
    </div>

    <?php if ($editDraw): ?>
    <form method="POST" enctype="multipart/form-data">
      <?= csrfField() ?>
      <input type="hidden" name="action"  value="edit">
      <input type="hidden" name="draw_id" value="<?= $editDraw['id'] ?>">

      <!-- Title -->
      <div class="form-group">
        <label class="form-label">Draw Title <span class="text-red-400">*</span></label>
        <input type="text" name="title" class="form-control"
               value="<?= e($editDraw['title']) ?>" required>
      </div>

      <!-- Category + Banner side-by-side -->
      <div class="grid grid-cols-2 gap-4">
        <div class="form-group">
          <label class="form-label">Category</label>
          <select name="category" class="form-control">
            <option value="">Select…</option>
            <?php foreach ($categories as $c): ?>
            <option value="<?= e($c) ?>" <?= ($editDraw['category']??'')===$c?'selected':'' ?>>
              <?= e($c) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">New Banner <span class="text-gray-500 font-normal text-xs">(leave blank to keep)</span></label>
          <input type="file" name="banner_image" class="form-control" accept="image/*">
          <?php if ($editDraw['banner_image']): ?>
          <div class="text-xs text-gray-500 mt-1">
            Current: <?= e(basename($editDraw['banner_image'])) ?>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Dates -->
      <div class="grid grid-cols-2 gap-4">
        <div class="form-group">
          <label class="form-label">Start Date &amp; Time <span class="text-red-400">*</span></label>
          <input type="datetime-local" name="start_date" class="form-control"
                 value="<?= date('Y-m-d\TH:i', strtotime($editDraw['start_date'])) ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label">End Date &amp; Time <span class="text-red-400">*</span></label>
          <input type="datetime-local" name="end_date" class="form-control"
                 value="<?= date('Y-m-d\TH:i', strtotime($editDraw['end_date'])) ?>" required>
        </div>
      </div>

      <!-- Description / Prize / Rules -->
      <div class="form-group">
        <label class="form-label">Description</label>
        <textarea name="description" class="form-control" rows="2"><?= e($editDraw['description'] ?? '') ?></textarea>
      </div>
      <div class="form-group">
        <label class="form-label">Prize Details</label>
        <textarea name="prize_details" class="form-control" rows="2"><?= e($editDraw['prize_details'] ?? '') ?></textarea>
      </div>
      <div class="form-group">
        <label class="form-label">Rules</label>
        <textarea name="rules" class="form-control" rows="2"><?= e($editDraw['rules'] ?? '') ?></textarea>
      </div>

      <div class="flex gap-3 mt-4">
        <a href="?tab=<?= urlencode($tab) ?>" class="btn btn-secondary flex-1 text-center">Cancel</a>
        <button type="submit" class="btn btn-primary flex-1">💾 Save Changes</button>
      </div>
    </form>

    <?php else: ?>
    <!-- No draw selected — shouldn't normally be visible -->
    <div class="p-8 text-center text-gray-500">
      <div class="text-3xl mb-2">❓</div>
      No draw selected. <a href="?tab=<?= urlencode($tab) ?>" class="text-orange-400 underline">Go back</a>
    </div>
    <?php endif; ?>

  </div>
</div>


<script src="<?= APP_URL ?>/assets/js/app.js"></script>
<script>
// ── Auto-open the edit modal when ?edit= is present in URL ──
// This runs AFTER app.js loads so Modal is defined.
(function () {
  const params = new URLSearchParams(window.location.search);
  if (params.has('edit') && params.get('edit')) {
    // Small delay to ensure Modal utility from app.js is ready
    setTimeout(function () {
      if (typeof Modal !== 'undefined' && Modal.open) {
        Modal.open('edit-modal');
      } else {
        // Fallback: show overlay directly
        var el = document.getElementById('edit-modal');
        if (el) el.style.display = 'flex';
      }
    }, 80);
  }
})();
</script>
</body>
</html>
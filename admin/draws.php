<?php
// admin/draws.php
// Admin can: create, edit, delete (pending only), cancel.
// Admin CANNOT: pause, activate, resume — all handled by cron.
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
$auth = requireAdmin(); $adminId = $auth['id'];
$db = getDB(); $msg = $err = '';

if (isPost() && verifyCsrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
    $action = $_POST['action'] ?? '';

    // ── Create or Edit ─────────────────────────────────────
    if ($action === 'create' || $action === 'edit') {
        $title  = trim($_POST['title']         ?? '');
        $desc   = trim($_POST['description']   ?? '');
        $rules  = trim($_POST['rules']         ?? '');
        $prize  = trim($_POST['prize_details'] ?? '');
        $cat    = trim($_POST['category']      ?? '');
        $start  = $_POST['start_date']         ?? '';
        $end    = $_POST['end_date']            ?? '';

        if (!$title || !$start || !$end) {
            $err = 'Title and dates are required.';
        } elseif (strtotime($end) <= strtotime($start)) {
            $err = 'End date must be after start date.';
        } else {
            // Banner upload
            $banner = null;
            if (!empty($_FILES['banner_image']['name'])) {
                $ext = strtolower(pathinfo($_FILES['banner_image']['name'], PATHINFO_EXTENSION));
                if (in_array($ext, ['jpg','jpeg','png','webp','gif'])) {
                    $banner = 'banner-'.uniqid().'.'.$ext;
                    move_uploaded_file($_FILES['banner_image']['tmp_name'], UPLOAD_PATH.$banner);
                }
            }

            if ($action === 'create') {
                // Always create as 'pending' — cron activates it when start_date arrives
                $db->prepare(
                    "INSERT INTO draws
                       (title,description,rules,prize_details,category,banner_image,
                        status,start_date,end_date,created_by)
                     VALUES (?,?,?,?,?,?,'pending',?,?,?)"
                )->execute([$title,$desc,$rules,$prize,$cat,$banner,$start,$end,$adminId]);
                $did = $db->lastInsertId();
                auditLog('admin',$adminId,'create_draw',"Draw '$title' created",'draw',$did);
                $msg = "Draw '<strong>".e($title)."</strong>' created. It will activate automatically when its start date arrives.";

            } else {
                $did = (int)($_POST['draw_id'] ?? 0);

                // Only allow editing draws that haven't completed or been cancelled
                $chk = $db->prepare("SELECT status FROM draws WHERE id=?");
                $chk->execute([$did]); $chk = $chk->fetchColumn();
                if (in_array($chk, ['completed','cancelled'])) {
                    $err = 'Completed or cancelled draws cannot be edited.';
                } else {
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
                    auditLog('admin',$adminId,'edit_draw',"Draw $did updated",'draw',$did);
                    $msg = "Draw updated.";
                }
            }
        }

    // ── Cancel (admin may cancel any non-completed draw) ───
    } elseif ($action === 'cancel' && ($did = (int)($_POST['draw_id'] ?? 0))) {
        $db->prepare("UPDATE draws SET status='cancelled',updated_at=NOW() WHERE id=? AND status NOT IN('completed')")
           ->execute([$did]);
        auditLog('admin',$adminId,'cancel_draw',"Draw $did cancelled",'draw',$did);
        $msg = 'Draw cancelled.';

    // ── Delete (pending only) ──────────────────────────────
    } elseif ($action === 'delete' && ($did = (int)($_POST['draw_id'] ?? 0))) {
        $db->prepare("DELETE FROM draws WHERE id=? AND status='pending'")->execute([$did]);
        auditLog('admin',$adminId,'delete_draw',"Draw $did deleted",'draw',$did);
        $msg = 'Draw deleted.';
    }
}

// ── Load draws ─────────────────────────────────────────────
$tab   = $_GET['tab'] ?? 'all';
$allowed = ['all','active','pending','completed','cancelled'];
if (!in_array($tab, $allowed)) $tab = 'all';

$where  = '1=1';
$params = [];
if ($tab !== 'all') { $where = 'status=?'; $params[] = $tab; }

$draws = $db->prepare(
    "SELECT d.*, (SELECT COUNT(*) FROM draw_entries WHERE draw_id=d.id) AS entries
     FROM draws d WHERE $where ORDER BY d.created_at DESC"
);
$draws->execute($params);
$draws = $draws->fetchAll();

// Tab counts
$tabCounts = [];
foreach (['active','pending','completed','cancelled'] as $t) {
    $tabCounts[$t] = (int)$db->query("SELECT COUNT(*) FROM draws WHERE status='$t'")->fetchColumn();
}

// For edit modal: load draw if ?edit=ID
$editDraw = null;
if (isset($_GET['edit']) && ($eid = (int)$_GET['edit'])) {
    $s = $db->prepare("SELECT * FROM draws WHERE id=?");
    $s->execute([$eid]); $editDraw = $s->fetch();
}

$aPage = 'draws';
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
    <button onclick="Modal.open('create-draw-modal')" class="btn btn-primary btn-sm">+ Create Draw</button>
  </div>

  <div class="p-4 md:p-6">

    <?php if ($msg): ?>
    <div class="bg-green-500/10 border border-green-500/30 text-green-400 rounded-xl p-4 mb-5 text-sm"><?= $msg ?></div>
    <?php endif; ?>
    <?php if ($err): ?>
    <div class="bg-red-500/10 border border-red-500/30 text-red-400 rounded-xl p-4 mb-5 text-sm"><?= e($err) ?></div>
    <?php endif; ?>

    <!-- Cron notice -->
    <div class="rounded-xl p-4 mb-5 flex items-start gap-3 text-sm"
         style="background:rgba(6,182,212,.06);border:1px solid rgba(6,182,212,.18)">
      <span class="text-cyan-400 text-lg flex-shrink-0">⚙️</span>
      <div class="text-gray-300">
        <strong class="text-cyan-400">Fully Automatic Draw System.</strong>
        Draws activate and finalize on schedule via cron job — no manual intervention needed.
        Admin role here is to <strong class="text-white">create, edit, and monitor</strong> draws only.
      </div>
    </div>

    <!-- Tabs -->
    <div class="flex gap-2 mb-5 flex-wrap">
      <a href="?tab=all" class="btn btn-sm <?= $tab==='all'?'btn-primary':'btn-secondary' ?>">All</a>
      <?php $tabMeta = ['active'=>['🟢','badge-success'],'pending'=>['⏳','badge-warning'],'completed'=>['✅','badge-muted'],'cancelled'=>['❌','badge-danger']]; ?>
      <?php foreach ($tabMeta as $t=>[$icon,$_]): ?>
      <a href="?tab=<?= $t ?>" class="btn btn-sm <?= $tab===$t?'btn-primary':'btn-secondary' ?> flex items-center gap-1.5">
        <?= $icon ?> <?= ucfirst($t) ?>
        <?php if ($tabCounts[$t] > 0): ?>
        <span class="<?= $tab===$t?'bg-white/30':'bg-orange-500' ?> text-white text-xs rounded-full w-5 h-5 flex items-center justify-center font-bold">
          <?= $tabCounts[$t] ?>
        </span>
        <?php endif; ?>
      </a>
      <?php endforeach; ?>
    </div>

    <!-- Draw list -->
    <div class="space-y-4">
      <?php foreach ($draws as $d): ?>
      <div class="card p-5">
        <div class="flex items-start gap-4">

          <!-- Banner thumb -->
          <div class="w-20 h-16 rounded-xl flex-shrink-0 overflow-hidden flex items-center justify-center text-3xl"
               style="background:linear-gradient(135deg,#1a2235,#0d1118)">
            <?php if ($d['banner_image'] && file_exists(UPLOAD_PATH.$d['banner_image'])): ?>
            <img src="<?= APP_URL ?>/uploads/<?= e($d['banner_image']) ?>" class="w-full h-full object-cover" alt="">
            <?php else: ?>🎯<?php endif; ?>
          </div>

          <div class="flex-1 min-w-0">
            <div class="flex items-start gap-2 flex-wrap mb-1">
              <h3 class="font-bold"><?= e($d['title']) ?></h3>
              <span class="badge <?= match($d['status']) {
                'active'    => 'badge-success',
                'pending'   => 'badge-warning',
                'completed' => 'badge-muted',
                'cancelled' => 'badge-danger',
                default     => 'badge-muted'
              } ?>"><?= ucfirst($d['status']) ?></span>
              <?php if ($d['category']): ?>
              <span class="badge badge-info"><?= e($d['category']) ?></span>
              <?php endif; ?>
              <?php if ($d['status'] === 'active'): ?>
              <span class="flex items-center gap-1 text-xs text-green-400">
                <span class="pulse-dot w-2 h-2"></span> Running
              </span>
              <?php endif; ?>
            </div>

            <div class="flex flex-wrap gap-x-5 gap-y-1 text-xs text-gray-400 mb-3">
              <span>📝 <?= number_format($d['entries']) ?> entries</span>
              <span>📅 <?= date('M j, Y g:i A', strtotime($d['start_date'])) ?></span>
              <span>⏰ Ends <?= date('M j, Y g:i A', strtotime($d['end_date'])) ?></span>
              <?php if ($d['winner_user_id']): ?>
              <span class="text-yellow-400">🏆 Winner announced</span>
              <?php endif; ?>
              <?php
              // Show countdown for active draws
              if ($d['status'] === 'active') {
                $diff = strtotime($d['end_date']) - time();
                if ($diff > 0) {
                  $days  = floor($diff/86400);
                  $hours = floor(($diff%86400)/3600);
                  $mins  = floor(($diff%3600)/60);
                  echo '<span class="text-cyan-400">⏱ '
                     . ($days > 0 ? "{$days}d " : '')
                     . "{$hours}h {$mins}m remaining</span>";
                }
              }
              // Pending — show time until auto-activation
              if ($d['status'] === 'pending') {
                $diff = strtotime($d['start_date']) - time();
                if ($diff > 0) {
                  $days  = floor($diff/86400);
                  $hours = floor(($diff%86400)/3600);
                  echo '<span class="text-yellow-400">🕐 Activates in '
                     . ($days > 0 ? "{$days}d " : '')
                     . "{$hours}h</span>";
                }
              }
              ?>
            </div>

            <!-- Action buttons — NO pause/activate/resume -->
            <div class="flex gap-2 flex-wrap">
              <a href="<?= APP_URL ?>/admin/draw-manage.php?id=<?= $d['id'] ?>"
                 class="btn btn-sm btn-secondary text-xs">
                <?= $d['status'] === 'completed' ? '🏆 View Result' : '📊 View Details' ?>
              </a>

              <?php if (in_array($d['status'], ['pending','active'])): ?>
              <a href="?tab=<?= $tab ?>&edit=<?= $d['id'] ?>"
                 class="btn btn-sm btn-secondary text-xs">✏️ Edit</a>
              <?php endif; ?>

              <?php if ($d['status'] === 'pending'): ?>
              <form method="POST" onsubmit="return confirm('Delete this draw permanently?')">
                <?= csrfField() ?>
                <input type="hidden" name="action"  value="delete">
                <input type="hidden" name="draw_id" value="<?= $d['id'] ?>">
                <button class="btn btn-sm btn-secondary text-xs text-red-400">🗑 Delete</button>
              </form>
              <?php endif; ?>

              <?php if (in_array($d['status'], ['pending','active'])): ?>
              <form method="POST" onsubmit="return confirm('Cancel this draw? This cannot be undone.')">
                <?= csrfField() ?>
                <input type="hidden" name="action"  value="cancel">
                <input type="hidden" name="draw_id" value="<?= $d['id'] ?>">
                <button class="btn btn-sm btn-secondary text-xs text-orange-400">✕ Cancel</button>
              </form>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>

      <?php if (!$draws): ?>
      <div class="card p-12 text-center">
        <div class="text-5xl mb-4">🎯</div>
        <div class="text-gray-500 mb-3">No draws found.</div>
        <button onclick="Modal.open('create-draw-modal')" class="btn btn-primary">+ Create First Draw</button>
      </div>
      <?php endif; ?>
    </div>

  </div>
</div>

<!-- ── CREATE DRAW MODAL ──────────────────────────────────── -->
<div class="modal-overlay" id="create-draw-modal">
  <div class="modal-box" style="max-width:620px;max-height:92vh;overflow-y:auto">
    <h3 class="text-xl font-bold mb-2">Create New Draw</h3>
    <p class="text-gray-400 text-sm mb-5">
      The draw will activate automatically when its <strong class="text-white">start date</strong> arrives
      and finalize automatically when the <strong class="text-white">end date</strong> passes.
    </p>
    <form method="POST" enctype="multipart/form-data">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="create">
      <div class="grid grid-cols-2 gap-4">
        <div class="col-span-2 form-group">
          <label class="form-label">Draw Title <span class="text-red-400">*</span></label>
          <input type="text" name="title" class="form-control" required autofocus>
        </div>
        <div class="form-group">
          <label class="form-label">Category</label>
          <select name="category" class="form-control">
            <option value="">Select…</option>
            <?php foreach (['Daily Patronage','Government Ticket','Transport','Dashboard Loyalty','Vendor Campaign','Grand Prize'] as $c): ?>
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
          <div class="text-xs text-gray-500 mt-1">Cron will auto-activate at this time</div>
        </div>
        <div class="form-group">
          <label class="form-label">End Date &amp; Time <span class="text-red-400">*</span></label>
          <input type="datetime-local" name="end_date" class="form-control" required>
          <div class="text-xs text-gray-500 mt-1">Cron will auto-finalize at this time</div>
        </div>
        <div class="col-span-2 form-group">
          <label class="form-label">Description</label>
          <textarea name="description" class="form-control" rows="3"></textarea>
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
      <div class="bg-cyan-500/8 border border-cyan-500/15 rounded-xl p-3 mb-4 text-xs text-cyan-300">
        ℹ️ Status is managed automatically. The draw starts as <strong>Pending</strong> and becomes
        <strong>Active</strong> → <strong>Completed</strong> via the cron job.
      </div>
      <div class="flex gap-3">
        <button type="button" data-close-modal="create-draw-modal" class="btn btn-secondary flex-1">Cancel</button>
        <button type="submit" class="btn btn-primary flex-1">🎯 Create Draw</button>
      </div>
    </form>
  </div>
</div>

<!-- ── EDIT DRAW MODAL (opened via ?edit=ID) ─────────────── -->
<?php if ($editDraw): ?>
<div class="modal-overlay" id="edit-draw-modal" style="display:flex">
  <div class="modal-box" style="max-width:620px;max-height:92vh;overflow-y:auto">
    <h3 class="text-xl font-bold mb-2">Edit Draw</h3>
    <p class="text-gray-400 text-sm mb-5">Editing a live draw only affects description/prizes — dates and title changes take effect immediately.</p>
    <form method="POST" enctype="multipart/form-data">
      <?= csrfField() ?>
      <input type="hidden" name="action"  value="edit">
      <input type="hidden" name="draw_id" value="<?= $editDraw['id'] ?>">
      <div class="grid grid-cols-2 gap-4">
        <div class="col-span-2 form-group">
          <label class="form-label">Draw Title <span class="text-red-400">*</span></label>
          <input type="text" name="title" class="form-control" value="<?= e($editDraw['title']) ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label">Category</label>
          <select name="category" class="form-control">
            <option value="">Select…</option>
            <?php foreach (['Daily Patronage','Government Ticket','Transport','Dashboard Loyalty','Vendor Campaign','Grand Prize'] as $c): ?>
            <option value="<?= $c ?>" <?= $editDraw['category']===$c?'selected':'' ?>><?= $c ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">New Banner Image <span class="text-gray-500 font-normal">(leave blank to keep)</span></label>
          <input type="file" name="banner_image" class="form-control" accept="image/*">
        </div>
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
        <div class="col-span-2 form-group">
          <label class="form-label">Description</label>
          <textarea name="description" class="form-control" rows="3"><?= e($editDraw['description'] ?? '') ?></textarea>
        </div>
        <div class="col-span-2 form-group">
          <label class="form-label">Prize Details</label>
          <textarea name="prize_details" class="form-control" rows="2"><?= e($editDraw['prize_details'] ?? '') ?></textarea>
        </div>
        <div class="col-span-2 form-group">
          <label class="form-label">Rules</label>
          <textarea name="rules" class="form-control" rows="2"><?= e($editDraw['rules'] ?? '') ?></textarea>
        </div>
      </div>
      <div class="flex gap-3 mt-2">
        <a href="?tab=<?= $tab ?>" class="btn btn-secondary flex-1 text-center">Cancel</a>
        <button type="submit" class="btn btn-primary flex-1">💾 Save Changes</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<script>
  window.APP_URL = '<?= APP_URL ?>';
</script>
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
</body>
</html>
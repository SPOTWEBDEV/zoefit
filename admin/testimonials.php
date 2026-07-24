<?php
// admin/testimonials.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
$auth = requireAdmin(); $adminId = $auth['id'];
$db = getDB(); $msg = $err = '';

// ── Pre-load edit testimonial BEFORE any POST so the modal data
//    is always available regardless of form submissions
$editT = null;
if (isset($_GET['edit']) && ($eid = (int)$_GET['edit'])) {
    $s = $db->prepare("SELECT * FROM testimonials WHERE id=?");
    $s->execute([$eid]);
    $editT = $s->fetch() ?: null;
}

// ── POST actions ───────────────────────────────────────────
if (isPost() && verifyCsrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
    $action = $_POST['action'] ?? '';
    $tid    = (int)($_POST['testimonial_id'] ?? 0);

    // ── Create ─────────────────────────────────────────────
    if ($action === 'create') {
        $name    = trim($_POST['full_name']  ?? '');
        $role    = trim($_POST['role_title'] ?? '');
        $content = trim($_POST['content']    ?? '');
        $rating  = (int)($_POST['rating']    ?? 5);
        $status  = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';
        $order   = (int)($_POST['display_order'] ?? 0);

        if ($rating < 1 || $rating > 5) $rating = 5;

        if (!$name || !$content) {
            $err = 'Full name and testimonial content are required.';
        } else {
            $photo = _uploadPhoto();
            $db->prepare(
                "INSERT INTO testimonials
                   (full_name,role_title,photo,rating,content,status,display_order,created_by)
                 VALUES (?,?,?,?,?,?,?,?)"
            )->execute([$name,$role,$photo,$rating,$content,$status,$order,$adminId]);
            $newId = (int)$db->lastInsertId();
            auditLog('admin',$adminId,'create_testimonial',"Testimonial by '$name' created",'testimonial',$newId);
            $msg = "Testimonial from <strong>".e($name)."</strong> added.";
        }

    // ── Edit ───────────────────────────────────────────────
    } elseif ($action === 'edit' && $tid) {
        $name    = trim($_POST['full_name']  ?? '');
        $role    = trim($_POST['role_title'] ?? '');
        $content = trim($_POST['content']    ?? '');
        $rating  = (int)($_POST['rating']    ?? 5);
        $status  = ($_POST['status'] ?? 'active') === 'inactive' ? 'inactive' : 'active';
        $order   = (int)($_POST['display_order'] ?? 0);

        if ($rating < 1 || $rating > 5) $rating = 5;

        if (!$name || !$content) {
            $err = 'Full name and testimonial content are required.';
        } else {
            $photo = _uploadPhoto();
            if ($photo) {
                $db->prepare(
                    "UPDATE testimonials SET full_name=?,role_title=?,photo=?,rating=?,
                     content=?,status=?,display_order=?,updated_at=NOW() WHERE id=?"
                )->execute([$name,$role,$photo,$rating,$content,$status,$order,$tid]);
            } else {
                $db->prepare(
                    "UPDATE testimonials SET full_name=?,role_title=?,rating=?,
                     content=?,status=?,display_order=?,updated_at=NOW() WHERE id=?"
                )->execute([$name,$role,$rating,$content,$status,$order,$tid]);
            }
            auditLog('admin',$adminId,'edit_testimonial',"Testimonial #$tid updated",'testimonial',$tid);
            $msg   = 'Testimonial updated successfully.';
            $editT = null; // clear so modal does not re-open
        }

    // ── Toggle status (active/inactive) ─────────────────────
    } elseif ($action === 'toggle_status' && $tid) {
        $chk = $db->prepare("SELECT status FROM testimonials WHERE id=?");
        $chk->execute([$tid]); $cur = $chk->fetchColumn();
        $new = $cur === 'active' ? 'inactive' : 'active';
        $db->prepare("UPDATE testimonials SET status=?,updated_at=NOW() WHERE id=?")
           ->execute([$new,$tid]);
        auditLog('admin',$adminId,'toggle_testimonial',"Testimonial #$tid set to $new",'testimonial',$tid);
        $msg = 'Testimonial is now '.($new === 'active' ? 'visible' : 'hidden').'.';

    // ── Delete ───────────────────────────────────────────────
    } elseif ($action === 'delete' && $tid) {
        $db->prepare("DELETE FROM testimonials WHERE id=?")->execute([$tid]);
        auditLog('admin',$adminId,'delete_testimonial',"Testimonial #$tid deleted",'testimonial',$tid);
        $msg = 'Testimonial deleted.';
    }
}

// ── Load testimonials list ────────────────────────────────
$tab = $_GET['tab'] ?? 'all';
if (!in_array($tab, ['all','active','inactive'])) $tab = 'all';
$where  = $tab !== 'all' ? "status=?" : "1=1";
$params = $tab !== 'all' ? [$tab]     : [];

$stmt = $db->prepare(
    "SELECT * FROM testimonials WHERE $where ORDER BY display_order ASC, created_at DESC"
);
$stmt->execute($params);
$testimonials = $stmt->fetchAll();

$tabCounts = [];
foreach (['active','inactive'] as $t)
    $tabCounts[$t] = (int)$db->query("SELECT COUNT(*) FROM testimonials WHERE status='$t'")->fetchColumn();

// ── Photo upload helper ───────────────────────────────────
function _uploadPhoto(): ?string {
    if (empty($_FILES['photo']['name'])) return null;
    $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','webp','gif'])) return null;
    $name = 'testimonial-'.uniqid().'.'.$ext;
    move_uploaded_file($_FILES['photo']['tmp_name'], UPLOAD_PATH.$name);
    return $name;
}

$aPage = 'testimonials';
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="csrf-token" content="<?= generateCsrf() ?>">
  <title>Testimonials — <?= APP_NAME ?> Admin</title>
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
    <h1 class="text-xl font-bold">Testimonials</h1>
    <button onclick="Modal.open('create-modal')" class="btn btn-primary btn-sm">+ Add Testimonial</button>
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

    <!-- Tabs -->
    <div class="flex gap-2 mb-5 flex-wrap">
      <a href="?tab=all" class="btn btn-sm <?= $tab==='all'?'btn-primary':'btn-secondary' ?>">
        All <span class="text-xs ml-1 text-gray-400">(<?= array_sum($tabCounts) ?>)</span>
      </a>
      <?php $tabMeta=['active'=>['🟢','text-green-400'],'inactive'=>['⚪','text-gray-400']]; ?>
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

    <!-- Testimonials list -->
    <div class="space-y-4">
      <?php if (!$testimonials): ?>
      <div class="card p-12 text-center">
        <div class="text-5xl mb-4">💬</div>
        <div class="text-gray-500 mb-3">No testimonials found.</div>
        <button onclick="Modal.open('create-modal')" class="btn btn-primary">+ Add First Testimonial</button>
      </div>
      <?php endif; ?>

      <?php foreach ($testimonials as $t): ?>
      <div class="card p-5">
        <div class="flex items-start gap-4">

          <!-- Photo thumb -->
          <div class="w-16 h-16 rounded-full flex-shrink-0 overflow-hidden flex items-center justify-center text-2xl"
               style="background:linear-gradient(135deg,#1a2235,#0d1118)">
            <?php if ($t['photo'] && file_exists(UPLOAD_PATH.$t['photo'])): ?>
            <img src="<?= APP_URL ?>/uploads/<?= e($t['photo']) ?>"
                 class="w-full h-full object-cover" alt="">
            <?php else: ?>🙂<?php endif; ?>
          </div>

          <div class="flex-1 min-w-0">
            <!-- Name + badges -->
            <div class="flex items-center gap-2 flex-wrap mb-1">
              <h3 class="font-bold text-base"><?= e($t['full_name']) ?></h3>
              <span class="badge <?= $t['status']==='active'?'badge-success':'badge-muted' ?>">
                <?= ucfirst($t['status']) ?>
              </span>
              <span class="text-yellow-400 text-sm">
                <?= str_repeat('★', (int)$t['rating']).str_repeat('☆', 5-(int)$t['rating']) ?>
              </span>
            </div>

            <?php if ($t['role_title']): ?>
            <div class="text-xs text-gray-400 mb-2"><?= e($t['role_title']) ?></div>
            <?php endif; ?>

            <!-- Content -->
            <p class="text-sm text-gray-300 mb-3"><?= nl2br(e($t['content'])) ?></p>

            <div class="text-xs text-gray-500 mb-3">
              Order: <?= (int)$t['display_order'] ?> · Added <?= date('M j, Y', strtotime($t['created_at'])) ?>
            </div>

            <!-- Actions -->
            <div class="flex gap-2 flex-wrap">
              <a href="?tab=<?= urlencode($tab) ?>&edit=<?= $t['id'] ?>"
                 class="btn btn-sm btn-secondary text-xs">✏️ Edit</a>

              <form method="POST" style="display:inline">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="toggle_status">
                <input type="hidden" name="testimonial_id" value="<?= $t['id'] ?>">
                <button class="btn btn-sm btn-secondary text-xs <?= $t['status']==='active'?'text-gray-400':'text-green-400' ?>">
                  <?= $t['status']==='active' ? '🙈 Hide' : '👁 Show' ?>
                </button>
              </form>

              <form method="POST" style="display:inline"
                    onsubmit="return confirm('Delete this testimonial permanently? This cannot be undone.')">
                <?= csrfField() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="testimonial_id" value="<?= $t['id'] ?>">
                <button class="btn btn-sm btn-secondary text-xs text-red-400">🗑 Delete</button>
              </form>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

  </div><!-- /p-4 -->
</div><!-- /main-content -->


<!-- ══════════════════════════════════════════════════════════
     CREATE TESTIMONIAL MODAL
══════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="create-modal">
  <div class="modal-box" style="max-width:560px;max-height:92vh;overflow-y:auto">
    <div class="flex items-center justify-between mb-4">
      <h3 class="text-xl font-bold">Add Testimonial</h3>
      <button data-close-modal="create-modal" class="text-gray-400 hover:text-white text-xl">✕</button>
    </div>
    <form method="POST" enctype="multipart/form-data">
      <?= csrfField() ?><input type="hidden" name="action" value="create">
      <div class="grid grid-cols-2 gap-4">
        <div class="col-span-2 form-group">
          <label class="form-label">Full Name <span class="text-red-400">*</span></label>
          <input type="text" name="full_name" class="form-control" required autofocus>
        </div>
        <div class="form-group">
          <label class="form-label">Role / Location</label>
          <input type="text" name="role_title" class="form-control" placeholder="e.g. Verified User, Lagos">
        </div>
        <div class="form-group">
          <label class="form-label">Photo</label>
          <input type="file" name="photo" class="form-control" accept="image/*">
        </div>
        <div class="form-group">
          <label class="form-label">Rating</label>
          <select name="rating" class="form-control">
            <?php for ($i=5;$i>=1;$i--): ?>
            <option value="<?= $i ?>" <?= $i===5?'selected':'' ?>><?= str_repeat('★',$i) ?> (<?= $i ?>)</option>
            <?php endfor; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Status</label>
          <select name="status" class="form-control">
            <option value="active">Active (visible)</option>
            <option value="inactive">Inactive (hidden)</option>
          </select>
        </div>
        <div class="col-span-2 form-group">
          <label class="form-label">Display Order</label>
          <input type="number" name="display_order" class="form-control" value="0" min="0">
          <div class="text-xs text-gray-500 mt-1">Lower numbers show first.</div>
        </div>
        <div class="col-span-2 form-group">
          <label class="form-label">Testimonial <span class="text-red-400">*</span></label>
          <textarea name="content" class="form-control" rows="4" required></textarea>
        </div>
      </div>
      <div class="flex gap-3 mt-2">
        <button type="button" data-close-modal="create-modal" class="btn btn-secondary flex-1">Cancel</button>
        <button type="submit" class="btn btn-primary flex-1">💬 Add Testimonial</button>
      </div>
    </form>
  </div>
</div>


<!-- ══════════════════════════════════════════════════════════
     EDIT TESTIMONIAL MODAL
     Always rendered — JS opens it when ?edit= is present.
══════════════════════════════════════════════════════════ -->
<div class="modal-overlay" id="edit-modal">
  <div class="modal-box" style="max-width:560px;max-height:92vh;overflow-y:auto">
    <div class="flex items-center justify-between mb-4">
      <h3 class="text-xl font-bold">Edit Testimonial</h3>
      <a href="?tab=<?= urlencode($tab) ?>" class="text-gray-400 hover:text-white text-xl">✕</a>
    </div>

    <?php if ($editT): ?>
    <form method="POST" enctype="multipart/form-data">
      <?= csrfField() ?>
      <input type="hidden" name="action"         value="edit">
      <input type="hidden" name="testimonial_id" value="<?= $editT['id'] ?>">

      <div class="grid grid-cols-2 gap-4">
        <div class="col-span-2 form-group">
          <label class="form-label">Full Name <span class="text-red-400">*</span></label>
          <input type="text" name="full_name" class="form-control"
                 value="<?= e($editT['full_name']) ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label">Role / Location</label>
          <input type="text" name="role_title" class="form-control"
                 value="<?= e($editT['role_title'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">New Photo <span class="text-gray-500 font-normal text-xs">(leave blank to keep)</span></label>
          <input type="file" name="photo" class="form-control" accept="image/*">
          <?php if ($editT['photo']): ?>
          <div class="text-xs text-gray-500 mt-1">Current: <?= e(basename($editT['photo'])) ?></div>
          <?php endif; ?>
        </div>
        <div class="form-group">
          <label class="form-label">Rating</label>
          <select name="rating" class="form-control">
            <?php for ($i=5;$i>=1;$i--): ?>
            <option value="<?= $i ?>" <?= (int)$editT['rating']===$i?'selected':'' ?>><?= str_repeat('★',$i) ?> (<?= $i ?>)</option>
            <?php endfor; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Status</label>
          <select name="status" class="form-control">
            <option value="active"   <?= $editT['status']==='active'?'selected':'' ?>>Active (visible)</option>
            <option value="inactive" <?= $editT['status']==='inactive'?'selected':'' ?>>Inactive (hidden)</option>
          </select>
        </div>
        <div class="col-span-2 form-group">
          <label class="form-label">Display Order</label>
          <input type="number" name="display_order" class="form-control"
                 value="<?= (int)$editT['display_order'] ?>" min="0">
        </div>
        <div class="col-span-2 form-group">
          <label class="form-label">Testimonial <span class="text-red-400">*</span></label>
          <textarea name="content" class="form-control" rows="4" required><?= e($editT['content']) ?></textarea>
        </div>
      </div>

      <div class="flex gap-3 mt-4">
        <a href="?tab=<?= urlencode($tab) ?>" class="btn btn-secondary flex-1 text-center">Cancel</a>
        <button type="submit" class="btn btn-primary flex-1">💾 Save Changes</button>
      </div>
    </form>

    <?php else: ?>
    <div class="p-8 text-center text-gray-500">
      <div class="text-3xl mb-2">❓</div>
      No testimonial selected. <a href="?tab=<?= urlencode($tab) ?>" class="text-orange-400 underline">Go back</a>
    </div>
    <?php endif; ?>

  </div>
</div>


<script src="<?= APP_URL ?>/assets/js/app.js"></script>
<script>
// ── Auto-open the edit modal when ?edit= is present in URL ──
(function () {
  const params = new URLSearchParams(window.location.search);
  if (params.has('edit') && params.get('edit')) {
    setTimeout(function () {
      if (typeof Modal !== 'undefined' && Modal.open) {
        Modal.open('edit-modal');
      } else {
        var el = document.getElementById('edit-modal');
        if (el) el.style.display = 'flex';
      }
    }, 80);
  }
})();
</script>
</body>
</html>
<?php
// Super admin slides page — redirects to admin slides with super session check
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
$auth = requireSuperAdmin(); $saId = $auth['id'];
// Also set admin session so the slides page works
startAppSession();
$_SESSION['admin_id']   = $saId;
$_SESSION['admin_name'] = $_SESSION['super_admin_name']??'Super Admin';
// Forward to admin slides
$_SERVER['QUERY_STRING'] = $_SERVER['QUERY_STRING']??'';
$saPage = 'slides';
$db = getDB(); $msg=$err='';

if (isPost() && verifyCsrf($_POST[CSRF_TOKEN_NAME]??'')) {
  $action=$_POST['action']??'';
  if ($action==='add') {
    $title=trim($_POST['title']??'');
    $sort=(int)($_POST['sort_order']??0);
    $link=trim($_POST['link_url']??'');
    if (!empty($_FILES['image']['name'])) {
      $ext=strtolower(pathinfo($_FILES['image']['name'],PATHINFO_EXTENSION));
      if (in_array($ext,['jpg','jpeg','png','webp','gif'])) {
        $fname='slide-'.uniqid().'.'.$ext;
        if(move_uploaded_file($_FILES['image']['tmp_name'],UPLOAD_PATH.$fname)) {
          $db->prepare("INSERT INTO slides (title,image_path,link_url,sort_order,status,created_by) VALUES (?,?,?,?,'active',?)")
             ->execute([$title,$fname,$link,$sort,$saId]);
          auditLog('super_admin',$saId,'add_slide',"Slide added: $title");
          $msg="Slide added.";
        } else { $err="Upload failed."; }
      } else { $err="Invalid file type."; }
    } else { $err="Image required."; }
  } elseif ($action==='toggle'&&($sid=(int)($_POST['slide_id']??0))) {
    $db->prepare("UPDATE slides SET status=IF(status='active','inactive','active') WHERE id=?")->execute([$sid]);
    $msg="Toggled.";
  } elseif ($action==='delete'&&($sid=(int)($_POST['slide_id']??0))) {
    $s=$db->prepare("SELECT image_path FROM slides WHERE id=?");$s->execute([$sid]);$s=$s->fetch();
    if ($s&&file_exists(UPLOAD_PATH.$s['image_path'])) unlink(UPLOAD_PATH.$s['image_path']);
    $db->prepare("DELETE FROM slides WHERE id=?")->execute([$sid]);
    $msg="Slide deleted.";
  } elseif ($action==='reorder') {
    $ids=array_map('intval',$_POST['ids']??[]);
    foreach ($ids as $i=>$id) {
      $db->prepare("UPDATE slides SET sort_order=? WHERE id=?")->execute([$i,$id]);
    }
    jsonResponse(['success'=>true]);
  }
}

$slides=$db->query("SELECT * FROM slides ORDER BY sort_order ASC, created_at DESC")->fetchAll();
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="csrf-token" content="<?= generateCsrf() ?>">
  <title>Banners — <?= APP_NAME ?> Super Admin</title>
  <script>
  window.APP_URL = '<?= APP_URL ?>';
</script>
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
  <style>*{font-family:'Poppins',sans-serif!important}</style>
</head>
<body class="bg-[#060b12] text-white">
<?php include __DIR__ . '/../components/super-sidebar.php'; ?>
<div class="main-content">
  <div class="topbar" style="background:#0a0f1a">
    <button onclick="toggleSidebar()" class="md:hidden text-gray-400 text-2xl mr-3">☰</button>
    <h1 class="text-xl font-bold">Banners &amp; Slides</h1>
    <button onclick="Modal.open('add-slide-modal')" class="btn btn-primary btn-sm">+ Add Banner</button>
  </div>
  <div class="p-6">
    <?php if($msg): ?><div class="bg-green-500/10 border border-green-500/30 text-green-400 rounded-xl p-4 mb-5 text-sm"><?= e($msg) ?></div><?php endif; ?>
    <?php if($err): ?><div class="bg-red-500/10 border border-red-500/30 text-red-400 rounded-xl p-4 mb-5 text-sm"><?= e($err) ?></div><?php endif; ?>

    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
      <?php foreach($slides as $s): ?>
      <div class="card overflow-hidden">
        <?php if(file_exists(UPLOAD_PATH.$s['image_path'])): ?>
        <img src="<?= APP_URL ?>/uploads/<?= e($s['image_path']) ?>" class="w-full h-40 object-cover" alt="">
        <?php else: ?><div class="w-full h-40 bg-gray-800 flex items-center justify-center text-gray-600 text-sm">Image missing</div><?php endif; ?>
        <div class="p-4">
          <div class="flex items-center justify-between mb-2">
            <div class="font-semibold text-sm"><?= e($s['title']??'Untitled') ?></div>
            <span class="badge <?= $s['status']==='active'?'badge-success':'badge-muted' ?>"><?= $s['status'] ?></span>
          </div>
          <div class="text-xs text-gray-500 mb-3">Sort: <?= $s['sort_order'] ?> · ID: <?= $s['id'] ?></div>
          <div class="flex gap-2">
            <form method="POST" class="flex-1"><?= csrfField() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="slide_id" value="<?= $s['id'] ?>"><button class="btn btn-sm btn-secondary w-full text-xs"><?= $s['status']==='active'?'Deactivate':'Activate' ?></button></form>
            <form method="POST" onsubmit="return confirm('Delete?')"><?= csrfField() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="slide_id" value="<?= $s['id'] ?>"><button class="btn btn-sm btn-danger text-xs">🗑</button></form>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
      <?php if(!$slides): ?><div class="col-span-3 card p-12 text-center text-gray-500">No banners. <button onclick="Modal.open('add-slide-modal')" class="text-orange-400 underline">Add one</button></div><?php endif; ?>
    </div>
  </div>
</div>
<div class="modal-overlay" id="add-slide-modal">
  <div class="modal-box">
    <h3 class="text-xl font-bold mb-5">Add Banner</h3>
    <form method="POST" enctype="multipart/form-data">
      <?= csrfField() ?><input type="hidden" name="action" value="add">
      <div class="form-group"><label class="form-label">Title</label><input type="text" name="title" class="form-control" placeholder="Campaign name"></div>
      <div class="form-group"><label class="form-label">Image *</label><input type="file" name="image" class="form-control" accept="image/*" required></div>
      <div class="form-group"><label class="form-label">Link URL</label><input type="url" name="link_url" class="form-control" placeholder="https://…"></div>
      <div class="form-group"><label class="form-label">Sort Order</label><input type="number" name="sort_order" class="form-control" value="0"></div>
      <div class="flex gap-3">
        <button type="button" data-close-modal="add-slide-modal" class="btn btn-secondary flex-1">Cancel</button>
        <button type="submit" class="btn btn-primary flex-1">Upload</button>
      </div>
    </form>
  </div>
</div>
<script>
  window.APP_URL = '<?= APP_URL ?>';
</script>
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
</body></html>

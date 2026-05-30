<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
$auth = requireAdmin(); $adminId = $auth['id'];
$db = getDB(); $msg=$err='';

if (isPost() && verifyCsrf($_POST[CSRF_TOKEN_NAME]??'')) {
  $action = $_POST['action']??'';

  if ($action==='create'||$action==='edit') {
    $title   = trim($_POST['title']??'');
    $desc    = trim($_POST['description']??'');
    $rules   = trim($_POST['rules']??'');
    $prize   = trim($_POST['prize_details']??'');
    $cat     = trim($_POST['category']??'');
    $start   = $_POST['start_date']??'';
    $end     = $_POST['end_date']??'';
    $status  = $_POST['status']??'pending';

    if (!$title||!$start||!$end) { $err='Title and dates required.'; }
    else {
      // Handle banner upload
      $banner = null;
      if (!empty($_FILES['banner_image']['name'])) {
        $ext=strtolower(pathinfo($_FILES['banner_image']['name'],PATHINFO_EXTENSION));
        if (in_array($ext,['jpg','jpeg','png','webp','gif'])) {
          $banner='banner-'.uniqid().'.'.$ext;
          move_uploaded_file($_FILES['banner_image']['tmp_name'],UPLOAD_PATH.$banner);
        }
      }

      if ($action==='create') {
        $db->prepare("INSERT INTO draws (title,description,rules,prize_details,category,banner_image,status,start_date,end_date,created_by) VALUES (?,?,?,?,?,?,?,?,?,?)")
           ->execute([$title,$desc,$rules,$prize,$cat,$banner,$status,$start,$end,$adminId]);
        $did=$db->lastInsertId();
        auditLog('admin',$adminId,'create_draw',"Draw '$title' created",'draw',$did);
        $msg="Draw '$title' created.";
      } else {
        $did=(int)($_POST['draw_id']??0);
        $setSql = $banner ? "title=?,description=?,rules=?,prize_details=?,category=?,banner_image=?,status=?,start_date=?,end_date=? WHERE id=?"
                          : "title=?,description=?,rules=?,prize_details=?,category=?,status=?,start_date=?,end_date=? WHERE id=?";
        $params = $banner
          ? [$title,$desc,$rules,$prize,$cat,$banner,$status,$start,$end,$did]
          : [$title,$desc,$rules,$prize,$cat,$status,$start,$end,$did];
        $db->prepare("UPDATE draws SET $setSql")->execute($params);
        auditLog('admin',$adminId,'edit_draw',"Draw $did updated",'draw',$did);
        $msg="Draw updated.";
      }
    }
  } elseif (in_array($action,['pause','activate','cancel']) && ($did=(int)($_POST['draw_id']??0))) {
    $map=['pause'=>'paused','activate'=>'active','cancel'=>'cancelled'];
    $db->prepare("UPDATE draws SET status=? WHERE id=?")->execute([$map[$action],$did]);
    auditLog('admin',$adminId,$action.'_draw',"Draw $did $action-d",'draw',$did);
    $msg="Draw status updated.";
  } elseif ($action==='delete' && ($did=(int)($_POST['draw_id']??0))) {
    $db->prepare("DELETE FROM draws WHERE id=? AND status='pending'")->execute([$did]);
    auditLog('admin',$adminId,'delete_draw',"Draw $did deleted",'draw',$did);
    $msg="Draw deleted.";
  }
}

$tab = $_GET['tab']??'all';
$where='1=1'; if($tab!=='all') { $where="status=?"; }
$draws=$db->prepare("SELECT d.*,(SELECT COUNT(*) FROM draw_entries WHERE draw_id=d.id) as entries FROM draws d WHERE $where ORDER BY d.created_at DESC");
$draws->execute($tab!=='all'?[$tab]:[]);$draws=$draws->fetchAll();

$aPage='draws';
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="csrf-token" content="<?= generateCsrf() ?>">
  <title>Draws — <?= APP_NAME ?> Admin</title>
  <script src="<?= APP_URL ?>/assets/js/app.js"></script>
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
  <div class="p-6">
    <?php if($msg): ?><div class="bg-green-500/10 border border-green-500/30 text-green-400 rounded-xl p-4 mb-5 text-sm"><?= e($msg) ?></div><?php endif; ?>
    <?php if($err): ?><div class="bg-red-500/10 border border-red-500/30 text-red-400 rounded-xl p-4 mb-5 text-sm"><?= e($err) ?></div><?php endif; ?>

    <!-- Tabs -->
    <div class="flex gap-2 mb-5 flex-wrap">
      <?php foreach(['all'=>'All','active'=>'🟢 Active','pending'=>'⏳ Pending','paused'=>'⏸ Paused','completed'=>'✅ Completed','cancelled'=>'❌ Cancelled'] as $v=>$l): ?>
      <a href="?tab=<?= $v ?>" class="btn btn-sm <?= $tab===$v?'btn-primary':'btn-secondary' ?>"><?= $l ?></a>
      <?php endforeach; ?>
    </div>

    <div class="space-y-4">
      <?php foreach($draws as $d): ?>
      <div class="card p-5">
        <div class="flex items-start gap-5">
          <div class="w-20 h-16 rounded-xl flex items-center justify-center text-3xl flex-shrink-0" style="background:linear-gradient(135deg,#1a2235,#0d1118)">
            <?php if($d['banner_image']&&file_exists(UPLOAD_PATH.$d['banner_image'])): ?><img src="<?= APP_URL ?>/uploads/<?= e($d['banner_image']) ?>" class="w-full h-full object-cover rounded-xl" alt=""><?php else: ?>🎯<?php endif; ?>
          </div>
          <div class="flex-1 min-w-0">
            <div class="flex items-start gap-3 flex-wrap">
              <h3 class="font-bold"><?= e($d['title']) ?></h3>
              <span class="badge <?= match($d['status']){'active'=>'badge-success','pending'=>'badge-warning','paused'=>'badge-info','completed'=>'badge-muted','cancelled'=>'badge-danger',default=>'badge-muted'} ?>">
                <?= ucfirst($d['status']) ?>
              </span>
              <?php if($d['category']): ?><span class="badge badge-info"><?= e($d['category']) ?></span><?php endif; ?>
            </div>
            <div class="flex flex-wrap gap-x-5 gap-y-1 mt-2 text-xs text-gray-400">
              <span>📝 <?= $d['entries'] ?> entries</span>
              <span>📅 <?= date('M j, Y', strtotime($d['start_date'])) ?> – <?= date('M j, Y', strtotime($d['end_date'])) ?></span>
              <?php if($d['winner_user_id']): ?><span class="text-yellow-400">🏆 Winner announced</span><?php endif; ?>
            </div>
            <!-- Action buttons -->
            <div class="flex gap-2 mt-3 flex-wrap">
              <a href="<?= APP_URL ?>/admin/draw-manage.php?id=<?= $d['id'] ?>" class="btn btn-sm btn-secondary text-xs">⚙️ Manage</a>
              <?php if($d['status']==='active'): ?>
                <a href="<?= APP_URL ?>/admin/live-draw-admin.php?id=<?= $d['id'] ?>" class="btn btn-sm btn-primary text-xs">🔴 Live Draw</a>
                <form method="POST" class="inline"><?= csrfField() ?><input type="hidden" name="action" value="pause"><input type="hidden" name="draw_id" value="<?= $d['id'] ?>"><button class="btn btn-sm btn-secondary text-xs">⏸ Pause</button></form>
              <?php elseif($d['status']==='pending'): ?>
                <form method="POST" class="inline"><?= csrfField() ?><input type="hidden" name="action" value="activate"><input type="hidden" name="draw_id" value="<?= $d['id'] ?>"><button class="btn btn-sm btn-success text-xs">▶ Activate</button></form>
                <form method="POST" class="inline" onsubmit="return confirm('Delete this draw?')"><?= csrfField() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="draw_id" value="<?= $d['id'] ?>"><button class="btn btn-sm btn-danger text-xs">🗑 Delete</button></form>
              <?php elseif($d['status']==='paused'): ?>
                <form method="POST" class="inline"><?= csrfField() ?><input type="hidden" name="action" value="activate"><input type="hidden" name="draw_id" value="<?= $d['id'] ?>"><button class="btn btn-sm btn-success text-xs">▶ Resume</button></form>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
      <?php if(!$draws): ?>
      <div class="card p-12 text-center"><div class="text-gray-500">No draws found. <button onclick="Modal.open('create-draw-modal')" class="text-orange-400 underline">Create one →</button></div></div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Create Draw Modal -->
<div class="modal-overlay" id="create-draw-modal">
  <div class="modal-box" style="max-width:600px;max-height:90vh;overflow-y:auto">
    <h3 class="text-xl font-bold mb-5">Create New Draw</h3>
    <form method="POST" enctype="multipart/form-data">
      <?= csrfField() ?><input type="hidden" name="action" value="create">
      <div class="grid grid-cols-2 gap-4">
        <div class="col-span-2 form-group"><label class="form-label">Draw Title</label><input type="text" name="title" class="form-control" required></div>
        <div class="form-group"><label class="form-label">Category</label>
          <select name="category" class="form-control">
            <option value="">Select…</option>
            <?php foreach(['Daily Patronage','Government Ticket','Transport','Dashboard Loyalty','Vendor Campaign','Grand Prize'] as $c): ?>
            <option value="<?= $c ?>"><?= $c ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group"><label class="form-label">Status</label>
          <select name="status" class="form-control">
            <option value="pending">Pending</option><option value="active">Active</option>
          </select>
        </div>
        <div class="form-group"><label class="form-label">Start Date</label><input type="datetime-local" name="start_date" class="form-control" required></div>
        <div class="form-group"><label class="form-label">End Date</label><input type="datetime-local" name="end_date" class="form-control" required></div>
        <div class="col-span-2 form-group"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="3"></textarea></div>
        <div class="col-span-2 form-group"><label class="form-label">Prize Details</label><textarea name="prize_details" class="form-control" rows="2"></textarea></div>
        <div class="col-span-2 form-group"><label class="form-label">Rules</label><textarea name="rules" class="form-control" rows="2"></textarea></div>
        <div class="col-span-2 form-group"><label class="form-label">Banner Image</label><input type="file" name="banner_image" class="form-control" accept="image/*"></div>
      </div>
      <div class="flex gap-3 mt-2">
        <button type="button" data-close-modal="create-draw-modal" class="btn btn-secondary flex-1">Cancel</button>
        <button type="submit" class="btn btn-primary flex-1">Create Draw</button>
      </div>
    </form>
  </div>
</div>
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
</body></html>

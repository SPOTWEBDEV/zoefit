<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
$auth = requireAdmin(); $adminId = $auth['id'];
$drawId = (int)($_GET['id']??0);
if (!$drawId) redirect(APP_URL.'/admin/draws.php');
$db = getDB();
$stmt = $db->prepare("SELECT * FROM draws WHERE id=?"); $stmt->execute([$drawId]); $draw = $stmt->fetch();
if (!$draw) redirect(APP_URL.'/admin/draws.php');

$msg = $err = '';

if (isPost() && verifyCsrf($_POST[CSRF_TOKEN_NAME]??'')) {
  $action = $_POST['action'] ?? '';
  if ($action === 'update') {
    $title  = trim($_POST['title']??'');
    $desc   = trim($_POST['description']??'');
    $rules  = trim($_POST['rules']??'');
    $prize  = trim($_POST['prize_details']??'');
    $cat    = trim($_POST['category']??'');
    $start  = $_POST['start_date']??'';
    $end    = $_POST['end_date']??'';
    $status = $_POST['status']??'pending';
    if (!$title||!$start||!$end) { $err='Title and dates required.'; }
    else {
      $banner = $draw['banner_image'];
      if (!empty($_FILES['banner_image']['name'])) {
        $ext = strtolower(pathinfo($_FILES['banner_image']['name'], PATHINFO_EXTENSION));
        if (in_array($ext,['jpg','jpeg','png','webp','gif'])) {
          $newFile = 'banner-'.uniqid().'.'.$ext;
          if (move_uploaded_file($_FILES['banner_image']['tmp_name'], UPLOAD_PATH.$newFile)) {
            if ($banner && file_exists(UPLOAD_PATH.$banner)) unlink(UPLOAD_PATH.$banner);
            $banner = $newFile;
          }
        }
      }
      $db->prepare("UPDATE draws SET title=?,description=?,rules=?,prize_details=?,category=?,banner_image=?,status=?,start_date=?,end_date=?,updated_at=NOW() WHERE id=?")
         ->execute([$title,$desc,$rules,$prize,$cat,$banner,$status,$start,$end,$drawId]);
      auditLog('admin',$adminId,'edit_draw',"Draw $drawId updated",'draw',$drawId);
      $msg = 'Draw updated successfully.';
      $stmt=$db->prepare("SELECT * FROM draws WHERE id=?");$stmt->execute([$drawId]);$draw=$stmt->fetch();
    }
  }
}

// Stats
$entryCount = $db->prepare("SELECT COUNT(*) FROM draw_entries WHERE draw_id=?"); $entryCount->execute([$drawId]); $entryCount=$entryCount->fetchColumn();
$participants = $db->prepare("SELECT COUNT(DISTINCT user_id) FROM draw_entries WHERE draw_id=?"); $participants->execute([$drawId]); $participants=$participants->fetchColumn();

// Top participants
$topUsers = $db->prepare("SELECT u.full_name, u.phone, COUNT(de.id) as entries, MIN(de.entered_at) as first_entry
  FROM draw_entries de JOIN users u ON de.user_id=u.id
  WHERE de.draw_id=? GROUP BY de.user_id ORDER BY entries DESC LIMIT 10");
$topUsers->execute([$drawId]); $topUsers=$topUsers->fetchAll();

$winner = null;
if ($draw['winner_user_id']) {
  $w=$db->prepare("SELECT dw.*,u.full_name,u.phone FROM draw_winners dw JOIN users u ON dw.user_id=u.id WHERE dw.draw_id=?");
  $w->execute([$drawId]); $winner=$w->fetch();
}

$aPage = 'draws';
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="csrf-token" content="<?= generateCsrf() ?>">
  <title>Manage Draw — <?= APP_NAME ?> Admin</title>
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
    <a href="<?= APP_URL ?>/admin/draws.php" class="text-orange-400 text-sm hover:underline mr-3">← Draws</a>
    <h1 class="text-xl font-bold">Manage Draw</h1>
    <?php if($draw['status']==='active'): ?>
    <a href="<?= APP_URL ?>/admin/live-draw-admin.php?id=<?= $drawId ?>" class="btn btn-primary btn-sm">🔴 Live Draw Control</a>
    <?php endif; ?>
  </div>
  <div class="p-6">
    <?php if($msg): ?><div class="bg-green-500/10 border border-green-500/30 text-green-400 rounded-xl p-4 mb-5 text-sm"><?= e($msg) ?></div><?php endif; ?>
    <?php if($err): ?><div class="bg-red-500/10 border border-red-500/30 text-red-400 rounded-xl p-4 mb-5 text-sm"><?= e($err) ?></div><?php endif; ?>

    <div class="grid lg:grid-cols-3 gap-6">
      <!-- Left: Stats + Winner -->
      <div class="space-y-4">
        <div class="card p-5 grid grid-cols-2 gap-4">
          <div class="text-center"><div class="text-2xl font-black text-orange-400"><?= $entryCount ?></div><div class="text-xs text-gray-400 mt-1">Entries</div></div>
          <div class="text-center"><div class="text-2xl font-black text-blue-400"><?= $participants ?></div><div class="text-xs text-gray-400 mt-1">Participants</div></div>
        </div>
        <div class="card p-5">
          <div class="text-sm font-semibold mb-3">Draw Info</div>
          <div class="space-y-2 text-sm">
            <div class="flex justify-between"><span class="text-gray-400">Status</span><span class="badge <?= match($draw['status']){'active'=>'badge-success','pending'=>'badge-warning','paused'=>'badge-info','completed'=>'badge-muted',default=>'badge-danger'} ?>"><?= ucfirst($draw['status']) ?></span></div>
            <div class="flex justify-between"><span class="text-gray-400">Start</span><span class="text-xs"><?= date('M j, Y g:ia',strtotime($draw['start_date'])) ?></span></div>
            <div class="flex justify-between"><span class="text-gray-400">End</span><span class="text-xs"><?= date('M j, Y g:ia',strtotime($draw['end_date'])) ?></span></div>
            <?php if($draw['winning_code']): ?>
            <div class="flex justify-between"><span class="text-gray-400">Win Code</span><span class="font-mono text-orange-400 text-xs"><?= e($draw['winning_code']) ?></span></div>
            <?php endif; ?>
          </div>
        </div>

        <?php if($winner): ?>
        <div class="card p-5 border-yellow-500/30">
          <div class="text-center">
            <div class="text-3xl mb-2">🏆</div>
            <div class="font-bold text-yellow-400 text-sm mb-1">WINNER</div>
            <div class="font-semibold"><?= e($winner['full_name']) ?></div>
            <div class="text-xs text-gray-400"><?= e(formatPhone($winner['phone'])) ?></div>
            <div class="font-mono text-orange-400 text-xs mt-2"><?= e($winner['winning_code']) ?></div>
            <div class="text-xs text-gray-500 mt-1"><?= $winner['matched_digits'] ?>/15 digits matched</div>
          </div>
        </div>
        <?php endif; ?>

        <!-- Top Participants -->
        <div class="card">
          <div class="p-4 font-bold text-sm border-b border-white/5">Top Participants</div>
          <?php foreach($topUsers as $u): ?>
          <div class="flex items-center justify-between p-3 border-b border-white/5 last:border-0">
            <div>
              <div class="text-sm font-medium"><?= e($u['full_name']) ?></div>
              <div class="text-xs text-gray-500"><?= e(formatPhone($u['phone'])) ?></div>
            </div>
            <span class="badge badge-info"><?= $u['entries'] ?> codes</span>
          </div>
          <?php endforeach; if(!$topUsers): ?>
          <div class="p-6 text-center text-gray-500 text-sm">No entries yet</div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Right: Edit form -->
      <div class="lg:col-span-2">
        <form method="POST" enctype="multipart/form-data" class="card p-6">
          <?= csrfField() ?><input type="hidden" name="action" value="update">
          <h2 class="font-bold text-lg mb-5">Edit Draw Details</h2>
          <div class="grid grid-cols-2 gap-4">
            <div class="col-span-2 form-group">
              <label class="form-label">Draw Title</label>
              <input type="text" name="title" class="form-control" value="<?= e($draw['title']) ?>" required>
            </div>
            <div class="form-group">
              <label class="form-label">Category</label>
              <select name="category" class="form-control">
                <option value="">Select…</option>
                <?php foreach(['Daily Patronage','Government Ticket','Transport','Dashboard Loyalty','Vendor Campaign','Grand Prize'] as $c): ?>
                <option value="<?= $c ?>" <?= $draw['category']===$c?'selected':'' ?>><?= $c ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Status</label>
              <select name="status" class="form-control">
                <?php foreach(['pending'=>'Pending','active'=>'Active','paused'=>'Paused','completed'=>'Completed','cancelled'=>'Cancelled'] as $v=>$l): ?>
                <option value="<?= $v ?>" <?= $draw['status']===$v?'selected':'' ?>><?= $l ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Start Date</label>
              <input type="datetime-local" name="start_date" class="form-control" value="<?= date('Y-m-d\TH:i',strtotime($draw['start_date'])) ?>" required>
            </div>
            <div class="form-group">
              <label class="form-label">End Date</label>
              <input type="datetime-local" name="end_date" class="form-control" value="<?= date('Y-m-d\TH:i',strtotime($draw['end_date'])) ?>" required>
            </div>
            <div class="col-span-2 form-group">
              <label class="form-label">Description</label>
              <textarea name="description" class="form-control" rows="3"><?= e($draw['description']??'') ?></textarea>
            </div>
            <div class="col-span-2 form-group">
              <label class="form-label">Prize Details</label>
              <textarea name="prize_details" class="form-control" rows="2"><?= e($draw['prize_details']??'') ?></textarea>
            </div>
            <div class="col-span-2 form-group">
              <label class="form-label">Rules</label>
              <textarea name="rules" class="form-control" rows="2"><?= e($draw['rules']??'') ?></textarea>
            </div>
            <div class="col-span-2 form-group">
              <label class="form-label">Banner Image <?php if($draw['banner_image']): ?><span class="text-gray-500 font-normal">(current: <?= e($draw['banner_image']) ?>)</span><?php endif; ?></label>
              <?php if($draw['banner_image']&&file_exists(UPLOAD_PATH.$draw['banner_image'])): ?>
              <img src="<?= APP_URL ?>/uploads/<?= e($draw['banner_image']) ?>" class="w-full h-32 object-cover rounded-xl mb-3" alt="">
              <?php endif; ?>
              <input type="file" name="banner_image" class="form-control" accept="image/*">
              <div class="text-xs text-gray-500 mt-1">Upload new image to replace existing one</div>
            </div>
          </div>
          <button type="submit" class="btn btn-primary w-full py-3 mt-2">Save Changes</button>
        </form>
      </div>
    </div>
  </div>
</div>
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
</body></html>

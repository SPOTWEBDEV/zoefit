<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
$auth = requireUser(); $userId = $auth['id'];
$currentPage = 'draws'; $pageTitle = 'Draws & Rewards';
$db = getDB();
$filter = $_GET['status'] ?? 'active';
$allowed = ['active','pending','completed'];
if (!in_array($filter,$allowed)) $filter='active';

$draws = $db->prepare("SELECT d.*, (SELECT COUNT(*) FROM draw_entries WHERE draw_id=d.id AND user_id=?) as my_entries FROM draws d WHERE d.status=? ORDER BY d.end_date ASC");
$draws->execute([$userId, $filter]);
$draws = $draws->fetchAll();
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="csrf-token" content="<?= generateCsrf() ?>">
  <title><?= e($pageTitle) ?> — <?= APP_NAME ?></title>
  <script src="<?= APP_URL ?>/assets/js/tailwind.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
  <style>*{font-family:'Poppins',sans-serif!important}</style>
</head>
<body class="bg-[#0a0f1a] text-white">
<?php include __DIR__ . '/../components/user-sidebar.php'; ?>
<div class="main-content">
  <div class="topbar">
    <button onclick="toggleSidebar()" class="md:hidden text-gray-400 text-2xl mr-3">☰</button>
    <h1 class="text-xl font-bold">Draws & Rewards</h1>
  </div>
  <div class="p-6">
    <!-- Tabs -->
    <div class="flex gap-2 mb-6">
      <?php foreach(['active'=>'🔴 Live','pending'=>'⏳ Upcoming','completed'=>'✅ Completed'] as $s=>$l): ?>
      <a href="?status=<?= $s ?>" class="btn btn-sm <?= $filter===$s?'btn-primary':'btn-secondary' ?>"><?= $l ?></a>
      <?php endforeach; ?>
    </div>

    <?php if ($draws): ?>
    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
      <?php foreach ($draws as $d): ?>
      <div class="draw-card">
        <div class="draw-banner flex items-center justify-center text-5xl" style="background:linear-gradient(135deg,#1a2235,#0d1118)">
          <?php if ($d['banner_image'] && file_exists(UPLOAD_PATH.$d['banner_image'])): ?>
            <img src="<?= APP_URL ?>/uploads/<?= e($d['banner_image']) ?>" class="w-full h-full object-cover" alt="">
          <?php else: ?>🎰<?php endif; ?>
        </div>
        <div class="p-4">
          <div class="flex gap-2 mb-2 flex-wrap">
            <?php if($d['status']==='active'): ?><span class="badge badge-success">● LIVE</span><?php endif; ?>
            <?php if($d['my_entries']>0): ?><span class="badge badge-info">✓ Entered (<?= $d['my_entries'] ?>)</span><?php endif; ?>
            <?php if($d['category']): ?><span class="badge badge-muted"><?= e($d['category']) ?></span><?php endif; ?>
          </div>
          <h3 class="font-bold mb-1"><?= e($d['title']) ?></h3>
          <?php if($d['prize_details']): ?><p class="text-sm text-orange-400 mb-2 font-semibold">🏆 <?= e(substr($d['prize_details'],0,50)) ?>…</p><?php endif; ?>
          <?php if($d['status']==='active'): ?>
          <div class="flex gap-3 items-center my-3" data-countdown="<?= e($d['end_date']) ?>"></div>
          <?php elseif($d['status']==='completed'): ?>
          <div class="text-sm text-gray-500 my-3">Completed <?= date('M j, Y',strtotime($d['end_date'])) ?></div>
          <?php else: ?>
          <div class="text-sm text-gray-500 my-3">Starts <?= date('M j, Y g:ia',strtotime($d['start_date'])) ?></div>
          <?php endif; ?>
          <?php if($d['status']==='active'): ?>
          <a href="<?= APP_URL ?>/user/enter-draw.php?id=<?= $d['id'] ?>" class="btn btn-primary w-full text-sm">Enter Draw →</a>
          <?php elseif($d['status']==='completed' && $d['winner_user_id']): ?>
          <a href="<?= APP_URL ?>/user/draw-detail.php?id=<?= $d['id'] ?>" class="btn btn-secondary w-full text-sm">View Results</a>
          <?php else: ?>
          <button class="btn btn-secondary w-full text-sm" disabled>Not Yet Active</button>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="card p-12 text-center">
      <div class="text-5xl mb-4">🎰</div>
      <div class="text-gray-400">No <?= $filter ?> draws at the moment.</div>
    </div>
    <?php endif; ?>
  </div>
</div>
<script>
  window.APP_URL = '<?= APP_URL ?>';
</script>
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
</body></html>

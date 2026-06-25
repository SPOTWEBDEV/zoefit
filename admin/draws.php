<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
$auth = requireUser(); $userId = $auth['id'];
$db   = getDB();

// Filter
$cat  = trim($_GET['cat'] ?? '');
$page = max(1,(int)($_GET['page'] ?? 1));
$per  = 9; $offset = ($page-1)*$per;

// CORRECTION 3: Only active draws — draws that have ended are excluded
// A draw is visible if status='active' AND end_date > NOW()
$where  = "status='active' AND end_date > NOW()";
$params = [];
if ($cat) { $where .= " AND category=?"; $params[] = $cat; }

$draws = $db->prepare("SELECT d.*,
  (SELECT COUNT(*) FROM draw_entries WHERE draw_id=d.id) as entry_count,
  (SELECT COUNT(*) FROM draw_entries WHERE draw_id=d.id AND user_id=?) as my_entries
  FROM draws d WHERE $where ORDER BY end_date ASC LIMIT $per OFFSET $offset");
$draws->execute(array_merge([$userId], $params)); $draws = $draws->fetchAll();

$cntS = $db->prepare("SELECT COUNT(*) FROM draws WHERE $where"); $cntS->execute($params); $total = $cntS->fetchColumn();
$pages = ceil($total / $per);

// Categories for filter tabs
$cats = $db->query("SELECT DISTINCT category FROM draws WHERE status='active' AND end_date > NOW() AND category IS NOT NULL ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);

// User balance
$stmt = $db->prepare("SELECT balance,full_name FROM users WHERE id=?"); $stmt->execute([$userId]); $user=$stmt->fetch();

$currentPage = 'draws'; $pageTitle = 'Live Draws';
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="csrf-token" content="<?= generateCsrf() ?>">
  <title><?= e($pageTitle) ?> — <?= APP_NAME ?></title>
  <script src="<?= APP_URL ?>/assets/js/tailwind.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
  <style>
    *{font-family:'Poppins',sans-serif!important}
    .draw-card{background:var(--bg-card);border:1px solid var(--border);border-radius:20px;overflow:hidden;transition:all .3s;}
    .draw-card:hover{transform:translateY(-4px);border-color:rgba(249,115,22,.35);box-shadow:0 16px 40px rgba(0,0,0,.4);}
    .entered{border-color:rgba(34,197,94,.35)!important;background:linear-gradient(135deg,rgba(34,197,94,.05),var(--bg-card))!important;}
  </style>
</head>
<body class="bg-[#0a0f1a] text-white">
<?php include __DIR__ . '/../components/user-sidebar.php'; ?>
<div class="main-content">
  <div class="topbar">
    <button onclick="toggleSidebar()" class="md:hidden text-gray-400 text-2xl mr-3">☰</button>
    <div>
      <h1 class="text-xl font-bold">Live Draws</h1>
      <div class="text-xs text-gray-400"><?= number_format($total) ?> active draw<?= $total!=1?'s':'' ?> right now</div>
    </div>
    <div class="flex items-center gap-2">
      <div class="balance-pill flex items-center gap-2 bg-orange-500/10 border border-orange-500/20 rounded-xl px-3 py-1.5">
        <span class="text-orange-400 text-sm font-bold"><?= $user['balance'] ?></span>
        <span class="text-gray-400 text-xs">codes</span>
      </div>
    </div>
  </div>

  <div class="p-4 md:p-6 pb-24 md:pb-6">

    <!-- Category filter tabs -->
    <?php if ($cats): ?>
    <div class="flex gap-2 mb-5 flex-wrap">
      <a href="?" class="btn btn-sm <?= !$cat?'btn-primary':'btn-secondary' ?>">All Draws</a>
      <?php foreach($cats as $c): ?>
      <a href="?cat=<?= urlencode($c) ?>" class="btn btn-sm <?= $cat===$c?'btn-primary':'btn-secondary' ?>"><?= e($c) ?></a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Draws grid -->
    <?php if ($draws): ?>
    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
      <?php foreach($draws as $d): ?>
      <div class="draw-card <?= $d['my_entries']>0?'entered':'' ?>">
        <!-- Banner -->
        <div class="h-36 flex items-center justify-center text-5xl relative"
             style="background:linear-gradient(135deg,#1a2235,#0d1118)">
          <?php if($d['banner_image']&&file_exists(UPLOAD_PATH.$d['banner_image'])): ?>
          <img src="<?= APP_URL ?>/uploads/<?= e($d['banner_image']) ?>" class="w-full h-full object-cover absolute inset-0" alt="">
          <?php else: ?>🎰<?php endif; ?>
          <!-- Live badge -->
          <div class="absolute top-3 left-3 flex items-center gap-1.5 bg-black/60 backdrop-blur rounded-full px-3 py-1">
            <span class="pulse-dot w-2 h-2"></span>
            <span class="text-xs font-semibold text-white">LIVE</span>
          </div>
          <?php if($d['my_entries']>0): ?>
          <div class="absolute top-3 right-3 bg-green-500/80 backdrop-blur rounded-full px-2 py-0.5 text-xs font-bold text-white">✓ Entered</div>
          <?php endif; ?>
        </div>
        <!-- Content -->
        <div class="p-5">
          <div class="flex gap-2 mb-2 flex-wrap">
            <?php if($d['category']): ?><span class="badge badge-info"><?= e($d['category']) ?></span><?php endif; ?>
            <?php if($d['my_entries']>0): ?><span class="badge badge-success"><?= $d['my_entries'] ?> entr<?= $d['my_entries']===1?'y':'ies' ?></span><?php endif; ?>
          </div>
          <h3 class="font-bold text-base mb-2 line-clamp-2"><?= e($d['title']) ?></h3>
          <?php if($d['prize_details']): ?>
          <p class="text-sm text-orange-400 font-semibold mb-3 line-clamp-1">🏆 <?= e($d['prize_details']) ?></p>
          <?php endif; ?>
          <!-- Countdown -->
          <div class="flex items-center gap-2 mb-3">
            <svg class="w-3.5 h-3.5 text-gray-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span class="text-sm font-semibold text-orange-300" data-countdown="<?= e($d['end_date']) ?>"></span>
          </div>
          <!-- Stats -->
          <div class="flex items-center gap-4 mb-4 text-xs text-gray-500">
            <span>📝 <?= number_format($d['entry_count']) ?> entries</span>
            <span>📅 Ends <?= date('M j', strtotime($d['end_date'])) ?></span>
          </div>
          <a href="<?= APP_URL ?>/user/draw-detail.php?id=<?= $d['id'] ?>"
             class="btn <?= $d['my_entries']>0?'btn-secondary':'btn-primary' ?> w-full text-sm py-2.5">
            <?= $d['my_entries']>0 ? '📋 View My Entries' : '🎯 Enter Draw →' ?>
          </a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if($pages>1): ?>
    <div class="flex items-center justify-between mt-6">
      <div class="text-sm text-gray-400">Page <?= $page ?>/<?= $pages ?></div>
      <div class="flex gap-2">
        <?php if($page>1): ?><a href="?page=<?=$page-1?>&cat=<?=urlencode($cat)?>" class="btn btn-sm btn-secondary">← Prev</a><?php endif; ?>
        <?php if($page<$pages): ?><a href="?page=<?=$page+1?>&cat=<?=urlencode($cat)?>" class="btn btn-sm btn-secondary">Next →</a><?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php else: ?>
    <!-- Empty state -->
    <div class="card p-16 text-center">
      <div class="text-6xl mb-5">🎯</div>
      <h2 class="text-2xl font-bold mb-3">No Active Draws Right Now</h2>
      <p class="text-gray-400 mb-6 max-w-md mx-auto text-sm">
        All current draws have ended or no new draws have started yet.
        Check back soon — new draws are added regularly!
      </p>
      <div class="flex flex-wrap gap-3 justify-center">
        <a href="<?= APP_URL ?>/user/past-winners.php" class="btn btn-secondary">🏆 View Past Winners</a>
        <a href="<?= APP_URL ?>/user/redeem.php" class="btn btn-primary">🎟️ Redeem a Code</a>
      </div>
    </div>
    <?php endif; ?>

    <!-- Past winners prompt -->
    <div class="mt-8 card p-5 flex items-center gap-4">
      <div class="w-12 h-12 bg-yellow-500/15 rounded-2xl flex items-center justify-center text-2xl flex-shrink-0">🏆</div>
      <div class="flex-1">
        <div class="font-bold text-sm">Missed a draw?</div>
        <div class="text-xs text-gray-400 mt-0.5">View all past draw results and verified winners.</div>
      </div>
      <a href="<?= APP_URL ?>/user/past-winners.php" class="btn btn-secondary btn-sm flex-shrink-0">View Past Winners →</a>
    </div>

  </div>
</div>
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
</body></html>

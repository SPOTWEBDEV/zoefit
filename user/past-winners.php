<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
$auth = requireUser(); $userId = $auth['id'];
$db   = getDB();

$page = max(1,(int)($_GET['page'] ?? 1));
$per  = 12; $offset = ($page-1)*$per;
$q    = trim($_GET['q'] ?? '');

// Build query — completed draws with winners, admin-managed
$where  = "dw.draw_id IS NOT NULL";
$params = [];
if ($q) { $where .= " AND (d.title LIKE ? OR d.category LIKE ?)"; $s="%$q%"; $params=[$s,$s]; }

$winners = $db->prepare(
  "SELECT dw.*,
          d.title        AS draw_title,
          d.category     AS draw_category,
          d.prize_details,
          d.end_date,
          u.full_name,
          u.phone
   FROM draw_winners dw
   JOIN draws d  ON dw.draw_id = d.id
   JOIN users u  ON dw.user_id = u.id
   WHERE $where
   ORDER BY dw.announced_at DESC
   LIMIT $per OFFSET $offset"
);
$winners->execute($params); $winners = $winners->fetchAll();

$cntS = $db->prepare("SELECT COUNT(*) FROM draw_winners dw JOIN draws d ON dw.draw_id=d.id WHERE $where");
$cntS->execute($params); $total = $cntS->fetchColumn();
$pages = ceil($total / $per);

// Check if current user has won any draw
$myWins = $db->prepare("SELECT COUNT(*) FROM draw_winners WHERE user_id=?");
$myWins->execute([$userId]); $myWins = (int)$myWins->fetchColumn();

$currentPage = 'draws'; $pageTitle = 'Past Winners';
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= e($pageTitle) ?> — <?= APP_NAME ?></title>
  <script src="<?= APP_URL ?>/assets/js/tailwind.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
  <style>
    *{font-family:'Poppins',sans-serif!important}
    .winner-card{background:linear-gradient(135deg,rgba(234,179,8,.08),var(--bg-card));border:1px solid rgba(234,179,8,.2);border-radius:18px;transition:all .25s;}
    .winner-card:hover{transform:translateY(-3px);border-color:rgba(234,179,8,.4);box-shadow:0 12px 32px rgba(0,0,0,.4);}
    .my-win{background:linear-gradient(135deg,rgba(34,197,94,.12),var(--bg-card))!important;border-color:rgba(34,197,94,.35)!important;}
  </style>
</head>
<body class="bg-[#0a0f1a] text-white">
<?php include __DIR__ . '/../components/user-sidebar.php'; ?>
<div class="main-content">
  <div class="topbar">
    <button onclick="toggleSidebar()" class="md:hidden text-gray-400 text-2xl mr-3">☰</button>
    <div>
      <h1 class="text-xl font-bold">Past Winners</h1>
      <div class="text-xs text-gray-400"><?= number_format($total) ?> winner<?= $total!=1?'s':'' ?> recorded</div>
    </div>
    <a href="<?= APP_URL ?>/user/draws.php" class="btn btn-primary btn-sm">🎯 Live Draws</a>
  </div>

  <div class="p-4 md:p-6 pb-24 md:pb-6">

    <!-- My wins banner -->
    <?php if ($myWins > 0): ?>
    <div class="bg-green-500/10 border border-green-500/25 rounded-2xl p-5 mb-5 flex items-center gap-4">
      <div class="text-4xl">🏆</div>
      <div>
        <div class="font-bold text-green-400">Congratulations! You've won <?= $myWins ?> draw<?= $myWins>1?'s':'' ?>!</div>
        <div class="text-sm text-gray-400 mt-0.5">Your winning entries are highlighted below in green.</div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Search -->
    <form method="GET" class="flex gap-3 mb-5">
      <div class="relative flex-1 max-w-sm">
        <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
        <input type="text" name="q" class="form-control pl-9" placeholder="Search draw name…" value="<?= e($q) ?>">
      </div>
      <button class="btn btn-primary px-5">Search</button>
      <?php if($q): ?><a href="?" class="btn btn-secondary px-4">Clear</a><?php endif; ?>
    </form>

    <!-- Winners grid -->
    <?php if ($winners): ?>
    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
      <?php foreach($winners as $w):
        $initial    = strtoupper(mb_substr($w['full_name'],0,1));
        $last       = mb_substr($w['full_name'],-1);
        $maskedName = $initial.'***'.$last;
        $isMyWin    = $w['user_id'] == $userId;
      ?>
      <div class="winner-card p-5 <?= $isMyWin?'my-win':'' ?>">
        <div class="flex items-start gap-3 mb-4">
          <div class="w-12 h-12 rounded-2xl flex items-center justify-center text-2xl font-black flex-shrink-0
            <?= $isMyWin?'bg-green-500/20 text-green-400':'bg-yellow-500/15 text-yellow-400' ?>">
            <?= $isMyWin ? '🏆' : $initial ?>
          </div>
          <div class="flex-1 min-w-0">
            <div class="font-bold <?= $isMyWin?'text-green-400':'text-yellow-400' ?>">
              <?= $isMyWin ? 'You won! 🎉' : e($maskedName) ?>
            </div>
            <div class="text-xs text-gray-400 line-clamp-2 mt-0.5"><?= e($w['draw_title']) ?></div>
            <?php if($w['draw_category']): ?>
            <span class="badge badge-info text-xs mt-1"><?= e($w['draw_category']) ?></span>
            <?php endif; ?>
          </div>
        </div>

        <?php if($w['prize_details']): ?>
        <div class="bg-orange-500/10 rounded-xl p-3 mb-3">
          <div class="text-xs text-gray-500 mb-0.5">Prize</div>
          <div class="text-sm font-semibold text-orange-400">🏆 <?= e($w['prize_details']) ?></div>
        </div>
        <?php endif; ?>

        <div class="space-y-2 text-xs text-gray-400">
          <div class="flex items-center justify-between">
            <span class="text-gray-500">Winning Code</span>
            <code class="text-orange-400 font-bold"><?= e(substr($w['winning_code'],0,7)) ?>·····</code>
          </div>
          <div class="flex items-center justify-between">
            <span class="text-gray-500">Matched Digits</span>
            <span class="font-semibold text-white"><?= $w['matched_digits'] ?>/15</span>
          </div>
          <?php if($w['tiebreaker_used']): ?>
          <div class="flex items-center justify-between">
            <span class="text-gray-500">Tiebreaker</span>
            <span class="text-gray-300"><?= e($w['tiebreaker_used']) ?></span>
          </div>
          <?php endif; ?>
          <div class="flex items-center justify-between border-t border-white/5 pt-2 mt-2">
            <span class="text-gray-500">Announced</span>
            <span class="text-gray-300 font-medium"><?= date('M j, Y', strtotime($w['announced_at'])) ?></span>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if($pages > 1): ?>
    <div class="flex items-center justify-between mt-6">
      <div class="text-sm text-gray-400">Page <?= $page ?>/<?= $pages ?> · <?= number_format($total) ?> winners</div>
      <div class="flex gap-2">
        <?php if($page>1): ?><a href="?page=<?=$page-1?>&q=<?=urlencode($q)?>" class="btn btn-sm btn-secondary">← Prev</a><?php endif; ?>
        <?php if($page<$pages): ?><a href="?page=<?=$page+1?>&q=<?=urlencode($q)?>" class="btn btn-sm btn-secondary">Next →</a><?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php else: ?>
    <div class="card p-16 text-center">
      <div class="text-6xl mb-5">🏆</div>
      <h2 class="text-2xl font-bold mb-3">No Winners Yet</h2>
      <p class="text-gray-400 mb-6 text-sm max-w-sm mx-auto">
        <?= $q ? 'No winners found for your search.' : 'No draws have been completed yet. Be the first to enter and win!' ?>
      </p>
      <?php if($q): ?>
      <a href="?" class="btn btn-secondary">Clear Search</a>
      <?php else: ?>
      <a href="<?= APP_URL ?>/user/draws.php" class="btn btn-primary px-8">🎯 Enter a Live Draw</a>
      <?php endif; ?>
    </div>
    <?php endif; ?>

  </div>
</div>
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
</body></html>

<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../lib/paystack.php';
$auth = requireUser();
$userId = $auth['id'];
$db = getDB();

// Fetch user data
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

$walletBalance = (int) ($user['wallet_balance'] ?? 0);

// Stats
$totalCodes   = $db->prepare("SELECT COUNT(*) FROM codes WHERE current_owner = ? AND status NOT IN ('used')");
$totalCodes->execute([$userId]); $totalCodes = $totalCodes->fetchColumn();

$totalnotUsedCodes = $db->prepare("SELECT COUNT(*) FROM codes WHERE current_owner = ? AND status = 'redeemed'");
$totalnotUsedCodes->execute([$userId]); 
$totalnotUsedCodes = $totalnotUsedCodes->fetchColumn();


$usedInDraws  = $db->prepare("SELECT COUNT(*) FROM draw_entries WHERE user_id = ?");
$usedInDraws->execute([$userId]); $usedInDraws = $usedInDraws->fetchColumn();

$totalWins    = $db->prepare("SELECT COUNT(*) FROM draw_winners WHERE user_id = ?");
$totalWins->execute([$userId]); $totalWins = $totalWins->fetchColumn();

// Wallet / deposit stats
$depositStats = $db->prepare("SELECT COUNT(*) AS cnt, COALESCE(SUM(amount),0) AS total FROM deposits WHERE user_id = ? AND status = 'success'");
$depositStats->execute([$userId]);
$depositStats = $depositStats->fetch();

$recentDeposits = $db->prepare("SELECT * FROM deposits WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
$recentDeposits->execute([$userId]);
$recentDeposits = $recentDeposits->fetchAll();

// Active draws
$draws = $db->prepare("SELECT * FROM draws WHERE status = 'active' ORDER BY end_date ASC LIMIT 4");
$draws->execute(); $draws = $draws->fetchAll();

// Recent transactions
$txns = $db->prepare("SELECT t.*, c.code FROM transactions t LEFT JOIN codes c ON t.code_id = c.id WHERE t.user_id = ? ORDER BY t.created_at DESC LIMIT 6");
$txns->execute([$userId]); $txns = $txns->fetchAll();

$currentPage = 'dashboard';
$pageTitle = 'Dashboard';

$flashType = $_SESSION['flash_type'] ?? null;
$flashMsg  = $_SESSION['flash_msg'] ?? null;
unset($_SESSION['flash_type'], $_SESSION['flash_msg']);
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="csrf-token" content="<?= generateCsrf() ?>">
  <title><?= e($pageTitle) ?> — <?= APP_NAME ?></title>
   <script src="<?= APP_URL ?>/assets/js/tailwind.js" ></script>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
</head>
<body class="bg-[#0a0f1a] text-white font-sans">

<?php include __DIR__ . '/../components/user-sidebar.php'; ?>

<div class="main-content">
  <!-- Topbar -->
  <div class="topbar">
    <button onclick="toggleSidebar()" class="md:hidden text-gray-400 hover:text-white text-2xl mr-3">☰</button>
    <div>
      <div class="font-semibold"><?= e($user['full_name']) ?></div>
      <div class="text-xs text-gray-400"><?= e(formatPhone($user['phone'])) ?></div>
    </div>
    <div class="flex items-center gap-3">
      <a href="<?= APP_URL ?>/user/notifications.php" class="relative p-2 rounded-lg hover:bg-white/5">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
        <span id="notif-badge" class="absolute top-1 right-1 w-4 h-4 bg-orange-500 rounded-full text-[10px] flex items-center justify-center" style="display:none"></span>
      </a>
    </div>
  </div>

  <div class="p-6">

    <?php if ($flashMsg): ?>
    <div class="mb-6 rounded-xl p-4 text-sm border <?= $flashType === 'success' ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-300' : 'border-red-500/30 bg-red-500/10 text-red-300' ?>">
      <?= e($flashMsg) ?>
    </div>
    <?php endif; ?>

    <!-- Wallet Balance Card -->
    <div class="mb-6 rounded-2xl p-6 fade-in" style="background:linear-gradient(135deg,#0f766e,#0a3d3a)">
      <div class="flex items-start justify-between mb-4">
        <div>
          <div class="text-sm text-emerald-200 font-medium">Wallet Balance</div>
          <div class="flex items-center gap-3 mt-2">
            <div class="text-4xl font-display font-bold"><?= formatNaira($walletBalance) ?></div>
          </div>
          <div class="text-sm text-emerald-200 mt-1">Available for airtime & data purchase</div>
        </div>
        <div class="bg-white/10 rounded-xl p-3">
          <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V6m0 12v-2m9-4a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </div>
      </div>
      <div class="flex gap-3 mt-4">
        <a href="<?= APP_URL ?>/user/deposit.php" class="btn btn-sm bg-white/20 hover:bg-white/30 text-white border-0 flex-1 justify-center flex items-center gap-2">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
          Deposit
        </a>
        <a href="<?= APP_URL ?>/user/wallet-history.php" class="btn btn-sm bg-white/20 hover:bg-white/30 text-white border-0 flex-1 justify-center">Wallet History</a>
      </div>
    </div>

    <!-- Wallet detail cards: deposits, orders, etc -->
    <div class="grid grid-cols-3 gap-4 mb-6">
      <div class="card p-4 text-center">
        <div class="text-2xl font-bold font-display text-emerald-400"><?= formatNaira($depositStats['total'] ?? 0) ?></div>
        <div class="text-xs text-gray-400 mt-1">Total Deposited</div>
      </div>
      <div class="card p-4 text-center">
        <div class="text-2xl font-bold font-display text-blue-400"><?= (int) ($depositStats['cnt'] ?? 0) ?></div>
        <div class="text-xs text-gray-400 mt-1">Successful Deposits</div>
      </div>
      <div class="card p-4 text-center relative">
        <div class="text-2xl font-bold font-display text-gray-500">0</div>
        <div class="text-xs text-gray-400 mt-1">Orders</div>
        <span class="absolute top-2 right-2 text-[9px] px-1.5 py-0.5 rounded-full bg-white/10 text-gray-400">Soon</span>
      </div>
    </div>

    <!-- Raffle Code Balance Card -->
    <div class="balance-card mb-6 fade-in">
      <div class="flex items-start justify-between mb-4">
        <div>
          <div class="text-sm text-orange-200 font-medium">Eligibility Balance</div>
          <div class="flex items-center gap-3 mt-2">
            <div class="text-4xl font-display font-bold" id="balance-value" data-value="<?= $totalnotUsedCodes ?>" data-hidden="0"><?= $totalnotUsedCodes ?></div>
            <button id="balance-toggle" onclick="toggleBalance()" class="text-xl">👁</button>
          </div>
          <div class="text-sm text-orange-200 mt-1">Active Raffle Codes</div>
        </div>
        <div class="bg-white/10 rounded-xl p-3">
          <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
        </div>
      </div>
      <div class="flex gap-3 mt-4">
        <a href="<?= APP_URL ?>/user/redeem.php" class="btn btn-sm bg-white/20 hover:bg-white/30 text-white border-0 flex-1 justify-center">+ Redeem Code</a>
        <a href="<?= APP_URL ?>/user/transfer.php" class="btn btn-sm bg-white/20 hover:bg-white/30 text-white border-0 flex-1 justify-center">Transfer Code</a>
       
      </div>
    </div>

    <!-- Stats Row -->
    <div class="grid grid-cols-3 gap-4 mb-6">
      <div class="card p-4 text-center">
        <div class="text-2xl font-bold font-display text-orange-400"><?= $totalCodes ?></div>
        <div class="text-xs text-gray-400 mt-1">Total Codes</div>
      </div>
      
      <div class="card p-4 text-center">
        <div class="text-2xl font-bold font-display text-blue-400"><?= $usedInDraws ?></div>
        <div class="text-xs text-gray-400 mt-1">Total Codes In Draws</div>
      </div>
      <div class="card p-4 text-center">
        <div class="text-2xl font-bold font-display text-green-400"><?= $totalWins ?></div>
        <div class="text-xs text-gray-400 mt-1">Wins</div>
      </div>
      
    </div>

    <!-- Active Draws -->
    <?php if ($draws): ?>
    <div class="mb-6">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-bold">Active Draws</h2>
        <a href="<?= APP_URL ?>/user/draws.php" class="text-sm text-orange-400 hover:underline">View all →</a>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <?php foreach ($draws as $draw): ?>
        <a href="<?= APP_URL ?>/user/enter-draw.php?id=<?= $draw['id'] ?>" class="draw-card block fade-in">
          <div class="draw-banner flex items-center justify-center text-4xl" style="background:linear-gradient(135deg,#1a2235,#0a0f1a)">
            <?php if ($draw['banner_image'] && file_exists(UPLOAD_PATH . $draw['banner_image'])): ?>
              <img src="<?= APP_URL ?>/uploads/<?= e($draw['banner_image']) ?>" alt="" class="w-full h-full object-cover">
            <?php else: ?>🎰<?php endif; ?>
          </div>
          <div class="p-4">
            <div class="badge badge-success mb-2">● LIVE</div>
            <h3 class="font-bold text-base mb-2"><?= e($draw['title']) ?></h3>
            <div class="flex items-center gap-1" data-countdown="<?= e($draw['end_date']) ?>"></div>
            <button onclick="window.location='<?= APP_URL ?>/user/enter-draw.php?id=<?= $draw['id'] ?>'" class="btn btn-sm bg-orange-400 hover:bg-orange-500 text-white mt-4 w-full">Enter Draw</button>
          </div>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Recent Deposits -->
    <div class="mb-6">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-bold">Recent Deposits</h2>
        <a href="<?= APP_URL ?>/user/deposit.php" class="text-sm text-orange-400 hover:underline">Deposit more →</a>
      </div>
      <div class="card">
        <?php if ($recentDeposits): ?>
          <?php foreach ($recentDeposits as $d): ?>
          <?php
            $badgeClass = match($d['status']) {
              'success' => 'badge-success',
              'pending' => 'badge-warning',
              default   => 'badge-danger',
            };
          ?>
          <div class="flex items-center gap-4 p-4 border-b border-white/5 last:border-0">
            <div class="w-10 h-10 rounded-xl bg-emerald-500/15 flex items-center justify-center text-lg">💳</div>
            <div class="flex-1 min-w-0">
              <div class="text-sm font-medium"><?= formatNaira($d['amount']) ?></div>
              <div class="text-xs text-gray-400 font-mono"><?= e($d['reference']) ?></div>
            </div>
            <div class="text-right">
              <span class="badge <?= $badgeClass ?>"><?= e(ucfirst($d['status'])) ?></span>
              <div class="text-xs text-gray-500 mt-1"><?= date('M j, g:ia', strtotime($d['created_at'])) ?></div>
            </div>
          </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="p-8 text-center text-gray-500">No deposits yet — <a href="<?= APP_URL ?>/user/deposit.php" class="text-orange-400 hover:underline">fund your wallet</a></div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Recent Transactions -->
    <div>
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-bold">Recent Activity</h2>
        <a href="<?= APP_URL ?>/user/transactions.php" class="text-sm text-orange-400 hover:underline">View all →</a>
      </div>
      <div class="card">
        <?php if ($txns): ?>
          <?php foreach ($txns as $t): ?>
          <div class="flex items-center gap-4 p-4 border-b border-white/5 last:border-0">
            <div class="w-10 h-10 rounded-xl flex items-center justify-center text-lg <?= $t['type']==='credit'?'bg-green-500/15':'bg-red-500/15' ?>">
              <?= $t['type']==='credit'?'↓':'↑' ?>
            </div>
            <div class="flex-1 min-w-0">
              <div class="text-sm font-medium"><?= e(ucfirst(str_replace('_',' ',$t['category']))) ?></div>
              <?php if ($t['code']): ?><div class="text-xs text-gray-400 font-mono"><?= e($t['code']) ?></div><?php endif; ?>
            </div>
            <div class="text-right">
              <div class="font-semibold <?= $t['type']==='credit'?'text-green-400':'text-red-400' ?>">
                <?= $t['type']==='credit'?'+':'-' ?><?= abs($t['amount']) ?> code<?= abs($t['amount'])>1?'s':'' ?>
              </div>
              <div class="text-xs text-gray-500"><?= date('M j, g:ia', strtotime($t['created_at'])) ?></div>
            </div>
          </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="p-8 text-center text-gray-500">No transactions yet</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<script src="<?= APP_URL ?>/assets/js/app.js"></script>
</body></html>
<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../lib/paystack.php';

$auth   = requireUser();
$userId = $auth['id'];
$db     = getDB();

$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

$walletBalance = (int) ($user['wallet_balance'] ?? 0);

// Filter
$filter = $_GET['filter'] ?? 'all';
$where  = 'user_id = ?';
$params = [$userId];
if ($filter === 'credit' || $filter === 'debit') {
    $where .= ' AND type = ?';
    $params[] = $filter;
}

$page    = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

$countStmt = $db->prepare("SELECT COUNT(*) FROM wallet_transactions WHERE $where");
$countStmt->execute($params);
$total = (int) $countStmt->fetchColumn();

$stmt = $db->prepare("SELECT * FROM wallet_transactions WHERE $where ORDER BY created_at DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$rows = $stmt->fetchAll();

$totalPages = max(1, (int) ceil($total / $perPage));

$categoryLabels = [
    'deposit'          => 'Wallet Deposit',
    'airtime_purchase' => 'Airtime Purchase',
    'data_purchase'    => 'Data Purchase',
    'refund'           => 'Refund',
    'reversal'         => 'Reversal',
];
$categoryIcons = [
    'deposit'          => '💳',
    'airtime_purchase' => '📞',
    'data_purchase'    => '📶',
    'refund'           => '↩️',
    'reversal'         => '⚠️',
];

$currentPage = 'wallet-history';
$pageTitle   = 'Wallet History';
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="csrf-token" content="<?= generateCsrf() ?>">
  <title><?= e($pageTitle) ?> — <?= APP_NAME ?></title>
  <script src="<?= APP_URL ?>/assets/js/tailwind.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
</head>
<body class="bg-[#0a0f1a] text-white font-sans">

<?php include __DIR__ . '/../components/user-sidebar.php'; ?>

<div class="main-content">
  <div class="topbar">
    <button onclick="toggleSidebar()" class="md:hidden text-gray-400 hover:text-white text-2xl mr-3">☰</button>
    <div>
      <div class="font-semibold">Wallet History</div>
      <div class="text-xs text-gray-400">All deposits, purchases and refunds</div>
    </div>
  </div>

  <div class="p-4 md:p-6">

    <!-- Balance summary -->
    <div class="rounded-2xl p-6 mb-6" style="background:linear-gradient(135deg,#0f766e,#0a3d3a)">
      <div class="text-sm text-emerald-200 font-medium">Current Wallet Balance</div>
      <div class="text-3xl font-display font-bold mt-2"><?= formatNaira($walletBalance) ?></div>
      <div class="flex gap-3 mt-4">
        <a href="<?= APP_URL ?>/user/deposit.php" class="btn btn-sm bg-white/20 hover:bg-white/30 text-white border-0">+ Deposit</a>
      </div>
    </div>

    <!-- Filter tabs -->
    <div class="flex gap-2 mb-4">
      <a href="?filter=all" class="px-4 py-2 rounded-lg text-sm <?= $filter==='all'?'bg-orange-500 text-white':'bg-white/5 text-gray-400 hover:bg-white/10' ?>">All</a>
      <a href="?filter=credit" class="px-4 py-2 rounded-lg text-sm <?= $filter==='credit'?'bg-orange-500 text-white':'bg-white/5 text-gray-400 hover:bg-white/10' ?>">Credits</a>
      <a href="?filter=debit" class="px-4 py-2 rounded-lg text-sm <?= $filter==='debit'?'bg-orange-500 text-white':'bg-white/5 text-gray-400 hover:bg-white/10' ?>">Debits</a>
    </div>

    <div class="card">
      <?php if ($rows): ?>
        <?php foreach ($rows as $r): ?>
        <div class="flex items-center gap-4 p-4 border-b border-white/5 last:border-0">
          <div class="w-10 h-10 rounded-xl flex items-center justify-center text-lg <?= $r['type']==='credit'?'bg-green-500/15':'bg-red-500/15' ?>">
            <?= $categoryIcons[$r['category']] ?? '💰' ?>
          </div>
          <div class="flex-1 min-w-0">
            <div class="text-sm font-medium truncate"><?= e($categoryLabels[$r['category']] ?? ucfirst($r['category'])) ?></div>
            <?php if ($r['description']): ?><div class="text-xs text-gray-400 truncate"><?= e($r['description']) ?></div><?php endif; ?>
          </div>
          <div class="text-right flex-shrink-0">
            <div class="font-semibold whitespace-nowrap <?= $r['type']==='credit'?'text-green-400':'text-red-400' ?>">
              <?= $r['type']==='credit'?'+':'-' ?><?= formatNaira($r['amount']) ?>
            </div>
            <div class="text-xs text-gray-500">Bal: <?= formatNaira($r['balance_after']) ?></div>
            <div class="text-xs text-gray-500"><?= date('M j, g:ia', strtotime($r['created_at'])) ?></div>
          </div>
        </div>
        <?php endforeach; ?>
      <?php else: ?>
        <div class="p-8 text-center text-gray-500">No wallet activity <?= $filter !== 'all' ? 'for this filter' : 'yet' ?></div>
      <?php endif; ?>
    </div>

    <?php if ($totalPages > 1): ?>
    <div class="flex justify-center gap-2 mt-6">
      <?php for ($p = 1; $p <= $totalPages; $p++): ?>
        <a href="?filter=<?= e($filter) ?>&page=<?= $p ?>"
           class="w-9 h-9 flex items-center justify-center rounded-lg text-sm <?= $p===$page?'bg-orange-500 text-white':'bg-white/5 text-gray-400 hover:bg-white/10' ?>">
          <?= $p ?>
        </a>
      <?php endfor; ?>
    </div>
    <?php endif; ?>

  </div>
</div>

<script src="<?= APP_URL ?>/assets/js/app.js"></script>
</body></html>
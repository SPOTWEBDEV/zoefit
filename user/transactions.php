<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
$auth = requireUser(); $userId = $auth['id'];
$currentPage = 'transactions'; $pageTitle = 'Transaction History';
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="csrf-token" content="<?= generateCsrf() ?>">
  <title><?= e($pageTitle) ?> — <?= APP_NAME ?></title>
  <script src="<?= APP_URL ?>/assets/js/tailwindcss.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
  <style>*{font-family:'Poppins',sans-serif!important}</style>
</head>
<body class="bg-[#0a0f1a] text-white">
<?php include __DIR__ . '/../components/user-sidebar.php'; ?>
<div class="main-content">
  <div class="topbar">
    <button onclick="toggleSidebar()" class="md:hidden text-gray-400 text-2xl mr-3">☰</button>
    <h1 class="text-xl font-bold">Transaction History</h1>
  </div>
  <div class="p-6">
    <div id="txn-container" class="space-y-2"></div>
    <div id="txn-loader" class="space-y-2">
      <?php for($i=0;$i<5;$i++): ?><div class="skeleton h-16 rounded-xl"></div><?php endfor; ?>
    </div>
  </div>
</div>
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
<script>
const icons = {
  redemption:'🎟️', transfer_in:'⬇️', transfer_out:'⬆️',
  draw_entry:'🎯', vendor_credit:'🏪', draw_deduction:'❌'
};
new InfiniteScroll({
  container: document.getElementById('txn-container'),
  loader: document.getElementById('txn-loader'),
  fetchUrl: '<?= APP_URL ?>/ajax/transactions.php',
  renderItem: t => `<div class="card p-4 flex items-center gap-4">
    <div class="w-10 h-10 rounded-xl flex items-center justify-center text-xl ${t.type==='credit'?'bg-green-500/15':'bg-red-500/15'}">
      ${icons[t.category]||'💳'}
    </div>
    <div class="flex-1 min-w-0">
      <div class="font-medium text-sm">${t.category.replace(/_/g,' ').replace(/\b\w/g,c=>c.toUpperCase())}</div>
      ${t.code ? `<div class="text-xs text-gray-400 font-mono">${t.code}</div>` : ''}
      ${t.description ? `<div class="text-xs text-gray-500">${t.description}</div>` : ''}
    </div>
    <div class="text-right flex-shrink-0">
      <div class="font-semibold ${t.type==='credit'?'text-green-400':'text-red-400'}">${t.type==='credit'?'+':'-'}${Math.abs(t.amount)} code${Math.abs(t.amount)>1?'s':''}</div>
      <div class="text-xs text-gray-500">${t.date}</div>
    </div>
  </div>`
});
</script>
</body></html>

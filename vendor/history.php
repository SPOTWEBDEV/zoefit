<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
$auth = requireVendor(); $vendorId = $auth['id'];
$vPage = 'history';
$tab = $_GET['tab'] ?? 'distribution';
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>History — <?= APP_NAME ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
  <style>*{font-family:'Poppins',sans-serif!important}</style>
</head>
<body class="bg-[#0a0f1a] text-white">
<?php include __DIR__ . '/../components/vendor-sidebar.php'; ?>
<div class="main-content">
  <div class="topbar">
    <button onclick="toggleSidebar()" class="md:hidden text-gray-400 text-2xl mr-3">☰</button>
    <h1 class="text-xl font-bold">Distribution History</h1>
  </div>
  <div class="p-6">
    <div class="flex gap-2 mb-5">
      <?php foreach(['distribution'=>'Distributions','redemption'=>'Redemptions'] as $t=>$l): ?>
      <a href="?tab=<?= $t ?>" class="btn btn-sm <?= $tab===$t?'btn-primary':'btn-secondary' ?>"><?= $l ?></a>
      <?php endforeach; ?>
    </div>
    <div id="hist-container" class="space-y-3"></div>
    <div id="hist-loader">
      <?php for($i=0;$i<5;$i++): ?><div class="skeleton h-16 rounded-xl mb-2"></div><?php endfor; ?>
    </div>
  </div>
</div>
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
<script>
new InfiniteScroll({
  container: document.getElementById('hist-container'),
  loader: document.getElementById('hist-loader'),
  fetchUrl: '<?= APP_URL ?>/ajax/vendor-history.php',
  params: { tab: '<?= e($tab) ?>' },
  renderItem: r => `<div class="card p-4 flex items-center gap-4">
    <div class="w-10 h-10 rounded-xl bg-orange-500/10 flex items-center justify-center text-xl">🎟️</div>
    <div class="flex-1 min-w-0">
      <div class="font-medium text-sm">${r.user_name}</div>
      <div class="text-xs text-gray-400">${r.phone} · <span class="font-mono text-orange-400">${r.code}</span></div>
    </div>
    <div class="text-xs text-gray-500 flex-shrink-0">${r.date}</div>
  </div>`
});
</script>
</body></html>

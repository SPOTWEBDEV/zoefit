<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
$auth = requireUser(); $userId = $auth['id'];
$currentPage = 'codes'; $pageTitle = 'My Codes';
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
    <button onclick="toggleSidebar()" class="md:hidden text-gray-400 text-2xl mr-3">☰</button>
    <h1 class="text-xl font-bold">My Codes</h1>
  </div>
  <div class="p-6">
    <!-- Filter Tabs -->
    <div class="flex gap-2 mb-6 overflow-x-auto pb-2">
      <?php foreach (['all'=>'All','redeemed'=>'Active','reserved'=>'In Draw','used'=>'Used','transferred'=>'Transferred'] as $val=>$label): ?>
      <button onclick="filterCodes('<?= $val ?>')" data-filter="<?= $val ?>" class="filter-btn btn btn-sm btn-secondary whitespace-nowrap <?= $val==='all'?'!border-orange-500 !text-orange-400':'' ?>">
        <?= $label ?>
      </button>
      <?php endforeach; ?>
    </div>

    <!-- Codes Container -->
    <div id="codes-container" class="grid gap-3"></div>
    <div id="codes-loader" class="py-6 text-center text-gray-500 text-sm">
      <div class="skeleton h-16 rounded-xl mb-3"></div>
      <div class="skeleton h-16 rounded-xl mb-3"></div>
      <div class="skeleton h-16 rounded-xl"></div>
    </div>
  </div>
</div>
<script>
  window.APP_URL = '<?= APP_URL ?>';
</script>
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
<script>
let currentFilter = 'all';
let scroller = null;

function renderCode(item) {
  const statusColors = {
    redeemed: 'badge-success', reserved: 'badge-warning',
    used: 'badge-muted', transferred: 'badge-info', unassigned: 'badge-muted'
  };
  const statusLabels = {
    redeemed: 'Active', reserved: 'In Draw', used: 'Used', transferred: 'Transferred'
  };
  return `<div class="code-item fade-in flex items-center justify-between gap-4">
    <div>
      <div class="code-number tracking-widest mb-1">${item.code}</div>
      <div class="text-xs text-gray-500">${item.date_added}</div>
    </div>
    <span class="badge ${statusColors[item.status]||'badge-muted'}">${statusLabels[item.status]||item.status}</span>
  </div>`;
}

function filterCodes(filter) {
  currentFilter = filter;
  document.querySelectorAll('.filter-btn').forEach(b => {
    b.classList.toggle('!border-orange-500', b.dataset.filter===filter);
    b.classList.toggle('!text-orange-400', b.dataset.filter===filter);
  });
  document.getElementById('codes-container').innerHTML = '';
  document.getElementById('codes-loader').innerHTML = `<div class="skeleton h-16 rounded-xl mb-3"></div><div class="skeleton h-16 rounded-xl mb-3"></div><div class="skeleton h-16 rounded-xl"></div>`;
  if (scroller) scroller.done = false;
  initScroller();
}

function initScroller() {
  const loader = document.getElementById('codes-loader');
  loader.innerHTML = '';
  scroller = new InfiniteScroll({
    container: document.getElementById('codes-container'),
    loader,
    fetchUrl: '<?= APP_URL ?>/ajax/codes.php',
    renderItem: renderCode,
    params: { filter: currentFilter }
  });
}

document.addEventListener('DOMContentLoaded', initScroller);
</script>
</body></html>

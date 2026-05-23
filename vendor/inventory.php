<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
$auth = requireVendor(); $vendorId = $auth['id'];
$db = getDB();
$vendor = $db->prepare("SELECT * FROM vendors WHERE id=?");
$vendor->execute([$vendorId]); $vendor = $vendor->fetch();

$stats = [
  'total'       => $db->prepare("SELECT COUNT(*) FROM codes WHERE assigned_vendor=?")->execute([$vendorId]) ? 0 : 0,
  'assigned'    => 0,
  'distributed' => 0,
  'used'        => 0,
];
$s = $db->prepare("SELECT status, COUNT(*) as cnt FROM codes WHERE assigned_vendor=? GROUP BY status");
$s->execute([$vendorId]);
foreach ($s->fetchAll() as $row) {
    if ($row['status'] === 'assigned')   $stats['assigned']    = $row['cnt'];
    if (in_array($row['status'],['distributed','redeemed','reserved','used','transferred'])) $stats['distributed'] += $row['cnt'];
    if ($row['status'] === 'used')       $stats['used']        = $row['cnt'];
    $stats['total'] += $row['cnt'];
}

$filter = $_GET['filter'] ?? 'all';
$vPage = 'inventory';
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="csrf-token" content="<?= generateCsrf() ?>">
  <title>Inventory — <?= APP_NAME ?></title>
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
    <h1 class="text-xl font-bold">Code Inventory</h1>
  </div>
  <div class="p-6">
    <!-- Stats -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
      <?php $statCards = [
        ['Total Assigned','total','text-blue-400'],
        ['Available','assigned','text-green-400'],
        ['Distributed','distributed','text-orange-400'],
        ['Used/Consumed','used','text-gray-400'],
      ]; foreach ($statCards as $sc): ?>
      <div class="card p-5 text-center">
        <div class="text-2xl font-bold <?= $sc[2] ?>"><?= $stats[$sc[1]] ?></div>
        <div class="text-xs text-gray-500 mt-1"><?= $sc[0] ?></div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Filter tabs -->
    <div class="flex gap-2 mb-5 overflow-x-auto pb-1">
      <?php foreach(['all'=>'All','assigned'=>'Available','distributed'=>'Distributed','redeemed'=>'Redeemed','used'=>'Used'] as $v=>$l): ?>
      <button data-filter="<?= $v ?>" onclick="filterInv('<?= $v ?>')"
        class="filter-btn btn btn-sm <?= $filter===$v?'btn-primary':'btn-secondary' ?> whitespace-nowrap"><?= $l ?></button>
      <?php endforeach; ?>
    </div>

    <!-- Codes list -->
    <div id="inv-container" class="space-y-2"></div>
    <div id="inv-loader" class="space-y-2">
      <?php for($i=0;$i<5;$i++): ?><div class="skeleton h-14 rounded-xl"></div><?php endfor; ?>
    </div>
  </div>
</div>
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
<script>
let currentFilter = '<?= e($filter) ?>';
let scroller = null;

const statusBadge = {
  assigned:'badge-info', distributed:'badge-warning', redeemed:'badge-success',
  reserved:'badge-warning', used:'badge-muted', transferred:'badge-muted'
};

function filterInv(f) {
  currentFilter = f;
  document.querySelectorAll('.filter-btn').forEach(b => {
    const active = b.dataset.filter === f;
    b.className = `filter-btn btn btn-sm ${active?'btn-primary':'btn-secondary'} whitespace-nowrap`;
  });
  document.getElementById('inv-container').innerHTML = '';
  document.getElementById('inv-loader').innerHTML =
    Array(3).fill('<div class="skeleton h-14 rounded-xl"></div>').join('');
  scroller = null; initScroller();
}

function initScroller() {
  const loader = document.getElementById('inv-loader');
  loader.innerHTML = '';
  scroller = new InfiniteScroll({
    container: document.getElementById('inv-container'),
    loader,
    fetchUrl: '<?= APP_URL ?>/ajax/vendor-codes.php',
    params: { filter: currentFilter },
    renderItem: c => `<div class="code-item flex items-center justify-between gap-4">
      <div>
        <div class="code-number tracking-widest">${c.code}</div>
        <div class="text-xs text-gray-500 mt-1">${c.date}</div>
      </div>
      <span class="badge ${statusBadge[c.status]||'badge-muted'}">${c.status}</span>
    </div>`
  });
}

document.addEventListener('DOMContentLoaded', initScroller);
</script>
</body></html>

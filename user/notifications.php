<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
$auth = requireUser(); $userId = $auth['id'];
// Mark all as read
getDB()->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?")->execute([$userId]);
$currentPage='notifications'; $pageTitle='Notifications';
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="csrf-token" content="<?= generateCsrf() ?>">
  <title><?= e($pageTitle) ?> — <?= APP_NAME ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
  <style>*{font-family:'Poppins',sans-serif!important}</style>
</head>
<body class="bg-[#0a0f1a] text-white">
<?php include __DIR__ . '/../components/user-sidebar.php'; ?>
<div class="main-content">
  <div class="topbar">
    <button onclick="toggleSidebar()" class="md:hidden text-gray-400 text-2xl mr-3">☰</button>
    <h1 class="text-xl font-bold">Notifications</h1>
  </div>
  <div class="p-6 max-w-2xl mx-auto">
    <div id="notif-container" class="space-y-3"></div>
    <div id="notif-loader">
      <?php for($i=0;$i<4;$i++): ?><div class="skeleton h-20 rounded-xl mb-3"></div><?php endfor; ?>
    </div>
  </div>
</div>
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
<script>
const typeIcons={info:'ℹ️',success:'✅',warning:'⚠️',draw:'🎰',transfer:'↔️',redemption:'🎟️'};
new InfiniteScroll({
  container:document.getElementById('notif-container'),
  loader:document.getElementById('notif-loader'),
  fetchUrl:'<?= APP_URL ?>/ajax/notifications.php',
  params:{page_load:1},
  renderItem: n=>`<div class="card p-4 flex gap-4 ${n.is_read?'opacity-70':'border-orange-500/20'}">
    <div class="w-10 h-10 rounded-xl bg-orange-500/10 flex items-center justify-center text-xl flex-shrink-0">${typeIcons[n.type]||'🔔'}</div>
    <div class="flex-1">
      <div class="font-semibold text-sm">${n.title}</div>
      <div class="text-gray-400 text-sm mt-1">${n.message}</div>
      <div class="text-xs text-gray-600 mt-2">${n.date}</div>
    </div>
  </div>`
});
</script>
</body></html>

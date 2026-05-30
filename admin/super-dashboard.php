<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
$auth = requireSuperAdmin(); $saId = $auth['id'];
$db = getDB();

$stats = [
  'users'       => $db->query("SELECT COUNT(*) FROM users")->fetchColumn(),
  'admins'      => $db->query("SELECT COUNT(*) FROM admins")->fetchColumn(),
  'vendors'     => $db->query("SELECT COUNT(*) FROM vendors")->fetchColumn(),
  'codes_total' => $db->query("SELECT COUNT(*) FROM codes")->fetchColumn(),
  'codes_used'  => $db->query("SELECT COUNT(*) FROM codes WHERE status='used'")->fetchColumn(),
  'draws_total' => $db->query("SELECT COUNT(*) FROM draws")->fetchColumn(),
  'draws_active'=> $db->query("SELECT COUNT(*) FROM draws WHERE status='active'")->fetchColumn(),
  'winners'     => $db->query("SELECT COUNT(*) FROM draw_winners")->fetchColumn(),
  'entries'     => $db->query("SELECT COUNT(*) FROM draw_entries")->fetchColumn(),
  'logs_today'  => $db->query("SELECT COUNT(*) FROM audit_logs WHERE DATE(created_at)=CURDATE()")->fetchColumn(),
];

$recentLogs = $db->query("SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT 12")->fetchAll();
$pendingAdmins = $db->query("SELECT * FROM admins WHERE status='pending' ORDER BY created_at DESC")->fetchAll();

$saPage = 'dashboard';
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="csrf-token" content="<?= generateCsrf() ?>">
  <title>Super Admin — <?= APP_NAME ?></title>
  <script src="<?= APP_URL ?>/assets/js/app.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
  <style>*{font-family:'Poppins',sans-serif!important}</style>
</head>
<body class="bg-[#060b12] text-white">
<?php include __DIR__ . '/../components/super-sidebar.php'; ?>
<div class="main-content">
  <div class="topbar" style="background:#0a0f1a">
    <button onclick="toggleSidebar()" class="md:hidden text-gray-400 text-2xl mr-3">☰</button>
    <div>
      <div class="font-semibold text-red-400">⚡ Super Admin Control Panel</div>
      <div class="text-xs text-gray-500"><?= date('l, F j, Y — g:i A') ?></div>
    </div>
  </div>
  <div class="p-6">

    <!-- Alert for pending admins -->
    <?php if ($pendingAdmins): ?>
    <div class="bg-yellow-500/10 border border-yellow-500/30 rounded-xl p-4 mb-6 flex items-center gap-3">
      <span class="text-yellow-400 text-xl">⚠️</span>
      <div class="flex-1">
        <div class="font-semibold text-yellow-400 text-sm"><?= count($pendingAdmins) ?> Pending Admin Approval(s)</div>
        <div class="text-xs text-gray-400">New admin accounts require your approval.</div>
      </div>
      <a href="<?= APP_URL ?>/admin/super-admins.php" class="btn btn-sm btn-primary">Review</a>
    </div>
    <?php endif; ?>

    <!-- Stats Grid -->
    <div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-6">
      <?php $cards = [
        ['Users','users','text-blue-400','👥'],
        ['Admins','admins','text-purple-400','🛡️'],
        ['Vendors','vendors','text-green-400','🏪'],
        ['Total Codes','codes_total','text-orange-400','🎟️'],
        ['Active Draws','draws_active','text-red-400','🎯'],
        ['Total Draws','draws_total','text-cyan-400','🎰'],
        ['Winners','winners','text-yellow-400','🏆'],
        ['Entries','entries','text-pink-400','📝'],
        ['Codes Used','codes_used','text-gray-400','♻️'],
        ['Logs Today','logs_today','text-lime-400','📋'],
      ]; ?>
      <?php foreach ($cards as $c): ?>
      <div class="card p-4 text-center">
        <div class="text-2xl mb-1"><?= $c[3] ?></div>
        <div class="text-2xl font-black <?= $c[2] ?>"><?= number_format($stats[$c[1]]) ?></div>
        <div class="text-xs text-gray-500 mt-1"><?= $c[0] ?></div>
      </div>
      <?php endforeach; ?>
    </div>

    <div class="grid lg:grid-cols-2 gap-6">
      <!-- Quick Links -->
      <div class="card p-6">
        <h2 class="font-bold text-lg mb-4">Quick Actions</h2>
        <div class="grid grid-cols-2 gap-3">
          <?php $links = [
            [APP_URL.'/admin/super-admins.php','🛡️ Manage Admins','Approve/suspend admin accounts'],
            [APP_URL.'/admin/super-slides.php','🖼️ Edit Banners','Control homepage slideshow'],
            [APP_URL.'/admin/super-draws.php','🎯 All Draws','Oversee all draw campaigns'],
            [APP_URL.'/admin/super-audit.php','📋 Audit Logs','Full platform activity logs'],
            [APP_URL.'/admin/super-settings.php','⚙️ Settings','Platform configuration'],
            [APP_URL.'/admin/dashboard.php','🔧 Admin Panel','Switch to admin view'],
          ]; foreach($links as $l): ?>
          <a href="<?= $l[0] ?>" class="card p-4 hover:border-orange-500/40 transition-colors">
            <div class="text-xl mb-2"><?= substr($l[1],0,2) ?></div>
            <div class="font-semibold text-sm"><?= substr($l[1],3) ?></div>
            <div class="text-xs text-gray-500 mt-1"><?= $l[2] ?></div>
          </a>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Recent Audit Logs -->
      <div class="card">
        <div class="flex items-center justify-between p-5 border-b border-white/5">
          <h2 class="font-bold">Latest Activity</h2>
          <a href="<?= APP_URL ?>/admin/super-audit.php" class="text-sm text-orange-400 hover:underline">Full logs →</a>
        </div>
        <div class="divide-y divide-white/5 max-h-96 overflow-y-auto">
          <?php foreach ($recentLogs as $log): ?>
          <div class="flex items-start gap-3 p-3">
            <?php $colors=['user'=>'bg-blue-500/20 text-blue-400','admin'=>'bg-orange-500/20 text-orange-400','vendor'=>'bg-green-500/20 text-green-400','super_admin'=>'bg-red-500/20 text-red-400','system'=>'bg-gray-500/20 text-gray-400']; ?>
            <div class="w-8 h-8 rounded-lg <?= $colors[$log['actor_type']]??'bg-gray-500/20 text-gray-400' ?> flex items-center justify-center text-xs font-bold flex-shrink-0 capitalize">
              <?= strtoupper($log['actor_type'][0]) ?>
            </div>
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2">
                <code class="text-orange-400 text-xs"><?= e($log['action']) ?></code>
                <span class="text-xs text-gray-600">#<?= $log['actor_id'] ?></span>
              </div>
              <div class="text-xs text-gray-500 truncate"><?= e($log['description']) ?></div>
            </div>
            <div class="text-xs text-gray-600 flex-shrink-0"><?= date('g:ia', strtotime($log['created_at'])) ?></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</div>
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
</body></html>

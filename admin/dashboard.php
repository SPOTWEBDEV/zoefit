<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
$auth = requireAdmin(); $adminId = $auth['id'];
$db = getDB();

// Platform stats
$stats = [];
$stats['users']    = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
$stats['vendors']  = $db->query("SELECT COUNT(*) FROM vendors WHERE status='active'")->fetchColumn();
$stats['codes']    = $db->query("SELECT COUNT(*) FROM codes")->fetchColumn();
$stats['draws']    = $db->query("SELECT COUNT(*) FROM draws WHERE status='active'")->fetchColumn();
$stats['entries']  = $db->query("SELECT COUNT(*) FROM draw_entries")->fetchColumn();
$stats['winners']  = $db->query("SELECT COUNT(*) FROM draw_winners")->fetchColumn();
$stats['new_users_today'] = $db->query("SELECT COUNT(*) FROM users WHERE DATE(created_at)=CURDATE()")->fetchColumn();
$stats['codes_today'] = $db->query("SELECT COUNT(*) FROM code_redemptions WHERE DATE(redeemed_at)=CURDATE()")->fetchColumn();

// Recent users
$recentUsers = $db->query("SELECT id,full_name,phone,balance,status,created_at FROM users ORDER BY created_at DESC LIMIT 8")->fetchAll();

// Active draws
$activeDraws = $db->query("SELECT d.*, (SELECT COUNT(*) FROM draw_entries WHERE draw_id=d.id) as entry_count FROM draws d WHERE d.status='active' ORDER BY d.end_date ASC LIMIT 5")->fetchAll();

// Recent audit logs
$logs = $db->query("SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT 8")->fetchAll();

$aPage = 'dashboard'; $pageTitle = 'Admin Dashboard';
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
<?php include __DIR__ . '/../components/admin-sidebar.php'; ?>
<div class="main-content">
  <!-- Topbar -->
  <div class="topbar">
    <button onclick="toggleSidebar()" class="md:hidden text-gray-400 text-2xl mr-3">☰</button>
    <div>
      <div class="font-semibold">Admin Dashboard</div>
      <div class="text-xs text-gray-400"><?= date('l, F j, Y') ?></div>
    </div>
    <div class="flex items-center gap-3">
      <a href="<?= APP_URL ?>/admin/codes.php?action=generate" class="btn btn-primary btn-sm">+ Generate Codes</a>
    </div>
  </div>

  <div class="p-6">
    <!-- Stats Grid -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
      <?php $cards = [
        ['Total Users',      $stats['users'],        'text-blue-400',   '👥', APP_URL.'/admin/users.php'],
        ['Active Draws',     $stats['draws'],        'text-green-400',  '🎯', APP_URL.'/admin/draws.php'],
        ['Total Codes',      $stats['codes'],        'text-orange-400', '🎟️', APP_URL.'/admin/codes.php'],
        ['Draw Winners',     $stats['winners'],      'text-yellow-400', '🏆', APP_URL.'/admin/draws.php'],
        ['Active Vendors',   $stats['vendors'],      'text-purple-400', '🏪', APP_URL.'/admin/vendors.php'],
        ['Draw Entries',     $stats['entries'],      'text-cyan-400',   '📝', '#'],
        ['New Users Today',  $stats['new_users_today'],'text-pink-400', '✨', APP_URL.'/admin/users.php'],
        ['Redemptions Today',$stats['codes_today'],  'text-lime-400',   '♻️', '#'],
      ]; ?>
      <?php foreach ($cards as $c): ?>
      <a href="<?= $c[4] ?>" class="card p-5 hover:border-orange-500/30">
        <div class="flex items-start justify-between mb-3">
          <div class="text-2xl"><?= $c[3] ?></div>
          <div class="text-3xl font-black <?= $c[2] ?>"><?= number_format($c[1]) ?></div>
        </div>
        <div class="text-xs text-gray-500"><?= $c[0] ?></div>
      </a>
      <?php endforeach; ?>
    </div>

    <div class="grid lg:grid-cols-2 gap-6 mb-6">
      <!-- Recent Users -->
      <div class="card">
        <div class="flex items-center justify-between p-5 border-b border-white/5">
          <h2 class="font-bold">Recent Users</h2>
          <a href="<?= APP_URL ?>/admin/users.php" class="text-sm text-orange-400 hover:underline">View all →</a>
        </div>
        <div class="divide-y divide-white/5">
          <?php foreach ($recentUsers as $u): ?>
          <div class="flex items-center gap-3 p-4">
            <div class="w-9 h-9 bg-orange-500/15 rounded-xl flex items-center justify-center font-bold text-orange-400 text-sm flex-shrink-0"><?= strtoupper($u['full_name'][0]) ?></div>
            <div class="flex-1 min-w-0">
              <div class="text-sm font-medium truncate"><?= e($u['full_name']) ?></div>
              <div class="text-xs text-gray-500"><?= e(formatPhone($u['phone'])) ?></div>
            </div>
            <div class="text-right flex-shrink-0">
              <div class="text-xs text-orange-400 font-semibold"><?= $u['balance'] ?> codes</div>
              <span class="badge <?= $u['status']==='active'?'badge-success':'badge-danger' ?> text-xs"><?= $u['status'] ?></span>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Active Draws -->
      <div class="card">
        <div class="flex items-center justify-between p-5 border-b border-white/5">
          <h2 class="font-bold">Active Draws</h2>
          <a href="<?= APP_URL ?>/admin/draws.php" class="text-sm text-orange-400 hover:underline">Manage →</a>
        </div>
        <div class="divide-y divide-white/5">
          <?php if ($activeDraws): foreach ($activeDraws as $d): ?>
          <div class="p-4">
            <div class="flex items-start justify-between gap-3 mb-2">
              <div class="font-medium text-sm"><?= e($d['title']) ?></div>
              <span class="badge badge-success flex-shrink-0">LIVE</span>
            </div>
            <div class="flex items-center gap-4 text-xs text-gray-400">
              <span>📝 <?= $d['entry_count'] ?> entries</span>
              <span>⏰ Ends <?= date('M j', strtotime($d['end_date'])) ?></span>
            </div>
            <div class="flex gap-2 mt-3">
              <a href="<?= APP_URL ?>/admin/draw-manage.php?id=<?= $d['id'] ?>" class="btn btn-sm btn-secondary text-xs">Manage</a>
              <a href="<?= APP_URL ?>/admin/live-draw-admin.php?id=<?= $d['id'] ?>" class="btn btn-sm btn-primary text-xs">🔴 Live Draw</a>
            </div>
          </div>
          <?php endforeach; else: ?>
          <div class="p-8 text-center text-gray-500 text-sm">No active draws. <a href="<?= APP_URL ?>/admin/draws.php?action=create" class="text-orange-400 hover:underline">Create one →</a></div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Recent Audit Logs -->
    <div class="card">
      <div class="flex items-center justify-between p-5 border-b border-white/5">
        <h2 class="font-bold">Recent Activity</h2>
        <a href="<?= APP_URL ?>/admin/audit-logs.php" class="text-sm text-orange-400 hover:underline">Full logs →</a>
      </div>
      <div class="table-wrap">
        <table>
          <thead><tr>
            <th>Actor</th><th>Action</th><th>Description</th><th>IP</th><th>Time</th>
          </tr></thead>
          <tbody>
            <?php foreach ($logs as $log): ?>
            <tr>
              <td><span class="badge badge-info capitalize"><?= e($log['actor_type']) ?> #<?= $log['actor_id'] ?></span></td>
              <td><code class="text-orange-400 text-xs"><?= e($log['action']) ?></code></td>
              <td class="text-gray-400 text-xs max-w-xs truncate"><?= e($log['description']) ?></td>
              <td class="text-gray-500 text-xs"><?= e($log['ip_address']) ?></td>
              <td class="text-gray-500 text-xs whitespace-nowrap"><?= date('M j, g:ia', strtotime($log['created_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
</body></html>

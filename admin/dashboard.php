<?php
// admin/dashboard.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
$auth = requireAdmin(); $adminId = $auth['id'];
$db = getDB();

$stats = [
  'users'            => $db->query("SELECT COUNT(*) FROM users")->fetchColumn(),
  'vendors_active'   => $db->query("SELECT COUNT(*) FROM vendors WHERE status='active'")->fetchColumn(),
  'vendors_pending'  => $db->query("SELECT COUNT(*) FROM vendors WHERE status='pending'")->fetchColumn(),
  'codes_total'      => $db->query("SELECT COUNT(*) FROM codes")->fetchColumn(),
  'codes_unassigned' => $db->query("SELECT COUNT(*) FROM codes WHERE status='unassigned'")->fetchColumn(),
  'draws_active'     => $db->query("SELECT COUNT(*) FROM draws WHERE status='active'")->fetchColumn(),
  'draw_entries'     => $db->query("SELECT COUNT(*) FROM draw_entries")->fetchColumn(),
  'winners'          => $db->query("SELECT COUNT(*) FROM draw_winners")->fetchColumn(),
  'new_users_today'  => $db->query("SELECT COUNT(*) FROM users WHERE DATE(created_at)=CURDATE()")->fetchColumn(),
  'redemptions_today'=> $db->query("SELECT COUNT(*) FROM code_redemptions WHERE DATE(redeemed_at)=CURDATE()")->fetchColumn(),
];

// Recent users — clean users table, no vendor columns
$recentUsers = $db->query(
  "SELECT id, full_name, phone, balance, status, created_at
   FROM users ORDER BY created_at DESC LIMIT 8"
)->fetchAll();

$activeDraws = $db->query(
  "SELECT d.*, (SELECT COUNT(*) FROM draw_entries WHERE draw_id=d.id) as entry_count
   FROM draws d WHERE d.status='active' ORDER BY d.end_date ASC LIMIT 4"
)->fetchAll();

// Pending vendors — now from vendors table
$pendingVendors = $db->query(
  "SELECT id, full_name, phone, business_name, applied_at
   FROM vendors WHERE status='pending' ORDER BY applied_at ASC LIMIT 5"
)->fetchAll();

$recentLogs = $db->query(
  "SELECT * FROM audit_logs ORDER BY created_at DESC LIMIT 6"
)->fetchAll();

$aPage = 'dashboard';
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="csrf-token" content="<?= generateCsrf() ?>">
  <title>Admin Dashboard — <?= APP_NAME ?></title>
  <script src="<?= APP_URL ?>/assets/js/tailwind.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
  <style>
    *{font-family:'Poppins',sans-serif!important}
    .stat-card{background:var(--bg-card);border:1px solid var(--border);border-radius:16px;padding:18px;transition:all .2s;text-decoration:none;display:block}
    .stat-card:hover{border-color:rgba(249,115,22,.3);transform:translateY(-2px)}
  </style>
</head>
<body class="bg-[#0a0f1a] text-white">
<?php include __DIR__ . '/../components/admin-sidebar.php'; ?>
<div class="main-content">
  <div class="topbar">
    <button onclick="toggleSidebar()" class="md:hidden text-gray-400 text-2xl mr-3">☰</button>
    <div>
      <div class="font-bold">Admin Dashboard</div>
      <div class="text-xs text-gray-400"><?= date('l, F j, Y') ?></div>
    </div>
    <div class="flex items-center gap-2">
      <?php if ($stats['vendors_pending'] > 0): ?>
      <a href="<?= APP_URL ?>/admin/vendor-requests.php"
         class="flex items-center gap-1.5 bg-orange-500/15 border border-orange-500/30 text-orange-400 rounded-xl px-3 py-1.5 text-xs font-semibold hover:bg-orange-500/25 transition-colors">
        <span class="pulse-dot w-2 h-2"></span>
        <?= $stats['vendors_pending'] ?> Vendor Request<?= $stats['vendors_pending'] > 1 ? 's' : '' ?>
      </a>
      <?php endif; ?>
      <a href="<?= APP_URL ?>/admin/codes.php" class="btn btn-primary btn-sm">+ Generate Codes</a>
    </div>
  </div>

  <div class="p-4 md:p-6">

    <!-- Stats Grid -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 mb-6">
      <?php $cards = [
        ['Users',            $stats['users'],             'text-blue-400',   '👥', APP_URL.'/admin/users.php'],
        ['Active Vendors',   $stats['vendors_active'],    'text-purple-400', '🏪', APP_URL.'/admin/vendors.php'],
        ['Pending Requests', $stats['vendors_pending'],   'text-yellow-400', '⏳', APP_URL.'/admin/vendor-requests.php'],
        ['Total Codes',      $stats['codes_total'],       'text-orange-400', '🎟️', APP_URL.'/admin/codes.php'],
        ['Active Draws',     $stats['draws_active'],      'text-green-400',  '🎯', APP_URL.'/admin/draws.php'],
        ['Draw Entries',     $stats['draw_entries'],      'text-cyan-400',   '📝', '#'],
        ['Winners',          $stats['winners'],           'text-yellow-300', '🏆', '#'],
        ['Unassigned',       $stats['codes_unassigned'],  'text-gray-400',   '📦', APP_URL.'/admin/codes.php?filter=unassigned'],
        ['New Today',        $stats['new_users_today'],   'text-pink-400',   '✨', APP_URL.'/admin/users.php'],
        ['Redeemed Today',   $stats['redemptions_today'], 'text-lime-400',   '♻️', '#'],
      ]; foreach ($cards as $c): ?>
      <a href="<?= $c[4] ?>" class="stat-card">
        <div class="flex items-start justify-between mb-1">
          <div class="text-xl"><?= $c[3] ?></div>
          <div class="text-2xl font-black <?= $c[2] ?>"><?= number_format((int)$c[1]) ?></div>
        </div>
        <div class="text-xs text-gray-500"><?= $c[0] ?></div>
      </a>
      <?php endforeach; ?>
    </div>

    <div class="grid lg:grid-cols-3 gap-5 mb-5">

      <!-- Recent Users — customer accounts only -->
      <div class="lg:col-span-2 card">
        <div class="flex items-center justify-between px-5 pt-5 pb-3 border-b border-white/5">
          <h2 class="font-bold">Recent Registrations</h2>
          <a href="<?= APP_URL ?>/admin/users.php" class="text-sm text-orange-400 hover:underline">All →</a>
        </div>
        <div class="divide-y divide-white/5">
          <?php foreach ($recentUsers as $u): ?>
          <div class="flex items-center gap-3 px-5 py-3">
            <div class="w-9 h-9 rounded-xl flex-shrink-0 flex items-center justify-center font-bold text-sm bg-orange-500/15 text-orange-400">
              <?= strtoupper($u['full_name'][0]) ?>
            </div>
            <div class="flex-1 min-w-0">
              <div class="text-sm font-medium truncate"><?= e($u['full_name']) ?></div>
              <div class="text-xs text-gray-500"><?= e(formatPhone($u['phone'])) ?></div>
            </div>
            <div class="text-right flex-shrink-0">
              <div class="text-xs text-orange-400 font-semibold"><?= $u['balance'] ?> codes</div>
              <span class="badge <?= $u['status'] === 'active' ? 'badge-success' : 'badge-danger' ?> text-xs">
                <?= $u['status'] ?>
              </span>
            </div>
          </div>
          <?php endforeach; ?>
          <?php if (!$recentUsers): ?>
          <div class="p-8 text-center text-gray-500 text-sm">No users yet</div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Pending Vendor Requests — from vendors table -->
      <div class="card">
        <div class="flex items-center justify-between px-5 pt-5 pb-3 border-b border-white/5">
          <h2 class="font-bold flex items-center gap-2">
            Vendor Requests
            <?php if ($stats['vendors_pending'] > 0): ?>
            <span class="w-5 h-5 bg-orange-500 rounded-full text-xs text-white flex items-center justify-center font-bold">
              <?= $stats['vendors_pending'] ?>
            </span>
            <?php endif; ?>
          </h2>
          <a href="<?= APP_URL ?>/admin/vendor-requests.php" class="text-sm text-orange-400 hover:underline">All →</a>
        </div>
        <?php if ($pendingVendors): ?>
        <div class="divide-y divide-white/5">
          <?php foreach ($pendingVendors as $v): ?>
          <div class="px-5 py-3">
            <div class="flex items-start gap-2 mb-2">
              <div class="w-8 h-8 bg-yellow-500/20 rounded-lg flex items-center justify-center text-xs font-bold text-yellow-400 flex-shrink-0">
                <?= strtoupper($v['full_name'][0]) ?>
              </div>
              <div class="min-w-0">
                <div class="text-sm font-medium truncate"><?= e($v['full_name']) ?></div>
                <div class="text-xs text-gray-400 truncate"><?= e($v['business_name'] ?? '—') ?></div>
              </div>
            </div>
            <div class="flex gap-1.5">
              <a href="<?= APP_URL ?>/admin/vendor-requests.php?quick_approve=<?= $v['id'] ?>"
                 class="btn btn-sm text-xs flex-1 py-1.5"
                 style="background:#22c55e;color:white"
                 onclick="return confirm('Approve <?= e(addslashes($v['full_name'])) ?>?')">
                ✓ Approve
              </a>
              <a href="<?= APP_URL ?>/admin/vendor-requests.php?quick_reject=<?= $v['id'] ?>"
                 class="btn btn-sm btn-secondary text-xs py-1.5 px-3 text-red-400"
                 onclick="return confirm('Reject this application?')">✕</a>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="p-8 text-center">
          <div class="text-3xl mb-2">✅</div>
          <div class="text-sm text-gray-500">No pending requests</div>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Active Draws -->
    <?php if ($activeDraws): ?>
    <div class="card mb-5">
      <div class="flex items-center justify-between px-5 pt-5 pb-3 border-b border-white/5">
        <h2 class="font-bold flex items-center gap-2"><span class="pulse-dot"></span> Active Draws</h2>
        <a href="<?= APP_URL ?>/admin/draws.php" class="text-sm text-orange-400 hover:underline">Manage →</a>
      </div>
      <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-0 divide-y sm:divide-y-0 sm:divide-x divide-white/5">
        <?php foreach ($activeDraws as $d): ?>
        <div class="p-5">
          <span class="badge badge-success mb-2">LIVE</span>
          <div class="font-semibold text-sm mb-2 line-clamp-2"><?= e($d['title']) ?></div>
          <div class="text-xs text-gray-400 mb-1">📝 <?= number_format($d['entry_count']) ?> entries</div>
          <div class="text-xs text-gray-400 mb-3">⏰ Ends <?= date('M j', strtotime($d['end_date'])) ?></div>
          <div class="flex gap-1.5">
            <a href="<?= APP_URL ?>/admin/live-draw-admin.php?id=<?= $d['id'] ?>"
               class="btn btn-primary btn-sm text-xs flex-1 text-center py-1.5">🔴 Live</a>
            <a href="<?= APP_URL ?>/admin/draw-manage.php?id=<?= $d['id'] ?>"
               class="btn btn-secondary btn-sm text-xs py-1.5 px-3">⚙</a>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Recent Audit Logs -->
    <div class="card">
      <div class="flex items-center justify-between px-5 pt-5 pb-3 border-b border-white/5">
        <h2 class="font-bold">Recent Activity</h2>
        <a href="<?= APP_URL ?>/admin/audit-logs.php" class="text-sm text-orange-400 hover:underline">Full logs →</a>
      </div>
      <?php $ac = [
        'user'        => 'bg-blue-500/20 text-blue-400',
        'admin'       => 'bg-orange-500/20 text-orange-400',
        'vendor'      => 'bg-green-500/20 text-green-400',
        'super_admin' => 'bg-red-500/20 text-red-400',
        'system'      => 'bg-gray-500/20 text-gray-400',
      ]; ?>
      <div class="divide-y divide-white/5">
        <?php foreach ($recentLogs as $l): ?>
        <div class="flex items-center gap-3 px-5 py-3">
          <div class="w-7 h-7 rounded-lg <?= $ac[$l['actor_type']] ?? 'bg-gray-500/20 text-gray-400' ?> flex items-center justify-center text-xs font-bold flex-shrink-0">
            <?= strtoupper($l['actor_type'][0]) ?>
          </div>
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2">
              <code class="text-orange-400 text-xs"><?= e($l['action']) ?></code>
              <span class="text-xs text-gray-600">#<?= $l['actor_id'] ?></span>
            </div>
            <div class="text-xs text-gray-500 truncate"><?= e($l['description'] ?? '') ?></div>
          </div>
          <div class="text-xs text-gray-600 flex-shrink-0"><?= date('g:ia', strtotime($l['created_at'])) ?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

  </div>
</div>
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
</body>
</html>
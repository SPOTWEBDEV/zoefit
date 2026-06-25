<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
$auth = requireUser(); $userId = $auth['id'];
$db   = getDB();
$stmt = $db->prepare("SELECT * FROM users WHERE id=?");
$stmt->execute([$userId]); $user=$stmt->fetch();

$services=$db->query("SELECT * FROM services WHERE status='active' ORDER BY sort_order ASC")->fetchAll();
// Stats for this user
$s=$db->prepare("SELECT COUNT(*) FROM codes WHERE current_owner=? AND status='redeemed'");$s->execute([$userId]);$activeCodes=$s->fetchColumn();
$s=$db->prepare("SELECT COUNT(*) FROM draw_entries WHERE user_id=?");$s->execute([$userId]);$totalEntries=$s->fetchColumn();
$s=$db->prepare("SELECT COUNT(*) FROM draws WHERE status='active'");$s->execute();$liveDraws=$s->fetchColumn();
$s=$db->prepare("SELECT COUNT(*) FROM draw_winners WHERE user_id=?");$s->execute([$userId]);$wins=$s->fetchColumn();

$currentPage='services'; $pageTitle='Services';
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= e($pageTitle) ?> — <?= APP_NAME ?></title>
  <script src="<?= APP_URL ?>/assets/js/tailwind.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
  <style>
    *{font-family:'Poppins',sans-serif!important}
    .service-card{background:var(--bg-card);border:1px solid var(--border);border-radius:20px;padding:24px;transition:all .3s;cursor:pointer;text-decoration:none;display:block;}
    .service-card:hover{transform:translateY(-4px);border-color:rgba(249,115,22,.35);box-shadow:0 16px 40px rgba(0,0,0,.4);}
    .service-icon{width:56px;height:56px;border-radius:16px;display:flex;align-items:center;justify-content:center;font-size:28px;margin-bottom:16px;}
  </style>
</head>
<body class="bg-[#0a0f1a] text-white">
<?php include __DIR__ . '/../components/user-sidebar.php'; ?>
<div class="main-content">
  <div class="topbar">
    <button onclick="toggleSidebar()" class="md:hidden text-gray-400 text-2xl mr-3">☰</button>
    <h1 class="text-xl font-bold">Services</h1>
  </div>
  <div class="p-4 md:p-6 pb-24 md:pb-6">

    <!-- Hero banner -->
    <div class="balance-card mb-6 relative overflow-hidden">
      <div class="absolute top-0 right-0 w-48 h-48 opacity-10" style="background:radial-gradient(circle,white,transparent);transform:translate(30%,-30%)"></div>
      <div class="relative z-10">
        <div class="text-sm text-orange-200 font-medium mb-1">Welcome back,</div>
        <div class="text-2xl font-black mb-3"><?= e(explode(' ',$user['full_name'])[0]) ?> 👋</div>
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
          <?php $qs=[['🎟️',$activeCodes,'Active Codes'],['🎯',$liveDraws,'Live Draws'],['📋',$totalEntries,'My Entries'],['🏆',$wins,'Wins']]; ?>
          <?php foreach($qs as $q): ?>
          <div class="bg-white/10 rounded-xl p-3 text-center">
            <div class="text-lg"><?= $q[0] ?></div>
            <div class="text-xl font-black"><?= $q[1] ?></div>
            <div class="text-xs text-orange-200"><?= $q[2] ?></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- Services heading -->
    <div class="flex items-center justify-between mb-5">
      <h2 class="text-xl font-bold">What can we help with?</h2>
    </div>

    <!-- Services Grid -->
    <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mb-8">

      <a href="<?= APP_URL ?>/user/draws.php" class="service-card">
        <div class="service-icon" style="background:linear-gradient(135deg,rgba(249,115,22,.25),rgba(234,88,12,.1))">🎯</div>
        <div class="font-bold text-base mb-1">Raffle Draws</div>
        <div class="text-gray-400 text-xs leading-relaxed">Enter live draws with your codes and win amazing prizes.</div>
        <div class="mt-3 flex items-center gap-1 text-orange-400 text-xs font-semibold">
          <?= $liveDraws ?> live now <span class="pulse-dot w-2 h-2 ml-1"></span>
        </div>
      </a>

      <a href="<?= APP_URL ?>/user/redeem.php" class="service-card">
        <div class="service-icon" style="background:linear-gradient(135deg,rgba(59,130,246,.25),rgba(37,99,235,.1))">🎟️</div>
        <div class="font-bold text-base mb-1">Redeem Code</div>
        <div class="text-gray-400 text-xs leading-relaxed">Add your 15-digit raffle code to your wallet instantly.</div>
        <div class="mt-3 text-blue-400 text-xs font-semibold">Instant activation →</div>
      </a>

      <a href="<?= APP_URL ?>/user/transfer.php" class="service-card">
        <div class="service-icon" style="background:linear-gradient(135deg,rgba(34,197,94,.25),rgba(22,163,74,.1))">↔️</div>
        <div class="font-bold text-base mb-1">Transfer Code</div>
        <div class="text-gray-400 text-xs leading-relaxed">Send your raffle codes to any registered user by phone number.</div>
        <div class="mt-3 text-green-400 text-xs font-semibold">PIN protected →</div>
      </a>

      <a href="<?= APP_URL ?>/user/codes.php" class="service-card">
        <div class="service-icon" style="background:linear-gradient(135deg,rgba(168,85,247,.25),rgba(139,92,246,.1))">📦</div>
        <div class="font-bold text-base mb-1">My Codes</div>
        <div class="text-gray-400 text-xs leading-relaxed">View, manage and track all your raffle codes in one place.</div>
        <div class="mt-3 text-purple-400 text-xs font-semibold"><?= $activeCodes ?> active codes →</div>
      </a>

      <a href="<?= APP_URL ?>/user/transactions.php" class="service-card">
        <div class="service-icon" style="background:linear-gradient(135deg,rgba(245,158,11,.25),rgba(217,119,6,.1))">📊</div>
        <div class="font-bold text-base mb-1">Transaction History</div>
        <div class="text-gray-400 text-xs leading-relaxed">Full history of all your redemptions, transfers, and draw entries.</div>
        <div class="mt-3 text-yellow-400 text-xs font-semibold">View history →</div>
      </a>

      <?php if (!$user['is_vendor'] || $user['vendor_status']!=='active'): ?>
      <a href="<?= APP_URL ?>/user/vendor-apply.php" class="service-card" style="border-color:rgba(168,85,247,.2)">
        <div class="service-icon" style="background:linear-gradient(135deg,rgba(168,85,247,.25),rgba(139,92,246,.1))">🏪</div>
        <div class="font-bold text-base mb-1 text-purple-300">Become Vendor</div>
        <div class="text-gray-400 text-xs leading-relaxed">Apply to distribute codes and earn as a ZoeFeeds vendor.</div>
        <div class="mt-3">
          <?php if($user['vendor_status']==='pending'): ?>
          <span class="badge badge-warning text-xs">⏳ Under Review</span>
          <?php else: ?>
          <span class="text-purple-400 text-xs font-semibold">Apply now →</span>
          <?php endif; ?>
        </div>
      </a>
      <?php else: ?>
      <a href="<?= APP_URL ?>/user/vendor-panel.php" class="service-card" style="border-color:rgba(168,85,247,.2)">
        <div class="service-icon" style="background:linear-gradient(135deg,rgba(168,85,247,.25),rgba(139,92,246,.1))">🏪</div>
        <div class="font-bold text-base mb-1 text-purple-300">Vendor Panel</div>
        <div class="text-gray-400 text-xs leading-relaxed">Distribute codes, manage inventory, and access API keys.</div>
        <div class="mt-3"><span class="badge" style="background:rgba(168,85,247,.2);color:#a855f7">Active Vendor ✓</span></div>
      </a>
      <?php endif; ?>
    </div>

    <!-- Custom services from DB -->
    <?php if($services): ?>
    <h2 class="text-lg font-bold mb-4">More Services</h2>
    <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mb-8">
      <?php foreach($services as $svc): ?>
      <a href="<?= e($svc['link_url']??'#') ?>" class="service-card">
        <div class="service-icon bg-gradient-to-br <?= e($svc['color_class']??'from-orange-500/20 to-red-500/10') ?>"><?= e($svc['icon']??'⭐') ?></div>
        <div class="font-bold text-sm mb-1"><?= e($svc['title']) ?></div>
        <div class="text-gray-400 text-xs leading-relaxed"><?= e($svc['description']??'') ?></div>
      </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Support -->
    <div class="card p-5 flex items-center gap-4">
      <div class="w-12 h-12 bg-orange-500/15 rounded-2xl flex items-center justify-center text-2xl flex-shrink-0">💬</div>
      <div class="flex-1">
        <div class="font-bold">Need Help?</div>
        <div class="text-sm text-gray-400 mt-0.5">Contact our support team for any questions about your account or services.</div>
      </div>
      <a href="mailto:support@zoefeeds.com" class="btn btn-secondary btn-sm flex-shrink-0">Contact Us</a>
    </div>
  </div>
</div>
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
</body></html>

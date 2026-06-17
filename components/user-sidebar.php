
<?php
// components/user-sidebar.php
// Vendor nav shows automatically when the logged-in user is an approved vendor.
// No version flags. Logic is purely: is_vendor=1 AND vendor_status='active'

$currentPage  = $currentPage ?? '';

// Sync vendor status from DB into session on every page load
$isVendor     = false;
$vendorStatus = null;

if (!empty($_SESSION['user_id'])) {
  try {
    $db = getDB();
    $sv = $db->prepare("SELECT is_vendor, vendor_status FROM users WHERE id=?");
    $sv->execute([$_SESSION['user_id']]);
    $sv = $sv->fetch();
    if ($sv) {
      $_SESSION['is_vendor']     = (bool)$sv['is_vendor'];
      $_SESSION['vendor_status'] = $sv['vendor_status'];
    }
  } catch(\Exception $e) {}

  $isVendor     = !empty($_SESSION['is_vendor']);
  $vendorStatus = $_SESSION['vendor_status'] ?? null;
}
?>

<!-- ── Desktop / Tablet Sidebar ─────────────────────────── -->
<nav class="sidebar flex flex-col" id="user-sidebar">

  <div class="sidebar-logo flex items-center gap-2">
    <div class="w-8 h-8 bg-orange-500 rounded-lg flex items-center justify-center font-black text-white text-sm">Z</div>
    <div class="text-lg font-bold leading-tight"><span class="text-orange-500">ZOE</span><span class="text-white">FEEDS</span></div>
  </div>

  <div class="flex-1 py-3 overflow-y-auto space-y-0.5">

    <!-- ── MAIN ───────────────────────────────────────── -->
    <div class="px-4 pt-1 pb-1 text-xs font-semibold text-gray-600 uppercase tracking-wider">Main</div>

    <a href="<?= APP_URL ?>/user/dashboard.php" class="nav-item <?= $currentPage==='dashboard'?'active':'' ?>">
      <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
      Dashboard
    </a>

    <a href="<?= APP_URL ?>/user/draws.php" class="nav-item <?= $currentPage==='draws'?'active':'' ?>">
      <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
      Live Draws
    </a>

    <a href="<?= APP_URL ?>/user/past-winners.php" class="nav-item <?= $currentPage==='past-winners'?'active':'' ?>">
      <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/></svg>
      Past Winners
    </a>

    <a href="<?= APP_URL ?>/user/redeem.php" class="nav-item <?= $currentPage==='redeem'?'active':'' ?>">
      <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
      Redeem Code
    </a>

    <a href="<?= APP_URL ?>/user/codes.php" class="nav-item <?= $currentPage==='codes'?'active':'' ?>">
      <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"/></svg>
      My Codes
    </a>

    <a href="<?= APP_URL ?>/user/services.php" class="nav-item <?= $currentPage==='services'?'active':'' ?>">
      <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
      Services
    </a>

    <a href="<?= APP_URL ?>/user/transactions.php" class="nav-item <?= $currentPage==='transactions'?'active':'' ?>">
      <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
      History
    </a>

    <a href="<?= APP_URL ?>/user/notifications.php" class="nav-item <?= $currentPage==='notifications'?'active':'' ?>">
      <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
      Notifications
      <span id="notif-badge" class="ml-auto bg-orange-500 text-white text-xs rounded-full w-5 h-5 items-center justify-center font-bold" style="display:none"></span>
    </a>

    <!-- ── VENDOR SECTION ────────────────────────────── -->
    <!-- Shows only when this user is an approved vendor. -->
    <!-- Shows "Become a Vendor" when user is not a vendor at all. -->
    <!-- Shows "Under Review" when application is pending. -->

    <?php if ($isVendor && $vendorStatus === 'active'): ?>

      <div class="px-4 pt-4 pb-1 text-xs font-semibold text-orange-500/70 uppercase tracking-wider">
        🏪 Vendor Panel
      </div>

      <a href="<?= APP_URL ?>/user/vendor-panel.php" class="nav-item <?= $currentPage==='vendor-panel'?'active':'' ?>">
        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
        Vendor Dashboard
      </a>

      <a href="<?= APP_URL ?>/user/vendor-panel.php?tab=codes" class="nav-item <?= ($currentPage==='vendor-panel'&&($_GET['tab']??'')==='codes')?'active':'' ?>">
        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
        Download My Codes
      </a>

      <a href="<?= APP_URL ?>/user/vendor-transfer.php" class="nav-item <?= $currentPage==='vendor-transfer'?'active':'' ?>">
        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
        Transfer to Vendor
      </a>

      <a href="<?= APP_URL ?>/user/vendor-panel.php?tab=keys" class="nav-item <?= ($currentPage==='vendor-panel'&&($_GET['tab']??'')==='keys')?'active':'' ?>">
        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
        API Keys
      </a>

      <a href="<?= APP_URL ?>/user/vendor-panel.php?tab=history" class="nav-item">
        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
        Distribution History
      </a>

    <?php elseif ($isVendor && $vendorStatus === 'pending'): ?>

      <div class="px-4 pt-4 pb-1 text-xs font-semibold text-gray-600 uppercase tracking-wider">Vendor</div>
      <div class="mx-3 p-3 bg-yellow-500/10 border border-yellow-500/20 rounded-xl text-xs text-yellow-400 leading-relaxed">
        ⏳ <strong>Application under review.</strong><br>You'll be notified once approved.
      </div>

    <?php elseif ($isVendor && in_array($vendorStatus, ['rejected','suspended'])): ?>

      <div class="px-4 pt-4 pb-1 text-xs font-semibold text-gray-600 uppercase tracking-wider">Vendor</div>
      <a href="<?= APP_URL ?>/user/vendor-apply.php" class="nav-item text-orange-400 hover:bg-orange-500/8">
        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
        Re-apply as Vendor
      </a>

    <?php else: ?>
      <!-- Not a vendor at all — show apply link -->
      <div class="px-4 pt-4 pb-1 text-xs font-semibold text-gray-600 uppercase tracking-wider">Vendor</div>
      <a href="<?= APP_URL ?>/user/vendor-apply.php" class="nav-item <?= $currentPage==='vendor-apply'?'active':'' ?> text-orange-400 hover:bg-orange-500/8">
        <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
        Become a Vendor
      </a>

    <?php endif; ?>

    <!-- ── ACCOUNT ─────────────────────────────────────── -->
    <div class="px-4 pt-4 pb-1 text-xs font-semibold text-gray-600 uppercase tracking-wider">Account</div>

    <a href="<?= APP_URL ?>/user/profile.php" class="nav-item <?= $currentPage==='profile'?'active':'' ?>">
      <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
      Profile
    </a>

  </div>

  <div class="p-3 border-t border-white/5">
    <a href="<?= APP_URL ?>/user/logout.php" class="nav-item text-red-400 hover:bg-red-500/10">
      <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
      Logout
    </a>
  </div>
</nav>

<!-- Mobile overlay -->
<div class="fixed inset-0 bg-black/60 z-40 md:hidden hidden" id="sidebar-overlay" onclick="closeSidebar()"></div>

<!-- ── Bottom Navigation (mobile) ───────────────────────── -->
<nav class="md:hidden fixed bottom-0 left-0 right-0 z-50"
     style="background:rgba(10,15,26,0.97);backdrop-filter:blur(20px);border-top:1px solid rgba(255,255,255,0.07);padding-bottom:env(safe-area-inset-bottom)">
  <div class="flex items-stretch h-16">

    <a href="<?= APP_URL ?>/user/dashboard.php" class="bnav-item <?= $currentPage==='dashboard'?'bnav-active':'' ?>">
      <svg class="bnav-icon" fill="<?= $currentPage==='dashboard'?'currentColor':'none' ?>" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
      <span>Home</span>
    </a>

    <a href="<?= APP_URL ?>/user/draws.php" class="bnav-item <?= $currentPage==='draws'?'bnav-active':'' ?>">
      <svg class="bnav-icon" fill="<?= $currentPage==='draws'?'currentColor':'none' ?>" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
      <span>Draws</span>
    </a>

    <!-- Centre Redeem FAB -->
    <a href="<?= APP_URL ?>/user/redeem.php" class="flex-1 flex flex-col items-center justify-center relative">
      <div class="w-14 h-14 rounded-2xl flex items-center justify-center -mt-7 transition-transform active:scale-90"
           style="background:linear-gradient(135deg,#f97316,#ea580c);box-shadow:0 4px 28px rgba(249,115,22,0.55)">
        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
      </div>
      <span class="text-gray-500 mt-0.5" style="font-size:10px">Redeem</span>
    </a>

    <a href="<?= APP_URL ?>/user/past-winners.php" class="bnav-item <?= $currentPage==='past-winners'?'bnav-active':'' ?>">
      <svg class="bnav-icon" fill="<?= $currentPage==='past-winners'?'currentColor':'none' ?>" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/></svg>
      <span>Winners</span>
    </a>

    <a href="<?= APP_URL ?>/user/profile.php"
       class="bnav-item <?= in_array($currentPage,['profile','notifications','transactions','services','codes'])?'bnav-active':'' ?>">
      <div class="relative">
        <svg class="bnav-icon" fill="<?= $currentPage==='profile'?'currentColor':'none' ?>" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
        <span id="profile-notif-dot" class="absolute -top-0.5 -right-0.5 w-2 h-2 bg-orange-500 rounded-full hidden"></span>
      </div>
      <span>Profile</span>
    </a>

  </div>
</nav>

<style>
  @media (max-width:767px) {
    .main-content { padding-bottom: calc(4.5rem + env(safe-area-inset-bottom, 0px)); }
  }
  .bnav-item {
    flex:1; display:flex; flex-direction:column; align-items:center; justify-content:center;
    gap:2px; color:#6b7280; font-size:10px; font-weight:500;
    text-decoration:none; transition:color .15s; padding:4px 0;
  }
  .bnav-item:active { opacity:.7; }
  .bnav-active { color:#f97316 !important; }
  .bnav-icon   { width:22px; height:22px; }
</style>

<script>
function closeSidebar() {
  document.getElementById('user-sidebar').classList.remove('open');
  document.getElementById('sidebar-overlay').classList.add('hidden');
}
function toggleSidebar() {
  const sb   = document.getElementById('user-sidebar');
  const ov   = document.getElementById('sidebar-overlay');
  const open = sb.classList.toggle('open');
  ov.classList.toggle('hidden', !open);
}
</script>
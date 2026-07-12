<?php
// components/user-sidebar.php
// Dedicated sidebar for the vendor portal.
// Reads from $_SESSION['vendor_id'] and vendors table only — no users table.

$currentPage    = $currentPage    ?? '';
$currentTab     = $_GET['tab']    ?? '';
$vendorName     = $_SESSION['vendor_name']   ?? 'Vendor';
$vendorStatus   = $_SESSION['vendor_status'] ?? 'pending';

// Sync vendor status from DB on every load
if (!empty($_SESSION['vendor_id'])) {
    try {
        $db = getDB();
        $sv = $db->prepare("SELECT full_name, business_name, status, code_balance FROM vendors WHERE id=?");
        $sv->execute([$_SESSION['vendor_id']]);
        $sv = $sv->fetch();
        if ($sv) {
            $_SESSION['vendor_name']   = $sv['full_name'];
            $_SESSION['vendor_status'] = $sv['status'];
            $vendorName   = $sv['full_name'];
            $vendorStatus = $sv['status'];
            $vendorBiz    = $sv['business_name'] ?? '';
            $codeBalance  = (int)$sv['code_balance'];
        }
    } catch (\Exception $e) {
        $vendorBiz   = '';
        $codeBalance = 0;
    }
} else {
    $vendorBiz   = '';
    $codeBalance = 0;
}

$isActive = ($vendorStatus === 'active');
?>

<!-- ── Vendor Desktop / Tablet Sidebar ──────────────────── -->
<nav class="sidebar flex flex-col z-100" id="user-sidebar" style="--sidebar-accent:#7c3aed">

  <!-- Logo — purple variant -->
  <div class="sidebar-logo flex items-center gap-2">
    <div class="w-8 h-8 rounded-lg flex items-center justify-center font-black text-white text-sm"
         style="background:#7c3aed">Z</div>
    <div class="text-lg font-bold leading-tight">
      <span style="color:#a78bfa">ZOE</span><span class="text-white">FEEDS</span>
    </div>
    <span class="text-xs rounded-full px-1.5 py-0.5 font-semibold"
          style="background:rgba(124,58,237,.2);border:1px solid rgba(124,58,237,.3);color:#c4b5fd;font-size:.6rem">
      VENDOR
    </span>
  </div>

  <!-- Vendor identity card -->
  <div class="mx-3 mb-2 p-3 rounded-xl" style="background:rgba(124,58,237,.08);border:1px solid rgba(124,58,237,.18)">
    <div class="flex items-center gap-2.5">
      <div class="w-9 h-9 rounded-xl flex items-center justify-center font-bold text-base flex-shrink-0"
           style="background:rgba(124,58,237,.25);color:#a78bfa">
        <?= strtoupper(mb_substr($vendorName, 0, 1)) ?>
      </div>
      <div class="min-w-0">
        <div class="text-sm font-semibold text-white truncate"><?= e($vendorBiz ?: $vendorName) ?></div>
        <?php if ($isActive): ?>
          <div class="flex items-center gap-1 text-xs" style="color:#4ade80">
            <span class="w-1.5 h-1.5 rounded-full bg-green-400 inline-block"></span> Active Vendor
          </div>
        <?php elseif ($vendorStatus === 'pending'): ?>
          <div class="text-xs text-yellow-400">⏳ Under Review</div>
        <?php elseif ($vendorStatus === 'suspended'): ?>
          <div class="text-xs text-red-400">🚫 Suspended</div>
        <?php else: ?>
          <div class="text-xs text-gray-500">Pending</div>
        <?php endif; ?>
      </div>
    </div>
    <?php if ($isActive): ?>
    <div class="mt-2.5 pt-2.5" style="border-top:1px solid rgba(124,58,237,.15)">
      <div class="text-xs text-gray-500 mb-0.5">Code Balance</div>
      <div class="text-xl font-black" style="color:#a78bfa"><?= number_format($codeBalance) ?></div>
    </div>
    <?php endif; ?>
  </div>

  <div class="flex-1 py-2 overflow-y-auto space-y-0.5">

    <!-- ── MAIN ─────────────────────────────────────────── -->
    <div class="px-4 pt-1 pb-1 text-xs font-semibold uppercase tracking-wider" style="color:rgba(167,139,250,.5)">Overview</div>

    <a href="<?= APP_URL ?>/vendor/dashboard.php"
       class="nav-item <?= $currentPage==='dashboard'?'active':'' ?>"
       style="<?= $currentPage==='dashboard'?'--nav-active-bg:rgba(124,58,237,.2);--nav-active-border:rgba(124,58,237,.5);':'' ?>">
      <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
      Dashboard
    </a>

    <!-- ── CODES ─────────────────────────────────────────── -->
    <?php if ($isActive): ?>

    <div class="px-4 pt-3 pb-1 text-xs font-semibold uppercase tracking-wider" style="color:rgba(167,139,250,.5)">Codes</div>


    <a href="<?= APP_URL ?>/vendor/dashboard.php?tab=codes"
       class="nav-item <?= ($currentPage==='dashboard'&&$currentTab==='codes')?'active':'' ?>">
      <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
      Print Tickets
    </a>

    <!-- ── REPORTS ────────────────────────────────────────── -->
    <div class="px-4 pt-3 pb-1 text-xs font-semibold uppercase tracking-wider" style="color:rgba(167,139,250,.5)">Reports</div>

    <a href="<?= APP_URL ?>/vendor/dashboard.php?tab=history"
       class="nav-item <?= ($currentPage==='dashboard'&&$currentTab==='history')?'active':'' ?>">
      <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
      Distribution History
    </a>

    <!-- ── SETTINGS ───────────────────────────────────────── -->
    <div class="px-4 pt-3 pb-1 text-xs font-semibold uppercase tracking-wider" style="color:rgba(167,139,250,.5)">Settings</div>

    <a href="<?= APP_URL ?>/vendor/dashboard.php?tab=keys"
       class="nav-item <?= ($currentPage==='dashboard'&&$currentTab==='keys')?'active':'' ?>">
      <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
      API Keys
    </a>

    <a href="<?= APP_URL ?>/vendor/profile.php"
       class="nav-item <?= $currentPage==='vendor-profile'?'active':'' ?>">
      <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
      Vendor Profile
    </a>

    <?php elseif ($vendorStatus === 'pending'): ?>

    <!-- Pending state — limited nav -->
    <div class="mx-3 mt-3 p-3 rounded-xl text-xs leading-relaxed" style="background:rgba(234,179,8,.08);border:1px solid rgba(234,179,8,.2);color:#fde68a">
      ⏳ <strong>Application under review.</strong><br>
      You'll receive a notification once approved. Most reviews complete within 24 hours.
    </div>

    <?php elseif (in_array($vendorStatus, ['rejected', 'suspended'])): ?>

    <!-- Rejected/Suspended — show re-apply -->
    <div class="mx-3 mt-3 p-3 rounded-xl text-xs leading-relaxed mb-2" style="background:rgba(239,68,68,.08);border:1px solid rgba(239,68,68,.2);color:#fca5a5">
      🚫 <?= $vendorStatus === 'suspended' ? 'Account suspended. Contact admin.' : 'Application rejected.' ?>
    </div>
    <a href="<?= APP_URL ?>/vendor/register.php" class="nav-item" style="color:#f472b6">
      <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
      Re-apply as Vendor
    </a>

    <?php endif; ?>

  </div>

  <!-- Bottom: customer portal crosslink + logout -->
  <div class="p-3 space-y-1" style="border-top:1px solid rgba(124,58,237,.15)">
   
    <a href="<?= APP_URL ?>/vendor/logout.php" class="nav-item text-red-400 hover:bg-red-500/10">
      <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
      Logout
    </a>
  </div>
</nav>

<!-- Mobile overlay -->
<div class="fixed inset-0 bg-black/60 z-40 md:hidden hidden" id="sidebar-overlay" onclick="closeSidebar()"></div>

<!-- ── Bottom Navigation (mobile) — vendor purple theme ─── -->
<nav class="md:hidden fixed bottom-0 left-0 right-0 z-50"
     style="background:rgba(10,15,26,0.97);backdrop-filter:blur(20px);border-top:1px solid rgba(124,58,237,.2);padding-bottom:env(safe-area-inset-bottom)">
  <div class="flex items-stretch h-16">

    <!-- 1. Overview / Dashboard -->
    <a href="<?= APP_URL ?>/vendor/dashboard.php"
       class="bnav-item <?= $currentPage==='dashboard'&&!$currentTab?'vbnav-active':'' ?>">
      <svg class="bnav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
      <span>Overview</span>
    </a>

    <!-- 2. History -->
    <a href="<?= APP_URL ?>/vendor/dashboard.php?tab=history"
       class="bnav-item <?= $currentTab==='history'?'vbnav-active':'' ?>">
      <svg class="bnav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
      <span>History</span>
    </a>

    <!-- 3. Centre FAB — Codes (Exact Original Transfer Style) -->
    <a href="<?= APP_URL ?>/vendor/dashboard.php?tab=codes" class="flex-1 flex flex-col items-center justify-center relative">
      <div class="w-14 h-14 rounded-2xl flex items-center justify-center -mt-7 transition-transform active:scale-90"
           style="background:linear-gradient(135deg,#7c3aed,#6d28d9);box-shadow:0 4px 28px rgba(124,58,237,0.55)">
        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"/></svg>
      </div>
      <span class="text-gray-500 mt-0.5" style="font-size:10px">Codes</span>
    </a>

    <!-- 4. API Keys -->
    <a href="<?= APP_URL ?>/vendor/dashboard.php?tab=keys"
       class="bnav-item <?= $currentTab==='keys'?'vbnav-active':'' ?>">
      <svg class="bnav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
      <span>API Keys</span>
    </a>

    <!-- 5. Profile -->
    <a href="<?= APP_URL ?>/vendor/dashboard.php?tab=profile"
       class="bnav-item <?= $currentTab==='profile'?'vbnav-active':'' ?>">
      <svg class="bnav-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
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
  .vbnav-active { color:#a78bfa !important; }
  .bnav-icon    { width:22px; height:22px; }

  /* Override nav-item active style for purple vendor theme */
  #user-sidebar .nav-item.active,
  #user-sidebar .nav-item:hover {
    background: rgba(124,58,237,.12) !important;
    border-left-color: #7c3aed !important;
    color: #c4b5fd !important;
  }
  #user-sidebar .nav-item.active {
    border-left-color: #a78bfa !important;
    color: #e9d5ff !important;
  }
</style>


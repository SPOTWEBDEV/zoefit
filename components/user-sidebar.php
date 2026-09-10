<?php
$currentPage = $currentPage ?? '';
?>

<nav class="sidebar flex flex-col" id="user-sidebar">

  <div class="sidebar-logo flex items-center gap-2">
    <div class="w-8 h-8 bg-orange-500 rounded-lg flex items-center justify-center font-black text-white text-sm">Z</div>
    <div class="text-lg font-bold leading-tight"><span class="text-orange-500">ZOE</span><span class="text-white">FEEDS</span></div>
  </div>

  <div class="flex-1 py-3 overflow-y-auto space-y-0.5">

    <div class="px-4 pt-1 pb-1 text-xs font-semibold text-gray-600 uppercase tracking-wider">Main</div>

    <a href="<?= APP_URL ?>/user/dashboard.php" class="nav-item <?= $currentPage === 'dashboard' ? 'active' : '' ?>">
      <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
      </svg>
      Dashboard
    </a>

    <a href="<?= APP_URL ?>/user/draws.php" class="nav-item <?= $currentPage === 'draws' ? 'active' : '' ?>">
      <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
      Live Draws
    </a>

    <a href="<?= APP_URL ?>/user/past-winners.php" class="nav-item <?= $currentPage === 'past-winners' ? 'active' : '' ?>">
      <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" />
      </svg>
      Past Winners
    </a>

    <div class="px-4 pt-4 pb-1 text-xs font-semibold text-gray-600 uppercase tracking-wider">Wallet</div>

    <a href="<?= APP_URL ?>/user/deposit.php" class="nav-item <?= $currentPage === 'deposit' ? 'active' : '' ?>">
      <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V6m0 12v-2m9-4a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
      Deposit
    </a>

    <a href="<?= APP_URL ?>/user/airtime.php" class="nav-item <?= $currentPage === 'airtime' ? 'active' : '' ?>">
      <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
      </svg>
      Buy Airtime
    </a>

    <a href="<?= APP_URL ?>/user/data.php" class="nav-item <?= $currentPage === 'data' ? 'active' : '' ?>">
      <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01M4.929 12.9a10.5 10.5 0 0114.142 0M1.5 9.75a15 15 0 0121 0" />
      </svg>
      Buy Data
    </a>

    <a href="<?= APP_URL ?>/user/wallet-history.php" class="nav-item <?= $currentPage === 'wallet-history' ? 'active' : '' ?>">
      <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
      Wallet History
    </a>

    <div class="px-4 pt-4 pb-1 text-xs font-semibold text-gray-600 uppercase tracking-wider">Raffle</div>

    <a href="<?= APP_URL ?>/user/redeem.php" class="nav-item <?= $currentPage === 'redeem' ? 'active' : '' ?>">
      <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
      </svg>
      Redeem Code
    </a>

    <a href="<?= APP_URL ?>/user/codes.php" class="nav-item <?= $currentPage === 'codes' ? 'active' : '' ?>">
      <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14" />
      </svg>
      My Codes
    </a>

    <a href="<?= APP_URL ?>/user/transactions.php" class="nav-item <?= $currentPage === 'transactions' ? 'active' : '' ?>">
      <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
      </svg>
      History
    </a>

    <a href="<?= APP_URL ?>/user/notifications.php" class="nav-item <?= $currentPage === 'notifications' ? 'active' : '' ?>">
      <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
      </svg>
      Notifications
      <span id="notif-badge" class="ml-auto bg-orange-500 text-white text-xs rounded-full w-5 h-5 items-center justify-center font-bold" style="display:none"></span>
    </a>

    <div class="px-4 pt-4 pb-1 text-xs font-semibold text-gray-600 uppercase tracking-wider">Account</div>

    <a href="<?= APP_URL ?>/user/profile.php" class="nav-item <?= $currentPage === 'profile' ? 'active' : '' ?>">
      <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
      </svg>
      Profile
    </a>
    <a href="<?= APP_URL ?>/user/referral.php" class="nav-item <?= $currentPage === 'referral' ? 'active' : '' ?>">
      <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z" />
      </svg>
      Referral
    </a>

    <div class="px-4 pt-3 pb-1 text-xs font-semibold text-gray-600 uppercase tracking-wider">Vendor</div>
    <a href="<?= APP_URL ?>/vendor/register.php" class="nav-item text-orange-400 hover:bg-orange-500/10 ">
      <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
      </svg>
      Become a Vendor
    </a>

  </div>

  <div class="p-3 border-t border-white/5 pb-[calc(4.5rem+env(safe-area-inset-bottom,0px))] md:pb-3">
    <a href="<?= APP_URL ?>/user/logout.php" class="nav-item text-red-400 hover:bg-red-500/10">
      <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
      </svg>
      Logout
    </a>
  </div>
</nav>

<div class="fixed inset-0 bg-black/60 z-40 md:hidden hidden" id="sidebar-overlay" onclick="closeSidebar()"></div>

<nav class="md:hidden fixed bottom-0 left-0 right-0 z-50"
  style="background:rgba(10,15,26,0.97);backdrop-filter:blur(20px);border-top:1px solid rgba(255,255,255,0.07);padding-bottom:env(safe-area-inset-bottom)">
  <div class="flex items-stretch h-16">

    <a href="<?= APP_URL ?>/user/dashboard.php" class="bnav-item <?= $currentPage === 'dashboard' ? 'bnav-active' : '' ?>">
      <svg class="bnav-icon" fill="<?= $currentPage === 'dashboard' ? 'currentColor' : 'none' ?>" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
      </svg>
      <span>Home</span>
    </a>

    <a href="<?= APP_URL ?>/user/draws.php" class="bnav-item <?= $currentPage === 'draws' ? 'bnav-active' : '' ?>">
      <svg class="bnav-icon" fill="<?= $currentPage === 'draws' ? 'currentColor' : 'none' ?>" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
      <span>Draws</span>
    </a>

    <!-- Services quick-action: opens bottom sheet with Airtime / Data instead of linking straight to Redeem -->
    <button type="button" onclick="toggleServiceSheet()" class="flex-1 flex flex-col items-center justify-center relative <?= in_array($currentPage, ['airtime','data']) ? 'bnav-active' : '' ?>">
      <div class="w-14 h-14 rounded-2xl flex items-center justify-center -mt-7 transition-transform active:scale-90"
        style="background:linear-gradient(135deg,#f97316,#ea580c);box-shadow:0 4px 28px rgba(249,115,22,0.55)">
        <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 3v2m6-2v2M9 19v2m6-2v2M5 9H3m2 6H3m18-6h-2m2 6h-2M7 19h10a2 2 0 002-2V7a2 2 0 00-2-2H7a2 2 0 00-2 2v10a2 2 0 002 2z" />
        </svg>
      </div>
      <span class="text-gray-500 mt-0.5" style="font-size:10px">Services</span>
    </button>

    <a href="<?= APP_URL ?>/user/past-winners.php" class="bnav-item <?= $currentPage === 'past-winners' ? 'bnav-active' : '' ?>">
      <svg class="bnav-icon" fill="<?= $currentPage === 'past-winners' ? 'currentColor' : 'none' ?>" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" />
      </svg>
      <span>Winners</span>
    </a>

    <a href="<?= APP_URL ?>/user/profile.php" 
      class="bnav-item <?= in_array($currentPage, ['profile', 'notifications', 'transactions', 'codes']) ? 'bnav-active' : '' ?>">
      <div class="relative">
        <svg class="bnav-icon" fill="<?= $currentPage === 'profile' ? 'currentColor' : 'none' ?>" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
        </svg>
        <span id="profile-notif-dot" class="absolute -top-0.5 -right-0.5 w-2 h-2 bg-orange-500 rounded-full hidden"></span>
      </div>
      <span>Profile</span>
    </a>

  </div>
</nav>

<!-- Services bottom sheet: Airtime / Data -->
<div class="fixed inset-0 bg-black/60 z-[60] hidden" id="service-sheet-overlay" onclick="closeServiceSheet()"></div>
<div class="fixed left-0 right-0 bottom-0 z-[70] translate-y-full transition-transform duration-250 ease-out md:hidden"
     id="service-sheet"
     style="background:#0f1524;border-top:1px solid rgba(255,255,255,0.08);border-radius:20px 20px 0 0;padding-bottom:env(safe-area-inset-bottom)">
  <div class="flex justify-center pt-3">
    <div class="w-10 h-1.5 rounded-full bg-white/15"></div>
  </div>
  <div class="p-5">
    <div class="text-base font-bold mb-4">Buy Airtime or Data</div>
    <div class="grid grid-cols-2 gap-3">
      <a href="<?= APP_URL ?>/user/airtime.php" class="rounded-2xl bg-white/5 hover:bg-white/10 border border-white/10 p-4 flex flex-col items-center gap-2 text-center">
        <div class="w-12 h-12 rounded-xl bg-orange-500/15 flex items-center justify-center">
          <svg class="w-6 h-6 text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
          </svg>
        </div>
        <div class="text-sm font-semibold">Airtime</div>
        <div class="text-[11px] text-gray-400">Top up any network</div>
      </a>
      <a href="<?= APP_URL ?>/user/data.php" class="rounded-2xl bg-white/5 hover:bg-white/10 border border-white/10 p-4 flex flex-col items-center gap-2 text-center">
        <div class="w-12 h-12 rounded-xl bg-blue-500/15 flex items-center justify-center">
          <svg class="w-6 h-6 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01M4.929 12.9a10.5 10.5 0 0114.142 0M1.5 9.75a15 15 0 0121 0" />
          </svg>
        </div>
        <div class="text-sm font-semibold">Data</div>
        <div class="text-[11px] text-gray-400">Buy a data plan</div>
      </a>
    </div>
    <button type="button" onclick="closeServiceSheet()" class="w-full mt-4 py-3 rounded-xl bg-white/5 hover:bg-white/10 text-sm text-gray-400">Cancel</button>
  </div>
</div>

<style>
  @media (max-width:767px) {
    .main-content {
      padding-bottom: calc(4.5rem + env(safe-area-inset-bottom, 0px));
    }

    /* Fixed side navigation initialization properties for mobile viewport sizes */
    #user-sidebar {
      position: fixed;
      top: 0;
      left: 0;
      bottom: 0;
      width: 270px;
      z-index: 50;
      background-color: #0b0f19; /* Adjust context hex to your theme panel colors */
      transform: translateX(-100%);
      transition: transform 0.25s ease-out;
    }

    /* Target state when layout open class is appended dynamically */
    #user-sidebar.open {
      transform: translateX(0);
    }
  }

  .bnav-item {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 2px;
    color: #6b7280;
    font-size: 10px;
    font-weight: 500;
    text-decoration: none;
    transition: color .15s;
    padding: 4px 0;
  }

  .bnav-item:active {
    opacity: .7;
  }

  .bnav-active {
    color: #f97316 !important;
  }

  .bnav-icon {
    width: 22px;
    height: 22px;
  }

  #service-sheet.open {
    transform: translateY(0);
  }
</style>

<script>
  function toggleServiceSheet() {
    var sheet = document.getElementById('service-sheet');
    var overlay = document.getElementById('service-sheet-overlay');
    var isOpen = sheet.classList.contains('open');
    if (isOpen) { closeServiceSheet(); return; }
    overlay.classList.remove('hidden');
    requestAnimationFrame(function () { sheet.classList.add('open'); });
  }
  function closeServiceSheet() {
    var sheet = document.getElementById('service-sheet');
    var overlay = document.getElementById('service-sheet-overlay');
    sheet.classList.remove('open');
    overlay.classList.add('hidden');
  }
</script>
<?php
// components/user-sidebar.php
$currentPage = $currentPage ?? '';
?>
<nav class="sidebar flex flex-col" id="user-sidebar">
  <div class="sidebar-logo flex items-center gap-2">
    <div class="text-xl font-display font-bold text-orange-500">ZOE<span class="text-white">FEEDS</span></div>
  </div>
  <div class="flex-1 py-4">
    <a href="<?= APP_URL ?>/user/dashboard.php" class="nav-item <?= $currentPage==='dashboard'?'active':'' ?>">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
      Dashboard
    </a>
    <a href="<?= APP_URL ?>/user/codes.php" class="nav-item <?= $currentPage==='codes'?'active':'' ?>">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"/></svg>
      My Codes
    </a>
    <a href="<?= APP_URL ?>/user/redeem.php" class="nav-item <?= $currentPage==='redeem'?'active':'' ?>">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
      Redeem Code
    </a>
    <a href="<?= APP_URL ?>/user/transfer.php" class="nav-item <?= $currentPage==='transfer'?'active':'' ?>">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7h12m0 0l-4-4m4 4l-4 4m0 6H4m0 0l4 4m-4-4l4-4"/></svg>
      Transfer
    </a>
    <a href="<?= APP_URL ?>/user/draws.php" class="nav-item <?= $currentPage==='draws'?'active':'' ?>">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
      Draws &amp; Rewards
    </a>
    <a href="<?= APP_URL ?>/user/transactions.php" class="nav-item <?= $currentPage==='transactions'?'active':'' ?>">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
      History
    </a>
    <a href="<?= APP_URL ?>/user/notifications.php" class="nav-item <?= $currentPage==='notifications'?'active':'' ?>">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
      Notifications
      <span id="notif-badge" class="ml-auto bg-orange-500 text-white text-xs rounded-full w-5 h-5 items-center justify-content-center hidden" style="display:none"></span>
    </a>
    <a href="<?= APP_URL ?>/user/profile.php" class="nav-item <?= $currentPage==='profile'?'active':'' ?>">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
      Profile
    </a>
  </div>
  <div class="p-4 border-t border-white/5">
    <a href="<?= APP_URL ?>/user/logout.php" class="nav-item text-red-400 hover:text-red-300 hover:bg-red-500/10">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
      Logout
    </a>
  </div>
</nav>
<!-- Mobile overlay -->
<div class="fixed inset-0 bg-black/50 z-40 hidden" id="sidebar-overlay" onclick="toggleSidebar(); this.classList.add('hidden')"></div>

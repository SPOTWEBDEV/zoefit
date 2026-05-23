<?php $vPage=$vPage??''; ?>
<nav class="sidebar flex flex-col" id="vendor-sidebar">
  <div class="sidebar-logo">
    <div class="text-lg font-bold text-orange-500">ZOE<span class="text-white">FEEDS</span></div>
    <div class="text-xs text-gray-500 mt-1">Vendor Portal</div>
  </div>
  <div class="flex-1 py-4">
    <a href="<?= APP_URL ?>/vendor/dashboard.php"  class="nav-item <?= $vPage==='dashboard'?'active':'' ?>">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
      Dashboard
    </a>
    <a href="<?= APP_URL ?>/vendor/inventory.php"  class="nav-item <?= $vPage==='inventory'?'active':'' ?>">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/></svg>
      Inventory
    </a>
    <a href="<?= APP_URL ?>/vendor/credit-user.php" class="nav-item <?= $vPage==='credit'?'active':'' ?>">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
      Credit User
    </a>
    <a href="<?= APP_URL ?>/vendor/history.php"    class="nav-item <?= $vPage==='history'?'active':'' ?>">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
      History
    </a>
  </div>
  <div class="p-4 border-t border-white/5">
    <a href="<?= APP_URL ?>/vendor/logout.php" class="nav-item text-red-400 hover:bg-red-500/10">
      <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class="w-5 h-5"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
      Logout
    </a>
  </div>
</nav>

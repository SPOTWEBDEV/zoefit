<?php $aPage = $aPage ?? ''; ?>
<nav class="sidebar flex flex-col" id="admin-sidebar">
  <div class="sidebar-logo">
    <div class="text-lg font-bold"><span class="text-orange-500">ZOE</span>FEEDS</div>
    <div class="text-xs text-gray-500 mt-0.5">Admin Panel</div>
  </div>
  <div class="flex-1 py-3 space-y-0.5">

    <div class="px-4 py-2 text-xs font-semibold text-gray-600 uppercase tracking-wider">Overview</div>
    <a href="<?= APP_URL ?>/admin/dashboard.php" class="nav-item <?= $aPage==='dashboard'?'active':'' ?>">
      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
      Dashboard
    </a>

    <div class="px-4 py-2 text-xs font-semibold text-gray-600 uppercase tracking-wider mt-2">Management</div>
    <a href="<?= APP_URL ?>/admin/users.php" class="nav-item <?= $aPage==='users'?'active':'' ?>">
      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
      Users
    </a>
    <a href="<?= APP_URL ?>/admin/vendors.php" class="nav-item <?= $aPage==='vendors'?'active':'' ?>">
      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
      Vendors
    </a>
    <a href="<?= APP_URL ?>/admin/codes.php" class="nav-item <?= $aPage==='codes'?'active':'' ?>">
      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"/></svg>
      Codes
    </a>
    <a href="<?= APP_URL ?>/admin/draws.php" class="nav-item <?= $aPage==='draws'?'active':'' ?>">
      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
      Draws
    </a>

    <div class="px-4 py-2 text-xs font-semibold text-gray-600 uppercase tracking-wider mt-2">Content</div>
    <a href="<?= APP_URL ?>/admin/slides.php" class="nav-item <?= $aPage==='slides'?'active':'' ?>">
      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
      Banners &amp; Slides
    </a>
    <a href="<?= APP_URL ?>/admin/audit-logs.php" class="nav-item <?= $aPage==='audit'?'active':'' ?>">
      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
      Audit Logs
    </a>
  </div>
  <div class="p-3 border-t border-white/5 text-xs text-gray-600 px-5 pb-2">
    Logged in as <span class="text-gray-400"><?= e($_SESSION['admin_name']??'Admin') ?></span>
  </div>
  <div class="px-3 pb-4">
    <a href="<?= APP_URL ?>/admin/logout.php" class="nav-item text-red-400 hover:bg-red-500/10">
      <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
      Logout
    </a>
  </div>
</nav>

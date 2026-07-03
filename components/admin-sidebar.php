<?php
// components/admin-sidebar.php
$aPage = $aPage ?? '';
$_pendingVendors = 0;
try {
  $_s = getDB()->prepare("SELECT COUNT(*) FROM users WHERE is_vendor=1 AND vendor_status='pending'");
  $_s->execute(); $_pendingVendors = (int)$_s->fetchColumn();
} catch(\Exception $e) {}
?>
<nav class="sidebar flex flex-col" id="admin-sidebar">
  <div class="sidebar-logo flex items-center gap-2">
    <div class="w-8 h-8 bg-orange-500 rounded-lg flex items-center justify-center font-black text-white text-sm">Z</div>
    <div>
      <div class="text-base font-bold leading-tight"><span class="text-orange-500">ZOE</span>FEEDS</div>
      <div class="text-xs text-gray-600">Admin Panel</div>
    </div>
  </div>
  <div class="flex-1 py-2 overflow-y-auto space-y-0.5">

    <div class="px-4 pt-2 pb-1 text-xs font-semibold text-gray-600 uppercase tracking-wider">Overview</div>
    <a href="<?= APP_URL ?>/admin/dashboard.php" class="nav-item <?= $aPage==='dashboard'?'active':'' ?>">
      <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"/></svg>
      Dashboard
    </a>

    <div class="px-4 pt-3 pb-1 text-xs font-semibold text-gray-600 uppercase tracking-wider">People</div>
    <a href="<?= APP_URL ?>/admin/users.php" class="nav-item <?= $aPage==='users'?'active':'' ?>">
      <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
      Users
    </a>
    <a href="<?= APP_URL ?>/admin/vendors.php" class="nav-item <?= $aPage==='vendors'?'active':'' ?>">
      <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/></svg>
      Active Vendors
    </a>
    <a href="<?= APP_URL ?>/admin/vendor-requests.php" class="nav-item <?= $aPage==='vendor-requests'?'active':'' ?>">
      <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
      Vendor Requests
      <?php if($_pendingVendors>0): ?>
      <span class="ml-auto bg-orange-500 text-white text-xs rounded-full min-w-[20px] h-5 flex items-center justify-center px-1 font-bold"><?= $_pendingVendors ?></span>
      <?php endif; ?>
    </a>

    <div class="px-4 pt-3 pb-1 text-xs font-semibold text-gray-600 uppercase tracking-wider">Lottery</div>
    <a href="<?= APP_URL ?>/admin/codes.php" class="nav-item <?= $aPage==='codes'?'active':'' ?>">
      <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14"/></svg>
      Codes
    </a>
    <a href="<?= APP_URL ?>/admin/draws.php" class="nav-item <?= $aPage==='draws'?'active':'' ?>">
      <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
      Draws
    </a>
    <a href="<?= APP_URL ?>/admin/draw-manage.php" class="nav-item <?= $aPage==='winners'?'active':'' ?>">
      <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/></svg>
      Winners
    </a>

    <div class="px-4 pt-3 pb-1 text-xs font-semibold text-gray-600 uppercase tracking-wider">Content</div>
    <a href="<?= APP_URL ?>/admin/slides.php" class="nav-item <?= $aPage==='slides'?'active':'' ?>">
      <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
      Banners &amp; Slides
    </a>

    <div class="px-4 pt-3 pb-1 text-xs font-semibold text-gray-600 uppercase tracking-wider">System</div>
    <a href="<?= APP_URL ?>/admin/audit-logs.php" class="nav-item <?= $aPage==='audit'?'active':'' ?>">
      <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
      Audit Logs
    </a>
    <!-- <a href="<?= APP_URL ?>/admin/super-login.php" class="nav-item text-red-400 hover:bg-red-500/10">
      <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/></svg>
      Super Admin ↗
    </a> -->
  </div>

  <div class="border-t border-white/5 px-3 py-3">
    <div class="flex items-center gap-2 mb-2 px-2">
      <div class="w-7 h-7 bg-orange-500/20 rounded-lg flex items-center justify-center text-xs font-bold text-orange-400"><?= strtoupper(substr($_SESSION['admin_name']??'A',0,1)) ?></div>
      <div class="min-w-0"><div class="text-xs font-semibold truncate"><?= e($_SESSION['admin_name']??'Admin') ?></div><div class="text-xs text-gray-600">Administrator</div></div>
    </div>
    <a href="<?= APP_URL ?>/admin/logout.php" class="nav-item text-red-400 hover:bg-red-500/10">
      <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
      Logout
    </a>
  </div>
</nav>
<div class="fixed inset-0 bg-black/60 z-40 md:hidden hidden" id="admin-overlay"
     onclick="this.classList.add('hidden');document.getElementById('admin-sidebar').classList.remove('open')"></div>
<script>
function toggleSidebar(){
  document.getElementById('admin-sidebar').classList.toggle('open');
  document.getElementById('admin-overlay').classList.toggle('hidden');
}
</script>

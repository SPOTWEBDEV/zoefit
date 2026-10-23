<?php
// components/admin-sidebar.php
$aPage = $aPage ?? '';
$_pendingVendors = 0;
try {
  $_s = getDB()->prepare("SELECT COUNT(*) FROM users WHERE is_vendor=1 AND vendor_status='pending'");
  $_s->execute();
  $_pendingVendors = (int)$_s->fetchColumn();
} catch (\Exception $e) {
}
?>
<style>
  /* ── Admin sidebar + topbar: SaaS look. Scoped, so it only affects admin pages. ── */
  #user-sidebar.admin-nav .sidebar-logo {
    padding: 1.1rem 1.25rem;
    border-bottom: 1px solid rgba(255,255,255,.06);
  }
  #user-sidebar.admin-nav .nav-group {
    padding: 1.1rem 1.25rem .4rem;
    font-size: .6875rem;
    font-weight: 600;
    letter-spacing: .08em;
    text-transform: uppercase;
    color: #6b7280;
  }
  #user-sidebar.admin-nav .nav-item {
    position: relative;
    display: flex;
    align-items: center;
    gap: .7rem;
    margin: 2px .75rem;
    padding: .55rem .75rem;
    border-radius: .7rem;
    font-size: .875rem;
    font-weight: 500;
    color: #9ca3af;
    border: 1px solid transparent;
    transition: background .15s, color .15s, border-color .15s;
  }
  #user-sidebar.admin-nav .nav-item svg { opacity: .75; transition: color .15s, opacity .15s; }
  #user-sidebar.admin-nav .nav-item:hover { background: rgba(255,255,255,.05); color: #fff; }
  #user-sidebar.admin-nav .nav-item:hover svg { opacity: 1; }
  #user-sidebar.admin-nav .nav-item.active {
    background: linear-gradient(90deg, rgba(249,115,22,.16), rgba(249,115,22,.04));
    border-color: rgba(249,115,22,.22);
    color: #fff;
  }
  #user-sidebar.admin-nav .nav-item.active svg { color: #fb923c; opacity: 1; }
  #user-sidebar.admin-nav .nav-item.active::before {
    content: '';
    position: absolute;
    left: -.75rem; top: 22%; bottom: 22%;
    width: 3px;
    border-radius: 0 3px 3px 0;
    background: #fb923c;
  }
  #user-sidebar.admin-nav .nav-item.nav-danger { color: #f87171; }
  #user-sidebar.admin-nav .nav-item.nav-danger:hover { background: rgba(239,68,68,.1); color: #fca5a5; }
  #user-sidebar.admin-nav .nav-new {
    margin-left: auto;
    font-size: .625rem; font-weight: 700; letter-spacing: .04em; text-transform: uppercase;
    padding: .1rem .45rem; border-radius: 999px;
    background: rgba(52,211,153,.14); color: #6ee7b7;
  }
  #user-sidebar.admin-nav .admin-card {
    background: rgba(255,255,255,.04);
    border: 1px solid rgba(255,255,255,.07);
    border-radius: .9rem;
    padding: .65rem;
  }

  /* Topbar: only adds polish (border, blur, title size, count pill). Position/size stay as in app.css. */
  .topbar {
    border-bottom: 1px solid rgba(255,255,255,.06);
    -webkit-backdrop-filter: blur(12px);
    backdrop-filter: blur(12px);
  }
  .topbar h1 { font-size: 1.125rem; font-weight: 700; letter-spacing: -.01em; }
  /* the "N entries" style text on the right becomes a pill */
  .topbar > div.text-gray-400 {
    margin-left: auto;
    padding: .25rem .75rem;
    border-radius: 999px;
    background: rgba(255,255,255,.05);
    border: 1px solid rgba(255,255,255,.08);
    font-size: .75rem;
    font-weight: 500;
    white-space: nowrap;
  }
</style>

<nav class="sidebar admin-nav flex flex-col" id="user-sidebar">
  <div class="sidebar-logo flex items-center gap-3">
    <div class="w-9 h-9 bg-gradient-to-br from-orange-400 to-orange-600 rounded-xl flex items-center justify-center font-black text-white text-sm shadow-lg shadow-orange-500/20">Z</div>
    <div>
      <div class="text-base font-bold leading-tight tracking-tight"><span class="text-orange-500">ZOE</span>FEEDS</div>
      <div class="text-[11px] text-gray-500">Admin Panel</div>
    </div>
  </div>

  <div class="flex-1 py-1 overflow-y-auto">

    <div class="nav-group">Overview</div>
    <a href="<?= APP_URL ?>/admin/dashboard.php" class="nav-item <?= $aPage === 'dashboard' ? 'active' : '' ?>">
      <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
      </svg>
      Dashboard
    </a>

    <div class="nav-group">People</div>
    <a href="<?= APP_URL ?>/admin/users.php" class="nav-item <?= $aPage === 'users' ? 'active' : '' ?>">
      <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z" />
      </svg>
      Users
    </a>
    <a href="<?= APP_URL ?>/admin/vendors.php" class="nav-item <?= $aPage === 'vendors' ? 'active' : '' ?>">
      <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
      </svg>
      Active Vendors
    </a>
    <a href="<?= APP_URL ?>/admin/vendor-requests.php" class="nav-item <?= $aPage === 'vendor-requests' ? 'active' : '' ?>">
      <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
      </svg>
      Vendor Requests
      <?php if ($_pendingVendors > 0): ?>
        <span class="ml-auto bg-orange-500 text-white text-xs rounded-full min-w-[20px] h-5 flex items-center justify-center px-1.5 font-bold"><?= $_pendingVendors ?></span>
      <?php endif; ?>
    </a>

    <div class="nav-group">Lottery</div>
    <a href="<?= APP_URL ?>/admin/codes.php" class="nav-item <?= $aPage === 'codes' ? 'active' : '' ?>">
      <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 20l4-16m2 16l4-16M6 9h14M4 15h14" />
      </svg>
      Codes
    </a>
    <a href="<?= APP_URL ?>/admin/draws.php" class="nav-item <?= $aPage === 'draws' ? 'active' : '' ?>">
      <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
      </svg>
      Draws
    </a>
    <a href="<?= APP_URL ?>/admin/winners.php" class="nav-item <?= $aPage === 'winners' ? 'active' : '' ?>">
      <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z" />
      </svg>
      Winners
    </a>

    <div class="nav-group">Finance</div>
    <a href="<?= APP_URL ?>/admin/pricing.php" class="nav-item <?= $aPage === 'pricing' ? 'active' : '' ?>">
      <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
      </svg>
      Pricing &amp; Profit
      <span class="nav-new">New</span>
    </a>

    <a href="<?= APP_URL ?>/admin/super-login.php" class="nav-item ">
      <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
      </svg>
      Transactions ↗
    </a>

    <div class="nav-group">Content</div>
    <a href="<?= APP_URL ?>/admin/slides.php" class="nav-item <?= $aPage === 'slides' ? 'active' : '' ?>">
      <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
      </svg>
      Banners &amp; Slides
    </a>

    <div class="nav-group">System</div>
    <a href="<?= APP_URL ?>/admin/audit-logs.php" class="nav-item <?= $aPage === 'audit' ? 'active' : '' ?>">
      <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
      </svg>
      Audit Logs
    </a>
    <!-- <a href="<?= APP_URL ?>/admin/super-login.php" class="nav-item nav-danger">
      <svg class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
      </svg>
      Super Admin ↗
    </a> -->
  </div>

  <div class="border-t border-white/5 p-3">
    <div class="admin-card">
      <div class="flex items-center gap-2.5">
        <div class="w-9 h-9 bg-gradient-to-br from-orange-500/30 to-orange-500/10 rounded-lg flex items-center justify-center text-sm font-bold text-orange-300"><?= strtoupper(substr($_SESSION['admin_name'] ?? 'A', 0, 1)) ?></div>
        <div class="min-w-0 flex-1">
          <div class="text-sm font-semibold truncate"><?= e($_SESSION['admin_name'] ?? 'Admin') ?></div>
          <div class="text-[11px] text-gray-500">Administrator</div>
        </div>
        <a href="<?= APP_URL ?>/admin/logout.php" title="Logout" aria-label="Logout"
           class="w-8 h-8 flex items-center justify-center rounded-lg text-gray-500 hover:text-red-400 hover:bg-red-500/10 transition flex-shrink-0">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
          </svg>
        </a>
      </div>
    </div>
  </div>
</nav>

<div class="fixed inset-0 bg-black/60 z-40 md:hidden hidden" id="sidebar-overlay" onclick="closeSidebar()"></div>
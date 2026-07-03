<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
startAppSession();
$db = getDB();

$draws  = $db->query("SELECT * FROM draws WHERE status='active' ORDER BY end_date ASC LIMIT 6")->fetchAll();
$slides = $db->query("SELECT * FROM slides WHERE status='active' ORDER BY sort_order ASC LIMIT 6")->fetchAll();
$winners= $db->query("SELECT dw.*, d.title as draw_title, u.full_name, u.phone FROM draw_winners dw JOIN draws d ON dw.draw_id=d.id JOIN users u ON dw.user_id=u.id ORDER BY dw.announced_at DESC LIMIT 4")->fetchAll();
$totalUsers   = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalWinners = $db->query("SELECT COUNT(*) FROM draw_winners")->fetchColumn();
$totalDraws   = $db->query("SELECT COUNT(*) FROM draws WHERE status='completed'")->fetchColumn();
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ZoeFeeds — Loyalty Reward & Raffle Platform</title>
  <meta name="description" content="ZoeFeeds is  official loyalty reward and raffle draw platform. Redeem codes, enter draws, win amazing prizes — fair, transparent and compliant.">
  <script src="<?= APP_URL ?>/assets/js/tailwind.js"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
  <style>
    *{font-family:'Poppins',sans-serif!important}
    .gt{background:linear-gradient(135deg,#f97316,#fb923c,#fcd34d);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;}
    .glass{background:rgba(255,255,255,0.04);backdrop-filter:blur(14px);border:1px solid rgba(255,255,255,0.08);}
    .hero-float{animation:hf 7s ease-in-out infinite;}
    @keyframes hf{0%,100%{transform:translateY(0)}50%{transform:translateY(-14px)}}
    .particle{position:absolute;border-radius:50%;opacity:.25;animation:pf var(--d) ease-in-out infinite;}
    @keyframes pf{0%,100%{transform:translate(0,0)}40%{transform:translate(18px,-26px)}70%{transform:translate(-12px,10px)}}
    .ticker-wrap{overflow:hidden;}
    .ticker{display:flex;width:max-content;animation:tk 28s linear infinite;}
    .ticker:hover{animation-play-state:paused;}
    @keyframes tk{from{transform:translateX(0)}to{transform:translateX(-50%)}}
    .faq-body{max-height:0;overflow:hidden;transition:max-height .4s ease;}
    .faq-open .faq-body{max-height:500px;}
    .faq-open .faq-arr{transform:rotate(180deg);}
    .faq-arr{transition:transform .3s;}
    .draw-card-land{background:var(--bg-card);border:1px solid var(--border);border-radius:20px;overflow:hidden;transition:all .3s;cursor:pointer;}
    .draw-card-land:hover{transform:translateY(-5px);border-color:rgba(249,115,22,.4);box-shadow:0 20px 48px rgba(0,0,0,.5);}
    .step-connector{position:absolute;top:28px;left:calc(50% + 28px);width:calc(100% - 56px);height:2px;background:linear-gradient(90deg,rgba(249,115,22,.5),transparent);}
    @media(max-width:768px){.step-connector{display:none;}}
    .winner-card{background:linear-gradient(135deg,rgba(234,179,8,.12),rgba(0,0,0,0));border:1px solid rgba(234,179,8,.25);}
    section{scroll-margin-top:72px;}
    /* Live counter animation */
    .live-counter{transition:all .3s ease;}
  </style>
</head>
<body class="bg-[#0a0f1a] text-white overflow-x-hidden">

<!-- ======================================================
     NAVBAR
====================================================== -->
<nav class="fixed top-0 w-full z-50 glass border-b border-white/5">
  <div class="max-w-7xl mx-auto px-4 sm:px-6">
    <div class="flex items-center justify-between h-16">

      <a href="<?= APP_URL ?>" class="flex items-center gap-2 flex-shrink-0">
        <div class="w-9 h-9 bg-orange-500 rounded-xl flex items-center justify-center font-black text-white text-xl">Z</div>
        <span class="font-bold text-xl">Zoe<span class="text-orange-500">Feeds</span></span>
      </a>

      <div class="hidden md:flex items-center gap-7">
        <a href="#draws"        class="text-gray-400 hover:text-white text-sm font-medium transition-colors">Draws</a>
        <a href="#how-it-works" class="text-gray-400 hover:text-white text-sm font-medium transition-colors">How It Works</a>
        <a href="#winners"      class="text-gray-400 hover:text-white text-sm font-medium transition-colors">Winners</a>
        <a href="#about"        class="text-gray-400 hover:text-white text-sm font-medium transition-colors">About</a>
        <a href="#faq"          class="text-gray-400 hover:text-white text-sm font-medium transition-colors">FAQ</a>
        <a href="<?= APP_URL ?>/user/terms.php" class="text-gray-400 hover:text-white text-sm font-medium transition-colors">Terms</a>
        <!-- Vendor separator + link -->
        <div class="w-px h-4 bg-white/10"></div>
        <a href="<?= APP_URL ?>/vendor"
           class="flex items-center gap-1.5 text-sm font-semibold transition-colors"
           style="color:#a78bfa"
           onmouseover="this.style.color='#c4b5fd'"
           onmouseout="this.style.color='#a78bfa'">
          🏪 Become a Vendor
        </a>
      </div>

      <div class="flex items-center gap-2">
        <a href="<?= APP_URL ?>/user/login.php"    class="hidden sm:inline-flex btn btn-secondary btn-sm">Log In</a>
        <a href="<?= APP_URL ?>/user/register.php" class="btn btn-primary btn-sm">Get Started</a>
        <button id="mob-menu-btn" class="md:hidden p-2 text-gray-400 hover:text-white" onclick="document.getElementById('mob-menu').classList.toggle('hidden')">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
      </div>
    </div>

    <!-- Mobile menu -->
    <div id="mob-menu" class="hidden md:hidden pb-4 space-y-1 border-t border-white/5 pt-3">
      <?php foreach(['#draws'=>'Draws','#how-it-works'=>'How It Works','#winners'=>'Winners','#about'=>'About','#faq'=>'FAQ'] as $h=>$l): ?>
      <a href="<?= $h ?>" class="block px-3 py-2 text-gray-400 hover:text-white text-sm rounded-lg hover:bg-white/5" onclick="document.getElementById('mob-menu').classList.add('hidden')"><?= $l ?></a>
      <?php endforeach; ?>
      <!-- Vendor link in mobile menu -->
      <a href="<?= APP_URL ?>/vendor"
         class="block px-3 py-2 text-sm font-semibold rounded-lg hover:bg-purple-500/10"
         style="color:#a78bfa">
        🏪 Become a Vendor
      </a>
      <div class="border-t border-white/5 mt-2 pt-2">
        <a href="<?= APP_URL ?>/user/login.php"    class="block px-3 py-2 text-orange-400 font-semibold text-sm">Log In</a>
        <a href="<?= APP_URL ?>/user/register.php" class="block px-3 py-2 text-white font-semibold text-sm bg-orange-500 rounded-xl text-center mt-1">Get Started Free</a>
      </div>
    </div>
  </div>
</nav>


<!-- ======================================================
     HERO
====================================================== -->
<section class="relative min-h-screen flex items-center pt-16 overflow-hidden">
  <!-- Background glow -->
  <div class="absolute inset-0 pointer-events-none">
    <div class="absolute top-1/4 right-1/4 w-96 h-96 bg-orange-500/10 rounded-full blur-3xl"></div>
    <div class="absolute bottom-1/4 left-1/4 w-64 h-64 bg-cyan-500/6 rounded-full blur-3xl"></div>
    <div class="absolute inset-0 opacity-[0.03]" style="background-image:repeating-linear-gradient(0deg,transparent,transparent 40px,rgba(255,255,255,.5) 40px,rgba(255,255,255,.5) 41px),repeating-linear-gradient(90deg,transparent,transparent 40px,rgba(255,255,255,.5) 40px,rgba(255,255,255,.5) 41px)"></div>
  </div>
  <!-- Particles -->
  <div class="particle w-3 h-3 bg-orange-500" style="top:18%;left:8%;--d:8s"></div>
  <div class="particle w-2 h-2 bg-cyan-400"   style="top:65%;left:4%;--d:10s"></div>
  <div class="particle w-4 h-4 bg-orange-400" style="top:28%;right:7%;--d:7s"></div>
  <div class="particle w-2 h-2 bg-yellow-400" style="top:72%;right:12%;--d:9s"></div>
  <div class="particle w-3 h-3 bg-cyan-300"   style="top:88%;left:28%;--d:11s"></div>

  <div class="max-w-7xl mx-auto px-4 sm:px-6 py-20 relative z-10 w-full">
    <div class="grid lg:grid-cols-2 gap-14 items-center">

      <!-- Left -->
      <div>
        <div class="inline-flex items-center gap-2 bg-orange-500/10 border border-orange-500/20 rounded-full px-4 py-2 text-sm font-semibold text-orange-400 mb-6">
          <span class="pulse-dot"></span> Official Loyalty Reward Platform
        </div>
        <h1 class="text-5xl md:text-6xl lg:text-7xl font-black leading-[1.05] mb-6 tracking-tight">
          Redeem.<br>
          Enter.<br>
          <span class="gt">Win Big.</span>
        </h1>
        <p class="text-gray-400 text-lg leading-relaxed mb-8 max-w-lg">
          ZoeFeeds rewards loyal customers through a <strong class="text-white">fair, transparent, and fully regulated</strong> draw program. Collect 15-digit raffle codes from eligible purchases, enter live draws, and win life-changing prizes.
        </p>
        <!-- Live Stats — rendered from DB and updated in real-time via JS -->
        <div class="flex flex-wrap gap-6 mb-9 text-sm">
          <div class="flex items-center gap-2">
            <span id="stat-users" class="live-counter text-2xl font-black text-orange-400"><?= number_format((int)$totalUsers) ?></span>
            <span class="text-gray-500">Registered</span>
          </div>
          <div class="flex items-center gap-2">
            <span id="stat-winners" class="live-counter text-2xl font-black text-green-400"><?= number_format((int)$totalWinners) ?></span>
            <span class="text-gray-500">Winners</span>
          </div>
          <div class="flex items-center gap-2">
            <span id="stat-draws" class="live-counter text-2xl font-black text-cyan-400"><?= number_format((int)$totalDraws) ?></span>
            <span class="text-gray-500">Draws Completed</span>
          </div>
        </div>
        <div class="flex flex-wrap gap-3 mb-8">
          <a href="<?= APP_URL ?>/user/register.php" class="btn btn-primary px-8 py-4 text-base font-bold">🎟️ Start Winning Free</a>
          <a href="#how-it-works" class="btn btn-secondary px-8 py-4 text-base">How It Works →</a>
        </div>
        <!-- Login prompt -->
        <div class="flex items-center gap-3 mb-6">
          <a href="<?= APP_URL ?>/user/login.php" class="btn btn-secondary px-6 py-3 text-sm font-semibold">🔑 Log In to Your Account</a>
        </div>
        <div class="flex flex-wrap gap-4 text-xs text-gray-500">
          <span class="flex items-center gap-1.5"><span class="text-green-400 text-base">✓</span> Free to join</span>
          <span class="flex items-center gap-1.5"><span class="text-green-400 text-base">✓</span> Regulated &amp; compliant</span>
          <span class="flex items-center gap-1.5"><span class="text-green-400 text-base">✓</span> Transparent draw process</span>
          <span class="flex items-center gap-1.5"><span class="text-green-400 text-base">✓</span> Verified winners</span>
        </div>
      </div>

      <!-- Right: Floating card mockup -->
      <div class="relative hidden lg:block">
        <div class="hero-float">
          <div class="card-glow p-6 glow-orange" style="border-radius:24px">
            <div class="flex items-center justify-between mb-5">
              <div>
                <div class="text-xs text-gray-400 font-medium">Eligibility Balance</div>
                <div class="text-4xl font-black text-orange-400 mt-1" style="font-family:'Courier New',monospace">47</div>
                <div class="text-xs text-gray-500">Active Codes</div>
              </div>
              <div class="w-14 h-14 bg-orange-500/20 rounded-2xl flex items-center justify-center text-3xl">🎯</div>
            </div>
            <div class="bg-black/30 rounded-xl p-4 mb-4">
              <div class="text-xs text-gray-500 mb-2">Latest Code</div>
              <div class="font-mono text-orange-400 font-bold text-lg tracking-widest">7 4 2 0 8 1 9 3 5 6 2 7 4 0 1</div>
              <div class="badge badge-success mt-2">● Active</div>
            </div>
            <div class="bg-gradient-to-r from-orange-900/40 to-transparent rounded-xl p-4 border border-orange-500/20">
              <div class="flex items-center justify-between">
                <div>
                  <div class="text-xs text-orange-300 font-semibold">🏆 Daily Patronage Draw</div>
                  <div class="text-xs text-gray-400 mt-1">Ends in</div>
                  <div class="font-bold text-white">04h 22m 38s</div>
                </div>
                <div class="text-2xl">🎰</div>
              </div>
            </div>
          </div>
          <div class="absolute -top-4 -right-4 glass rounded-2xl px-4 py-3 glow-blue">
            <div class="text-xs text-cyan-300 font-semibold">🎉 Winner!</div>
            <div class="text-xs text-gray-400">Emeka just won</div>
            <div class="text-sm font-bold text-white">₦500,000</div>
          </div>
          <div class="absolute -bottom-4 -left-4 glass rounded-2xl px-4 py-3">
            <div class="text-xs text-green-400 font-semibold">✓ Code Redeemed</div>
            <div class="font-mono text-xs text-gray-300 mt-1">7 4 2 0 8 1 9 ...</div>
          </div>
        </div>
      </div>

    </div>
  </div>

  <div class="absolute bottom-8 left-1/2 -translate-x-1/2 flex flex-col items-center gap-1 text-gray-600 text-xs animate-bounce">
    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
  </div>
</section>


<!-- ======================================================
     TICKER
====================================================== -->
<div class="bg-orange-500/8 border-y border-orange-500/15 py-3 ticker-wrap">
  <div class="ticker gap-0">
    <?php $items=['🎯 Raffle Draw Platform','✅ Verified &amp; Compliant','🏆 Life-Changing Prizes','🔒 Secure &amp; Transparent','🎟️ Free Code Redemption','📱 Airtime &amp; Data Soon','⚡ Utility Bills Soon'];
    for($i=0;$i<4;$i++) foreach($items as $it): ?>
    <span class="text-orange-300/80 font-medium text-sm px-8 whitespace-nowrap"><?= $it ?></span>
    <span class="text-orange-500/30 text-xl px-2">·</span>
    <?php endforeach; ?>
  </div>
</div>


<!-- ======================================================
     ADVERT SLIDESHOW
====================================================== -->
<?php if ($slides): ?>
<section class="py-14 max-w-7xl mx-auto px-4 sm:px-6">
  <div class="flex items-center justify-between mb-6">
    <h2 class="text-2xl font-bold">Featured Campaigns</h2>
  </div>
  <div class="relative slideshow bg-[#111827] rounded-2xl overflow-hidden" data-slideshow style="min-height:260px">
    <?php foreach($slides as $s): ?>
    <div class="slide">
      <?php if($s['image_path']&&file_exists(UPLOAD_PATH.$s['image_path'])): ?>
      <img src="<?= APP_URL ?>/uploads/<?= e($s['image_path']) ?>" class="w-full h-64 object-cover" alt="<?= e($s['title']??'') ?>">
      <?php else: ?>
      <div class="h-64 flex flex-col items-center justify-center" style="background:linear-gradient(135deg,#1a2235,#0d1929)">
        <div class="text-5xl mb-3">🎯</div><div class="text-xl font-bold"><?= e($s['title']??'ZoeFeeds Campaign') ?></div>
      </div>
      <?php endif; ?>
    </div>
    <?php endforeach; ?>
    <div class="slide-dots absolute bottom-4 left-0 right-0 flex justify-center gap-2"></div>
  </div>
</section>
<?php endif; ?>


<!-- ======================================================
     LIVE DRAWS
====================================================== -->
<section id="draws" class="py-16 bg-[#0d1218]">
  <div class="max-w-7xl mx-auto px-4 sm:px-6">
    <div class="text-center mb-12">
      <div class="inline-flex items-center gap-2 bg-red-500/10 border border-red-500/20 rounded-full px-4 py-1.5 text-sm font-semibold text-red-400 mb-4">
        <span class="pulse-dot" style="background:#ef4444"></span> Live Draws
      </div>
      <h2 class="text-4xl font-black mb-3">Active Draw Campaigns</h2>
      <p class="text-gray-400 max-w-lg mx-auto text-sm">Enter draws using your redeemed codes. Every valid entry has an equal chance of selection. There are <strong class="text-orange-400">mandatory</strong> winners every draw.</p>
    </div>

    <?php if ($draws): ?>
    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
      <?php foreach($draws as $d): ?>
      <div class="draw-card-land" onclick="location.href='<?= APP_URL ?>/user/register.php'">
        <div class="h-40 flex items-center justify-center text-5xl" style="background:linear-gradient(135deg,#1a2235,#0d1118)">
          <?php if($d['banner_image']&&file_exists(UPLOAD_PATH.$d['banner_image'])): ?>
          <img src="<?= APP_URL ?>/uploads/<?= e($d['banner_image']) ?>" class="w-full h-full object-cover" alt="">
          <?php else: ?>🎰<?php endif; ?>
        </div>
        <div class="p-5">
          <div class="flex gap-2 mb-2 flex-wrap">
            <span class="badge badge-success">● LIVE</span>
            <?php if($d['category']): ?><span class="badge badge-info"><?= e($d['category']) ?></span><?php endif; ?>
          </div>
          <h3 class="font-bold mb-2 line-clamp-2"><?= e($d['title']) ?></h3>
          <?php if($d['prize_details']): ?><p class="text-sm text-orange-400 font-semibold mb-3">🏆 <?= e(mb_substr($d['prize_details'],0,55)) ?>…</p><?php endif; ?>
          <div class="flex gap-3 items-center flex-wrap mb-4" data-countdown="<?= e($d['end_date']) ?>"></div>
          <a href="<?= APP_URL ?>/user/register.php" class="btn btn-primary w-full text-sm py-2.5">Enter Draw →</a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
      <?php $dummyDraws=[
        ['🏆','Monthly Grand Draw','Win up to ₦1,000,000','Grand Prize','2026-07-31 23:59:00'],
        ['🎯','Daily Patronage Draw','Win daily cash prizes','Daily Patronage','2026-06-30 23:59:00'],
        ['🚌','Transport Credits Draw','Win ₦50,000 transport credits','Transport','2026-07-15 23:59:00'],
        ['📱','Dashboard Loyalty Draw','Active users only','Dashboard Loyalty','2026-07-01 23:59:00'],
        ['⭐','Vendor Campaign Draw','Special campaign draw','Vendor Campaign','2026-06-28 23:59:00'],
        ['🏛️','Government Ticket Draw','Special category draw','Government Ticket','2026-08-01 23:59:00'],
      ]; foreach($dummyDraws as $dd): ?>
      <div class="draw-card-land" onclick="location.href='<?= APP_URL ?>/user/register.php'">
        <div class="h-40 flex items-center justify-center text-5xl" style="background:linear-gradient(135deg,#1a2235,#0d1118)"><?= $dd[0] ?></div>
        <div class="p-5">
          <div class="flex gap-2 mb-2"><span class="badge badge-success">● LIVE</span><span class="badge badge-info"><?= $dd[3] ?></span></div>
          <h3 class="font-bold mb-2"><?= $dd[1] ?></h3>
          <p class="text-sm text-orange-400 font-semibold mb-3">🏆 <?= $dd[2] ?></p>
          <div class="flex gap-3 mb-4" data-countdown="<?= $dd[4] ?>"></div>
          <a href="<?= APP_URL ?>/user/register.php" class="btn btn-primary w-full text-sm py-2.5">Enter Draw →</a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</section>


<!-- ======================================================
     HOW IT WORKS
====================================================== -->
<section id="how-it-works" class="py-20 max-w-7xl mx-auto px-4 sm:px-6">
  <div class="text-center mb-14">
    <h2 class="text-4xl font-black mb-3">How ZoeFeeds Works</h2>
    <p class="text-gray-400 max-w-xl mx-auto">Simple steps to start winning. Participation is straightforward, transparent, and fully documented.</p>
  </div>
  <div class="grid md:grid-cols-4 gap-8 relative">
    <?php $steps=[
      ['1','📝','Create Your Account','Register with your phone number in under 60 seconds. Free forever. Age 18+ only.'],
      ['2','🎟️','Redeem Your Code','Redeem your unique 15-digit raffle code given by our verified vendor into your ZoeFeeds wallet.'],
      ['3','🎯','Enter a Draw','Choose an active draw campaign and submit your code(s). More codes = more chances.'],
      ['4','🏆','Win Prizes','Our transparent, certified manual machine draw process selects winners fairly. Winners are notified via SMS, email &amp; website announcement.'],
    ]; foreach($steps as $i=>$s): ?>
    <div class="text-center relative">
      <?php if($i<3): ?><div class="step-connector hidden md:block"></div><?php endif; ?>
      <div class="w-16 h-16 bg-orange-500/10 border-2 border-orange-500/25 rounded-2xl flex items-center justify-center text-3xl mx-auto mb-4 relative z-10"><?= $s[1] ?></div>
      <div class="inline-flex items-center justify-center w-6 h-6 bg-orange-500 rounded-full text-white text-xs font-black mb-2"><?= $s[0] ?></div>
      <h3 class="font-bold text-base mb-2"><?= $s[2] ?></h3>
      <p class="text-gray-400 text-sm leading-relaxed"><?= $s[3] ?></p>
    </div>
    <?php endforeach; ?>
  </div>
  <div class="text-center mt-12">
    <a href="<?= APP_URL ?>/user/register.php" class="btn btn-primary px-10 py-4 text-base font-bold">Start Now — It's Free</a>
  </div>
</section>


<!-- ======================================================
     ABOUT
====================================================== -->
<section id="about" class="py-20 bg-[#0d1218]">
  <div class="max-w-7xl mx-auto px-4 sm:px-6">
    <div class="grid lg:grid-cols-2 gap-14 items-center">
      <div>
        <div class="inline-flex items-center gap-2 bg-green-500/10 border border-green-500/20 rounded-full px-4 py-1.5 text-sm font-semibold text-green-400 mb-5">
          ✅ Regulated &amp; Compliant
        </div>
        <h2 class="text-4xl font-black mb-5">Our Purpose</h2>
        <p class="text-gray-400 leading-relaxed mb-4">
          The <strong class="text-white">ZoeFeeds Reward Draw</strong> is a customer appreciation and promotional campaign organized by ZoeFeeds. It is designed to reward eligible customers through a transparent and fair draw process.
        </p>
        <p class="text-gray-400 leading-relaxed mb-4">
          Our aim is to <strong class="text-white">reward and appreciate loyal customers</strong> through a fair, transparent, and compliant promotional reward program while providing access to valuable products and services.
        </p>
        <p class="text-gray-400 leading-relaxed mb-6">
          The Promotion is intended solely as a <strong class="text-white">customer appreciation initiative</strong> and shall not be construed as a gambling, betting, or wagering activity. All draws are conducted in accordance with applicable  laws and regulatory approvals.
        </p>
        <div class="flex flex-wrap gap-3">
          <a href="<?= APP_URL ?>/user/terms.php" class="btn btn-secondary">📄 Read Full T&amp;C</a>
          <a href="<?= APP_URL ?>/user/register.php" class="btn btn-primary">Join ZoeFeeds</a>
        </div>
      </div>
      <div class="grid grid-cols-2 gap-4">
        <?php $vals=[
          ['🔒','Secure','End-to-end account security and data protection'],
          ['⚖️','Fair','Equal chance for every valid entry. Certified random selection process.'],
          ['👁️','Transparent','Live draw reveals. Every digit shown publicly in real-time.'],
          ['✅','Verified','All winners independently verified before prizes are awarded.'],
          ['🇳🇬','Compliant','Operating under Nigerian laws with all required regulatory approvals.'],
          ['🏆','Real Prizes','Cash, credits, and other tangible prizes — no points, no vouchers.'],
        ]; foreach($vals as $v): ?>
        <div class="card p-5">
          <div class="text-2xl mb-2"><?= $v[0] ?></div>
          <div class="font-bold text-sm mb-1"><?= $v[1] ?></div>
          <div class="text-xs text-gray-500 leading-relaxed"><?= $v[2] ?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>


<!-- ======================================================
     RECENT WINNERS
====================================================== -->
<section id="winners" class="py-20 max-w-7xl mx-auto px-4 sm:px-6">
  <div class="text-center mb-12">
    <h2 class="text-4xl font-black mb-3">Recent Winners</h2>
    <p class="text-gray-400 max-w-xl mx-auto text-sm">Winners are selected transparently. Every winner is verified before prizes are awarded.</p>
  </div>
  <?php if ($winners): ?>
  <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
    <?php foreach($winners as $w):
      $initial = strtoupper(mb_substr($w['full_name'],0,1));
      $maskedName = $initial.'***'.mb_substr($w['full_name'],-1);
    ?>
    <div class="winner-card rounded-2xl p-5 text-center">
      <div class="text-4xl mb-3">🏆</div>
      <div class="font-bold text-yellow-400 text-sm mb-0.5"><?= e($maskedName) ?></div>
      <div class="text-xs text-gray-400 mb-2"><?= e(mb_substr($w['draw_title'],0,30)) ?>…</div>
      <div class="font-mono text-xs text-orange-400 bg-black/20 rounded-lg px-2 py-1 mb-2"><?= e(substr($w['winning_code'],0,7)).'·····' ?></div>
      <div class="text-xs text-gray-600"><?= date('M j, Y',strtotime($w['announced_at'])) ?></div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php else: ?>
  <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
    <?php $dummyW=[['E***a','Monthly Grand Draw','₦500,000'],['B***g','Daily Draw','₦50,000'],['C***n','Transport Draw','₦30,000'],['M***e','Dashboard Loyalty','₦20,000']];
    foreach($dummyW as $dw): ?>
    <div class="winner-card rounded-2xl p-5 text-center">
      <div class="text-4xl mb-3">🏆</div>
      <div class="font-bold text-yellow-400 text-sm mb-0.5"><?= $dw[0] ?></div>
      <div class="text-xs text-gray-400 mb-2"><?= $dw[1] ?></div>
      <div class="font-semibold text-orange-400 text-sm mb-1"><?= $dw[2] ?></div>
      <div class="text-xs text-gray-600">Verified Winner</div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
  <div class="text-center">
    <a href="<?= APP_URL ?>/user/winners.php" class="btn btn-secondary px-8 py-3">View All Winners →</a>
  </div>
</section>


<!-- ======================================================
     TESTIMONIALS
====================================================== -->
<section class="py-20 max-w-7xl mx-auto px-4 sm:px-6">
  <div class="text-center mb-12">
    <h2 class="text-4xl font-black mb-3">What Participants Say</h2>
    <p class="text-gray-400 text-sm">Real experiences from real ZoeFeeds users</p>
  </div>
  <div class="grid md:grid-cols-3 gap-5">
    <?php $testimonials=[
      ['E. O.','Lagos','⭐⭐⭐⭐⭐','I redeemed 3 codes and won in the monthly draw! The process was completely transparent — I watched every digit revealed live. ZoeFeeds is legit!'],
      ['B. A.','Abuja','⭐⭐⭐⭐⭐','Got my codes through an eligible purchase, entered the draw, and won. The notification came same day. Easy process and everything was verified properly.'],
      ['C. N.','Port Harcourt','⭐⭐⭐⭐⭐','The live draw experience is amazing. You can see each digit revealed on screen in real-time. Very transparent, exactly as they promised in their terms.'],
    ]; foreach($testimonials as $t): ?>
    <div class="card p-6">
      <div class="text-yellow-400 mb-3"><?= $t[2] ?></div>
      <p class="text-gray-300 text-sm leading-relaxed mb-5">"<?= $t[3] ?>"</p>
      <div class="flex items-center gap-3">
        <div class="w-9 h-9 bg-orange-500/20 rounded-full flex items-center justify-center font-bold text-orange-400"><?= $t[0][0] ?></div>
        <div><div class="font-semibold text-sm"><?= $t[0] ?></div><div class="text-xs text-gray-500"><?= $t[1] ?></div></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</section>


<!-- ======================================================
     FAQ  — 20 Questions
====================================================== -->
<section id="faq" class="py-20 bg-[#0d1218]">
  <div class="max-w-3xl mx-auto px-4 sm:px-6">
    <div class="text-center mb-12">
      <h2 class="text-4xl font-black mb-3">Frequently Asked Questions</h2>
      <p class="text-gray-400 text-sm">Answers based on the official ZoeFeeds Terms &amp; Conditions</p>
    </div>
    <?php $faqs=[
      [
        'How do I get ZoeFeeds raffle codes (raffle tickets)?',
        'You automatically receive real free ZoeFeeds codes (raffle tickets) in two ways: the first is whenever you purchase eligible products or services from ZoeFeeds; the second is by gifting — whenever anyone freely gifts you the ticket code directly or automatically. Each qualifying purchase earns you entry codes that are included in the promotional draw. When you redeem your code it will be recorded and linked to your account. You can also check your account dashboard to view your available codes.'
      ],
      [
        'How much is a ZoeFeeds raffle code (raffle ticket)?',
        'ZoeFeeds raffle codes are completely free and are automatically awarded when you purchase eligible products or services on ZoeFeeds. You do not pay separately for raffle codes. <strong class="text-red-400">Warning:</strong> ZoeFeeds does not sell raffle codes or tickets, and anyone claiming to sell them is not authorized by ZoeFeeds. Our aim is to reward and appreciate our customers through a fair and transparent promotional reward program while providing affordable products and services. On any third-party platform, ZoeFeeds codes must be transferred to you as a gift — never sold.'
      ],
      [
        'What is a ZoeFeeds Gift Code (Raffle Ticket)?',
        'A ZoeFeeds Gift Code is a free 15-digit code you receive after completing an eligible transaction on ZoeFeeds, or one that is gifted to you by another party. You can redeem the code in your ZoeFeeds wallet and use it to enter any available draw of your choice. Each code serves as your unique entry identifier in the draw. If your entry is selected as the winning identifier according to the draw rules, you win the prize.'
      ],
      [
        'How can I check my raffle codes?',
        'You can view all your raffle codes in your ZoeFeeds account dashboard after a successful purchase or after you have manually entered and redeemed your raffle code into your ZoeFeeds wallet.'
      ],
      [
        'When will the raffle draw take place?',
        'Each raffle draw\'s date is always counting down on our website and may be announced on our official social media platforms.'
      ],
      [
        'How is the winner selected?',
        'Winners are selected through a transparent and random manual process draw to ensure fairness for all participants. The owner of the ticket that matches the draw result in the most positions wins. If there is a tie, the winner with more ticket entries is chosen. If still tied, the participant who registered earliest on the platform wins.'
      ],
      [
        'Who can participate in the ZoeFeeds Reward Draw?',
        'The promotion is open only to persons aged 18 years and above who meet the participation requirements stated in the promotion rules, possess valid identification, and hold a valid ZoeFeeds account.'
      ],
      [
        'Can I get more than one raffle code?',
        'Yes. Each ticket gift code you receive will earn you additional raffle tickets, increasing your chances of matching the draw.'
      ],
      [
        'What prizes can be won?',
        'Prize details for each promotional draw will be announced before the draw date and displayed on that particular draw\'s page and on our official channels.'
      ],
      [
        'How will I know if I win?',
        'Winners will be contacted through their registered phone number and/or email address and may also be announced on ZoeFeeds\' official platforms live!'
      ],
      [
        'What happens if a winner cannot be reached?',
        'If a winner cannot be contacted or fails to verify their identity within the specified period, another winner may be selected according to the promotion rules. ZoeFeeds operates by the principle that someone must win in each draw — it is mandatory!'
      ],
      [
        'Can I transfer my raffle codes to someone else?',
        'Yes. Redeemed raffle codes are linked to the account that earned them and guarded with a transfer PIN. However, if you wish, you can transfer a code as a gift to another user — but codes must never be sold.'
      ],
      [
        'Is the ZoeFeeds Reward Draw a lottery or gambling scheme?',
        'No. The ZoeFeeds Reward Draw is a customer appreciation promotion. Raffle codes are provided free as a promotional benefit and are not sold. They must remain free and not for sale — forever.'
      ],
      [
        'Are my chances of winning the same as everyone else\'s?',
        'Yes. Every valid raffle code you enter into a particular draw has an equal chance of matching with the draw. Entering more codes increases your chances.'
      ],
      [
        'How can I report fraud or suspicious activities?',
        'If anyone claims to sell ZoeFeeds raffle codes or contacts you requesting payment to claim a prize, please contact ZoeFeeds support immediately through our official channels on the website.'
      ],
      [
        'Where can I find the full promotion rules?',
        'The complete Terms and Conditions for the ZoeFeeds Reward Draws are available on our website. <a href="'.APP_URL.'/user/terms.php" class="text-orange-400 hover:underline">Click here to read the full Terms &amp; Conditions →</a>'
      ],
      [
        'What do I do when I am gifted a ZoeFeeds raffle code offline?',
        'If you receive a ZoeFeeds raffle code offline, log in to your ZoeFeeds account and redeem it using the Raffle Code Redemption button on your dashboard. Once redeemed, your eligibility balance will increase and the code will be added to your Redeemed Codes Wallet, where you can use it to enter any available draw of your choice.'
      ],
      [
        'How can I verify my raffle codes?',
        'All valid ZoeFeeds raffle codes must be redeemed through your ZoeFeeds account. Once a code is successfully redeemed, it will appear in your Redeemed Codes Wallet. If a code cannot be redeemed or does not appear in your wallet, contact ZoeFeeds Support for assistance.'
      ],
      [
        'Can I enter a particular draw with more than one raffle ticket code?',
        'Yes. You are encouraged to enter with as many tickets as you can to increase your chances of being the winner.'
      ],
      [
        'What if nobody won in a draw?',
        'ZoeFeeds raffles operate by the principle that someone must win and be given the prize in each draw. It is mandatory — every draw produces a winner.'
      ],
    ]; foreach($faqs as $i=>$faq): ?>
    <div class="faq-item card mb-3 overflow-hidden rounded-xl">
      <button onclick="this.closest('.faq-item').classList.toggle('faq-open')" class="w-full text-left px-5 py-4 flex items-center justify-between gap-3 hover:bg-white/2 transition-colors">
        <span class="font-semibold text-sm md:text-base"><?= $faq[0] ?></span>
        <svg class="faq-arr w-5 h-5 text-orange-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
      </button>
      <div class="faq-body">
        <div class="px-5 pb-5 text-sm text-gray-400 leading-relaxed border-t border-white/5 pt-4"><?= $faq[1] ?></div>
      </div>
    </div>
    <?php endforeach; ?>
    <div class="text-center mt-8">
      <a href="<?= APP_URL ?>/user/terms.php" class="text-orange-400 hover:underline text-sm font-medium">Read the full Terms &amp; Conditions →</a>
    </div>
  </div>
</section>


<!-- ======================================================
     WARNING BANNER
====================================================== -->
<section class="py-10 bg-red-500/8 border-y border-red-500/20">
  <div class="max-w-4xl mx-auto px-4 sm:px-6 text-center">
    <div class="text-3xl mb-3">⚠️</div>
    <h3 class="text-xl font-black text-red-400 mb-2">Official Warning</h3>
    <p class="text-gray-300 text-sm leading-relaxed max-w-2xl mx-auto">
      <strong>ZoeFeeds does not authorize the sale of raffle codes.</strong> Never pay any individual claiming to sell ZoeFeeds raffle codes or guarantee winning entries. All official information is communicated solely through ZoeFeeds' official channels at <strong class="text-white">www.zoefeeds.com</strong>.
    </p>
  </div>
</section>


<!-- ======================================================
     CTA
====================================================== -->
<section class="py-24 relative overflow-hidden">
  <div class="absolute inset-0 pointer-events-none" style="background:radial-gradient(ellipse at center,rgba(249,115,22,0.12) 0%,transparent 70%)"></div>
  <div class="max-w-3xl mx-auto px-4 text-center relative z-10">
    <div class="text-6xl mb-5">🎯</div>
    <h2 class="text-4xl md:text-5xl font-black mb-4 tracking-tight">Ready to Start Winning?</h2>
    <p class="text-gray-400 text-lg mb-8 max-w-xl mx-auto">Join thousands of  participating in ZoeFeeds' fair and transparent reward draws. Create your free account today.</p>
    <div class="flex flex-wrap gap-4 justify-center">
      <a href="<?= APP_URL ?>/user/register.php" class="btn btn-primary px-12 py-5 text-lg font-bold">🎟️ Create Free Account</a>
      <a href="<?= APP_URL ?>/user/login.php"    class="btn btn-secondary px-10 py-5 text-lg font-bold">🔑 Log In</a>
    </div>
    <div class="text-xs text-gray-600 mt-4">No credit card required · Free forever · Instant setup · Age 18+</div>
  </div>
</section>


<!-- ======================================================
     FOOTER
====================================================== -->
<footer class="bg-[#060b12] border-t border-white/5 py-14">
  <div class="max-w-7xl mx-auto px-4 sm:px-6">
    <div class="grid md:grid-cols-4 gap-8 mb-10">

      <div class="md:col-span-1">
        <div class="flex items-center gap-2 mb-4">
          <div class="w-8 h-8 bg-orange-500 rounded-lg flex items-center justify-center font-black text-white text-sm">Z</div>
          <span class="font-bold text-lg">Zoe<span class="text-orange-500">Feeds</span></span>
        </div>
        <p class="text-gray-500 text-sm leading-relaxed mb-4">loyalty reward and raffle eligibility platform. Fair. Transparent. Compliant.</p>
        <div class="text-xs text-gray-600">Official Website:<br><span class="text-gray-400">www.zoefeeds.com</span></div>
      </div>

      <div>
        <h4 class="font-bold text-sm mb-4 text-gray-300 uppercase tracking-wider">Platform</h4>
        <ul class="space-y-2 text-sm">
          <li><a href="<?= APP_URL ?>/user/register.php" class="text-gray-500 hover:text-orange-400 transition-colors">Get Started</a></li>
          <li><a href="#draws"         class="text-gray-500 hover:text-orange-400 transition-colors">Live Draws</a></li>
          <li><a href="#how-it-works"  class="text-gray-500 hover:text-orange-400 transition-colors">How It Works</a></li>
          <li><a href="#winners"       class="text-gray-500 hover:text-orange-400 transition-colors">Winners</a></li>
          <li><a href="<?= APP_URL ?>/user/winners.php" class="text-gray-500 hover:text-orange-400 transition-colors">All Winners</a></li>
        </ul>
      </div>

      <div>
        <h4 class="font-bold text-sm mb-4 text-gray-300 uppercase tracking-wider">Account</h4>
        <ul class="space-y-2 text-sm">
          <li><a href="<?= APP_URL ?>/user/login.php"    class="text-gray-500 hover:text-orange-400 transition-colors">Log In</a></li>
          <li><a href="<?= APP_URL ?>/user/register.php" class="text-gray-500 hover:text-orange-400 transition-colors">Register</a></li>
        </ul>
      </div>

      <div>
        <h4 class="font-bold text-sm mb-4 text-gray-300 uppercase tracking-wider">Legal &amp; Contact</h4>
        <ul class="space-y-2 text-sm">
          <li><a href="<?= APP_URL ?>/user/terms.php"    class="text-gray-500 hover:text-orange-400 transition-colors">Terms &amp; Conditions</a></li>
          <li><a href="<?= APP_URL ?>/user/terms.php#privacy" class="text-gray-500 hover:text-orange-400 transition-colors">Privacy Policy</a></li>
          <li><a href="<?= APP_URL ?>/user/terms.php#rules"   class="text-gray-500 hover:text-orange-400 transition-colors">Draw Rules</a></li>
        </ul>
        <div class="mt-5 space-y-1 text-xs text-gray-500">
          <div>📧 support@zoefeeds.com</div>
          <div>🌐 www.zoefeeds.com</div>
        </div>
      </div>

    </div>
    <div class="border-t border-white/5 pt-8 flex flex-col md:flex-row items-center justify-between gap-4 text-sm text-gray-600">
      <div>© <?= date('Y') ?> ZoeFeeds. All rights reserved.</div>
      <div class="flex gap-5 flex-wrap justify-center">
        <a href="<?= APP_URL ?>/user/terms.php" class="hover:text-orange-400 transition-colors">Terms &amp; Conditions</a>
        <a href="<?= APP_URL ?>/user/terms.php#privacy" class="hover:text-orange-400 transition-colors">Privacy Policy</a>
        <a href="<?= APP_URL ?>/user/terms.php#rules" class="hover:text-orange-400 transition-colors">Draw Rules</a>
      </div>
    </div>
    <div class="text-center mt-6 text-xs text-gray-700">
      The ZoeFeeds Reward Draw is a customer appreciation initiative operating under applicable  laws. Not a gambling or betting service.
    </div>
  </div>
</footer>

<script src="<?= APP_URL ?>/assets/js/app.js"></script>
<script>
// Smooth scroll
document.querySelectorAll('a[href^="#"]').forEach(a => {
  a.addEventListener('click', e => {
    const el = document.getElementById(a.getAttribute('href').slice(1));
    if (el) { e.preventDefault(); el.scrollIntoView({ behavior:'smooth', block:'start' }); }
  });
});
// Fade-in on scroll
const obs = new IntersectionObserver(entries => {
  entries.forEach(e => { if (e.isIntersecting) { e.target.style.opacity='1'; e.target.style.transform='translateY(0)'; obs.unobserve(e.target); } });
}, { threshold: 0.08 });
document.querySelectorAll('.draw-card-land, .card, .winner-card').forEach(el => {
  el.style.cssText += ';opacity:0;transform:translateY(20px);transition:opacity .5s ease,transform .5s ease';
  obs.observe(el);
});

// Live stats polling — refreshes every 30 seconds from a lightweight endpoint
(function pollStats() {
  fetch('<?= APP_URL ?>/api/stats.php')
    .then(r => r.json())
    .then(data => {
      if (data.users   !== undefined) document.getElementById('stat-users').textContent   = Number(data.users).toLocaleString();
      if (data.winners !== undefined) document.getElementById('stat-winners').textContent = Number(data.winners).toLocaleString();
      if (data.draws   !== undefined) document.getElementById('stat-draws').textContent   = Number(data.draws).toLocaleString();
    })
    .catch(() => {}) // silently fail — DB values already rendered server-side
    .finally(() => setTimeout(pollStats, 30000));
})();
</script>
</body>
</html>
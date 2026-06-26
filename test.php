<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
startAppSession();

$db = getDB();

// Active draws for landing
$draws = $db->query("SELECT * FROM draws WHERE status='active' ORDER BY end_date ASC LIMIT 6")->fetchAll();

// Active slides
$slides = $db->query("SELECT * FROM slides WHERE status='active' ORDER BY sort_order ASC LIMIT 6")->fetchAll();
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ZoeFeeds — Loyalty Reward & Raffle Platform</title>
  <meta name="description" content="ZoeFeeds is Nigeria's #1 loyalty reward and raffle eligibility platform. Redeem codes, enter draws, win amazing prizes.">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
  <script src="<?= APP_URL ?>/assets/js/tailwind.js" defer></script>
  <!-- <script>
    tailwind.config = {
      theme: {
        extend: {
          fontFamily: { sans: ['Poppins', 'sans-serif'] },
          colors: {
            primary: '#f97316',
            brand: '#0a0f1a',
          }
        }
      }
    }
  </script> -->
  <style>
    * { font-family: 'Poppins', sans-serif !important; }
    .gradient-text { background: linear-gradient(135deg, #f97316, #fb923c, #fcd34d); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
    .glass { background: rgba(255,255,255,0.04); backdrop-filter: blur(12px); border: 1px solid rgba(255,255,255,0.08); }
    .nav-link { color: #94a3b8; font-size: 14px; font-weight: 500; transition: color .2s; }
    .nav-link:hover { color: white; }
    .hero-float { animation: heroFloat 6s ease-in-out infinite; }
    @keyframes heroFloat { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-16px)} }
    .glow-orange { box-shadow: 0 0 40px rgba(249,115,22,0.25); }
    .glow-blue { box-shadow: 0 0 40px rgba(0,212,255,0.15); }
    .step-line::after { content:''; position:absolute; top:50%; left:100%; width:100%; height:2px; background:linear-gradient(90deg,#f97316,transparent); transform:translateY(-50%); }
    .faq-content { max-height: 0; overflow: hidden; transition: max-height .4s ease; }
    .faq-item.open .faq-content { max-height: 400px; }
    .faq-item.open .faq-arrow { transform: rotate(180deg); }
    .faq-arrow { transition: transform .3s; }
    .ticker { display: flex; animation: ticker 20s linear infinite; }
    .ticker:hover { animation-play-state: paused; }
    @keyframes ticker { 0%{transform:translateX(0)} 100%{transform:translateX(-50%)} }
    .particle { position:absolute; border-radius:50%; opacity:.3; animation: particleFloat var(--dur) ease-in-out infinite; }
    @keyframes particleFloat { 0%,100%{transform:translateY(0) translateX(0)} 33%{transform:translateY(-30px) translateX(20px)} 66%{transform:translateY(10px) translateX(-15px)} }
  </style>
</head>
<body class="bg-[#0a0f1a] text-white font-sans overflow-x-hidden">

<!-- ===================== NAVBAR ===================== -->
<nav class="fixed top-0 w-full z-50 glass border-b border-white/5">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="flex items-center justify-between h-16">
      <a href="<?= APP_URL ?>" class="flex items-center gap-2">
        <div class="w-9 h-9 bg-orange-500 rounded-xl flex items-center justify-center font-black text-white text-lg">Z</div>
        <span class="font-bold text-xl tracking-tight">Zoe<span class="text-orange-500">Feeds</span></span>
      </a>
      <div class="hidden md:flex items-center gap-8">
        <a href="#draws" class="nav-link">Draws</a>
        <a href="#how-it-works" class="nav-link">How It Works</a>
        <a href="#rewards" class="nav-link">Rewards</a>
        <a href="#faq" class="nav-link">FAQ</a>
      </div>
      <div class="flex items-center gap-3">
        <a href="<?= APP_URL ?>/user/login.php" class="hidden sm:inline-flex btn btn-secondary btn-sm">Log In</a>
        <a href="<?= APP_URL ?>/user/register.php" class="btn btn-primary btn-sm">Get Started</a>
        <button onclick="document.getElementById('mobile-menu').classList.toggle('hidden')" class="md:hidden text-gray-400 ml-1">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
      </div>
    </div>
    <!-- Mobile menu -->
    <div id="mobile-menu" class="hidden md:hidden pb-4 space-y-1">
      <a href="#draws" class="block px-3 py-2 text-gray-400 hover:text-white text-sm">Draws</a>
      <a href="#how-it-works" class="block px-3 py-2 text-gray-400 hover:text-white text-sm">How It Works</a>
      <a href="#rewards" class="block px-3 py-2 text-gray-400 hover:text-white text-sm">Rewards</a>
      <a href="#faq" class="block px-3 py-2 text-gray-400 hover:text-white text-sm">FAQ</a>
      <a href="<?= APP_URL ?>/user/login.php" class="block px-3 py-2 text-orange-400 font-semibold text-sm">Log In</a>
    </div>
  </div>
</nav>

<!-- ===================== HERO ===================== -->
<section class="hero relative min-h-screen flex items-center pt-16">
  <div class="hero-bg"></div>
  <div class="hero-pattern"></div>

  <!-- Floating particles -->
  <div class="particle w-3 h-3 bg-orange-500" style="top:20%;left:10%;--dur:7s"></div>
  <div class="particle w-2 h-2 bg-cyan-400" style="top:60%;left:5%;--dur:9s"></div>
  <div class="particle w-4 h-4 bg-orange-400" style="top:30%;right:8%;--dur:6s"></div>
  <div class="particle w-2 h-2 bg-yellow-400" style="top:70%;right:15%;--dur:8s"></div>
  <div class="particle w-3 h-3 bg-cyan-300" style="top:85%;left:30%;--dur:10s"></div>

  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-20 relative z-10">
    <div class="grid lg:grid-cols-2 gap-16 items-center">
      <!-- Left -->
      <div class="fade-in">
        <div class="inline-flex items-center gap-2 bg-orange-500/10 border border-orange-500/20 rounded-full px-4 py-2 text-sm font-medium text-orange-400 mb-6">
          <span class="pulse-dot"></span>
          Nigeria's #1 Loyalty Reward Platform
        </div>
        <h1 class="text-5xl md:text-6xl font-black leading-[1.1] mb-6">
          Redeem Codes.<br>
          Enter Draws.<br>
          <span class="gradient-text">Win Big.</span>
        </h1>
        <p class="text-gray-400 text-lg leading-relaxed mb-8 max-w-lg">
          ZoeFeeds is an exclusive loyalty reward ecosystem. Collect 15-digit raffle codes, enter live draws, and win life-changing prizes — all inside one platform.
        </p>
        <div class="flex flex-wrap gap-4 mb-10">
          <a href="<?= APP_URL ?>/user/register.php" class="btn btn-primary px-8 py-4 text-base">
            🎟️ Start Winning Free
          </a>
          <a href="#how-it-works" class="btn btn-secondary px-8 py-4 text-base">
            How It Works →
          </a>
        </div>
        <!-- Trust badges -->
        <div class="flex flex-wrap items-center gap-6 text-sm text-gray-500">
          <div class="flex items-center gap-2"><span class="text-green-400">✓</span> Free to join</div>
          <div class="flex items-center gap-2"><span class="text-green-400">✓</span> Instant code redemption</div>
          <div class="flex items-center gap-2"><span class="text-green-400">✓</span> Transparent draws</div>
        </div>
      </div>

      <!-- Right — Hero Card -->
      <div class="relative hidden lg:block">
        <div class="hero-float">
          <!-- Main card -->
          <div class="card-glow p-6 glow-orange" style="border-radius:24px">
            <div class="flex items-center justify-between mb-5">
              <div>
                <div class="text-xs text-gray-400 font-medium">Eligibility Balance</div>
                <div class="text-4xl font-black text-orange-400 mt-1" style="font-family:'Courier New',monospace">47</div>
                <div class="text-xs text-gray-500">Active Codes</div>
              </div>
              <div class="w-14 h-14 bg-orange-500/20 rounded-2xl flex items-center justify-center text-3xl">🎯</div>
            </div>
            <!-- Fake code entry -->
            <div class="bg-black/30 rounded-xl p-4 mb-4">
              <div class="text-xs text-gray-500 mb-2">Latest Code</div>
              <div class="font-mono text-orange-400 font-bold text-lg tracking-widest">7 4 2 0 8 1 9 3 5 6 2 7 4 0 1</div>
              <div class="badge badge-success mt-2">● Active</div>
            </div>
            <!-- Mini draw card -->
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

          <!-- Floating badges -->
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

  <!-- Scroll indicator -->
  <div class="absolute bottom-8 left-1/2 -translate-x-1/2 flex flex-col items-center gap-2 text-gray-500 text-xs animate-bounce">
    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
  </div>
</section>

<!-- ===================== TICKER ===================== -->
<div class="bg-orange-500/10 border-y border-orange-500/20 py-3 overflow-hidden">
  <div class="ticker gap-16 whitespace-nowrap">
    <?php $items = ['🎉 Daily Patronage Rewards','🚌 Transport Draw LIVE','🏆 ₦1M Grand Prize Draw','✓ Code Redemption: Instant','🎯 Government Ticket Rewards','💳 Dashboard Loyalty Rewards','🎰 Vendor Campaign Prizes']; ?>
    <?php for ($i=0;$i<4;$i++): foreach($items as $item): ?>
      <span class="text-orange-300 font-medium text-sm mx-8"><?= $item ?></span>
    <?php endforeach; endfor; ?>
  </div>
</div>

<!-- ===================== ADVERT SLIDESHOW ===================== -->
<section class="py-16 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
  <div class="flex items-center justify-between mb-8">
    <h2 class="text-2xl font-bold">Featured Campaigns</h2>
    <div class="flex gap-2" id="slide-nav"></div>
  </div>
  <div class="slideshow bg-[#111827] rounded-2xl overflow-hidden" data-slideshow style="min-height:280px">
    <?php if ($slides): foreach ($slides as $s): ?>
    <div class="slide">
      <?php if ($s['image_path'] && file_exists(UPLOAD_PATH . $s['image_path'])): ?>
        <img src="<?= APP_URL ?>/uploads/<?= e($s['image_path']) ?>" alt="<?= e($s['title']) ?>" class="w-full h-72 object-cover">
      <?php else: ?>
        <div class="h-72 flex flex-col items-center justify-center" style="background:linear-gradient(135deg,#1a2235,#0d1929)">
          <div class="text-6xl mb-4">🎯</div>
          <div class="text-xl font-bold"><?= e($s['title'] ?? 'ZoeFeeds Campaign') ?></div>
        </div>
      <?php endif; ?>
    </div>
    <?php endforeach;
    else: // Default slides ?>
    <?php $defaultSlides = [
      ['🏆','Grand Prize Draw','Win up to ₦1,000,000 in our monthly grand draw','from-orange-900/60 to-red-900/40'],
      ['🎯','Daily Patronage Rewards','Active users get daily raffle entries automatically','from-blue-900/60 to-indigo-900/40'],
      ['🚌','Transport Reward Draw','Win free transport credits every week','from-green-900/60 to-emerald-900/40'],
    ];
    foreach ($defaultSlides as $s): ?>
    <div class="slide">
      <div class="h-72 flex flex-col items-center justify-center bg-gradient-to-r <?= $s[3] ?> relative overflow-hidden">
        <div class="hero-pattern absolute inset-0 opacity-5"></div>
        <div class="text-7xl mb-4 relative z-10"><?= $s[0] ?></div>
        <div class="text-2xl font-black mb-2 relative z-10"><?= $s[1] ?></div>
        <div class="text-gray-400 text-center max-w-sm relative z-10"><?= $s[2] ?></div>
        <a href="<?= APP_URL ?>/user/register.php" class="btn btn-primary mt-6 relative z-10">Join Now</a>
      </div>
    </div>
    <?php endforeach; endif; ?>
    <div class="slide-dots absolute bottom-4 left-0 right-0 flex justify-center gap-2"></div>
  </div>
</section>

<!-- ===================== LIVE DRAWS ===================== -->
<section id="draws" class="py-16 bg-[#0d1218]">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="text-center mb-12">
      <div class="inline-flex items-center gap-2 bg-red-500/10 border border-red-500/20 rounded-full px-4 py-2 text-sm font-medium text-red-400 mb-4">
        <span class="pulse-dot" style="background:#ef4444"></span> Live Draws
      </div>
      <h2 class="text-4xl font-black mb-4">Active Draw Campaigns</h2>
      <p class="text-gray-400 max-w-xl mx-auto">Enter active draws using your raffle codes. More codes = better chances. Multiple winners every draw.</p>
    </div>

    <?php if ($draws): ?>
    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
      <?php foreach ($draws as $draw): ?>
      <div class="draw-card fade-in" onclick="window.location='<?= APP_URL ?>/user/register.php'">
        <div class="draw-banner flex items-center justify-center text-5xl" style="background:linear-gradient(135deg,#1a2235,#0d1118)">
          <?php if ($draw['banner_image'] && file_exists(UPLOAD_PATH . $draw['banner_image'])): ?>
            <img src="<?= APP_URL ?>/uploads/<?= e($draw['banner_image']) ?>" class="w-full h-full object-cover" alt="">
          <?php else: ?>🎰<?php endif; ?>
        </div>
        <div class="p-5">
          <div class="flex items-center gap-2 mb-3">
            <span class="badge badge-success">● LIVE</span>
            <?php if ($draw['category']): ?><span class="badge badge-info"><?= e($draw['category']) ?></span><?php endif; ?>
          </div>
          <h3 class="font-bold text-base mb-2 line-clamp-2"><?= e($draw['title']) ?></h3>
          <?php if ($draw['prize_details']): ?>
            <p class="text-sm text-orange-400 font-semibold mb-3">🏆 <?= e(substr($draw['prize_details'],0,60)) ?>...</p>
          <?php endif; ?>
          <div class="flex items-center gap-3 mt-4" data-countdown="<?= e($draw['end_date']) ?>"></div>
          <a href="<?= APP_URL ?>/user/register.php" class="btn btn-primary w-full mt-4 text-sm">Enter Draw</a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
      <?php $sampleDraws = [
        ['🏆','Monthly Grand Prize Draw','Win up to ₦1,000,000','2026-06-30 23:59:59'],
        ['🎯','Daily Patronage Reward','Win daily prizes','2026-05-23 23:59:59'],
        ['🚌','Transport Credits Draw','Win ₦50,000 transport credits','2026-05-28 23:59:59'],
        ['📱','Dashboard Loyalty Draw','Active users only','2026-06-15 23:59:59'],
        ['🏪','Vendor Campaign Prize','Special vendor reward','2026-06-01 23:59:59'],
        ['🎫','Government Ticket Draw','Special category','2026-06-20 23:59:59'],
      ];
      foreach ($sampleDraws as $s): ?>
      <div class="draw-card">
        <div class="draw-banner flex items-center justify-center text-5xl" style="background:linear-gradient(135deg,#1a2235,#0d1118)"><?= $s[0] ?></div>
        <div class="p-5">
          <span class="badge badge-success mb-3">● LIVE</span>
          <h3 class="font-bold text-base mb-2"><?= $s[1] ?></h3>
          <p class="text-sm text-orange-400 font-semibold mb-3">🏆 <?= $s[2] ?></p>
          <div class="flex items-center gap-3 mt-4" data-countdown="<?= $s[3] ?>"></div>
          <a href="<?= APP_URL ?>/user/register.php" class="btn btn-primary w-full mt-4 text-sm">Enter Draw</a>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</section>

<!-- ===================== STATS ===================== -->
<section class="py-16 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
  <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
    <?php $stats = [['50K+','Registered Users'],['₦10M+','Prizes Awarded'],['200K+','Codes Redeemed'],['99.9%','Draw Transparency']]; ?>
    <?php foreach ($stats as $s): ?>
    <div class="card p-6 text-center">
      <div class="text-3xl font-black text-orange-400 mb-2"><?= $s[0] ?></div>
      <div class="text-sm text-gray-400"><?= $s[1] ?></div>
    </div>
    <?php endforeach; ?>
  </div>
</section>

<!-- ===================== HOW IT WORKS ===================== -->
<section id="how-it-works" class="py-20 bg-[#0d1218]">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="text-center mb-16">
      <h2 class="text-4xl font-black mb-4">How ZoeFeeds Works</h2>
      <p class="text-gray-400 max-w-xl mx-auto">Simple steps to start winning amazing rewards on our platform</p>
    </div>
    <div class="grid md:grid-cols-4 gap-8">
      <?php $steps = [
        ['1','🔐','Create Account','Sign up with your phone number in under 60 seconds. Free forever.'],
        ['2','🎟️','Get Your Code','Redeem your unique 15-digit raffle code given by a vendor or admin.'],
        ['3','🎯','Enter Draws','Choose an active draw and use your codes to enter. More codes = better odds.'],
        ['4','🏆','Win Prizes','Our transparent draw engine picks winners live. Prizes awarded instantly.'],
      ]; ?>
      <?php foreach ($steps as $i => $s): ?>
      <div class="text-center fade-in relative">
        <?php if ($i < 3): ?>
        <div class="hidden md:block absolute top-10 left-[60%] w-full h-0.5" style="background:linear-gradient(90deg,rgba(249,115,22,0.5),transparent)"></div>
        <?php endif; ?>
        <div class="w-20 h-20 bg-orange-500/10 border-2 border-orange-500/30 rounded-2xl flex items-center justify-center text-4xl mx-auto mb-5 relative z-10">
          <?= $s[2] ?>
        </div>
        <div class="inline-flex items-center justify-center w-7 h-7 bg-orange-500 rounded-full text-white text-xs font-black mb-3"><?= $s[0] ?></div>
        <h3 class="font-bold text-lg mb-2"><?= $s[2] ?></h3>
        <p class="text-gray-400 text-sm leading-relaxed"><?= $s[3] ?></p>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="text-center mt-12">
      <a href="<?= APP_URL ?>/user/register.php" class="btn btn-primary px-10 py-4 text-base">Start Now — It's Free</a>
    </div>
  </div>
</section>

<!-- ===================== REWARD CATEGORIES ===================== -->
<section id="rewards" class="py-20 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
  <div class="text-center mb-16">
    <h2 class="text-4xl font-black mb-4">Reward Categories</h2>
    <p class="text-gray-400 max-w-xl mx-auto">Multiple draw categories means more chances to win across different prize pools</p>
  </div>
  <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
    <?php $cats = [
      ['🏆','Daily Patronage Rewards','Daily draws for active platform users','from-orange-500/20 to-red-500/10','border-orange-500/30'],
      ['🏛️','Government Ticket Rewards','Special draws tied to government initiatives','from-blue-500/20 to-indigo-500/10','border-blue-500/30'],
      ['🚌','Transport Rewards','Win transport credits and free rides','from-green-500/20 to-emerald-500/10','border-green-500/30'],
      ['📊','Dashboard Loyalty Rewards','Rewards for consistent platform engagement','from-purple-500/20 to-violet-500/10','border-purple-500/30'],
      ['🏪','Vendor Campaign Rewards','Special draws created by verified vendors','from-yellow-500/20 to-amber-500/10','border-yellow-500/30'],
      ['🎰','Grand Prize Draws','Monthly mega draws with life-changing prizes','from-pink-500/20 to-rose-500/10','border-pink-500/30'],
    ]; ?>
    <?php foreach ($cats as $c): ?>
    <div class="card p-6 bg-gradient-to-br <?= $c[3] ?> border <?= $c[4] ?> hover:scale-105 transition-transform cursor-default">
      <div class="text-4xl mb-4"><?= $c[0] ?></div>
      <h3 class="font-bold text-base mb-2"><?= $c[1] ?></h3>
      <p class="text-gray-400 text-sm"><?= $c[2] ?></p>
    </div>
    <?php endforeach; ?>
  </div>
</section>

<!-- ===================== TESTIMONIALS ===================== -->
<section class="py-20 bg-[#0d1218]">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="text-center mb-12">
      <h2 class="text-4xl font-black mb-4">What Winners Say</h2>
      <p class="text-gray-400">Real stories from real ZoeFeeds winners</p>
    </div>
    <div class="grid md:grid-cols-3 gap-6">
      <?php $testimonials = [
        ['Emeka O.','Lagos','I redeemed 3 codes and won ₦200,000 in the monthly draw! ZoeFeeds is legit 💯','⭐⭐⭐⭐⭐'],
        ['Blessing A.','Abuja','Super easy platform. Got my codes from the vendor, entered the draw, and won transport credits the next week!','⭐⭐⭐⭐⭐'],
        ['Chioma N.','Port Harcourt','The live draw experience is amazing. You can see every digit revealed in real time. Very transparent!','⭐⭐⭐⭐⭐'],
      ]; ?>
      <?php foreach ($testimonials as $t): ?>
      <div class="card p-6">
        <div class="mb-4 text-yellow-400 text-lg"><?= $t[3] ?></div>
        <p class="text-gray-300 text-sm leading-relaxed mb-5">"<?= $t[0] === 'Emeka O.' ? 'I redeemed 3 codes and won ₦200,000 in the monthly draw! ZoeFeeds is legit 💯' : ($t[0]==='Blessing A.'?'Super easy platform. Got my codes from the vendor, entered the draw, and won transport credits the next week!':'The live draw experience is amazing. You can see every digit revealed in real time. Very transparent!') ?>"</p>
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 bg-orange-500/20 rounded-full flex items-center justify-center font-bold text-orange-400"><?= $t[0][0] ?></div>
          <div>
            <div class="font-semibold text-sm"><?= $t[0] ?></div>
            <div class="text-xs text-gray-500"><?= $t[1] ?></div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- ===================== FAQ ===================== -->
<section id="faq" class="py-20 max-w-4xl mx-auto px-4 sm:px-6 lg:px-8">
  <div class="text-center mb-12">
    <h2 class="text-4xl font-black mb-4">Frequently Asked Questions</h2>
    <p class="text-gray-400">Everything you need to know about ZoeFeeds</p>
  </div>
  <?php $faqs = [
    ['What is ZoeFeeds?','ZoeFeeds is an exclusive loyalty reward and raffle eligibility platform. Users collect 15-digit raffle codes, enter live draws, and win prizes — all within our platform.'],
    ['How do I get raffle codes?','Raffle codes are distributed by verified ZoeFeeds vendors. You can also receive them from other users via transfers, or get them directly from authorized channels.'],
    ['Is ZoeFeeds free to use?','Yes! Creating an account and redeeming codes is completely free. There are no hidden fees or charges.'],
    ['How are winners selected?','Winners are selected based on digit matching between your code and the winning code. The participant whose code matches the most digits in the same positions wins. Ties are broken by entry time, number of codes, and other fair criteria.'],
    ['Can I transfer my codes to another user?','Yes! You can transfer codes to any registered ZoeFeeds user using their phone number. A 4-digit transfer PIN is required for security.'],
    ['What happens to my codes after a draw?','After a draw is finalized, all codes used in that draw are consumed — they cannot be re-used. Make sure you want to enter before submitting.'],
    ['How are draws conducted?','Draws are conducted live on the platform. Admins reveal the winning code digit by digit in real-time, and matching codes are highlighted as each digit is revealed.'],
    ['Who are the vendors?','Vendors are verified ZoeFeeds internal operators who distribute and renew raffle codes for users. They operate exclusively within the ZoeFeeds platform.'],
  ]; ?>
  <div class="space-y-3">
    <?php foreach ($faqs as $i => $faq): ?>
    <div class="faq-item card p-0 overflow-hidden">
      <button onclick="toggleFaq(this)" class="w-full text-left p-5 flex items-center justify-between gap-4 hover:bg-white/2 transition-colors">
        <span class="font-semibold text-sm md:text-base"><?= $faq[0] ?></span>
        <svg class="faq-arrow w-5 h-5 text-orange-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
      </button>
      <div class="faq-content">
        <div class="px-5 pb-5 text-gray-400 text-sm leading-relaxed border-t border-white/5 pt-4"><?= $faq[1] ?></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</section>

<!-- ===================== CTA ===================== -->
<section class="py-20 relative overflow-hidden">
  <div class="absolute inset-0" style="background:radial-gradient(ellipse at center,rgba(249,115,22,0.15) 0%,transparent 70%)"></div>
  <div class="max-w-3xl mx-auto px-4 text-center relative z-10">
    <div class="text-6xl mb-6">🎯</div>
    <h2 class="text-4xl md:text-5xl font-black mb-4">Ready to Start Winning?</h2>
    <p class="text-gray-400 text-lg mb-8 max-w-xl mx-auto">Join thousands of Nigerians already winning on ZoeFeeds. Create your free account today.</p>
    <a href="<?= APP_URL ?>/user/register.php" class="btn btn-primary px-12 py-5 text-lg font-bold">
      🎟️ Create Free Account
    </a>
    <div class="text-sm text-gray-500 mt-4">No credit card required • Free forever • Instant setup</div>
  </div>
</section>

<!-- ===================== FOOTER ===================== -->
<footer class="bg-[#060b12] border-t border-white/5 py-12">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="grid md:grid-cols-4 gap-8 mb-10">
      <div>
        <div class="flex items-center gap-2 mb-4">
          <div class="w-8 h-8 bg-orange-500 rounded-lg flex items-center justify-center font-black text-white">Z</div>
          <span class="font-bold text-lg">Zoe<span class="text-orange-500">Feeds</span></span>
        </div>
        <p class="text-gray-500 text-sm leading-relaxed">Nigeria's #1 loyalty reward and raffle eligibility platform. Transparent. Fair. Exciting.</p>
      </div>
      <div>
        <h4 class="font-bold text-sm mb-4 text-gray-300 uppercase tracking-wider">Platform</h4>
        <ul class="space-y-2 text-sm">
          <li><a href="<?= APP_URL ?>/user/register.php" class="text-gray-500 hover:text-orange-400">Get Started</a></li>
          <li><a href="#draws" class="text-gray-500 hover:text-orange-400">Live Draws</a></li>
          <li><a href="#how-it-works" class="text-gray-500 hover:text-orange-400">How It Works</a></li>
          <li><a href="#rewards" class="text-gray-500 hover:text-orange-400">Rewards</a></li>
        </ul>
      </div>
      <div>
        <h4 class="font-bold text-sm mb-4 text-gray-300 uppercase tracking-wider">Account</h4>
        <ul class="space-y-2 text-sm">
          <li><a href="<?= APP_URL ?>/user/login.php" class="text-gray-500 hover:text-orange-400">Log In</a></li>
          <li><a href="<?= APP_URL ?>/user/register.php" class="text-gray-500 hover:text-orange-400">Register</a></li>
          <li><a href="<?= APP_URL ?>/vendor/login.php" class="text-gray-500 hover:text-orange-400">Vendor Login</a></li>
          <li><a href="<?= APP_URL ?>/admin/login.php" class="text-gray-500 hover:text-orange-400">Admin</a></li>
        </ul>
      </div>
      <div>
        <h4 class="font-bold text-sm mb-4 text-gray-300 uppercase tracking-wider">Contact</h4>
        <ul class="space-y-2 text-sm text-gray-500">
          <li>📧 support@zoefeeds.com</li>
          <li>📞 +234 800 ZOEFEEDS</li>
          <li>📍 Lagos, Nigeria</li>
        </ul>
      </div>
    </div>
    <div class="border-t border-white/5 pt-8 flex flex-col md:flex-row items-center justify-between gap-4">
      <div class="text-sm text-gray-600">© <?= date('Y') ?> ZoeFeeds. All rights reserved.</div>
      <div class="flex gap-6 text-sm text-gray-600">
        <a href="#" class="hover:text-orange-400">Privacy Policy</a>
        <a href="#" class="hover:text-orange-400">Terms of Service</a>
        <a href="#" class="hover:text-orange-400">Draw Rules</a>
      </div>
    </div>
  </div>
</footer>

<script src="<?= APP_URL ?>/assets/js/app.js"></script>
<script>
function toggleFaq(btn) {
  const item = btn.closest('.faq-item');
  const isOpen = item.classList.contains('open');
  document.querySelectorAll('.faq-item.open').forEach(i => i.classList.remove('open'));
  if (!isOpen) item.classList.add('open');
}

// Smooth scroll for anchor links
document.querySelectorAll('a[href^="#"]').forEach(a => {
  a.addEventListener('click', e => {
    const id = a.getAttribute('href').slice(1);
    const el = document.getElementById(id);
    if (el) { e.preventDefault(); el.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
  });
});

// Intersection observer for fade-in
const obs = new IntersectionObserver(entries => {
  entries.forEach(e => { if (e.isIntersecting) { e.target.classList.add('fade-in'); obs.unobserve(e.target); } });
}, { threshold: 0.1 });
document.querySelectorAll('.card, .draw-card').forEach(el => obs.observe(el));
</script>
</body>
</html>

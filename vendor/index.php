<?php
// vendor/index.php — Vendor landing page
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
startAppSession();
$db = getDB();

$totalVendors  = $db->query("SELECT COUNT(*) FROM vendors WHERE status='active'")->fetchColumn();
$totalCodes    = $db->query("SELECT COUNT(*) FROM codes")->fetchColumn();
$totalRedeemed = $db->query("SELECT COUNT(*) FROM codes WHERE status='redeemed'")->fetchColumn();
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Become a ZoeFeeds Vendor — Distribute Codes & Earn</title>
  <meta name="description" content="Join the ZoeFeeds vendor network. Distribute raffle codes to your customers, grow your business, and earn through our verified merchant program.">
  <script src="<?= APP_URL ?>/assets/js/tailwind.js"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
  <style>
    * { font-family: 'Poppins', sans-serif !important; }

    /* Purple-violet palette for vendor identity — distinct from orange customer theme */
    :root {
      --v-primary:   #7c3aed;
      --v-accent:    #a78bfa;
      --v-glow:      rgba(124, 58, 237, 0.18);
      --v-border:    rgba(124, 58, 237, 0.22);
    }

    .gt-vendor {
      background: linear-gradient(135deg, #7c3aed, #a78bfa, #c4b5fd);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }

    .glass { background: rgba(255,255,255,0.04); backdrop-filter: blur(14px); border: 1px solid rgba(255,255,255,0.08); }

    .vendor-card {
      background: var(--bg-card, rgba(255,255,255,0.03));
      border: 1px solid var(--v-border);
      border-radius: 20px;
      transition: all .3s;
    }
    .vendor-card:hover {
      transform: translateY(-5px);
      border-color: rgba(124,58,237,.5);
      box-shadow: 0 20px 48px rgba(124,58,237,.18);
    }

    .btn-vendor-primary {
      background: linear-gradient(135deg, #7c3aed, #6d28d9);
      color: #fff;
      border: none;
      border-radius: 14px;
      padding: .75rem 2rem;
      font-weight: 700;
      cursor: pointer;
      transition: all .2s;
      display: inline-flex;
      align-items: center;
      gap: .5rem;
    }
    .btn-vendor-primary:hover {
      background: linear-gradient(135deg, #6d28d9, #5b21b6);
      transform: translateY(-1px);
      box-shadow: 0 8px 24px rgba(124,58,237,.4);
    }

    .btn-vendor-secondary {
      background: transparent;
      color: #a78bfa;
      border: 1.5px solid var(--v-border);
      border-radius: 14px;
      padding: .75rem 2rem;
      font-weight: 600;
      cursor: pointer;
      transition: all .2s;
      display: inline-flex;
      align-items: center;
      gap: .5rem;
    }
    .btn-vendor-secondary:hover {
      background: rgba(124,58,237,.08);
      border-color: rgba(124,58,237,.5);
    }

    .glow-vendor { box-shadow: 0 0 48px var(--v-glow), 0 0 80px rgba(124,58,237,.06); }

    .hero-float { animation: hf 7s ease-in-out infinite; }
    @keyframes hf { 0%,100% { transform: translateY(0) } 50% { transform: translateY(-14px) } }

    .particle { position: absolute; border-radius: 50%; opacity: .2; animation: pf var(--d) ease-in-out infinite; }
    @keyframes pf { 0%,100% { transform: translate(0,0) } 40% { transform: translate(18px,-26px) } 70% { transform: translate(-12px,10px) } }

    .step-connector { position: absolute; top: 28px; left: calc(50% + 28px); width: calc(100% - 56px); height: 2px; background: linear-gradient(90deg, rgba(124,58,237,.5), transparent); }
    @media(max-width:768px) { .step-connector { display: none; } }

    .ticker-wrap { overflow: hidden; }
    .ticker { display: flex; width: max-content; animation: tk 28s linear infinite; }
    .ticker:hover { animation-play-state: paused; }
    @keyframes tk { from { transform: translateX(0) } to { transform: translateX(-50%) } }

    .faq-body { max-height: 0; overflow: hidden; transition: max-height .4s ease; }
    .faq-open .faq-body { max-height: 400px; }
    .faq-open .faq-arr { transform: rotate(180deg); }
    .faq-arr { transition: transform .3s; }

    .benefit-icon {
      width: 52px; height: 52px;
      background: rgba(124,58,237,.15);
      border: 1px solid var(--v-border);
      border-radius: 14px;
      display: flex; align-items: center; justify-content: center;
      font-size: 1.5rem;
      flex-shrink: 0;
    }

    .stat-pill {
      background: rgba(124,58,237,.1);
      border: 1px solid var(--v-border);
      border-radius: 100px;
      padding: .4rem 1.2rem;
    }

    section { scroll-margin-top: 72px; }
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
        <span class="hidden sm:inline-flex ml-1 text-xs bg-purple-500/20 border border-purple-500/30 text-purple-300 rounded-full px-2 py-0.5 font-semibold">Vendors</span>
      </a>

      <div class="hidden md:flex items-center gap-7">
        <a href="#benefits"    class="text-gray-400 hover:text-white text-sm font-medium transition-colors">Benefits</a>
        <a href="#how-it-works" class="text-gray-400 hover:text-white text-sm font-medium transition-colors">How It Works</a>
        <a href="#requirements" class="text-gray-400 hover:text-white text-sm font-medium transition-colors">Requirements</a>
        <a href="#faq"         class="text-gray-400 hover:text-white text-sm font-medium transition-colors">FAQ</a>
        <a href="<?= APP_URL ?>" class="text-gray-400 hover:text-white text-sm font-medium transition-colors">← Customer Site</a>
      </div>

      <div class="flex items-center gap-2">
        <a href="<?= APP_URL ?>/vendor/login.php"    class="hidden sm:inline-flex btn-vendor-secondary text-sm px-4 py-2">Log In</a>
        <a href="<?= APP_URL ?>/vendor/register.php" class="btn-vendor-primary text-sm px-4 py-2">Apply Now →</a>
        <button class="md:hidden p-2 text-gray-400 hover:text-white" onclick="document.getElementById('mob-menu').classList.toggle('hidden')">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
      </div>
    </div>

    <div id="mob-menu" class="hidden md:hidden pb-4 space-y-1 border-t border-white/5 pt-3">
      <?php foreach(['#benefits'=>'Benefits','#how-it-works'=>'How It Works','#requirements'=>'Requirements','#faq'=>'FAQ'] as $h=>$l): ?>
      <a href="<?= $h ?>" class="block px-3 py-2 text-gray-400 hover:text-white text-sm rounded-lg hover:bg-white/5" onclick="document.getElementById('mob-menu').classList.add('hidden')"><?= $l ?></a>
      <?php endforeach; ?>
      <a href="<?= APP_URL ?>/vendor/login.php"    class="block px-3 py-2 text-purple-400 font-semibold text-sm">Vendor Login</a>
      <a href="<?= APP_URL ?>/vendor/register.php" class="block px-3 py-2 text-white font-semibold text-sm bg-purple-600 rounded-xl text-center mt-1">Apply as Vendor →</a>
    </div>
  </div>
</nav>


<!-- ======================================================
     HERO
====================================================== -->
<section class="relative min-h-screen flex items-center pt-16 overflow-hidden">
  <!-- Background glow — purple for vendor -->
  <div class="absolute inset-0 pointer-events-none">
    <div class="absolute top-1/4 right-1/4 w-96 h-96 bg-purple-600/10 rounded-full blur-3xl"></div>
    <div class="absolute bottom-1/3 left-1/4 w-64 h-64 bg-violet-500/8 rounded-full blur-3xl"></div>
    <div class="absolute inset-0 opacity-[0.025]" style="background-image:repeating-linear-gradient(0deg,transparent,transparent 40px,rgba(255,255,255,.5) 40px,rgba(255,255,255,.5) 41px),repeating-linear-gradient(90deg,transparent,transparent 40px,rgba(255,255,255,.5) 40px,rgba(255,255,255,.5) 41px)"></div>
  </div>

  <!-- Particles -->
  <div class="particle w-3 h-3 bg-purple-500" style="top:18%;left:8%;--d:8s"></div>
  <div class="particle w-2 h-2 bg-violet-400" style="top:65%;left:4%;--d:10s"></div>
  <div class="particle w-4 h-4 bg-purple-400" style="top:28%;right:7%;--d:7s"></div>
  <div class="particle w-2 h-2 bg-pink-400"   style="top:72%;right:12%;--d:9s"></div>
  <div class="particle w-3 h-3 bg-violet-300" style="top:88%;left:28%;--d:11s"></div>

  <div class="max-w-7xl mx-auto px-4 sm:px-6 py-20 relative z-10 w-full">
    <div class="grid lg:grid-cols-2 gap-14 items-center">

      <!-- Left copy -->
      <div>
        <div class="inline-flex items-center gap-2 bg-purple-500/10 border border-purple-500/20 rounded-full px-4 py-2 text-sm font-semibold text-purple-300 mb-6">
          🏪 ZoeFeeds Vendor Network
        </div>
        <h1 class="text-5xl md:text-6xl lg:text-7xl font-black leading-[1.05] mb-6 tracking-tight">
          Distribute.<br>
          Reward.<br>
          <span class="gt-vendor">Grow Together.</span>
        </h1>
        <p class="text-gray-400 text-lg leading-relaxed mb-8 max-w-lg">
          Become an authorized ZoeFeeds vendor. Distribute raffle codes to your customers, drive loyalty, and be part of a <strong class="text-white">verified merchant network</strong> built on trust and transparency.
        </p>

        <!-- Live Stats -->
        <div class="flex flex-wrap gap-4 mb-9">
          <div class="stat-pill flex items-center gap-2 text-sm">
            <span class="text-2xl font-black text-purple-400"><?= number_format((int)$totalVendors) ?></span>
            <span class="text-gray-400">Active Vendors</span>
          </div>
          <div class="stat-pill flex items-center gap-2 text-sm">
            <span class="text-2xl font-black text-violet-400"><?= number_format((int)$totalCodes) ?></span>
            <span class="text-gray-400">Codes Distributed</span>
          </div>
          <div class="stat-pill flex items-center gap-2 text-sm">
            <span class="text-2xl font-black text-pink-400"><?= number_format((int)$totalRedeemed) ?></span>
            <span class="text-gray-400">Codes Redeemed</span>
          </div>
        </div>

        <div class="flex flex-wrap gap-3 mb-8">
          <a href="<?= APP_URL ?>/vendor/register.php" class="btn-vendor-primary px-8 py-4 text-base">🏪 Apply as Vendor</a>
          <a href="#how-it-works" class="btn-vendor-secondary px-8 py-4 text-base">How It Works →</a>
        </div>
        <div class="flex items-center gap-3 mb-6">
          <a href="<?= APP_URL ?>/vendor/login.php" class="btn-vendor-secondary px-6 py-3 text-sm">🔑 Vendor Login</a>
        </div>
        <div class="flex flex-wrap gap-4 text-xs text-gray-500">
          <span class="flex items-center gap-1.5"><span class="text-purple-400 text-base">✓</span> Admin-verified approval</span>
          <span class="flex items-center gap-1.5"><span class="text-purple-400 text-base">✓</span> API access included</span>
          <span class="flex items-center gap-1.5"><span class="text-purple-400 text-base">✓</span> Real-time dashboard</span>
          <span class="flex items-center gap-1.5"><span class="text-purple-400 text-base">✓</span> Dedicated support</span>
        </div>
      </div>

      <!-- Right: Floating vendor dashboard mockup -->
      <div class="relative hidden lg:block">
        <div class="hero-float">
          <div class="glow-vendor p-6" style="background:rgba(124,58,237,.06);border:1px solid var(--v-border);border-radius:24px">
            <div class="flex items-center justify-between mb-5">
              <div>
                <div class="text-xs text-gray-400 font-medium">Vendor Dashboard</div>
                <div class="text-xl font-bold text-white mt-0.5">Daniel Services</div>
                <div class="inline-flex items-center gap-1.5 text-xs text-green-400 font-semibold mt-1">
                  <span class="w-2 h-2 bg-green-400 rounded-full"></span> Active Vendor
                </div>
              </div>
              <div class="w-14 h-14 bg-purple-500/20 rounded-2xl flex items-center justify-center text-3xl">🏪</div>
            </div>

            <div class="grid grid-cols-2 gap-3 mb-4">
              <div class="bg-black/30 rounded-xl p-3 text-center">
                <div class="text-xs text-gray-500 mb-1">Code Balance</div>
                <div class="text-2xl font-black text-purple-400">124</div>
                <div class="text-xs text-gray-600">Available</div>
              </div>
              <div class="bg-black/30 rounded-xl p-3 text-center">
                <div class="text-xs text-gray-500 mb-1">Distributed</div>
                <div class="text-2xl font-black text-violet-400">891</div>
                <div class="text-xs text-gray-600">All time</div>
              </div>
            </div>

            <div class="bg-black/30 rounded-xl p-3 mb-3">
              <div class="text-xs text-gray-500 mb-2">API Public Key</div>
              <div class="font-mono text-purple-300 text-xs tracking-wider">zf_pub_37cb4bc6····e39d32</div>
            </div>

            <div class="bg-gradient-to-r from-purple-900/40 to-transparent rounded-xl p-3 border border-purple-500/20">
              <div class="text-xs text-purple-300 font-semibold mb-1">🎟️ Latest Batch Assigned</div>
              <div class="text-xs text-gray-400">BATCH-20260621T231042 · 9 codes</div>
              <div class="text-xs text-green-400 mt-1">✓ All distributed to customers</div>
            </div>
          </div>

          <div class="absolute -top-4 -right-4 glass rounded-2xl px-4 py-3">
            <div class="text-xs text-purple-300 font-semibold">✅ Approved!</div>
            <div class="text-xs text-gray-400">Application approved</div>
            <div class="text-sm font-bold text-white">within 24 hours</div>
          </div>
          <div class="absolute -bottom-4 -left-4 glass rounded-2xl px-4 py-3">
            <div class="text-xs text-green-400 font-semibold">🎉 Customer Redeemed</div>
            <div class="text-xs text-gray-300 mt-1">Code from your batch</div>
          </div>
        </div>
      </div>

    </div>
  </div>
</section>


<!-- ======================================================
     TICKER
====================================================== -->
<div class="border-y py-3 ticker-wrap" style="background:rgba(124,58,237,.06);border-color:rgba(124,58,237,.15)">
  <div class="ticker">
    <?php $items=['🏪 Verified Vendor Network','✅ Admin-Approved Merchants','🎟️ Real-Time Code Distribution','🔑 API Access Included','📊 Vendor Analytics Dashboard','🚀 Fast Application Review','💜 Transparent &amp; Compliant'];
    for($i=0;$i<4;$i++) foreach($items as $it): ?>
    <span class="text-purple-300/70 font-medium text-sm px-8 whitespace-nowrap"><?= $it ?></span>
    <span class="px-2 text-xl" style="color:rgba(124,58,237,.3)">·</span>
    <?php endforeach; ?>
  </div>
</div>


<!-- ======================================================
     BENEFITS
====================================================== -->
<section id="benefits" class="py-20 max-w-7xl mx-auto px-4 sm:px-6">
  <div class="text-center mb-14">
    <h2 class="text-4xl font-black mb-3">Why Become a ZoeFeeds Vendor?</h2>
    <p class="text-gray-400 max-w-xl mx-auto">Everything you need to distribute codes, grow customer loyalty, and participate in the official ZoeFeeds reward ecosystem.</p>
  </div>

  <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-5">
    <?php $benefits = [
      ['🎟️', 'Code Distribution Rights', 'Receive official ZoeFeeds raffle codes in bulk from the admin. Distribute them to your customers as part of purchases or loyalty rewards.'],
      ['📊', 'Vendor Dashboard', 'Real-time dashboard showing your code inventory, distribution history, customer redemption stats, and batch management.'],
      ['🔑', 'API Access', 'Integrate ZoeFeeds code distribution directly into your own platform or POS system using your unique API key pair.'],
      ['✅', 'Verified Merchant Badge', 'Stand out as an officially verified ZoeFeeds merchant, building trust with your customers and differentiating your business.'],
      ['📦', 'Batch Management', 'Receive codes in tracked batches. Easily manage, monitor, and audit every code assigned to your vendor account.'],
      ['⚡', 'Fast Approvals', 'Applications are reviewed promptly. Most vendors receive a decision within 24 hours of submitting a complete application.'],
      ['🛡️', 'Separated Credentials', 'Your vendor account is completely separate from customer accounts — dedicated login, dedicated dashboard, full privacy.'],
      ['📱', 'Mobile-Friendly Panel', 'Manage your vendor operations from any device — phone, tablet, or desktop. No app download needed.'],
      ['🤝', 'Direct Admin Support', 'As an approved vendor, you get direct support from the ZoeFeeds admin team for all operational needs.'],
    ]; foreach($benefits as $b): ?>
    <div class="vendor-card p-6 flex gap-4 items-start">
      <div class="benefit-icon"><?= $b[0] ?></div>
      <div>
        <h3 class="font-bold mb-1.5 text-sm"><?= $b[1] ?></h3>
        <p class="text-gray-400 text-xs leading-relaxed"><?= $b[2] ?></p>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</section>


<!-- ======================================================
     HOW IT WORKS
====================================================== -->
<section id="how-it-works" class="py-20 bg-[#0d1218]">
  <div class="max-w-7xl mx-auto px-4 sm:px-6">
    <div class="text-center mb-14">
      <h2 class="text-4xl font-black mb-3">How the Vendor Program Works</h2>
      <p class="text-gray-400 max-w-xl mx-auto">From application to active distribution in a few simple steps.</p>
    </div>

    <div class="grid md:grid-cols-4 gap-8 relative">
      <?php $steps = [
        ['1', '📝', 'Apply Online', 'Fill out the vendor application form with your business name and reason for joining. Takes under 2 minutes.'],
        ['2', '✅', 'Get Approved', 'Our admin team reviews your application and approves qualified merchants, usually within 24 hours. You\'ll be notified instantly.'],
        ['3', '🎟️', 'Receive Codes', 'Admin assigns code batches to your account. View and manage your inventory from your vendor dashboard.'],
        ['4', '🚀', 'Distribute & Grow', 'Share codes with customers through purchases or promotions. Every redeemed code credits back to your vendor record.'],
      ]; foreach($steps as $i=>$s): ?>
      <div class="text-center relative">
        <?php if($i<3): ?><div class="step-connector hidden md:block" style="background:linear-gradient(90deg,rgba(124,58,237,.5),transparent)"></div><?php endif; ?>
        <div class="w-16 h-16 flex items-center justify-center text-3xl mx-auto mb-4 relative z-10" style="background:rgba(124,58,237,.1);border:2px solid var(--v-border);border-radius:1rem"><?= $s[1] ?></div>
        <div class="inline-flex items-center justify-center w-6 h-6 rounded-full text-white text-xs font-black mb-2" style="background:#7c3aed"><?= $s[0] ?></div>
        <h3 class="font-bold text-base mb-2"><?= $s[2] ?></h3>
        <p class="text-gray-400 text-sm leading-relaxed"><?= $s[3] ?></p>
      </div>
      <?php endforeach; ?>
    </div>

    <div class="text-center mt-12">
      <a href="<?= APP_URL ?>/vendor/register.php" class="btn-vendor-primary px-10 py-4 text-base">Apply as a Vendor Now</a>
    </div>
  </div>
</section>


<!-- ======================================================
     REQUIREMENTS
====================================================== -->
<section id="requirements" class="py-20 max-w-7xl mx-auto px-4 sm:px-6">
  <div class="grid lg:grid-cols-2 gap-14 items-start">
    <div>
      <div class="inline-flex items-center gap-2 rounded-full px-4 py-1.5 text-sm font-semibold text-purple-300 mb-5" style="background:rgba(124,58,237,.1);border:1px solid var(--v-border)">
        📋 Eligibility Requirements
      </div>
      <h2 class="text-4xl font-black mb-5">Who Can Apply?</h2>
      <p class="text-gray-400 leading-relaxed mb-6">
        ZoeFeeds vendors are trusted business partners. We accept applications from individuals and businesses who genuinely want to distribute codes as part of customer appreciation — not to resell them.
      </p>

      <div class="space-y-4">
        <?php $reqs = [
          ['✅', 'Must have an existing ZoeFeeds customer account', 'Apply from within your dashboard after logging into your customer account.'],
          ['✅', 'Must be 18 years or older', 'The same age requirement that applies to all ZoeFeeds participants.'],
          ['✅', 'Must have a legitimate business or distribution reason', 'Explain clearly why you want to distribute codes and how you intend to use them.'],
          ['✅', 'Must agree never to sell codes', 'ZoeFeeds codes are always free. Selling them to customers is prohibited and grounds for immediate suspension.'],
          ['✅', 'Must use a unique phone number and email', 'Your vendor account credentials must be separate from any other user account on the platform.'],
        ]; foreach($reqs as $r): ?>
        <div class="flex gap-3 items-start">
          <span class="text-green-400 text-lg flex-shrink-0 mt-0.5"><?= $r[0] ?></span>
          <div>
            <div class="font-semibold text-sm text-white"><?= $r[1] ?></div>
            <div class="text-xs text-gray-500 mt-0.5"><?= $r[2] ?></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div>
      <div class="inline-flex items-center gap-2 rounded-full px-4 py-1.5 text-sm font-semibold text-red-300 mb-5" style="background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.2)">
        ⚠️ Vendor Obligations
      </div>
      <h2 class="text-4xl font-black mb-5">Your Responsibilities</h2>
      <p class="text-gray-400 leading-relaxed mb-6">
        Approved vendors carry the ZoeFeeds brand in their communities. These obligations are non-negotiable and violations result in immediate suspension.
      </p>

      <div class="space-y-3">
        <?php $obligations = [
          ['🚫', 'Never sell raffle codes', 'Codes must always be gifted or distributed free as part of a purchase or promotion.'],
          ['🚫', 'Never share your API secret key', 'Your vendor secret key is private. Sharing it compromises your account security.'],
          ['🚫', 'Never assign codes to fictitious customers', 'All code distributions must go to real, verified customer accounts.'],
          ['🚫', 'Never misrepresent ZoeFeeds to customers', 'Do not make prize promises or claims that go beyond what ZoeFeeds officially offers.'],
          ['✅', 'Promptly report suspicious activity', 'If you notice fraudulent code usage or suspicious redemptions, contact admin immediately.'],
        ]; foreach($obligations as $o): ?>
        <div class="flex gap-3 items-start p-3 rounded-xl" style="background:rgba(239,68,68,.04);border:1px solid rgba(239,68,68,.1)">
          <span class="text-lg flex-shrink-0"><?= $o[0] ?></span>
          <div>
            <div class="font-semibold text-sm text-white"><?= $o[1] ?></div>
            <div class="text-xs text-gray-500 mt-0.5"><?= $o[2] ?></div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</section>


<!-- ======================================================
     TESTIMONIALS / VENDOR STORIES
====================================================== -->
<section class="py-20 bg-[#0d1218]">
  <div class="max-w-7xl mx-auto px-4 sm:px-6">
    <div class="text-center mb-12">
      <h2 class="text-4xl font-black mb-3">Vendors Love ZoeFeeds</h2>
      <p class="text-gray-400 text-sm">Real feedback from our active vendor network</p>
    </div>
    <div class="grid md:grid-cols-3 gap-5">
      <?php $testimonials = [
        ['E. U.', 'SPOTWEB COM', 'The vendor dashboard is clean and easy to use. I received my first batch of codes within hours of being approved. Great experience.'],
        ['D. O.', 'Daniel Services', 'Applying was super simple — just a form and a short wait. My customers love getting free raffle codes with their orders. Great loyalty tool.'],
        ['A. M.', 'Merchant, Enugu', 'The API key feature is brilliant. We integrated it with our sales system so codes are distributed automatically. ZoeFeeds is built for real businesses.'],
      ]; foreach($testimonials as $t): ?>
      <div class="vendor-card p-6">
        <div class="text-yellow-400 mb-3">⭐⭐⭐⭐⭐</div>
        <p class="text-gray-300 text-sm leading-relaxed mb-5">"<?= $t[2] ?>"</p>
        <div class="flex items-center gap-3">
          <div class="w-9 h-9 rounded-full flex items-center justify-center font-bold text-purple-400" style="background:rgba(124,58,237,.2)"><?= $t[0][0] ?></div>
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


<!-- ======================================================
     FAQ
====================================================== -->
<section id="faq" class="py-20 max-w-3xl mx-auto px-4 sm:px-6">
  <div class="text-center mb-12">
    <h2 class="text-4xl font-black mb-3">Vendor FAQ</h2>
    <p class="text-gray-400 text-sm">Common questions from prospective and active vendors</p>
  </div>

  <?php $faqs = [
    ['Is there a fee to become a ZoeFeeds vendor?', 'No. Joining the ZoeFeeds vendor network is completely free. There are no registration fees, monthly fees, or hidden charges.'],
    ['Do I need a registered business to apply?', 'Not necessarily. Individuals with a clear and legitimate distribution plan can apply. However, having a registered business name strengthens your application.'],
    ['How long does approval take?', 'Most applications are reviewed and decided within 24 hours. You will receive a notification on your customer account dashboard and via email/SMS when your application is reviewed.'],
    ['Can I apply to be a vendor if I already have a customer account?', 'Yes! In fact, you must have a customer account first. Vendor applications are made from within your existing ZoeFeeds customer dashboard under the Vendor Panel section.'],
    ['What is the difference between a vendor account and a customer account?', 'Customer accounts are for individuals who receive, hold, and enter codes into draws. Vendor accounts are for merchants who distribute codes to customers. They are now completely separate systems with separate credentials and dashboards.'],
    ['How do I receive codes as a vendor?', 'Once approved, codes are assigned to your vendor account by the ZoeFeeds admin team in batches. You will see them in your vendor dashboard and can track their status.'],
    ['Can I sell codes to my customers?', 'Absolutely not. This is the most important rule. ZoeFeeds codes must always be free to end-users. Selling them — at any price — is a violation that results in immediate and permanent suspension of your vendor account.'],
    ['What happens if my application is rejected?', 'You will be notified of the rejection and may be given the reason. You may reapply after addressing the issues raised. Contact admin support for clarification.'],
    ['Can I have both a customer account and a vendor account?', 'Yes, but they must use different phone numbers and different email addresses. Customer and vendor credentials cannot be shared across account types.'],
    ['How do I access the vendor API?', 'Once approved, visit your vendor dashboard and navigate to the API Settings section to generate your public and secret API keys. Documentation is provided in the panel.'],
  ]; foreach($faqs as $faq): ?>
  <div class="faq-item mb-3 overflow-hidden rounded-xl" style="background:rgba(124,58,237,.04);border:1px solid var(--v-border)">
    <button onclick="this.closest('.faq-item').classList.toggle('faq-open')" class="w-full text-left px-5 py-4 flex items-center justify-between gap-3 hover:bg-white/2 transition-colors">
      <span class="font-semibold text-sm md:text-base"><?= $faq[0] ?></span>
      <svg class="faq-arr w-5 h-5 text-purple-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
    </button>
    <div class="faq-body">
      <div class="px-5 pb-5 text-sm text-gray-400 leading-relaxed border-t pt-4" style="border-color:rgba(124,58,237,.15)"><?= $faq[1] ?></div>
    </div>
  </div>
  <?php endforeach; ?>
</section>


<!-- ======================================================
     CTA
====================================================== -->
<section class="py-24 relative overflow-hidden">
  <div class="absolute inset-0 pointer-events-none" style="background:radial-gradient(ellipse at center,rgba(124,58,237,0.12) 0%,transparent 70%)"></div>
  <div class="max-w-3xl mx-auto px-4 text-center relative z-10">
    <div class="text-6xl mb-5">🏪</div>
    <h2 class="text-4xl md:text-5xl font-black mb-4 tracking-tight">Ready to Join the Vendor Network?</h2>
    <p class="text-gray-400 text-lg mb-8 max-w-xl mx-auto">Apply today and start distributing ZoeFeeds raffle codes to your customers. Build loyalty, grow your business, and be part of a transparent reward ecosystem.</p>
    <div class="flex flex-wrap gap-4 justify-center">
      <a href="<?= APP_URL ?>/vendor/register.php" class="btn-vendor-primary px-12 py-5 text-lg">🏪 Apply as Vendor</a>
      <a href="<?= APP_URL ?>/vendor/login.php"    class="btn-vendor-secondary px-10 py-5 text-lg">🔑 Vendor Login</a>
    </div>
    <div class="text-xs text-gray-600 mt-4">Free to apply · Admin-reviewed · Separate from customer account</div>
    <div class="mt-6">
      <a href="<?= APP_URL ?>" class="text-gray-500 hover:text-gray-300 text-sm transition-colors">← Back to Customer Site</a>
    </div>
  </div>
</section>


<!-- ======================================================
     FOOTER
====================================================== -->
<footer class="bg-[#060b12] border-t border-white/5 py-12">
  <div class="max-w-7xl mx-auto px-4 sm:px-6">
    <div class="grid md:grid-cols-3 gap-8 mb-8">
      <div>
        <div class="flex items-center gap-2 mb-4">
          <div class="w-8 h-8 bg-orange-500 rounded-lg flex items-center justify-center font-black text-white text-sm">Z</div>
          <span class="font-bold">Zoe<span class="text-orange-500">Feeds</span></span>
          <span class="text-xs text-purple-300 bg-purple-500/20 border border-purple-500/30 rounded-full px-2 py-0.5 font-semibold">Vendors</span>
        </div>
        <p class="text-gray-500 text-sm leading-relaxed">The official ZoeFeeds vendor portal. Authorized code distribution for verified merchants.</p>
      </div>
      <div>
        <h4 class="font-bold text-sm mb-4 text-gray-300 uppercase tracking-wider">Vendor Links</h4>
        <ul class="space-y-2 text-sm">
          <li><a href="<?= APP_URL ?>/vendor/register.php" class="text-gray-500 hover:text-purple-400 transition-colors">Apply as Vendor</a></li>
          <li><a href="<?= APP_URL ?>/vendor/login.php"    class="text-gray-500 hover:text-purple-400 transition-colors">Vendor Login</a></li>
          <li><a href="#benefits"     class="text-gray-500 hover:text-purple-400 transition-colors">Benefits</a></li>
          <li><a href="#how-it-works" class="text-gray-500 hover:text-purple-400 transition-colors">How It Works</a></li>
          <li><a href="#faq"          class="text-gray-500 hover:text-purple-400 transition-colors">FAQ</a></li>
        </ul>
      </div>
      <div>
        <h4 class="font-bold text-sm mb-4 text-gray-300 uppercase tracking-wider">Customer Site</h4>
        <ul class="space-y-2 text-sm">
          <li><a href="<?= APP_URL ?>"                      class="text-gray-500 hover:text-orange-400 transition-colors">ZoeFeeds Home</a></li>
          <li><a href="<?= APP_URL ?>/user/register.php"    class="text-gray-500 hover:text-orange-400 transition-colors">Customer Register</a></li>
          <li><a href="<?= APP_URL ?>/user/login.php"       class="text-gray-500 hover:text-orange-400 transition-colors">Customer Login</a></li>
          <li><a href="<?= APP_URL ?>/user/terms.php"       class="text-gray-500 hover:text-orange-400 transition-colors">Terms &amp; Conditions</a></li>
        </ul>
      </div>
    </div>
    <div class="border-t border-white/5 pt-6 flex flex-col md:flex-row items-center justify-between gap-3 text-sm text-gray-600">
      <div>© <?= date('Y') ?> ZoeFeeds. Vendor Portal. All rights reserved.</div>
      <div class="text-xs">📧 support@zoefeeds.com · 🌐 www.zoefeeds.com</div>
    </div>
  </div>
</footer>

<script src="<?= APP_URL ?>/assets/js/app.js"></script>
<script>
document.querySelectorAll('a[href^="#"]').forEach(a => {
  a.addEventListener('click', e => {
    const el = document.getElementById(a.getAttribute('href').slice(1));
    if (el) { e.preventDefault(); el.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
  });
});
const obs = new IntersectionObserver(entries => {
  entries.forEach(e => { if (e.isIntersecting) { e.target.style.opacity='1'; e.target.style.transform='translateY(0)'; obs.unobserve(e.target); } });
}, { threshold: 0.08 });
document.querySelectorAll('.vendor-card').forEach(el => {
  el.style.cssText += ';opacity:0;transform:translateY(20px);transition:opacity .5s ease,transform .5s ease';
  obs.observe(el);
});
</script>
</body>
</html>

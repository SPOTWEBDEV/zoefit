<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
startAppSession();
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Terms & Conditions — ZoeFeeds</title>
  <meta name="description" content="Official ZoeFeeds Reward Draw Terms and Conditions. Read the full rules, eligibility, draw process, and prize information.">
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&family=Playfair+Display:wght@700;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
  <style>
    :root {
      --orange: #f97316;
      --orange-dim: rgba(249,115,22,0.12);
      --orange-border: rgba(249,115,22,0.25);
      --bg: #0a0f1a;
      --bg-card: #0f1623;
      --bg-raised: #131c2b;
      --border: rgba(255,255,255,0.07);
      --text-muted: #6b7280;
      --text-dim: #9ca3af;
    }
    * { font-family: 'Poppins', sans-serif !important; }
    .display { font-family: 'Playfair Display', serif !important; }

    body { background: var(--bg); color: #e5e7eb; }

    /* ── hero ── */
    .tc-hero {
      background:
        radial-gradient(ellipse 80% 50% at 50% -10%, rgba(249,115,22,0.18) 0%, transparent 70%),
        repeating-linear-gradient(0deg,transparent,transparent 48px,rgba(255,255,255,0.025) 48px,rgba(255,255,255,0.025) 49px),
        repeating-linear-gradient(90deg,transparent,transparent 48px,rgba(255,255,255,0.025) 48px,rgba(255,255,255,0.025) 49px);
    }

    /* ── sticky sidebar nav ── */
    .toc-link {
      display: flex; align-items: center; gap: 10px;
      padding: 8px 14px; border-radius: 10px;
      font-size: 0.78rem; font-weight: 500; color: var(--text-muted);
      border-left: 2px solid transparent;
      transition: all .2s; cursor: pointer; text-decoration: none;
    }
    .toc-link:hover { color: #e5e7eb; background: rgba(255,255,255,0.04); border-left-color: var(--orange); }
    .toc-link.active { color: var(--orange); background: var(--orange-dim); border-left-color: var(--orange); }
    .toc-num { width: 20px; height: 20px; border-radius: 6px; background: rgba(255,255,255,0.06);
      display: inline-flex; align-items: center; justify-content: center;
      font-size: 0.65rem; font-weight: 700; flex-shrink: 0; }
    .toc-link.active .toc-num { background: var(--orange); color: #fff; }

    /* ── section cards ── */
    .tc-section {
      scroll-margin-top: 88px;
      background: var(--bg-card);
      border: 1px solid var(--border);
      border-radius: 20px;
      padding: 36px 40px;
      margin-bottom: 20px;
      transition: border-color .3s;
    }
    .tc-section:hover { border-color: var(--orange-border); }
    @media(max-width:640px){ .tc-section { padding: 24px 20px; } }

    .tc-section-header {
      display: flex; align-items: flex-start; gap: 16px; margin-bottom: 24px;
      padding-bottom: 20px; border-bottom: 1px solid var(--border);
    }
    .tc-icon {
      width: 48px; height: 48px; border-radius: 14px;
      background: var(--orange-dim); border: 1px solid var(--orange-border);
      display: flex; align-items: center; justify-content: center;
      font-size: 1.4rem; flex-shrink: 0;
    }
    .tc-section-num {
      font-size: 0.7rem; font-weight: 700; color: var(--orange);
      text-transform: uppercase; letter-spacing: .1em; margin-bottom: 4px;
    }
    .tc-section-title {
      font-family: 'Playfair Display', serif !important;
      font-size: 1.3rem; font-weight: 700; color: #fff; line-height: 1.25;
    }

    /* ── content elements ── */
    .tc-body p { color: var(--text-dim); line-height: 1.8; font-size: 0.92rem; margin-bottom: 14px; }
    .tc-body p:last-child { margin-bottom: 0; }
    .tc-body strong { color: #e5e7eb; font-weight: 600; }

    .tc-list { list-style: none; padding: 0; margin: 14px 0; }
    .tc-list li {
      display: flex; align-items: flex-start; gap: 10px;
      color: var(--text-dim); font-size: 0.88rem; line-height: 1.7;
      padding: 7px 0; border-bottom: 1px solid rgba(255,255,255,0.04);
    }
    .tc-list li:last-child { border-bottom: none; }
    .tc-list li::before {
      content: ''; width: 6px; height: 6px; border-radius: 50%;
      background: var(--orange); margin-top: 8px; flex-shrink: 0;
    }

    .tc-check-list { list-style: none; padding: 0; margin: 14px 0; }
    .tc-check-list li {
      display: flex; align-items: flex-start; gap: 10px;
      color: #ef4444; font-size: 0.88rem; line-height: 1.7; padding: 6px 0;
    }
    .tc-check-list li::before { content: '✗'; font-weight: 700; flex-shrink: 0; margin-top: 1px; }

    .tc-eligible-list li { color: #4ade80 !important; }
    .tc-eligible-list li::before { content: '✓'; font-weight: 700; flex-shrink: 0; margin-top: 1px; }

    .sub-heading {
      font-size: 0.78rem; font-weight: 700; color: var(--orange);
      text-transform: uppercase; letter-spacing: .08em;
      margin: 20px 0 8px;
    }

    .highlight-box {
      background: rgba(249,115,22,0.07);
      border: 1px solid var(--orange-border);
      border-left: 3px solid var(--orange);
      border-radius: 12px; padding: 16px 20px; margin: 16px 0;
    }
    .highlight-box p { color: #d1d5db; font-size: 0.88rem; margin: 0; }
    .highlight-box strong { color: var(--orange); }

    .warning-box {
      background: rgba(239,68,68,0.08);
      border: 1px solid rgba(239,68,68,0.3);
      border-left: 3px solid #ef4444;
      border-radius: 12px; padding: 16px 20px; margin: 16px 0;
    }
    .warning-box p { color: #fca5a5; font-size: 0.88rem; margin: 0; }

    .info-grid {
      display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin: 16px 0;
    }
    @media(max-width:500px){ .info-grid { grid-template-columns: 1fr; } }
    .info-cell {
      background: rgba(255,255,255,0.03); border: 1px solid var(--border);
      border-radius: 10px; padding: 14px 16px;
    }
    .info-cell-label { font-size: 0.7rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: .07em; margin-bottom: 4px; }
    .info-cell-value { font-size: 0.9rem; color: #e5e7eb; font-weight: 500; }

    /* ── warning banner ── */
    .big-warning {
      background: linear-gradient(135deg, rgba(239,68,68,0.12), rgba(239,68,68,0.04));
      border: 1px solid rgba(239,68,68,0.3);
      border-radius: 20px; padding: 32px 36px;
    }

    /* ── aim banner ── */
    .aim-banner {
      background: linear-gradient(135deg, rgba(249,115,22,0.12), rgba(234,179,8,0.06));
      border: 1px solid rgba(249,115,22,0.25);
      border-radius: 20px; padding: 32px 36px;
    }

    /* ── progress bar ── */
    #read-progress { position: fixed; top: 0; left: 0; height: 3px; background: linear-gradient(90deg,#f97316,#fbbf24); width: 0%; z-index: 100; transition: width .1s; }

    /* ── glass nav ── */
    .glass { background: rgba(10,15,26,0.85); backdrop-filter: blur(16px); border-bottom: 1px solid rgba(255,255,255,0.06); }

    /* ── back-to-top ── */
    #btt { position: fixed; bottom: 28px; right: 28px; width: 44px; height: 44px; background: var(--orange); border-radius: 12px; display: flex; align-items: center; justify-content: center; cursor: pointer; opacity: 0; transform: translateY(10px); transition: all .3s; z-index: 50; box-shadow: 0 8px 24px rgba(249,115,22,0.4); }
    #btt.show { opacity: 1; transform: translateY(0); }
    #btt:hover { transform: translateY(-3px) scale(1.05); }

    section { scroll-margin-top: 80px; }

    /* ── last-updated badge ── */
    .last-updated { display: inline-flex; align-items: center; gap: 8px; background: rgba(74,222,128,0.1); border: 1px solid rgba(74,222,128,0.25); border-radius: 100px; padding: 6px 16px; font-size: 0.75rem; color: #4ade80; font-weight: 600; }
  </style>
</head>
<body>

<!-- Read progress bar -->
<div id="read-progress"></div>

<!-- ======================================================
     NAVBAR
====================================================== -->
<nav class="fixed top-0 w-full z-50 glass">
  <div class="max-w-7xl mx-auto px-4 sm:px-6">
    <div class="flex items-center justify-between h-16">
      <a href="<?= APP_URL ?>" class="flex items-center gap-2">
        <div class="w-9 h-9 bg-orange-500 rounded-xl flex items-center justify-center font-black text-white text-xl">Z</div>
        <span class="font-bold text-xl text-white">Zoe<span class="text-orange-500">Feeds</span></span>
      </a>
      <div class="flex items-center gap-2">
        <a href="<?= APP_URL ?>" class="text-sm text-gray-400 hover:text-white transition-colors hidden sm:block">← Back to Home</a>
        <a href="<?= APP_URL ?>/user/login.php" class="btn btn-secondary btn-sm hidden sm:inline-flex">Log In</a>
        <a href="<?= APP_URL ?>/user/register.php" class="btn btn-primary btn-sm">Get Started</a>
      </div>
    </div>
  </div>
</nav>


<!-- ======================================================
     HERO
====================================================== -->
<section class="tc-hero pt-28 pb-16 px-4">
  <div class="max-w-4xl mx-auto text-center">
    <div class="last-updated mb-6">
      <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/></svg>
      Current & In Force
    </div>
    <h1 class="display text-5xl sm:text-6xl font-black text-white mb-5 leading-tight">
      Terms &amp;<br><span style="color:var(--orange)">Conditions</span>
    </h1>
    <p class="text-gray-400 text-lg max-w-2xl mx-auto leading-relaxed mb-8">
      Official rules governing the <strong class="text-white">ZoeFeeds Reward Draw</strong> — a customer appreciation and promotional campaign. Please read carefully before participating.
    </p>
    <!-- Quick stats -->
    <div class="inline-flex flex-wrap justify-center gap-6 bg-white/[0.03] border border-white/[0.07] rounded-2xl px-8 py-5">
      <div class="text-center">
        <div class="text-xl font-black text-orange-400">20</div>
        <div class="text-xs text-gray-500">Sections</div>
      </div>
      <div class="w-px bg-white/10 self-stretch hidden sm:block"></div>
      <div class="text-center">
        <div class="text-xl font-black text-green-400">18+</div>
        <div class="text-xs text-gray-500">Age Requirement</div>
      </div>
      <div class="w-px bg-white/10 self-stretch hidden sm:block"></div>
      <div class="text-center">
        <div class="text-xl font-black text-cyan-400">🇳🇬</div>
        <div class="text-xs text-gray-500">Nigerian Law</div>
      </div>
      <div class="w-px bg-white/10 self-stretch hidden sm:block"></div>
      <div class="text-center">
        <div class="text-xl font-black text-yellow-400">Free</div>
        <div class="text-xs text-gray-500">Entry Always</div>
      </div>
    </div>
  </div>
</section>


<!-- ======================================================
     MAIN LAYOUT — Sidebar + Content
====================================================== -->
<div class="max-w-7xl mx-auto px-4 sm:px-6 pb-24">
  <div class="flex gap-8 items-start">

    <!-- ── Sticky Sidebar TOC ── -->
    <aside class="hidden xl:block w-64 flex-shrink-0 sticky top-24">
      <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:16px;padding:20px;">
        <div class="text-xs font-bold text-gray-500 uppercase tracking-widest mb-3 px-2">Contents</div>
        <nav id="toc">
          <?php $sections=[
            ['introduction','📋','Introduction'],
            ['promoter','🏢','Promoter Info'],
            ['purpose','🎯','Purpose'],
            ['eligibility','👤','Eligibility'],
            ['participation','🎟️','How to Participate'],
            ['codes','🔑','Raffle Codes'],
            ['entry','📝','Draw Entry'],
            ['process','⚙️','Draw Process'],
            ['prizes','🏆','Prizes'],
            ['winner-selection','🎲','Winner Selection'],
            ['notification','📲','Notification'],
            ['claiming','✅','Claiming Prizes'],
            ['unclaimed','🔄','Unclaimed Prizes'],
            ['fraud','🚫','Fraud & Disqualification'],
            ['liability','⚠️','Liability'],
            ['privacy','🔒','Privacy'],
            ['publicity','📢','Publicity'],
            ['modification','✏️','Modification'],
            ['compliance','📜','Compliance'],
            ['governing-law','⚖️','Governing Law'],
          ];
          foreach($sections as $idx=>[$id,$icon,$label]): ?>
          <a href="#<?= $id ?>" class="toc-link" data-target="<?= $id ?>">
            <span class="toc-num"><?= $idx+1 ?></span>
            <span><?= $label ?></span>
          </a>
          <?php endforeach; ?>
        </nav>
      </div>
      <!-- Print button -->
      <button onclick="window.print()" class="mt-3 w-full text-sm text-gray-500 hover:text-orange-400 flex items-center justify-center gap-2 py-2 border border-white/5 rounded-xl hover:border-orange-500/30 transition-all">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/></svg>
        Print / Save PDF
      </button>
    </aside>

    <!-- ── Main Content ── -->
    <main class="flex-1 min-w-0 pt-4">

      <!-- Acceptance notice -->
      <div class="highlight-box mb-6">
        <p><strong>By participating in the ZoeFeeds Reward Draw, you confirm that you have read, understood, and agree to be bound by these Terms and Conditions.</strong></p>
      </div>

      <!-- §1 Introduction -->
      <div class="tc-section" id="introduction">
        <div class="tc-section-header">
          <div class="tc-icon">📋</div>
          <div><div class="tc-section-num">Section 01</div><div class="tc-section-title">Introduction</div></div>
        </div>
        <div class="tc-body">
          <p>The <strong>ZoeFeeds Reward Draw</strong> ("Promotion") is a customer appreciation and promotional campaign organized by ZoeFeeds ("the Promoter"). The Promotion is designed to reward eligible customers through a transparent and fair draw process.</p>
          <p>Participation in the Promotion constitutes acceptance of these Terms and Conditions.</p>
        </div>
      </div>

      <!-- §2 Promoter -->
      <div class="tc-section" id="promoter">
        <div class="tc-section-header">
          <div class="tc-icon">🏢</div>
          <div><div class="tc-section-num">Section 02</div><div class="tc-section-title">Promoter Information</div></div>
        </div>
        <div class="tc-body">
          <p>The Promotion is organized and administered by ZoeFeeds.</p>
          <div class="info-grid">
            <div class="info-cell">
              <div class="info-cell-label">Official Website</div>
              <div class="info-cell-value">www.zoefeeds.com</div>
            </div>
            <div class="info-cell">
              <div class="info-cell-label">Support Channels</div>
              <div class="info-cell-value">support@zoefeeds.com</div>
            </div>
            <div class="info-cell">
              <div class="info-cell-label">Location</div>
              <div class="info-cell-value">🇳🇬 Nigeria</div>
            </div>
            <div class="info-cell">
              <div class="info-cell-label">Social Media</div>
              <div class="info-cell-value">Official channels on website</div>
            </div>
          </div>
        </div>
      </div>

      <!-- §3 Purpose -->
      <div class="tc-section" id="purpose">
        <div class="tc-section-header">
          <div class="tc-icon">🎯</div>
          <div><div class="tc-section-num">Section 03</div><div class="tc-section-title">Purpose of the Promotion</div></div>
        </div>
        <div class="tc-body">
          <p>The ZoeFeeds Reward Draw is established to reward customers who purchase eligible products and services through the ZoeFeeds platform.</p>
          <div class="highlight-box">
            <p>The Promotion is intended solely as a <strong>customer appreciation initiative</strong> and shall not be construed as a gambling, betting, or wagering activity.</p>
          </div>
        </div>
      </div>

      <!-- §4 Eligibility -->
      <div class="tc-section" id="eligibility">
        <div class="tc-section-header">
          <div class="tc-icon">👤</div>
          <div><div class="tc-section-num">Section 04</div><div class="tc-section-title">Eligibility</div></div>
        </div>
        <div class="tc-body">
          <div class="sub-heading">Who may participate</div>
          <ul class="tc-list tc-eligible-list" style="list-style:none;padding:0;margin:14px 0;">
            <li style="display:flex;align-items:flex-start;gap:10px;color:#4ade80;font-size:.88rem;line-height:1.7;padding:7px 0;border-bottom:1px solid rgba(255,255,255,0.04);">
              <span style="font-weight:700;flex-shrink:0;margin-top:1px;color:#4ade80;">✓</span> Individuals aged at least 18 years old
            </li>
            <li style="display:flex;align-items:flex-start;gap:10px;color:#4ade80;font-size:.88rem;line-height:1.7;padding:7px 0;border-bottom:1px solid rgba(255,255,255,0.04);">
              <span style="font-weight:700;flex-shrink:0;margin-top:1px;color:#4ade80;">✓</span> Persons who possess valid identification
            </li>
            <li style="display:flex;align-items:flex-start;gap:10px;color:#4ade80;font-size:.88rem;line-height:1.7;padding:7px 0;">
              <span style="font-weight:700;flex-shrink:0;margin-top:1px;color:#4ade80;">✓</span> Customers with a valid ZoeFeeds account
            </li>
          </ul>
          <div class="sub-heading">Who is not eligible</div>
          <ul style="list-style:none;padding:0;margin:14px 0;">
            <li style="display:flex;align-items:flex-start;gap:10px;color:#ef4444;font-size:.88rem;line-height:1.7;padding:6px 0;">
              <span style="font-weight:700;flex-shrink:0;margin-top:1px;">✗</span> Employees, directors, and officers of ZoeFeeds
            </li>
            <li style="display:flex;align-items:flex-start;gap:10px;color:#ef4444;font-size:.88rem;line-height:1.7;padding:6px 0;">
              <span style="font-weight:700;flex-shrink:0;margin-top:1px;">✗</span> Any individual directly involved in the administration of the Promotion
            </li>
            <li style="display:flex;align-items:flex-start;gap:10px;color:#ef4444;font-size:.88rem;line-height:1.7;padding:6px 0;">
              <span style="font-weight:700;flex-shrink:0;margin-top:1px;">✗</span> Persons prohibited by law from participating
            </li>
          </ul>
          <p>ZoeFeeds reserves the right to request proof of eligibility at any time.</p>
        </div>
      </div>

      <!-- §5 How to Participate -->
      <div class="tc-section" id="participation">
        <div class="tc-section-header">
          <div class="tc-icon">🎟️</div>
          <div><div class="tc-section-num">Section 05</div><div class="tc-section-title">How to Participate</div></div>
        </div>
        <div class="tc-body">
          <p>Participants may obtain Reward Draw entries through eligible purchases made on the ZoeFeeds platform.</p>
          <div class="sub-heading">Eligible products &amp; services include</div>
          <ul class="tc-list">
            <li>Digital or physical services on the ZoeFeeds platform</li>
            <li>Online or offline products designated by ZoeFeeds</li>
            <li>Other products or services designated by ZoeFeeds from time to time</li>
          </ul>
          <p>Each qualifying transaction may generate one or more raffle codes based on the promotion structure applicable at the time.</p>
          <div class="highlight-box">
            <p>Participation is <strong>not automatic</strong> once an eligible transaction is completed — the corresponding raffle code must be redeemed through your ZoeFeeds account.</p>
          </div>
        </div>
      </div>

      <!-- §6 Raffle Codes -->
      <div class="tc-section" id="codes">
        <div class="tc-section-header">
          <div class="tc-icon">🔑</div>
          <div><div class="tc-section-num">Section 06</div><div class="tc-section-title">Raffle Codes</div></div>
        </div>
        <div class="tc-body">
          <div class="sub-heading">6.1 — Issuance</div>
          <p>Raffle codes are issued exclusively by ZoeFeeds. Each code is a unique <strong>15-digit identifier</strong> linked to your account upon redemption.</p>

          <div class="sub-heading">6.2 — Ownership</div>
          <p>Raffle codes remain linked to the participant's ZoeFeeds account once redeemed. Each code is guarded with a transfer PIN for security.</p>

          <div class="sub-heading">6.3 — Non-Sale of Codes</div>
          <div class="warning-box">
            <p><strong>ZoeFeeds does not sell raffle codes separately.</strong> Any person offering to sell ZoeFeeds raffle codes is acting without authorization. Codes must remain free — forever. On any third-party platform, ZoeFeeds codes must be transferred to you only as a gift.</p>
          </div>

          <div class="sub-heading">6.4 — Code Redemption</div>
          <p>Offline raffle codes must be redeemed through the participant's ZoeFeeds account using the designated redemption feature on the dashboard. Successfully redeemed codes will appear in the participant's <strong>Redeemed Codes Wallet</strong>.</p>

          <div class="sub-heading">6.5 — Gifting &amp; Transfer</div>
          <p>Codes may be gifted freely to other users. Transfers are processed digitally through the platform and are protected by a transfer PIN. Codes cannot be sold under any circumstances.</p>
        </div>
      </div>

      <!-- §7 Draw Entry -->
      <div class="tc-section" id="entry">
        <div class="tc-section-header">
          <div class="tc-icon">📝</div>
          <div><div class="tc-section-num">Section 07</div><div class="tc-section-title">Draw Entry</div></div>
        </div>
        <div class="tc-body">
          <p>Participants may use eligible redeemed raffle codes to enter available reward draws displayed on the ZoeFeeds platform.</p>
          <ul class="tc-list">
            <li>Each raffle code may only be used once per draw unless otherwise stated</li>
            <li>Multiple codes may be entered into a single draw to increase chances</li>
            <li>Unused raffle codes remain subject to expiration periods announced by ZoeFeeds</li>
            <li>Before confirming entry, participants must confirm they have read and understood the draw rules</li>
          </ul>
        </div>
      </div>

      <!-- §8 Draw Process -->
      <div class="tc-section" id="process">
        <div class="tc-section-header">
          <div class="tc-icon">⚙️</div>
          <div><div class="tc-section-num">Section 08</div><div class="tc-section-title">Draw Process</div></div>
        </div>
        <div class="tc-body">
          <p>All draws shall be conducted using a <strong>fair, transparent, random and manual machine selection process</strong>.</p>
          <div class="sub-heading">The draw process may include</div>
          <ul class="tc-list">
            <li>Certified manual random machine selection systems</li>
            <li>Independent observers</li>
            <li>Regulatory representatives where applicable</li>
            <li>Recorded or publicly observable draw procedures with live digit reveals</li>
          </ul>
          <div class="highlight-box">
            <p><strong>Every valid entry shall have an equal chance of selection.</strong> ZoeFeeds operates by the principle that someone must win in each draw — it is mandatory.</p>
          </div>
        </div>
      </div>

      <!-- §9 Prizes -->
      <div class="tc-section" id="prizes">
        <div class="tc-section-header">
          <div class="tc-icon">🏆</div>
          <div><div class="tc-section-num">Section 09</div><div class="tc-section-title">Prizes</div></div>
        </div>
        <div class="tc-body">
          <p>Prize details shall be announced before each draw and displayed on that particular draw's page and on our official channels.</p>
          <ul class="tc-list">
            <li>Prizes are the promotional rewards specified per draw</li>
            <li>Prize values and specifications are published before each draw commences</li>
            <li>Prizes are non-transferable unless expressly approved by ZoeFeeds</li>
            <li>All prizes are real, tangible rewards — no points or voucher substitutes</li>
          </ul>
        </div>
      </div>

      <!-- §10 Winner Selection -->
      <div class="tc-section" id="winner-selection">
        <div class="tc-section-header">
          <div class="tc-icon">🎲</div>
          <div><div class="tc-section-num">Section 10</div><div class="tc-section-title">Winner Selection</div></div>
        </div>
        <div class="tc-body">
          <p>The winner is selected from the pool of valid entries. The owner of the ticket that <strong>matches the draw result in the most positions</strong> (in order of appearance) is selected as the winner.</p>
          <div class="sub-heading">Tiebreaker rules (in order)</div>
          <ul class="tc-list">
            <li><strong style="color:#e5e7eb;">Step 1:</strong> The participant with more ticket entries wins</li>
            <li><strong style="color:#e5e7eb;">Step 2:</strong> If still tied, the participant who registered first on the platform wins</li>
          </ul>
          <p>The selected participant may be required to verify their identity, verify account ownership, and comply with any applicable regulatory requirements. Failure to complete verification may result in disqualification.</p>
        </div>
      </div>

      <!-- §11 Notification -->
      <div class="tc-section" id="notification">
        <div class="tc-section-header">
          <div class="tc-icon">📲</div>
          <div><div class="tc-section-num">Section 11</div><div class="tc-section-title">Winner Notification</div></div>
        </div>
        <div class="tc-body">
          <p>Winners may be notified through:</p>
          <ul class="tc-list">
            <li>Telephone calls to registered number</li>
            <li>SMS to registered phone number</li>
            <li>Email to registered address</li>
            <li>Website announcements</li>
            <li>Official ZoeFeeds social media platforms (live announcement)</li>
          </ul>
          <div class="highlight-box">
            <p>Participants are <strong>responsible for ensuring their contact details remain accurate and current</strong> in their ZoeFeeds account.</p>
          </div>
        </div>
      </div>

      <!-- §12 Claiming -->
      <div class="tc-section" id="claiming">
        <div class="tc-section-header">
          <div class="tc-icon">✅</div>
          <div><div class="tc-section-num">Section 12</div><div class="tc-section-title">Claiming Prizes</div></div>
        </div>
        <div class="tc-body">
          <p>Winners must claim prizes within the period specified by ZoeFeeds. Failure to claim within the prescribed period may result in <strong>forfeiture of the prize</strong>, and an alternate winner may be selected where applicable.</p>
        </div>
      </div>

      <!-- §13 Unclaimed -->
      <div class="tc-section" id="unclaimed">
        <div class="tc-section-header">
          <div class="tc-icon">🔄</div>
          <div><div class="tc-section-num">Section 13</div><div class="tc-section-title">Unclaimed or Unwon Prizes</div></div>
        </div>
        <div class="tc-body">
          <p>If no valid winner emerges, no participant qualifies, or a prize remains unclaimed, ZoeFeeds may:</p>
          <ul class="tc-list">
            <li>Conduct a redraw</li>
            <li>Roll the prize over to a future draw</li>
            <li>Apply any alternative process approved by the relevant regulatory authority</li>
          </ul>
          <p>Participants shall be informed of any such decision through official channels.</p>
        </div>
      </div>

      <!-- §14 Fraud -->
      <div class="tc-section" id="fraud">
        <div class="tc-section-header">
          <div class="tc-icon">🚫</div>
          <div><div class="tc-section-num">Section 14</div><div class="tc-section-title">Fraud &amp; Disqualification</div></div>
        </div>
        <div class="tc-body">
          <p>ZoeFeeds reserves the right to disqualify any participant who:</p>
          <ul style="list-style:none;padding:0;margin:14px 0;">
            <?php foreach(['Uses fraudulent means to participate','Provides false information','Manipulates the draw process','Uses unauthorized or stolen raffle codes','Violates these Terms and Conditions'] as $item): ?>
            <li style="display:flex;align-items:flex-start;gap:10px;color:#ef4444;font-size:.88rem;line-height:1.7;padding:6px 0;border-bottom:1px solid rgba(255,255,255,0.04);">
              <span style="font-weight:700;flex-shrink:0;margin-top:1px;">✗</span> <?= $item ?>
            </li>
            <?php endforeach; ?>
          </ul>
          <p>Any fraudulent activity may be reported to law enforcement agencies.</p>
        </div>
      </div>

      <!-- §15 Liability -->
      <div class="tc-section" id="liability">
        <div class="tc-section-header">
          <div class="tc-icon">⚠️</div>
          <div><div class="tc-section-num">Section 15</div><div class="tc-section-title">Limitation of Liability</div></div>
        </div>
        <div class="tc-body">
          <p>ZoeFeeds shall not be liable for:</p>
          <ul class="tc-list">
            <li>Technical failures or network interruptions</li>
            <li>Lost, delayed, or corrupted data</li>
            <li>Unauthorized access to participant accounts</li>
            <li>Circumstances beyond its reasonable control</li>
          </ul>
        </div>
      </div>

      <!-- §16 Privacy -->
      <div class="tc-section" id="privacy">
        <div class="tc-section-header">
          <div class="tc-icon">🔒</div>
          <div><div class="tc-section-num">Section 16</div><div class="tc-section-title">Privacy</div></div>
        </div>
        <div class="tc-body">
          <p>Participants consent to the collection, processing, and storage of personal information necessary for the administration of the Promotion. Information shall be handled in accordance with applicable data protection laws and the <strong>ZoeFeeds Privacy Policy</strong>.</p>
        </div>
      </div>

      <!-- §17 Publicity -->
      <div class="tc-section" id="publicity">
        <div class="tc-section-header">
          <div class="tc-icon">📢</div>
          <div><div class="tc-section-num">Section 17</div><div class="tc-section-title">Publicity</div></div>
        </div>
        <div class="tc-body">
          <p>By accepting a prize, winners agree that ZoeFeeds may publish the following for promotional and verification purposes, subject to applicable laws:</p>
          <ul class="tc-list">
            <li>Winner's name</li>
            <li>Photograph (where consented)</li>
            <li>City or State of residence</li>
            <li>Prize information</li>
          </ul>
        </div>
      </div>

      <!-- §18 Modification -->
      <div class="tc-section" id="modification">
        <div class="tc-section-header">
          <div class="tc-icon">✏️</div>
          <div><div class="tc-section-num">Section 18</div><div class="tc-section-title">Modification or Cancellation</div></div>
        </div>
        <div class="tc-body">
          <p>ZoeFeeds reserves the right to amend, suspend, or terminate the Promotion where necessary due to regulatory requirements, fraud prevention, technical issues, or circumstances beyond reasonable control. Any material changes will be communicated through official channels.</p>
        </div>
      </div>

      <!-- §19 Compliance -->
      <div class="tc-section" id="compliance">
        <div class="tc-section-header">
          <div class="tc-icon">📜</div>
          <div><div class="tc-section-num">Section 19</div><div class="tc-section-title">Regulatory Compliance</div></div>
        </div>
        <div class="tc-body">
          <p>The Promotion shall operate in accordance with applicable laws and regulatory approvals. Where required, ZoeFeeds shall obtain all necessary licenses, permits, approvals, and authorizations from the appropriate regulatory authorities before conducting any draw.</p>
        </div>
      </div>

      <!-- §20 Governing Law -->
      <div class="tc-section" id="governing-law">
        <div class="tc-section-header">
          <div class="tc-icon">⚖️</div>
          <div><div class="tc-section-num">Section 20</div><div class="tc-section-title">Governing Law</div></div>
        </div>
        <div class="tc-body">
          <p>These Terms and Conditions shall be governed by the <strong>laws of the Federal Republic of Nigeria</strong>. Any dispute arising from the Promotion shall be subject to the jurisdiction of the competent courts of Nigeria.</p>
          <div class="info-grid" style="margin-top:20px;">
            <div class="info-cell">
              <div class="info-cell-label">Jurisdiction</div>
              <div class="info-cell-value">🇳🇬 Federal Republic of Nigeria</div>
            </div>
            <div class="info-cell">
              <div class="info-cell-label">Dispute Resolution</div>
              <div class="info-cell-value">Competent Nigerian Courts</div>
            </div>
          </div>
        </div>
      </div>

      <!-- Contact Section -->
      <div class="tc-section" id="contact">
        <div class="tc-section-header">
          <div class="tc-icon">📞</div>
          <div><div class="tc-section-num">Contact</div><div class="tc-section-title">Get in Touch</div></div>
        </div>
        <div class="tc-body">
          <p>For enquiries, complaints, or verification requests, participants may contact ZoeFeeds through the official support channels published on the ZoeFeeds website.</p>
          <div class="info-grid">
            <div class="info-cell">
              <div class="info-cell-label">Email</div>
              <div class="info-cell-value">support@zoefeeds.com</div>
            </div>
            <div class="info-cell">
              <div class="info-cell-label">Website</div>
              <div class="info-cell-value">www.zoefeeds.com</div>
            </div>
          </div>
        </div>
      </div>

      <!-- ── BIG WARNING ── -->
      <div class="big-warning mb-6">
        <div class="flex items-start gap-4">
          <div class="text-4xl flex-shrink-0">⚠️</div>
          <div>
            <h3 class="display text-xl font-black text-red-400 mb-2">Official Warning</h3>
            <p class="text-gray-300 text-sm leading-relaxed mb-2">
              <strong>ZoeFeeds does not authorize the sale of raffle codes.</strong> Never pay any individual claiming to sell ZoeFeeds raffle codes or guarantee winning entries.
            </p>
            <p class="text-gray-400 text-sm leading-relaxed">
              All official information regarding the Promotion shall be communicated solely through ZoeFeeds' official channels at <strong class="text-white">www.zoefeeds.com</strong>.
            </p>
          </div>
        </div>
      </div>

      <!-- ── OUR AIM ── -->
      <div class="aim-banner mb-6">
        <div class="flex items-start gap-4">
          <div class="text-4xl flex-shrink-0">🎯</div>
          <div>
            <h3 class="display text-xl font-black text-orange-400 mb-2">Our Aim</h3>
            <p class="text-gray-300 text-sm leading-relaxed">
              Our aim is to <strong class="text-white">reward and appreciate loyal customers</strong> through a fair, transparent, and compliant promotional reward program while providing access to valuable products and services.
            </p>
          </div>
        </div>
      </div>

      <!-- CTA -->
      <div class="text-center py-8">
        <p class="text-gray-400 text-sm mb-5">Ready to participate? Join ZoeFeeds — it's completely free.</p>
        <div class="flex flex-wrap gap-3 justify-center">
          <a href="<?= APP_URL ?>/user/register.php" class="btn btn-primary px-8 py-3 font-bold">🎟️ Create Free Account</a>
          <a href="<?= APP_URL ?>/user/login.php"    class="btn btn-secondary px-8 py-3">Log In</a>
          <a href="<?= APP_URL ?>"                   class="btn btn-secondary px-8 py-3">← Back to Home</a>
        </div>
      </div>

    </main>
  </div>
</div>


<!-- ======================================================
     FOOTER
====================================================== -->
<footer style="background:#060b12;border-top:1px solid rgba(255,255,255,0.05);padding:40px 0;">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 text-center">
    <div class="flex items-center justify-center gap-2 mb-3">
      <div class="w-7 h-7 bg-orange-500 rounded-lg flex items-center justify-center font-black text-white text-sm">Z</div>
      <span class="font-bold text-white">Zoe<span class="text-orange-500">Feeds</span></span>
    </div>
    <p class="text-xs text-gray-600 mb-4">The ZoeFeeds Reward Draw is a customer appreciation initiative operating under applicable Nigerian laws. Not a gambling or betting service.</p>
    <p class="text-xs text-gray-700">© <?= date('Y') ?> ZoeFeeds. All rights reserved.</p>
  </div>
</footer>

<!-- Back to top -->
<div id="btt" onclick="window.scrollTo({top:0,behavior:'smooth'})">
  <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 15l7-7 7 7"/></svg>
</div>

<script src="<?= APP_URL ?>/assets/js/app.js"></script>
<script>
// Read progress bar
const bar = document.getElementById('read-progress');
window.addEventListener('scroll', () => {
  const total = document.documentElement.scrollHeight - window.innerHeight;
  bar.style.width = (window.scrollY / total * 100) + '%';
  document.getElementById('btt').classList.toggle('show', window.scrollY > 400);
});

// Active TOC link on scroll
const sections = document.querySelectorAll('.tc-section[id]');
const tocLinks  = document.querySelectorAll('.toc-link[data-target]');
const io = new IntersectionObserver(entries => {
  entries.forEach(e => {
    if (e.isIntersecting) {
      tocLinks.forEach(l => l.classList.remove('active'));
      const active = document.querySelector('.toc-link[data-target="' + e.target.id + '"]');
      if (active) { active.classList.add('active'); active.scrollIntoView({block:'nearest'}); }
    }
  });
}, { rootMargin: '-25% 0px -65% 0px' });
sections.forEach(s => io.observe(s));

// Smooth scroll for TOC links
document.querySelectorAll('a[href^="#"]').forEach(a => {
  a.addEventListener('click', e => {
    const el = document.getElementById(a.getAttribute('href').slice(1));
    if (el) { e.preventDefault(); el.scrollIntoView({ behavior:'smooth', block:'start' }); }
  });
});

// Fade-in on scroll
const fadeObs = new IntersectionObserver(entries => {
  entries.forEach(e => {
    if (e.isIntersecting) {
      e.target.style.opacity = '1';
      e.target.style.transform = 'translateY(0)';
      fadeObs.unobserve(e.target);
    }
  });
}, { threshold: 0.06 });
document.querySelectorAll('.tc-section').forEach((el, i) => {
  el.style.cssText += ';opacity:0;transform:translateY(16px);transition:opacity .45s ease ' + (i * 0.04) + 's, transform .45s ease ' + (i * 0.04) + 's';
  fadeObs.observe(el);
});
</script>
</body>
</html>
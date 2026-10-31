<?php
// index.php — public welcome page
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/lib/draw_trigger.php';   // drawHasDeadline()
startAppSession();
$db = getDB();

// Live draws, with entry counts so entry-triggered draws can show progress instead of a countdown
$draws = $db->query(
    "SELECT d.*, (SELECT COUNT(*) FROM draw_entries WHERE draw_id=d.id) AS total_entries
     FROM draws d WHERE d.status='active'"
)->fetchAll();
// Draws with a real deadline first (soonest ending first), then entry draws with no deadline (oldest start first)
usort($draws, function ($a, $b) {
    $da = drawHasDeadline($a['end_date']); $db_ = drawHasDeadline($b['end_date']);
    if ($da !== $db_) return $da ? -1 : 1;
    return $da ? strtotime($a['end_date']) <=> strtotime($b['end_date'])
               : strtotime($a['start_date']) <=> strtotime($b['start_date']);
});
$draws = array_slice($draws, 0, 6);

$slides  = $db->query("SELECT * FROM slides WHERE status='active' ORDER BY sort_order ASC LIMIT 6")->fetchAll();
$winners = $db->query("SELECT dw.*, d.title as draw_title, u.full_name, u.phone FROM draw_winners dw JOIN draws d ON dw.draw_id=d.id JOIN users u ON dw.user_id=u.id ORDER BY dw.announced_at DESC LIMIT 4")->fetchAll();
$totalWinners = $db->query("SELECT COUNT(*) FROM draw_winners")->fetchColumn();
$totalDraws   = $db->query("SELECT COUNT(*) FROM draws WHERE status='completed'")->fetchColumn();

$testimonials = $db->query(
    "SELECT full_name, role_title, photo, rating, content
     FROM testimonials WHERE status='active'
     ORDER BY display_order ASC, created_at DESC LIMIT 6"
)->fetchAll();
// Sample cards only fill empty slots up to 5 and disappear as real testimonials are added in the admin panel.
// TODO: replace these with real customer feedback (with permission) before launch.
$sampleTestimonials = [
    ['full_name' => 'Chinedu Okafor',    'role_title' => 'Student, Enugu',             'photo' => '', 'rating' => 5, 'content' => 'Data on ZoeFeeds is really affordable and it lands in seconds. Getting a reward ticket with every purchase is a nice bonus.'],
    ['full_name' => 'Aisha Bello',       'role_title' => 'Trader, Kano',               'photo' => '', 'rating' => 5, 'content' => 'I buy airtime for my shop every week. Fast delivery, fair prices, and I can see my reward tickets right in my wallet.'],
    ['full_name' => 'Oluwaseun Adeyemi', 'role_title' => 'Freelance Designer, Lagos', 'photo' => '', 'rating' => 4, 'content' => 'Simple to use and my monthly data bundle always arrives on time. I like that the draws are shown openly.'],
    ['full_name' => 'Ngozi Eze',         'role_title' => 'Teacher, Awka',              'photo' => '', 'rating' => 5, 'content' => 'Sign-up took about a minute. Buying data is easy, and the reward tickets make everyday purchases feel worthwhile.'],
    ['full_name' => 'Ibrahim Musa',      'role_title' => 'Business Owner, Abuja',      'photo' => '', 'rating' => 5, 'content' => 'Good rates compared to what I used to pay. Support replied quickly when I had a question about my reward tickets.'],
];
$testimonials = array_merge($testimonials, array_slice($sampleTestimonials, 0, max(0, 5 - count($testimonials))));

$socials = [
    ['Facebook', 'https://www.facebook.com/share/1ENRgcyQ5N/?mibextid=wwXIfr', '#3b82f6', 'rgba(59,130,246,.12)', 'Updates & announcements',
     'M22 12.06C22 6.51 17.52 2 12 2S2 6.51 2 12.06c0 5 3.66 9.15 8.44 9.94v-7.03H7.9v-2.91h2.54V9.84c0-2.51 1.49-3.9 3.77-3.9 1.09 0 2.23.2 2.23.2v2.46h-1.26c-1.24 0-1.63.77-1.63 1.56v1.9h2.78l-.44 2.91h-2.34V22c4.78-.79 8.44-4.94 8.44-9.94Z'],
    ['YouTube', 'https://youtube.com/@zoefeedsloyaltyrewards?si=j0ibB156206GB7Zc', '#ef4444', 'rgba(239,68,68,.12)', 'Live draw reveals',
     'M23.5 6.2a3.02 3.02 0 0 0-2.12-2.14C19.5 3.5 12 3.5 12 3.5s-7.5 0-9.38.56A3.02 3.02 0 0 0 .5 6.2 31.6 31.6 0 0 0 0 12a31.6 31.6 0 0 0 .5 5.8 3.02 3.02 0 0 0 2.12 2.14c1.88.56 9.38.56 9.38.56s7.5 0 9.38-.56a3.02 3.02 0 0 0 2.12-2.14A31.6 31.6 0 0 0 24 12a31.6 31.6 0 0 0-.5-5.8ZM9.6 15.6V8.4l6.3 3.6-6.3 3.6Z'],
    ['TikTok', 'https://www.tiktok.com/@zoefeedsraffles?_r=1&_t=ZN-97tsm7fZ1Mc', '#ffffff', 'rgba(6,182,212,.12)', 'Behind the scenes',
     'M16.6 5.82c-.9-.98-1.4-2.26-1.4-3.62h-3.1v13.4a3.03 3.03 0 1 1-2.1-2.88V9.6a6.13 6.13 0 1 0 5.2 6.06V9.83a8.2 8.2 0 0 0 4.8 1.53V8.28a5.1 5.1 0 0 1-3.4-2.46Z'],
    ['WhatsApp', 'https://whatsapp.com/channel/0029VaDTNdKGU3BBUThFvS1Y', '#22c55e', 'rgba(34,197,94,.12)', 'Channel updates',
     'M12.04 2a9.93 9.93 0 0 0-8.5 15.07L2 22l5.07-1.5A9.94 9.94 0 1 0 12.04 2Zm5.8 14.05c-.25.7-1.45 1.34-2 1.42-.5.08-1.13.11-1.82-.11-.42-.14-.96-.31-1.65-.61-2.9-1.25-4.8-4.17-4.94-4.37-.14-.19-1.18-1.57-1.18-3s.75-2.13 1.02-2.42c.26-.29.57-.36.76-.36h.55c.18 0 .42-.07.65.5.25.6.84 2.05.91 2.2.07.14.12.31.02.5-.09.2-.14.31-.28.48-.14.17-.3.38-.43.51-.14.14-.29.3-.12.58.17.29.74 1.22 1.6 1.98 1.1.98 2.03 1.28 2.32 1.43.29.14.46.12.63-.07.17-.2.72-.84.91-1.13.19-.29.38-.24.65-.14.26.1 1.68.79 1.97.94.29.14.48.21.55.33.07.12.07.7-.18 1.4Z'],
];

$faqs = [
    ['How do I get ZoeFeeds reward tickets?',
     'You automatically receive free ZoeFeeds reward tickets whenever you buy airtime or data on ZoeFeeds — each successful purchase instantly generates a reward ticket for you. You can also receive reward tickets as gifts, whenever anyone freely gifts you a ticket directly. Every qualifying purchase earns you reward tickets that are included in the promotional draw. Once issued, a reward ticket is recorded and linked to your account, and you can check your account dashboard to view your available reward tickets.'],
    ['How much is a ZoeFeeds reward ticket?',
     'ZoeFeeds reward tickets are completely free and are automatically awarded when you purchase airtime, data, or other eligible products on ZoeFeeds. You do not pay separately for reward tickets — you simply pay the normal price for the airtime or data bundle you\'re buying. <strong class="text-red-400">Warning:</strong> ZoeFeeds does not sell reward tickets, and anyone claiming to sell them is not authorized by ZoeFeeds. Our aim is to reward and appreciate our customers through a fair and transparent promotional reward program while providing affordable products and services. On any third-party platform, ZoeFeeds reward tickets must be transferred to you as a gift — never sold.'],
    ['What is a ZoeFeeds Reward Ticket?',
     'A ZoeFeeds Reward Ticket is a free 15-digit ticket you receive automatically after buying airtime or data (or completing another eligible transaction) on ZoeFeeds, or one that is gifted to you by another party. The reward ticket lands directly in your ZoeFeeds wallet and you can use it to enter any available draw of your choice. Each reward ticket serves as your unique entry identifier in the draw. If your entry is selected as the winning identifier according to the draw rules, you win the prize.'],
    ['How can I check my reward tickets?',
     'You can view all your reward tickets in your ZoeFeeds account dashboard immediately after a successful airtime or data purchase, or after you have manually redeemed a gifted reward ticket into your ZoeFeeds wallet.'],
    ['When will the raffle draw take place?',
     'Each timed draw\'s date is always counting down on our website and may be announced on our official social media platforms. Entry-triggered draws have no end date — they close automatically as soon as they reach their entry target, and the progress is shown on the draw card.'],
    ['How is the winner selected?',
     'Winners are selected through a transparent and random manual process draw to ensure fairness for all participants. The owner of the ticket that matches the draw result in the most positions wins. If there is a tie, the winner with more ticket entries is chosen. If still tied, the participant who registered earliest on the platform wins.'],
    ['Who can participate in the ZoeFeeds Reward Draw?',
     'The promotion is open only to persons aged 18 years and above who meet the participation requirements stated in the promotion rules, possess valid identification, and hold a valid ZoeFeeds account.'],
    ['Can I get more than one reward ticket?',
     'Yes. Every airtime or data purchase you make earns you a new reward ticket, and any gifted reward ticket you receive will earn you additional entries too — increasing your chances of matching the draw.'],
    ['What prizes can be won?',
     'Prize details for each promotional draw will be announced before the draw date and displayed on that particular draw\'s page and on our official channels.'],
    ['How will I know if I win?',
     'Winners will be contacted through their registered phone number and/or email address and may also be announced on ZoeFeeds\' official platforms live!'],
    ['What happens if a winner cannot be reached?',
     'If a winner cannot be contacted or fails to verify their identity within the specified period, another winner may be selected according to the promotion rules. ZoeFeeds operates by the principle that someone must win in each draw — it is mandatory!'],
    ['Can I transfer my reward tickets to someone else?',
     'Yes. Redeemed reward tickets are linked to the account that earned them and guarded with a transfer PIN. However, if you wish, you can transfer a reward ticket as a gift to another user — but reward tickets must never be sold.'],
    ['Is the ZoeFeeds Reward Draw a lottery or gambling scheme?',
     'No. The ZoeFeeds Reward Draw is a customer appreciation promotion tied to your everyday airtime and data purchases. Reward tickets are provided free as a promotional benefit and are not sold. They must remain free and not for sale — forever.'],
    ['Are my chances of winning the same as everyone else\'s?',
     'Yes. Every valid reward ticket you enter into a particular draw has an equal chance of matching with the draw. Buying more airtime or data — and entering more reward tickets — increases your chances.'],
    ['How can I report fraud or suspicious activities?',
     'If anyone claims to sell ZoeFeeds reward tickets or contacts you requesting payment to claim a prize, please contact ZoeFeeds support immediately through our official channels on the website.'],
    ['Where can I find the full promotion rules?',
     'The complete Terms and Conditions for the ZoeFeeds Reward Draws are available on our website. <a href="' . APP_URL . '/user/terms.php" class="text-orange-400 hover:underline">Click here to read the full Terms &amp; Conditions →</a>'],
    ['What do I do when I am gifted a ZoeFeeds reward ticket offline?',
     'If you receive a ZoeFeeds reward ticket offline, log in to your ZoeFeeds account and redeem it using the Reward Ticket Redemption button on your dashboard. Once redeemed, your eligibility balance will increase and the reward ticket will be added to your Redeemed Tickets Wallet, where you can use it to enter any available draw of your choice.'],
    ['How can I verify my reward tickets?',
     'All valid ZoeFeeds reward tickets — whether earned from a purchase or redeemed from a gift — appear in your Redeemed Tickets Wallet on your dashboard. If a reward ticket from a purchase does not appear in your wallet, or a gifted reward ticket cannot be redeemed, contact ZoeFeeds Support for assistance.'],
    ['Can I enter a particular draw with more than one reward ticket?',
     'Yes. You are encouraged to enter with as many reward tickets as you can to increase your chances of being the winner.'],
    ['What if nobody won in a draw?',
     'ZoeFeeds raffles operate by the principle that someone must win and be given the prize in each draw. It is mandatory — every draw produces a winner.'],
];

$steps = [
    ['1', '📝', 'Create your account', 'Register with your phone number in under 60 seconds. Free forever. Age 18+ only.'],
    ['2', '📶', 'Buy airtime or data', 'Purchase affordable, long-lasting airtime or data bundles directly on ZoeFeeds. Every successful purchase automatically issues you a unique 15-digit reward ticket into your wallet — no extra step needed.'],
    ['3', '🎯', 'Enter a draw', 'Choose an active draw campaign and submit your reward ticket(s). More purchases = more reward tickets = more chances.'],
    ['4', '🏆', 'Win prizes', 'Our transparent, certified manual machine draw process selects winners fairly. Winners are notified via SMS, email &amp; website announcement.'],
];
$vals = [
    ['📶', 'Affordable',  'Long-lasting airtime & data bundles at competitive rates'],
    ['⚖️', 'Fair',        'Equal chance for every valid entry. Certified random selection process.'],
    ['👁️', 'Transparent', 'Live draw reveals. Every digit shown publicly in real-time.'],
    ['✅', 'Verified',    'All winners independently verified before prizes are awarded.'],
    ['🇳🇬', 'Compliant',   'Operating under Nigerian laws with all required regulatory approvals.'],
    ['🏆', 'Real prizes', 'Cash, credits, and other tangible prizes — no points, no vouchers.'],
];
$tickerItems = ['📶 Buy Affordable Airtime &amp; Data', '🎟️ Free Reward Ticket Per Purchase', '✅ Verified &amp; Compliant', '🏆 Life-Changing Prizes', '🔒 Secure &amp; Transparent', '⚡ Utility Bills Soon'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ZoeFeeds — Buy Affordable Airtime &amp; Data, Win Big Prizes</title>
  <meta name="description" content="ZoeFeeds lets you buy affordable, long-lasting airtime and data — and every purchase automatically earns you a reward ticket to enter our fair, transparent draws for cash and prizes.">
  <script src="<?= APP_URL ?>/assets/js/tailwind.js"></script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
  <style>
    :root { --topbar-h: 44px; --nav-h: 64px; }
    @media (max-width: 379px) { :root { --topbar-h: 72px; } }
    html { scroll-behavior: smooth; }
    html, body { max-width: 100%; overflow-x: hidden; }
    body { background: #0a0f1a; color: #fff; -webkit-font-smoothing: antialiased; }
    section[id] { scroll-margin-top: calc(var(--topbar-h) + var(--nav-h) + 8px); }

    .gt { background: linear-gradient(135deg,#f97316,#fb923c,#fcd34d); -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent; }
    .glass { background: rgba(10,15,26,.82); backdrop-filter: blur(14px); -webkit-backdrop-filter: blur(14px); }

    /* ── Layout helpers (self-contained responsive) ── */
    .lp-wrap { width: 100%; max-width: 1200px; margin: 0 auto; padding: 0 16px; box-sizing: border-box; }
    .lp-section { padding: 56px 0; }
    .lp-alt { background: #0d1218; border-top: 1px solid rgba(255,255,255,.04); border-bottom: 1px solid rgba(255,255,255,.04); }
    .lp-head { text-align: center; max-width: 640px; margin: 0 auto 36px; }
    .lp-head h2 { font-size: clamp(1.6rem, 5vw, 2.4rem); font-weight: 800; letter-spacing: -.02em; line-height: 1.15; margin-bottom: 10px; }
    .lp-head p { color: #94a3b8; font-size: 14.5px; line-height: 1.65; }
    .lp-kicker { display: inline-flex; align-items: center; gap: 8px; padding: 5px 14px; border-radius: 999px; font-size: 12.5px; font-weight: 600; margin-bottom: 14px; border: 1px solid; }

    .lp-grid { display: grid; gap: 16px; grid-template-columns: repeat(var(--c0,1), minmax(0,1fr)); }
    .lp-grid > * { min-width: 0; }
    @media (min-width: 640px)  { .lp-grid { grid-template-columns: repeat(var(--c1, var(--c0,1)), minmax(0,1fr)); } }
    @media (min-width: 768px)  { .lp-grid { grid-template-columns: repeat(var(--c2, var(--c1, var(--c0,1))), minmax(0,1fr)); } }
    @media (min-width: 1024px) { .lp-grid { grid-template-columns: repeat(var(--c3, var(--c2, var(--c1, var(--c0,1)))), minmax(0,1fr)); gap: 20px; } }
    @media (min-width: 768px)  { .lp-section { padding: 80px 0; } .lp-wrap { padding: 0 24px; } }

    /* ── Top bar + nav ── */
    .lp-topbar { position: fixed; top: 0; left: 0; right: 0; z-index: 50; height: var(--topbar-h); display: flex; align-items: center; justify-content: flex-end; padding: 0 16px; border-bottom: 1px solid rgba(255,255,255,.05); }
    .lp-topbar-inner { display: flex; align-items: center; gap: 12px; }
    .lp-topbar p { font-size: 12px; color: #cbd5e1; white-space: nowrap; margin: 0; }
    .lp-topbar a svg { width: 24px; height: 24px; }
    @media (max-width: 379px) { .lp-topbar-inner { flex-direction: column; gap: 4px; } }
    .lp-nav { position: fixed; top: var(--topbar-h); left: 0; right: 0; z-index: 49; border-bottom: 1px solid rgba(255,255,255,.06); }
    .lp-nav-row { height: var(--nav-h); display: flex; align-items: center; justify-content: space-between; gap: 12px; }
    .lp-links { display: none; align-items: center; gap: 22px; }
    .lp-links a { font-size: 13.5px; font-weight: 500; color: #94a3b8; transition: color .15s; white-space: nowrap; }
    .lp-links a:hover { color: #fff; }
    .lp-burger { display: inline-flex; padding: 8px; color: #94a3b8; }
    .lp-login { display: none; }
    @media (min-width: 640px)  { .lp-login { display: inline-flex; } }
    @media (min-width: 1024px) { .lp-links { display: flex; } .lp-burger { display: none; } }
    #mob-menu a.lp-mlink { display: block; padding: 10px 12px; color: #94a3b8; font-size: 14px; border-radius: 10px; }
    #mob-menu a.lp-mlink:hover { background: rgba(255,255,255,.05); color: #fff; }

    /* ── Hero ── */
    .hero { position: relative; overflow: hidden; padding-top: calc(var(--topbar-h) + var(--nav-h) + 24px); padding-bottom: 40px; }
    .hero-grid { display: grid; gap: 28px; align-items: center; grid-template-columns: minmax(0,1fr); }
    .hero-grid > * { min-width: 0; }
    @media (min-width: 1024px) { .hero { padding-bottom: 64px; min-height: 100vh; display: flex; align-items: center; } .hero .lp-wrap { width: 100%; } .hero-grid { grid-template-columns: repeat(2, minmax(0,1fr)); gap: 56px; } .hero-photo-col { order: 2; } }
    .hero h1 { font-size: clamp(2.1rem, 8vw, 4.4rem); line-height: 1.07; font-weight: 800; letter-spacing: -.03em; margin-bottom: 18px; }
    .hero-lead { color: #cbd5e1; font-size: clamp(.95rem, 2.6vw, 1.1rem); line-height: 1.7; max-width: 560px; margin-bottom: 24px; }
    .hero-actions { display: flex; flex-wrap: wrap; gap: 12px; margin-bottom: 28px; }
    .hero-stats { display: flex; align-items: center; gap: 20px; flex-wrap: wrap; }
    .hero-stat b { display: block; font-size: clamp(1.7rem, 6vw, 2.3rem); font-weight: 800; line-height: 1; }
    .hero-stat span { font-size: 12.5px; color: #94a3b8; }
    .hero-divider { width: 1px; height: 36px; background: rgba(255,255,255,.14); }
    .hero-photo { position: relative; width: 100%; aspect-ratio: 4/3; overflow: hidden; border-radius: 24px; border: 1px solid rgba(251,146,60,.35); box-shadow: 0 18px 40px -18px rgba(251,146,60,.55); }
    .hero-photo img { display: block; width: 100%; height: 100%; object-fit: cover; object-position: 55% 30%; }
    @media (min-width: 1024px) {
      .hero-photo { aspect-ratio: auto; height: min(600px, 72vh); border: 0; border-bottom: 3px solid #fbbf24; border-radius: 0 0 0 220px; box-shadow: 0 14px 30px -14px rgba(251,146,60,.9); }
      .hero-photo img { object-position: 55% center; -webkit-mask-image: linear-gradient(to right, transparent 0%, #000 30%); mask-image: linear-gradient(to right, transparent 0%, #000 30%); }
    }
    .particle { position: absolute; border-radius: 50%; opacity: .25; animation: pf var(--d) ease-in-out infinite; pointer-events: none; }
    @keyframes pf { 0%,100% { transform: translate(0,0) } 40% { transform: translate(18px,-26px) } 70% { transform: translate(-12px,10px) } }

    /* ── Buttons (landing) ── */
    .lp-btn { display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 12px 22px; border-radius: 12px; font-size: 14.5px; font-weight: 600; border: 1px solid transparent; transition: background .15s, border-color .15s, transform .15s; text-decoration: none; cursor: pointer; }
    .lp-btn:hover { transform: translateY(-1px); }
    .lp-btn-primary { background: #f97316; color: #fff; box-shadow: 0 8px 22px -8px rgba(249,115,22,.7); }
    .lp-btn-primary:hover { background: #ea580c; }
    .lp-btn-ghost { background: rgba(255,255,255,.04); border-color: rgba(255,255,255,.14); color: #e2e8f0; }
    .lp-btn-ghost:hover { background: rgba(255,255,255,.09); }
    .lp-btn-lg { padding: 15px 30px; font-size: 16px; border-radius: 14px; }
    .lp-btn-block { width: 100%; }

    /* ── Ticker ── */
    .ticker-wrap { overflow: hidden; background: rgba(249,115,22,.06); border-top: 1px solid rgba(249,115,22,.15); border-bottom: 1px solid rgba(249,115,22,.15); padding: 11px 0; }
    .ticker { display: flex; width: max-content; animation: tk 32s linear infinite; }
    .ticker:hover { animation-play-state: paused; }
    @keyframes tk { from { transform: translateX(0) } to { transform: translateX(-50%) } }

    /* ── Draw cards ── */
    .lp-draw { display: flex; flex-direction: column; overflow: hidden; border-radius: 18px; background: rgba(255,255,255,.03); border: 1px solid rgba(255,255,255,.08); transition: transform .25s, border-color .25s, box-shadow .25s; color: inherit; text-decoration: none; }
    .lp-draw:hover { transform: translateY(-4px); border-color: rgba(249,115,22,.4); box-shadow: 0 20px 44px rgba(0,0,0,.45); }
    .lp-draw-banner { height: 150px; display: flex; align-items: center; justify-content: center; color: #475569; background: linear-gradient(135deg,#1a2235,#0d1118); overflow: hidden; }
    .lp-draw-banner img { width: 100%; height: 100%; object-fit: cover; }
    .lp-draw-body { padding: 18px; display: flex; flex-direction: column; flex: 1; }
    .lp-draw-body h3 { font-weight: 600; font-size: 16px; line-height: 1.35; margin-bottom: 6px; overflow-wrap: anywhere; }

    /* ── Cards ── */
    .lp-card { background: rgba(255,255,255,.03); border: 1px solid rgba(255,255,255,.08); border-radius: 16px; padding: 22px; }
    .lp-icon { width: 46px; height: 46px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 22px; background: rgba(249,115,22,.1); border: 1px solid rgba(249,115,22,.22); flex-shrink: 0; }
    .step-num { display: inline-flex; align-items: center; justify-content: center; width: 24px; height: 24px; border-radius: 50%; background: #f97316; font-size: 12px; font-weight: 700; margin-bottom: 8px; }
    .winner-card { background: linear-gradient(135deg, rgba(234,179,8,.10), rgba(0,0,0,0)); border: 1px solid rgba(234,179,8,.25); border-radius: 16px; padding: 22px; text-align: center; }

    /* ── Slideshow (markup kept for app.js) ── */
    .slideshow { border-radius: 18px; border: 1px solid rgba(255,255,255,.08); }
    .slide img { width: 100%; height: 256px; object-fit: cover; display: block; }
    @media (min-width: 768px) { .slide img { height: 340px; } }

    /* ── FAQ ── */
    .faq-item { background: rgba(255,255,255,.03); border: 1px solid rgba(255,255,255,.08); border-radius: 14px; margin-bottom: 10px; overflow: hidden; transition: border-color .2s; }
    .faq-item.faq-open { border-color: rgba(249,115,22,.35); }
    .faq-q { width: 100%; text-align: left; padding: 16px 18px; display: flex; align-items: center; justify-content: space-between; gap: 12px; font-weight: 600; font-size: 14.5px; line-height: 1.4; }
    .faq-q:hover { background: rgba(255,255,255,.03); }
    .faq-body { display: grid; grid-template-rows: 0fr; transition: grid-template-rows .3s ease; }
    .faq-body > div { overflow: hidden; }
    .faq-open .faq-body { grid-template-rows: 1fr; }
    .faq-ans { padding: 0 18px 18px; font-size: 13.5px; line-height: 1.7; color: #94a3b8; }
    .faq-arr { transition: transform .3s; flex-shrink: 0; }
    .faq-open .faq-arr { transform: rotate(180deg); }

    .live-counter { transition: all .3s ease; }
  </style>
</head>

<body>
<?php include __DIR__ . '/components/saas-ui.php'; // ico(), pills, progress bar styles ?>

  <!-- TOP BAR: social -->
  <div class="lp-topbar glass">
    <div class="lp-topbar-inner">
      <p>Connect with us on social media</p>
      <div style="display:flex;gap:12px;align-items:center">
        <?php foreach ($socials as [$name, $url, $color, $tint, $tag, $path]): ?>
        <a href="<?= e($url) ?>" target="_blank" rel="noopener noreferrer" aria-label="<?= e($name) ?>"><svg viewBox="0 0 24 24" fill="<?= $color ?>"><path d="<?= $path ?>"/></svg></a>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- NAVBAR -->
  <nav class="lp-nav glass">
    <div class="lp-wrap">
      <div class="lp-nav-row">
        <a href="<?= APP_URL ?>" class="flex items-center gap-2 flex-shrink-0">
          <div class="w-9 h-9 bg-orange-500 rounded-xl flex items-center justify-center font-black text-white text-xl">Z</div>
          <span class="font-bold text-xl">Zoe<span class="text-orange-500">Feeds</span></span>
        </a>

        <div class="lp-links">
          <a href="#buy">Buy Airtime/Data</a>
          <a href="#draws">Draws</a>
          <a href="#how-it-works">How It Works</a>
          <a href="#winners">Winners</a>
          <a href="#about">About</a>
          <a href="#faq">FAQ</a>
          <a href="<?= APP_URL ?>/user/terms.php">Terms</a>
          <span style="width:1px;height:16px;background:rgba(255,255,255,.12)"></span>
          <a href="<?= APP_URL ?>/vendor" style="color:#a78bfa;font-weight:600">🏪 Become a Vendor</a>
        </div>

        <div class="flex items-center gap-2">
          <a href="<?= APP_URL ?>/user/login.php" class="lp-btn lp-btn-ghost lp-login" style="padding:8px 16px;font-size:13px">Log in</a>
          <a href="<?= APP_URL ?>/user/register.php" class="lp-btn lp-btn-primary" style="padding:8px 16px;font-size:13px">Get started</a>
          <button id="mob-menu-btn" class="lp-burger" onclick="document.getElementById('mob-menu').classList.toggle('hidden')" aria-label="Menu">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
          </button>
        </div>
      </div>

      <!-- Mobile menu -->
      <div id="mob-menu" class="hidden" style="padding:10px 0 16px;border-top:1px solid rgba(255,255,255,.06)">
        <?php foreach (['#buy' => 'Buy Airtime/Data', '#draws' => 'Draws', '#how-it-works' => 'How It Works', '#winners' => 'Winners', '#about' => 'About', '#faq' => 'FAQ'] as $h => $l): ?>
        <a href="<?= $h ?>" class="lp-mlink" onclick="document.getElementById('mob-menu').classList.add('hidden')"><?= $l ?></a>
        <?php endforeach; ?>
        <a href="<?= APP_URL ?>/user/terms.php" class="lp-mlink">Terms</a>
        <a href="<?= APP_URL ?>/vendor" class="lp-mlink" style="color:#a78bfa;font-weight:600">🏪 Become a Vendor</a>
        <div style="border-top:1px solid rgba(255,255,255,.06);margin-top:8px;padding-top:10px;display:grid;gap:8px">
          <a href="<?= APP_URL ?>/user/login.php" class="lp-btn lp-btn-ghost">Log in</a>
          <a href="<?= APP_URL ?>/user/register.php" class="lp-btn lp-btn-primary">Get started free</a>
        </div>
      </div>
    </div>
  </nav>


  <!-- HERO -->
  <header class="hero" id="buy">
    <div class="absolute inset-0 pointer-events-none" style="overflow:hidden">
      <div style="position:absolute;top:20%;right:20%;width:380px;height:380px;background:rgba(249,115,22,.10);border-radius:50%;filter:blur(70px)"></div>
      <div style="position:absolute;bottom:15%;left:15%;width:260px;height:260px;background:rgba(6,182,212,.06);border-radius:50%;filter:blur(70px)"></div>
    </div>
    <div class="particle bg-orange-500" style="width:12px;height:12px;top:18%;left:8%;--d:8s"></div>
    <div class="particle bg-cyan-400" style="width:8px;height:8px;top:65%;left:4%;--d:10s"></div>
    <div class="particle bg-orange-400" style="width:16px;height:16px;top:28%;right:7%;--d:7s"></div>
    <div class="particle bg-yellow-400" style="width:8px;height:8px;top:72%;right:12%;--d:9s"></div>

    <div class="lp-wrap" style="position:relative;z-index:1">
      <div class="hero-grid">
        <div class="hero-photo-col">
          <div class="hero-photo">
            <img src="<?= APP_URL ?>/assets/img/hero-loyalty.jpg" alt="A mother and her child smiling together" width="1080" height="720" fetchpriority="high">
          </div>
        </div>

        <div>
          <h1>Your Loyalty<br class="hidden sm:block"> Creates<br><span class="gt">Opportunity,<br>Shared Future.</span></h1>
          <p class="hero-lead">
            Zoefeeds is more than buying and selling. We’re building <strong class="text-white">a movement where customer loyalty creates rewarding opportunities and business growth helps us support people in need.</strong>
            <span class="text-sky-400">Shop, earn eligible raffle entries, discover rewards, and be part of something meaningful.</span>
          </p>
          <div class="hero-actions">
            <a href="#draws" class="lp-btn lp-btn-primary lp-btn-lg">Explore rewards <?= ico('arrow-left', 'w-5 h-5 rotate-180') ?></a>
            <a href="<?= APP_URL ?>/user/login.php" class="lp-btn lp-btn-ghost lp-btn-lg">Log in</a>
          </div>
          <div class="hero-stats">
            <div class="hero-stat"><b id="stat-winners" class="live-counter text-green-400"><?= (int)$totalWinners ?></b><span>Winners</span></div>
            <div class="hero-divider"></div>
            <div class="hero-stat"><b id="stat-draws" class="live-counter text-cyan-400"><?= (int)$totalDraws ?></b><span>Draws completed</span></div>
          </div>
        </div>
      </div>
    </div>
  </header>


  <!-- TICKER -->
  <div class="ticker-wrap" aria-hidden="true">
    <div class="ticker">
      <?php for ($i = 0; $i < 4; $i++) foreach ($tickerItems as $it): ?>
        <span class="text-orange-300/80 font-medium text-sm whitespace-nowrap" style="padding:0 28px"><?= $it ?></span>
        <span class="text-orange-500/30 text-xl" style="padding:0 6px">·</span>
      <?php endforeach; ?>
    </div>
  </div>


  <!-- ADVERT SLIDESHOW -->
  <?php if ($slides): ?>
  <section class="lp-section" style="padding-bottom:0">
    <div class="lp-wrap">
      <h2 class="text-xl md:text-2xl font-bold mb-5">Featured campaigns</h2>
      <div class="relative slideshow bg-[#111827] overflow-hidden" data-slideshow style="min-height:256px">
        <?php foreach ($slides as $s): ?>
        <div class="slide">
          <?php if ($s['image_path'] && file_exists(UPLOAD_PATH . $s['image_path'])): ?>
            <img src="<?= APP_URL ?>/uploads/<?= e($s['image_path']) ?>" alt="<?= e($s['title'] ?? '') ?>">
          <?php else: ?>
            <div class="flex flex-col items-center justify-center" style="height:256px;background:linear-gradient(135deg,#1a2235,#0d1929)">
              <div class="text-5xl mb-3">📶</div>
              <div class="text-xl font-bold"><?= e($s['title'] ?? 'ZoeFeeds Campaign') ?></div>
            </div>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <div class="slide-dots absolute bottom-4 left-0 right-0 flex justify-center gap-2"></div>
      </div>
    </div>
  </section>
  <?php endif; ?>


  <!-- LIVE DRAWS -->
  <section id="draws" class="lp-section">
    <div class="lp-wrap">
      <div class="lp-head">
        <div class="lp-kicker" style="color:#f87171;background:rgba(239,68,68,.1);border-color:rgba(239,68,68,.22)"><span class="pulse-dot" style="background:#ef4444"></span> Live draws</div>
        <h2>Active draw campaigns</h2>
        <p>Every airtime or data purchase earns you a reward ticket. Enter draws using your redeemed reward tickets — every valid entry has an equal chance of selection, and there is a <strong class="text-orange-400">mandatory</strong> winner every draw.</p>
      </div>

      <?php if ($draws): ?>
      <div class="lp-grid" style="--c1:2;--c3:3">
        <?php foreach ($draws as $d):
          $isEntries   = ($d['draw_type'] ?? 'timed') === 'entries';
          $hasDeadline = drawHasDeadline($d['end_date']);        // entry draws without a deadline store a placeholder date: never show it
          $target      = (int)($d['target_entries'] ?? 0);
          $total       = (int)$d['total_entries'];
          $pct         = ($isEntries && $target > 0) ? min(100, (int)floor($total / $target * 100)) : 0;
          $reached     = $isEntries && $target > 0 && $total >= $target;
          $left        = max(0, $target - $total);
          $prize       = trim((string)$d['prize_details']);
          if (mb_strlen($prize) > 55) $prize = mb_substr($prize, 0, 55) . '…';
        ?>
        <a href="<?= APP_URL ?>/user/register.php" class="lp-draw">
          <div class="lp-draw-banner">
            <?php if ($d['banner_image'] && file_exists(UPLOAD_PATH . $d['banner_image'])): ?>
              <img src="<?= APP_URL ?>/uploads/<?= e($d['banner_image']) ?>" alt="">
            <?php else: ?><?= ico($isEntries ? 'target' : 'trophy', 'w-12 h-12') ?><?php endif; ?>
          </div>
          <div class="lp-draw-body">
            <div class="flex gap-1.5 flex-wrap mb-3">
              <span class="s-pill s-pill-green dot">Live</span>
              <?php if ($isEntries): ?><span class="s-pill s-pill-purple"><?= ico('target', 'w-3 h-3') ?> Entry-triggered</span><?php endif; ?>
              <?php if ($d['category']): ?><span class="s-pill s-pill-blue"><?= e($d['category']) ?></span><?php endif; ?>
            </div>
            <h3><?= e($d['title']) ?></h3>
            <?php if ($prize !== ''): ?>
            <p class="text-sm text-orange-400 font-medium mb-3 flex items-start gap-1.5"><?= ico('trophy', 'w-4 h-4 mt-0.5 flex-shrink-0') ?><span><?= e($prize) ?></span></p>
            <?php endif; ?>

            <div style="flex:1" class="space-y-3 mb-4">
              <?php if ($hasDeadline): ?>
              <!-- Countdown only for draws with a real deadline -->
              <div class="flex gap-3 items-center flex-wrap" data-countdown="<?= e($d['end_date']) ?>"></div>
              <?php endif; ?>

              <?php if ($isEntries && $target > 0): ?>
              <div>
                <div class="flex items-center justify-between text-xs mb-1.5">
                  <span class="font-medium <?= $reached ? 'text-green-400' : 'text-slate-300' ?>"><?= number_format($total) ?> / <?= number_format($target) ?> entries</span>
                  <span class="<?= $reached ? 'text-green-400' : 'text-slate-500' ?>"><?= $reached ? 'Target reached' : $pct . '%' ?></span>
                </div>
                <div class="s-progress"><div class="<?= $reached ? 'done' : '' ?>" style="width:<?= $pct ?>%"></div></div>
                <?php if (!$reached): ?>
                <div class="text-[11.5px] text-slate-500 mt-1.5">Closes after <?= number_format($left) ?> more <?= $left === 1 ? 'entry' : 'entries' ?><?= $hasDeadline ? ', or at the deadline' : '' ?>.</div>
                <?php endif; ?>
              </div>
              <?php elseif (!$hasDeadline): ?>
              <div class="text-xs text-slate-500">No end date</div>
              <?php endif; ?>
            </div>

            <span class="lp-btn lp-btn-primary lp-btn-block" style="padding:11px 18px;font-size:14px">Enter draw →</span>
          </div>
        </a>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
      <div class="lp-card text-center" style="max-width:520px;margin:0 auto;padding:40px 24px">
        <div class="w-12 h-12 mx-auto mb-4 rounded-full bg-white/5 flex items-center justify-center text-slate-400"><?= ico('inbox', 'w-6 h-6') ?></div>
        <div class="font-semibold mb-1">No live draws right now</div>
        <p class="text-sm text-slate-400 mb-5">New draws are announced on our social channels. Create a free account so you're ready when the next one opens.</p>
        <a href="<?= APP_URL ?>/user/register.php" class="lp-btn lp-btn-primary">Create free account</a>
      </div>
      <?php endif; ?>
    </div>
  </section>


  <!-- HOW IT WORKS -->
  <section id="how-it-works" class="lp-section lp-alt">
    <div class="lp-wrap">
      <div class="lp-head">
        <h2>How ZoeFeeds works</h2>
        <p>Simple steps to start winning. Participation is straightforward, transparent, and fully documented.</p>
      </div>
      <div class="lp-grid" style="--c1:2;--c3:4">
        <?php foreach ($steps as $s): ?>
        <div class="lp-card">
          <div class="flex items-center justify-between mb-4">
            <div class="lp-icon"><?= $s[1] ?></div>
            <span class="step-num"><?= $s[0] ?></span>
          </div>
          <h3 class="font-semibold text-base mb-2"><?= $s[2] ?></h3>
          <p class="text-slate-400 text-sm leading-relaxed"><?= $s[3] ?></p>
        </div>
        <?php endforeach; ?>
      </div>
      <div class="text-center mt-10">
        <a href="<?= APP_URL ?>/user/register.php" class="lp-btn lp-btn-primary lp-btn-lg">Start now — it's free</a>
      </div>
    </div>
  </section>


  <!-- ABOUT -->
  <section id="about" class="lp-section">
    <div class="lp-wrap">
      <div class="lp-grid" style="--c3:2;gap:40px;align-items:center">
        <div>
          <div class="lp-kicker" style="color:#4ade80;background:rgba(34,197,94,.1);border-color:rgba(34,197,94,.22)">✅ Regulated &amp; compliant</div>
          <h2 style="font-size:clamp(1.7rem,5vw,2.4rem);font-weight:800;letter-spacing:-.02em;margin-bottom:16px">Our purpose</h2>
          <p class="text-slate-400 leading-relaxed mb-4">ZoeFeeds helps customers get <strong class="text-white">affordable, reliable airtime and data</strong> while running the <strong class="text-white">ZoeFeeds Reward Draw</strong> — a customer appreciation and promotional campaign designed to reward eligible customers through a transparent and fair draw process.</p>
          <p class="text-slate-400 leading-relaxed mb-4">Our aim is to <strong class="text-white">reward and appreciate loyal customers</strong> through a fair, transparent, and compliant promotional reward program while providing affordable access to airtime, data, and other valuable products and services.</p>
          <p class="text-slate-400 leading-relaxed mb-6">The Promotion is intended solely as a <strong class="text-white">customer appreciation initiative</strong> and shall not be construed as a gambling, betting, or wagering activity. All draws are conducted in accordance with applicable laws and regulatory approvals.</p>
          <div class="flex flex-wrap gap-3">
            <a href="<?= APP_URL ?>/user/terms.php" class="lp-btn lp-btn-ghost">📄 Read full T&amp;C</a>
            <a href="<?= APP_URL ?>/user/register.php" class="lp-btn lp-btn-primary">Join ZoeFeeds</a>
          </div>
        </div>
        <div class="lp-grid" style="--c0:2;gap:12px">
          <?php foreach ($vals as $v): ?>
          <div class="lp-card" style="padding:18px">
            <div class="text-2xl mb-2"><?= $v[0] ?></div>
            <div class="font-semibold text-sm mb-1"><?= $v[1] ?></div>
            <div class="text-xs text-slate-500 leading-relaxed"><?= $v[2] ?></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </section>


  <!-- RECENT WINNERS -->
  <section id="winners" class="lp-section lp-alt">
    <div class="lp-wrap">
      <div class="lp-head">
        <h2>Recent winners</h2>
        <p>Winners are selected transparently. Every winner is verified before prizes are awarded.</p>
      </div>
      <?php if ($winners): ?>
      <div class="lp-grid" style="--c0:1;--c1:2;--c3:4">
        <?php foreach ($winners as $w):
          $initial = strtoupper(mb_substr($w['full_name'], 0, 1));
          $maskedName = $initial . '***' . mb_substr($w['full_name'], -1);
          $dt = mb_strlen($w['draw_title']) > 30 ? mb_substr($w['draw_title'], 0, 30) . '…' : $w['draw_title'];
        ?>
        <div class="winner-card">
          <div class="w-12 h-12 mx-auto mb-3 rounded-full bg-yellow-500/15 text-yellow-400 flex items-center justify-center"><?= ico('trophy', 'w-6 h-6') ?></div>
          <div class="font-semibold text-yellow-400 text-sm mb-0.5"><?= e($maskedName) ?></div>
          <div class="text-xs text-slate-400 mb-3"><?= e($dt) ?></div>
          <div class="font-mono text-xs text-orange-400 bg-black/25 rounded-lg px-2 py-1 mb-2"><?= e(substr($w['winning_code'], 0, 7)) . '·····' ?></div>
          <div class="text-xs text-slate-600"><?= date('M j, Y', strtotime($w['announced_at'])) ?></div>
        </div>
        <?php endforeach; ?>
      </div>
      <div class="text-center mt-8"><a href="<?= APP_URL ?>/user/winners.php" class="lp-btn lp-btn-ghost">View all winners →</a></div>
      <?php else: ?>
      <div class="lp-card text-center" style="max-width:520px;margin:0 auto;padding:40px 24px">
        <div class="w-12 h-12 mx-auto mb-4 rounded-full bg-yellow-500/10 text-yellow-400 flex items-center justify-center"><?= ico('trophy', 'w-6 h-6') ?></div>
        <div class="font-semibold mb-1">Winners will appear here</div>
        <p class="text-sm text-slate-400">As soon as a draw is completed, the verified winner is announced on this page.</p>
      </div>
      <?php endif; ?>
    </div>
  </section>


  <!-- TESTIMONIALS -->
  <section class="lp-section">
    <div class="lp-wrap">
      <div class="lp-head">
        <h2>What participants say</h2>
        <p>Real experiences from real ZoeFeeds users</p>
      </div>
      <div class="lp-grid" style="--c2:2;--c3:3">
        <?php foreach ($testimonials as $t): ?>
        <div class="lp-card">
          <div class="text-yellow-400 mb-3 text-sm"><?= str_repeat('⭐', max(0, min(5, (int)$t['rating']))) ?></div>
          <p class="text-slate-300 text-sm leading-relaxed mb-5">"<?= nl2br(e($t['content'])) ?>"</p>
          <div class="flex items-center gap-3">
            <?php if ($t['photo'] && file_exists(UPLOAD_PATH . $t['photo'])): ?>
              <img src="<?= APP_URL ?>/uploads/<?= e($t['photo']) ?>" class="w-9 h-9 rounded-full object-cover flex-shrink-0" alt="">
            <?php else: ?>
              <div class="w-9 h-9 bg-orange-500/20 rounded-full flex items-center justify-center font-bold text-orange-400 flex-shrink-0"><?= e(mb_substr($t['full_name'], 0, 1)) ?></div>
            <?php endif; ?>
            <div class="min-w-0">
              <div class="font-semibold text-sm truncate"><?= e($t['full_name']) ?></div>
              <?php if ($t['role_title']): ?><div class="text-xs text-slate-500 truncate"><?= e($t['role_title']) ?></div><?php endif; ?>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>


  <!-- SOCIAL MEDIA -->
  <section class="lp-section lp-alt">
    <div class="lp-wrap" style="max-width:960px">
      <div class="lp-head">
        <div class="lp-kicker" style="color:#fb923c;background:rgba(249,115,22,.1);border-color:rgba(249,115,22,.22)">📲 Stay connected</div>
        <h2>Connect with us</h2>
        <p>Follow ZoeFeeds on social media for live draw reveals, winner announcements, and updates — straight from our official channels.</p>
      </div>
      <div class="lp-grid" style="--c0:1;--c1:2;--c3:4">
        <?php foreach ($socials as [$name, $url, $color, $tint, $tag, $path]): ?>
        <a href="<?= e($url) ?>" target="_blank" rel="noopener noreferrer" class="lp-card text-center hover:-translate-y-1 transition" style="display:flex;flex-direction:column;align-items:center;gap:10px">
          <div class="w-14 h-14 rounded-2xl flex items-center justify-center" style="background:<?= $tint ?>"><svg class="w-7 h-7" viewBox="0 0 24 24" fill="<?= $color ?>"><path d="<?= $path ?>"/></svg></div>
          <div class="font-semibold text-sm"><?= e($name) ?></div>
          <div class="text-xs text-slate-500"><?= e($tag) ?></div>
        </a>
        <?php endforeach; ?>
      </div>
    </div>
  </section>


  <!-- FAQ -->
  <section id="faq" class="lp-section">
    <div class="lp-wrap" style="max-width:780px">
      <div class="lp-head">
        <h2>Frequently asked questions</h2>
        <p>Answers based on the official ZoeFeeds Terms &amp; Conditions</p>
      </div>
      <?php foreach ($faqs as $faq): ?>
      <div class="faq-item">
        <button type="button" class="faq-q" aria-expanded="false" onclick="var i=this.closest('.faq-item');i.classList.toggle('faq-open');this.setAttribute('aria-expanded',i.classList.contains('faq-open'))">
          <span><?= $faq[0] ?></span>
          <svg class="faq-arr w-5 h-5 text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
        </button>
        <div class="faq-body"><div><div class="faq-ans"><?= $faq[1] ?></div></div></div>
      </div>
      <?php endforeach; ?>
      <div class="text-center mt-8"><a href="<?= APP_URL ?>/user/terms.php" class="text-orange-400 hover:underline text-sm font-medium">Read the full Terms &amp; Conditions →</a></div>
    </div>
  </section>


  <!-- WARNING BANNER -->
  <section style="padding:40px 0;background:rgba(239,68,68,.06);border-top:1px solid rgba(239,68,68,.2);border-bottom:1px solid rgba(239,68,68,.2)">
    <div class="lp-wrap text-center" style="max-width:820px">
      <div class="w-12 h-12 mx-auto mb-3 rounded-full bg-red-500/15 text-red-400 flex items-center justify-center"><?= ico('alert', 'w-6 h-6') ?></div>
      <h3 class="text-xl font-bold text-red-400 mb-2">Official warning</h3>
      <p class="text-slate-300 text-sm leading-relaxed">
        <strong>ZoeFeeds does not authorize the sale of reward tickets.</strong> Never pay any individual claiming to sell ZoeFeeds reward tickets or guarantee winning entries. All official information is communicated solely through ZoeFeeds' official channels at <strong class="text-white">www.zoefeeds.com</strong>.
      </p>
    </div>
  </section>


  <!-- CTA -->
  <section class="lp-section" style="position:relative;overflow:hidden">
    <div class="absolute inset-0 pointer-events-none" style="background:radial-gradient(ellipse at center,rgba(249,115,22,.12) 0%,transparent 70%)"></div>
    <div class="lp-wrap text-center" style="position:relative;max-width:760px">
      <div class="text-5xl mb-4">📶</div>
      <h2 style="font-size:clamp(1.8rem,6vw,3rem);font-weight:800;letter-spacing:-.02em;line-height:1.12;margin-bottom:14px">Ready to buy &amp; start winning?</h2>
      <p class="text-slate-400 mb-8 mx-auto" style="max-width:560px;font-size:clamp(.95rem,2.4vw,1.1rem);line-height:1.65">Join thousands buying affordable, long-lasting airtime and data on ZoeFeeds — and getting free reward tickets to win in our fair, transparent draws. Create your free account today.</p>
      <div class="flex flex-wrap gap-3 justify-center">
        <a href="<?= APP_URL ?>/user/register.php" class="lp-btn lp-btn-primary lp-btn-lg">📶 Create free account</a>
        <a href="<?= APP_URL ?>/user/login.php" class="lp-btn lp-btn-ghost lp-btn-lg">🔑 Log in</a>
      </div>
      <div class="text-xs text-slate-600 mt-4">No credit card required · Free forever · Instant setup · Age 18+</div>
    </div>
  </section>


  <?php require __DIR__ . '/components/footer.php'; ?>

  <script>window.APP_URL = '<?= APP_URL ?>';</script>
  <script src="<?= APP_URL ?>/assets/js/app.js"></script>
  <script>
    // Smooth scroll for in-page links
    document.querySelectorAll('a[href^="#"]').forEach(a => {
      a.addEventListener('click', e => {
        const id = a.getAttribute('href').slice(1);
        const el = id && document.getElementById(id);
        if (el) { e.preventDefault(); el.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
      });
    });
    // Close mobile menu when clicking outside of it
    document.addEventListener('click', (e) => {
      const menu = document.getElementById('mob-menu');
      const btn  = document.getElementById('mob-menu-btn');
      if (!menu || menu.classList.contains('hidden')) return;
      if (!menu.contains(e.target) && !btn.contains(e.target)) menu.classList.add('hidden');
    });
    // Fade-in on scroll (skipped when the browser lacks IntersectionObserver)
    if ('IntersectionObserver' in window) {
      const obs = new IntersectionObserver(entries => {
        entries.forEach(en => {
          if (en.isIntersecting) { en.target.style.opacity = '1'; en.target.style.transform = 'translateY(0)'; obs.unobserve(en.target); }
        });
      }, { threshold: 0.08 });
      document.querySelectorAll('.lp-draw, .lp-card, .winner-card').forEach(el => {
        el.style.cssText += ';opacity:0;transform:translateY(18px);transition:opacity .5s ease,transform .5s ease';
        obs.observe(el);
      });
    }
    // Live stats polling — refreshes every 30 seconds from a lightweight endpoint
    (function pollStats() {
      const setNum = (id, v) => { const el = document.getElementById(id); if (el && v !== undefined) el.textContent = Number(v).toLocaleString(); };
      fetch('<?= APP_URL ?>/api/stats.php')
        .then(r => r.json())
        .then(data => { setNum('stat-winners', data.winners); setNum('stat-draws', data.draws); })
        .catch(() => {})
        .finally(() => setTimeout(pollStats, 30000));
    })();
  </script>
</body>
</html>
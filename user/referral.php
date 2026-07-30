<?php
// user/referral.php
// Referral dashboard: user's shareable code/link, and a table of everyone
// they've referred (name, date joined, reward status). Free codes are
// granted automatically the moment a referred user redeems their first
// code — see includes/referral.php::maybeGrantReferralReward(), which is
// called from the redemption flow. Every reward is logged to audit_logs.
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/referral.php';
startAppSession();
requireUser(); // redirects to login if not authenticated — matches other user/* pages

$db     = getDB();
$userId = (int)$_SESSION['user_id'];

$data          = getReferralDashboardData($db, $userId);
$referralCode  = $data['referral_code'];
$referredList  = $data['referred'];
$totalReferred = $data['total_referred'];
$totalRewards  = $data['total_rewards'];
$referralLink  = APP_URL . '/user/register.php?ref=' . urlencode($referralCode);

$currentPage = 'referral';
$pageTitle   = 'Refer & Earn';
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= $pageTitle ?> — <?= APP_NAME ?></title>
  <script src="<?= APP_URL ?>/assets/js/tailwind.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
  <style>
    * { font-family: 'Poppins', sans-serif !important; }
    code { font-family: 'Courier New', monospace !important; }

    .ref-code-box {
      font-family: 'Courier New', monospace;
      font-size: 1.5rem; font-weight: 900; letter-spacing: .1em;
      color: #f97316;
    }
    .copy-btn {
      transition: background .15s, transform .1s;
    }
    .copy-btn:active { transform: scale(.96); }
    .copy-btn.copied {
      background: rgba(34,197,94,.15) !important;
      border-color: rgba(34,197,94,.4) !important;
      color: #22c55e !important;
    }
    .status-pill {
      display: inline-flex; align-items: center; gap: 4px;
      font-size: .68rem; font-weight: 700; padding: 3px 9px; border-radius: 999px;
    }
    .status-pending { background: rgba(107,114,128,.12); color: #9ca3af; border: 1px solid rgba(107,114,128,.25); }
    .status-granted { background: rgba(34,197,94,.12); color: #22c55e; border: 1px solid rgba(34,197,94,.3); }
  </style>
</head>
<body class="bg-[#0a0f1a] text-white">
<?php include __DIR__ . '/../components/user-sidebar.php'; ?>

<div class="main-content">
  <div class="topbar">
    <button onclick="toggleSidebar()" class="md:hidden text-gray-400 text-2xl mr-3">☰</button>
    <div>
      <h1 class="text-lg font-bold mt-0.5">🎁 Refer &amp; Earn</h1>
    </div>
  </div>

  <div class="p-4 md:p-6 pb-24 md:pb-6 max-w-3xl mx-auto">

    <!-- ── HOW IT WORKS ─────────────────────────────────────── -->
    <div class="rounded-xl p-4 mb-5 flex items-start gap-3"
         style="background:rgba(249,115,22,.06);border:1px solid rgba(249,115,22,.15)">
      <span class="text-2xl flex-shrink-0">💡</span>
      <div class="text-sm text-gray-300 leading-relaxed">
        Share your referral code or link below. When someone signs up with it and
        redeems <strong class="text-white">their first raffle code</strong>, you automatically
        get <strong class="text-orange-400">1 free raffle code</strong> added to your wallet.
      </div>
    </div>

    <!-- ── STATS ─────────────────────────────────────────────── -->
    <div class="grid grid-cols-2 gap-3 mb-5">
      <div class="card p-4 text-center">
        <div class="text-2xl font-black text-white"><?= number_format($totalReferred) ?></div>
        <div class="text-xs text-gray-500 mt-1">People Referred</div>
      </div>
      <div class="card p-4 text-center">
        <div class="text-2xl font-black text-orange-400"><?= number_format($totalRewards) ?></div>
        <div class="text-xs text-gray-500 mt-1">Free Codes Earned</div>
      </div>
    </div>

    <!-- ── YOUR CODE / LINK ─────────────────────────────────── -->
    <div class="card p-5 mb-5 text-center">
      <div class="text-xs text-gray-500 font-semibold uppercase tracking-wide mb-2">Your Referral Code</div>
      <div class="ref-code-box mb-4"><?= e($referralCode) ?></div>

      <button type="button" onclick="copyToClipboard('<?= e($referralCode) ?>', this)"
              class="copy-btn btn btn-secondary w-full py-2.5 text-sm font-semibold mb-2 flex items-center justify-center gap-2">
        📋 Copy Code
      </button>

      <div class="flex items-center gap-2 mt-3 bg-white/5 border border-white/10 rounded-lg px-3 py-2">
        <input type="text" readonly value="<?= e($referralLink) ?>" id="ref-link-input"
               class="bg-transparent flex-1 text-xs text-gray-400 outline-none truncate">
        <button type="button" onclick="copyToClipboard(document.getElementById('ref-link-input').value, this)"
                class="copy-btn text-xs px-3 py-1.5 rounded-md border border-white/10 text-gray-300 hover:text-white flex-shrink-0">
          Copy Link
        </button>
      </div>
    </div>

    <!-- ── PEOPLE YOU REFERRED ──────────────────────────────── -->
    <div class="card mb-5">
      <div class="px-5 pt-5 pb-3 border-b border-white/5">
        <h3 class="font-bold text-sm">👥 People You Referred</h3>
        <div class="text-xs text-gray-500 mt-0.5">
          <?= number_format($totalReferred) ?> total ·
          reward is granted once they redeem their first code
        </div>
      </div>

      <?php if (empty($referredList)): ?>
      <div class="p-10 text-center text-gray-500 text-sm">
        <div class="text-3xl mb-2">🔗</div>
        No referrals yet — share your code above to start earning free codes!
      </div>
      <?php else: ?>
      <div class="overflow-x-auto">
        <table class="w-full min-w-[420px]">
          <thead>
            <tr style="background:rgba(255,255,255,.02)">
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Name</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Joined</th>
              <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Reward</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-white/5">
            <?php foreach ($referredList as $r): ?>
            <tr>
              <td class="px-4 py-3 text-sm font-semibold text-white"><?= e($r['full_name']) ?></td>
              <td class="px-4 py-3 text-sm text-gray-400"><?= date('M j, Y', strtotime($r['joined_at'])) ?></td>
              <td class="px-4 py-3 text-right">
                <?php if ($r['status'] === 'granted'): ?>
                <span class="status-pill status-granted">✓ Earned <?= $r['granted_at'] ? date('M j', strtotime($r['granted_at'])) : '' ?></span>
                <?php else: ?>
                <span class="status-pill status-pending">⏳ Awaiting first redemption</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>

  </div>
</div>

<script src="<?= APP_URL ?>/assets/js/app.js"></script>
<script>
function copyToClipboard(text, btnEl) {
  const done = () => {
    const original = btnEl.innerHTML;
    btnEl.classList.add('copied');
    btnEl.innerHTML = btnEl.innerHTML.includes('Copy Code') || btnEl.textContent.trim() === '📋 Copy Code'
      ? '✅ Copied!' : '✅ Copied';
    setTimeout(() => { btnEl.classList.remove('copied'); btnEl.innerHTML = original; }, 1600);
  };

  if (navigator.clipboard && window.isSecureContext) {
    navigator.clipboard.writeText(text).then(done).catch(() => fallbackCopy(text, done));
  } else {
    fallbackCopy(text, done);
  }
}

function fallbackCopy(text, cb) {
  const ta = document.createElement('textarea');
  ta.value = text;
  ta.style.position = 'fixed';
  ta.style.opacity = '0';
  document.body.appendChild(ta);
  ta.select();
  try { document.execCommand('copy'); } catch (e) {}
  document.body.removeChild(ta);
  cb();
}
</script>
</body>
</html>

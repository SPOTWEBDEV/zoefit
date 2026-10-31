<?php
// user/dashboard.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../lib/paystack.php';
require_once __DIR__ . '/../lib/draw_trigger.php';   // drawHasDeadline()
$auth = requireUser();
$userId = $auth['id'];
$db = getDB();

// Fetch user data
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

$walletBalance = (int) ($user['wallet_balance'] ?? 0);
$firstName = trim(explode(' ', trim((string)$user['full_name']))[0] ?? '') ?: 'there';

// Stats
$totalCodes   = $db->prepare("SELECT COUNT(*) FROM codes WHERE current_owner = ? AND status NOT IN ('used')");
$totalCodes->execute([$userId]); $totalCodes = $totalCodes->fetchColumn();

$totalnotUsedCodes = $db->prepare("SELECT COUNT(*) FROM codes WHERE current_owner = ? AND status = 'redeemed'");
$totalnotUsedCodes->execute([$userId]);
$totalnotUsedCodes = $totalnotUsedCodes->fetchColumn();

$usedInDraws  = $db->prepare("SELECT COUNT(*) FROM draw_entries WHERE user_id = ?");
$usedInDraws->execute([$userId]); $usedInDraws = $usedInDraws->fetchColumn();

$totalWins    = $db->prepare("SELECT COUNT(*) FROM draw_winners WHERE user_id = ?");
$totalWins->execute([$userId]); $totalWins = $totalWins->fetchColumn();

$recentDeposits = $db->prepare("SELECT * FROM deposits WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
$recentDeposits->execute([$userId]);
$recentDeposits = $recentDeposits->fetchAll();

// Active draws (with entry counts, so entry-triggered draws can show progress)
$draws = $db->prepare(
    "SELECT d.*,
            (SELECT COUNT(*) FROM draw_entries WHERE draw_id=d.id)               AS total_entries,
            (SELECT COUNT(*) FROM draw_entries WHERE draw_id=d.id AND user_id=?) AS my_entries
     FROM draws d
     WHERE d.status = 'active'"
);
$draws->execute([$userId]); $draws = $draws->fetchAll();

// Draws with a real deadline first (soonest ending first), then entry draws with no deadline (oldest start first)
usort($draws, function ($a, $b) {
    $da = drawHasDeadline($a['end_date']); $db_ = drawHasDeadline($b['end_date']);
    if ($da !== $db_) return $da ? -1 : 1;
    return $da ? strtotime($a['end_date']) <=> strtotime($b['end_date'])
               : strtotime($a['start_date']) <=> strtotime($b['start_date']);
});
$draws = array_slice($draws, 0, 4);

// Recent transactions
$txns = $db->prepare("SELECT t.*, c.code FROM transactions t LEFT JOIN codes c ON t.code_id = c.id WHERE t.user_id = ? ORDER BY t.created_at DESC LIMIT 6");
$txns->execute([$userId]); $txns = $txns->fetchAll();

$currentPage = 'dashboard';
$pageTitle = 'Dashboard';

$flashType = $_SESSION['flash_type'] ?? null;
$flashMsg  = $_SESSION['flash_msg'] ?? null;
unset($_SESSION['flash_type'], $_SESSION['flash_msg']);
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="csrf-token" content="<?= generateCsrf() ?>">
  <title><?= e($pageTitle) ?> — <?= APP_NAME ?></title>
  <script src="<?= APP_URL ?>/assets/js/tailwind.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
  <style>
    .hero-wallet  { background: linear-gradient(135deg, rgba(15,118,110,.55), rgba(10,61,58,.55)); border: 1px solid rgba(45,212,191,.22); }
    .hero-tickets { background: linear-gradient(135deg, rgba(249,115,22,.30), rgba(124,45,18,.45)); border: 1px solid rgba(251,146,60,.28); }
    .hero-btn { display:inline-flex; align-items:center; justify-content:center; gap:6px; flex:1; padding:9px 14px; border-radius:10px; font-size:13px; font-weight:500; color:#fff; background:rgba(255,255,255,.14); transition:background .15s; text-decoration:none; }
    .hero-btn:hover { background:rgba(255,255,255,.24); }
    .tabular { font-variant-numeric: tabular-nums; }
  </style>
</head>
<body class="bg-[#0a0f1a] text-white antialiased">
<?php include __DIR__ . '/../components/saas-ui.php'; ?>
<?php include __DIR__ . '/../components/user-sidebar.php'; ?>

<div class="main-content">
  <!-- Topbar -->
  <div class="topbar">
    <button onclick="toggleSidebar()" class="md:hidden text-gray-400 hover:text-white text-2xl mr-3" aria-label="Menu">☰</button>
    <div class="min-w-0">
      <div class="font-semibold leading-tight truncate"><?= e($user['full_name']) ?></div>
      <div class="text-xs text-slate-400"><?= e(formatPhone($user['phone'])) ?></div>
    </div>
    <div class="flex items-center gap-3 ml-auto">
      <a href="<?= APP_URL ?>/user/notifications.php" class="relative p-2 rounded-lg hover:bg-white/5" aria-label="Notifications">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
        <span id="notif-badge" class="absolute top-1 right-1 w-4 h-4 bg-orange-500 rounded-full text-[10px] flex items-center justify-center" style="display:none"></span>
      </a>
    </div>
  </div>

  <div class="p-4 md:p-8 max-w-6xl mx-auto space-y-6">

    <?php if ($flashMsg): ?>
    <div class="s-alert <?= $flashType === 'success' ? 's-alert-success' : 's-alert-error' ?>" role="status">
      <?= ico($flashType === 'success' ? 'check-circle' : 'alert', 'w-5 h-5 flex-shrink-0 mt-px') ?><div><?= e($flashMsg) ?></div>
    </div>
    <?php endif; ?>

    <!-- Greeting -->
    <div>
      <h1 class="text-xl md:text-2xl font-semibold">Welcome back, <?= e($firstName) ?></h1>
      <p class="text-sm text-slate-400 mt-1">Here's a quick look at your wallet, tickets and live draws.</p>
    </div>

    <!-- Balances -->
    <div class="grid md:grid-cols-2 gap-4">
      <!-- Wallet -->
      <div class="hero-wallet rounded-2xl p-5 md:p-6 fade-in">
        <div class="flex items-start justify-between gap-3">
          <div>
            <div class="text-sm text-emerald-200 font-medium">Wallet balance</div>
            <div class="text-3xl md:text-4xl font-bold mt-2 tabular"><?= formatNaira($walletBalance) ?></div>
            <div class="text-xs text-emerald-200/80 mt-1.5">Available for airtime &amp; data purchase</div>
          </div>
          <div class="w-11 h-11 rounded-xl bg-white/10 flex items-center justify-center flex-shrink-0">
            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V6m0 12v-2m9-4a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
          </div>
        </div>
        <div class="flex gap-2.5 mt-5">
          <a href="<?= APP_URL ?>/user/deposit.php" class="hero-btn"><?= ico('plus') ?> Deposit</a>
          <a href="<?= APP_URL ?>/user/wallet-history.php" class="hero-btn">Wallet history</a>
        </div>
      </div>

      <!-- Reward tickets -->
      <div class="hero-tickets rounded-2xl p-5 md:p-6 fade-in">
        <div class="flex items-start justify-between gap-3">
          <div>
            <div class="text-sm text-orange-200 font-medium">Eligibility balance</div>
            <div class="flex items-center gap-3 mt-2">
              <div class="text-3xl md:text-4xl font-bold tabular" id="balance-value" data-value="<?= $totalnotUsedCodes ?>" data-hidden="0"><?= $totalnotUsedCodes ?></div>
              <button id="balance-toggle" onclick="toggleBalance()" class="text-xl leading-none" aria-label="Show or hide balance">👁</button>
            </div>
            <div class="text-xs text-orange-200/80 mt-1.5">Active reward tickets</div>
          </div>
          <div class="w-11 h-11 rounded-xl bg-white/10 flex items-center justify-center flex-shrink-0">
            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/></svg>
          </div>
        </div>
        <div class="flex gap-2.5 mt-5">
          <a href="<?= APP_URL ?>/user/redeem.php" class="hero-btn"><?= ico('plus') ?> Redeem ticket</a>
          <a href="<?= APP_URL ?>/user/transfer.php" class="hero-btn">Transfer ticket</a>
        </div>
      </div>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-3 gap-3 md:gap-4">
      <div class="s-card p-4 md:p-5">
        <div class="s-stat-icon bg-orange-500/10 text-orange-400 mb-3"><?= ico('layers', 'w-[18px] h-[18px]') ?></div>
        <div class="text-2xl font-semibold tabular"><?= $totalCodes ?></div>
        <div class="s-eyebrow mt-0.5">Total tickets</div>
      </div>
      <div class="s-card p-4 md:p-5">
        <div class="s-stat-icon bg-blue-500/10 text-blue-400 mb-3"><?= ico('target', 'w-[18px] h-[18px]') ?></div>
        <div class="text-2xl font-semibold tabular"><?= $usedInDraws ?></div>
        <div class="s-eyebrow mt-0.5">Tickets in draws</div>
      </div>
      <div class="s-card p-4 md:p-5">
        <div class="s-stat-icon bg-green-500/10 text-green-400 mb-3"><?= ico('trophy', 'w-[18px] h-[18px]') ?></div>
        <div class="text-2xl font-semibold tabular"><?= $totalWins ?></div>
        <div class="s-eyebrow mt-0.5">Wins</div>
      </div>
    </div>

    <!-- Active Draws -->
    <?php if ($draws): ?>
    <section>
      <div class="flex items-center justify-between mb-3">
        <h2 class="text-base font-semibold">Active draws</h2>
        <a href="<?= APP_URL ?>/user/draws.php" class="text-sm text-orange-400 hover:underline">View all →</a>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <?php foreach ($draws as $draw):
          $isEntries   = ($draw['draw_type'] ?? 'timed') === 'entries';
          $hasDeadline = drawHasDeadline($draw['end_date']);     // entry draws without a deadline store a placeholder date: never show it
          $target      = (int)($draw['target_entries'] ?? 0);
          $total       = (int)$draw['total_entries'];
          $mine        = (int)$draw['my_entries'];
          $pct         = ($isEntries && $target > 0) ? min(100, (int)floor($total / $target * 100)) : 0;
          $reached     = $isEntries && $target > 0 && $total >= $target;
          $left        = max(0, $target - $total);
          $enterUrl    = APP_URL . '/user/enter-draw.php?id=' . $draw['id'];
        ?>
        <article class="s-card s-card-hover overflow-hidden flex flex-col fade-in">
          <a href="<?= $enterUrl ?>" class="block h-32 flex items-center justify-center text-slate-600" style="background:linear-gradient(135deg,#1a2235,#0a0f1a)">
            <?php if ($draw['banner_image'] && file_exists(UPLOAD_PATH . $draw['banner_image'])): ?>
              <img src="<?= APP_URL ?>/uploads/<?= e($draw['banner_image']) ?>" alt="" class="w-full h-full object-cover">
            <?php else: ?><?= ico($isEntries ? 'target' : 'trophy', 'w-10 h-10') ?><?php endif; ?>
          </a>
          <div class="p-4 flex flex-col flex-1">
            <div class="flex gap-1.5 flex-wrap mb-2.5">
              <span class="s-pill s-pill-green dot">Live</span>
              <?php if ($isEntries): ?><span class="s-pill s-pill-purple"><?= ico('target', 'w-3 h-3') ?> Entry-triggered</span><?php endif; ?>
              <?php if ($mine > 0): ?><span class="s-pill s-pill-blue"><?= ico('check', 'w-3 h-3') ?> Entered (<?= $mine ?>)</span><?php endif; ?>
            </div>
            <h3 class="font-semibold text-[15px] leading-snug mb-3"><a href="<?= $enterUrl ?>" class="hover:text-orange-300 transition"><?= e($draw['title']) ?></a></h3>

            <div class="flex-1 space-y-3 mb-4">
              <?php if ($hasDeadline): ?>
              <!-- Countdown only for draws with a real deadline -->
              <div class="flex items-center gap-1" data-countdown="<?= e($draw['end_date']) ?>"></div>
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

            <a href="<?= $enterUrl ?>" class="s-btn s-btn-primary w-full py-2.5">Enter draw →</a>
          </div>
        </article>
        <?php endforeach; ?>
      </div>
    </section>
    <?php endif; ?>

    <!-- Deposits + Activity -->
    <div class="grid lg:grid-cols-2 gap-5 items-start">

      <!-- Recent Deposits -->
      <section>
        <div class="flex items-center justify-between mb-3">
          <h2 class="text-base font-semibold">Recent deposits</h2>
          <a href="<?= APP_URL ?>/user/deposit.php" class="text-sm text-orange-400 hover:underline">Deposit more →</a>
        </div>
        <div class="s-card overflow-hidden">
          <?php if ($recentDeposits): ?>
            <div class="divide-y divide-white/5">
            <?php foreach ($recentDeposits as $d):
              $pill = match($d['status']) { 'success' => 's-pill-green', 'pending' => 's-pill-yellow', default => 's-pill-red' };
            ?>
            <div class="flex items-center gap-3 px-4 py-3.5">
              <div class="w-10 h-10 rounded-xl bg-emerald-500/10 text-emerald-400 flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
              </div>
              <div class="flex-1 min-w-0">
                <div class="text-sm font-medium tabular"><?= formatNaira($d['amount']) ?></div>
                <div class="text-xs text-slate-500 font-mono truncate"><?= e($d['reference']) ?></div>
              </div>
              <div class="text-right flex-shrink-0">
                <span class="s-pill dot <?= $pill ?>"><?= e(ucfirst($d['status'])) ?></span>
                <div class="text-xs text-slate-500 mt-1"><?= date('M j, g:ia', strtotime($d['created_at'])) ?></div>
              </div>
            </div>
            <?php endforeach; ?>
            </div>
          <?php else: ?>
            <div class="p-8 text-center">
              <div class="w-11 h-11 mx-auto mb-3 rounded-full bg-white/5 flex items-center justify-center text-slate-400"><?= ico('inbox', 'w-5 h-5') ?></div>
              <div class="text-sm text-slate-400">No deposits yet — <a href="<?= APP_URL ?>/user/deposit.php" class="text-orange-400 hover:underline">fund your wallet</a></div>
            </div>
          <?php endif; ?>
        </div>
      </section>

      <!-- Recent Transactions -->
      <section>
        <div class="flex items-center justify-between mb-3">
          <h2 class="text-base font-semibold">Recent activity</h2>
          <a href="<?= APP_URL ?>/user/transactions.php" class="text-sm text-orange-400 hover:underline">View all →</a>
        </div>
        <div class="s-card overflow-hidden">
          <?php if ($txns): ?>
            <div class="divide-y divide-white/5">
            <?php foreach ($txns as $t): $credit = $t['type'] === 'credit'; ?>
            <div class="flex items-center gap-3 px-4 py-3.5">
              <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0 <?= $credit ? 'bg-green-500/10 text-green-400' : 'bg-red-500/10 text-red-400' ?>">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><?= $credit ? '<path d="M12 5v14M19 12l-7 7-7-7"/>' : '<path d="M12 19V5M5 12l7-7 7 7"/>' ?></svg>
              </div>
              <div class="flex-1 min-w-0">
                <div class="text-sm font-medium truncate"><?= e(ucfirst(str_replace('_',' ',$t['category']))) ?></div>
                <?php if ($t['code']): ?><div class="text-xs text-slate-500 font-mono truncate"><?= e($t['code']) ?></div><?php endif; ?>
              </div>
              <div class="text-right flex-shrink-0">
                <div class="text-sm font-semibold tabular <?= $credit ? 'text-green-400' : 'text-red-400' ?>">
                  <?= $credit ? '+' : '-' ?><?= abs($t['amount']) ?> ticket<?= abs($t['amount'])>1 ? 's' : '' ?>
                </div>
                <div class="text-xs text-slate-500 mt-0.5"><?= date('M j, g:ia', strtotime($t['created_at'])) ?></div>
              </div>
            </div>
            <?php endforeach; ?>
            </div>
          <?php else: ?>
            <div class="p-8 text-center">
              <div class="w-11 h-11 mx-auto mb-3 rounded-full bg-white/5 flex items-center justify-center text-slate-400"><?= ico('inbox', 'w-5 h-5') ?></div>
              <div class="text-sm text-slate-400">No transactions yet</div>
            </div>
          <?php endif; ?>
        </div>
      </section>

    </div>
  </div>
</div>

<script>window.APP_URL = '<?= APP_URL ?>';</script>
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
</body></html>
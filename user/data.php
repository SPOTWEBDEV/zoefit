<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../lib/paystack.php';
require_once __DIR__ . '/../lib/vtu_provider.php';
require_once __DIR__ . '/../lib/rewards.php';

$auth   = requireUser();
$userId = $auth['id'];
$db     = getDB();

$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

$walletBalance = (int) ($user['wallet_balance'] ?? 0);

$networks = ['mtn' => 'MTN', 'glo' => 'Glo', 'airtel' => 'Airtel', '9mobile' => '9mobile'];
$networkColors = ['mtn' => '#FFCC00', 'glo' => '#2FB34A', 'airtel' => '#E4002B', '9mobile' => '#8DC63F'];
$allPlans = getDataPlans();

$errors  = [];
$success = null;
$notice  = null;
$phoneInput   = isPost() ? '' : trim($_GET['phone'] ?? '');   // survives switching network tabs
$networkInput = $_POST['network'] ?? $_GET['network'] ?? 'mtn';
$planInput    = '';

if (!isset($networks[$networkInput])) $networkInput = 'mtn';

if (isPost()) {
    if (!verifyCsrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $errors[] = 'Your session expired, please try again.';
    } else {
        $phoneInput = trim($_POST['phone'] ?? '');
        $planInput  = $_POST['plan_code'] ?? '';

        if (!isset($networks[$networkInput])) {
            $errors[] = 'Please select a valid network.';
        }
        $normalizedPhone = normalizePhone($phoneInput);
        if (!preg_match('/^234\d{10}$/', $normalizedPhone)) {
            $errors[] = 'Please enter a valid Nigerian phone number.';
        }

        $plan = getDataPlanByCode($networkInput, $planInput);
        if (!$plan) {
            $errors[] = 'Please select a valid data plan.';
        } elseif ((int) ($_POST['expected_price'] ?? 0) !== (int) $plan['price']) {
            // The price on the user's screen is not the current price (admin changed profit,
            // or the provider changed the plan price). Never charge more than they were shown.
            $errors[] = 'The price of this bundle has just changed to ' . formatNaira($plan['price']) . '. Please review and try again.';
        }

        if (!$errors) {
            $amountKobo = (int) $plan['price'];

            if ($amountKobo > $walletBalance) {
                $errors[] = 'Insufficient wallet balance. Please deposit more funds.';
            } else {
                $reference = 'ZF-DATA-' . $userId . '-' . time() . '-' . strtoupper(bin2hex(random_bytes(3)));

                try {
                    $db->beginTransaction();

                    $lock = $db->prepare("SELECT wallet_balance FROM users WHERE id = ? FOR UPDATE");
                    $lock->execute([$userId]);
                    $lockedBalance = (int) $lock->fetchColumn();

                    if ($amountKobo > $lockedBalance) {
                        $db->rollBack();
                        $errors[] = 'Insufficient wallet balance. Please deposit more funds.';
                    } else {
                        $db->prepare("UPDATE users SET wallet_balance = wallet_balance - ? WHERE id = ?")
                           ->execute([$amountKobo, $userId]);

                        $newBalance = $lockedBalance - $amountKobo;

                        $orderStmt = $db->prepare("INSERT INTO orders (user_id, type, network, phone, plan_code, plan_name, amount, cost_amount, profit_amount, reference, status)
                            VALUES (?,?,?,?,?,?,?,?,?,?,'pending')");
                        $orderStmt->execute([$userId, 'data', $networkInput, $normalizedPhone, $plan['code'], $plan['name'],
                                             $amountKobo, (int) $plan['cost'], $amountKobo - (int) $plan['cost'], $reference]);
                        $orderId = (int) $db->lastInsertId();

                        $db->prepare("INSERT INTO wallet_transactions
                            (user_id, type, category, amount, balance_after, reference_type, reference_id, description)
                            VALUES (?,?,?,?,?,?,?,?)")
                           ->execute([$userId, 'debit', 'data_purchase', $amountKobo, $newBalance, 'order', $orderId,
                                      "Data purchase - {$networks[$networkInput]} {$plan['name']} - " . formatPhone($normalizedPhone)]);

                        $db->commit();
                        $walletBalance = $newBalance;

                        $result = dispatchDataOrder($networkInput, $normalizedPhone, $plan['code'], $amountKobo, $reference);

                        if ($result['success']) {
                            $db->prepare("UPDATE orders SET status='success', provider=?, provider_reference=?, provider_response=? WHERE id=?")
                               ->execute([$result['provider'], $result['provider_reference'], $result['raw'], $orderId]);

                            createNotification($userId, 'Data Purchase Successful', $plan['name'] . ' sent to ' . formatPhone($normalizedPhone) . '.', 'success');
                            auditLog('user', $userId, 'data_purchase_success', "Data {$plan['code']} to {$normalizedPhone}, ref {$reference}", 'order', $orderId);

                            $rewardCodes = grantPurchaseReward($db, $userId, $orderId, $amountKobo);

                            $success = 'Data purchase successful! ' . $plan['name'] . ' sent to ' . formatPhone($normalizedPhone) . '.'
                                     . ($rewardCodes ? " 🎟️ You earned {$rewardCodes} free raffle code" . ($rewardCodes > 1 ? 's' : '') . '!' : '');
                            $planInput = '';

                        } elseif (!empty($result['pending'])) {
                            // OUTCOME UNKNOWN (timeout / provider 5xx): may have been delivered.
                            // No refund, no retry. Leave 'pending' and reconcile manually.
                            $db->prepare("UPDATE orders SET provider=?, provider_response=? WHERE id=?")
                               ->execute([$result['provider'], $result['raw'], $orderId]);
                            auditLog('user', $userId, 'data_purchase_unconfirmed', "ref {$reference}", 'order', $orderId);
                            $notice = $result['message'] . " Reference: {$reference}";
                            $planInput = '';

                        } else {
                            $db->beginTransaction();
                            $db->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?")
                               ->execute([$amountKobo, $userId]);

                            $refundBal = $db->prepare("SELECT wallet_balance FROM users WHERE id = ?");
                            $refundBal->execute([$userId]);
                            $refundedBalance = (int) $refundBal->fetchColumn();

                            $db->prepare("INSERT INTO wallet_transactions
                                (user_id, type, category, amount, balance_after, reference_type, reference_id, description)
                                VALUES (?,?,?,?,?,?,?,?)")
                               ->execute([$userId, 'credit', 'refund', $amountKobo, $refundedBalance, 'order', $orderId,
                                          'Refund - data purchase failed']);

                            $db->prepare("UPDATE orders SET status='refunded', provider_response=? WHERE id=?")
                               ->execute([$result['message'], $orderId]);

                            $db->commit();
                            $walletBalance = $refundedBalance;

                            auditLog('user', $userId, 'data_purchase_failed', $result['message'] . " ref {$reference}", 'order', $orderId);
                            $errors[] = $result['message'] . ' Your wallet has been refunded.';
                        }
                    }
                } catch (\Exception $ex) {
                    if ($db->inTransaction()) $db->rollBack();
                    error_log('[DATA] ' . $ex->getMessage());
                    $errors[] = 'Something went wrong processing your order. Please check your order history before trying again.';
                }
            }
        }
    }
}


$rewardProgressKobo = getRewardProgressKobo($db, $userId);

// ── Plans for the page. Only what the UI needs: NEVER send provider cost or profit to the browser. ──
$plansForJs = [];
foreach ($allPlans as $net => $list) {
    foreach ($list as $p) {
        $meta = dataPlanMeta($p);
        $plansForJs[] = [
            'id'     => (string) $p['code'],
            'net'    => $net,
            'name'   => (string) $p['name'],
            'type'   => (string) ($p['type'] ?? ''),
            'price'  => (int) $p['price'],            // kobo, what the user pays
            'size'   => $meta['size'],
            'unit'   => $meta['unit'],
            'sizeGb' => $meta['sizeGb'],
            'vlabel' => $meta['vlabel'],
            'cat'    => $meta['cat'],
            'ppg'    => $meta['ppg'],
        ];
    }
}

// Recent numbers (a contact shortcut that works on every browser/device)
$recentPhones = [];
$rp = $db->prepare("SELECT phone FROM orders WHERE user_id = ? AND type IN ('data','airtime') ORDER BY created_at DESC LIMIT 40");
$rp->execute([$userId]);
foreach ($rp->fetchAll(PDO::FETCH_COLUMN) as $ph) {
    $local = preg_match('/^234(\d{10})$/', $ph, $m) ? '0' . $m[1] : $ph;
    if (!in_array($local, $recentPhones, true)) $recentPhones[] = $local;
    if (count($recentPhones) >= 5) break;
}

$initialPhone = preg_replace('/\D/', '', $phoneInput);
if (preg_match('/^234(\d{10})$/', $initialPhone, $m)) $initialPhone = '0' . $m[1];

$recent = $db->prepare("SELECT * FROM orders WHERE user_id = ? AND type = 'data' ORDER BY created_at DESC LIMIT 10");
$recent->execute([$userId]);
$recent = $recent->fetchAll();

$jsConfig = [
    'plans'      => $plansForJs,
    'networks'   => array_map(fn($k) => ['label' => $networks[$k], 'color' => $networkColors[$k]], array_combine(array_keys($networks), array_keys($networks))),
    'balance'    => $walletBalance,
    'rewardUnit' => rewardUnitKobo(),
    'progress'   => $rewardProgressKobo,
    'depositUrl' => APP_URL . '/user/deposit.php',
    'initial'    => ['network' => $networkInput, 'phone' => $initialPhone, 'plan' => $planInput, 'hadError' => (bool) $errors],
];

$currentPage = 'data';
$pageTitle   = 'Mobile Data';

/** Pull size / validity / category out of a provider plan name so the UI can draw tiles. */
function dataPlanMeta(array $p): array {
    $t = trim(($p['name'] ?? '') . ' ' . ($p['type'] ?? ''));

    $size = null; $unit = null; $sizeGb = null;
    if (preg_match('/(\d+(?:[.,]\d+)?)\s*(TB|GB|MB)(?![A-Za-z])/i', $t, $m)) {
        $size = (float) str_replace(',', '.', $m[1]);
        $unit = strtoupper($m[2]);
        $sizeGb = $unit === 'TB' ? $size * 1024 : ($unit === 'GB' ? $size : $size / 1024);
    }

    $days = null; $vlabel = '';
    $plural = fn(int $n, string $one, string $many) => $n . ' ' . ($n === 1 ? $one : $many);
    if (preg_match('/(\d+)\s*[- ]?\s*(?:hrs?|hours?)(?![A-Za-z])/i', $t, $m)) {
        $days = 1; $vlabel = $plural((int) $m[1], 'Hour', 'Hours');
    } elseif (preg_match('/(\d+)\s*[- ]?\s*days?(?![A-Za-z])/i', $t, $m)) {
        $days = (int) $m[1]; $vlabel = $plural($days, 'Day', 'Days');
    } elseif (preg_match('/(\d+)\s*d(?![A-Za-z])/i', $t, $m)) {          // "7d"
        $days = (int) $m[1]; $vlabel = $plural($days, 'Day', 'Days');
    } elseif (preg_match('/(\d+)\s*[- ]?\s*weeks?(?![A-Za-z])/i', $t, $m)) {
        $days = (int) $m[1] * 7; $vlabel = $plural((int) $m[1], 'Week', 'Weeks');
    } elseif (preg_match('/(\d+)\s*[- ]?\s*months?(?![A-Za-z])/i', $t, $m)) {
        $days = (int) $m[1] * 30; $vlabel = $plural((int) $m[1], 'Month', 'Months');
    } elseif (preg_match('/\bdaily\b/i', $t))   { $days = 1;   $vlabel = '1 Day'; }
      elseif (preg_match('/\bweekly\b/i', $t))  { $days = 7;   $vlabel = '7 Days'; }
      elseif (preg_match('/\bmonthly\b/i', $t)) { $days = 30;  $vlabel = '30 Days'; }
      elseif (preg_match('/\b(yearly|annual)\b/i', $t)) { $days = 365; $vlabel = '1 Year'; }

    if (preg_match('/night|midnight|weekend/i', $t))   $cat = 'night';
    elseif ($days === null)                            $cat = 'other';
    elseif ($days <= 2)                                $cat = 'daily';
    elseif ($days <= 14)                               $cat = 'weekly';
    elseif ($days <= 62)                               $cat = 'monthly';
    else                                               $cat = 'other';

    $ppg = ($sizeGb && $sizeGb > 0) ? round(((int) ($p['price'] ?? 0)) / 100 / $sizeGb, 2) : null;

    return ['size' => $size, 'unit' => $unit, 'sizeGb' => $sizeGb, 'days' => $days, 'vlabel' => $vlabel, 'cat' => $cat, 'ppg' => $ppg];
}
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="csrf-token" content="<?= generateCsrf() ?>">
  <title><?= e($pageTitle) ?> — <?= APP_NAME ?></title>
  <script src="<?= APP_URL ?>/assets/js/tailwind.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
  <style>
    /* ── Plan tiles ── */
    .plan-grid { display: grid; gap: .65rem; grid-template-columns: repeat(3, minmax(0, 1fr)); }
    .plan-grid[data-view="list"] { grid-template-columns: 1fr; gap: .5rem; }
    .tile {
      display: flex; flex-direction: column; align-items: center; justify-content: space-between; text-align: center;
      gap: .3rem; padding: .85rem .4rem .7rem; min-height: 8.6rem; width: 100%;
      background: rgba(255,255,255,.045); border: 1px solid rgba(255,255,255,.08); border-radius: 1rem;
      cursor: pointer; color: inherit; transition: transform .12s, border-color .15s, background .15s;
    }
    .tile:hover { background: rgba(255,255,255,.08); }
    .tile:active { transform: scale(.97); }
    .tile.selected { border-color: #fb923c; background: rgba(249,115,22,.12); box-shadow: 0 0 0 1px rgba(251,146,60,.4); }
    .tile .size { font-size: 1.35rem; font-weight: 800; line-height: 1.1; letter-spacing: -.02em; }
    .tile .size small { font-size: .7rem; font-weight: 700; margin-left: 1px; }
    .tile .nm { font-size: .78rem; font-weight: 600; line-height: 1.25; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
    .tile .vl { font-size: .8rem; color: #9ca3af; }
    .tile .pr { font-size: .8rem; color: #d1d5db; font-weight: 600; }
    .tile .rw { font-size: .7rem; font-weight: 600; color: #34d399; }
    .tile .tag { font-size: .62rem; font-weight: 600; color: #fbbf24; background: rgba(251,191,36,.12); border-radius: .4rem; padding: .1rem .45rem; }
    .plan-grid[data-view="list"] .tile { flex-direction: row; text-align: left; min-height: 0; padding: .8rem 1rem; gap: .75rem; }
    .plan-grid[data-view="list"] .tile .main { flex: 1; display: flex; align-items: baseline; gap: .6rem; flex-wrap: wrap; }
    .plan-grid[data-view="list"] .tile .side { text-align: right; }
    @media (max-width: 380px) { .tile .size { font-size: 1.15rem; } }

    /* ── Category tabs ── */
    .tabs { display: flex; gap: 1.4rem; overflow-x: auto; scrollbar-width: none; border-bottom: 1px solid rgba(255,255,255,.08); }
    .tabs::-webkit-scrollbar { display: none; }
    .tab { position: relative; flex: 0 0 auto; padding: .6rem 0 .7rem; font-size: .9rem; font-weight: 600; color: #9ca3af; background: none; border: 0; cursor: pointer; white-space: nowrap; }
    .tab.active { color: #fff; font-weight: 800; }
    .tab.active::after { content: ''; position: absolute; left: 0; bottom: -1px; width: 100%; max-width: 2rem; height: 3px; border-radius: 3px; background: #fb923c; }

    /* ── Confirm sheet ── */
    .sheet { position: fixed; inset: 0; z-index: 60; display: none; }
    .sheet.open { display: block; }
    .sheet-bg { position: absolute; inset: 0; background: rgba(0,0,0,.65); backdrop-filter: blur(2px); }
    .sheet-panel {
      position: absolute; left: 0; right: 0; bottom: 0; background: #111827; border-top: 1px solid rgba(255,255,255,.1);
      border-radius: 1.5rem 1.5rem 0 0; padding: 1.25rem 1.25rem calc(1.25rem + env(safe-area-inset-bottom));
      animation: sheetUp .22s ease-out; max-height: 92vh; overflow-y: auto;
    }
    @keyframes sheetUp { from { transform: translateY(40px); opacity: .4; } to { transform: none; opacity: 1; } }
    @media (min-width: 768px) {
      .sheet-panel { top: 50%; bottom: auto; left: 50%; right: auto; width: 440px; transform: translate(-50%, -50%); border-radius: 1.5rem; animation: none; border: 1px solid rgba(255,255,255,.1); }
    }
    @keyframes shake { 0%,100% { transform: translateX(0); } 25% { transform: translateX(-6px); } 75% { transform: translateX(6px); } }
    .shake { animation: shake .3s; }
  </style>
</head>
<body class="bg-[#0a0f1a] text-white font-sans">

<?php include __DIR__ . '/../components/user-sidebar.php'; ?>

<div class="main-content">
  <div class="topbar">
    <button onclick="toggleSidebar()" class="md:hidden text-gray-400 hover:text-white text-2xl mr-3">☰</button>
    <h1 class="text-lg font-bold">Mobile Data</h1>
    <a href="#history" class="ml-auto text-sm font-semibold text-orange-400 hover:text-orange-300">History</a>
  </div>

  <div class="p-4 md:p-6 max-w-xl mx-auto space-y-4">

    <!-- Alerts -->
    <?php if ($errors): ?>
      <div class="flex gap-3 rounded-2xl border border-red-500/30 bg-red-500/10 text-red-200 p-4 text-sm" role="alert">
        <svg class="w-5 h-5 flex-shrink-0 text-red-400 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.7 7.3a1 1 0 00-1.4 1.4L8.6 10l-1.3 1.3a1 1 0 101.4 1.4L10 11.4l1.3 1.3a1 1 0 001.4-1.4L11.4 10l1.3-1.3a1 1 0 00-1.4-1.4L10 8.6 8.7 7.3z" clip-rule="evenodd"/></svg>
        <div class="space-y-1"><?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?></div>
      </div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="flex gap-3 rounded-2xl border border-emerald-500/30 bg-emerald-500/10 text-emerald-200 p-4 text-sm" role="status">
        <svg class="w-5 h-5 flex-shrink-0 text-emerald-400 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.7-9.3a1 1 0 00-1.4-1.4L9 10.6 7.7 9.3a1 1 0 00-1.4 1.4l2 2a1 1 0 001.4 0l4-4z" clip-rule="evenodd"/></svg>
        <div><?= e($success) ?></div>
      </div>
    <?php endif; ?>
    <?php if ($notice): ?>
      <div class="flex gap-3 rounded-2xl border border-amber-500/30 bg-amber-500/10 text-amber-200 p-4 text-sm" role="status">
        <svg class="w-5 h-5 flex-shrink-0 text-amber-400 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.3 3.2a2 2 0 013.4 0l6 10.5A2 2 0 0116 16.7H4a2 2 0 01-1.7-3L8.3 3.2zM10 7a1 1 0 00-1 1v3a1 1 0 002 0V8a1 1 0 00-1-1zm0 7.5a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg>
        <div><?= e($notice) ?></div>
      </div>
    <?php endif; ?>

    <!-- Promo banner -->
    <div class="relative overflow-hidden rounded-2xl p-5 border border-orange-400/20" style="background:linear-gradient(120deg,#1c1209 0%,#2a1707 55%,#3a1d06 100%)">
      <div class="absolute -right-6 -top-6 w-36 h-36 rounded-full bg-orange-500/20 blur-2xl"></div>
      <div class="relative flex items-center justify-between gap-4">
        <div>
          <div class="text-lg font-extrabold leading-tight">Buy data.<br><span class="text-orange-400">Win big.</span></div>
          <div class="text-xs text-gray-300 mt-2">Every ₦<?= number_format(getRewardNairaPerCode()) ?> you spend = 1 free raffle code</div>
        </div>
        <div class="text-5xl flex-shrink-0">🎟️</div>
      </div>
    </div>

    <!-- Network + number -->
    <div class="card p-4">
      <div class="flex items-center gap-3">
        <div class="relative">
          <button type="button" id="net-btn" class="flex items-center gap-1.5" aria-label="Choose network" aria-haspopup="listbox">
            <span id="net-badge" class="w-11 h-11 rounded-full flex items-center justify-center text-[11px] font-extrabold text-black" style="background:#FFCC00">MTN</span>
            <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
          </button>
          <div id="net-menu" class="hidden absolute left-0 top-14 z-20 w-44 rounded-2xl border border-white/10 bg-[#111827] shadow-2xl p-1.5" role="listbox">
            <?php foreach ($networks as $key => $label): ?>
              <button type="button" data-net="<?= e($key) ?>" class="net-opt w-full flex items-center gap-3 px-3 py-2.5 rounded-xl hover:bg-white/10 text-sm font-medium text-left" role="option">
                <span class="w-3 h-3 rounded-full" style="background:<?= e($networkColors[$key]) ?>"></span><?= e($label) ?>
              </button>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="w-px h-8 bg-white/10"></div>
        <input type="tel" id="phone-display" inputmode="numeric" autocomplete="off" maxlength="13" placeholder="080 1234 5678"
               class="flex-1 min-w-0 bg-transparent text-xl font-bold tracking-wide placeholder:text-gray-600 focus:outline-none" aria-label="Phone number">
        <button type="button" id="contact-btn" title="Pick from contacts" aria-label="Pick from contacts"
                class="w-11 h-11 rounded-full bg-orange-500/15 hover:bg-orange-500/25 text-orange-400 flex items-center justify-center flex-shrink-0 transition">
          <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M12 12a4 4 0 100-8 4 4 0 000 8zm0 2c-3.3 0-8 1.7-8 5v1h16v-1c0-3.3-4.7-5-8-5z"/></svg>
        </button>
      </div>
      <div id="phone-hint" class="text-xs mt-3 min-h-[1rem] text-gray-500"></div>

      <?php if ($recentPhones): ?>
      <div class="mt-3 pt-3 border-t border-white/5">
        <div class="text-[11px] uppercase tracking-wider text-gray-500 mb-2">Recent numbers</div>
        <div class="flex flex-wrap gap-2">
          <?php foreach ($recentPhones as $rp): ?>
            <button type="button" data-phone="<?= e($rp) ?>" class="recent-phone px-3 py-1.5 rounded-full bg-white/5 hover:bg-white/10 border border-white/10 text-xs font-medium"><?= e(substr($rp, 0, 4) . ' ' . substr($rp, 4, 3) . ' ' . substr($rp, 7)) ?></button>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>

    <!-- Plans -->
    <div class="card p-4 md:p-5">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-xl font-extrabold">Data Plans</h2>
        <div class="flex items-center gap-1" role="group" aria-label="View">
          <button type="button" id="view-grid" class="w-9 h-9 rounded-lg flex items-center justify-center text-orange-400" aria-label="Grid view">
            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M4 4h4v4H4zM10 4h4v4h-4zM16 4h4v4h-4zM4 10h4v4H4zM10 10h4v4h-4zM16 10h4v4h-4zM4 16h4v4H4zM10 16h4v4h-4zM16 16h4v4h-4z"/></svg>
          </button>
          <button type="button" id="view-list" class="w-9 h-9 rounded-lg flex items-center justify-center text-gray-500" aria-label="List view">
            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24"><path d="M4 5h16v3H4zM4 10.5h16v3H4zM4 16h16v3H4z"/></svg>
          </button>
        </div>
      </div>

      <div id="tabs" class="tabs mb-4" role="tablist"></div>
      <div id="plan-grid" class="plan-grid" data-view="grid"></div>
      <div id="plan-empty" class="hidden rounded-xl border border-dashed border-white/10 p-8 text-center">
        <div class="text-2xl mb-2">📡</div>
        <div class="text-sm text-gray-300">No bundles available here right now</div>
        <div class="text-xs text-gray-500 mt-1">Try another network or check back in a few minutes.</div>
      </div>
    </div>

    <!-- History -->
    <div id="history" class="pt-2">
      <h2 class="text-lg font-bold mb-3">Recent data orders</h2>
      <div class="card overflow-hidden">
        <?php if ($recent): ?>
          <?php foreach ($recent as $o): ?>
          <?php
            $badgeClass = match($o['status']) {
              'success'   => 'badge-success',
              'pending', 'processing' => 'badge-warning',
              'refunded'  => 'badge-info',
              default     => 'badge-danger',
            };
          ?>
          <div class="flex items-center gap-3 px-4 py-3.5 border-b border-white/5 last:border-0">
            <div class="w-10 h-10 rounded-xl bg-blue-500/15 text-blue-400 flex items-center justify-center flex-shrink-0">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01M4.929 12.9a10.5 10.5 0 0114.142 0M1.5 9.75a15 15 0 0121 0"/></svg>
            </div>
            <div class="flex-1 min-w-0">
              <div class="text-sm font-medium truncate"><?= e(strtoupper($o['network'])) ?> · <?= e($o['plan_name']) ?></div>
              <div class="text-xs text-gray-500 truncate"><?= e(formatPhone($o['phone'])) ?> · <?= e(date('M j, g:i A', strtotime($o['created_at']))) ?></div>
              <div class="text-[11px] text-gray-600 font-mono truncate"><?= e($o['reference']) ?></div>
            </div>
            <div class="text-right flex-shrink-0">
              <div class="text-sm font-semibold"><?= formatNaira($o['amount']) ?></div>
              <span class="badge <?= $badgeClass ?>"><?= e(ucfirst($o['status'])) ?></span>
            </div>
          </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="p-10 text-center">
            <div class="text-3xl mb-2">📶</div>
            <div class="text-sm text-gray-400">No data orders yet</div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- ───────── Confirm sheet (this form is what actually submits) ───────── -->
<form method="POST" id="data-form" autocomplete="off">
  <?= csrfField() ?>
  <input type="hidden" name="network" id="f-network" value="<?= e($networkInput) ?>">
  <input type="hidden" name="phone" id="f-phone" value="">
  <input type="hidden" name="plan_code" id="f-plan" value="">
  <input type="hidden" name="expected_price" id="f-price" value="">

  <div class="sheet" id="sheet" aria-hidden="true">
    <div class="sheet-bg" id="sheet-bg"></div>
    <div class="sheet-panel" role="dialog" aria-modal="true" aria-labelledby="sheet-title">
      <div class="flex items-center justify-between mb-4">
        <h3 id="sheet-title" class="text-lg font-extrabold">Confirm purchase</h3>
        <button type="button" id="sheet-close" class="w-8 h-8 rounded-full bg-white/5 hover:bg-white/10 text-gray-300 text-lg leading-none" aria-label="Close">×</button>
      </div>

      <div class="text-center py-3 mb-4 rounded-2xl bg-white/[0.04] border border-white/10">
        <div id="sh-size" class="text-3xl font-extrabold tracking-tight">—</div>
        <div id="sh-validity" class="text-sm text-gray-400 mt-0.5"></div>
        <div id="sh-price" class="text-2xl font-extrabold text-orange-400 mt-2">₦0</div>
      </div>

      <dl class="space-y-2.5 text-sm mb-4">
        <div class="flex justify-between gap-4"><dt class="text-gray-400">Network</dt><dd id="sh-net" class="font-medium">—</dd></div>
        <div class="flex justify-between gap-4"><dt class="text-gray-400">Recipient</dt><dd id="sh-phone" class="font-medium">—</dd></div>
        <div class="flex justify-between gap-4"><dt class="text-gray-400">Wallet balance</dt><dd id="sh-balance" class="font-medium">—</dd></div>
        <div class="flex justify-between gap-4"><dt class="text-gray-400">Balance after</dt><dd id="sh-after" class="font-medium">—</dd></div>
      </dl>

      <div class="rounded-xl p-3.5 mb-4 border border-orange-400/25 bg-orange-500/10">
        <div class="text-sm" id="sh-reward">🎟️</div>
        <div class="h-1.5 rounded-full bg-white/10 overflow-hidden mt-2.5"><div id="sh-bar" class="h-full bg-orange-400 rounded-full transition-all" style="width:0%"></div></div>
      </div>

      <div id="sh-low" class="hidden text-sm text-amber-300 bg-amber-500/10 border border-amber-500/25 rounded-xl p-3 mb-4">
        Your wallet balance is too low for this bundle. <a id="sh-deposit" href="#" class="underline font-semibold">Deposit funds</a>
      </div>

      <button type="submit" id="pay-btn" class="btn bg-orange-400 hover:bg-orange-500 disabled:opacity-50 disabled:cursor-not-allowed text-white w-full justify-center py-3.5 font-semibold text-base rounded-xl">
        <span id="pay-label">Pay</span>
      </button>
      <div class="text-center text-xs text-gray-500 mt-3">If delivery fails, your wallet is refunded automatically.</div>
    </div>
  </div>
</form>

<script>window.APP_URL = '<?= APP_URL ?>';</script>
<script>window.ZF = <?= json_encode($jsConfig, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;</script>
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
<script>
(function () {
  const Z = window.ZF;
  const $ = (s, r = document) => r.querySelector(s);
  const fmtN = (n) => '₦' + Number(n).toLocaleString('en-NG');
  const fmtK = (k) => fmtN(k / 100);
  const TABS = [['hot', '🔥 Hot'], ['daily', 'Daily'], ['night', 'Extra Night'], ['weekly', 'Weekly'], ['monthly', 'Monthly'], ['other', 'Others']];

  const state = { net: Z.initial.network, phone: '', tab: 'hot', selected: Z.initial.plan || '', view: 'grid' };
  try { state.view = localStorage.getItem('zf_plan_view') === 'list' ? 'list' : 'grid'; } catch (e) {}

  // ── Phone helpers ─────────────────────────────────────────────
  function toLocal(v) {
    let d = String(v || '').replace(/\D/g, '');
    if (d.startsWith('234') && d.length >= 13) d = '0' + d.slice(3);
    else if (d.length === 10 && !d.startsWith('0')) d = '0' + d;
    return d.slice(0, 11);
  }
  const fmtPhone = (d) => [d.slice(0, 3), d.slice(3, 7), d.slice(7, 11)].filter(Boolean).join(' ');
  const validPhone = (d) => /^0[789][01]\d{8}$/.test(d);

  // Prefix → network. Only prefixes I'm sure of; ported numbers can differ, so it's a suggestion the user can override.
  const PREFIX = {};
  ['0703','0706','0803','0806','0810','0813','0814','0816','0903','0906','0913','0916'].forEach(p => PREFIX[p] = 'mtn');
  ['0705','0805','0807','0811','0815','0905','0915'].forEach(p => PREFIX[p] = 'glo');
  ['0701','0708','0802','0808','0812','0901','0902','0904','0907','0912'].forEach(p => PREFIX[p] = 'airtel');
  ['0809','0817','0818','0908','0909'].forEach(p => PREFIX[p] = '9mobile');

  const phoneEl = $('#phone-display'), hint = $('#phone-hint');

  function setPhone(v, detect = true) {
    state.phone = toLocal(v);
    phoneEl.value = fmtPhone(state.phone);
    hint.className = 'text-xs mt-3 min-h-[1rem] text-gray-500';
    hint.textContent = '';
    if (detect && state.phone.length >= 4 && PREFIX[state.phone.slice(0, 4)] && PREFIX[state.phone.slice(0, 4)] !== state.net) {
      setNetwork(PREFIX[state.phone.slice(0, 4)], true);
    }
  }

  // ── Network ───────────────────────────────────────────────────
  function setNetwork(n, detected = false) {
    if (!Z.networks[n]) return;
    state.net = n; state.tab = 'hot';
    const b = $('#net-badge');
    b.style.background = Z.networks[n].color;
    b.textContent = n === '9mobile' ? '9m' : Z.networks[n].label.toUpperCase();
    b.style.fontSize = n === '9mobile' ? '13px' : '11px';
    $('#f-network').value = n;
    if (detected) {
      hint.className = 'text-xs mt-3 min-h-[1rem] text-emerald-400';
      hint.textContent = 'Detected ' + Z.networks[n].label + '. If this number was ported, change the network above.';
    }
    closeSheet(); renderTabs(); renderPlans();
  }
  $('#net-btn').addEventListener('click', (e) => { e.stopPropagation(); $('#net-menu').classList.toggle('hidden'); });
  document.addEventListener('click', () => $('#net-menu').classList.add('hidden'));
  document.querySelectorAll('.net-opt').forEach(b => b.addEventListener('click', () => { setNetwork(b.dataset.net); $('#net-menu').classList.add('hidden'); }));

  // ── Contact picker (Chrome on Android; not available on iOS Safari / most desktops) ──
  $('#contact-btn').addEventListener('click', async () => {
    const supported = 'contacts' in navigator && 'ContactsManager' in window;
    if (!supported || !window.isSecureContext) {
      hint.className = 'text-xs mt-3 min-h-[1rem] text-amber-400';
      hint.textContent = !window.isSecureContext && supported
        ? 'Contact picking needs a secure (https) page.'
        : "Your browser can't open contacts. Type the number, or tap one of your recent numbers.";
      return;
    }
    try {
      const res = await navigator.contacts.select(['tel'], { multiple: false });
      if (res && res.length && res[0].tel && res[0].tel.length) setPhone(res[0].tel[0]);
    } catch (e) { /* user closed the picker */ }
  });
  document.querySelectorAll('.recent-phone').forEach(b => b.addEventListener('click', () => setPhone(b.dataset.phone)));
  phoneEl.addEventListener('input', () => setPhone(phoneEl.value));
  phoneEl.addEventListener('paste', (e) => { e.preventDefault(); setPhone((e.clipboardData || window.clipboardData).getData('text')); });

  // ── Plans ─────────────────────────────────────────────────────
  const plansOf = () => Z.plans.filter(p => p.net === state.net);
  function hotPlans(list) {
    const good = list.filter(p => p.sizeGb >= 1 && p.ppg).sort((a, b) => a.ppg - b.ppg).slice(0, 9);
    return good.length >= 3 ? good : [...list].sort((a, b) => a.price - b.price).slice(0, 9);
  }
  function inTab(list, tab) {
    return tab === 'hot' ? hotPlans(list) : list.filter(p => p.cat === tab);
  }
  function renderTabs() {
    const list = plansOf();
    const box = $('#tabs'); box.innerHTML = '';
    const visible = TABS.filter(([k]) => k === 'hot' ? list.length > 0 : list.some(p => p.cat === k));
    if (!visible.some(([k]) => k === state.tab) && visible.length) state.tab = visible[0][0];
    visible.forEach(([k, label]) => {
      const b = document.createElement('button');
      b.type = 'button'; b.className = 'tab' + (k === state.tab ? ' active' : ''); b.textContent = label; b.setAttribute('role', 'tab');
      b.addEventListener('click', () => { state.tab = k; renderTabs(); renderPlans(); });
      box.appendChild(b);
    });
  }
  const sizeText = (n) => (Math.round(n * 100) / 100).toString();

  function renderPlans() {
    const grid = $('#plan-grid'); grid.innerHTML = ''; grid.dataset.view = state.view;
    const list = inTab(plansOf(), state.tab).sort((a, b) => a.price - b.price);
    $('#plan-empty').classList.toggle('hidden', list.length > 0);
    list.forEach(p => {
      const t = document.createElement('button');
      t.type = 'button'; t.className = 'tile' + (p.id === state.selected ? ' selected' : '');
      const codes = Math.floor(p.price / Z.rewardUnit);

      const main = document.createElement('div'); main.className = 'main';
      if (p.size) {
        const s = document.createElement('div'); s.className = 'size'; s.textContent = sizeText(p.size);
        const u = document.createElement('small'); u.textContent = p.unit; s.appendChild(u); main.appendChild(s);
      } else {
        const n = document.createElement('div'); n.className = 'nm'; n.textContent = p.name; main.appendChild(n);
      }
      if (p.vlabel) { const v = document.createElement('div'); v.className = 'vl'; v.textContent = p.vlabel; main.appendChild(v); }

      const side = document.createElement('div'); side.className = 'side';
      const pr = document.createElement('div'); pr.className = 'pr'; pr.textContent = fmtK(p.price); side.appendChild(pr);
      if (codes > 0) { const r = document.createElement('div'); r.className = 'rw'; r.textContent = '🎟️ ' + codes + (codes > 1 ? ' codes' : ' code'); side.appendChild(r); }

      t.appendChild(main); t.appendChild(side);
      if (p.type) { const g = document.createElement('div'); g.className = 'tag'; g.textContent = p.type; t.appendChild(g); }
      t.addEventListener('click', () => choose(p));
      grid.appendChild(t);
    });
  }
  function setView(v) {
    state.view = v;
    try { localStorage.setItem('zf_plan_view', v); } catch (e) {}
    $('#view-grid').className = 'w-9 h-9 rounded-lg flex items-center justify-center ' + (v === 'grid' ? 'text-orange-400' : 'text-gray-500');
    $('#view-list').className = 'w-9 h-9 rounded-lg flex items-center justify-center ' + (v === 'list' ? 'text-orange-400' : 'text-gray-500');
    renderPlans();
  }
  $('#view-grid').addEventListener('click', () => setView('grid'));
  $('#view-list').addEventListener('click', () => setView('list'));

  // ── Confirm sheet ─────────────────────────────────────────────
  const sheet = $('#sheet');
  function openSheet() { sheet.classList.add('open'); sheet.setAttribute('aria-hidden', 'false'); document.body.style.overflow = 'hidden'; }
  function closeSheet() { sheet.classList.remove('open'); sheet.setAttribute('aria-hidden', 'true'); document.body.style.overflow = ''; }
  $('#sheet-bg').addEventListener('click', closeSheet);
  $('#sheet-close').addEventListener('click', closeSheet);
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeSheet(); });

  function choose(p) {
    if (!validPhone(state.phone)) {
      phoneEl.classList.remove('shake'); void phoneEl.offsetWidth; phoneEl.classList.add('shake');
      hint.className = 'text-xs mt-3 min-h-[1rem] text-red-400';
      hint.textContent = 'Enter a valid 11-digit Nigerian number first.';
      phoneEl.focus(); window.scrollTo({ top: 0, behavior: 'smooth' });
      return;
    }
    state.selected = p.id; renderPlans();

    $('#sh-size').textContent = p.size ? sizeText(p.size) + ' ' + p.unit : p.name;
    $('#sh-validity').textContent = [p.vlabel, p.type].filter(Boolean).join(' · ');
    $('#sh-price').textContent = fmtK(p.price);
    $('#sh-net').textContent = Z.networks[state.net].label;
    $('#sh-phone').textContent = fmtPhone(state.phone);
    $('#sh-balance').textContent = fmtK(Z.balance);

    const after = Z.balance - p.price, low = after < 0;
    $('#sh-after').textContent = low ? 'Insufficient' : fmtK(after);
    $('#sh-after').className = 'font-medium ' + (low ? 'text-amber-400' : '');
    $('#sh-low').classList.toggle('hidden', !low);
    $('#sh-deposit').href = Z.depositUrl;

    const total = Z.progress + p.price, codes = Math.floor(total / Z.rewardUnit), rest = total - codes * Z.rewardUnit;
    $('#sh-reward').innerHTML = codes > 0
      ? "🎟️ You'll earn <strong class=\"text-orange-300\">" + codes + ' free raffle code' + (codes > 1 ? 's' : '') + '</strong> with this purchase.'
      : '🎟️ Spend ' + fmtK(Z.rewardUnit - rest) + ' more to earn your next free code.';
    $('#sh-bar').style.width = Math.min(100, rest / Z.rewardUnit * 100) + '%';

    $('#f-phone').value = state.phone;
    $('#f-plan').value = p.id;
    $('#f-price').value = p.price;      // server rejects the order if the price changed since this page loaded
    $('#pay-btn').disabled = low;
    $('#pay-label').textContent = low ? 'Insufficient balance' : 'Pay ' + fmtK(p.price);
    openSheet();
  }

  // One submit only (a double-tap must never buy twice)
  let submitting = false;
  $('#data-form').addEventListener('submit', (e) => {
    if (submitting || !validPhone(state.phone) || !$('#f-plan').value) { e.preventDefault(); return; }
    submitting = true; $('#pay-btn').disabled = true; $('#pay-label').textContent = 'Processing…';
  });

  // ── Init ──────────────────────────────────────────────────────
  setPhone(Z.initial.phone, false);
  setNetwork(state.net);
  setView(state.view);
  // After a server error (e.g. price changed) keep their selection highlighted so they can re-confirm.
  if (Z.initial.hadError && state.selected) {
    const p = Z.plans.find(x => x.id === state.selected && x.net === state.net);
    if (p) {
      const tab = TABS.find(([k]) => k !== 'hot' && p.cat === k);
      if (!inTab(plansOf(), 'hot').includes(p) && tab) state.tab = tab[0];
      renderTabs(); renderPlans();
    }
  }
})();
</script>
</body></html>

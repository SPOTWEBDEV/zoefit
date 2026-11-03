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
// Brand colours for the network chips (dot + selected ring)
$networkColors = ['mtn' => '#FFCC00', 'glo' => '#2FB34A', 'airtel' => '#E4002B', '9mobile' => '#8DC63F'];

$errors  = [];
$success = null;
$notice  = null;
$phoneInput   = '';   // after a failed POST this is re-filled below
$amountInput  = '';
$networkInput = 'mtn';

if (isPost()) {
    if (!verifyCsrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $errors[] = 'Your session expired, please try again.';
    } else {
        $networkInput = $_POST['network'] ?? '';
        $phoneInput   = trim($_POST['phone'] ?? '');
        $amountInput  = trim($_POST['amount'] ?? '');
        $amountNaira  = (float) $amountInput;

        if (!isset($networks[$networkInput])) {
            $errors[] = 'Please select a valid network.';
        }
        $normalizedPhone = normalizePhone($phoneInput);
        if (!preg_match('/^234\d{10}$/', $normalizedPhone)) {
            $errors[] = 'Please enter a valid Nigerian phone number.';
        }
        if ($amountNaira < 50) {
            $errors[] = 'Minimum airtime purchase is ₦50.';
        } elseif ($amountNaira > 50000) {
            $errors[] = 'Maximum airtime purchase is ₦50,000.';
        } elseif ($amountNaira != floor($amountNaira)) {
            $errors[] = 'Please enter a whole-naira amount.';
        }

        $airtimeKobo = (int) round($amountNaira * 100);   // airtime the user receives
        $feeKobo     = airtimeFeeKobo();                  // service fee, shown to the user
        $amountKobo  = $airtimeKobo + $feeKobo;           // TOTAL charged to the wallet

        if (!$errors && (int) ($_POST['expected_total'] ?? 0) !== $amountKobo) {
            // The total on the user's screen is not the current total (admin changed the fee).
            $errors[] = 'The service fee has just changed. Your new total is ' . formatNaira($amountKobo) . '. Please review and try again.';
        }

        if (!$errors && $amountKobo > $walletBalance) {
            $errors[] = 'Insufficient wallet balance. Please deposit more funds.';
        }

        if (!$errors) {
            $reference = 'ZF-AIR-' . $userId . '-' . time() . '-' . strtoupper(bin2hex(random_bytes(3)));

            try {
                $db->beginTransaction();

                // Lock the user row and re-check balance to avoid race conditions
                // (e.g. two purchases submitted at once).
                $lock = $db->prepare("SELECT wallet_balance FROM users WHERE id = ? FOR UPDATE");
                $lock->execute([$userId]);
                $lockedBalance = (int) $lock->fetchColumn();

                if ($amountKobo > $lockedBalance) {
                    $db->rollBack();
                    $errors[] = 'Insufficient wallet balance. Please deposit more funds.';
                } else {
                    // Reserve funds up front, then refund automatically if delivery definitely fails.
                    $db->prepare("UPDATE users SET wallet_balance = wallet_balance - ? WHERE id = ?")
                       ->execute([$amountKobo, $userId]);

                    $newBalance = $lockedBalance - $amountKobo;

                    // amount = total charged = cost_amount (airtime sent to provider) + profit_amount (service fee)
                    $profitKobo = $feeKobo;
                    $costKobo   = $airtimeKobo;

                    $orderStmt = $db->prepare("INSERT INTO orders (user_id, type, network, phone, amount, cost_amount, profit_amount, reference, status)
                        VALUES (?,?,?,?,?,?,?,?,'pending')");
                    $orderStmt->execute([$userId, 'airtime', $networkInput, $normalizedPhone, $amountKobo, $costKobo, $profitKobo, $reference]);
                    $orderId = (int) $db->lastInsertId();

                    $db->prepare("INSERT INTO wallet_transactions
                        (user_id, type, category, amount, balance_after, reference_type, reference_id, description)
                        VALUES (?,?,?,?,?,?,?,?)")
                       ->execute([$userId, 'debit', 'airtime_purchase', $amountKobo, $newBalance, 'order', $orderId,
                                  "Airtime purchase - {$networks[$networkInput]} - " . formatPhone($normalizedPhone)
                                  . ($feeKobo ? ' (incl. ' . formatNaira($feeKobo) . ' fee)' : '')]);

                    $db->commit();
                    $walletBalance = $newBalance;

                    // Attempt delivery outside the DB transaction (network call).
                    $result = dispatchAirtimeOrder($networkInput, $normalizedPhone, $airtimeKobo, $reference);   // provider gets the airtime value only, never the fee

                    if ($result['success']) {
                        $db->prepare("UPDATE orders SET status='success', provider=?, provider_reference=?, provider_response=? WHERE id=?")
                           ->execute([$result['provider'], $result['provider_reference'], $result['raw'], $orderId]);

                        createNotification($userId, 'Airtime Purchase Successful', formatNaira($airtimeKobo) . ' airtime sent to ' . formatPhone($normalizedPhone) . '.', 'success');
                        auditLog('user', $userId, 'airtime_purchase_success', "Airtime {$airtimeKobo} kobo + fee {$feeKobo} kobo to {$normalizedPhone}, ref {$reference}", 'order', $orderId);

                        // Tickets come from the admin's tiers, based on the AIRTIME value (₦350 earns the ₦300 tier), not the fee.
                        $rewardCodes = grantPurchaseReward($db, $userId, $orderId, $amountKobo, airtimeTicketsFor((int) $amountNaira));

                        $success = 'Airtime purchase successful! ' . formatNaira($airtimeKobo) . ' sent to ' . formatPhone($normalizedPhone) . '.'
                                 . ($rewardCodes ? " 🎟️ You earned {$rewardCodes} free raffle ticket" . ($rewardCodes > 1 ? 's' : '') . '!' : '');
                        $amountInput = '';

                    } elseif (!empty($result['pending'])) {
                        // OUTCOME UNKNOWN (timeout / provider 5xx). The provider may have delivered,
                        // so do NOT refund and do NOT retry. Leave 'pending' and reconcile manually.
                        $db->prepare("UPDATE orders SET provider=?, provider_response=? WHERE id=?")
                           ->execute([$result['provider'], $result['raw'], $orderId]);
                        auditLog('user', $userId, 'airtime_purchase_unconfirmed', "ref {$reference}", 'order', $orderId);
                        $notice = $result['message'] . " Reference: {$reference}";
                        $amountInput = '';

                    } else {
                        // Provider definitely did not deliver — refund the reserved amount.
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
                                      'Refund - airtime purchase failed']);

                        $db->prepare("UPDATE orders SET status='refunded', provider_response=? WHERE id=?")
                           ->execute([$result['message'], $orderId]);

                        $db->commit();
                        $walletBalance = $refundedBalance;

                        auditLog('user', $userId, 'airtime_purchase_failed', $result['message'] . " ref {$reference}", 'order', $orderId);
                        $errors[] = $result['message'] . ' Your wallet has been refunded.';
                    }
                }
            } catch (\Exception $ex) {
                if ($db->inTransaction()) $db->rollBack();
                error_log('[AIRTIME] ' . $ex->getMessage());
                $errors[] = 'Something went wrong processing your order. Please check your order history before trying again.';
            }
        }
    }
}

$airtimeFeeKobo = airtimeFeeKobo();
$airtimeTiers   = [];
foreach (airtimeTicketTiers() as $a => $t) $airtimeTiers[] = [$a, $t];

$recent = $db->prepare("SELECT * FROM orders WHERE user_id = ? AND type = 'airtime' ORDER BY created_at DESC LIMIT 10");
$recent->execute([$userId]);
$recent = $recent->fetchAll();

$currentPage = 'airtime';
$pageTitle   = 'Buy Airtime';

// Amount tiles: the admin's tiers (amount -> tickets). Before any are saved, show a default set.
$tileAmounts = [];
foreach (airtimeTicketTiers() as $a => $t) $tileAmounts[] = [(int) $a, (int) $t];
$hasTiers = (bool) $tileAmounts;
if (!$tileAmounts) foreach ([100, 200, 500, 1000, 2000, 5000] as $a) $tileAmounts[] = [$a, airtimeTicketsFor($a)];

// Recent numbers (shortcut that works on every browser/device)
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
if (!isset($networks[$networkInput])) $networkInput = 'mtn';

$jsConfig = [
    'tiles'    => $tileAmounts,                       // [[amount, tickets], ...] ascending
    'hasTiers' => $hasTiers,
    'unit'     => getRewardNairaPerCode(),            // only used before the admin saves tiers
    'balance'  => $walletBalance / 100,               // naira
    'fee'      => $airtimeFeeKobo / 100,              // naira
    'depositUrl' => APP_URL . '/user/deposit.php',
    'networks' => array_map(fn($k) => ['label' => $networks[$k], 'color' => $networkColors[$k]], array_combine(array_keys($networks), array_keys($networks))),
    'initial'  => ['network' => $networkInput, 'phone' => $initialPhone, 'amount' => $amountInput === '' ? '' : (string) (int) $amountInput, 'hadError' => (bool) $errors],
];
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
  <meta name="csrf-token" content="<?= generateCsrf() ?>">
  <title><?= e($pageTitle) ?> — <?= APP_NAME ?></title>
  <script src="<?= APP_URL ?>/assets/js/tailwind.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
  <style>
    .plan-grid { display: grid; gap: .65rem; grid-template-columns: repeat(3, minmax(0, 1fr)); }
    .tile {
      display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center;
      gap: .3rem; padding: .9rem .4rem; min-height: 6rem; width: 100%;
      background: rgba(255,255,255,.045); border: 1px solid rgba(255,255,255,.08); border-radius: 1rem;
      cursor: pointer; color: inherit; transition: transform .12s, border-color .15s, background .15s;
    }
    .tile:hover { background: rgba(255,255,255,.08); }
    .tile:active { transform: scale(.97); }
    .tile.selected { border-color: #fb923c; background: rgba(249,115,22,.12); box-shadow: 0 0 0 1px rgba(251,146,60,.4); }
    .tile .size { font-size: 1.35rem; font-weight: 800; line-height: 1.1; letter-spacing: -.02em; }
    .tile .rw { font-size: .72rem; font-weight: 600; color: #34d399; }
    .tile .rw.none { color: #6b7280; font-weight: 500; }
    @media (max-width: 380px) { .tile .size { font-size: 1.15rem; } }

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

    /* ── Mobile ── */
    html { -webkit-text-size-adjust: 100%; }
    .tile, .tab, .net-opt, .recent-phone, .quick-amt, button { touch-action: manipulation; -webkit-tap-highlight-color: transparent; }
    .phone-in { font-size: clamp(1.05rem, 5.4vw, 1.3rem); }              /* 16px+ so iOS never zooms on focus */
    .amt-row { display: flex; flex-direction: column; gap: .5rem; }
    @media (min-width: 400px) { .amt-row { flex-direction: row; } }
    .sheet-panel { max-height: 92vh; max-height: 92dvh; overscroll-behavior: contain; }
    @media (max-width: 359px) {
      .plan-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }   /* very small phones: 2 per row */
    }
    @media (max-width: 767px) {
      .tile { min-height: 7.4rem; }
      .tile .size { font-size: clamp(1.1rem, 5.2vw, 1.35rem); }
      .sheet-panel { padding-bottom: calc(1.25rem + env(safe-area-inset-bottom)); }
      .sheet-panel #pay-btn { position: sticky; bottom: 0; }
    }
  </style>
</head>
<body class="bg-[#0a0f1a] text-white font-sans">

<?php include __DIR__ . '/../components/user-sidebar.php'; ?>

<div class="main-content">
  <div class="topbar">
    <button onclick="toggleSidebar()" class="md:hidden text-gray-400 hover:text-white text-2xl mr-3">☰</button>
    <h1 class="text-lg font-bold">Buy Airtime</h1>
    <a href="#history" class="ml-auto text-sm font-semibold text-orange-400 hover:text-orange-300 py-2 pl-3">History</a>
  </div>

  <div class="px-3 py-4 sm:p-4 md:p-6 max-w-xl mx-auto space-y-4">

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
          <div class="text-lg font-extrabold leading-tight">Top up.<br><span class="text-orange-400">Win big.</span></div>
          <div class="text-xs text-gray-300 mt-2">Every airtime purchase earns you free raffle tickets</div>
        </div>
        <div class="text-5xl flex-shrink-0">🎟️</div>
      </div>
    </div>

    <!-- Network + number -->
    <div class="card p-4">
      <div class="flex items-center gap-2 sm:gap-3">
        <div class="relative flex-shrink-0">
          <button type="button" id="net-btn" class="flex items-center gap-1.5" aria-label="Choose network" aria-haspopup="listbox">
            <span id="net-badge" class="w-11 h-11 rounded-full flex items-center justify-center text-[11px] font-extrabold text-black" style="background:#FFCC00">MTN</span>
            <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
          </button>
          <div id="net-menu" class="hidden absolute left-0 top-14 z-20 w-44 max-w-[calc(100vw-2rem)] rounded-2xl border border-white/10 bg-[#111827] shadow-2xl p-1.5" role="listbox">
            <?php foreach ($networks as $key => $label): ?>
              <button type="button" data-net="<?= e($key) ?>" class="net-opt w-full flex items-center gap-3 px-3 py-2.5 rounded-xl hover:bg-white/10 text-sm font-medium text-left" role="option">
                <span class="w-3 h-3 rounded-full" style="background:<?= e($networkColors[$key]) ?>"></span><?= e($label) ?>
              </button>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="w-px h-8 bg-white/10"></div>
        <input type="tel" id="phone-display" inputmode="numeric" autocomplete="off" maxlength="13" placeholder="080 1234 5678"
               class="phone-in flex-1 min-w-0 bg-transparent font-bold tracking-wide placeholder:text-gray-600 focus:outline-none" aria-label="Phone number">
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
          <?php foreach ($recentPhones as $rph): ?>
            <button type="button" data-phone="<?= e($rph) ?>" class="recent-phone px-3.5 py-2 rounded-full bg-white/5 hover:bg-white/10 border border-white/10 text-xs font-medium"><?= e(substr($rph, 0, 4) . ' ' . substr($rph, 4, 3) . ' ' . substr($rph, 7)) ?></button>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>

    <!-- Amounts -->
    <div class="card p-4 md:p-5">
      <div class="flex items-center justify-between flex-wrap gap-x-3 gap-y-1 mb-1">
        <h2 class="text-xl font-extrabold">Airtime</h2>
        <div class="text-xs text-gray-400" id="wallet-line">Wallet <span class="font-semibold text-gray-200"><?= formatNaira($walletBalance) ?></span></div>
      </div>
      <p class="text-xs text-gray-400 mb-4" id="fee-note"><?php if ($airtimeFeeKobo > 0): ?>A service fee of <strong class="text-gray-200"><?= formatNaira($airtimeFeeKobo) ?></strong> is added to each purchase.<?php else: ?>Pick an amount to top up.<?php endif; ?></p>

      <div id="amount-grid" class="plan-grid"></div>

      <div class="mt-5 pt-4 border-t border-white/5">
        <label for="custom-amt" class="block text-sm text-gray-400 mb-2">Or enter another amount</label>
        <div class="amt-row">
          <div class="relative flex-1 min-w-0">
            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-xl font-semibold text-gray-500">₦</span>
            <input type="number" id="custom-amt" min="50" max="50000" step="1" inputmode="numeric" placeholder="350"
                   class="w-full bg-white/[0.04] border border-white/10 rounded-xl pl-10 pr-4 py-3 text-xl font-bold focus:outline-none focus:border-orange-400 focus:ring-1 focus:ring-orange-400/40 placeholder:text-gray-700">
          </div>
          <button type="button" id="custom-go" class="btn bg-orange-400 hover:bg-orange-500 text-white px-5 py-3 justify-center font-semibold rounded-xl">Continue</button>
        </div>
        <div id="custom-hint" class="text-xs mt-2 min-h-[1rem] text-gray-500"></div>
      </div>
    </div>

    <!-- History -->
    <div id="history" class="pt-2">
      <h2 class="text-lg font-bold mb-3">Recent airtime orders</h2>
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
          <div class="flex items-center gap-3 px-3 sm:px-4 py-3.5 border-b border-white/5 last:border-0">
            <div class="w-10 h-10 rounded-xl bg-orange-500/15 text-orange-400 flex items-center justify-center flex-shrink-0">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
            </div>
            <div class="flex-1 min-w-0">
              <div class="text-sm font-medium truncate"><?= e(strtoupper($o['network'])) ?> · <?= e(formatPhone($o['phone'])) ?></div>
              <div class="text-xs text-gray-500 truncate"><?= e(date('M j, g:i A', strtotime($o['created_at']))) ?> · <span class="font-mono"><?= e($o['reference']) ?></span></div>
            </div>
            <div class="text-right flex-shrink-0">
              <div class="text-sm font-semibold"><?= formatNaira($o['amount']) ?></div>
              <?php if (!empty($o['profit_amount']) && (int) $o['cost_amount'] + (int) $o['profit_amount'] === (int) $o['amount']): ?>
                <div class="text-[11px] text-gray-500"><?= formatNaira($o['cost_amount']) ?> + <?= formatNaira($o['profit_amount']) ?> fee</div>
              <?php endif; ?>
              <span class="badge <?= $badgeClass ?>"><?= e(ucfirst($o['status'])) ?></span>
            </div>
          </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="p-10 text-center">
            <div class="text-3xl mb-2">📞</div>
            <div class="text-sm text-gray-400">No airtime orders yet</div>
            <div class="text-xs text-gray-600 mt-1">Your top-ups will appear here.</div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- ───────── Confirm sheet (this form is what actually submits) ───────── -->
<form method="POST" id="airtime-form" autocomplete="off">
  <?= csrfField() ?>
  <input type="hidden" name="network" id="f-network" value="<?= e($networkInput) ?>">
  <input type="hidden" name="phone" id="f-phone" value="">
  <input type="hidden" name="amount" id="f-amount" value="">
  <input type="hidden" name="expected_total" id="f-total" value="">

  <div class="sheet" id="sheet" aria-hidden="true">
    <div class="sheet-bg" id="sheet-bg"></div>
    <div class="sheet-panel" role="dialog" aria-modal="true" aria-labelledby="sheet-title">
      <div class="flex items-center justify-between mb-4">
        <h3 id="sheet-title" class="text-lg font-extrabold">Confirm purchase</h3>
        <button type="button" id="sheet-close" class="w-10 h-10 rounded-full bg-white/5 hover:bg-white/10 text-gray-300 text-xl leading-none" aria-label="Close">×</button>
      </div>

      <div class="text-center py-3 mb-4 rounded-2xl bg-white/[0.04] border border-white/10">
        <div class="text-sm text-gray-400">Airtime</div>
        <div id="sh-amount" class="text-3xl font-extrabold tracking-tight">₦0</div>
      </div>

      <dl class="space-y-2.5 text-sm mb-4">
        <div class="flex justify-between gap-4"><dt class="text-gray-400">Network</dt><dd id="sh-net" class="font-medium">—</dd></div>
        <div class="flex justify-between gap-4"><dt class="text-gray-400">Recipient</dt><dd id="sh-phone" class="font-medium">—</dd></div>
        <div id="sh-fee-row" class="flex justify-between gap-4"><dt class="text-gray-400">Service fee</dt><dd id="sh-fee" class="font-medium">₦0</dd></div>
        <div class="flex justify-between gap-4 items-baseline border-t border-white/10 pt-2.5"><dt class="text-gray-300 font-medium">You pay</dt><dd id="sh-total" class="text-xl font-extrabold text-orange-400">₦0</dd></div>
        <div class="flex justify-between gap-4 text-xs"><dt class="text-gray-500">Wallet balance</dt><dd id="sh-balance" class="text-gray-400">—</dd></div>
        <div class="flex justify-between gap-4 text-xs"><dt class="text-gray-500">Balance after</dt><dd id="sh-after" class="text-gray-400">—</dd></div>
      </dl>

      <div class="rounded-xl p-3.5 mb-4 border border-orange-400/25 bg-orange-500/10">
        <div class="text-sm" id="sh-reward">🎟️</div>
        <div class="text-xs text-gray-400 mt-1.5" id="sh-next"></div>
      </div>

      <div id="sh-low" class="hidden text-sm text-amber-300 bg-amber-500/10 border border-amber-500/25 rounded-xl p-3 mb-4">
        Your wallet balance is too low. <a id="sh-deposit" href="#" class="underline font-semibold">Deposit funds</a>
      </div>

      <button type="submit" id="pay-btn" class="btn bg-orange-400 hover:bg-orange-500 disabled:opacity-50 disabled:cursor-not-allowed text-white w-full justify-center py-3.5 font-semibold text-base rounded-xl">
        <span id="pay-label">Pay</span>
      </button>
      <div class="text-center text-xs text-gray-500 mt-3">If the top-up fails, your wallet is refunded automatically.</div>
    </div>
  </div>
</form>

<script>window.APP_URL = '<?= APP_URL ?>';</script>
<!-- NOT window.ZF: assets/js/app.js defines window.ZF (the AJAX helper) and would overwrite it -->
<script>window.ZF_AIR = <?= json_encode($jsConfig, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;</script>
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
<script>
(function () {
  const Z = window.ZF_AIR;
  const $ = (s, r = document) => r.querySelector(s);
  const fmt = (n) => '₦' + Number(n).toLocaleString('en-NG');

  const state = { net: Z.initial.network, phone: '', amount: 0 };

  // ── Phone helpers ─────────────────────────────────────────────
  function toLocal(v) {
    let d = String(v || '').replace(/\D/g, '');
    if (d.startsWith('234') && d.length >= 13) d = '0' + d.slice(3);
    else if (d.length === 10 && !d.startsWith('0')) d = '0' + d;
    return d.slice(0, 11);
  }
  const fmtPhone = (d) => [d.slice(0, 3), d.slice(3, 7), d.slice(7, 11)].filter(Boolean).join(' ');
  const validPhone = (d) => /^0[789][01]\d{8}$/.test(d);

  // Prefix → network. A suggestion only: ported numbers can differ, so the user can still change it.
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
    state.net = n;
    const b = $('#net-badge');
    b.style.background = Z.networks[n].color;
    b.textContent = n === '9mobile' ? '9m' : Z.networks[n].label.toUpperCase();
    b.style.fontSize = n === '9mobile' ? '13px' : '11px';
    $('#f-network').value = n;
    if (detected) {
      hint.className = 'text-xs mt-3 min-h-[1rem] text-emerald-400';
      hint.textContent = 'Detected ' + Z.networks[n].label + '. If this number was ported, change the network above.';
    }
    closeSheet();
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

  // ── Tickets: highest tier the AIRTIME amount reaches (fee not counted) ──
  function ticketsFor(amt) {
    if (!Z.hasTiers) return Math.floor(amt / Z.unit);   // before the admin saves tiers
    let t = 0; Z.tiles.forEach(([a, k]) => { if (a <= amt) t = k; });
    return t;
  }
  function nextTier(amt, have) {
    if (!Z.hasTiers) return null;
    for (const [a, k] of Z.tiles) if (a > amt && k > have) return [a, k];
    return null;
  }

  // ── Amount tiles ──────────────────────────────────────────────
  function renderTiles() {
    const grid = $('#amount-grid'); grid.innerHTML = '';
    Z.tiles.forEach(([a, k]) => {
      const t = document.createElement('button');
      t.type = 'button'; t.className = 'tile' + (a === state.amount ? ' selected' : '');
      const s = document.createElement('div'); s.className = 'size'; s.textContent = fmt(a);
      const r = document.createElement('div');
      const tk = Z.hasTiers ? k : ticketsFor(a);
      r.className = 'rw' + (tk > 0 ? '' : ' none');
      r.textContent = tk > 0 ? '🎟️ ' + tk + (tk > 1 ? ' tickets' : ' ticket') : 'No tickets';
      t.appendChild(s); t.appendChild(r);
      t.addEventListener('click', () => choose(a));
      grid.appendChild(t);
    });
  }

  // ── Confirm sheet ─────────────────────────────────────────────
  const sheet = $('#sheet');
  function openSheet() { sheet.classList.add('open'); sheet.setAttribute('aria-hidden', 'false'); document.body.style.overflow = 'hidden'; }
  function closeSheet() { sheet.classList.remove('open'); sheet.setAttribute('aria-hidden', 'true'); document.body.style.overflow = ''; }
  $('#sheet-bg').addEventListener('click', closeSheet);
  $('#sheet-close').addEventListener('click', closeSheet);
  document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeSheet(); });

  function choose(amt) {
    if (!validPhone(state.phone)) {
      phoneEl.classList.remove('shake'); void phoneEl.offsetWidth; phoneEl.classList.add('shake');
      hint.className = 'text-xs mt-3 min-h-[1rem] text-red-400';
      hint.textContent = 'Enter a valid 11-digit Nigerian number first.';
      phoneEl.focus(); window.scrollTo({ top: 0, behavior: 'smooth' });
      return;
    }
    state.amount = amt; renderTiles();

    const fee = Z.fee, pay = amt + fee, after = Z.balance - pay, low = after < 0;
    $('#sh-amount').textContent = fmt(amt);
    $('#sh-net').textContent = Z.networks[state.net].label;
    $('#sh-phone').textContent = fmtPhone(state.phone);
    $('#sh-fee-row').classList.toggle('hidden', !(fee > 0));
    $('#sh-fee').textContent = fmt(fee);
    $('#sh-total').textContent = fmt(pay);
    $('#sh-balance').textContent = fmt(Z.balance);
    $('#sh-after').textContent = low ? 'Insufficient' : fmt(after);
    $('#sh-after').className = low ? 'text-amber-400' : 'text-gray-400';
    $('#sh-low').classList.toggle('hidden', !low);
    $('#sh-deposit').href = Z.depositUrl;

    const codes = ticketsFor(amt), nx = nextTier(amt, codes);
    $('#sh-reward').innerHTML = codes > 0
      ? "🎟️ You'll earn <strong class=\"text-orange-300\">" + codes + ' free raffle ticket' + (codes > 1 ? 's' : '') + '</strong> with this purchase.'
      : '🎟️ This amount does not earn raffle tickets.';
    $('#sh-next').textContent = nx ? 'Buy ' + fmt(nx[0]) + ' to earn ' + nx[1] + ' ticket' + (nx[1] > 1 ? 's' : '') + '.' : '';

    $('#f-phone').value = state.phone;
    $('#f-amount').value = amt;
    $('#f-total').value = Math.round(pay * 100);   // server rejects the order if the fee changed since this page loaded
    $('#pay-btn').disabled = low;
    $('#pay-label').textContent = low ? 'Insufficient balance' : 'Pay ' + fmt(pay);
    openSheet();
  }

  // Custom amount
  const customEl = $('#custom-amt'), customHint = $('#custom-hint');
  function goCustom() {
    const v = customEl.value.trim();
    const n = Number(v);
    customHint.className = 'text-xs mt-2 min-h-[1rem] text-red-400';
    if (!/^\d+$/.test(v)) { customHint.textContent = 'Enter a whole-naira amount.'; return; }
    if (n < 50) { customHint.textContent = 'Minimum airtime purchase is ₦50.'; return; }
    if (n > 50000) { customHint.textContent = 'Maximum airtime purchase is ₦50,000.'; return; }
    customHint.textContent = '';
    choose(n);
  }
  $('#custom-go').addEventListener('click', goCustom);
  customEl.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); goCustom(); } });

  // One submit only (a double-tap must never buy twice)
  let submitting = false;
  $('#airtime-form').addEventListener('submit', (e) => {
    if (submitting || !validPhone(state.phone) || !$('#f-amount').value) { e.preventDefault(); return; }
    submitting = true; $('#pay-btn').disabled = true; $('#pay-label').textContent = 'Processing…';
  });

  // ── Init ──────────────────────────────────────────────────────
  setPhone(Z.initial.phone, false);
  setNetwork(state.net);
  if (Z.initial.amount) state.amount = Number(Z.initial.amount);
  renderTiles();
})();
</script>
</body></html>
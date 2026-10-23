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
$phoneInput   = '';
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

        $amountKobo = (int) round($amountNaira * 100);

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

                    // amount = total charged to the user = cost_amount (provider price) + profit_amount (site profit)
                    $profitKobo = airtimeProfitKobo($amountKobo);
                    $costKobo   = $amountKobo - $profitKobo;

                    $orderStmt = $db->prepare("INSERT INTO orders (user_id, type, network, phone, amount, cost_amount, profit_amount, reference, status)
                        VALUES (?,?,?,?,?,?,?,?,'pending')");
                    $orderStmt->execute([$userId, 'airtime', $networkInput, $normalizedPhone, $amountKobo, $costKobo, $profitKobo, $reference]);
                    $orderId = (int) $db->lastInsertId();

                    $db->prepare("INSERT INTO wallet_transactions
                        (user_id, type, category, amount, balance_after, reference_type, reference_id, description)
                        VALUES (?,?,?,?,?,?,?,?)")
                       ->execute([$userId, 'debit', 'airtime_purchase', $amountKobo, $newBalance, 'order', $orderId,
                                  "Airtime purchase - {$networks[$networkInput]} - " . formatPhone($normalizedPhone)]);

                    $db->commit();
                    $walletBalance = $newBalance;

                    // Attempt delivery outside the DB transaction (network call).
                    $result = dispatchAirtimeOrder($networkInput, $normalizedPhone, $amountKobo, $reference);

                    if ($result['success']) {
                        $db->prepare("UPDATE orders SET status='success', provider=?, provider_reference=?, provider_response=? WHERE id=?")
                           ->execute([$result['provider'], $result['provider_reference'], $result['raw'], $orderId]);

                        createNotification($userId, 'Airtime Purchase Successful', formatNaira($amountKobo) . ' airtime sent to ' . formatPhone($normalizedPhone) . '.', 'success');
                        auditLog('user', $userId, 'airtime_purchase_success', "Airtime {$amountKobo} kobo to {$normalizedPhone}, ref {$reference}", 'order', $orderId);

                        $rewardCodes = grantPurchaseReward($db, $userId, $orderId, $amountKobo);

                        $success = 'Airtime purchase successful! ' . formatNaira($amountKobo) . ' sent to ' . formatPhone($normalizedPhone) . '.'
                                 . ($rewardCodes ? " 🎟️ You earned {$rewardCodes} free raffle code" . ($rewardCodes > 1 ? 's' : '') . '!' : '');
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
                $errors[] = 'Something went wrong processing your order. Please check your order history before trying again.' . $ex->getMessage();
            }
        }
    }
}

$rewardProgressKobo = getRewardProgressKobo($db, $userId);

$recent = $db->prepare("SELECT * FROM orders WHERE user_id = ? AND type = 'airtime' ORDER BY created_at DESC LIMIT 10");
$recent->execute([$userId]);
$recent = $recent->fetchAll();

$currentPage = 'airtime';
$pageTitle   = 'Buy Airtime';
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="csrf-token" content="<?= generateCsrf() ?>">
  <title><?= e($pageTitle) ?> — <?= APP_NAME ?></title>
  <script src="<?= APP_URL ?>/assets/js/tailwind.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
</head>
<body class="bg-[#0a0f1a] text-white font-sans">

<?php include __DIR__ . '/../components/user-sidebar.php'; ?>

<div class="main-content">
  <div class="topbar">
    <button onclick="toggleSidebar()" class="md:hidden text-gray-400 hover:text-white text-2xl mr-3">☰</button>
    <div>
      <div class="font-semibold">Buy Airtime</div>
      <div class="text-xs text-gray-400">Instant top-up from your wallet</div>
    </div>
  </div>

  <div class="p-4 md:p-8 max-w-6xl mx-auto">

    <!-- Page header -->
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 mb-6">
      <div>
        <h1 class="text-2xl md:text-3xl font-bold tracking-tight">Buy Airtime</h1>
        <p class="text-sm text-gray-400 mt-1">Top up any Nigerian line in seconds and earn raffle codes on every purchase.</p>
      </div>
      <div class="inline-flex items-center gap-3 rounded-2xl border border-white/10 bg-white/[0.04] px-4 py-2.5 self-start">
        <div class="w-9 h-9 rounded-xl bg-orange-500/15 flex items-center justify-center text-orange-400">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
        </div>
        <div>
          <div class="text-[11px] uppercase tracking-wider text-gray-400">Wallet balance</div>
          <div class="font-bold leading-tight" id="wallet-balance"><?= formatNaira($walletBalance) ?></div>
        </div>
      </div>
    </div>

    <!-- Alerts -->
    <?php if ($errors): ?>
      <div class="mb-6 flex gap-3 rounded-2xl border border-red-500/30 bg-red-500/10 text-red-200 p-4 text-sm" role="alert">
        <svg class="w-5 h-5 flex-shrink-0 text-red-400 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.7 7.3a1 1 0 00-1.4 1.4L8.6 10l-1.3 1.3a1 1 0 101.4 1.4L10 11.4l1.3 1.3a1 1 0 001.4-1.4L11.4 10l1.3-1.3a1 1 0 00-1.4-1.4L10 8.6 8.7 7.3z" clip-rule="evenodd"/></svg>
        <div class="space-y-1"><?php foreach ($errors as $err): ?><div><?= e($err) ?></div><?php endforeach; ?></div>
      </div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="mb-6 flex gap-3 rounded-2xl border border-emerald-500/30 bg-emerald-500/10 text-emerald-200 p-4 text-sm" role="status">
        <svg class="w-5 h-5 flex-shrink-0 text-emerald-400 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.7-9.3a1 1 0 00-1.4-1.4L9 10.6 7.7 9.3a1 1 0 00-1.4 1.4l2 2a1 1 0 001.4 0l4-4z" clip-rule="evenodd"/></svg>
        <div><?= e($success) ?></div>
      </div>
    <?php endif; ?>
    <?php if ($notice): ?>
      <div class="mb-6 flex gap-3 rounded-2xl border border-amber-500/30 bg-amber-500/10 text-amber-200 p-4 text-sm" role="status">
        <svg class="w-5 h-5 flex-shrink-0 text-amber-400 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M8.3 3.2a2 2 0 013.4 0l6 10.5A2 2 0 0116 16.7H4a2 2 0 01-1.7-3L8.3 3.2zM10 7a1 1 0 00-1 1v3a1 1 0 002 0V8a1 1 0 00-1-1zm0 7.5a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg>
        <div><?= e($notice) ?></div>
      </div>
    <?php endif; ?>

    <div class="grid lg:grid-cols-5 gap-6 items-start">

      <!-- ───────────── FORM ───────────── -->
      <form method="POST" id="airtime-form" class="lg:col-span-3 card p-5 md:p-7 space-y-7" autocomplete="off">
        <?= csrfField() ?>

        <!-- Step 1 -->
        <section>
          <div class="flex items-center gap-3 mb-3">
            <span class="w-6 h-6 rounded-full bg-orange-500/20 text-orange-400 text-xs font-bold flex items-center justify-center">1</span>
            <h2 class="text-sm font-semibold">Choose network</h2>
          </div>
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
            <?php foreach ($networks as $key => $label): ?>
            <label class="cursor-pointer min-w-0">
              <input type="radio" name="network" value="<?= $key ?>" data-label="<?= e($label) ?>" <?= $networkInput===$key?'checked':'' ?> class="peer sr-only">
              <div class="flex items-center justify-center gap-2 py-3 px-2 rounded-xl border border-white/10 bg-white/[0.04] text-sm font-medium transition
                          hover:bg-white/10 peer-checked:border-orange-400 peer-checked:bg-orange-500/10 peer-checked:ring-1 peer-checked:ring-orange-400/40 peer-focus-visible:ring-2 peer-focus-visible:ring-orange-400">
                <span class="w-2.5 h-2.5 rounded-full flex-shrink-0" style="background:<?= $networkColors[$key] ?>"></span>
                <span class="truncate"><?= e($label) ?></span>
              </div>
            </label>
            <?php endforeach; ?>
          </div>
        </section>

        <!-- Step 2 -->
        <section>
          <div class="flex items-center gap-3 mb-3">
            <span class="w-6 h-6 rounded-full bg-orange-500/20 text-orange-400 text-xs font-bold flex items-center justify-center">2</span>
            <h2 class="text-sm font-semibold">Phone number</h2>
          </div>
          <input type="tel" name="phone" id="phone" required inputmode="numeric" value="<?= e($phoneInput) ?>" placeholder="08012345678"
                 class="w-full bg-white/[0.04] border border-white/10 rounded-xl px-4 py-3.5 text-base focus:outline-none focus:border-orange-400 focus:ring-1 focus:ring-orange-400/40 placeholder:text-gray-600">
          <p class="text-xs text-gray-500 mt-2">Make sure the number is on the network you selected.</p>
        </section>

        <!-- Step 3 -->
        <section>
          <div class="flex items-center gap-3 mb-3">
            <span class="w-6 h-6 rounded-full bg-orange-500/20 text-orange-400 text-xs font-bold flex items-center justify-center">3</span>
            <h2 class="text-sm font-semibold">Amount</h2>
          </div>
          <div class="relative">
            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-xl font-semibold text-gray-500">₦</span>
            <input type="number" name="amount" id="amount" min="50" max="50000" step="1" required inputmode="numeric" value="<?= e($amountInput) ?>" placeholder="500"
                   class="w-full bg-white/[0.04] border border-white/10 rounded-xl pl-10 pr-4 py-3.5 text-2xl font-bold focus:outline-none focus:border-orange-400 focus:ring-1 focus:ring-orange-400/40 placeholder:text-gray-700">
          </div>
          <div class="flex flex-wrap gap-2 mt-3" id="quick-amounts">
            <?php foreach ([100, 200, 500, 1000, 2000, 5000] as $q): ?>
              <button type="button" data-amount="<?= $q ?>"
                      class="quick-amt px-3.5 py-1.5 rounded-full bg-white/[0.04] hover:bg-white/10 text-xs font-medium border border-white/10 whitespace-nowrap transition">₦<?= number_format($q) ?></button>
            <?php endforeach; ?>
          </div>
          <p id="balance-warning" class="hidden text-xs text-amber-400 mt-3">This is more than your wallet balance. Deposit funds to continue.</p>
        </section>

        <button type="submit" id="submit-btn"
                class="btn bg-orange-400 hover:bg-orange-500 disabled:opacity-50 disabled:cursor-not-allowed text-white w-full justify-center py-3.5 font-semibold text-base rounded-xl">
          <span id="submit-label">Buy Airtime</span>
        </button>
      </form>

      <!-- ───────────── SUMMARY ───────────── -->
      <aside class="lg:col-span-2 space-y-4 lg:sticky lg:top-6">

        <div class="card p-5 md:p-6">
          <h3 class="text-sm font-semibold mb-4">Order summary</h3>
          <dl class="space-y-3 text-sm">
            <div class="flex justify-between gap-4"><dt class="text-gray-400">Network</dt><dd class="font-medium" id="sum-network">—</dd></div>
            <div class="flex justify-between gap-4"><dt class="text-gray-400">Recipient</dt><dd class="font-medium truncate" id="sum-phone">—</dd></div>
            <div class="flex justify-between gap-4"><dt class="text-gray-400">Airtime value</dt><dd class="font-medium" id="sum-amount">₦0</dd></div>
            <div class="border-t border-white/10 pt-3 flex justify-between gap-4 items-baseline">
              <dt class="text-gray-300 font-medium">You pay</dt><dd class="text-xl font-bold text-orange-400" id="sum-total">₦0</dd>
            </div>
            <div class="flex justify-between gap-4 text-xs"><dt class="text-gray-500">Balance after</dt><dd class="text-gray-400" id="sum-after">—</dd></div>
          </dl>
        </div>

        <div class="rounded-2xl p-5 md:p-6 border border-orange-400/25 bg-gradient-to-br from-orange-500/15 via-orange-500/5 to-transparent">
          <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-semibold flex items-center gap-2">🎟️ Purchase rewards</h3>
            <span class="text-[11px] px-2 py-0.5 rounded-full bg-orange-400/15 text-orange-300 font-medium">₦<?= number_format(REWARD_NAIRA_PER_CODE) ?> = 1 code</span>
          </div>
          <div class="text-sm" id="reward-text">Spend ₦<?= number_format(REWARD_NAIRA_PER_CODE) ?> or more to earn free raffle codes.</div>
          <div class="h-2 rounded-full bg-white/10 overflow-hidden mt-3">
            <div id="reward-bar" class="h-full rounded-full bg-orange-400 transition-all duration-300" style="width:0%"></div>
          </div>
          <div class="flex justify-between text-[11px] text-gray-400 mt-1.5">
            <span id="reward-progress-label">₦0</span><span>₦<?= number_format(REWARD_NAIRA_PER_CODE) ?></span>
          </div>
        </div>

        <div class="flex items-start gap-2.5 text-xs text-gray-500 px-1">
          <svg class="w-4 h-4 flex-shrink-0 mt-0.5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.6-3a12 12 0 01-8.6 3.3A12 12 0 013.4 5 12 12 0 003 9c0 5.6 3.8 10.3 9 11.6 5.2-1.3 9-6 9-11.6 0-1.1-.1-2.1-.4-3z"/></svg>
          <span>If a top-up fails, your wallet is refunded automatically.</span>
        </div>
      </aside>
    </div>

    <!-- ───────────── RECENT ORDERS ───────────── -->
    <div class="mt-10">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-bold">Recent airtime orders</h2>
        <span class="text-xs text-gray-500">Last 10</span>
      </div>
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
          <div class="flex items-center gap-4 px-4 md:px-5 py-4 border-b border-white/5 last:border-0 hover:bg-white/[0.02] transition">
            <div class="w-10 h-10 rounded-xl bg-orange-500/15 text-orange-400 flex items-center justify-center flex-shrink-0">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
            </div>
            <div class="flex-1 min-w-0">
              <div class="text-sm font-medium truncate"><?= e(strtoupper($o['network'])) ?> · <?= e(formatPhone($o['phone'])) ?></div>
              <div class="text-xs text-gray-500 truncate"><?= e(date('M j, g:i A', strtotime($o['created_at']))) ?> · <span class="font-mono"><?= e($o['reference']) ?></span></div>
            </div>
            <div class="text-right flex-shrink-0">
              <div class="text-sm font-semibold"><?= formatNaira($o['amount']) ?></div>
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

<script>
window.ZF_REWARD = <?= json_encode([
    'unit'     => REWARD_NAIRA_PER_CODE,
    'progress' => $rewardProgressKobo / 100,
    'balance'  => $walletBalance / 100,
]) ?>;

 window.APP_URL = '<?= APP_URL ?>';
   console.log('APP_URL', window.APP_URL);

</script>
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
<script>
(function () {
  
  const R = window.ZF_REWARD;
  const $ = (s) => document.querySelector(s);
  const fmt = (n) => '₦' + Number(n).toLocaleString('en-NG');
  const amountEl = $('#amount'), phoneEl = $('#phone'), btn = $('#submit-btn');
  let submitting = false;

  function update() {
    const net = document.querySelector('input[name=network]:checked');
    const amt = Math.floor(parseFloat(amountEl.value) || 0);

    $('#sum-network').textContent = net ? net.dataset.label : '—';
    $('#sum-phone').textContent   = phoneEl.value.trim() || '—';
    $('#sum-amount').textContent  = fmt(amt);
    $('#sum-total').textContent   = fmt(amt);

    const after = R.balance - amt;
    $('#sum-after').textContent = amt ? (after >= 0 ? fmt(after) : 'Insufficient') : '—';
    $('#sum-after').className   = after < 0 && amt ? 'text-amber-400' : 'text-gray-400';
    $('#balance-warning').classList.toggle('hidden', !(amt > R.balance));
    if (!submitting) btn.disabled = amt > R.balance;

    // Rewards: running balance carries leftover spend between purchases.
    const total = R.progress + amt;
    const codes = Math.floor(total / R.unit);
    const rest  = total - codes * R.unit;
    $('#reward-bar').style.width = Math.min(100, (rest / R.unit) * 100) + '%';
    $('#reward-progress-label').textContent = fmt(rest);
    if (codes > 0) {
      $('#reward-text').innerHTML = "You'll earn <strong class=\"text-orange-300\">" + codes + ' free code' + (codes > 1 ? 's' : '') + '</strong> with this purchase.';
    } else if (R.progress > 0 || amt > 0) {
      $('#reward-text').textContent = 'Spend ' + fmt(R.unit - rest) + ' more to earn your next free code.';
    }

    document.querySelectorAll('.quick-amt').forEach((b) => {
      const on = Number(b.dataset.amount) === amt;
      b.classList.toggle('border-orange-400', on);
      b.classList.toggle('bg-orange-500/15', on);
    });
  }

  document.querySelectorAll('.quick-amt').forEach((b) =>
    b.addEventListener('click', () => { amountEl.value = b.dataset.amount; update(); }));
  document.querySelectorAll('input[name=network]').forEach((r) => r.addEventListener('change', update));
  amountEl.addEventListener('input', update);
  phoneEl.addEventListener('input', update);

  // Block double-submits (a double-click must never buy twice).
  $('#airtime-form').addEventListener('submit', function (e) {
    if (submitting) { e.preventDefault(); return; }
    submitting = true;
    btn.disabled = true;
    $('#submit-label').textContent = 'Processing…';
  });

  update();
})();
</script>
</body></html>
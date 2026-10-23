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

$networkPlans = $allPlans[$networkInput] ?? [];
$planTypes = [];
foreach ($networkPlans as $p) {
    if (($p['type'] ?? '') !== '') $planTypes[$p['type']] = true;
}
$planTypes = array_keys($planTypes);

$recent = $db->prepare("SELECT * FROM orders WHERE user_id = ? AND type = 'data' ORDER BY created_at DESC LIMIT 10");
$recent->execute([$userId]);
$recent = $recent->fetchAll();

$currentPage = 'data';
$pageTitle   = 'Buy Data';
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
      <div class="font-semibold">Buy Data</div>
      <div class="text-xs text-gray-400">Instant data bundles from your wallet</div>
    </div>
  </div>

  <div class="p-4 md:p-8 max-w-6xl mx-auto">

    <!-- Page header -->
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 mb-6">
      <div>
        <h1 class="text-2xl md:text-3xl font-bold tracking-tight">Buy Data</h1>
        <p class="text-sm text-gray-400 mt-1">Pick a network and a bundle. Delivered in seconds, with raffle codes on every purchase.</p>
      </div>
      <div class="inline-flex items-center gap-3 rounded-2xl border border-white/10 bg-white/[0.04] px-4 py-2.5 self-start">
        <div class="w-9 h-9 rounded-xl bg-blue-500/15 flex items-center justify-center text-blue-400">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>
        </div>
        <div>
          <div class="text-[11px] uppercase tracking-wider text-gray-400">Wallet balance</div>
          <div class="font-bold leading-tight"><?= formatNaira($walletBalance) ?></div>
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
      <div class="lg:col-span-3 card p-5 md:p-7 space-y-7">

        <!-- Step 1: network tabs (reload the plan list, keep the typed number) -->
        <section>
          <div class="flex items-center gap-3 mb-3">
            <span class="w-6 h-6 rounded-full bg-orange-500/20 text-orange-400 text-xs font-bold flex items-center justify-center">1</span>
            <h2 class="text-sm font-semibold">Choose network</h2>
          </div>
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
            <?php foreach ($networks as $key => $label): ?>
              <a href="?network=<?= $key ?>" data-network="<?= $key ?>"
                 class="net-tab flex items-center justify-center gap-2 py-3 px-2 rounded-xl border text-sm font-medium transition
                        <?= $networkInput===$key ? 'border-orange-400 bg-orange-500/10 ring-1 ring-orange-400/40' : 'border-white/10 bg-white/[0.04] hover:bg-white/10' ?>">
                <span class="w-2.5 h-2.5 rounded-full flex-shrink-0" style="background:<?= $networkColors[$key] ?>"></span>
                <span class="truncate"><?= e($label) ?></span>
              </a>
            <?php endforeach; ?>
          </div>
        </section>

        <form method="POST" id="data-form" class="space-y-7" autocomplete="off">
          <?= csrfField() ?>
          <input type="hidden" name="network" value="<?= e($networkInput) ?>">
          <input type="hidden" name="expected_price" id="expected-price" value="">

          <!-- Step 2 -->
          <section>
            <div class="flex items-center gap-3 mb-3">
              <span class="w-6 h-6 rounded-full bg-orange-500/20 text-orange-400 text-xs font-bold flex items-center justify-center">2</span>
              <h2 class="text-sm font-semibold">Phone number</h2>
            </div>
            <input type="tel" name="phone" id="phone" required inputmode="numeric" value="<?= e($phoneInput) ?>" placeholder="08012345678"
                   class="w-full bg-white/[0.04] border border-white/10 rounded-xl px-4 py-3.5 text-base focus:outline-none focus:border-orange-400 focus:ring-1 focus:ring-orange-400/40 placeholder:text-gray-600">
          </section>

          <!-- Step 3 -->
          <section>
            <div class="flex items-center justify-between gap-3 mb-3">
              <div class="flex items-center gap-3">
                <span class="w-6 h-6 rounded-full bg-orange-500/20 text-orange-400 text-xs font-bold flex items-center justify-center">3</span>
                <h2 class="text-sm font-semibold">Select a bundle</h2>
              </div>
              <span class="text-xs text-gray-500"><?= count($networkPlans) ?> available</span>
            </div>

            <?php if (count($planTypes) > 1): ?>
            <div class="flex flex-wrap gap-2 mb-3" id="type-filters">
              <button type="button" data-type="" class="type-chip px-3.5 py-1.5 rounded-full text-xs font-medium border border-orange-400 bg-orange-500/15 transition">All</button>
              <?php foreach ($planTypes as $t): ?>
                <button type="button" data-type="<?= e($t) ?>" class="type-chip px-3.5 py-1.5 rounded-full text-xs font-medium border border-white/10 bg-white/[0.04] hover:bg-white/10 transition"><?= e($t) ?></button>
              <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if ($networkPlans): ?>
            <div class="grid sm:grid-cols-2 gap-2.5" id="plan-grid">
              <?php foreach ($networkPlans as $plan): ?>
              <label class="plan-card cursor-pointer min-w-0" data-type="<?= e($plan['type'] ?? '') ?>">
                <input type="radio" name="plan_code" value="<?= e($plan['code']) ?>"
                       data-name="<?= e($plan['name']) ?>" data-price="<?= (int) $plan['price'] / 100 ?>" data-kobo="<?= (int) $plan['price'] ?>"
                       <?= $planInput===$plan['code']?'checked':'' ?> required class="peer sr-only">
                <div class="h-full flex flex-col justify-between gap-3 p-3.5 rounded-xl border border-white/10 bg-white/[0.04] transition
                            hover:bg-white/10 peer-checked:border-orange-400 peer-checked:bg-orange-500/10 peer-checked:ring-1 peer-checked:ring-orange-400/40 peer-focus-visible:ring-2 peer-focus-visible:ring-orange-400">
                  <div class="text-sm font-medium leading-snug"><?= e($plan['name']) ?></div>
                  <div class="flex items-center justify-between gap-2">
                    <span class="text-base font-bold text-orange-400"><?= formatNaira($plan['price']) ?></span>
                    <?php if (!empty($plan['type'])): ?><span class="text-[10px] uppercase tracking-wide px-2 py-0.5 rounded-full bg-white/10 text-gray-400"><?= e($plan['type']) ?></span><?php endif; ?>
                  </div>
                </div>
              </label>
              <?php endforeach; ?>
            </div>
            <?php else: ?>
              <div class="rounded-xl border border-dashed border-white/10 p-8 text-center">
                <div class="text-2xl mb-2">📡</div>
                <div class="text-sm text-gray-300">Bundles are unavailable right now</div>
                <div class="text-xs text-gray-500 mt-1">Please try again in a few minutes.</div>
              </div>
            <?php endif; ?>
            <p id="balance-warning" class="hidden text-xs text-amber-400 mt-3">This bundle costs more than your wallet balance. Deposit funds to continue.</p>
          </section>

          <button type="submit" id="submit-btn" <?= $networkPlans ? '' : 'disabled' ?>
                  class="btn bg-orange-400 hover:bg-orange-500 disabled:opacity-50 disabled:cursor-not-allowed text-white w-full justify-center py-3.5 font-semibold text-base rounded-xl">
            <span id="submit-label">Buy Data</span>
          </button>
        </form>
      </div>

      <!-- ───────────── SUMMARY ───────────── -->
      <aside class="lg:col-span-2 space-y-4 lg:sticky lg:top-6">

        <div class="card p-5 md:p-6">
          <h3 class="text-sm font-semibold mb-4">Order summary</h3>
          <dl class="space-y-3 text-sm">
            <div class="flex justify-between gap-4"><dt class="text-gray-400">Network</dt><dd class="font-medium"><?= e($networks[$networkInput]) ?></dd></div>
            <div class="flex justify-between gap-4"><dt class="text-gray-400">Recipient</dt><dd class="font-medium truncate" id="sum-phone">—</dd></div>
            <div class="flex justify-between gap-4"><dt class="text-gray-400">Bundle</dt><dd class="font-medium text-right" id="sum-plan">—</dd></div>
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
          <span>If a bundle fails to deliver, your wallet is refunded automatically.</span>
        </div>
      </aside>
    </div>

    <!-- ───────────── RECENT ORDERS ───────────── -->
    <div class="mt-10">
      <div class="flex items-center justify-between mb-4">
        <h2 class="text-lg font-bold">Recent data orders</h2>
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
            <div class="w-10 h-10 rounded-xl bg-blue-500/15 text-blue-400 flex items-center justify-center flex-shrink-0">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01M4.929 12.9a10.5 10.5 0 0114.142 0M1.5 9.75a15 15 0 0121 0"/></svg>
            </div>
            <div class="flex-1 min-w-0">
              <div class="text-sm font-medium truncate"><?= e(strtoupper($o['network'])) ?> · <?= e($o['plan_name']) ?></div>
              <div class="text-xs text-gray-500 truncate"><?= e(formatPhone($o['phone'])) ?> · <?= e(date('M j, g:i A', strtotime($o['created_at']))) ?> · <span class="font-mono"><?= e($o['reference']) ?></span></div>
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
            <div class="text-xs text-gray-600 mt-1">Your bundles will appear here.</div>
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
</script>
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
<script>
(function () {
  const R = window.ZF_REWARD;
  const $ = (s) => document.querySelector(s);
  const fmt = (n) => '₦' + Number(n).toLocaleString('en-NG');
  const phoneEl = $('#phone'), btn = $('#submit-btn');
  let submitting = false;

  function update() {
    const sel   = document.querySelector('input[name=plan_code]:checked');
    const price = sel ? Number(sel.dataset.price) : 0;

    $('#expected-price').value  = sel ? sel.dataset.kobo : '';
    $('#sum-phone').textContent = phoneEl.value.trim() || '—';
    $('#sum-plan').textContent  = sel ? sel.dataset.name : '—';
    $('#sum-total').textContent = fmt(price);

    const after = R.balance - price;
    $('#sum-after').textContent = sel ? (after >= 0 ? fmt(after) : 'Insufficient') : '—';
    $('#sum-after').className   = sel && after < 0 ? 'text-amber-400' : 'text-gray-400';
    const warn = $('#balance-warning');
    if (warn) warn.classList.toggle('hidden', !(sel && price > R.balance));
    if (!submitting && btn && document.querySelector('input[name=plan_code]')) btn.disabled = !!(sel && price > R.balance);

    // Rewards: running balance carries leftover spend between purchases.
    const total = R.progress + price;
    const codes = Math.floor(total / R.unit);
    const rest  = total - codes * R.unit;
    $('#reward-bar').style.width = Math.min(100, (rest / R.unit) * 100) + '%';
    $('#reward-progress-label').textContent = fmt(rest);
    if (codes > 0) {
      $('#reward-text').innerHTML = "You'll earn <strong class=\"text-orange-300\">" + codes + ' free code' + (codes > 1 ? 's' : '') + '</strong> with this purchase.';
    } else if (R.progress > 0 || price > 0) {
      $('#reward-text').textContent = 'Spend ' + fmt(R.unit - rest) + ' more to earn your next free code.';
    }
  }

  document.querySelectorAll('input[name=plan_code]').forEach((r) => r.addEventListener('change', update));
  phoneEl.addEventListener('input', update);

  // Plan type filter chips (e.g. SME / Gifting / Corporate)
  document.querySelectorAll('.type-chip').forEach((chip) => chip.addEventListener('click', () => {
    const t = chip.dataset.type;
    document.querySelectorAll('.type-chip').forEach((c) => {
      const on = c === chip;
      c.classList.toggle('border-orange-400', on);
      c.classList.toggle('bg-orange-500/15', on);
      c.classList.toggle('border-white/10', !on);
      c.classList.toggle('bg-white/[0.04]', !on);
    });
    document.querySelectorAll('.plan-card').forEach((card) => {
      const show = !t || card.dataset.type === t;
      card.classList.toggle('hidden', !show);
      // a hidden-but-checked radio must not be submitted by accident
      if (!show) card.querySelector('input').checked = false;
    });
    update();
  }));

  // Switching network keeps the number you already typed.
  document.querySelectorAll('.net-tab').forEach((a) => a.addEventListener('click', (e) => {
    e.preventDefault();
    const p = phoneEl.value.trim();
    location.href = '?network=' + a.dataset.network + (p ? '&phone=' + encodeURIComponent(p) : '');
  }));

  // Block double-submits (a double-click must never buy twice).
  $('#data-form').addEventListener('submit', function (e) {
    if (submitting) { e.preventDefault(); return; }
    submitting = true;
    btn.disabled = true;
    $('#submit-label').textContent = 'Processing…';
  });

  update();
})();
</script>
</body></html>
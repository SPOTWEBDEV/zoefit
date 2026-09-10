<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../lib/paystack.php';
require_once __DIR__ . '/../lib/vtu_provider.php';

$auth   = requireUser();
$userId = $auth['id'];
$db     = getDB();

$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

$walletBalance = (int) ($user['wallet_balance'] ?? 0);

$networks = ['mtn' => 'MTN', 'glo' => 'Glo', 'airtel' => 'Airtel', '9mobile' => '9mobile'];
$allPlans = getDataPlans();

$errors  = [];
$success = null;
$phoneInput   = '';
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

                        $orderStmt = $db->prepare("INSERT INTO orders (user_id, type, network, phone, plan_code, plan_name, amount, reference, status)
                            VALUES (?,?,?,?,?,?,?,?,'pending')");
                        $orderStmt->execute([$userId, 'data', $networkInput, $normalizedPhone, $plan['code'], $plan['name'], $amountKobo, $reference]);
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

                            $success = 'Data purchase successful! ' . $plan['name'] . ' sent to ' . formatPhone($normalizedPhone) . '.';
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
                    $errors[] = 'Something went wrong processing your order. Please try again.';
                }
            }
        }
    }
}

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
      <div class="text-xs text-gray-400">Wallet balance: <?= formatNaira($walletBalance) ?></div>
    </div>
  </div>

  <div class="p-4 md:p-6 max-w-2xl">

    <?php if ($errors): ?>
      <div class="mb-6 rounded-xl border border-red-500/30 bg-red-500/10 text-red-300 p-4 text-sm">
        <?php foreach ($errors as $err): ?><div>• <?= e($err) ?></div><?php endforeach; ?>
      </div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="mb-6 rounded-xl border border-emerald-500/30 bg-emerald-500/10 text-emerald-300 p-4 text-sm"><?= e($success) ?></div>
    <?php endif; ?>

    <div class="card p-6 mb-6">
      <div class="flex items-center gap-3 mb-6">
        <div class="w-11 h-11 rounded-xl bg-blue-500/15 flex items-center justify-center">
          <svg class="w-6 h-6 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.111 16.404a5.5 5.5 0 017.778 0M12 20h.01M4.929 12.9a10.5 10.5 0 0114.142 0M1.5 9.75a15 15 0 0121 0"/></svg>
        </div>
        <div>
          <div class="font-bold text-lg">Buy Data</div>
          <div class="text-xs text-gray-400">Pick a network, then a plan — paid from your wallet</div>
        </div>
      </div>

      <!-- Network selector reloads plan list -->
      <div class="mb-5">
        <label class="text-sm text-gray-400 mb-2 block">Network</label>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
          <?php foreach ($networks as $key => $label): ?>
            <a href="?network=<?= $key ?>" class="text-center py-3 px-1 rounded-xl border text-sm font-medium truncate <?= $networkInput===$key ? 'border-orange-400 bg-orange-500/15' : 'border-white/10 bg-white/5 hover:bg-white/10' ?>">
              <?= e($label) ?>
            </a>
          <?php endforeach; ?>
        </div>
      </div>

      <form method="POST" class="space-y-4">
        <?= csrfField() ?>
        <input type="hidden" name="network" value="<?= e($networkInput) ?>">

        <div>
          <label class="text-sm text-gray-400 mb-1 block">Phone Number</label>
          <input type="tel" name="phone" required value="<?= e($phoneInput) ?>" placeholder="08012345678"
                 class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 focus:outline-none focus:border-orange-400">
        </div>

        <div>
          <label class="text-sm text-gray-400 mb-2 block">Select Plan</label>
          <div class="space-y-2">
            <?php foreach (($allPlans[$networkInput] ?? []) as $plan): ?>
            <label class="flex items-center justify-between gap-2 p-3 rounded-xl border border-white/10 bg-white/5 hover:bg-white/10 cursor-pointer">
              <div class="flex items-center gap-3 min-w-0">
                <input type="radio" name="plan_code" value="<?= e($plan['code']) ?>" <?= $planInput===$plan['code']?'checked':'' ?> required class="accent-orange-400 flex-shrink-0">
                <span class="text-sm font-medium truncate"><?= e($plan['name']) ?></span>
              </div>
              <span class="text-sm font-semibold text-orange-400 flex-shrink-0 whitespace-nowrap"><?= formatNaira($plan['price']) ?></span>
            </label>
            <?php endforeach; ?>
          </div>
        </div>

        <button type="submit" class="btn bg-orange-400 hover:bg-orange-500 text-white w-full justify-center py-3 font-semibold">
          Buy Data →
        </button>
      </form>
    </div>

    <div>
      <h2 class="text-lg font-bold mb-4">Recent Data Orders</h2>
      <div class="card">
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
          <div class="flex items-center gap-4 p-4 border-b border-white/5 last:border-0">
            <div class="w-10 h-10 rounded-xl bg-blue-500/15 flex items-center justify-center text-lg">📶</div>
            <div class="flex-1 min-w-0">
              <div class="text-sm font-medium truncate"><?= e(strtoupper($o['network'])) ?> — <?= e($o['plan_name']) ?></div>
              <div class="text-xs text-gray-400 truncate"><?= e(formatPhone($o['phone'])) ?> · <span class="font-mono"><?= e($o['reference']) ?></span></div>
            </div>
            <div class="text-right">
              <div class="text-sm font-semibold"><?= formatNaira($o['amount']) ?></div>
              <span class="badge <?= $badgeClass ?>"><?= e(ucfirst($o['status'])) ?></span>
            </div>
          </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="p-8 text-center text-gray-500">No data orders yet</div>
        <?php endif; ?>
      </div>
    </div>

  </div>
</div>

<script src="<?= APP_URL ?>/assets/js/app.js"></script>
</body></html>
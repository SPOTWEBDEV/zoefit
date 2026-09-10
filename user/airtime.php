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

$errors  = [];
$success = null;
$phoneInput  = '';
$amountInput = '';
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
                    // Reserve funds up front, then refund automatically if delivery fails.
                    $db->prepare("UPDATE users SET wallet_balance = wallet_balance - ? WHERE id = ?")
                       ->execute([$amountKobo, $userId]);

                    $newBalance = $lockedBalance - $amountKobo;

                    $orderStmt = $db->prepare("INSERT INTO orders (user_id, type, network, phone, amount, reference, status)
                        VALUES (?,?,?,?,?,?,'pending')");
                    $orderStmt->execute([$userId, 'airtime', $networkInput, $normalizedPhone, $amountKobo, $reference]);
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

                        $success = 'Airtime purchase successful! ' . formatNaira($amountKobo) . ' sent to ' . formatPhone($normalizedPhone) . '.';
                    } else {
                        // Delivery failed — refund the reserved amount.
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
                $errors[] = 'Something went wrong processing your order. Please try again.';
            }
        }
    }
}

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
        <div class="w-11 h-11 rounded-xl bg-orange-500/15 flex items-center justify-center">
          <svg class="w-6 h-6 text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/></svg>
        </div>
        <div>
          <div class="font-bold text-lg">Buy Airtime</div>
          <div class="text-xs text-gray-400">Instant top-up, paid from your wallet</div>
        </div>
      </div>

      <form method="POST" class="space-y-4">
        <?= csrfField() ?>

        <div>
          <label class="text-sm text-gray-400 mb-2 block">Network</label>
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
            <?php foreach ($networks as $key => $label): ?>
            <label class="cursor-pointer min-w-0">
              <input type="radio" name="network" value="<?= $key ?>" <?= $networkInput===$key?'checked':'' ?> class="peer hidden">
              <div class="text-center py-3 px-1 rounded-xl border border-white/10 bg-white/5 peer-checked:border-orange-400 peer-checked:bg-orange-500/15 text-sm font-medium truncate">
                <?= e($label) ?>
              </div>
            </label>
            <?php endforeach; ?>
          </div>
        </div>

        <div>
          <label class="text-sm text-gray-400 mb-1 block">Phone Number</label>
          <input type="tel" name="phone" required value="<?= e($phoneInput) ?>" placeholder="08012345678"
                 class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 focus:outline-none focus:border-orange-400">
        </div>

        <div>
          <label class="text-sm text-gray-400 mb-1 block">Amount (₦)</label>
          <input type="number" name="amount" min="50" max="50000" required value="<?= e($amountInput) ?>" placeholder="500"
                 class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-lg font-semibold focus:outline-none focus:border-orange-400">
          <div class="flex flex-wrap gap-2 mt-3">
            <?php foreach ([100, 200, 500, 1000, 2000] as $q): ?>
              <button type="button" onclick="document.querySelector('[name=amount]').value='<?= $q ?>'"
                      class="px-3 py-1.5 rounded-lg bg-white/5 hover:bg-white/10 text-xs border border-white/10 whitespace-nowrap">₦<?= number_format($q) ?></button>
            <?php endforeach; ?>
          </div>
        </div>

        <button type="submit" class="btn bg-orange-400 hover:bg-orange-500 text-white w-full justify-center py-3 font-semibold">
          Buy Airtime →
        </button>
      </form>
    </div>

    <div>
      <h2 class="text-lg font-bold mb-4">Recent Airtime Orders</h2>
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
            <div class="w-10 h-10 rounded-xl bg-orange-500/15 flex items-center justify-center text-lg">📞</div>
            <div class="flex-1 min-w-0">
              <div class="text-sm font-medium truncate"><?= e(strtoupper($o['network'])) ?> — <?= e(formatPhone($o['phone'])) ?></div>
              <div class="text-xs text-gray-400 font-mono truncate"><?= e($o['reference']) ?></div>
            </div>
            <div class="text-right">
              <div class="text-sm font-semibold"><?= formatNaira($o['amount']) ?></div>
              <span class="badge <?= $badgeClass ?>"><?= e(ucfirst($o['status'])) ?></span>
            </div>
          </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="p-8 text-center text-gray-500">No airtime orders yet</div>
        <?php endif; ?>
      </div>
    </div>

  </div>
</div>

<script src="<?= APP_URL ?>/assets/js/app.js"></script>
</body></html>
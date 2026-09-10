<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../lib/paystack.php';

$auth   = requireUser();
$userId = $auth['id'];
$db     = getDB();

$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

$errors  = [];
$amountInput = '';

if (isPost()) {
    if (!verifyCsrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $errors[] = 'Your session expired, please try again.';
    } else {
        $amountInput = trim($_POST['amount'] ?? '');
        $email       = trim($_POST['email'] ?? ($user['email'] ?? ''));
        $amountNaira = (float) $amountInput;

        if ($amountInput === '' || $amountNaira <= 0) {
            $errors[] = 'Please enter a valid amount.';
        } elseif ($amountNaira < 100) {
            $errors[] = 'Minimum deposit is ₦100.';
        } elseif ($amountNaira > 500000) {
            $errors[] = 'Maximum deposit is ₦500,000 per transaction.';
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please provide a valid email address for your payment receipt.';
        }

        if (!$errors) {
            $amountKobo  = (int) round($amountNaira * 100);
            $reference   = generateDepositReference($userId);
            $callbackUrl = APP_URL . '/user/deposit-callback.php';

            $db->prepare("INSERT INTO deposits (user_id, reference, amount, status) VALUES (?,?,?,'pending')")
               ->execute([$userId, $reference, $amountKobo]);

            $resp = paystackInitializeTransaction($email, $amountKobo, $reference, $callbackUrl, [
                'user_id' => $userId,
                'purpose' => 'wallet_deposit',
            ]);

            if (!empty($resp['status']) && !empty($resp['data']['authorization_url'])) {
                auditLog('user', $userId, 'deposit_initiated', "Initiated deposit of ₦{$amountNaira} (ref {$reference})", 'deposit', 0);
                redirect($resp['data']['authorization_url']);
            } else {
                $db->prepare("UPDATE deposits SET status='failed', gateway_response=? WHERE reference=?")
                   ->execute([$resp['message'] ?? 'Failed to initialize transaction', $reference]);
                $errors[] = $resp['message'] ?? 'Could not start payment right now. Please try again shortly.';
            }
        }
    }
}

// Recent deposit history
$recent = $db->prepare("SELECT * FROM deposits WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
$recent->execute([$userId]);
$recent = $recent->fetchAll();

$currentPage = 'deposit';
$pageTitle   = 'Deposit Funds';
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
      <div class="font-semibold">Deposit Funds</div>
      <div class="text-xs text-gray-400">Fund your wallet securely via Paystack</div>
    </div>
  </div>

  <div class="p-4 md:p-6 max-w-3xl">

    <?php if ($errors): ?>
      <div class="mb-6 rounded-xl border border-red-500/30 bg-red-500/10 text-red-300 p-4 text-sm">
        <?php foreach ($errors as $err): ?><div>• <?= e($err) ?></div><?php endforeach; ?>
      </div>
    <?php endif; ?>

    <div class="card p-6 mb-6">
      <div class="flex items-center gap-3 mb-6">
        <div class="w-11 h-11 rounded-xl bg-emerald-500/15 flex items-center justify-center">
          <svg class="w-6 h-6 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V6m0 12v-2m9-4a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
        </div>
        <div>
          <div class="font-bold text-lg">Fund Your Wallet</div>
          <div class="text-xs text-gray-400">Secured by Paystack — you'll be redirected to complete payment</div>
        </div>
      </div>

      <form method="POST" class="space-y-4">
        <?= csrfField() ?>

        <div>
          <label class="text-sm text-gray-400 mb-1 block">Amount (₦)</label>
          <div class="relative">
            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400">₦</span>
            <input type="number" name="amount" min="100" max="500000" step="1" required
                   value="<?= e($amountInput) ?>"
                   placeholder="1,000"
                   class="w-full bg-white/5 border border-white/10 rounded-xl pl-9 pr-4 py-3 text-lg font-semibold focus:outline-none focus:border-orange-400">
          </div>
          <div class="flex flex-wrap gap-2 mt-3">
            <?php foreach ([500, 1000, 2000, 5000, 10000] as $q): ?>
              <button type="button" onclick="document.querySelector('[name=amount]').value='<?= $q ?>'"
                      class="px-3 py-1.5 rounded-lg bg-white/5 hover:bg-white/10 text-xs border border-white/10 whitespace-nowrap">
                ₦<?= number_format($q) ?>
              </button>
            <?php endforeach; ?>
          </div>
        </div>

        <div>
          <label class="text-sm text-gray-400 mb-1 block">Email (for payment receipt)</label>
          <input type="email" name="email" required
                 value="<?= e($_POST['email'] ?? ($user['email'] ?? '')) ?>"
                 placeholder="you@example.com"
                 class="w-full bg-white/5 border border-white/10 rounded-xl px-4 py-3 focus:outline-none focus:border-orange-400">
        </div>

        <button type="submit" class="btn bg-orange-400 hover:bg-orange-500 text-white w-full justify-center py-3 font-semibold">
          Proceed to Payment →
        </button>
      </form>
    </div>

    <!-- Recent Deposits -->
    <div>
      <h2 class="text-lg font-bold mb-4">Recent Deposits</h2>
      <div class="card">
        <?php if ($recent): ?>
          <?php foreach ($recent as $d): ?>
          <?php
            $badgeClass = match($d['status']) {
              'success'   => 'badge-success',
              'pending'   => 'badge-warning',
              default     => 'badge-danger',
            };
          ?>
          <div class="flex items-center gap-4 p-4 border-b border-white/5 last:border-0">
            <div class="w-10 h-10 rounded-xl bg-emerald-500/15 flex items-center justify-center text-lg">💳</div>
            <div class="flex-1 min-w-0">
              <div class="text-sm font-medium truncate"><?= formatNaira($d['amount']) ?></div>
              <div class="text-xs text-gray-400 font-mono truncate"><?= e($d['reference']) ?></div>
            </div>
            <div class="text-right">
              <span class="badge <?= $badgeClass ?>"><?= e(ucfirst($d['status'])) ?></span>
              <div class="text-xs text-gray-500 mt-1"><?= date('M j, g:ia', strtotime($d['created_at'])) ?></div>
            </div>
          </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="p-8 text-center text-gray-500">No deposits yet</div>
        <?php endif; ?>
      </div>
    </div>

  </div>
</div>

<script src="<?= APP_URL ?>/assets/js/app.js"></script>
</body></html>
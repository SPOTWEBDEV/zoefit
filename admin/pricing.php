<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../lib/vtu_provider.php';   // also loads lib/settings.php

// ⚠ Use whatever guard your other admin pages use. I haven't seen them, so I assumed requireAdmin().
$auth    = requireAdmin();
$adminId = (int) $auth['id'];
$db      = getDB();

$errors  = [];
$success = null;

$oldAirtime = getAirtimeFeeNaira();
$oldData    = getDataProfitNaira();
$oldReward  = getRewardNairaPerCode();

if (isPost()) {
    if (!verifyCsrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $errors[] = 'Your session expired, please try again.';
    } else {
        $air  = trim((string) ($_POST['airtime_fee'] ?? ''));
        $data = trim((string) ($_POST['data_profit'] ?? ''));
        $rate = trim((string) ($_POST['reward_rate'] ?? ''));

        if ($air === '' || !preg_match('/^\d+$/', $air)) {
            $errors[] = 'Airtime fee must be a whole number of naira (0 or more).';
        } elseif ((int) $air > 1000) {
            $errors[] = 'Airtime fee can\'t be more than ₦1,000. Is that a typo?';
        }
        if ($data === '' || !preg_match('/^\d+$/', $data)) {
            $errors[] = 'Data profit must be a whole number of naira (0 or more).';
        } elseif ((int) $data > 5000) {
            $errors[] = 'Data profit can\'t be more than ₦5,000. Is that a typo?';
        }

        if ($rate === '' || !preg_match('/^\d+$/', $rate)) {
            $errors[] = 'The ticket price must be a whole number of naira.';
        } elseif ((int) $rate < 10 || (int) $rate > 100000) {
            $errors[] = 'The ticket price must be between ₦10 and ₦100,000. Is that a typo?';
        }

        if (!$errors) {
            try {
                saveSiteSetting('airtime_fee_naira', (string) (int) $air, $adminId);
                saveSiteSetting('data_profit_naira', (string) (int) $data, $adminId);
                saveSiteSetting('reward_naira_per_code', (string) (int) $rate, $adminId);
                siteSettings(true);
                auditLog('admin', $adminId, 'pricing_update',
                    "Airtime fee ₦{$oldAirtime} -> ₦{$air}; data profit ₦{$oldData} -> ₦{$data}; ₦ per raffle ticket {$oldReward} -> {$rate}", 'settings', 0);
                $success = 'Saved. The new prices apply to the next purchase.';
            } catch (\Throwable $e) {
                error_log('[PRICING] ' . $e->getMessage());
                $errors[] = 'Could not save. Have you created the site_settings table? (see README)';
            }
        }
    }
}

$curAir  = (isPost() && $errors) ? ($_POST['airtime_fee'] ?? '') : (string) (int) getAirtimeFeeNaira();
$curData = (isPost() && $errors) ? ($_POST['data_profit'] ?? '') : (string) (int) getDataProfitNaira();
$curRate = (isPost() && $errors) ? ($_POST['reward_rate'] ?? '') : (string) getRewardNairaPerCode();
$notSet  = !siteSettingIsSet('airtime_fee_naira') && !siteSettingIsSet('data_profit_naira');

// Provider plans, only for the read-only price preview
$allPlans = getDataPlans();
$planCount = 0;
foreach ($allPlans as $list) $planCount += count($list);
$networks = ['mtn' => 'MTN', 'glo' => 'Glo', 'airtel' => 'Airtel', '9mobile' => '9mobile'];
$sampleCost = 430;
foreach ($allPlans as $list) { if ($list) { $sampleCost = (int) round($list[0]['cost'] / 100); break; } }

// Profit stats (successful orders only; refunded/pending are excluded)
$stats = [];
try {
    $q = $db->query("SELECT type,
            COALESCE(SUM(CASE WHEN DATE(created_at) = CURDATE() THEN profit_amount END),0) AS today,
            COALESCE(SUM(CASE WHEN created_at >= DATE_FORMAT(NOW(), '%Y-%m-01') THEN profit_amount END),0) AS month,
            COALESCE(SUM(profit_amount),0) AS total
        FROM orders WHERE status = 'success' AND profit_amount IS NOT NULL GROUP BY type");
    foreach ($q as $r) $stats[$r['type']] = $r;
} catch (\Throwable $e) {
    error_log('[PRICING] stats: ' . $e->getMessage());
}
$sum = fn(string $k) => (int) (($stats['data'][$k] ?? 0) + ($stats['airtime'][$k] ?? 0));

$aPage     = 'pricing';
$pageTitle = 'Pricing & Profit';
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

<?php include __DIR__ . '/../components/admin-sidebar.php'; ?>

<div class="main-content">
  <div class="topbar">
    <button onclick="toggleSidebar()" class="md:hidden text-gray-400 text-2xl mr-3">☰</button>
    <h1 class="text-xl font-bold">Pricing &amp; Profit</h1>
    <div class="text-sm text-gray-400">Your fee and profit</div>
  </div>

  <div class="p-4 md:p-8 max-w-3xl mx-auto">

    <?php if ($errors): ?>
      <div class="mb-6 rounded-2xl border border-red-500/30 bg-red-500/10 text-red-200 p-4 text-sm space-y-1" role="alert">
        <?php foreach ($errors as $err): ?><div>• <?= e($err) ?></div><?php endforeach; ?>
      </div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="mb-6 rounded-2xl border border-emerald-500/30 bg-emerald-500/10 text-emerald-200 p-4 text-sm" role="status"><?= e($success) ?></div>
    <?php endif; ?>
    <?php if ($notSet && !$success): ?>
      <div class="mb-6 rounded-2xl border border-amber-500/30 bg-amber-500/10 text-amber-200 p-4 text-sm" role="status">
        You haven't set your prices yet. Until you save below, users pay no fee on airtime and no profit is added to data.
      </div>
    <?php endif; ?>

    <!-- Profit so far -->
    <div class="grid grid-cols-3 gap-3 md:gap-4 mb-8">
      <?php foreach (['today' => 'Today', 'month' => 'This month', 'total' => 'All time'] as $k => $label): ?>
      <div class="card p-4 md:p-5">
        <div class="text-[11px] md:text-xs uppercase tracking-wider text-gray-400">Profit · <?= $label ?></div>
        <div class="text-lg md:text-2xl font-bold mt-1 text-emerald-400"><?= formatNaira($sum($k)) ?></div>
      </div>
      <?php endforeach; ?>
    </div>

    <form method="POST" id="pricing-form" class="space-y-5">
      <?= csrfField() ?>

      <!-- Airtime -->
      <div class="card p-5 md:p-7">
        <div class="flex items-start gap-4">
          <div class="w-11 h-11 rounded-xl bg-orange-500/15 text-orange-400 flex items-center justify-center flex-shrink-0 text-xl">📞</div>
          <div class="flex-1 min-w-0">
            <h2 class="font-bold text-lg">Airtime fee</h2>
            <p class="text-sm text-gray-400 mt-1">A fixed fee added to every airtime purchase. <strong class="text-gray-200">Users can see it</strong> on their order summary as "Service fee".</p>

            <label for="airtime_fee" class="block text-sm text-gray-400 mt-5 mb-1.5">Fee per airtime purchase (₦)</label>
            <div class="relative max-w-xs">
              <span class="absolute left-4 top-1/2 -translate-y-1/2 text-xl font-semibold text-gray-500">₦</span>
              <input type="number" id="airtime_fee" name="airtime_fee" min="0" max="1000" step="1" required inputmode="numeric" value="<?= e((string) $curAir) ?>"
                     class="w-full bg-white/[0.04] border border-white/10 rounded-xl pl-10 pr-4 py-3 text-2xl font-bold focus:outline-none focus:border-orange-400 focus:ring-1 focus:ring-orange-400/40">
            </div>

            <div class="mt-4 rounded-xl bg-white/[0.04] border border-white/10 p-3.5 text-sm">
              <div class="text-gray-400 text-xs mb-1">Example</div>
              A user buys <strong>₦200</strong> airtime → they pay <strong class="text-orange-400" id="ex-air">₦200</strong>
              <span class="text-gray-500">(₦200 airtime + <span id="ex-air-fee">₦0</span> fee)</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Data -->
      <div class="card p-5 md:p-7">
        <div class="flex items-start gap-4">
          <div class="w-11 h-11 rounded-xl bg-blue-500/15 text-blue-400 flex items-center justify-center flex-shrink-0 text-xl">📶</div>
          <div class="flex-1 min-w-0">
            <h2 class="font-bold text-lg">Data profit</h2>
            <p class="text-sm text-gray-400 mt-1">A fixed profit added to every data plan. <strong class="text-gray-200">Users don't see it.</strong> They only see the total price.</p>

            <label for="data_profit" class="block text-sm text-gray-400 mt-5 mb-1.5">Profit per data plan (₦)</label>
            <div class="relative max-w-xs">
              <span class="absolute left-4 top-1/2 -translate-y-1/2 text-xl font-semibold text-gray-500">₦</span>
              <input type="number" id="data_profit" name="data_profit" min="0" max="5000" step="1" required inputmode="numeric" value="<?= e((string) $curData) ?>"
                     class="w-full bg-white/[0.04] border border-white/10 rounded-xl pl-10 pr-4 py-3 text-2xl font-bold focus:outline-none focus:border-orange-400 focus:ring-1 focus:ring-orange-400/40">
            </div>

            <div class="mt-4 rounded-xl bg-white/[0.04] border border-white/10 p-3.5 text-sm">
              <div class="text-gray-400 text-xs mb-1">Example</div>
              A plan that costs you <strong>₦<?= number_format($sampleCost) ?></strong> → users pay <strong class="text-orange-400" id="ex-data">₦<?= number_format($sampleCost) ?></strong>
              <span class="text-gray-500">(they won't see the profit)</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Raffle ticket reward -->
      <div class="card p-5 md:p-7">
        <div class="flex items-start gap-4">
          <div class="w-11 h-11 rounded-xl bg-emerald-500/15 text-emerald-400 flex items-center justify-center flex-shrink-0 text-xl">🎟️</div>
          <div class="flex-1 min-w-0">
            <h2 class="font-bold text-lg">Raffle ticket price</h2>
            <p class="text-sm text-gray-400 mt-1">Customers earn <strong class="text-gray-200">1 free raffle ticket</strong> for every amount they spend on airtime or data. Leftover spend carries over to their next purchase.</p>

            <label for="reward_rate" class="block text-sm text-gray-400 mt-5 mb-1.5">Spend this much to earn 1 ticket (₦)</label>
            <div class="relative max-w-xs">
              <span class="absolute left-4 top-1/2 -translate-y-1/2 text-xl font-semibold text-gray-500">₦</span>
              <input type="number" id="reward_rate" name="reward_rate" min="10" max="100000" step="1" required inputmode="numeric" value="<?= e((string) $curRate) ?>"
                     class="w-full bg-white/[0.04] border border-white/10 rounded-xl pl-10 pr-4 py-3 text-2xl font-bold focus:outline-none focus:border-orange-400 focus:ring-1 focus:ring-orange-400/40">
            </div>

            <div class="mt-4 rounded-xl bg-white/[0.04] border border-white/10 p-3.5 text-sm space-y-1">
              <div class="text-gray-400 text-xs mb-1">Examples</div>
              <div>A <strong>₦500</strong> purchase earns <strong class="text-orange-400" id="ex-r500">5 tickets</strong></div>
              <div>A <strong>₦2,000</strong> purchase earns <strong class="text-orange-400" id="ex-r2000">20 tickets</strong></div>
            </div>
            <p class="text-xs text-gray-500 mt-3">The higher the price, the fewer tickets customers earn. Changes apply to purchases made after you save. Tickets already given are not changed.</p>
          </div>
        </div>
      </div>

      <div class="flex justify-end">
        <button type="submit" class="btn bg-orange-400 hover:bg-orange-500 text-white px-8 py-3 font-semibold rounded-xl">Save</button>
      </div>
    </form>

    <!-- Read-only preview -->
    <details class="card mt-8 group">
      <summary class="cursor-pointer select-none px-5 md:px-7 py-4 text-sm font-semibold flex items-center justify-between">
        <span>See what users pay for every data plan <span class="text-gray-500 font-normal">(<?= number_format($planCount) ?> plans)</span></span>
        <span class="text-gray-500 group-open:rotate-180 transition">▾</span>
      </summary>
      <div class="px-5 md:px-7 pb-5">
        <?php if ($planCount === 0): ?>
          <div class="text-sm text-gray-400 py-4">Couldn't load plans from the provider right now. Check your API key.</div>
        <?php else: ?>
        <div class="max-h-96 overflow-y-auto overflow-x-auto rounded-xl border border-white/10">
          <table class="w-full text-sm min-w-[420px]">
            <thead class="sticky top-0 bg-[#111827]">
              <tr class="text-left text-xs uppercase tracking-wider text-gray-500">
                <th class="py-2.5 px-3 font-medium">Plan</th>
                <th class="py-2.5 px-3 font-medium text-right">You pay</th>
                <th class="py-2.5 px-3 font-medium text-right">Users pay</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($allPlans as $net => $list): foreach ($list as $p): $c = $p['cost'] / 100; ?>
              <tr class="border-t border-white/5 plan-row" data-cost="<?= e((string) $c) ?>">
                <td class="py-2 px-3"><div class="font-medium"><?= e($p['name']) ?></div><div class="text-xs text-gray-500"><?= e($networks[$net] ?? strtoupper($net)) ?></div></td>
                <td class="py-2 px-3 text-right text-gray-400">₦<?= number_format($c) ?></td>
                <td class="py-2 px-3 text-right font-semibold text-orange-400 plan-user">—</td>
              </tr>
              <?php endforeach; endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>
    </details>

  </div>
</div>

<script>window.APP_URL = '<?= APP_URL ?>';</script>
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
<script>
(function () {
  const $ = (s) => document.querySelector(s);
  const fmt = (n) => '₦' + Number(n).toLocaleString('en-NG');
  const sampleCost = <?= (int) $sampleCost ?>;
  function refresh() {
    const fee    = Math.max(0, parseInt($('#airtime_fee').value, 10) || 0);
    const profit = Math.max(0, parseInt($('#data_profit').value, 10) || 0);
    $('#ex-air').textContent     = fmt(200 + fee);
    $('#ex-air-fee').textContent = fmt(fee);
    $('#ex-data').textContent    = fmt(Math.ceil(sampleCost + profit));
    const rate = parseInt($('#reward_rate').value, 10) || 0;
    const t = (n) => (rate >= 1 ? Math.floor(n / rate) : 0) + ' ticket' + ((rate >= 1 && Math.floor(n / rate) === 1) ? '' : 's');
    $('#ex-r500').textContent  = t(500);
    $('#ex-r2000').textContent = t(2000);
    document.querySelectorAll('.plan-row').forEach((r) => {
      r.querySelector('.plan-user').textContent = fmt(Math.ceil(parseFloat(r.dataset.cost) + profit));   // same rounding as the server
    });
  }
  $('#pricing-form').addEventListener('input', refresh);
  refresh();
})();
</script>
</body></html>
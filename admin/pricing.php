<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../lib/paystack.php'; 
require_once __DIR__ . '/../lib/vtu_provider.php';   // also loads lib/settings.php

// ⚠ Use whatever guard your other admin pages use. I haven't seen them, so I assumed requireAdmin().
$auth    = requireAdmin();
$adminId = (int) $auth['id'];
$db      = getDB();

$networks = ['mtn' => 'MTN', 'glo' => 'Glo', 'airtel' => 'Airtel', '9mobile' => '9mobile'];
$allPlans = getDataPlans();
$planCount = 0;
foreach ($allPlans as $list) $planCount += count($list);

$errors  = [];
$success = null;

// Provider cost (naira) for every plan id, used to sanity-check overrides.
$costByCode = [];
foreach ($allPlans as $net => $list) foreach ($list as $p) $costByCode[$p['code']] = $p['cost'] / 100;

if (isPost()) {
    if (!verifyCsrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $errors[] = 'Your session expired, please try again.';
    } else {
        // ---- Data tiers ----
        $upIn     = (array) ($_POST['tier_up_to'] ?? []);
        $profitIn = (array) ($_POST['tier_profit'] ?? []);
        $tiers = [];
        $seen  = [];
        foreach ($upIn as $i => $upRaw) {
            $upRaw = trim((string) $upRaw);
            $prRaw = trim((string) ($profitIn[$i] ?? ''));
            if ($upRaw === '' && $prRaw === '') continue;
            if (!is_numeric($upRaw) || !is_numeric($prRaw) || (float) $upRaw <= 0 || (float) $prRaw < 0) {
                $errors[] = "Tier row " . ($i + 1) . ": enter a plan price above 0 and a profit of 0 or more.";
                continue;
            }
            if ((float) $prRaw > (float) $upRaw) {
                $errors[] = "Tier row " . ($i + 1) . ": profit (₦$prRaw) can't be more than the plan price (₦$upRaw). Typo?";
                continue;
            }
            if (isset($seen[(string) (float) $upRaw])) {
                $errors[] = "Two tiers have the same \"up to\" price (₦$upRaw).";
                continue;
            }
            $seen[(string) (float) $upRaw] = true;
            $tiers[] = [(float) $upRaw, (float) $prRaw];
        }
        if (!$tiers && !$errors) $errors[] = 'Add at least one profit tier.';
        usort($tiers, fn($a, $b) => $a[0] <=> $b[0]);

        $pctAbove = trim((string) ($_POST['percent_above'] ?? ''));
        if (!is_numeric($pctAbove) || $pctAbove < 0 || $pctAbove > 100) {
            $errors[] = 'Profit % above the top tier must be between 0 and 100.';
        }

        // ---- Airtime ----
        $airPct = trim((string) ($_POST['airtime_percent'] ?? ''));
        if (!is_numeric($airPct) || $airPct < 0 || $airPct > 20) {
            $errors[] = 'Airtime profit % must be between 0 and 20.';
        }

        // ---- Per-plan overrides (start from what's saved so plans not currently listed keep theirs) ----
        $overrides = getDataProfitConfig()['overrides'];
        if ($planCount > 0) {
            $ovIn = (array) ($_POST['override'] ?? []);
            foreach ($costByCode as $code => $cost) {
                $raw = trim((string) ($ovIn[$code] ?? ''));
                if ($raw === '') { unset($overrides[(string) $code]); continue; }
                if (!is_numeric($raw) || (float) $raw < 0) {
                    $errors[] = "Plan #$code: profit must be a number, 0 or more.";
                    continue;
                }
                if ((float) $raw > $cost) {
                    $errors[] = "Plan #$code: profit ₦$raw is more than the provider price ₦" . number_format($cost) . '. Typo?';
                    continue;
                }
                $overrides[(string) $code] = (float) $raw;
            }
        }

        if (!$errors) {
            try {
                saveSiteSetting('data_profit_tiers', json_encode(['tiers' => $tiers, 'percent_above' => (float) $pctAbove]), $adminId);
                saveSiteSetting('data_profit_overrides', json_encode((object) $overrides), $adminId);
                saveSiteSetting('airtime_profit_percent', (string) (float) $airPct, $adminId);
                siteSettings(true);

                auditLog('admin', $adminId, 'pricing_update',
                    'Data tiers: ' . json_encode($tiers) . "; above-top %: $pctAbove; overrides: " . count($overrides) . "; airtime %: $airPct",
                    'settings', 0);
                $success = 'Pricing saved. New prices apply to the next purchase immediately.';
                $allPlans = getDataPlans(); // re-price with the new rules
            } catch (\Throwable $e) {
                error_log('[PRICING] ' . $e->getMessage());
                $errors[] = 'Could not save. Have you created the site_settings table? (see README)';
            }
        }
    }
}

// ---- What to show in the form: posted values if there were errors, else the saved config ----
$cfg = getDataProfitConfig();
if ($errors && isPost()) {
    $viewTiers = [];
    foreach ((array) ($_POST['tier_up_to'] ?? []) as $i => $u) $viewTiers[] = [$u, $_POST['tier_profit'][$i] ?? ''];
    $viewPct   = $_POST['percent_above'] ?? $cfg['percent_above'];
    $viewAir   = $_POST['airtime_percent'] ?? getAirtimeProfitPercent();
    $viewOv    = (array) ($_POST['override'] ?? []);
} else {
    $viewTiers = $cfg['tiers'];
    $viewPct   = $cfg['percent_above'];
    $viewAir   = getAirtimeProfitPercent();
    $viewOv    = $cfg['overrides'];
}

// ---- Profit stats (successful orders only; refunded/pending are excluded) ----
$stats = [];
try {
    $q = $db->query("SELECT type,
            COUNT(*) AS cnt,
            COALESCE(SUM(CASE WHEN DATE(created_at) = CURDATE() THEN profit_amount END),0) AS today,
            COALESCE(SUM(CASE WHEN created_at >= DATE_FORMAT(NOW(), '%Y-%m-01') THEN profit_amount END),0) AS month,
            COALESCE(SUM(profit_amount),0) AS total
        FROM orders WHERE status = 'success' AND profit_amount IS NOT NULL GROUP BY type");
    foreach ($q as $r) $stats[$r['type']] = $r;
} catch (\Throwable $e) {
    error_log('[PRICING] stats: ' . $e->getMessage());
}
$sum = fn(string $k) => (int) (($stats['data'][$k] ?? 0) + ($stats['airtime'][$k] ?? 0));

$aPage     = 'pricing';   // highlights the item in components/admin-sidebar.php
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
    <div class="text-sm text-gray-400"><?= number_format($planCount) ?> data plans</div>
  </div>

  <div class="p-4 md:p-8 max-w-6xl mx-auto">

    <?php if ($errors): ?>
      <div class="mb-6 rounded-2xl border border-red-500/30 bg-red-500/10 text-red-200 p-4 text-sm space-y-1" role="alert">
        <?php foreach ($errors as $err): ?><div>• <?= e($err) ?></div><?php endforeach; ?>
      </div>
    <?php endif; ?>
    <?php if ($success): ?>
      <div class="mb-6 rounded-2xl border border-emerald-500/30 bg-emerald-500/10 text-emerald-200 p-4 text-sm" role="status"><?= e($success) ?></div>
    <?php endif; ?>

    <!-- Profit stats -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
      <?php foreach (['today' => 'Profit today', 'month' => 'Profit this month', 'total' => 'Profit all time'] as $k => $label): ?>
      <div class="card p-5">
        <div class="text-xs uppercase tracking-wider text-gray-400"><?= $label ?></div>
        <div class="text-2xl font-bold mt-1 text-emerald-400"><?= formatNaira($sum($k)) ?></div>
        <div class="text-xs text-gray-500 mt-1">Data <?= formatNaira((int) ($stats['data'][$k] ?? 0)) ?> · Airtime <?= formatNaira((int) ($stats['airtime'][$k] ?? 0)) ?></div>
      </div>
      <?php endforeach; ?>
    </div>

    <form method="POST" id="pricing-form" class="space-y-6">
      <?= csrfField() ?>

      <!-- Data tiers -->
      <div class="card p-5 md:p-7">
        <h2 class="font-bold text-lg">Data profit tiers</h2>
        <p class="text-sm text-gray-400 mt-1 mb-5">Your profit depends on the provider's price for the plan. The first tier whose "up to" price covers the plan wins.</p>

        <div id="tier-rows" class="space-y-2.5">
          <?php foreach ($viewTiers as $t): ?>
          <div class="tier-row flex items-center gap-3">
            <span class="text-sm text-gray-400 w-24 flex-shrink-0">Plan up to ₦</span>
            <input type="number" step="any" min="0" name="tier_up_to[]" value="<?= e((string) $t[0]) ?>" class="tier-up w-full min-w-0 bg-white/[0.04] border border-white/10 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-orange-400">
            <span class="text-sm text-gray-400 flex-shrink-0">profit ₦</span>
            <input type="number" step="any" min="0" name="tier_profit[]" value="<?= e((string) $t[1]) ?>" class="tier-profit w-full min-w-0 bg-white/[0.04] border border-white/10 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-orange-400">
            <button type="button" class="remove-tier text-gray-500 hover:text-red-400 text-xl px-1" aria-label="Remove tier">×</button>
          </div>
          <?php endforeach; ?>
        </div>
        <button type="button" id="add-tier" class="mt-3 text-sm text-orange-400 hover:text-orange-300 font-medium">+ Add tier</button>

        <div class="mt-6 pt-5 border-t border-white/10 flex flex-wrap items-center gap-3">
          <span class="text-sm text-gray-300">Plans above the top tier: profit</span>
          <input type="number" step="any" min="0" max="100" name="percent_above" id="pct-above" value="<?= e((string) $viewPct) ?>" class="w-24 bg-white/[0.04] border border-white/10 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-orange-400">
          <span class="text-sm text-gray-300">% of the provider price</span>
        </div>
      </div>

      <!-- Airtime -->
      <div class="card p-5 md:p-7">
        <h2 class="font-bold text-lg">Airtime profit</h2>
        <p class="text-sm text-gray-400 mt-1 mb-4">Users pay the face value. Enter the % of it you keep (your InstantMobile discount). It is recorded on every airtime order as profit; the remainder is recorded as provider price.</p>
        <div class="flex items-center gap-3">
          <input type="number" step="any" min="0" max="20" name="airtime_percent" value="<?= e((string) $viewAir) ?>" class="w-28 bg-white/[0.04] border border-white/10 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-orange-400">
          <span class="text-sm text-gray-300">% of airtime value</span>
        </div>
      </div>

      <!-- Per-plan -->
      <div class="card p-5 md:p-7">
        <div class="flex flex-col md:flex-row md:items-end md:justify-between gap-3 mb-5">
          <div>
            <h2 class="font-bold text-lg">Per-plan profit <span class="text-sm font-normal text-gray-500">(optional)</span></h2>
            <p class="text-sm text-gray-400 mt-1">Type a profit to override the tier for one plan. Leave blank to use the tier.</p>
          </div>
          <div class="flex gap-2">
            <select id="plan-network" class="bg-white/[0.04] border border-white/10 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-orange-400">
              <option value="">All networks</option>
              <?php foreach ($networks as $k => $l): ?><option value="<?= $k ?>"><?= e($l) ?></option><?php endforeach; ?>
            </select>
            <input type="search" id="plan-search" placeholder="Search plans" class="bg-white/[0.04] border border-white/10 rounded-xl px-3 py-2.5 text-sm w-40 focus:outline-none focus:border-orange-400">
          </div>
        </div>

        <?php if ($planCount === 0): ?>
          <div class="rounded-xl border border-dashed border-white/10 p-8 text-center text-sm text-gray-400">
            Couldn't load plans from the provider. Saved per-plan overrides are kept as they are. Check your API key and try again.
          </div>
        <?php else: ?>
        <div class="overflow-x-auto -mx-5 md:mx-0">
          <table class="w-full text-sm min-w-[640px]">
            <thead>
              <tr class="text-left text-xs uppercase tracking-wider text-gray-500 border-b border-white/10">
                <th class="py-2 px-5 md:px-3 font-medium">Plan</th>
                <th class="py-2 px-3 font-medium text-right">Provider price</th>
                <th class="py-2 px-3 font-medium w-32">Your profit (₦)</th>
                <th class="py-2 px-5 md:px-3 font-medium text-right">User pays</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($allPlans as $net => $list): foreach ($list as $p): $costN = $p['cost'] / 100; ?>
              <tr class="plan-row border-b border-white/5" data-network="<?= e($net) ?>" data-cost="<?= e((string) $costN) ?>" data-name="<?= e(strtolower($p['name'])) ?>">
                <td class="py-2.5 px-5 md:px-3">
                  <div class="font-medium"><?= e($p['name']) ?></div>
                  <div class="text-xs text-gray-500"><?= e($networks[$net] ?? strtoupper($net)) ?> · #<?= e($p['code']) ?></div>
                </td>
                <td class="py-2.5 px-3 text-right text-gray-300">₦<?= number_format($costN) ?></td>
                <td class="py-2.5 px-3">
                  <input type="number" step="any" min="0" name="override[<?= e($p['code']) ?>]" value="<?= e((string) ($viewOv[$p['code']] ?? '')) ?>" placeholder="tier"
                         class="ov w-full bg-white/[0.04] border border-white/10 rounded-lg px-2.5 py-1.5 text-sm focus:outline-none focus:border-orange-400">
                </td>
                <td class="py-2.5 px-5 md:px-3 text-right">
                  <div class="font-semibold text-orange-400 user-pays">—</div>
                  <div class="text-xs text-gray-500 profit-hint">—</div>
                </td>
              </tr>
              <?php endforeach; endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>

      <div class="flex justify-end">
        <button type="submit" class="btn bg-orange-400 hover:bg-orange-500 text-white px-8 py-3 font-semibold rounded-xl">Save pricing</button>
      </div>
    </form>
  </div>
</div>

<template id="tier-template">
  <div class="tier-row flex items-center gap-3">
    <span class="text-sm text-gray-400 w-24 flex-shrink-0">Plan up to ₦</span>
    <input type="number" step="any" min="0" name="tier_up_to[]" class="tier-up w-full min-w-0 bg-white/[0.04] border border-white/10 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-orange-400">
    <span class="text-sm text-gray-400 flex-shrink-0">profit ₦</span>
    <input type="number" step="any" min="0" name="tier_profit[]" class="tier-profit w-full min-w-0 bg-white/[0.04] border border-white/10 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-orange-400">
    <button type="button" class="remove-tier text-gray-500 hover:text-red-400 text-xl px-1" aria-label="Remove tier">×</button>
  </div>
</template>

<script>window.APP_URL = '<?= APP_URL ?>';</script>
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
<script>
(function () {
  const $ = (s, r = document) => r.querySelector(s);
  const fmt = (n) => '₦' + Number(n).toLocaleString('en-NG');

  function readTiers() {
    return [...document.querySelectorAll('.tier-row')].map((r) => [parseFloat($('.tier-up', r).value), parseFloat($('.tier-profit', r).value)])
      .filter((t) => t[0] > 0 && t[1] >= 0).sort((a, b) => a[0] - b[0]);
  }
  function profitFor(cost, ov) {
    if (ov !== '' && !isNaN(parseFloat(ov))) return parseFloat(ov);
    for (const [upTo, profit] of readTiers()) if (cost <= upTo) return profit;
    return cost * (parseFloat($('#pct-above').value) || 0) / 100;
  }
  // Mirrors the server: user pays ceil(provider price + profit)
  function refresh() {
    document.querySelectorAll('.plan-row').forEach((row) => {
      const cost = parseFloat(row.dataset.cost);
      const ov   = $('.ov', row).value.trim();
      const pays = Math.ceil(cost + profitFor(cost, ov));
      $('.user-pays', row).textContent = fmt(pays);
      $('.profit-hint', row).textContent = 'profit ' + fmt(pays - cost) + (ov !== '' ? ' (override)' : ' (tier)');
    });
  }
  function filter() {
    const net = $('#plan-network').value, q = $('#plan-search').value.trim().toLowerCase();
    document.querySelectorAll('.plan-row').forEach((row) => {
      row.classList.toggle('hidden', !!((net && row.dataset.network !== net) || (q && !row.dataset.name.includes(q))));
    });
  }

  document.addEventListener('input', (e) => { if (e.target.closest('#pricing-form')) refresh(); });
  document.addEventListener('click', (e) => {
    if (e.target.classList.contains('remove-tier')) { e.target.closest('.tier-row').remove(); refresh(); }
  });
  $('#add-tier').addEventListener('click', () => {
    $('#tier-rows').appendChild($('#tier-template').content.cloneNode(true));
  });
  const n = $('#plan-network'), s = $('#plan-search');
  if (n) { n.addEventListener('change', filter); s.addEventListener('input', filter); }
  refresh();
})();
</script>
</body></html>
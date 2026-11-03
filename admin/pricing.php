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

$networks = ['mtn' => 'MTN', 'glo' => 'Glo', 'airtel' => 'Airtel', '9mobile' => '9mobile'];
$maxTickets = (int) REWARD_MAX_CODES_PER_ORDER;

$oldAirtime = getAirtimeFeeNaira();
$oldData    = getDataProfitNaira();

// Catalogue from the provider (cost, selling price, per-plan gain/tickets already applied)
$allPlans = getDataPlans();
$catalog  = [];
foreach ($allPlans as $net => $list) foreach ($list as $p) $catalog[$net][$p['code']] = true;

$postedTiers = null;   // kept so the form can be re-shown after an error

if (isPost()) {
    if (!verifyCsrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $errors[] = 'Your session expired, please try again.';
    } else {
        $air  = trim((string) ($_POST['airtime_fee'] ?? ''));
        $data = trim((string) ($_POST['data_profit'] ?? ''));

        if ($air === '' || !ctype_digit($air)) {
            $errors[] = 'Airtime fee must be a whole number of naira (0 or more).';
        } elseif ((int) $air > 1000) {
            $errors[] = 'Airtime fee can\'t be more than ₦1,000. Is that a typo?';
        }
        if ($data === '' || !ctype_digit($data)) {
            $errors[] = 'Default data profit must be a whole number of naira (0 or more).';
        } elseif ((int) $data > 5000) {
            $errors[] = 'Default data profit can\'t be more than ₦5,000. Is that a typo?';
        }

        // ── Airtime ticket tiers ──
        $tiers = [];
        $ta = (array) ($_POST['tier_amount'] ?? []);
        $tt = (array) ($_POST['tier_tickets'] ?? []);
        $postedTiers = [];
        foreach ($ta as $i => $a) {
            $a = trim((string) $a); $t = trim((string) ($tt[$i] ?? ''));
            if ($a === '' && $t === '') continue;                      // blank row
            $postedTiers[] = [$a, $t];
            if (!ctype_digit($a) || (int) $a < 50 || (int) $a > 50000) { $errors[] = "Airtime amount \"$a\" must be a whole number between ₦50 and ₦50,000."; continue; }
            if ($t === '' || !ctype_digit($t) || (int) $t > $maxTickets) { $errors[] = "Tickets for ₦$a must be a whole number from 0 to $maxTickets."; continue; }
            if (isset($tiers[(int) $a])) { $errors[] = "₦$a is listed twice."; continue; }
            $tiers[(int) $a] = (int) $t;
        }
        if (!$tiers && !$errors) $errors[] = 'Add at least one airtime amount.';
        ksort($tiers);

        // ── Data plans (only the rows the admin changed arrive, as JSON) ──
        $planRows = [];
        $pj = trim((string) ($_POST['plans_json'] ?? ''));
        if ($pj !== '') {
            $dec = json_decode($pj, true);
            if (!is_array($dec)) {
                $errors[] = 'Could not read the data plan changes. Please reload the page and try again.';
            } else {
                foreach ($dec as $r) {
                    $n = (string) ($r['n'] ?? ''); $c = (string) ($r['c'] ?? '');
                    $g = trim((string) ($r['g'] ?? '')); $t = trim((string) ($r['t'] ?? ''));
                    if (!isset($catalog[$n][$c])) continue;               // plan no longer exists at the provider
                    if ($g !== '' && (!ctype_digit($g) || (int) $g > 5000)) { $errors[] = "Gain for plan $c ($n) must be a whole naira amount up to ₦5,000, or empty to use the default."; continue; }
                    if ($t === '' || !ctype_digit($t) || (int) $t > $maxTickets) { $errors[] = "Tickets for plan $c ($n) must be a whole number from 0 to $maxTickets."; continue; }
                    $planRows[] = ['network' => $n, 'code' => $c, 'gain' => $g === '' ? null : (int) $g, 'tickets' => (int) $t];
                }
            }
        }

        if (!$errors) {
            try {
                saveSiteSetting('airtime_fee_naira', (string) (int) $air, $adminId);
                saveSiteSetting('data_profit_naira', (string) (int) $data, $adminId);
                siteSettings(true);
                saveAirtimeTiers($tiers);
                if ($planRows) saveDataPlanPricing($planRows, $adminId);

                auditLog('admin', $adminId, 'pricing_update',
                    "Airtime fee ₦{$oldAirtime} -> ₦{$air}; default data profit ₦{$oldData} -> ₦{$data}; airtime tiers: "
                    . implode(', ', array_map(fn($a, $t) => "₦$a=$t", array_keys($tiers), $tiers))
                    . '; data plans changed: ' . count($planRows), 'settings', 0);

                $success = 'Saved. The new prices and tickets apply to the next purchase.'
                         . ($planRows ? ' (' . count($planRows) . ' data plan' . (count($planRows) > 1 ? 's' : '') . ' updated)' : '');
                $postedTiers = null;
                $allPlans = getDataPlans();   // reload with the new values
            } catch (\Throwable $e) {
                error_log('[PRICING] ' . $e->getMessage());
                $errors[] = 'Could not save. Have you run the SQL migration (data_plan_pricing, airtime_ticket_tiers, site_settings)?';
            }
        }
    }
}

$curAir  = (isPost() && $errors) ? ($_POST['airtime_fee'] ?? '') : (string) (int) getAirtimeFeeNaira();
$curData = (isPost() && $errors) ? ($_POST['data_profit'] ?? '') : (string) (int) getDataProfitNaira();
$notSet  = !siteSettingIsSet('airtime_fee_naira') && !siteSettingIsSet('data_profit_naira');

// Tier rows to show: re-posted (after an error) > saved > suggested starting list
$tierRows = [];
if ($postedTiers !== null) {
    $tierRows = $postedTiers;
} else {
    $saved = airtimeTicketTiers(true);
    if ($saved) {
        foreach ($saved as $a => $t) $tierRows[] = [(string) $a, (string) $t];
    } else {
        foreach ([100, 200, 300, 400, 500, 1000] as $a) $tierRows[] = [(string) $a, (string) airtimeTicketsFor($a)];
    }
}
$tiersSaved = !empty(airtimeTicketTiers());

$planCount = 0;
foreach ($allPlans as $list) $planCount += count($list);
$overrides = dataPlanOverrides(true);

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
  <style>
    .cell-in { width: 100%; background: rgba(255,255,255,.05); border: 1px solid rgba(255,255,255,.12); border-radius: .6rem; padding: .4rem .55rem; font-weight: 600; font-size: .85rem; }
    .cell-in:focus { outline: none; border-color: #fb923c; box-shadow: 0 0 0 1px rgba(251,146,60,.4); }
    .row-dirty { background: rgba(251,146,60,.07); }
  </style>
</head>
<body class="bg-[#0a0f1a] text-white font-sans">

<?php include __DIR__ . '/../components/admin-sidebar.php'; ?>

<div class="main-content">
  <div class="topbar">
    <button onclick="toggleSidebar()" class="md:hidden text-gray-400 text-2xl mr-3">☰</button>
    <h1 class="text-xl font-bold">Pricing &amp; Profit</h1>
    <div class="text-sm text-gray-400">Your fee, profit and raffle tickets</div>
  </div>

  <div class="p-4 md:p-8 max-w-5xl mx-auto">

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
      <input type="hidden" name="plans_json" id="plans-json" value="">

      <!-- ═════════ AIRTIME ═════════ -->
      <div class="card p-5 md:p-7">
        <div class="flex items-start gap-4">
          <div class="w-11 h-11 rounded-xl bg-orange-500/15 text-orange-400 flex items-center justify-center flex-shrink-0 text-xl">📞</div>
          <div class="flex-1 min-w-0">
            <h2 class="font-bold text-lg">Airtime</h2>

            <!-- Fee -->
            <p class="text-sm text-gray-400 mt-1"><strong class="text-gray-200">Your gain:</strong> one fixed fee added to <strong class="text-gray-200">every</strong> airtime purchase, whatever the amount. Users can see it as "Service fee".</p>
            <label for="airtime_fee" class="block text-sm text-gray-400 mt-4 mb-1.5">Fee per airtime purchase (₦)</label>
            <div class="relative max-w-xs">
              <span class="absolute left-4 top-1/2 -translate-y-1/2 text-xl font-semibold text-gray-500">₦</span>
              <input type="number" id="airtime_fee" name="airtime_fee" min="0" max="1000" step="1" required inputmode="numeric" value="<?= e((string) $curAir) ?>"
                     class="w-full bg-white/[0.04] border border-white/10 rounded-xl pl-10 pr-4 py-3 text-2xl font-bold focus:outline-none focus:border-orange-400 focus:ring-1 focus:ring-orange-400/40">
            </div>
            <div class="mt-3 rounded-xl bg-white/[0.04] border border-white/10 p-3.5 text-sm">
              A user buys <strong>₦350</strong> airtime → they pay <strong class="text-orange-400" id="ex-air">₦350</strong>
              <span class="text-gray-500">(₦350 airtime + <span id="ex-air-fee">₦0</span> fee, for any amount)</span>
            </div>

            <!-- Tiers -->
            <h3 class="font-semibold mt-7">Raffle tickets per amount</h3>
            <p class="text-sm text-gray-400 mt-1">A purchase earns the tickets of the <strong class="text-gray-200">highest amount it reaches</strong>. Example: with ₦300 = 3 tickets and ₦400 = 4 tickets, a ₦350 purchase earns 3 tickets. Below the smallest amount earns none.</p>
            <?php if (!$tiersSaved): ?>
              <p class="text-xs text-amber-300 mt-2">These are suggested values. Nothing is applied until you save.</p>
            <?php endif; ?>

            <div class="mt-4 rounded-xl border border-white/10 overflow-hidden max-w-md">
              <table class="w-full text-sm">
                <thead class="bg-[#111827]"><tr class="text-left text-xs uppercase tracking-wider text-gray-500">
                  <th class="py-2.5 px-3 font-medium">Airtime amount (₦)</th>
                  <th class="py-2.5 px-3 font-medium">Tickets</th>
                  <th class="w-10"></th>
                </tr></thead>
                <tbody id="tier-rows">
                  <?php foreach ($tierRows as [$ta_, $tt_]): ?>
                  <tr class="border-t border-white/5 tier-row">
                    <td class="py-2 px-3"><input type="number" name="tier_amount[]" min="50" max="50000" step="1" inputmode="numeric" class="cell-in tier-a" value="<?= e((string) $ta_) ?>"></td>
                    <td class="py-2 px-3"><input type="number" name="tier_tickets[]" min="0" max="<?= $maxTickets ?>" step="1" inputmode="numeric" class="cell-in tier-t" value="<?= e((string) $tt_) ?>"></td>
                    <td class="px-2 text-center"><button type="button" class="tier-del text-gray-500 hover:text-red-400 text-lg leading-none" aria-label="Remove">×</button></td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
            <button type="button" id="tier-add" class="mt-3 text-sm font-semibold text-orange-400 hover:text-orange-300">+ Add amount</button>

            <div class="mt-4 rounded-xl bg-white/[0.04] border border-white/10 p-3.5 text-sm">
              Try it: a user buys ₦
              <input type="number" id="try-amt" value="350" min="0" class="cell-in !w-24 inline-block mx-1">
              → earns <strong class="text-orange-400" id="try-out">0 tickets</strong>
            </div>
          </div>
        </div>
      </div>

      <!-- ═════════ DATA ═════════ -->
      <div class="card p-5 md:p-7">
        <div class="flex items-start gap-4">
          <div class="w-11 h-11 rounded-xl bg-blue-500/15 text-blue-400 flex items-center justify-center flex-shrink-0 text-xl">📶</div>
          <div class="flex-1 min-w-0">
            <h2 class="font-bold text-lg">Data plans</h2>
            <p class="text-sm text-gray-400 mt-1">For each plan you see what the provider charges you, what users pay, and your gain. <strong class="text-gray-200">Users only see the total price</strong>, never your gain. Set the gain and the raffle tickets per plan.</p>

            <label for="data_profit" class="block text-sm text-gray-400 mt-4 mb-1.5">Default gain per plan (₦) <span class="text-gray-500">— used by every plan whose gain box is left empty</span></label>
            <div class="relative max-w-xs">
              <span class="absolute left-4 top-1/2 -translate-y-1/2 text-xl font-semibold text-gray-500">₦</span>
              <input type="number" id="data_profit" name="data_profit" min="0" max="5000" step="1" required inputmode="numeric" value="<?= e((string) $curData) ?>"
                     class="w-full bg-white/[0.04] border border-white/10 rounded-xl pl-10 pr-4 py-3 text-2xl font-bold focus:outline-none focus:border-orange-400 focus:ring-1 focus:ring-orange-400/40">
            </div>
          </div>
        </div>

        <?php if ($planCount === 0): ?>
          <div class="text-sm text-gray-400 mt-6">Couldn't load plans from the provider right now. Check your API key.</div>
        <?php else: ?>

        <!-- Toolbar -->
        <div class="mt-6 flex flex-col md:flex-row gap-3 md:items-center">
          <select id="f-net" class="cell-in md:!w-40">
            <option value="">All networks</option>
            <?php foreach ($networks as $k => $label): ?><option value="<?= e($k) ?>"><?= e($label) ?></option><?php endforeach; ?>
          </select>
          <input type="search" id="f-q" placeholder="Search plans…" class="cell-in md:!w-56">
          <div class="md:ml-auto flex items-center gap-2 text-xs text-gray-400 flex-wrap">
            <span>Shown plans:</span>
            <input type="number" id="bulk-g" min="0" max="5000" placeholder="gain ₦" class="cell-in !w-24">
            <button type="button" id="bulk-g-btn" class="px-3 py-1.5 rounded-lg bg-white/10 hover:bg-white/15 font-semibold">Set gain</button>
            <input type="number" id="bulk-t" min="0" max="<?= $maxTickets ?>" placeholder="tickets" class="cell-in !w-24">
            <button type="button" id="bulk-t-btn" class="px-3 py-1.5 rounded-lg bg-white/10 hover:bg-white/15 font-semibold">Set tickets</button>
          </div>
        </div>
        <div class="text-xs text-gray-500 mt-2"><span id="shown-count"><?= number_format($planCount) ?></span> of <?= number_format($planCount) ?> plans. Rows you change are highlighted; only those are saved.</div>

        <div class="mt-3 max-h-[34rem] overflow-y-auto overflow-x-auto rounded-xl border border-white/10">
          <table class="w-full text-sm min-w-[720px]">
            <thead class="sticky top-0 bg-[#111827] z-10">
              <tr class="text-left text-xs uppercase tracking-wider text-gray-500">
                <th class="py-2.5 px-3 font-medium">Plan</th>
                <th class="py-2.5 px-3 font-medium text-right">Provider price<br><span class="normal-case text-[10px]">(you pay)</span></th>
                <th class="py-2.5 px-3 font-medium w-28">Your gain (₦)</th>
                <th class="py-2.5 px-3 font-medium text-right">Users pay</th>
                <th class="py-2.5 px-3 font-medium text-right">Real gain</th>
                <th class="py-2.5 px-3 font-medium w-24">Tickets</th>
              </tr>
            </thead>
            <tbody id="plan-body">
              <?php foreach ($allPlans as $net => $list): foreach ($list as $p):
                    $cost   = $p['cost'] / 100;
                    $ov     = $overrides[$net][$p['code']] ?? null;
                    $gainIn = ($ov && $ov['gain'] !== null) ? (string) $ov['gain'] : '';
                    $tix    = (int) $p['tickets'];
              ?>
              <tr class="border-t border-white/5 plan-row" data-net="<?= e($net) ?>" data-code="<?= e($p['code']) ?>" data-cost="<?= e((string) $cost) ?>"
                  data-og="<?= e($gainIn) ?>" data-ot="<?= $tix ?>" data-search="<?= e(strtolower($p['name'] . ' ' . ($networks[$net] ?? $net) . ' ' . $p['type'])) ?>">
                <td class="py-2 px-3"><div class="font-medium"><?= e($p['name']) ?></div><div class="text-xs text-gray-500"><?= e($networks[$net] ?? strtoupper($net)) ?><?= $p['type'] ? ' · ' . e($p['type']) : '' ?></div></td>
                <td class="py-2 px-3 text-right text-gray-300">₦<?= number_format($cost, $cost == floor($cost) ? 0 : 2) ?></td>
                <td class="py-2 px-3"><input type="number" min="0" max="5000" step="1" inputmode="numeric" class="cell-in in-g" value="<?= e($gainIn) ?>" placeholder="default"></td>
                <td class="py-2 px-3 text-right font-semibold text-orange-400 out-user">—</td>
                <td class="py-2 px-3 text-right font-semibold text-emerald-400 out-gain">—</td>
                <td class="py-2 px-3"><input type="number" min="0" max="<?= $maxTickets ?>" step="1" inputmode="numeric" class="cell-in in-t" value="<?= $tix ?>"></td>
              </tr>
              <?php endforeach; endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>

      <div class="flex justify-end">
        <button type="submit" class="btn bg-orange-400 hover:bg-orange-500 text-white px-8 py-3 font-semibold rounded-xl">Save</button>
      </div>
    </form>

  </div>
</div>

<script>window.APP_URL = '<?= APP_URL ?>';</script>
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
<script>
(function () {
  const $  = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));
  const fmt = (n) => '₦' + Number(n).toLocaleString('en-NG', { maximumFractionDigits: 2 });
  const MAXT = <?= $maxTickets ?>;

  // ── Airtime example + fee ──
  function tierMap() {
    const m = [];
    $$('.tier-row').forEach((r) => {
      const a = parseInt($('.tier-a', r).value, 10), t = parseInt($('.tier-t', r).value, 10);
      if (a > 0 && t >= 0) m.push([a, t]);
    });
    return m.sort((x, y) => x[0] - y[0]);
  }
  function ticketsFor(n) { let t = 0; tierMap().forEach(([a, k]) => { if (a <= n) t = k; }); return t; }
  function refreshAirtime() {
    const fee = Math.max(0, parseInt($('#airtime_fee').value, 10) || 0);
    $('#ex-air').textContent = fmt(350 + fee);
    $('#ex-air-fee').textContent = fmt(fee);
    const n = parseInt($('#try-amt').value, 10) || 0, t = ticketsFor(n);
    $('#try-out').textContent = t + ' ticket' + (t === 1 ? '' : 's');
  }

  function addTier(a, t) {
    const tr = document.createElement('tr');
    tr.className = 'border-t border-white/5 tier-row';
    tr.innerHTML = '<td class="py-2 px-3"><input type="number" name="tier_amount[]" min="50" max="50000" step="1" inputmode="numeric" class="cell-in tier-a"></td>'
      + '<td class="py-2 px-3"><input type="number" name="tier_tickets[]" min="0" max="' + MAXT + '" step="1" inputmode="numeric" class="cell-in tier-t"></td>'
      + '<td class="px-2 text-center"><button type="button" class="tier-del text-gray-500 hover:text-red-400 text-lg leading-none" aria-label="Remove">×</button></td>';
    $('.tier-a', tr).value = a || ''; $('.tier-t', tr).value = (t === undefined ? '' : t);
    $('#tier-rows').appendChild(tr);
    return tr;
  }
  $('#tier-add').addEventListener('click', () => { const r = addTier(); $('.tier-a', r).focus(); });
  $('#tier-rows').addEventListener('click', (e) => {
    if (e.target.classList.contains('tier-del')) { e.target.closest('tr').remove(); refreshAirtime(); }
  });

  // ── Data plans ──
  const rows = $$('.plan-row');
  function defaultProfit() { return Math.max(0, parseInt($('#data_profit').value, 10) || 0); }
  function calcRow(r) {
    const cost = parseFloat(r.dataset.cost);
    const gv = $('.in-g', r).value.trim();
    const g = gv === '' ? defaultProfit() : Math.max(0, parseInt(gv, 10) || 0);
    const user = Math.ceil(cost + g);                       // same rounding as the server
    $('.out-user', r).textContent = fmt(user);
    $('.out-gain', r).textContent = fmt(user - cost);
    r.classList.toggle('row-dirty', $('.in-g', r).value !== r.dataset.og || $('.in-t', r).value !== r.dataset.ot);
  }
  function refreshPlans() { rows.forEach(calcRow); }

  function applyFilter() {
    const net = $('#f-net') ? $('#f-net').value : '', q = $('#f-q') ? $('#f-q').value.trim().toLowerCase() : '';
    let n = 0;
    rows.forEach((r) => {
      const ok = (!net || r.dataset.net === net) && (!q || r.dataset.search.includes(q));
      r.classList.toggle('hidden', !ok);
      if (ok) n++;
    });
    if ($('#shown-count')) $('#shown-count').textContent = n.toLocaleString();
  }
  const shown = () => rows.filter((r) => !r.classList.contains('hidden'));
  if ($('#f-net')) {
    $('#f-net').addEventListener('change', applyFilter);
    $('#f-q').addEventListener('input', applyFilter);
    $('#bulk-g-btn').addEventListener('click', () => {
      const v = $('#bulk-g').value.trim(); if (v === '') return;
      shown().forEach((r) => { $('.in-g', r).value = v; calcRow(r); });
    });
    $('#bulk-t-btn').addEventListener('click', () => {
      const v = $('#bulk-t').value.trim(); if (v === '') return;
      shown().forEach((r) => { $('.in-t', r).value = v; calcRow(r); });
    });
  }

  // ── Events ──
  $('#pricing-form').addEventListener('input', (e) => {
    if (e.target.closest('.plan-row')) calcRow(e.target.closest('.plan-row'));
    else if (e.target.id === 'data_profit') refreshPlans();
    refreshAirtime();
  });

  // Only changed plans are sent (hundreds of plans would exceed PHP's max_input_vars otherwise).
  $('#pricing-form').addEventListener('submit', () => {
    const changed = [];
    rows.forEach((r) => {
      const g = $('.in-g', r).value.trim(), t = $('.in-t', r).value.trim();
      if (g !== r.dataset.og || t !== r.dataset.ot) changed.push({ n: r.dataset.net, c: r.dataset.code, g: g, t: t });
    });
    $('#plans-json').value = changed.length ? JSON.stringify(changed) : '';
  });

  refreshPlans(); refreshAirtime();
})();
</script>
</body></html>
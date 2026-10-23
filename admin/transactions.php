<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../lib/paystack.php'; 

// ⚠ Use whatever guard your other admin pages use (same as admin/pricing.php).
$auth    = requireAdmin();
$adminId = (int) $auth['id'];
$db      = getDB();

// ── Filters (all GET so results are linkable / exportable) ──────────────────
$typeOpts   = ['' => 'All types', 'airtime' => 'Airtime', 'data' => 'Data', 'deposit' => 'Deposits'];
$statusOpts = ['' => 'All statuses', 'success' => 'Success', 'pending' => 'Pending', 'processing' => 'Processing', 'refunded' => 'Refunded', 'failed' => 'Failed'];

$fType   = isset($typeOpts[$_GET['type'] ?? '']) ? ($_GET['type'] ?? '') : '';
$fStatus = isset($statusOpts[$_GET['status'] ?? '']) ? ($_GET['status'] ?? '') : '';
$fFrom   = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['from'] ?? '') ? $_GET['from'] : '';
$fTo     = preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['to'] ?? '') ? $_GET['to'] : '';
$fQ      = trim((string) ($_GET['q'] ?? ''));
$fUser   = (int) ($_GET['user_id'] ?? 0);
$page    = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 25;

// users table: I don't know which name column you have, so detect it.
$nameExpr = "''";
try {
    $cols = $db->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
    foreach (['full_name', 'name', 'username'] as $c) {
        if (in_array($c, $cols, true)) { $nameExpr = "COALESCE(u.`$c`,'')"; break; }
    }
    if ($nameExpr === "''" && in_array('first_name', $cols, true)) {
        $nameExpr = in_array('last_name', $cols, true)
            ? "TRIM(CONCAT_WS(' ', u.first_name, u.last_name))" : "COALESCE(u.first_name,'')";
    }
} catch (\Throwable $e) { /* fall back to email only */ }

/** WHERE pieces shared by the orders part and the deposits part. */
$filters = function (string $a, bool $isOrder) use ($fType, $fStatus, $fFrom, $fTo, $fQ, $fUser, $nameExpr): array {
    $w = []; $p = [];
    if ($isOrder && ($fType === 'airtime' || $fType === 'data')) { $w[] = "$a.type = ?"; $p[] = $fType; }
    if ($fStatus !== '') { $w[] = "$a.status = ?"; $p[] = $fStatus; }
    if ($fFrom !== '')   { $w[] = "$a.created_at >= ?"; $p[] = $fFrom . ' 00:00:00'; }
    if ($fTo !== '')     { $w[] = "$a.created_at < ?";  $p[] = date('Y-m-d', strtotime($fTo . ' +1 day')) . ' 00:00:00'; }
    if ($fUser > 0)      { $w[] = "$a.user_id = ?"; $p[] = $fUser; }
    if ($fQ !== '') {
        $like = '%' . addcslashes($fQ, '%_\\') . '%';
        $or = ["$a.reference LIKE ?", "u.email LIKE ?", "$nameExpr LIKE ?"];
        $p[] = $like; $p[] = $like; $p[] = $like;
        if ($isOrder) {
            $or[] = "$a.provider_reference LIKE ?"; $p[] = $like;
            $or[] = "$a.phone LIKE ?";              $p[] = $like;
            $digits = preg_replace('/\D/', '', $fQ);                       // 0801… also matches stored 234801…
            if (preg_match('/^0\d{10}$/', $digits)) {
                $or[] = "$a.phone LIKE ?"; $p[] = '%' . substr($digits, 1) . '%';
            }
        }
        $w[] = '(' . implode(' OR ', $or) . ')';
    }
    return [$w ? 'WHERE ' . implode(' AND ', $w) : '', $p];
};

// ── Build one UNION of orders + deposits (skip a side the type filter excludes) ──
$parts = []; $params = [];
if ($fType !== 'deposit') {
    [$w, $p] = $filters('o', true);
    $parts[] = "SELECT 'order' AS src, o.user_id, u.email AS user_email, $nameExpr AS user_name, o.type, o.network, o.phone,
                       o.plan_name, o.amount, o.cost_amount, o.profit_amount, o.reference, o.provider_reference,
                       o.status, LEFT(o.provider_response, 600) AS detail, o.created_at
                FROM orders o LEFT JOIN users u ON u.id = o.user_id $w";
    $params = array_merge($params, $p);
}
if ($fType === '' || $fType === 'deposit') {
    [$w, $p] = $filters('d', false);
    $parts[] = "SELECT 'deposit' AS src, d.user_id, u.email AS user_email, $nameExpr AS user_name, 'deposit' AS type, NULL AS network, NULL AS phone,
                       NULL AS plan_name, d.amount, NULL AS cost_amount, NULL AS profit_amount, d.reference, NULL AS provider_reference,
                       d.status, LEFT(d.gateway_response, 600) AS detail, d.created_at
                FROM deposits d LEFT JOIN users u ON u.id = d.user_id $w";
    $params = array_merge($params, $p);
}
$union = implode(' UNION ALL ', $parts);

// ── CSV export (same filters, up to 10,000 rows) ────────────────────────────
if (isset($_GET['export'])) {
    $st = $db->prepare("SELECT * FROM ($union) t ORDER BY created_at DESC LIMIT 10000");
    $st->execute($params);
    auditLog('admin', $adminId, 'transactions_export', 'Exported transactions CSV', 'settings', 0);

    $safe = fn($v) => (is_string($v) && preg_match('/^[=+\-@\t\r]/', $v)) ? "'" . $v : $v; // block spreadsheet formula injection
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="transactions-' . date('Ymd-His') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Date', 'Type', 'Status', 'User', 'Email', 'Network', 'Phone', 'Plan', 'Total charged (NGN)', 'Provider price (NGN)', 'Profit (NGN)', 'Reference', 'Provider reference']);
    foreach ($st as $r) {
        $n = fn($k) => $r[$k] === null ? '' : number_format($r[$k] / 100, 2, '.', '');
        fputcsv($out, array_map($safe, [$r['created_at'], $r['type'], $r['status'], $r['user_name'], $r['user_email'], $r['network'], $r['phone'],
            $r['plan_name'], $n('amount'), $n('cost_amount'), $n('profit_amount'), $r['reference'], $r['provider_reference']]));
    }
    fclose($out);
    exit;
}

// ── Count + stats + page of rows ────────────────────────────────────────────
$total = 0; $stats = []; $rows = []; $loadError = null;
try {
    $st = $db->prepare("SELECT COUNT(*) AS n,
            COALESCE(SUM(CASE WHEN src='order'   AND status='success' THEN amount END),0)        AS sales,
            COALESCE(SUM(CASE WHEN src='deposit' AND status='success' THEN amount END),0)        AS deposits,
            COALESCE(SUM(CASE WHEN src='order'   AND status='success' THEN profit_amount END),0) AS profit,
            COALESCE(SUM(CASE WHEN src='order'   AND status IN ('pending','processing') THEN 1 END),0) AS pending
        FROM ($union) t");
    $st->execute($params);
    $stats = $st->fetch();
    $total = (int) $stats['n'];

    $pages = max(1, (int) ceil($total / $perPage));
    $page  = min($page, $pages);
    $offset = ($page - 1) * $perPage;

    $st = $db->prepare("SELECT * FROM ($union) t ORDER BY created_at DESC, reference DESC LIMIT " . (int) $perPage . " OFFSET " . (int) $offset);
    $st->execute($params);
    $rows = $st->fetchAll();
} catch (\Throwable $e) {
    error_log('[ADMIN TX] ' . $e->getMessage());
    $loadError = 'Could not load transactions. Check that the orders and deposits tables exist and the cost_amount / profit_amount columns were added (see README).';
    $pages = 1;
}

$qs = fn(array $over = []) => '?' . http_build_query(array_filter(
    array_merge(['type' => $fType, 'status' => $fStatus, 'from' => $fFrom, 'to' => $fTo, 'q' => $fQ, 'user_id' => $fUser ?: '', 'page' => $page], $over),
    fn($v) => $v !== '' && $v !== null && $v !== 0));

$typeStyle = [
    'airtime' => ['bg-orange-500/15 text-orange-400', 'Airtime'],
    'data'    => ['bg-blue-500/15 text-blue-400',     'Data'],
    'deposit' => ['bg-emerald-500/15 text-emerald-400', 'Deposit'],
];

$aPage     = 'transactions';
$pageTitle = 'Transactions';
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
    <h1 class="text-xl font-bold">Transactions</h1>
    <div class="text-sm text-gray-400"><?= number_format($total) ?> entries</div>
  </div>

  <div class="p-4 md:p-8 max-w-7xl mx-auto">

    <?php if ($loadError): ?>
      <div class="mb-6 rounded-2xl border border-red-500/30 bg-red-500/10 text-red-200 p-4 text-sm" role="alert"><?= e($loadError) ?></div>
    <?php endif; ?>

    <!-- Stats (for the current filters) -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
      <div class="card p-5">
        <div class="text-xs uppercase tracking-wider text-gray-400">Sales (airtime + data)</div>
        <div class="text-xl md:text-2xl font-bold mt-1"><?= formatNaira((int) ($stats['sales'] ?? 0)) ?></div>
        <div class="text-xs text-gray-500 mt-1">successful orders</div>
      </div>
      <div class="card p-5">
        <div class="text-xs uppercase tracking-wider text-gray-400">Deposits received</div>
        <div class="text-xl md:text-2xl font-bold mt-1 text-emerald-400"><?= formatNaira((int) ($stats['deposits'] ?? 0)) ?></div>
        <div class="text-xs text-gray-500 mt-1">successful deposits</div>
      </div>
      <div class="card p-5">
        <div class="text-xs uppercase tracking-wider text-gray-400">Profit</div>
        <div class="text-xl md:text-2xl font-bold mt-1 text-orange-400"><?= formatNaira((int) ($stats['profit'] ?? 0)) ?></div>
        <div class="text-xs text-gray-500 mt-1">from successful orders</div>
      </div>
      <div class="card p-5 <?= (int) ($stats['pending'] ?? 0) > 0 ? 'border-amber-500/30' : '' ?>">
        <div class="text-xs uppercase tracking-wider text-gray-400">Needs attention</div>
        <div class="text-xl md:text-2xl font-bold mt-1 <?= (int) ($stats['pending'] ?? 0) > 0 ? 'text-amber-400' : '' ?>"><?= number_format((int) ($stats['pending'] ?? 0)) ?></div>
        <div class="text-xs text-gray-500 mt-1">pending / unconfirmed orders</div>
      </div>
    </div>

    <!-- Filters -->
    <form method="GET" class="card p-4 md:p-5 mb-6">
      <div class="grid sm:grid-cols-2 lg:grid-cols-6 gap-3">
        <div class="lg:col-span-2">
          <label class="text-xs text-gray-400 mb-1 block">Search</label>
          <input type="search" name="q" value="<?= e($fQ) ?>" placeholder="Reference, phone, email or name"
                 class="w-full bg-white/[0.04] border border-white/10 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-orange-400">
        </div>
        <div>
          <label class="text-xs text-gray-400 mb-1 block">Type</label>
          <select name="type" class="w-full bg-white/[0.04] border border-white/10 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-orange-400">
            <?php foreach ($typeOpts as $k => $l): ?><option value="<?= e($k) ?>" <?= $fType === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="text-xs text-gray-400 mb-1 block">Status</label>
          <select name="status" class="w-full bg-white/[0.04] border border-white/10 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-orange-400">
            <?php foreach ($statusOpts as $k => $l): ?><option value="<?= e($k) ?>" <?= $fStatus === $k ? 'selected' : '' ?>><?= e($l) ?></option><?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="text-xs text-gray-400 mb-1 block">From</label>
          <input type="date" name="from" value="<?= e($fFrom) ?>" class="w-full bg-white/[0.04] border border-white/10 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-orange-400">
        </div>
        <div>
          <label class="text-xs text-gray-400 mb-1 block">To</label>
          <input type="date" name="to" value="<?= e($fTo) ?>" class="w-full bg-white/[0.04] border border-white/10 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:border-orange-400">
        </div>
      </div>
      <?php if ($fUser): ?><input type="hidden" name="user_id" value="<?= (int) $fUser ?>"><?php endif; ?>
      <div class="flex flex-wrap items-center gap-3 mt-4">
        <button type="submit" class="btn bg-orange-400 hover:bg-orange-500 text-white px-5 py-2.5 text-sm font-semibold rounded-xl">Apply filters</button>
        <a href="transactions.php" class="text-sm text-gray-400 hover:text-white">Reset</a>
        <?php if ($fUser): ?><span class="text-xs px-2.5 py-1 rounded-full bg-white/5 border border-white/10 text-gray-300">User #<?= (int) $fUser ?> only</span><?php endif; ?>
        <a href="<?= e($qs(['export' => 1, 'page' => ''])) ?>" class="ml-auto text-sm text-orange-400 hover:text-orange-300 font-medium">⬇ Export CSV</a>
      </div>
    </form>

    <!-- Table -->
    <div class="card overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-sm min-w-[860px]">
          <thead>
            <tr class="text-left text-xs uppercase tracking-wider text-gray-500 border-b border-white/10 bg-white/[0.02]">
              <th class="py-3 px-4 font-medium">Date</th>
              <th class="py-3 px-3 font-medium">User</th>
              <th class="py-3 px-3 font-medium">Type</th>
              <th class="py-3 px-3 font-medium">Details</th>
              <th class="py-3 px-3 font-medium text-right">Amount</th>
              <th class="py-3 px-3 font-medium">Status</th>
              <th class="py-3 px-4"></th>
            </tr>
          </thead>
          <tbody>
          <?php if ($rows): foreach ($rows as $i => $r):
            $badge = match ($r['status']) {
                'success' => 'badge-success',
                'pending', 'processing' => 'badge-warning',
                'refunded' => 'badge-info',
                default => 'badge-danger',
            };
            [$tCls, $tLabel] = $typeStyle[$r['type']] ?? ['bg-white/10 text-gray-300', ucfirst((string) $r['type'])];
            $stale = in_array($r['status'], ['pending', 'processing'], true) && $r['src'] === 'order'
                     && (time() - strtotime($r['created_at'])) > 600;
          ?>
            <tr class="border-b border-white/5 hover:bg-white/[0.02] transition">
              <td class="py-3 px-4 whitespace-nowrap text-gray-300"><?= e(date('M j, Y', strtotime($r['created_at']))) ?><div class="text-xs text-gray-500"><?= e(date('g:i A', strtotime($r['created_at']))) ?></div></td>
              <td class="py-3 px-3 max-w-[200px]">
                <div class="font-medium truncate"><?= e($r['user_name'] !== '' && $r['user_name'] !== null ? $r['user_name'] : ($r['user_email'] ?? 'Deleted user')) ?></div>
                <div class="text-xs text-gray-500 truncate"><?= e($r['user_email'] ?? '') ?> · <a class="hover:text-orange-400" href="<?= e($qs(['user_id' => (int) $r['user_id'], 'page' => ''])) ?>">#<?= (int) $r['user_id'] ?></a></div>
              </td>
              <td class="py-3 px-3"><span class="text-[11px] font-semibold px-2.5 py-1 rounded-full <?= $tCls ?>"><?= e($tLabel) ?></span></td>
              <td class="py-3 px-3 max-w-[260px]">
                <?php if ($r['src'] === 'order'): ?>
                  <div class="truncate"><?= e(strtoupper((string) $r['network'])) ?><?= $r['plan_name'] ? ' · ' . e($r['plan_name']) : '' ?></div>
                  <div class="text-xs text-gray-500 truncate"><?= e(formatPhone($r['phone'])) ?></div>
                <?php else: ?>
                  <div class="truncate">Wallet deposit</div>
                  <div class="text-xs text-gray-500">via Paystack</div>
                <?php endif; ?>
                <div class="text-[11px] text-gray-600 font-mono truncate"><?= e($r['reference']) ?></div>
              </td>
              <td class="py-3 px-3 text-right whitespace-nowrap font-semibold <?= $r['src'] === 'deposit' ? 'text-emerald-400' : '' ?>">
                <?= $r['src'] === 'deposit' ? '+' : '' ?><?= formatNaira((int) $r['amount']) ?>
              </td>
              <td class="py-3 px-3 whitespace-nowrap">
                <span class="badge <?= $badge ?>"><?= e(ucfirst($r['status'])) ?></span>
                <?php if ($stale): ?><div class="text-[11px] text-amber-400 mt-1">Unconfirmed, check provider</div><?php endif; ?>
              </td>
              <td class="py-3 px-4 text-right"><button type="button" class="tx-toggle text-xs text-gray-400 hover:text-white" data-target="tx-<?= $i ?>">Details ▾</button></td>
            </tr>
            <tr id="tx-<?= $i ?>" class="hidden bg-white/[0.02] border-b border-white/5">
              <td colspan="7" class="px-4 py-4">
                <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4 text-xs">
                  <div><div class="text-gray-500 mb-0.5">Total charged to user</div><div class="text-sm font-semibold"><?= formatNaira((int) $r['amount']) ?></div></div>
                  <?php if ($r['src'] === 'order'): ?>
                    <div><div class="text-gray-500 mb-0.5">Provider price</div><div class="text-sm font-semibold"><?= $r['cost_amount'] === null ? '—' : formatNaira((int) $r['cost_amount']) ?></div></div>
                    <div><div class="text-gray-500 mb-0.5">Site profit</div><div class="text-sm font-semibold <?= $r['status'] === 'success' ? 'text-orange-400' : 'text-gray-500' ?>"><?= $r['profit_amount'] === null ? '—' : formatNaira((int) $r['profit_amount']) ?></div><?php if ($r['status'] !== 'success' && $r['profit_amount'] !== null): ?><div class="text-gray-600">not counted (<?= e($r['status']) ?>)</div><?php endif; ?></div>
                    <div><div class="text-gray-500 mb-0.5">Provider reference</div><div class="font-mono break-all"><?= e($r['provider_reference'] ?: '—') ?></div></div>
                  <?php endif; ?>
                  <div class="sm:col-span-2 lg:col-span-4">
                    <div class="text-gray-500 mb-0.5"><?= $r['src'] === 'order' ? 'Provider response' : 'Gateway response' ?></div>
                    <div class="font-mono text-gray-400 break-all whitespace-pre-wrap max-h-32 overflow-y-auto"><?= e($r['detail'] ?: '—') ?></div>
                  </div>
                </div>
              </td>
            </tr>
          <?php endforeach; else: ?>
            <tr><td colspan="7" class="p-12 text-center">
              <div class="text-3xl mb-2">🧾</div>
              <div class="text-sm text-gray-400">No transactions match these filters</div>
            </td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>

      <?php if ($pages > 1): ?>
      <div class="flex items-center justify-between gap-3 px-4 py-3 border-t border-white/10 text-sm">
        <div class="text-gray-500">Page <?= $page ?> of <?= $pages ?></div>
        <div class="flex items-center gap-1.5">
          <?php if ($page > 1): ?><a class="px-3 py-1.5 rounded-lg bg-white/5 hover:bg-white/10 border border-white/10" href="<?= e($qs(['page' => $page - 1])) ?>">← Prev</a><?php endif; ?>
          <?php for ($p = max(1, $page - 2); $p <= min($pages, $page + 2); $p++): ?>
            <a class="px-3 py-1.5 rounded-lg border <?= $p === $page ? 'bg-orange-500/15 border-orange-400 text-orange-300' : 'bg-white/5 hover:bg-white/10 border-white/10' ?>" href="<?= e($qs(['page' => $p])) ?>"><?= $p ?></a>
          <?php endfor; ?>
          <?php if ($page < $pages): ?><a class="px-3 py-1.5 rounded-lg bg-white/5 hover:bg-white/10 border border-white/10" href="<?= e($qs(['page' => $page + 1])) ?>">Next →</a><?php endif; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>

  </div>
</div>

<script>window.APP_URL = '<?= APP_URL ?>';</script>
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
<script>
document.addEventListener('click', function (e) {
  const b = e.target.closest('.tx-toggle');
  if (!b) return;
  const row = document.getElementById(b.dataset.target);
  const open = row.classList.toggle('hidden') === false;
  b.textContent = open ? 'Hide ▴' : 'Details ▾';
});
</script>
</body></html>
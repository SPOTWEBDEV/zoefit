<?php
// admin/codes.php — fixed to use vendors table
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
$auth = requireAdmin(); $adminId = $auth['id'];
$db   = getDB();
$msg  = $err = '';

// ── Generate codes ─────────────────────────────────────────
if (isPost() && verifyCsrf($_POST[CSRF_TOKEN_NAME]??'') && ($_POST['action']??'')==='generate') {
    $qty       = min(10000, max(1, (int)($_POST['quantity']??0)));
    $assignTo  = (int)($_POST['assign_vendor']??0);
    $batch     = 'BATCH-'.date('Ymd').'T'.date('His').'-'.strtoupper(substr(md5(uniqid()),0,6));
    $status    = $assignTo ? 'assigned' : 'unassigned';
    $generated = 0;
    $vendorName = '';

    if ($assignTo) {
        // ── FIX: query vendors table, not users ──
        $v = $db->prepare("SELECT full_name FROM vendors WHERE id=? AND status='active'");
        $v->execute([$assignTo]); $v = $v->fetch();
        if (!$v) { $err = 'Selected vendor not found or not active.'; goto SKIP_GEN; }
        $vendorName = $v['full_name'];
    }

    $ins = $db->prepare(
        "INSERT IGNORE INTO codes (code,status,generated_by,assigned_vendor,batch_id,assigned_at)
         VALUES(?,?,?,?,?,?)"
    );
    for ($i = 0, $attempts = 0; $generated < $qty && $attempts < $qty * 5; $attempts++) {
        $code = '';
        for ($j = 0; $j < 15; $j++) $code .= random_int(0,9);
        $ins->execute([$code, $status, $adminId, $assignTo ?: null, $batch, $assignTo ? date('Y-m-d H:i:s') : null]);
        if ($ins->rowCount()) $generated++;
    }

    // ── FIX: update vendors.code_balance, not users.vendor_code_balance ──
    if ($assignTo && $generated) {
        $db->prepare("UPDATE vendors SET code_balance = code_balance + ?, updated_at=NOW() WHERE id=?")
           ->execute([$generated, $assignTo]);
    }

    auditLog('admin', $adminId, 'generate_codes',
        "Generated $generated codes, batch: $batch".($assignTo ? " → vendor $assignTo ($vendorName)" : ''));
    $msg = "✅ Generated <strong>$generated codes</strong>. Batch: <code>$batch</code>".
           ($assignTo ? " — Assigned to <strong>".e($vendorName)."</strong>" : '');
    SKIP_GEN:;
}

// ── Unassign batch ─────────────────────────────────────────
if (isPost() && verifyCsrf($_POST[CSRF_TOKEN_NAME]??'') && ($_POST['action']??'')==='unassign_batch') {
    $batch = trim($_POST['batch_id']??'');
    if (!$batch) { $err = 'Batch ID required.'; }
    else {
        // ── FIX: update vendors.code_balance, not users.vendor_code_balance ──
        $vRows = $db->prepare(
            "SELECT assigned_vendor, COUNT(*) cnt
             FROM codes WHERE batch_id=? AND status='assigned'
             GROUP BY assigned_vendor"
        );
        $vRows->execute([$batch]);
        foreach ($vRows->fetchAll() as $vr) {
            if ($vr['assigned_vendor']) {
                $db->prepare(
                    "UPDATE vendors SET code_balance = GREATEST(0, code_balance - ?), updated_at=NOW() WHERE id=?"
                )->execute([$vr['cnt'], $vr['assigned_vendor']]);
            }
        }
        $db->prepare(
            "UPDATE codes SET status='unassigned', assigned_vendor=NULL, assigned_at=NULL
             WHERE batch_id=? AND status='assigned'"
        )->execute([$batch]);
        auditLog('admin', $adminId, 'unassign_batch', "Batch $batch unassigned");
        $msg = "✅ Batch <code>$batch</code> unassigned.";
    }
}

// ── CSV Export ─────────────────────────────────────────────
if (isset($_GET['export'])) {
    $batch  = trim($_GET['batch']??'');
    $filter = $_GET['filter']??'all';
    $wh = '1=1'; $pp = [];
    if ($batch)           { $wh .= " AND batch_id=?"; $pp[] = $batch; }
    elseif ($filter!=='all') { $wh .= " AND status=?"; $pp[] = $filter; }

    // ── FIX: join vendors table for vendor name, not users ──
    $rows = $db->prepare(
        "SELECT c.code, c.status, c.batch_id,
                u.full_name  AS owner,
                v.full_name  AS vendor,
                c.generated_at
         FROM codes c
         LEFT JOIN users   u ON c.current_owner   = u.id
         LEFT JOIN vendors v ON c.assigned_vendor  = v.id
         WHERE $wh LIMIT 100000"
    );
    $rows->execute($pp);
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="zoefeeds-codes-'.date('Ymd').'.csv"');
    $f = fopen('php://output','w');
    fputcsv($f, ['Code','Status','Batch','Owner','Vendor','Generated']);
    while ($r = $rows->fetch())
        fputcsv($f, [$r['code'], $r['status'], $r['batch_id'], $r['owner']??'', $r['vendor']??'', $r['generated_at']]);
    fclose($f);
    exit;
}

// ── Filter / search ────────────────────────────────────────
$filter = $_GET['filter'] ?? 'all';
$page   = max(1, (int)($_GET['page']??1));
$per    = 25; $offset = ($page-1)*$per;
$q      = trim($_GET['q']??'');

$where = '1=1'; $params = [];
if ($filter !== 'all') { $where .= " AND c.status=?"; $params[] = $filter; }
if ($q) {
    if (str_starts_with($q, 'BATCH-')) { $where .= " AND c.batch_id=?"; $params[] = $q; }
    else                                { $where .= " AND c.code LIKE ?"; $params[] = "%$q%"; }
}

// ── FIX: join vendors table for vendor_name ──
$codes = $db->prepare(
    "SELECT c.*,
            u.full_name AS owner_name,
            v.full_name AS vendor_name
     FROM codes c
     LEFT JOIN users   u ON c.current_owner  = u.id
     LEFT JOIN vendors v ON c.assigned_vendor = v.id
     WHERE $where
     ORDER BY c.generated_at DESC
     LIMIT $per OFFSET $offset"
);
$codes->execute($params); $codes = $codes->fetchAll();

$cntS = $db->prepare("SELECT COUNT(*) FROM codes c WHERE $where");
$cntS->execute($params); $total = (int)$cntS->fetchColumn();
$pages = (int)ceil($total / $per);

// Stats
$cstats = ['unassigned'=>0,'assigned'=>0,'redeemed'=>0,'reserved'=>0,'used'=>0,'transferred'=>0];
foreach ($db->query("SELECT status, COUNT(*) c FROM codes GROUP BY status")->fetchAll() as $r)
    if (isset($cstats[$r['status']])) $cstats[$r['status']] = (int)$r['c'];

// ── FIX: vendors for dropdown from vendors table ──
$vendors = $db->query(
    "SELECT id, full_name, business_name, code_balance
     FROM vendors WHERE status='active' ORDER BY full_name"
)->fetchAll();

// Batches for unassign picker
$batches = $db->query(
    "SELECT batch_id, COUNT(*) cnt, MIN(generated_at) gen_at,
            SUM(CASE WHEN status='assigned' THEN 1 ELSE 0 END) assigned_cnt,
            MAX(COALESCE(assigned_vendor,0)) vendor_id
     FROM codes WHERE batch_id IS NOT NULL
     GROUP BY batch_id ORDER BY gen_at DESC LIMIT 80"
)->fetchAll();

$aPage = 'codes';
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="csrf-token" content="<?= generateCsrf() ?>">
  <title>Codes — <?= APP_NAME ?> Admin</title>
  <script src="<?= APP_URL ?>/assets/js/tailwind.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
  <style>
    *{font-family:'Poppins',sans-serif!important}
    code{font-family:'Courier New',monospace!important}
    @media print {
      body > *:not(#print-area){ display:none!important; }
      #print-area{ display:block!important; }
      @page{ margin:10mm; size:A4; }
    }
    #print-area{ display:none; }
    .ticket-grid{ display:grid; grid-template-columns:repeat(4,1fr); gap:6px; padding:8px; }
    .ticket-slip{ border:1.5px dashed #555; border-radius:6px; padding:10px 8px; text-align:center; background:#fff; color:#000; page-break-inside:avoid; }
    .ticket-slip .t-brand{ font-size:9px; font-weight:700; letter-spacing:1px; color:#ea580c; margin-bottom:4px; font-family:'Arial',sans-serif; }
    .ticket-slip .t-code{ font-size:15px; font-weight:900; letter-spacing:3px; font-family:'Courier New',monospace; color:#1a1a1a; line-height:1.2; }
    .ticket-slip .t-hint{ font-size:7px; color:#666; margin-top:4px; font-family:'Arial',sans-serif; }
    .ticket-slip .t-cut{ font-size:7px; color:#999; margin-top:5px; border-top:1px dotted #ccc; padding-top:4px; font-family:'Arial',sans-serif; }
  </style>
</head>
<body class="bg-[#0a0f1a] text-white">
<?php include __DIR__ . '/../components/admin-sidebar.php'; ?>

<!-- Hidden printable ticket area -->
<div id="print-area">
  <div style="font-family:'Arial',sans-serif;font-size:10px;color:#333;padding:6px 8px;border-bottom:2px solid #ea580c;margin-bottom:8px;display:flex;justify-content:space-between;align-items:center;">
    <div><strong style="font-size:14px;color:#ea580c;">ZOEFEEDS</strong> &nbsp;— Raffle Code Tickets</div>
    <div>Printed: <?= date('M j, Y g:i A') ?> &nbsp;·&nbsp; Cut along dotted lines &amp; distribute</div>
  </div>
  <div class="ticket-grid" id="ticket-grid"></div>
</div>

<div class="main-content">
  <div class="topbar">
    <button onclick="toggleSidebar()" class="md:hidden text-gray-400 text-2xl mr-3">☰</button>
    <h1 class="text-xl font-bold">Code Management</h1>
    <div class="flex gap-2 flex-wrap">
      <button onclick="Modal.open('unassign-modal')" class="btn btn-secondary btn-sm text-yellow-400">🔓 Unassign Batch</button>
      <button onclick="Modal.open('generate-modal')" class="btn btn-primary btn-sm">+ Generate Codes</button>
    </div>
  </div>

  <div class="p-4 md:p-6">

    <?php if ($msg): ?><div class="bg-green-500/10 border border-green-500/30 text-green-400 rounded-xl p-4 mb-5 text-sm"><?= $msg ?></div><?php endif; ?>
    <?php if ($err): ?><div class="bg-red-500/10 border border-red-500/30 text-red-400 rounded-xl p-4 mb-5 text-sm"><?= e($err) ?></div><?php endif; ?>

    <!-- Stats bar -->
    <div class="grid grid-cols-3 md:grid-cols-6 gap-3 mb-5">
      <?php $cs = [
        ['Unassigned','unassigned','text-gray-300'],
        ['Assigned',  'assigned',  'text-blue-400'],
        ['Redeemed',  'redeemed',  'text-green-400'],
        ['In Draw',   'reserved',  'text-yellow-400'],
        ['Used',      'used',      'text-red-400'],
        ['Transferred','transferred','text-purple-400'],
      ]; ?>
      <?php foreach ($cs as $c): ?>
      <a href="?filter=<?= $c[1] ?>"
         class="card p-3 text-center hover:border-orange-500/30 transition-colors <?= $filter===$c[1]?'border-orange-500/50':'' ?>">
        <div class="text-xl font-black <?= $c[2] ?>"><?= number_format($cstats[$c[1]]) ?></div>
        <div class="text-xs text-gray-500 mt-0.5"><?= $c[0] ?></div>
      </a>
      <?php endforeach; ?>
    </div>

    <!-- Selection toolbar -->
    <div id="sel-toolbar" class="hidden mb-3 p-3 bg-orange-500/10 border border-orange-500/25 rounded-xl flex items-center justify-between gap-3 flex-wrap">
      <div class="flex items-center gap-3">
        <span class="text-sm text-orange-300 font-semibold"><span id="sel-count">0</span> code(s) selected</span>
        <button onclick="deselectAll()" class="text-xs text-gray-400 hover:text-white underline">Deselect all</button>
      </div>
      <div class="flex gap-2 flex-wrap">
        <button onclick="downloadSelected('txt')" class="btn btn-secondary btn-sm text-cyan-400">⬇ Download .txt</button>
        <button onclick="downloadSelected('csv')" class="btn btn-secondary btn-sm text-green-400">⬇ Download .csv</button>
        <button onclick="printTickets()"          class="btn btn-primary btn-sm">🖨️ Print Tickets</button>
        <button onclick="copySelected()"          class="btn btn-secondary btn-sm">📋 Copy</button>
      </div>
    </div>

    <!-- Search & filter -->
    <form method="GET" class="flex flex-wrap gap-3 mb-4">
      <div class="flex gap-2 flex-1 min-w-64">
        <input type="text" name="q" class="form-control font-mono"
               placeholder="Search code digits or paste BATCH-…" value="<?= e($q) ?>">
        <input type="hidden" name="filter" value="<?= e($filter) ?>">
        <button class="btn btn-primary px-5">Search</button>
      </div>
    </form>
    <div class="flex gap-2 mb-5 flex-wrap">
      <?php foreach (['all'=>'All','unassigned'=>'Unassigned','assigned'=>'Assigned','redeemed'=>'Redeemed','reserved'=>'In Draw','used'=>'Used'] as $v=>$l): ?>
      <a href="?filter=<?= $v ?><?= $q?'&q='.urlencode($q):'' ?>"
         class="btn btn-sm <?= $filter===$v?'btn-primary':'btn-secondary' ?>"><?= $l ?></a>
      <?php endforeach; ?>
      <a href="?export=csv&filter=<?= e($filter) ?><?= $q?'&q='.urlencode($q):'' ?>"
         class="btn btn-sm btn-secondary ml-auto text-gray-400">⬇ Export CSV</a>
    </div>

    <!-- Table -->
    <div class="card">
      <div class="table-wrap">
        <table id="codes-table">
          <thead>
            <tr>
              <th style="width:40px">
                <input type="checkbox" id="chk-all" class="w-4 h-4 accent-orange-500" onchange="toggleAll(this)">
              </th>
              <th>Code</th><th>Status</th><th>Owner</th><th>Vendor</th><th>Batch</th><th>Generated</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($codes as $c): ?>
            <tr class="code-row">
              <td>
                <input type="checkbox" class="code-chk w-4 h-4 accent-orange-500"
                       value="<?= e($c['code']) ?>" onchange="updateSel()">
              </td>
              <td>
                <code class="text-orange-400 font-bold tracking-widest text-sm select-all"><?= e($c['code']) ?></code>
              </td>
              <td>
                <span class="badge <?= match($c['status']) {
                  'unassigned'  => 'badge-muted',
                  'assigned'    => 'badge-info',
                  'redeemed'    => 'badge-success',
                  'reserved'    => 'badge-warning',
                  'used'        => 'badge-danger',
                  'transferred' => 'badge-muted',
                  default       => 'badge-muted'
                } ?>">
                  <?= $c['status'] ?>
                </span>
              </td>
              <td class="text-sm">
                <?= $c['owner_name']
                    ? e($c['owner_name'])
                    : '<span class="text-gray-600">—</span>' ?>
              </td>
              <td class="text-sm">
                <?= $c['vendor_name']
                    ? '<span class="text-purple-400">'.e($c['vendor_name']).'</span>'
                    : '<span class="text-gray-600">—</span>' ?>
              </td>
              <td>
                <?php if ($c['batch_id']): ?>
                <a href="?filter=all&q=<?= urlencode($c['batch_id']) ?>"
                   class="text-xs text-cyan-400 hover:underline font-mono truncate max-w-[110px] block">
                  <?= e($c['batch_id']) ?>
                </a>
                <?php else: ?>
                <span class="text-gray-600">—</span>
                <?php endif; ?>
              </td>
              <td class="text-xs text-gray-500"><?= date('M j, Y', strtotime($c['generated_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$codes): ?>
            <tr>
              <td colspan="7" class="text-center text-gray-500 py-10">
                No codes found<?= $q ? ' for "'.e($q).'"' : '' ?>
              </td>
            </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <?php if ($pages > 1): ?>
      <div class="flex items-center justify-between p-4 border-t border-white/5">
        <div class="text-sm text-gray-400"><?= number_format($total) ?> codes · Page <?= $page ?>/<?= $pages ?></div>
        <div class="flex gap-2">
          <?php if ($page > 1): ?>
          <a href="?page=<?=$page-1?>&filter=<?=e($filter)?>&q=<?=urlencode($q)?>" class="btn btn-sm btn-secondary">← Prev</a>
          <?php endif; ?>
          <?php if ($page < $pages): ?>
          <a href="?page=<?=$page+1?>&filter=<?=e($filter)?>&q=<?=urlencode($q)?>" class="btn btn-sm btn-secondary">Next →</a>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>

  </div>
</div>

<!-- ── GENERATE MODAL ──────────────────────────────────── -->
<div class="modal-overlay" id="generate-modal">
  <div class="modal-box" style="max-width:520px">
    <h3 class="text-xl font-bold mb-1">Generate Raffle Codes</h3>
    <p class="text-gray-400 text-sm mb-5">All codes are 15-digit numeric, cryptographically unique.</p>
    <form method="POST">
      <?= csrfField() ?><input type="hidden" name="action" value="generate">
      <div class="form-group">
        <label class="form-label">Quantity <span class="text-gray-500 font-normal">(max 10,000 per batch)</span></label>
        <input type="number" name="quantity" class="form-control" min="1" max="10000" value="100" required>
      </div>
      <div class="form-group">
        <label class="form-label">Assign to Vendor <span class="text-gray-500 font-normal">(optional)</span></label>
        <!-- ── FIX: options now from vendors table using business_name + code_balance ── -->
        <select name="assign_vendor" class="form-control"
                onchange="this.nextElementSibling.classList.toggle('hidden', this.value==='0')">
          <option value="0">— Keep unassigned —</option>
          <?php foreach ($vendors as $v): ?>
          <option value="<?= $v['id'] ?>">
            <?= e($v['full_name']) ?>
            <?= $v['business_name'] ? ' — '.e($v['business_name']) : '' ?>
            (<?= number_format((int)$v['code_balance']) ?> codes)
          </option>
          <?php endforeach; ?>
        </select>
        <div class="hidden mt-2 p-2 bg-purple-500/10 border border-purple-500/20 rounded-lg text-xs text-purple-300">
          Codes will be assigned directly to this vendor's inventory.
        </div>
      </div>
      <div class="flex gap-3">
        <button type="button" data-close-modal="generate-modal" class="btn btn-secondary flex-1">Cancel</button>
        <button type="submit" class="btn btn-primary flex-1">🎟️ Generate</button>
      </div>
    </form>
  </div>
</div>

<!-- ── UNASSIGN BATCH MODAL ────────────────────────────── -->
<div class="modal-overlay" id="unassign-modal">
  <div class="modal-box" style="max-width:540px">
    <h3 class="text-xl font-bold mb-1">🔓 Unassign Code Batch</h3>
    <p class="text-gray-400 text-sm mb-4">
      Returns assigned codes back to the unassigned pool.
      Only <code class="text-blue-400">assigned</code> codes are affected.
    </p>
    <form method="POST">
      <?= csrfField() ?><input type="hidden" name="action" value="unassign_batch">
      <div class="form-group">
        <label class="form-label">Batch ID</label>
        <input type="text" name="batch_id" id="unassign-batch-input" class="form-control font-mono"
               placeholder="BATCH-20260523T043041-AB12CD" required>
      </div>
      <?php if ($batches): ?>
      <div class="form-group">
        <label class="form-label">Or pick a recent batch</label>
        <div class="max-h-44 overflow-y-auto space-y-1 p-1">
          <?php foreach ($batches as $b): ?>
          <button type="button"
                  onclick="document.getElementById('unassign-batch-input').value='<?= e($b['batch_id']) ?>'"
                  class="w-full text-left p-3 rounded-lg border border-white/5 hover:border-orange-500/30 hover:bg-white/3 transition-colors">
            <div class="flex items-center justify-between gap-3">
              <code class="text-xs text-cyan-400 truncate"><?= e($b['batch_id']) ?></code>
              <?php if ($b['assigned_cnt'] > 0): ?>
              <span class="badge badge-info text-xs flex-shrink-0"><?= $b['assigned_cnt'] ?> assigned</span>
              <?php endif; ?>
            </div>
            <div class="text-xs text-gray-500 mt-0.5">
              <?= $b['cnt'] ?> total · <?= date('M j, Y', strtotime($b['gen_at'])) ?>
            </div>
          </button>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
      <div class="bg-yellow-500/10 border border-yellow-500/20 rounded-xl p-3 mb-4 text-xs text-yellow-300">
        ⚠️ This reduces the vendor's inventory balance by the number of unassigned codes.
      </div>
      <div class="flex gap-3">
        <button type="button" data-close-modal="unassign-modal" class="btn btn-secondary flex-1">Cancel</button>
        <button type="submit" class="btn btn-sm flex-1 py-3 font-bold text-white"
                style="background:#f59e0b"
                onclick="return confirm('Unassign all assigned codes in this batch?')">
          🔓 Unassign
        </button>
      </div>
    </form>
  </div>
</div>

<script>
  window.APP_URL = '<?= APP_URL ?>';
</script>
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
<script>
let selectedCodes = new Set();

function updateSel() {
  selectedCodes.clear();
  document.querySelectorAll('.code-chk:checked').forEach(c => selectedCodes.add(c.value));
  const n     = selectedCodes.size;
  const total = document.querySelectorAll('.code-chk').length;
  document.getElementById('sel-count').textContent = n;
  document.getElementById('sel-toolbar').classList.toggle('hidden', n === 0);
  const all = document.getElementById('chk-all');
  all.checked       = n === total && total > 0;
  all.indeterminate = n > 0 && n < total;
}
function toggleAll(master) {
  document.querySelectorAll('.code-chk').forEach(c => {
    c.checked = master.checked;
    master.checked ? selectedCodes.add(c.value) : selectedCodes.delete(c.value);
  });
  updateSel();
}
function deselectAll() {
  document.querySelectorAll('.code-chk').forEach(c => c.checked = false);
  selectedCodes.clear(); updateSel();
}
function downloadSelected(format) {
  if (!selectedCodes.size) { Toast.error('Select at least one code first.'); return; }
  const codes = [...selectedCodes];
  let content, filename, mime;
  if (format === 'txt') {
    content  = codes.join('\n');
    filename = 'zoefeeds-codes-' + new Date().toISOString().slice(0,10) + '.txt';
    mime     = 'text/plain';
  } else {
    content  = 'Code,Platform\n' + codes.map(c => `${c},ZoeFeeds`).join('\n');
    filename = 'zoefeeds-codes-' + new Date().toISOString().slice(0,10) + '.csv';
    mime     = 'text/csv';
  }
  const a = document.createElement('a');
  a.href  = URL.createObjectURL(new Blob([content], { type: mime }));
  a.download = filename; a.click();
  Toast.success(`Downloaded ${codes.length} code(s) as .${format}`);
}
function copySelected() {
  if (!selectedCodes.size) { Toast.error('Select codes first.'); return; }
  navigator.clipboard.writeText([...selectedCodes].join('\n'))
    .then(() => Toast.success(`${selectedCodes.size} code(s) copied!`));
}
function printTickets() {
  if (!selectedCodes.size) { Toast.error('Select codes to print first.'); return; }
  const grid = document.getElementById('ticket-grid');
  grid.innerHTML = '';
  [...selectedCodes].forEach(code => {
    const f = code.replace(/(\d{5})(\d{5})(\d{5})/, '$1-$2-$3');
    grid.insertAdjacentHTML('beforeend', `
      <div class="ticket-slip">
        <div class="t-brand">ZOEFEEDS</div>
        <div class="t-code">${f}</div>
        <div class="t-hint">Raffle Code — Redeem at zoefeeds.com</div>
        <div class="t-cut">✂ Cut here · Do not sell · Free gift only</div>
      </div>
    `);
  });
  document.getElementById('print-area').style.display = 'block';
  window.print();
  setTimeout(() => document.getElementById('print-area').style.display = 'none', 1500);
}
</script>
</body>
</html>
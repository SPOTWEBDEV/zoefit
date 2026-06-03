<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
$auth = requireAdmin(); $adminId = $auth['id'];
$db   = getDB();
$msg  = $err = '';

// ── Generate codes ─────────────────────────────────────────
if (isPost() && verifyCsrf($_POST[CSRF_TOKEN_NAME]??'') && ($_POST['action']??'')==='generate') {
  $qty        = min(10000, max(1,(int)($_POST['quantity']??0)));
  $assignTo   = (int)($_POST['assign_vendor']??0);  // 0 = unassigned
  $batch      = 'BATCH-'.date('Ymd').'T'.date('His').'-'.strtoupper(substr(md5(uniqid()),0,6));
  $status     = $assignTo ? 'assigned' : 'unassigned';
  $generated  = 0;

  // Validate vendor if provided
  $vendorName = '';
  if ($assignTo) {
    $v=$db->prepare("SELECT full_name FROM users WHERE id=? AND is_vendor=1 AND vendor_status='active'");
    $v->execute([$assignTo]); $v=$v->fetch();
    if (!$v) { $err='Selected vendor not found or not active.'; goto SKIP_GEN; }
    $vendorName = $v['full_name'];
  }

  $ins = $db->prepare("INSERT IGNORE INTO codes (code,status,generated_by,assigned_vendor,batch_id,assigned_at) VALUES (?,?,?,?,?,?)");
  for ($i=0, $attempts=0; $generated<$qty && $attempts<$qty*4; $attempts++) {
    $code=''; for($j=0;$j<15;$j++) $code.=random_int(0,9);
    $ins->execute([$code, $status, $adminId, $assignTo?:null, $batch, $assignTo?date('Y-m-d H:i:s'):null]);
    if ($ins->rowCount()) $generated++;
  }

  if ($assignTo && $generated) {
    $db->prepare("UPDATE users SET vendor_code_balance=vendor_code_balance+? WHERE id=?")->execute([$generated,$assignTo]);
  }
  auditLog('admin',$adminId,'generate_codes',
    "Generated $generated codes, batch: $batch".($assignTo?" → assigned to vendor $assignTo ($vendorName)":''));
  $msg = "✅ Generated <strong>$generated codes</strong>. Batch: <code>$batch</code>".($assignTo?" — Assigned to <strong>".e($vendorName)."</strong>":'');
  SKIP_GEN:;
}

// ── Unassign a batch ───────────────────────────────────────
if (isPost() && verifyCsrf($_POST[CSRF_TOKEN_NAME]??'') && ($_POST['action']??'')==='unassign_batch') {
  $batch = trim($_POST['batch_id']??'');
  if (!$batch) { $err='Batch ID required.'; }
  else {
    // Get vendor who had these codes
    $vendorRow=$db->prepare("SELECT assigned_vendor,COUNT(*) as cnt FROM codes WHERE batch_id=? AND status='assigned' GROUP BY assigned_vendor");
    $vendorRow->execute([$batch]); $vendorRow=$vendorRow->fetchAll();
    foreach($vendorRow as $vr){
      if ($vr['assigned_vendor']){
        $db->prepare("UPDATE users SET vendor_code_balance=GREATEST(0,vendor_code_balance-?) WHERE id=?")
           ->execute([$vr['cnt'],$vr['assigned_vendor']]);
      }
    }
    $db->prepare("UPDATE codes SET status='unassigned',assigned_vendor=NULL,assigned_at=NULL WHERE batch_id=? AND status='assigned'")
       ->execute([$batch]);
    $cnt=$db->prepare("SELECT ROW_COUNT()"); $unassigned=$db->query("SELECT ROW_COUNT()")->fetchColumn();
    auditLog('admin',$adminId,'unassign_batch',"Batch $batch unassigned");
    $msg = "✅ Batch <code>$batch</code> unassigned successfully.";
  }
}

// ── Export CSV ─────────────────────────────────────────────
if (isset($_GET['export'])) {
  $batch  = trim($_GET['batch']??'');
  $filter = $_GET['filter']??'all';
  $where  = '1=1'; $params=[];
  if ($batch)        { $where.=" AND batch_id=?"; $params[]=$batch; }
  elseif($filter!=='all') { $where.=" AND status=?"; $params[]=$filter; }
  $rows=$db->prepare("SELECT c.code,c.status,c.batch_id,u.full_name as owner,v.full_name as vendor,c.generated_at
    FROM codes c LEFT JOIN users u ON c.current_owner=u.id LEFT JOIN users v ON c.assigned_vendor=v.id
    WHERE $where LIMIT 100000");
  $rows->execute($params);
  $fname = 'zoefeeds-codes-'.($batch?:$filter).'-'.date('Ymd').'.csv';
  header('Content-Type: text/csv');
  header('Content-Disposition: attachment; filename="'.$fname.'"');
  $f=fopen('php://output','w');
  fputcsv($f,['Code','Status','Batch','Owner','Vendor','Generated']);
  while($r=$rows->fetch()) fputcsv($f,[$r['code'],$r['status'],$r['batch_id'],$r['owner']??'',$r['vendor']??'',$r['generated_at']]);
  fclose($f); exit;
}

// ── List / filter / search ─────────────────────────────────
$filter = $_GET['filter'] ?? 'all';
$page   = max(1,(int)($_GET['page']??1));
$per    = 25; $offset=($page-1)*$per;
$q      = trim($_GET['q']??'');    // can be a code fragment OR full batch ID

$where='1=1'; $params=[];
if ($filter!=='all')   { $where.=" AND c.status=?";      $params[]=$filter; }
if ($q) {
  if (str_starts_with($q,'BATCH-')) {
    $where.=" AND c.batch_id=?";   $params[]=$q;
  } else {
    $where.=" AND c.code LIKE ?";  $params[]="%$q%";
  }
}

$codes=$db->prepare(
  "SELECT c.*,
          u.full_name AS owner_name,
          v.full_name AS vendor_name
   FROM codes c
   LEFT JOIN users u ON c.current_owner=u.id
   LEFT JOIN users v ON c.assigned_vendor=v.id
   WHERE $where
   ORDER BY c.generated_at DESC
   LIMIT $per OFFSET $offset");
$codes->execute($params); $codes=$codes->fetchAll();

$cntS=$db->prepare("SELECT COUNT(*) FROM codes c WHERE $where");
$cntS->execute($params); $total=$cntS->fetchColumn();
$pages=ceil($total/$per);

// Stats bar
$cstats=array_fill_keys(['unassigned','assigned','redeemed','reserved','used','transferred'],0);
foreach($db->query("SELECT status,COUNT(*) c FROM codes GROUP BY status")->fetchAll() as $r)
  $cstats[$r['status']]=(int)$r['c'];

// All active vendors for generate form
$vendors=$db->query("SELECT id,full_name,vendor_business_name,vendor_code_balance FROM users WHERE is_vendor=1 AND vendor_status='active' ORDER BY full_name")->fetchAll();

// Batches for unassign picker
$batches=$db->query("SELECT batch_id, COUNT(*) as cnt, MIN(generated_at) as gen_at,
  SUM(CASE WHEN status='assigned' THEN 1 ELSE 0 END) as assigned_cnt,
  MAX(CASE WHEN assigned_vendor IS NOT NULL THEN assigned_vendor ELSE 0 END) as vendor_id
  FROM codes WHERE batch_id IS NOT NULL GROUP BY batch_id ORDER BY gen_at DESC LIMIT 100")->fetchAll();

$aPage='codes';
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="csrf-token" content="<?= generateCsrf() ?>">
  <title>Codes — <?= APP_NAME ?> Admin</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
  <style>*{font-family:'Poppins',sans-serif!important}code{font-family:'Courier New',monospace!important}</style>
</head>
<body class="bg-[#0a0f1a] text-white">
<?php include __DIR__ . '/../components/admin-sidebar.php'; ?>
<div class="main-content">
  <div class="topbar">
    <button onclick="toggleSidebar()" class="md:hidden text-gray-400 text-2xl mr-3">☰</button>
    <h1 class="text-xl font-bold">Code Management</h1>
    <div class="flex gap-2 flex-wrap">
      <a href="?export=csv&filter=<?= e($filter) ?><?= $q?'&batch='.urlencode($q):'' ?>" class="btn btn-secondary btn-sm">⬇ Export CSV</a>
      <button onclick="Modal.open('unassign-modal')" class="btn btn-secondary btn-sm text-yellow-400">🔓 Unassign Batch</button>
      <button onclick="Modal.open('generate-modal')" class="btn btn-primary btn-sm">+ Generate Codes</button>
    </div>
  </div>

  <div class="p-4 md:p-6">
    <?php if($msg): ?><div class="bg-green-500/10 border border-green-500/30 text-green-400 rounded-xl p-4 mb-5 text-sm"><?= $msg ?></div><?php endif; ?>
    <?php if($err): ?><div class="bg-red-500/10 border border-red-500/30 text-red-400 rounded-xl p-4 mb-5 text-sm"><?= e($err) ?></div><?php endif; ?>

    <!-- Stats -->
    <div class="grid grid-cols-3 md:grid-cols-6 gap-3 mb-5">
      <?php $cs=[['Unassigned','unassigned','text-gray-400'],['Assigned','assigned','text-blue-400'],['Redeemed','redeemed','text-green-400'],['In Draw','reserved','text-yellow-400'],['Used','used','text-red-400'],['Transferred','transferred','text-purple-400']]; ?>
      <?php foreach($cs as $c): ?>
      <a href="?filter=<?= $c[1] ?>" class="card p-3 text-center hover:border-orange-500/30 transition-colors <?= $filter===$c[1]?'border-orange-500/50':'' ?>">
        <div class="text-xl font-black <?= $c[2] ?>"><?= number_format($cstats[$c[1]]) ?></div>
        <div class="text-xs text-gray-500 mt-0.5"><?= $c[0] ?></div>
      </a>
      <?php endforeach; ?>
    </div>

    <!-- Search & Filter -->
    <form method="GET" class="flex flex-wrap gap-3 mb-4">
      <div class="flex gap-2 flex-1 min-w-64">
        <input type="text" name="q" class="form-control font-mono" placeholder="Search code digits or paste BATCH-20260523T..." value="<?= e($q) ?>">
        <input type="hidden" name="filter" value="<?= e($filter) ?>">
        <button class="btn btn-primary px-5">Search</button>
      </div>
    </form>
    <div class="flex gap-2 mb-5 flex-wrap">
      <?php foreach(['all'=>'All','unassigned'=>'Unassigned','assigned'=>'Assigned','redeemed'=>'Redeemed','reserved'=>'In Draw','used'=>'Used'] as $v=>$l): ?>
      <a href="?filter=<?= $v ?><?= $q?'&q='.urlencode($q):'' ?>" class="btn btn-sm <?= $filter===$v?'btn-primary':'btn-secondary' ?>"><?= $l ?></a>
      <?php endforeach; ?>
    </div>

    <!-- Selection toolbar (shown when items checked) -->
    <div id="sel-toolbar" class="hidden mb-3 p-3 bg-orange-500/10 border border-orange-500/25 rounded-xl flex items-center justify-between gap-3 flex-wrap">
      <div class="flex items-center gap-3">
        <span class="text-sm text-orange-300 font-semibold"><span id="sel-count">0</span> code(s) selected</span>
        <button onclick="deselectAll()" class="text-xs text-gray-400 hover:text-white">Deselect all</button>
      </div>
      <div class="flex gap-2 flex-wrap">
        <button onclick="downloadSelected('txt')" class="btn btn-secondary btn-sm text-cyan-400">⬇ Download .txt</button>
        <button onclick="downloadSelected('csv')" class="btn btn-primary btn-sm">⬇ Download .csv</button>
        <button onclick="copySelected()" class="btn btn-secondary btn-sm text-green-400">📋 Copy</button>
      </div>
    </div>

    <!-- Table -->
    <div class="card">
      <div class="table-wrap">
        <table id="codes-table">
          <thead>
            <tr>
              <th style="width:40px">
                <input type="checkbox" id="chk-all" class="w-4 h-4 accent-orange-500" onchange="toggleAllCodes(this)">
              </th>
              <th>Code</th><th>Status</th><th>Owner</th><th>Vendor</th><th>Batch</th><th>Generated</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach($codes as $c): ?>
            <tr class="code-row" data-code="<?= e($c['code']) ?>">
              <td>
                <input type="checkbox" class="code-chk w-4 h-4 accent-orange-500"
                       value="<?= e($c['code']) ?>" onchange="updateSel()">
              </td>
              <td><code class="text-orange-400 font-bold tracking-widest text-sm select-all"><?= e($c['code']) ?></code></td>
              <td><span class="badge <?= match($c['status']){'unassigned'=>'badge-muted','assigned'=>'badge-info','redeemed'=>'badge-success','reserved'=>'badge-warning','used'=>'badge-danger','transferred'=>'badge-muted',default=>'badge-muted'} ?>"><?= $c['status'] ?></span></td>
              <td class="text-sm"><?= $c['owner_name'] ? e($c['owner_name']) : '<span class="text-gray-600">—</span>' ?></td>
              <td class="text-sm"><?= $c['vendor_name'] ? '<span class="text-purple-400">'.e($c['vendor_name']).'</span>' : '<span class="text-gray-600">—</span>' ?></td>
              <td>
                <?php if($c['batch_id']): ?>
                <button onclick="searchBatch('<?= e($c['batch_id']) ?>')" class="text-xs text-cyan-400 hover:underline font-mono truncate max-w-[120px] block"><?= e($c['batch_id']) ?></button>
                <?php else: ?>—<?php endif; ?>
              </td>
              <td class="text-xs text-gray-500"><?= date('M j, Y', strtotime($c['generated_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if(!$codes): ?><tr><td colspan="7" class="text-center text-gray-500 py-10">No codes found<?= $q?' for "'.e($q).'"':'' ?></td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>

      <?php if($pages>1): ?>
      <div class="flex items-center justify-between p-4 border-t border-white/5">
        <div class="text-sm text-gray-400"><?= number_format($total) ?> codes · Page <?= $page ?>/<?= $pages ?></div>
        <div class="flex gap-2">
          <?php if($page>1): ?><a href="?page=<?=$page-1?>&filter=<?=e($filter)?>&q=<?=urlencode($q)?>" class="btn btn-sm btn-secondary">← Prev</a><?php endif; ?>
          <?php if($page<$pages): ?><a href="?page=<?=$page+1?>&filter=<?=e($filter)?>&q=<?=urlencode($q)?>" class="btn btn-sm btn-secondary">Next →</a><?php endif; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- ── GENERATE MODAL ─────────────────────────────────── -->
<div class="modal-overlay" id="generate-modal">
  <div class="modal-box" style="max-width:520px">
    <h3 class="text-xl font-bold mb-1">Generate Raffle Codes</h3>
    <p class="text-gray-400 text-sm mb-5">All codes are 15-digit numeric, cryptographically unique.</p>
    <form method="POST" enctype="multipart/form-data">
      <?= csrfField() ?><input type="hidden" name="action" value="generate">
      <div class="form-group">
        <label class="form-label">Quantity <span class="text-gray-500 font-normal">(max 10,000 per batch)</span></label>
        <input type="number" name="quantity" class="form-control" min="1" max="10000" value="100" required>
      </div>
      <div class="form-group">
        <label class="form-label">Assign to Vendor <span class="text-gray-500 font-normal">(optional — leave blank to keep unassigned)</span></label>
        <select name="assign_vendor" class="form-control" id="vendor-select" onchange="updateVendorInfo(this)">
          <option value="0">— Keep unassigned —</option>
          <?php foreach($vendors as $v): ?>
          <option value="<?= $v['id'] ?>" data-balance="<?= $v['vendor_code_balance'] ?>">
            <?= e($v['full_name']) ?><?= $v['vendor_business_name']?' — '.e($v['vendor_business_name']):'' ?>
            (<?= $v['vendor_code_balance'] ?> codes)
          </option>
          <?php endforeach; ?>
        </select>
        <div id="vendor-info" class="hidden mt-2 p-3 bg-purple-500/10 border border-purple-500/20 rounded-lg text-xs text-purple-300"></div>
        <?php if(!$vendors): ?>
        <div class="text-xs text-yellow-400 mt-1">⚠️ No active vendors found. <a href="<?= APP_URL ?>/admin/vendor-requests.php?tab=approved" class="underline">Approve a vendor first →</a></div>
        <?php endif; ?>
      </div>
      <div class="flex gap-3">
        <button type="button" data-close-modal="generate-modal" class="btn btn-secondary flex-1">Cancel</button>
        <button type="submit" class="btn btn-primary flex-1">🎟️ Generate</button>
      </div>
    </form>
  </div>
</div>

<!-- ── UNASSIGN BATCH MODAL ───────────────────────────── -->
<div class="modal-overlay" id="unassign-modal">
  <div class="modal-box" style="max-width:540px">
    <h3 class="text-xl font-bold mb-1">🔓 Unassign Code Batch</h3>
    <p class="text-gray-400 text-sm mb-5">Returns assigned codes back to the unassigned pool. Only codes with status <code class="text-blue-400">assigned</code> are affected — already redeemed or used codes remain unchanged.</p>
    <form method="POST">
      <?= csrfField() ?><input type="hidden" name="action" value="unassign_batch">
      <div class="form-group">
        <label class="form-label">Batch ID</label>
        <input type="text" name="batch_id" id="unassign-batch-input" class="form-control font-mono" placeholder="BATCH-20260523T043041-AB12CD" required>
      </div>
      <!-- Recent batches -->
      <?php if($batches): ?>
      <div class="form-group">
        <label class="form-label">Or pick a recent batch</label>
        <div class="max-h-48 overflow-y-auto space-y-1.5 p-1">
          <?php foreach($batches as $b): ?>
          <?php $vLabel=''; if($b['vendor_id']){$vn=$db->prepare("SELECT full_name FROM users WHERE id=?");$vn->execute([$b['vendor_id']]);$vn=$vn->fetch();$vLabel=$vn?e($vn['full_name']):'';} ?>
          <button type="button" onclick="pickBatch('<?= e($b['batch_id']) ?>')"
            class="w-full text-left p-3 rounded-lg border border-white/5 hover:border-orange-500/30 hover:bg-white/3 transition-colors">
            <div class="flex items-center justify-between gap-3">
              <div class="font-mono text-xs text-cyan-400 truncate"><?= e($b['batch_id']) ?></div>
              <div class="flex items-center gap-2 flex-shrink-0">
                <?php if($b['assigned_cnt']>0): ?><span class="badge badge-info text-xs"><?= $b['assigned_cnt'] ?> assigned</span><?php endif; ?>
                <?php if($vLabel): ?><span class="text-xs text-purple-400 truncate max-w-28"><?= $vLabel ?></span><?php endif; ?>
              </div>
            </div>
            <div class="text-xs text-gray-500 mt-0.5"><?= $b['cnt'] ?> total · Generated <?= date('M j, Y g:ia',strtotime($b['gen_at'])) ?></div>
          </button>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
      <div class="bg-yellow-500/10 border border-yellow-500/20 rounded-xl p-4 mb-4 text-xs text-yellow-300">
        ⚠️ This will reduce the vendor's inventory balance by the number of unassigned codes.
      </div>
      <div class="flex gap-3">
        <button type="button" data-close-modal="unassign-modal" class="btn btn-secondary flex-1">Cancel</button>
        <button type="submit" class="btn btn-sm flex-1 py-3 font-bold text-white" style="background:#f59e0b" onclick="return confirm('Unassign all assigned codes in this batch?')">🔓 Unassign Batch</button>
      </div>
    </form>
  </div>
</div>

<script src="<?= APP_URL ?>/assets/js/app.js"></script>
<script>
function searchBatch(batchId) {
  window.location = '?q=' + encodeURIComponent(batchId) + '&filter=all';
}
function pickBatch(batchId) {
  document.getElementById('unassign-batch-input').value = batchId;
}
function updateVendorInfo(sel) {
  const opt = sel.options[sel.selectedIndex];
  const el  = document.getElementById('vendor-info');
  if (sel.value==='0') { el.classList.add('hidden'); return; }
  const balance = opt.dataset.balance;
  el.textContent = `Current inventory: ${balance} codes. Generated codes will be added on top.`;
  el.classList.remove('hidden');
}
</script>
</body></html>
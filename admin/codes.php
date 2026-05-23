<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
$auth = requireAdmin(); $adminId = $auth['id'];
$db = getDB();
$msg=$err='';

// Handle generate
if (isPost() && verifyCsrf($_POST[CSRF_TOKEN_NAME]??'') && ($_POST['action']??'')==='generate') {
  $qty   = min(10000,max(1,(int)($_POST['quantity']??0)));
  $batch = 'BATCH-'.date('YmdHis').'-'.strtoupper(substr(md5(uniqid()),0,6));
  $generated = 0; $maxAttempts = $qty*3;

  $stmt = $db->prepare("INSERT IGNORE INTO codes (code,status,generated_by,batch_id) VALUES (?,?,?,?)");
  for ($i=0; $i<$maxAttempts && $generated<$qty; $i++) {
    // Generate unique 15-digit numeric code
    $code = '';
    for ($j=0;$j<15;$j++) $code .= random_int(0,9);
    // Check uniqueness via INSERT IGNORE
    $stmt->execute([$code,'unassigned',$adminId,$batch]);
    if ($stmt->rowCount()) $generated++;
  }
  auditLog('admin',$adminId,'generate_codes',"Generated $generated codes, batch: $batch");
  $msg = "✓ Generated $generated codes. Batch ID: $batch";
}

// Handle export
if (isset($_GET['export']) && $_GET['export']==='csv') {
  $batch = $_GET['batch']??'';
  $where = $batch ? "WHERE batch_id=?" : "WHERE status='unassigned'";
  $params = $batch?[$batch]:[];
  $rows = $db->prepare("SELECT code,status,batch_id,generated_at FROM codes $where LIMIT 50000");
  $rows->execute($params); $rows=$rows->fetchAll();
  header('Content-Type: text/csv');
  header('Content-Disposition: attachment; filename="zoefeeds-codes-'.date('Y-m-d').'.csv"');
  $out = fopen('php://output','w');
  fputcsv($out,['Code','Status','Batch','Generated At']);
  foreach($rows as $r) fputcsv($out,[$r['code'],$r['status'],$r['batch_id'],$r['generated_at']]);
  fclose($out); exit;
}

$filter = $_GET['filter']??'all'; $page=max(1,(int)($_GET['page']??1)); $per=25; $offset=($page-1)*$per;
$where='1=1'; $params=[];
if ($filter!=='all') { $where="status=?"; $params[]=$filter; }
$q=trim($_GET['q']??'');
if ($q) { $where.=" AND code LIKE ?"; $params[]="%$q%"; }
$codes=$db->prepare("SELECT c.*,u.full_name as owner_name,v.full_name as vendor_name FROM codes c LEFT JOIN users u ON c.current_owner=u.id LEFT JOIN vendors v ON c.assigned_vendor=v.id WHERE $where ORDER BY c.generated_at DESC LIMIT $per OFFSET $offset");
$codes->execute($params);$codes=$codes->fetchAll();
$total=$db->prepare("SELECT COUNT(*) FROM codes WHERE $where");$total->execute($params);$total=$total->fetchColumn();
$pages=ceil($total/$per);

// Stats
$cstats=['unassigned'=>0,'assigned'=>0,'redeemed'=>0,'reserved'=>0,'used'=>0];
foreach($db->query("SELECT status,COUNT(*) c FROM codes GROUP BY status")->fetchAll() as $r) $cstats[$r['status']]=$r['c'];

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
  <style>*{font-family:'Poppins',sans-serif!important}</style>
</head>
<body class="bg-[#0a0f1a] text-white">
<?php include __DIR__ . '/../components/admin-sidebar.php'; ?>
<div class="main-content">
  <div class="topbar">
    <button onclick="toggleSidebar()" class="md:hidden text-gray-400 text-2xl mr-3">☰</button>
    <h1 class="text-xl font-bold">Code Management</h1>
    <div class="flex gap-2">
      <a href="?export=csv" class="btn btn-secondary btn-sm">⬇ Export CSV</a>
      <button onclick="Modal.open('generate-modal')" class="btn btn-primary btn-sm">+ Generate Codes</button>
    </div>
  </div>
  <div class="p-6">
    <?php if($msg): ?><div class="bg-green-500/10 border border-green-500/30 text-green-400 rounded-xl p-4 mb-5 text-sm"><?= e($msg) ?></div><?php endif; ?>

    <!-- Stats -->
    <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-5">
      <?php $cs=[['Unassigned','unassigned','text-gray-400'],['Assigned','assigned','text-blue-400'],['Redeemed','redeemed','text-green-400'],['In Draw','reserved','text-yellow-400'],['Used','used','text-red-400']]; ?>
      <?php foreach($cs as $c): ?>
      <a href="?filter=<?= $c[1] ?>" class="card p-4 text-center hover:border-orange-500/30">
        <div class="text-xl font-black <?= $c[2] ?>"><?= number_format($cstats[$c[1]]??0) ?></div>
        <div class="text-xs text-gray-500 mt-1"><?= $c[0] ?></div>
      </a>
      <?php endforeach; ?>
    </div>

    <!-- Filters -->
    <div class="flex flex-wrap gap-3 mb-5">
      <form class="flex gap-2 flex-1 min-w-48" method="GET">
        <input type="text" name="q" class="form-control font-mono" placeholder="Search code…" value="<?= e($q) ?>">
        <input type="hidden" name="filter" value="<?= e($filter) ?>">
        <button class="btn btn-primary px-4">Search</button>
      </form>
      <div class="flex gap-2 flex-wrap">
        <?php foreach(['all'=>'All','unassigned'=>'Unassigned','assigned'=>'Assigned','redeemed'=>'Redeemed','reserved'=>'In Draw','used'=>'Used'] as $v=>$l): ?>
        <a href="?filter=<?= $v ?>" class="btn btn-sm <?= $filter===$v?'btn-primary':'btn-secondary' ?>"><?= $l ?></a>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Table -->
    <div class="card">
      <div class="table-wrap">
        <table>
          <thead><tr><th>Code</th><th>Status</th><th>Owner</th><th>Vendor</th><th>Batch</th><th>Generated</th></tr></thead>
          <tbody>
            <?php foreach($codes as $c): ?>
            <tr>
              <td><span class="font-mono text-orange-400 font-semibold tracking-widest text-sm"><?= e($c['code']) ?></span></td>
              <td><span class="badge <?= match($c['status']){'unassigned'=>'badge-muted','assigned'=>'badge-info','redeemed'=>'badge-success','reserved'=>'badge-warning','used'=>'badge-danger',default=>'badge-muted'} ?>"><?= $c['status'] ?></span></td>
              <td class="text-sm"><?= $c['owner_name'] ? e($c['owner_name']) : '<span class="text-gray-600">—</span>' ?></td>
              <td class="text-sm"><?= $c['vendor_name'] ? e($c['vendor_name']) : '<span class="text-gray-600">—</span>' ?></td>
              <td class="text-xs text-gray-500 font-mono"><?= e(substr($c['batch_id']??'—',0,20)) ?></td>
              <td class="text-xs text-gray-500"><?= date('M j, Y',strtotime($c['generated_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if(!$codes): ?><tr><td colspan="6" class="text-center text-gray-500 py-8">No codes found</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
      <?php if($pages>1): ?>
      <div class="flex items-center justify-between p-4 border-t border-white/5">
        <div class="text-sm text-gray-400"><?= number_format($total) ?> codes · Page <?= $page ?> of <?= $pages ?></div>
        <div class="flex gap-2">
          <?php if($page>1): ?><a href="?page=<?= $page-1 ?>&filter=<?= $filter ?>&q=<?= urlencode($q) ?>" class="btn btn-sm btn-secondary">← Prev</a><?php endif; ?>
          <?php if($page<$pages): ?><a href="?page=<?= $page+1 ?>&filter=<?= $filter ?>&q=<?= urlencode($q) ?>" class="btn btn-sm btn-secondary">Next →</a><?php endif; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Generate Modal -->
<div class="modal-overlay" id="generate-modal">
  <div class="modal-box">
    <h3 class="text-xl font-bold mb-2">Generate Raffle Codes</h3>
    <p class="text-gray-400 text-sm mb-5">Codes are 15-digit numeric, fully unique, and collision-proof.</p>
    <form method="POST">
      <?= csrfField() ?><input type="hidden" name="action" value="generate">
      <div class="form-group">
        <label class="form-label">Quantity to Generate</label>
        <input type="number" name="quantity" class="form-control" min="1" max="10000" value="100" required>
        <div class="text-xs text-gray-500 mt-1">Max 10,000 per batch</div>
      </div>
      <div class="flex gap-3">
        <button type="button" data-close-modal="generate-modal" class="btn btn-secondary flex-1">Cancel</button>
        <button type="submit" class="btn btn-primary flex-1">🎟️ Generate</button>
      </div>
    </form>
  </div>
</div>
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
</body></html>

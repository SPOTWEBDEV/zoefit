<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
$auth = requireUser(); $userId = $auth['id'];
$db   = getDB();

// ── Filter parameters ──────────────────────────────────────
$type      = trim($_GET['type']   ?? 'all');
$category  = trim($_GET['cat']    ?? 'all');
$dateFrom  = trim($_GET['from']   ?? '');
$dateTo    = trim($_GET['to']     ?? '');
$search    = trim($_GET['q']      ?? '');
$page      = max(1,(int)($_GET['page'] ?? 1));
$per       = 25; $offset = ($page-1)*$per;

$allowedTypes = ['all','credit','debit'];
$allowedCats  = ['all','redemption','transfer_in','transfer_out','draw_entry','vendor_credit','draw_deduction'];
if (!in_array($type,$allowedTypes))    $type     = 'all';
if (!in_array($category,$allowedCats)) $category = 'all';

// ── Build WHERE ────────────────────────────────────────────
$where  = "t.user_id=?"; $params = [$userId];
if ($type !== 'all')     { $where .= " AND t.type=?"; $params[] = $type; }
if ($category !== 'all') { $where .= " AND t.category=?"; $params[] = $category; }
if ($dateFrom)           { $where .= " AND DATE(t.created_at) >= ?"; $params[] = $dateFrom; }
if ($dateTo)             { $where .= " AND DATE(t.created_at) <= ?"; $params[] = $dateTo; }
if ($search)             { $where .= " AND (t.description LIKE ? OR c.code LIKE ?)"; $s="%$search%"; $params=array_merge($params,[$s,$s]); }

$rows = $db->prepare(
  "SELECT t.*, c.code
   FROM transactions t
   LEFT JOIN codes c ON t.code_id = c.id
   WHERE $where
   ORDER BY t.created_at DESC
   LIMIT $per OFFSET $offset"
);
$rows->execute($params); $rows = $rows->fetchAll();

$cntS = $db->prepare("SELECT COUNT(*) FROM transactions t LEFT JOIN codes c ON t.code_id=c.id WHERE $where");
$cntS->execute($params); $total = $cntS->fetchColumn();
$pages = ceil($total / $per);

// ── Summary stats (filtered) ───────────────────────────────
$creditS = $db->prepare("SELECT COALESCE(SUM(amount),0) FROM transactions t LEFT JOIN codes c ON t.code_id=c.id WHERE $where AND t.type='credit'");
$creditS->execute($params); $totalCredits = $creditS->fetchColumn();
$debitS = $db->prepare("SELECT COALESCE(SUM(ABS(amount)),0) FROM transactions t LEFT JOIN codes c ON t.code_id=c.id WHERE $where AND t.type='debit'");
$debitS->execute($params); $totalDebits = $debitS->fetchColumn();

// Category counts
$catCounts=[];
foreach($allowedCats as $c){
  if($c==='all') continue;
  $s=$db->prepare("SELECT COUNT(*) FROM transactions WHERE user_id=? AND category=?");
  $s->execute([$userId,$c]); $catCounts[$c]=(int)$s->fetchColumn();
}

$currentPage='transactions'; $pageTitle='Transaction History';
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="csrf-token" content="<?= generateCsrf() ?>">
  <title><?= e($pageTitle) ?> — <?= APP_NAME ?></title>
  <script src="<?= APP_URL ?>/assets/js/tailwind.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
  <style>
    *{font-family:'Poppins',sans-serif!important}
    .chip{display:inline-flex;align-items:center;gap:5px;padding:5px 12px;border-radius:20px;font-size:12px;font-weight:600;border:1px solid rgba(255,255,255,0.08);cursor:pointer;transition:all .2s;text-decoration:none;white-space:nowrap;}
    .chip:hover{border-color:rgba(249,115,22,.4);color:#f97316;}
    .chip.active{background:rgba(249,115,22,.15);border-color:rgba(249,115,22,.5);color:#f97316;}
    .txn-row{background:var(--bg-card);border:1px solid var(--border);border-radius:14px;padding:14px 16px;display:flex;align-items:center;gap:14px;transition:border-color .2s;}
    .txn-row:hover{border-color:rgba(249,115,22,.2);}
  </style>
</head>
<body class="bg-[#0a0f1a] text-white">
<?php include __DIR__ . '/../components/user-sidebar.php'; ?>
<div class="main-content">
  <div class="topbar">
    <button onclick="toggleSidebar()" class="md:hidden text-gray-400 text-2xl mr-3">☰</button>
    <h1 class="text-xl font-bold">Transaction History</h1>
    <div class="text-sm text-gray-400"><?= number_format($total) ?> record<?= $total!=1?'s':'' ?></div>
  </div>

  <div class="p-4 md:p-6 pb-24 md:pb-6">

    <!-- ── Summary Stats ───────────────────────────────── -->
    <div class="grid grid-cols-3 gap-3 mb-5">
      <div class="card p-4 text-center">
        <div class="text-xl font-black text-green-400">+<?= number_format($totalCredits) ?></div>
        <div class="text-xs text-gray-500 mt-0.5">Codes In</div>
      </div>
      <div class="card p-4 text-center">
        <div class="text-xl font-black text-red-400">-<?= number_format($totalDebits) ?></div>
        <div class="text-xs text-gray-500 mt-0.5">Codes Out</div>
      </div>
      <div class="card p-4 text-center">
        <div class="text-xl font-black text-orange-400"><?= number_format($total) ?></div>
        <div class="text-xs text-gray-500 mt-0.5">Transactions</div>
      </div>
    </div>

    <!-- ── Filter Form ──────────────────────────────────── -->
    <form method="GET" class="card p-4 mb-5 space-y-4">

      <!-- Search -->
      <div class="flex gap-2">
        <div class="relative flex-1">
          <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
          <input type="text" name="q" class="form-control pl-9" placeholder="Search code, description…" value="<?= e($search) ?>">
        </div>
        <button class="btn btn-primary px-5 flex-shrink-0">Search</button>
        <?php if($search||$type!=='all'||$category!=='all'||$dateFrom||$dateTo): ?>
        <a href="?" class="btn btn-secondary px-4 flex-shrink-0">Clear</a>
        <?php endif; ?>
      </div>

      <!-- Date range -->
      <div class="grid grid-cols-2 gap-3">
        <div>
          <label class="form-label">From Date</label>
          <input type="date" name="from" class="form-control" value="<?= e($dateFrom) ?>">
        </div>
        <div>
          <label class="form-label">To Date</label>
          <input type="date" name="to" class="form-control" value="<?= e($dateTo) ?>">
        </div>
      </div>

      <!-- Type: Credit / Debit -->
      <div>
        <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Transaction Type</div>
        <div class="flex gap-2 flex-wrap">
          <?php foreach(['all'=>'All Types','credit'=>'+ Credits','debit'=>'- Debits'] as $v=>$l): ?>
          <a href="?<?= http_build_query(['type'=>$v,'cat'=>$category,'from'=>$dateFrom,'to'=>$dateTo,'q'=>$search]) ?>"
             class="chip <?= $type===$v?'active':'' ?>"><?= $l ?></a>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Category chips -->
      <div>
        <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Category</div>
        <div class="flex gap-2 flex-wrap">
          <?php $catLabels=['all'=>'All','redemption'=>'🎟️ Redemption','transfer_in'=>'⬇️ Transfer In','transfer_out'=>'⬆️ Transfer Out',
            'draw_entry'=>'🎯 Draw Entry','vendor_credit'=>'🏪 Vendor Credit','draw_deduction'=>'❌ Draw Deduction'];
          foreach($catLabels as $v=>$l):
            $cnt = $v==='all' ? array_sum($catCounts) : ($catCounts[$v]??0);
          ?>
          <a href="?<?= http_build_query(['type'=>$type,'cat'=>$v,'from'=>$dateFrom,'to'=>$dateTo,'q'=>$search]) ?>"
             class="chip <?= $category===$v?'active':'' ?>">
            <?= $l ?>
            <?php if($cnt>0): ?><span class="bg-white/10 rounded-full px-1.5 text-xs"><?= $cnt ?></span><?php endif; ?>
          </a>
          <?php endforeach; ?>
        </div>
      </div>

    </form>

    <!-- ── Transactions List ────────────────────────────── -->
    <?php
    $icons=['redemption'=>'🎟️','transfer_in'=>'⬇️','transfer_out'=>'⬆️','draw_entry'=>'🎯','vendor_credit'=>'🏪','draw_deduction'=>'❌'];
    $ibg  =['redemption'=>'bg-green-500/15','transfer_in'=>'bg-blue-500/15','transfer_out'=>'bg-orange-500/15',
            'draw_entry'=>'bg-orange-500/15','vendor_credit'=>'bg-purple-500/15','draw_deduction'=>'bg-red-500/15'];
    ?>
    <?php if ($rows): ?>
    <div class="space-y-2">
      <?php foreach ($rows as $t):
        $icon = $icons[$t['category']] ?? '💳';
        $bg   = $ibg[$t['category']] ?? 'bg-gray-500/15';
        $catLabel = ucwords(str_replace('_',' ',$t['category']));
      ?>
      <div class="txn-row fade-in">
        <div class="w-11 h-11 rounded-xl <?= $bg ?> flex items-center justify-center text-xl flex-shrink-0"><?= $icon ?></div>
        <div class="flex-1 min-w-0">
          <div class="font-semibold text-sm"><?= $catLabel ?></div>
          <?php if($t['code']): ?><div class="text-xs text-gray-400 font-mono mt-0.5"><?= e($t['code']) ?></div><?php endif; ?>
          <?php if($t['description']): ?><div class="text-xs text-gray-500 mt-0.5 truncate"><?= e($t['description']) ?></div><?php endif; ?>
          <div class="text-xs text-gray-600 mt-1"><?= date('M j, Y · g:i A', strtotime($t['created_at'])) ?></div>
        </div>
        <div class="text-right flex-shrink-0">
          <div class="font-bold text-base <?= $t['type']==='credit'?'text-green-400':'text-red-400' ?>">
            <?= $t['type']==='credit'?'+':'-' ?><?= abs($t['amount']) ?>
          </div>
          <div class="text-xs text-gray-500"><?= $t['type']==='credit'?'credit':'debit' ?></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if ($pages > 1): ?>
    <div class="flex items-center justify-between mt-6">
      <div class="text-sm text-gray-400">Page <?= $page ?>/<?= $pages ?> · <?= number_format($total) ?> records</div>
      <div class="flex gap-2">
        <?php $qBase=['type'=>$type,'cat'=>$category,'from'=>$dateFrom,'to'=>$dateTo,'q'=>$search];
        if($page>1): ?><a href="?<?= http_build_query(array_merge($qBase,['page'=>$page-1])) ?>" class="btn btn-sm btn-secondary">← Prev</a><?php endif; ?>
        <?php if($page<$pages): ?><a href="?<?= http_build_query(array_merge($qBase,['page'=>$page+1])) ?>" class="btn btn-sm btn-secondary">Next →</a><?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php else: ?>
    <div class="card p-12 text-center">
      <div class="text-5xl mb-4">📊</div>
      <div class="font-semibold text-gray-300 mb-2">No transactions found</div>
      <?php if($search||$type!=='all'||$category!=='all'||$dateFrom||$dateTo): ?>
      <div class="text-gray-500 text-sm mb-4">No records match your filters.</div>
      <a href="?" class="btn btn-secondary btn-sm">Clear Filters</a>
      <?php else: ?>
      <div class="text-gray-500 text-sm">No transactions yet. <a href="<?= APP_URL ?>/user/redeem.php" class="text-orange-400 hover:underline">Redeem a code to get started →</a></div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

  </div>
</div>
<script>
  window.APP_URL = '<?= APP_URL ?>';
</script>
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
</body></html>

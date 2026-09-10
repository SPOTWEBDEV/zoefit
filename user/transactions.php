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

// Category counts + labels
$catLabels=['all'=>'All Categories','redemption'=>'Redemption','transfer_in'=>'Transfer In','transfer_out'=>'Transfer Out',
  'draw_entry'=>'Draw Entry','vendor_credit'=>'Vendor Credit','draw_deduction'=>'Draw Deduction'];
$catCounts=[];
foreach($allowedCats as $c){
  if($c==='all') continue;
  $s=$db->prepare("SELECT COUNT(*) FROM transactions WHERE user_id=? AND category=?");
  $s->execute([$userId,$c]); $catCounts[$c]=(int)$s->fetchColumn();
}

// ── Applied filter chips (everything except free-text search) ──────
$typeLabels = ['credit'=>'+ Credits','debit'=>'- Debits'];
$chips = [];
if ($type !== 'all')     $chips['type'] = $typeLabels[$type] ?? $type;
if ($category !== 'all') $chips['cat']  = $catLabels[$category] ?? ucwords(str_replace('_',' ',$category));
if ($dateFrom)            $chips['from'] = 'From ' . date('M j, Y', strtotime($dateFrom));
if ($dateTo)               $chips['to']   = 'To ' . date('M j, Y', strtotime($dateTo));

$activeAdvancedCount = count($chips);
$currentParams = ['q'=>$search,'type'=>$type,'cat'=>$category,'from'=>$dateFrom,'to'=>$dateTo];

function filterUrl(array $overrides, array $current): string {
    $p = array_merge($current, $overrides);
    $p = array_filter($p, fn($v) => $v !== '' && $v !== null && $v !== 'all');
    $qs = http_build_query($p);
    return $qs ? "?$qs" : '?';
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
    .txn-row{background:var(--bg-card);border:1px solid var(--border);border-radius:14px;padding:14px 16px;display:flex;align-items:center;gap:14px;transition:border-color .2s;}
    .txn-row:hover{border-color:rgba(249,115,22,.2);}

    /* ── Filter toolbar ─────────────────────────────────── */
    .filter-toolbar{
      background:rgba(255,255,255,.03);
      border:1px solid rgba(255,255,255,.08);
      border-radius:14px;
      padding:.6rem;
    }
    .fp-input{
      background:transparent;border:none;color:#fff;
      font-size:.85rem;width:100%;padding:.55rem .25rem;
    }
    .fp-input:focus{outline:none;}
    .fp-input::placeholder{color:#6b7280;}
    .search-wrap{
      display:flex;align-items:center;gap:.5rem;
      background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);
      border-radius:10px;padding:0 .75rem;flex:1;min-width:0;
    }
    .search-wrap svg{flex-shrink:0;color:#6b7280;width:16px;height:16px;}

    .filters-btn{
      display:flex;align-items:center;gap:.4rem;
      background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);
      color:#d1d5db;border-radius:10px;padding:.55rem .9rem;font-size:.83rem;font-weight:600;
      cursor:pointer;white-space:nowrap;transition:all .15s;
    }
    .filters-btn:hover{border-color:rgba(249,115,22,.35);color:#f97316;}
    .filters-btn.has-active{border-color:rgba(249,115,22,.4);background:rgba(249,115,22,.1);color:#f97316;}
    .filters-count{
      background:#f97316;color:#0a0f1a;font-size:.68rem;font-weight:800;
      border-radius:100px;min-width:18px;height:18px;display:flex;align-items:center;justify-content:center;padding:0 .3rem;
    }

    #filters-panel{
      max-height:0;overflow:hidden;opacity:0;
      transition:max-height .25s ease, opacity .2s ease, margin-top .25s ease;
    }
    #filters-panel.open{max-height:400px;opacity:1;margin-top:.6rem;}

    .fp-select{
      width:100%;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.1);
      border-radius:10px;color:#fff;padding:.6rem .75rem;font-size:.83rem;appearance:none;
      background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%236b7280'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'/%3E%3C/svg%3E");
      background-repeat:no-repeat;background-position:right .6rem center;background-size:16px;
    }
    .fp-select:focus{outline:none;border-color:#f97316;}
    .fp-date{
      width:100%;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.1);
      border-radius:10px;color:#fff;padding:.55rem .75rem;font-size:.83rem;
    }
    .fp-date:focus{outline:none;border-color:#f97316;}
    .fp-label{font-size:.7rem;font-weight:600;color:#9ca3af;margin-bottom:.35rem;display:block;}

    .chip{
      display:inline-flex;align-items:center;gap:.4rem;
      background:rgba(249,115,22,.12);border:1px solid rgba(249,115,22,.3);
      color:#fdba74;border-radius:100px;padding:.3rem .7rem;font-size:.75rem;font-weight:600;
      text-decoration:none;
    }
    .chip svg{width:12px;height:12px;}
    .chip:hover{background:rgba(249,115,22,.2);}
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

    <!-- ── FILTER TOOLBAR ──────────────────────────────────── -->
    <form method="GET" id="filter-form" class="mb-5">
      <div class="filter-toolbar">
        <div class="flex gap-2">
          <div class="search-wrap">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
            <input type="text" name="q" class="fp-input" placeholder="Search code, description…" value="<?= e($search) ?>">
          </div>

          <button type="button" id="filters-toggle" class="filters-btn <?= $activeAdvancedCount ? 'has-active' : '' ?>">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="16" height="16"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h18M6 8h12M9 12h6M11 16h2"/></svg>
            Filters
            <?php if ($activeAdvancedCount): ?><span class="filters-count"><?= $activeAdvancedCount ?></span><?php endif; ?>
          </button>

          <button type="submit" class="btn btn-primary px-4 text-sm flex-shrink-0">Search</button>
        </div>

        <!-- Advanced filters, collapsed by default -->
        <div id="filters-panel">
          <div class="grid grid-cols-1 md:grid-cols-4 gap-3 pt-1">
            <div>
              <label class="fp-label">From date</label>
              <input type="date" name="from" class="fp-date" value="<?= e($dateFrom) ?>">
            </div>
            <div>
              <label class="fp-label">To date</label>
              <input type="date" name="to" class="fp-date" value="<?= e($dateTo) ?>">
            </div>
            <div>
              <label class="fp-label">Type</label>
              <select name="type" class="fp-select">
                <option class="text-black"  value="all" <?= $type==='all'?'selected':'' ?>>All Types</option>
                <option  class="text-black" value="credit" <?= $type==='credit'?'selected':'' ?>>+ Credits</option>
                <option class="text-black"  value="debit" <?= $type==='debit'?'selected':'' ?>>- Debits</option>
              </select>
            </div>
            <div>
              <label class="fp-label">Category</label>
              <select name="cat" class="fp-select">
                <?php foreach ($catLabels as $v => $l):
                  $cnt = $v==='all' ? array_sum($catCounts) : ($catCounts[$v]??0);
                ?>
                <option class="text-black" value="<?= $v ?>" <?= $category===$v?'selected':'' ?>><?= e($l) ?><?= $v!=='all' ? " ($cnt)" : '' ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="flex justify-end gap-2 pt-3">
            <a href="<?= APP_URL ?>/user/transactions.php" class="btn btn-secondary btn-sm text-xs">Reset all</a>
            <button type="submit" class="btn btn-primary btn-sm text-xs">Apply filters</button>
          </div>
        </div>
      </div>

      <!-- Applied filter chips -->
      <?php if ($chips): ?>
      <div class="flex flex-wrap gap-2 mt-3">
        <?php foreach ($chips as $key => $label): ?>
        <a href="<?= filterUrl([$key => ($key==='type'||$key==='cat') ? 'all' : ''], $currentParams) ?>" class="chip">
          <?= e($label) ?>
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
        </a>
        <?php endforeach; ?>
        <a href="<?= APP_URL ?>/user/transactions.php" class="chip" style="background:rgba(255,255,255,.06);border-color:rgba(255,255,255,.15);color:#9ca3af">
          Clear all
        </a>
      </div>
      <?php endif; ?>
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
<script>
  var toggleBtn = document.getElementById('filters-toggle');
  var panel     = document.getElementById('filters-panel');
  var hasActive = <?= $activeAdvancedCount ? 'true' : 'false' ?>;

  // Auto-open the panel if an advanced filter is already applied,
  // so the user immediately sees what's filtering their results.
  if (hasActive) panel.classList.add('open');

  toggleBtn.addEventListener('click', function () {
    panel.classList.toggle('open');
  });
</script>
</body></html>
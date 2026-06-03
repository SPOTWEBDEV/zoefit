<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
$auth = requireUser(); $userId = $auth['id'];
$db = getDB();

// ── Mark all as read when page loads ──────────────────────
$db->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?")->execute([$userId]);

// ── Filter parameters ──────────────────────────────────────
$type      = trim($_GET['type']  ?? 'all');
$dateFrom  = trim($_GET['from']  ?? '');
$dateTo    = trim($_GET['to']    ?? '');
$search    = trim($_GET['q']     ?? '');
$page      = max(1,(int)($_GET['page'] ?? 1));
$per       = 20; $offset = ($page-1)*$per;

$allowedTypes = ['all','info','success','warning','draw','transfer','redemption','vendor'];
if (!in_array($type, $allowedTypes)) $type = 'all';

// ── Build WHERE ────────────────────────────────────────────
$where  = "user_id=?"; $params = [$userId];
if ($type !== 'all')  { $where .= " AND type=?"; $params[] = $type; }
if ($dateFrom)        { $where .= " AND DATE(created_at) >= ?"; $params[] = $dateFrom; }
if ($dateTo)          { $where .= " AND DATE(created_at) <= ?"; $params[] = $dateTo; }
if ($search)          { $where .= " AND (title LIKE ? OR message LIKE ?)"; $s="%$search%"; $params=array_merge($params,[$s,$s]); }

$rows = $db->prepare("SELECT * FROM notifications WHERE $where ORDER BY created_at DESC LIMIT $per OFFSET $offset");
$rows->execute($params); $rows = $rows->fetchAll();

$cntS = $db->prepare("SELECT COUNT(*) FROM notifications WHERE $where");
$cntS->execute($params); $total = $cntS->fetchColumn();
$pages = ceil($total / $per);

// ── Type counts for filter badges ─────────────────────────
$typeCounts = [];
foreach ($allowedTypes as $t) {
    if ($t === 'all') continue;
    $s = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND type=?");
    $s->execute([$userId, $t]); $typeCounts[$t] = (int)$s->fetchColumn();
}
$totalCount = array_sum($typeCounts);

// ── Unread count (for display before mark-all) ─────────────
// (already marked read above, this is informational)

$currentPage = 'notifications'; $pageTitle = 'Notifications';
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="csrf-token" content="<?= generateCsrf() ?>">
  <title><?= e($pageTitle) ?> — <?= APP_NAME ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
  <style>
    *{font-family:'Poppins',sans-serif!important}
    .filter-chip{display:inline-flex;align-items:center;gap:6px;padding:6px 14px;border-radius:20px;font-size:12px;font-weight:600;border:1px solid rgba(255,255,255,0.08);cursor:pointer;transition:all .2s;text-decoration:none;white-space:nowrap;}
    .filter-chip:hover{border-color:rgba(249,115,22,.4);color:#f97316;}
    .filter-chip.active{background:rgba(249,115,22,.15);border-color:rgba(249,115,22,.5);color:#f97316;}
    .notif-row{display:flex;align-items:flex-start;gap:14px;padding:16px;background:var(--bg-card);border:1px solid var(--border);border-radius:14px;transition:all .2s;}
    .notif-row.unread{border-color:rgba(249,115,22,.2);}
    .notif-row:hover{border-color:rgba(249,115,22,.3);}
  </style>
</head>
<body class="bg-[#0a0f1a] text-white">
<?php include __DIR__ . '/../components/user-sidebar.php'; ?>
<div class="main-content">
  <div class="topbar">
    <button onclick="toggleSidebar()" class="md:hidden text-gray-400 text-2xl mr-3">☰</button>
    <h1 class="text-xl font-bold">Notifications</h1>
    <div class="text-sm text-gray-400"><?= number_format($total) ?> result<?= $total!=1?'s':'' ?></div>
  </div>

  <div class="p-4 md:p-6 pb-24 md:pb-6 max-w-2xl mx-auto">

    <!-- ── Filter Form ──────────────────────────────────── -->
    <form method="GET" class="card p-4 mb-5 space-y-4">
      <!-- Search input -->
      <div class="flex gap-2">
        <div class="relative flex-1">
          <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
          <input type="text" name="q" class="form-control pl-9" placeholder="Search title or message…" value="<?= e($search) ?>">
        </div>
        <button class="btn btn-primary px-5 flex-shrink-0">Search</button>
        <?php if($search||$type!=='all'||$dateFrom||$dateTo): ?>
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

      <!-- Type chips -->
      <div>
        <div class="text-xs font-semibold text-gray-500 uppercase tracking-wider mb-2">Filter by Type</div>
        <div class="flex flex-wrap gap-2">
          <?php
          $typeLabels = ['all'=>'All','info'=>'ℹ️ Info','success'=>'✅ Success','warning'=>'⚠️ Warning',
                         'draw'=>'🎰 Draws','transfer'=>'↔️ Transfer','redemption'=>'🎟️ Redemption','vendor'=>'🏪 Vendor'];
          $typeColors = ['draw'=>'from-orange-500/15','transfer'=>'from-blue-500/15','redemption'=>'from-green-500/15',
                         'vendor'=>'from-purple-500/15','warning'=>'from-yellow-500/15','success'=>'from-green-500/15'];
          foreach($typeLabels as $v=>$l):
            $count = $v==='all' ? $totalCount : ($typeCounts[$v]??0);
            $qs = http_build_query(['type'=>$v,'q'=>$search,'from'=>$dateFrom,'to'=>$dateTo]);
          ?>
          <a href="?<?= $qs ?>" class="filter-chip <?= $type===$v?'active':'' ?>">
            <?= $l ?>
            <?php if($count>0): ?><span class="bg-white/10 rounded-full px-1.5 text-xs"><?= $count ?></span><?php endif; ?>
          </a>
          <?php endforeach; ?>
        </div>
      </div>
    </form>

    <!-- ── Notifications List ───────────────────────────── -->
    <?php if ($rows): ?>
    <div class="space-y-3">
      <?php
      $typeIcons=['info'=>'ℹ️','success'=>'✅','warning'=>'⚠️','draw'=>'🎰','transfer'=>'↔️','redemption'=>'🎟️','vendor'=>'🏪'];
      $typeIconBg=['info'=>'bg-blue-500/15','success'=>'bg-green-500/15','warning'=>'bg-yellow-500/15',
                   'draw'=>'bg-orange-500/15','transfer'=>'bg-cyan-500/15','redemption'=>'bg-green-500/15','vendor'=>'bg-purple-500/15'];
      foreach ($rows as $n):
        $icon = $typeIcons[$n['type']] ?? '🔔';
        $bg   = $typeIconBg[$n['type']] ?? 'bg-orange-500/15';
      ?>
      <div class="notif-row <?= $n['is_read']?'':'unread' ?> fade-in">
        <div class="w-11 h-11 rounded-xl <?= $bg ?> flex items-center justify-center text-xl flex-shrink-0"><?= $icon ?></div>
        <div class="flex-1 min-w-0">
          <div class="flex items-start justify-between gap-2">
            <div class="font-semibold text-sm"><?= e($n['title']) ?></div>
            <?php if(!$n['is_read']): ?><span class="w-2 h-2 bg-orange-500 rounded-full flex-shrink-0 mt-1.5"></span><?php endif; ?>
          </div>
          <div class="text-gray-400 text-sm mt-0.5 leading-relaxed"><?= e($n['message']) ?></div>
          <div class="flex items-center gap-3 mt-1.5">
            <span class="text-xs text-gray-600"><?= date('M j, Y · g:i A', strtotime($n['created_at'])) ?></span>
            <span class="text-xs px-2 py-0.5 rounded-full" style="background:rgba(255,255,255,0.06);color:#9ca3af"><?= ucfirst($n['type']) ?></span>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if ($pages > 1): ?>
    <div class="flex items-center justify-between mt-6">
      <div class="text-sm text-gray-400">Page <?= $page ?>/<?= $pages ?></div>
      <div class="flex gap-2">
        <?php
        $qBase = ['type'=>$type,'q'=>$search,'from'=>$dateFrom,'to'=>$dateTo];
        if($page>1): ?><a href="?<?= http_build_query(array_merge($qBase,['page'=>$page-1])) ?>" class="btn btn-sm btn-secondary">← Prev</a><?php endif; ?>
        <?php if($page<$pages): ?><a href="?<?= http_build_query(array_merge($qBase,['page'=>$page+1])) ?>" class="btn btn-sm btn-secondary">Next →</a><?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php else: ?>
    <div class="card p-12 text-center">
      <div class="text-5xl mb-4">🔔</div>
      <div class="font-semibold text-gray-300 mb-2">No notifications found</div>
      <?php if($search||$type!=='all'||$dateFrom||$dateTo): ?>
      <div class="text-gray-500 text-sm mb-4">No results match your filters.</div>
      <a href="?" class="btn btn-secondary btn-sm">Clear Filters</a>
      <?php else: ?>
      <div class="text-gray-500 text-sm">You have no notifications yet. Activity like code redemptions, draw entries and wins will appear here.</div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Quick links -->
    <div class="mt-6 grid grid-cols-2 gap-3">
      <a href="<?= APP_URL ?>/user/redeem.php" class="card p-4 flex items-center gap-3 hover:border-orange-500/30 transition-colors">
        <div class="w-9 h-9 bg-green-500/15 rounded-xl flex items-center justify-center text-xl">🎟️</div>
        <div><div class="font-semibold text-sm">Redeem Code</div><div class="text-xs text-gray-500">Add new code</div></div>
      </a>
      <a href="<?= APP_URL ?>/user/draws.php" class="card p-4 flex items-center gap-3 hover:border-orange-500/30 transition-colors">
        <div class="w-9 h-9 bg-orange-500/15 rounded-xl flex items-center justify-center text-xl">🎯</div>
        <div><div class="font-semibold text-sm">Live Draws</div><div class="text-xs text-gray-500">Enter a draw</div></div>
      </a>
    </div>

  </div>
</div>
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
</body></html>

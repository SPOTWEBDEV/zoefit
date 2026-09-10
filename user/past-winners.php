<?php
// user/past-winners.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
startAppSession();
$db = getDB();
$loggedInUserId = $_SESSION['user_id'] ?? null;

// ── Filters ────────────────────────────────────────────────
$page     = max(1, (int)($_GET['page']      ?? 1));
$per      = 12;
$offset   = $per * ($page - 1);
$q        = trim($_GET['q']         ?? '');
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo   = trim($_GET['date_to']   ?? '');
$month    = trim($_GET['month']     ?? '');
$category = trim($_GET['category']  ?? '');

// ── WHERE clause ──────────────────────────────────────────
$where  = "d.status = 'completed'";
$params = [];

if ($q) {
    $where   .= " AND (d.title LIKE ? OR d.category LIKE ?)";
    $s        = "%$q%"; $params[] = $s; $params[] = $s;
}
if ($category) { $where .= " AND d.category = ?"; $params[] = $category; }
if ($month) {
    $where .= " AND DATE_FORMAT(d.end_date,'%Y-%m') = ?"; $params[] = $month;
} else {
    if ($dateFrom) { $where .= " AND DATE(d.end_date) >= ?"; $params[] = $dateFrom; }
    if ($dateTo)   { $where .= " AND DATE(d.end_date) <= ?"; $params[] = $dateTo; }
}

// ── Fetch ─────────────────────────────────────────────────
$sql = "SELECT d.*,
               dw.winning_code, dw.matched_digits,
               dw.announced_at, dw.tiebreaker_used,
               dw.user_id      AS winner_uid,
               u.full_name     AS winner_name,
               (SELECT COUNT(*) FROM draw_entries WHERE draw_id=d.id) AS total_entries,
               (SELECT COUNT(DISTINCT user_id) FROM draw_entries WHERE draw_id=d.id) AS total_participants
        FROM draws d
        LEFT JOIN draw_winners dw ON dw.draw_id = d.id
        LEFT JOIN users u         ON u.id        = dw.user_id
        WHERE $where
        ORDER BY dw.announced_at DESC
        LIMIT $per OFFSET $offset";

$draws = $db->prepare($sql); $draws->execute($params); $draws = $draws->fetchAll();

$cnt = $db->prepare("SELECT COUNT(*) FROM draws d LEFT JOIN draw_winners dw ON dw.draw_id=d.id LEFT JOIN users u ON u.id=dw.user_id WHERE $where");
$cnt->execute($params); $total = (int)$cnt->fetchColumn(); $pages = (int)ceil($total/$per);

// Stats
$totalWinners   = (int)$db->query("SELECT COUNT(*) FROM draw_winners")->fetchColumn();
$totalCompleted = (int)$db->query("SELECT COUNT(*) FROM draws WHERE status='completed'")->fetchColumn();

// Dropdown data
$cats = $db->query("SELECT DISTINCT category FROM draws WHERE category IS NOT NULL AND category != '' ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);
$months = $db->query("SELECT DISTINCT DATE_FORMAT(end_date,'%Y-%m') AS ym, DATE_FORMAT(end_date,'%M %Y') AS label FROM draws WHERE status='completed' ORDER BY ym DESC")->fetchAll();

function maskWinnerName(string $n): string {
    $p = explode(' ', trim($n));
    return implode(' ', array_map(fn($w) => mb_strlen($w)<=1?$w:mb_substr($w,0,1).str_repeat('*',min(mb_strlen($w)-1,4)), $p));
}

// ── Build "applied filter" chips (everything except free-text search,
//    which already shows in the search box itself) ─────────────────
$monthLabel = $month;
foreach ($months as $m) { if ($m['ym'] === $month) { $monthLabel = $m['label']; break; } }

$chips = [];
if ($dateFrom) $chips['date_from'] = 'From ' . date('M j, Y', strtotime($dateFrom));
if ($dateTo)   $chips['date_to']   = 'To ' . date('M j, Y', strtotime($dateTo));
if ($month)    $chips['month']     = $monthLabel;
if ($category) $chips['category']  = $category;

$activeAdvancedCount = count($chips);
$currentParams = ['q'=>$q, 'date_from'=>$dateFrom, 'date_to'=>$dateTo, 'month'=>$month, 'category'=>$category];

function filterUrl(array $overrides, array $current): string {
    $p = array_merge($current, $overrides);
    $p = array_filter($p, fn($v) => $v !== '' && $v !== null);
    $qs = http_build_query($p);
    return $qs ? "?$qs" : '?';
}

$currentPage = 'past-winners';
$pageTitle   = 'Past Winners';
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= e($pageTitle) ?> — <?= APP_NAME ?></title>
  <script src="<?= APP_URL ?>/assets/js/tailwind.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
  <style>
    *{font-family:'Poppins',sans-serif!important}
    .draw-card{
      background:rgba(255,255,255,.03);
      border:1px solid rgba(255,255,255,.07);
      border-radius:18px;
      transition:all .25s;
      text-decoration:none;
      display:block; color:inherit;
    }
    .draw-card:hover{border-color:rgba(249,115,22,.35);transform:translateY(-3px);box-shadow:0 14px 36px rgba(0,0,0,.4);}
    .draw-card.my-win{border-color:rgba(234,179,8,.3);background:rgba(234,179,8,.04);}
    .draw-card.my-win:hover{border-color:rgba(234,179,8,.6);box-shadow:0 14px 36px rgba(234,179,8,.12);}

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
    <button id="sidebar-hamburger-btn" onclick="toggleSidebar()" class="md:hidden text-gray-400 text-2xl mr-3">☰</button>
    <h1 class="text-xl font-bold">🏆 Past Winners</h1>
    <div class="text-sm text-gray-400"><?= number_format($total) ?> draws</div>
  </div>

  <div class="p-4 md:p-6 pb-24 md:pb-6">

    <!-- Stats -->
    <div class="grid grid-cols-2 gap-3 mb-5">
      <div class="card p-4 text-center">
        <div class="text-2xl font-black text-yellow-400"><?= number_format($totalWinners) ?></div>
        <div class="text-xs text-gray-500 mt-1">Total Winners</div>
      </div>
      <div class="card p-4 text-center">
        <div class="text-2xl font-black text-orange-400"><?= number_format($totalCompleted) ?></div>
        <div class="text-xs text-gray-500 mt-1">Draws Completed</div>
      </div>
    </div>

    <!-- ── FILTER TOOLBAR ──────────────────────────────────── -->
    <form method="GET" id="filter-form" class="mb-5">
      <div class="filter-toolbar">
        <div class="flex gap-2">
          <div class="search-wrap">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
            <input type="text" name="q" class="fp-input" placeholder="Search draw name or category…" value="<?= e($q) ?>">
          </div>

          <button type="button" id="filters-toggle" class="filters-btn <?= $activeAdvancedCount ? 'has-active' : '' ?>">
            <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="16" height="16"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h18M6 8h12M9 12h6M11 16h2"/></svg>
            Filters
            <?php if ($activeAdvancedCount): ?><span class="filters-count"><?= $activeAdvancedCount ?></span><?php endif; ?>
          </button>

          <button type="submit" class="btn btn-primary px-4 text-sm">Search</button>
        </div>

        <!-- Advanced filters, collapsed by default -->
        <div id="filters-panel">
          <div class="grid grid-cols-1 md:grid-cols-4 gap-3 pt-1">
            <div>
              <label class="fp-label">From date</label>
              <input type="date" name="date_from" class="fp-date" value="<?= e($dateFrom) ?>" onchange="document.querySelector('[name=month]').value=''">
            </div>
            <div>
              <label class="fp-label">To date</label>
              <input type="date" name="date_to" class="fp-date" value="<?= e($dateTo) ?>" onchange="document.querySelector('[name=month]').value=''">
            </div>
            <div>
              <label class="fp-label">Month</label>
              <select name="month" class="fp-select " onchange="if(this.value){document.querySelector('[name=date_from]').value='';document.querySelector('[name=date_to]').value='';}">
                <option value="">Any month</option>
                <?php foreach ($months as $m): ?>
                <option class="text-black" value="<?= e($m['ym']) ?>" <?= $month===$m['ym']?'selected':'' ?>><?= e($m['label']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <label class="fp-label">Category</label>
              <select name="category" class="fp-select">
                <option value="">Any category</option>
                <?php foreach ($cats as $c): ?>
                <option class="text-black" value="<?= e($c) ?>" <?= $category===$c?'selected':'' ?>><?= e($c) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>
          <div class="flex justify-end gap-2 pt-3">
            <a href="<?= APP_URL ?>/user/past-winners.php" class="btn btn-secondary btn-sm text-xs">Reset all</a>
            <button type="submit" class="btn btn-primary btn-sm text-xs">Apply filters</button>
          </div>
        </div>
      </div>

      <!-- Applied filter chips -->
      <?php if ($chips): ?>
      <div class="flex flex-wrap gap-2 mt-3">
        <?php foreach ($chips as $key => $label): ?>
        <a href="<?= filterUrl([$key => ''], $currentParams) ?>" class="chip">
          <?= e($label) ?>
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
        </a>
        <?php endforeach; ?>
        <a href="<?= APP_URL ?>/user/past-winners.php" class="chip" style="background:rgba(255,255,255,.06);border-color:rgba(255,255,255,.15);color:#9ca3af">
          Clear all
        </a>
      </div>
      <?php endif; ?>
    </form>

    <!-- ── DRAW CARDS ──────────────────────────────────────── -->
    <?php if ($draws): ?>
    <div class="space-y-3">
      <?php foreach ($draws as $d):
        $iWon = $loggedInUserId && (int)($d['winner_uid']??0) === (int)$loggedInUserId;
        $iParticipated = false;
        if ($loggedInUserId) {
            $chk = $db->prepare("SELECT id FROM draw_entries WHERE draw_id=? AND user_id=? LIMIT 1");
            $chk->execute([$d['id'],$loggedInUserId]); $iParticipated = (bool)$chk->fetch();
        }
      ?>
      <!-- WHOLE CARD is a link to draw-detail.php -->
      <a href="<?= APP_URL ?>/user/draw-detail.php?id=<?= $d['id'] ?>"
         class="draw-card <?= $iWon?'my-win':'' ?> p-4 flex items-center gap-4">

        <!-- Thumb -->
        <div class="w-14 h-14 rounded-xl flex-shrink-0 flex items-center justify-center text-2xl overflow-hidden"
             style="background:linear-gradient(135deg,#1a2235,#0d1118)">
          <?php if ($d['banner_image'] && file_exists(UPLOAD_PATH.$d['banner_image'])): ?>
          <img src="<?= APP_URL ?>/uploads/<?= e($d['banner_image']) ?>" class="w-full h-full object-cover" alt="">
          <?php else: ?>🏆<?php endif; ?>
        </div>

        <div class="flex-1 min-w-0">
          <div class="flex items-center gap-2 flex-wrap mb-0.5">
            <span class="font-bold text-sm"><?= e($d['title']) ?></span>
            <?php if ($iWon): ?>
            <span class="badge text-xs font-bold flex-shrink-0"
                  style="background:rgba(234,179,8,.2);border:1px solid rgba(234,179,8,.4);color:#fbbf24">
              🏆 You Won!
            </span>
            <?php elseif ($iParticipated): ?>
            <span class="badge badge-muted text-xs flex-shrink-0">You participated</span>
            <?php endif; ?>
            <?php if ($d['category']): ?>
            <span class="badge badge-info text-xs flex-shrink-0"><?= e($d['category']) ?></span>
            <?php endif; ?>
          </div>
          <?php if ($d['winner_name']): ?>
          <div class="text-xs text-yellow-400">
            🏆 <?= e(maskWinnerName($d['winner_name'])) ?>
            <span class="text-gray-500 ml-1"><?= $d['matched_digits'] ?>/15 matched</span>
          </div>
          <?php else: ?>
          <div class="text-xs text-gray-500">No winner recorded</div>
          <?php endif; ?>
          <div class="flex flex-wrap gap-x-4 gap-y-0.5 text-xs text-gray-500 mt-0.5">
            <span>📅 <?= date('M j, Y', strtotime($d['end_date'])) ?></span>
            <span>👥 <?= number_format((int)$d['total_participants']) ?> participants</span>
            <span>📝 <?= number_format((int)$d['total_entries']) ?> entries</span>
          </div>
        </div>

        <!-- Arrow CTA -->
        <div class="flex-shrink-0 flex flex-col items-end gap-1">
          <span class="btn btn-secondary btn-sm text-xs pointer-events-none whitespace-nowrap">
            View Result →
          </span>
        </div>
      </a>
      <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if ($pages > 1): ?>
    <div class="flex items-center justify-between mt-5">
      <div class="text-sm text-gray-400">Page <?= $page ?>/<?= $pages ?> · <?= number_format($total) ?> draws</div>
      <div class="flex gap-2">
        <?php if ($page > 1): ?>
        <a href="?<?= http_build_query(array_merge(['q'=>$q,'date_from'=>$dateFrom,'date_to'=>$dateTo,'month'=>$month,'category'=>$category],['page'=>$page-1])) ?>"
           class="btn btn-secondary btn-sm">← Prev</a>
        <?php endif; ?>
        <?php if ($page < $pages): ?>
        <a href="?<?= http_build_query(array_merge(['q'=>$q,'date_from'=>$dateFrom,'date_to'=>$dateTo,'month'=>$month,'category'=>$category],['page'=>$page+1])) ?>"
           class="btn btn-secondary btn-sm">Next →</a>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php else: ?>
    <div class="card p-16 text-center">
      <div class="text-5xl mb-4">🏆</div>
      <?php if ($q||$dateFrom||$dateTo||$month||$category): ?>
      <div class="text-gray-400 text-lg font-semibold mb-2">No draws match your filters</div>
      <a href="<?= APP_URL ?>/user/past-winners.php" class="btn btn-secondary mt-2">Clear Filters</a>
      <?php else: ?>
      <div class="text-gray-400 text-lg font-semibold mb-2">No completed draws yet</div>
      <div class="text-gray-600 text-sm">Check back after the first draw ends!</div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

  </div>
</div>

<script src="<?= APP_URL ?>/assets/js/app.js"></script>
<script>
  var toggleBtn   = document.getElementById('filters-toggle');
  var panel       = document.getElementById('filters-panel');
  var hasActive   = <?= $activeAdvancedCount ? 'true' : 'false' ?>;

  // Auto-open the panel if an advanced filter is already applied,
  // so the user immediately sees what's filtering their results.
  if (hasActive) panel.classList.add('open');

  toggleBtn.addEventListener('click', function () {
    panel.classList.toggle('open');
  });
</script>
</body>
</html>
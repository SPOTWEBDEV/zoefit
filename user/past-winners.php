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
    .fp-input{background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.1);border-radius:10px;color:#fff;padding:.5rem .85rem;font-size:.82rem;transition:border-color .2s;}
    .fp-input:focus{border-color:#f97316;outline:none;}
    .fp-input::placeholder{color:#6b7280;}
    input[type="date"].fp-input::-webkit-calendar-picker-indicator{filter:invert(.5);cursor:pointer;}
    .filter-pill{display:inline-flex;align-items:center;gap:.35rem;background:rgba(255,255,255,.05);border:1px solid rgba(255,255,255,.1);border-radius:100px;padding:.3rem .85rem;font-size:.73rem;font-weight:600;color:#9ca3af;text-decoration:none;transition:all .2s;white-space:nowrap;cursor:pointer;}
    .filter-pill:hover{background:rgba(249,115,22,.1);border-color:rgba(249,115,22,.3);color:#f97316;}
    .filter-pill.active{background:rgba(249,115,22,.15);border-color:rgba(249,115,22,.4);color:#f97316;}
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

    <!-- ── FILTERS ─────────────────────────────────────────── -->
    <form method="GET" id="filter-form" class="mb-5">

      <!-- Search -->
      <div class="flex gap-2 mb-3">
        <input type="text" name="q" class="fp-input flex-1"
               placeholder="Search draw name or category…" value="<?= e($q) ?>">
        <input type="hidden" name="month"    id="month-input"    value="<?= e($month) ?>">
        <input type="hidden" name="category" id="cat-input"      value="<?= e($category) ?>">
        <button type="submit" class="btn btn-primary px-5 text-sm">Search</button>
        <?php if ($q||$dateFrom||$dateTo||$month||$category): ?>
        <a href="<?= APP_URL ?>/user/past-winners.php" class="btn btn-secondary px-4 text-sm">Clear</a>
        <?php endif; ?>
      </div>

      <!-- Date range -->
      <div class="flex flex-wrap gap-2 items-center mb-3">
        <span class="text-xs text-gray-500 font-semibold uppercase tracking-wider">Date:</span>
        <input type="date" name="date_from" class="fp-input" value="<?= e($dateFrom) ?>"
               onchange="document.getElementById('month-input').value='';this.form.submit()">
        <span class="text-gray-600 text-xs">to</span>
        <input type="date" name="date_to"   class="fp-input" value="<?= e($dateTo) ?>"
               onchange="document.getElementById('month-input').value='';this.form.submit()">
      </div>

      <!-- Month quick-pills -->
      <?php if ($months): ?>
      <div class="flex flex-wrap gap-1.5 mb-3">
        <span class="text-xs text-gray-500 font-semibold uppercase tracking-wider self-center">Month:</span>
        <span onclick="setMonth('')"
              class="filter-pill <?= !$month?'active':'' ?>">All</span>
        <?php foreach ($months as $m): ?>
        <span onclick="setMonth('<?= e($m['ym']) ?>')"
              class="filter-pill <?= $month===$m['ym']?'active':'' ?>">
          <?= e($m['label']) ?>
        </span>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <!-- Category pills -->
      <?php if ($cats): ?>
      <div class="flex flex-wrap gap-1.5">
        <span class="text-xs text-gray-500 font-semibold uppercase tracking-wider self-center">Category:</span>
        <span onclick="setCat('')"
              class="filter-pill <?= !$category?'active':'' ?>">All</span>
        <?php foreach ($cats as $c): ?>
        <span onclick="setCat('<?= e(addslashes($c)) ?>')"
              class="filter-pill <?= $category===$c?'active':'' ?>"><?= e($c) ?></span>
        <?php endforeach; ?>
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
function setMonth(val) {
  document.getElementById('month-input').value = val;
  document.querySelector('[name="date_from"]').value = '';
  document.querySelector('[name="date_to"]').value   = '';
  document.getElementById('filter-form').submit();
}
function setCat(val) {
  document.getElementById('cat-input').value = val;
  document.getElementById('filter-form').submit();
}
</script>
</body>
</html>
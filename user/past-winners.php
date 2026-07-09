<?php
// user/winners.php
// Public winners page — any visitor can see this (no login required).
// Shows all completed draws, winner + top 3 ranking.
// Codes are NEVER shown to the public — only position, name, matched digits.
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
startAppSession();
$db = getDB();

// ── Filters ────────────────────────────────────────────────
$page    = max(1, (int)($_GET['page'] ?? 1));
$per     = 10;
$offset  = ($page - 1) * $per;
$search  = trim($_GET['q'] ?? '');
$drawId  = (int)($_GET['draw'] ?? 0); // single draw view

// ── Single draw view ───────────────────────────────────────
if ($drawId) {
    $draw = $db->prepare(
        "SELECT d.*, dw.winning_code, dw.matched_digits, dw.tiebreaker_used,
                dw.announced_at, u.full_name AS winner_name, u.phone AS winner_phone
         FROM draws d
         LEFT JOIN draw_winners dw ON dw.draw_id = d.id
         LEFT JOIN users u ON u.id = dw.user_id
         WHERE d.id = ? AND d.status = 'completed'"
    );
    $draw->execute([$drawId]);
    $draw = $draw->fetch();

    if (!$draw) redirect(APP_URL . '/user/winners.php');

    // Top 3 rankings for this draw
    $rankings = $db->prepare(
        "SELECT dr.rank_position, dr.matched_digits, dr.entries_count,
                dr.tiebreaker, u.full_name, u.phone
         FROM draw_rankings dr
         JOIN users u ON u.id = dr.user_id
         WHERE dr.draw_id = ?
         ORDER BY dr.rank_position ASC"
    );
    $rankings->execute([$drawId]);
    $rankings = $rankings->fetchAll();

    // All participants for this draw (aggregated, no codes)
    $participants = $db->prepare(
        "SELECT u.full_name, u.phone, COUNT(de.id) AS entry_count
         FROM draw_entries de
         JOIN users u ON u.id = de.user_id
         WHERE de.draw_id = ?
         GROUP BY de.user_id
         ORDER BY entry_count DESC
         LIMIT 50"
    );
    $participants->execute([$drawId]);
    $participants = $participants->fetchAll();

    $pageMode = 'single';
} else {
    $pageMode = 'list';
}

// ── List view: all completed draws ─────────────────────────
if ($pageMode === 'list') {
    $where  = "d.status = 'completed'";
    $params = [];
    if ($search) {
        $where   .= " AND (d.title LIKE ? OR d.category LIKE ?)";
        $s        = "%$search%";
        $params   = [$s, $s];
    }

    $draws = $db->prepare(
        "SELECT d.*,
                dw.winning_code, dw.matched_digits, dw.announced_at, dw.tiebreaker_used,
                u.full_name AS winner_name, u.phone AS winner_phone,
                (SELECT COUNT(*) FROM draw_entries WHERE draw_id=d.id) AS total_entries,
                (SELECT COUNT(DISTINCT user_id) FROM draw_entries WHERE draw_id=d.id) AS total_participants
         FROM draws d
         LEFT JOIN draw_winners dw ON dw.draw_id = d.id
         LEFT JOIN users u ON u.id = dw.user_id
         WHERE $where
         ORDER BY dw.announced_at DESC
         LIMIT $per OFFSET $offset"
    );
    $draws->execute($params);
    $draws = $draws->fetchAll();

    $cntS = $db->prepare("SELECT COUNT(*) FROM draws d WHERE $where");
    $cntS->execute($params);
    $total = (int)$cntS->fetchColumn();
    $pages = (int)ceil($total / $per);
}

// ── Stats for header ───────────────────────────────────────
$totalWinners  = (int)$db->query("SELECT COUNT(*) FROM draw_winners")->fetchColumn();
$totalDrawsDone = (int)$db->query("SELECT COUNT(*) FROM draws WHERE status='completed'")->fetchColumn();

$currentPage = 'past-winners';
$pageTitle   = $drawId ? 'Draw Result' : 'Winners';
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= e($pageTitle) ?> — <?= APP_NAME ?></title>
  <script src="<?= APP_URL ?>/assets/js/tailwind.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
  <style>
    * { font-family: 'Poppins', sans-serif !important; }

    .winner-glow {
      background: linear-gradient(135deg, rgba(234,179,8,.12), rgba(0,0,0,0));
      border: 1px solid rgba(234,179,8,.3);
      border-radius: 20px;
    }

    .podium-1 { background: linear-gradient(135deg,rgba(234,179,8,.15),rgba(0,0,0,0)); border:1px solid rgba(234,179,8,.3); }
    .podium-2 { background: linear-gradient(135deg,rgba(156,163,175,.1),rgba(0,0,0,0)); border:1px solid rgba(156,163,175,.2); }
    .podium-3 { background: linear-gradient(135deg,rgba(180,83,9,.1),rgba(0,0,0,0));   border:1px solid rgba(180,83,9,.2); }

    .digit-slot {
      display: inline-flex; align-items: center; justify-content: center;
      width: 28px; height: 34px; border-radius: 6px;
      font-size: 15px; font-weight: 900;
      font-family: 'Courier New', monospace;
      border: 1.5px solid rgba(255,255,255,.08);
      background: rgba(255,255,255,.04);
    }
    .digit-slot.match    { background:rgba(34,197,94,.18); border-color:#22c55e; color:#22c55e; }
    .digit-slot.no-match { background:rgba(239,68,68,.08); border-color:rgba(239,68,68,.2); color:#6b7280; }

    .draw-card {
      background: var(--bg-card, rgba(255,255,255,.03));
      border: 1px solid rgba(255,255,255,.07);
      border-radius: 18px;
      transition: all .25s;
      text-decoration: none;
      display: block;
      color: inherit;
    }
    .draw-card:hover {
      border-color: rgba(234,179,8,.3);
      transform: translateY(-3px);
      box-shadow: 0 12px 32px rgba(0,0,0,.4);
    }

    .rank-badge {
      width: 32px; height: 32px; border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      font-size: .75rem; font-weight: 900; flex-shrink: 0;
    }
  </style>
</head>
<body class="bg-[#0a0f1a] text-white">
<?php
// Include sidebar if logged in, otherwise show standalone page
if (!empty($_SESSION['user_id'])) {
    include __DIR__ . '/../components/user-sidebar.php';
}
?>

<div class="<?= !empty($_SESSION['user_id']) ? 'main-content' : 'min-h-screen' ?>">

  <?php if (!empty($_SESSION['user_id'])): ?>
  <!-- Topbar for logged-in users -->
  <div class="topbar">
    <button onclick="toggleSidebar()" class="md:hidden text-gray-400 text-2xl mr-3">☰</button>
    <?php if ($drawId): ?>
    <div>
      <a href="<?= APP_URL ?>/user/winners.php" class="text-orange-400 text-sm hover:underline">← All Winners</a>
      <h1 class="text-lg font-bold mt-0.5"><?= e($draw['title']) ?></h1>
    </div>
    <?php else: ?>
    <h1 class="text-xl font-bold">🏆 Winners</h1>
    <?php endif; ?>
  </div>
  <?php else: ?>
  <!-- Minimal nav for public visitors -->
  <nav class="border-b border-white/5 px-4 sm:px-6 h-16 flex items-center justify-between"
       style="background:rgba(10,15,26,.97);backdrop-filter:blur(14px);position:sticky;top:0;z-index:50">
    <a href="<?= APP_URL ?>" class="flex items-center gap-2">
      <div class="w-8 h-8 bg-orange-500 rounded-lg flex items-center justify-center font-black text-white text-sm">Z</div>
      <span class="font-bold">Zoe<span class="text-orange-500">Feeds</span></span>
    </a>
    <div class="flex items-center gap-2">
      <?php if ($drawId): ?>
      <a href="<?= APP_URL ?>/user/winners.php" class="text-gray-400 hover:text-white text-sm">← All Winners</a>
      <?php endif; ?>
      <a href="<?= APP_URL ?>/user/login.php"    class="btn btn-secondary btn-sm">Log In</a>
      <a href="<?= APP_URL ?>/user/register.php" class="btn btn-primary btn-sm">Join Free</a>
    </div>
  </nav>
  <?php endif; ?>

  <div class="p-4 md:p-6 pb-24 md:pb-6 max-w-4xl mx-auto">

    <!-- ════════════════════════════════════════════════════
         SINGLE DRAW VIEW
    ════════════════════════════════════════════════════════ -->
    <?php if ($pageMode === 'single'): ?>

    <!-- Draw info header -->
    <div class="winner-glow p-6 mb-6">
      <div class="flex items-start gap-4">
        <div class="w-16 h-16 rounded-2xl flex items-center justify-center text-3xl flex-shrink-0 overflow-hidden"
             style="background:linear-gradient(135deg,#1a2235,#0d1118)">
          <?php if ($draw['banner_image'] && file_exists(UPLOAD_PATH.$draw['banner_image'])): ?>
          <img src="<?= APP_URL ?>/uploads/<?= e($draw['banner_image']) ?>" class="w-full h-full object-cover" alt="">
          <?php else: ?>🏆<?php endif; ?>
        </div>
        <div class="flex-1 min-w-0">
          <div class="flex items-center gap-2 flex-wrap mb-1">
            <h1 class="font-black text-xl text-yellow-400"><?= e($draw['title']) ?></h1>
            <span class="badge badge-muted text-xs">Completed</span>
            <?php if ($draw['category']): ?><span class="badge badge-info text-xs"><?= e($draw['category']) ?></span><?php endif; ?>
          </div>
          <div class="text-xs text-gray-500 flex flex-wrap gap-4 mt-1">
            <span>📅 Ended <?= date('M j, Y g:i A', strtotime($draw['end_date'])) ?></span>
            <span>📝 <?= number_format(count($participants)) ?>+ participants</span>
            <?php if ($draw['announced_at']): ?>
            <span>🤖 Auto-finalized <?= date('M j, Y g:i A', strtotime($draw['announced_at'])) ?></span>
            <?php endif; ?>
          </div>
          <?php if ($draw['prize_details']): ?>
          <div class="mt-2 text-sm text-orange-400 font-semibold">🏆 <?= e($draw['prize_details']) ?></div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Winning code display (no user code — just the winning number) -->
    <?php if ($draw['winning_code']): ?>
    <div class="card p-5 mb-5 text-center">
      <div class="text-sm text-gray-400 mb-3">🎲 Generated Winning Code</div>
      <div class="flex flex-wrap gap-1.5 justify-center mb-2">
        <?php for ($i = 0; $i < 15; $i++): ?>
        <div class="digit-slot" style="border-color:rgba(249,115,22,.3);background:rgba(249,115,22,.08);color:#f97316">
          <?= $draw['winning_code'][$i] ?>
        </div>
        <?php endfor; ?>
      </div>
      <div class="text-xs text-gray-600 mt-2">Generated automatically by the ZoeFeeds draw system</div>
    </div>
    <?php endif; ?>

    <!-- Podium — top 3 -->
    <?php if ($rankings): ?>
    <div class="mb-6">
      <h2 class="font-bold text-lg mb-4 flex items-center gap-2">🏅 Top 3 Finishers</h2>
      <div class="grid sm:grid-cols-3 gap-3">
        <?php
        $podiumMeta = [
          1 => ['🥇', 'podium-1', 'text-yellow-400', 'Gold'],
          2 => ['🥈', 'podium-2', 'text-gray-300',   'Silver'],
          3 => ['🥉', 'podium-3', 'text-orange-300',  'Bronze'],
        ];
        // Sort to show #1 in middle on desktop
        $orderedRankings = [];
        foreach ([2, 1, 3] as $pos) {
            foreach ($rankings as $r) {
                if ($r['rank_position'] === $pos) { $orderedRankings[] = $r; break; }
            }
        }
        foreach ($orderedRankings as $r):
            $pm  = $podiumMeta[$r['rank_position']] ?? $podiumMeta[3];
            $isWinner = $r['rank_position'] === 1;
        ?>
        <div class="<?= $pm[1] ?> rounded-2xl p-5 text-center <?= $isWinner ? 'sm:order-2' : ($r['rank_position']===2?'sm:order-1':'sm:order-3') ?>">
          <?php if ($isWinner): ?>
          <div class="text-xs text-yellow-400 font-bold uppercase tracking-widest mb-2">🏆 Winner</div>
          <?php endif; ?>
          <div class="text-4xl mb-3"><?= $pm[0] ?></div>
          <div class="font-black <?= $pm[2] ?> text-base mb-0.5">
            <?= e(_maskName($r['full_name'])) ?>
          </div>
          <div class="text-xs text-gray-500 mb-3"><?= e(_maskPhone($r['phone'])) ?></div>
          <!-- NO code shown on user-facing page -->
          <div class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-sm font-bold"
               style="background:rgba(255,255,255,.05)">
            <span class="<?= $r['matched_digits']>=10?'text-green-400':($r['matched_digits']>=5?'text-yellow-400':'text-gray-400') ?>">
              <?= $r['matched_digits'] ?>/15
            </span>
            <span class="text-gray-500 text-xs">matched</span>
          </div>
          <div class="text-xs text-gray-600 mt-2"><?= $r['entries_count'] ?> entries</div>
          <?php if ($r['tiebreaker']): ?>
          <div class="text-xs text-orange-400 mt-1">via <?= e(str_replace('_',' ',$r['tiebreaker'])) ?></div>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Participant list (top 50, no codes) -->
    <?php if ($participants): ?>
    <div class="card">
      <div class="px-5 pt-5 pb-3 border-b border-white/5 flex items-center justify-between">
        <h3 class="font-bold text-sm">📋 Participants</h3>
        <span class="text-xs text-gray-500"><?= number_format(count($participants)) ?> shown</span>
      </div>
      <div class="divide-y divide-white/5">
        <?php foreach ($participants as $i => $p): ?>
        <div class="flex items-center gap-3 px-5 py-3">
          <div class="rank-badge flex-shrink-0 <?= $i===0?'bg-yellow-500/20 text-yellow-400':($i===1?'bg-gray-400/15 text-gray-300':($i===2?'bg-orange-700/15 text-orange-400':'bg-white/5 text-gray-600')) ?>">
            <?= $i < 3 ? ['🥇','🥈','🥉'][$i] : ($i+1) ?>
          </div>
          <div class="flex-1 min-w-0">
            <div class="text-sm font-medium"><?= e(_maskName($p['full_name'])) ?></div>
          </div>
          <div class="text-xs text-gray-500 flex-shrink-0">
            <?= $p['entry_count'] ?> <?= $p['entry_count']===1?'entry':'entries' ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>


    <!-- ════════════════════════════════════════════════════
         LIST VIEW — all completed draws
    ════════════════════════════════════════════════════════ -->
    <?php else: ?>

    <!-- Header stats -->
    <div class="grid grid-cols-2 gap-3 mb-6">
      <div class="card p-5 text-center">
        <div class="text-3xl font-black text-yellow-400"><?= number_format($totalWinners) ?></div>
        <div class="text-xs text-gray-500 mt-1">Total Winners</div>
      </div>
      <div class="card p-5 text-center">
        <div class="text-3xl font-black text-orange-400"><?= number_format($totalDrawsDone) ?></div>
        <div class="text-xs text-gray-500 mt-1">Draws Completed</div>
      </div>
    </div>

    <!-- Search -->
    <form method="GET" class="flex gap-3 mb-6">
      <input type="text" name="q" class="form-control flex-1" placeholder="Search draws…" value="<?= e($search) ?>">
      <button class="btn btn-primary px-6">Search</button>
      <?php if ($search): ?><a href="<?= APP_URL ?>/user/winners.php" class="btn btn-secondary px-4">Clear</a><?php endif; ?>
    </form>

    <?php if ($draws): ?>
    <div class="space-y-4">
      <?php foreach ($draws as $d): ?>
      <a href="<?= APP_URL ?>/user/winners.php?draw=<?= $d['id'] ?>" class="draw-card p-5 block">
        <div class="flex items-start gap-4">
          <!-- Thumb -->
          <div class="w-14 h-14 rounded-xl flex-shrink-0 flex items-center justify-center text-2xl overflow-hidden"
               style="background:linear-gradient(135deg,#1a2235,#0d1118)">
            <?php if ($d['banner_image'] && file_exists(UPLOAD_PATH.$d['banner_image'])): ?>
            <img src="<?= APP_URL ?>/uploads/<?= e($d['banner_image']) ?>" class="w-full h-full object-cover" alt="">
            <?php else: ?>🏆<?php endif; ?>
          </div>

          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 flex-wrap mb-1">
              <h3 class="font-bold text-base"><?= e($d['title']) ?></h3>
              <?php if ($d['category']): ?>
              <span class="badge badge-info text-xs"><?= e($d['category']) ?></span>
              <?php endif; ?>
            </div>

            <?php if ($d['winner_name']): ?>
            <div class="flex items-center gap-2 mb-2">
              <span class="text-yellow-400 font-bold text-sm">🏆 <?= e(_maskName($d['winner_name'])) ?></span>
              <span class="text-xs text-gray-500">
                <?= $d['matched_digits'] ?>/15 matched
                <?php if ($d['tiebreaker_used']): ?>
                · via <?= e(str_replace('_',' ',$d['tiebreaker_used'])) ?>
                <?php endif; ?>
              </span>
            </div>
            <?php else: ?>
            <div class="text-sm text-gray-500 mb-2">No winner recorded</div>
            <?php endif; ?>

            <div class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-gray-500">
              <span>📅 Ended <?= date('M j, Y', strtotime($d['end_date'])) ?></span>
              <span>👥 <?= number_format($d['total_participants']) ?> participants</span>
              <span>📝 <?= number_format($d['total_entries']) ?> entries</span>
              <?php if ($d['prize_details']): ?>
              <span class="text-orange-400">🎁 <?= e(mb_substr($d['prize_details'],0,40)) ?><?= mb_strlen($d['prize_details'])>40?'…':'' ?></span>
              <?php endif; ?>
            </div>
          </div>

          <div class="flex-shrink-0 text-gray-600 text-lg">→</div>
        </div>
      </a>
      <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if ($pages > 1): ?>
    <div class="flex items-center justify-between mt-6">
      <div class="text-sm text-gray-400">Page <?= $page ?>/<?= $pages ?> · <?= number_format($total) ?> draws</div>
      <div class="flex gap-2">
        <?php if ($page > 1): ?>
        <a href="?page=<?= $page-1 ?>&q=<?= urlencode($search) ?>" class="btn btn-secondary btn-sm">← Prev</a>
        <?php endif; ?>
        <?php if ($page < $pages): ?>
        <a href="?page=<?= $page+1 ?>&q=<?= urlencode($search) ?>" class="btn btn-secondary btn-sm">Next →</a>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php else: ?>
    <div class="card p-16 text-center">
      <div class="text-5xl mb-4">🏆</div>
      <div class="text-gray-400 text-lg font-semibold mb-2">No completed draws yet</div>
      <div class="text-gray-600 text-sm">Check back after the first draw ends!</div>
    </div>
    <?php endif; ?>

    <?php endif; // end list vs single view ?>

  </div>
</div>

<script src="<?= APP_URL ?>/assets/js/app.js"></script>
</body>
</html>
<?php
// ── Private helpers ────────────────────────────────────────
function _maskName(string $name): string {
    $parts = explode(' ', trim($name));
    return implode(' ', array_map(function($p) {
        if (mb_strlen($p) <= 1) return $p;
        return mb_substr($p,0,1) . str_repeat('*', mb_strlen($p)-1);
    }, $parts));
}
function _maskPhone(string $phone): string {
    $p = preg_replace('/\D/','',$phone);
    return mb_substr($p,0,4) . '****' . mb_substr($p,-3);
}

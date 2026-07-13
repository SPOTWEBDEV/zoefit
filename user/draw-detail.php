<?php
// user/draw-detail.php
// Shows full draw result: winner, top 3, ALL participants ranked,
// logged-in user's row is highlighted and pinned at top.
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
startAppSession();
$db = getDB();

$drawId = (int)($_GET['id'] ?? 0);
if (!$drawId) redirect(APP_URL . '/user/past-winners.php');

// Only show completed draws to users
$draw = $db->prepare(
    "SELECT d.*,
            dw.winning_code, dw.matched_digits, dw.tiebreaker_used,
            dw.announced_at, dw.user_code AS winner_code,
            u.full_name AS winner_name, u.phone AS winner_phone,
            u.id        AS winner_uid
     FROM draws d
     LEFT JOIN draw_winners dw ON dw.draw_id = d.id
     LEFT JOIN users u         ON u.id        = dw.user_id
     WHERE d.id = ? AND d.status = 'completed'"
);
$draw->execute([$drawId]); $draw = $draw->fetch();
if (!$draw) redirect(APP_URL . '/user/past-winners.php');

$loggedInUserId = $_SESSION['user_id'] ?? null;
$isLoggedIn     = (bool)$loggedInUserId;

// ── Top 3 official rankings ────────────────────────────────
$top3 = $db->prepare(
    "SELECT dr.rank_position, dr.matched_digits, dr.entries_count, dr.tiebreaker,
            u.full_name, u.phone, u.id AS uid
     FROM draw_rankings dr
     JOIN users u ON u.id = dr.user_id
     WHERE dr.draw_id = ?
     ORDER BY dr.rank_position ASC"
);
$top3->execute([$drawId]); $top3 = $top3->fetchAll();

// ── ALL participants ranked by matched digits ──────────────
// We score each user's BEST code against the winning code
$allParticipants = $db->prepare(
    "SELECT u.id AS uid, u.full_name, u.phone,
            COUNT(de.id)   AS entry_count,
            MIN(de.entered_at) AS first_entry
     FROM draw_entries de
     JOIN users u ON u.id = de.user_id
     WHERE de.draw_id = ?
     GROUP BY de.user_id
     ORDER BY entry_count DESC"
);
$allParticipants->execute([$drawId]);
$allParticipants = $allParticipants->fetchAll();

// Score each participant — find their best code match
$winningCode = $draw['winning_code'] ?? null;
$scoredParticipants = [];

foreach ($allParticipants as $p) {
    $matched  = 0;
    $bestCode = null;

    if ($winningCode) {
        // Get all codes this user entered
        $userCodes = $db->prepare(
            "SELECT c.code FROM draw_entries de
             JOIN codes c ON c.id = de.code_id
             WHERE de.draw_id = ? AND de.user_id = ?"
        );
        $userCodes->execute([$drawId, $p['uid']]);
        $userCodes = $userCodes->fetchAll(PDO::FETCH_COLUMN);

        foreach ($userCodes as $uc) {
            $m = 0;
            for ($i = 0; $i < 15; $i++) {
                if (isset($uc[$i], $winningCode[$i]) && $uc[$i] === $winningCode[$i]) $m++;
            }
            if ($m > $matched) { $matched = $m; $bestCode = $uc; }
        }
    }

    $scoredParticipants[] = array_merge($p, [
        'matched'   => $matched,
        'best_code' => $bestCode,
    ]);
}

// Sort: matched DESC → entry_count DESC → first_entry ASC
usort($scoredParticipants, function($a, $b) {
    if ($b['matched']     !== $a['matched'])     return $b['matched']     - $a['matched'];
    if ($b['entry_count'] !== $a['entry_count']) return $b['entry_count'] - $a['entry_count'];
    return strtotime($a['first_entry'])           - strtotime($b['first_entry']);
});

// Find logged-in user's position
$myPosition  = null;
$myRow       = null;
foreach ($scoredParticipants as $pos => $p) {
    if ($isLoggedIn && (int)$p['uid'] === (int)$loggedInUserId) {
        $myPosition = $pos + 1; // 1-based
        $myRow      = $p;
        break;
    }
}

$totalParticipants = count($scoredParticipants);
$totalEntries      = array_sum(array_column($scoredParticipants, 'entry_count'));

// ── Pagination for participant table ──────────────────────
$tpage   = max(1, (int)($_GET['tpage'] ?? 1));
$tper    = 20;
$toffset = ($tpage - 1) * $tper;
$tpages  = (int)ceil($totalParticipants / $tper);
$tableRows = array_slice($scoredParticipants, $toffset, $tper);

$currentPage = 'past-winners';
$pageTitle   = e($draw['title']) . ' — Draw Result';

function maskName(string $name, bool $isMe = false): string {
    if ($isMe) return $name; // Show full name to the logged-in user for their own row
    $parts = explode(' ', trim($name));
    return implode(' ', array_map(function($p) {
        if (mb_strlen($p) <= 1) return $p;
        return mb_substr($p,0,1) . str_repeat('*', min(mb_strlen($p)-1, 4));
    }, $parts));
}
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= $pageTitle ?> — <?= APP_NAME ?></title>
  <script src="<?= APP_URL ?>/assets/js/tailwind.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
  <style>
    * { font-family: 'Poppins', sans-serif !important; }
    code { font-family: 'Courier New', monospace !important; }

    /* Digit comparison slots */
    .digit-slot {
      display: inline-flex; align-items: center; justify-content: center;
      width: 28px; height: 34px; border-radius: 6px;
      font-size: 15px; font-weight: 900;
      font-family: 'Courier New', monospace;
      border: 1.5px solid rgba(255,255,255,.08);
      background: rgba(255,255,255,.04);
    }
    .digit-slot.match    { background: rgba(34,197,94,.18); border-color: #22c55e; color: #22c55e; }
    .digit-slot.no-match { background: rgba(239,68,68,.08); border-color: rgba(239,68,68,.2); color: #6b7280; }
    .digit-slot.winning  { background: rgba(249,115,22,.12); border-color: rgba(249,115,22,.35); color: #f97316; }

    /* Podium */
    .podium-1 { background: linear-gradient(135deg,rgba(234,179,8,.15),rgba(0,0,0,0)); border: 1px solid rgba(234,179,8,.3); }
    .podium-2 { background: linear-gradient(135deg,rgba(156,163,175,.1),rgba(0,0,0,0)); border: 1px solid rgba(156,163,175,.2); }
    .podium-3 { background: linear-gradient(135deg,rgba(180,83,9,.1),rgba(0,0,0,0));   border: 1px solid rgba(180,83,9,.2); }

    /* Participant table — MY row */
    .my-row { background: rgba(249,115,22,.08) !important; }
    .my-row td { border-top: 1px solid rgba(249,115,22,.2) !important; border-bottom: 1px solid rgba(249,115,22,.2) !important; }
    .my-row td:first-child { border-left: 3px solid #f97316; }

    /* Position badge */
    .pos-badge {
      width: 32px; height: 32px; border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      font-size: .72rem; font-weight: 900; flex-shrink: 0;
    }
  </style>
</head>
<body class="bg-[#0a0f1a] text-white">
<?php include __DIR__ . '/../components/user-sidebar.php'; ?>

<div class="main-content">
  <div class="topbar">
    <button onclick="toggleSidebar()" class="md:hidden text-gray-400 text-2xl mr-3">☰</button>
    <div class="min-w-0">
      <a href="<?= APP_URL ?>/user/past-winners.php"
         class="text-orange-400 text-sm hover:underline">← Past Winners</a>
      <h1 class="text-base font-bold mt-0.5 truncate"><?= e($draw['title']) ?></h1>
    </div>
  </div>

  <div class="p-4 md:p-6 pb-24 md:pb-6">

    <!-- ── DRAW HEADER ─────────────────────────────────────── -->
    <div class="card p-5 mb-5"
         style="border-color:rgba(234,179,8,.2);background:linear-gradient(135deg,rgba(234,179,8,.06),rgba(0,0,0,0))">
      <div class="flex items-start gap-4">
        <div class="w-16 h-16 rounded-2xl flex-shrink-0 flex items-center justify-center text-3xl overflow-hidden"
             style="background:linear-gradient(135deg,#1a2235,#0d1118)">
          <?php if ($draw['banner_image'] && file_exists(UPLOAD_PATH.$draw['banner_image'])): ?>
          <img src="<?= APP_URL ?>/uploads/<?= e($draw['banner_image']) ?>" class="w-full h-full object-cover" alt="">
          <?php else: ?>🏆<?php endif; ?>
        </div>
        <div class="flex-1 min-w-0">
          <div class="flex items-center gap-2 flex-wrap mb-1">
            <h1 class="font-black text-xl text-yellow-400"><?= e($draw['title']) ?></h1>
            <span class="badge badge-muted text-xs">Completed</span>
            <?php if ($draw['category']): ?>
            <span class="badge badge-info text-xs"><?= e($draw['category']) ?></span>
            <?php endif; ?>
          </div>
          <div class="text-xs text-gray-500 flex flex-wrap gap-4">
            <span>📅 Ended <?= date('M j, Y g:i A', strtotime($draw['end_date'])) ?></span>
            <span>👥 <?= number_format($totalParticipants) ?> participants</span>
            <span>📝 <?= number_format($totalEntries) ?> total entries</span>
            <?php if ($draw['announced_at']): ?>
            <span>🔒 Winner selected <?= date('M j, Y g:i A', strtotime($draw['announced_at'])) ?></span>
            <?php endif; ?>
          </div>
          <?php if ($draw['prize_details']): ?>
          <div class="mt-1 text-sm text-orange-400 font-semibold">🎁 <?= e($draw['prize_details']) ?></div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- ── MY POSITION BANNER (if logged in and participated) ── -->
    <?php if ($isLoggedIn && $myPosition !== null): ?>
    <div class="rounded-xl p-4 mb-5 flex items-center gap-4 flex-wrap"
         style="background:rgba(249,115,22,.08);border:2px solid rgba(249,115,22,.3)">
      <div class="text-3xl flex-shrink-0">
        <?= $myPosition === 1 ? '🏆' : ($myPosition === 2 ? '🥈' : ($myPosition === 3 ? '🥉' : '📊')) ?>
      </div>
      <div class="flex-1 min-w-0">
        <div class="font-bold text-orange-400">
          Your Position: <span class="text-white text-xl font-black">#<?= number_format($myPosition) ?></span>
          of <?= number_format($totalParticipants) ?>
        </div>
        <div class="text-sm text-gray-400 mt-0.5">
          You entered <?= number_format($myRow['entry_count']) ?> code<?= $myRow['entry_count']!==1?'s':'' ?>
          <?php if ($winningCode && $myRow['matched'] !== null): ?>
          · Your best code matched <strong class="text-<?= $myRow['matched']>=10?'green':'yellow' ?>-400"><?= $myRow['matched'] ?>/15</strong> digits
          <?php endif; ?>
        </div>
      </div>
      <?php if ($myPosition === 1): ?>
      <div class="text-yellow-400 font-black text-sm">🎉 YOU WON!</div>
      <?php endif; ?>
    </div>
    <?php elseif ($isLoggedIn && $myPosition === null): ?>
    <div class="rounded-xl p-4 mb-5 flex items-center gap-3 text-sm"
         style="background:rgba(107,114,128,.08);border:1px solid rgba(107,114,128,.2)">
      <span class="text-gray-500">ℹ️</span>
      <span class="text-gray-400">You did not participate in this draw.</span>
    </div>
    <?php elseif (!$isLoggedIn): ?>
    <div class="rounded-xl p-4 mb-5 flex items-center gap-3 text-sm"
         style="background:rgba(249,115,22,.06);border:1px solid rgba(249,115,22,.15)">
      <span class="text-orange-400">👤</span>
      <span class="text-gray-300">
        <a href="<?= APP_URL ?>/user/login.php" class="text-orange-400 hover:underline font-semibold">Log in</a>
        to see your position in this draw.
      </span>
    </div>
    <?php endif; ?>

    <!-- ── WINNING CODE ────────────────────────────────────── -->
    <?php if ($draw['winning_code']): ?>
    <div class="card p-5 mb-5 text-center">
      <div class="text-sm text-gray-400 font-semibold mb-3">🎲 Winning Number</div>
      <div class="flex flex-wrap gap-1.5 justify-center mb-2">
        <?php for ($i = 0; $i < 15; $i++): ?>
        <div class="digit-slot winning"><?= $draw['winning_code'][$i] ?></div>
        <?php endfor; ?>
      </div>
      <div class="text-xs text-gray-600">
        Entered by admin from the physical draw machine
      </div>
    </div>
    <?php endif; ?>

    <!-- ── WINNER CARD ─────────────────────────────────────── -->
    <?php if ($draw['winner_name']): ?>
    <div class="card p-6 mb-5"
         style="border-color:rgba(234,179,8,.4);background:linear-gradient(135deg,rgba(234,179,8,.07),rgba(0,0,0,0))">
      <div class="flex items-center gap-3 mb-4">
        <span class="text-3xl">🏆</span>
        <h2 class="font-black text-xl text-yellow-400">Winner</h2>
      </div>
      <div class="flex items-center gap-4 flex-wrap">
        <div class="w-14 h-14 bg-yellow-500/20 rounded-2xl flex items-center justify-center
                    text-xl font-black text-yellow-400 flex-shrink-0">
          <?= strtoupper(mb_substr($draw['winner_name'],0,1)) ?>
        </div>
        <div class="flex-1 min-w-0">
          <div class="font-bold text-yellow-400 text-lg"><?= e(maskName($draw['winner_name'], (int)($draw['winner_uid']??0) === $loggedInUserId)) ?></div>
          <div class="text-sm text-gray-400"><?= e(_maskPhone($draw['winner_phone'])) ?></div>
          <?php if ($draw['tiebreaker_used']): ?>
          <div class="text-xs text-orange-400 mt-0.5">via <?= e(str_replace('_',' ',$draw['tiebreaker_used'])) ?></div>
          <?php endif; ?>
        </div>
        <div class="text-right flex-shrink-0">
          <div class="text-2xl font-black text-yellow-400"><?= $draw['matched_digits'] ?>/15</div>
          <div class="text-xs text-gray-500">digits matched</div>
        </div>
      </div>

      <!-- Winner's code vs winning code (no code shown to users — just match count) -->
      <?php if ($draw['winning_code'] && $draw['winner_code']): ?>
      <div class="mt-4 pt-4 border-t border-white/5">
        <div class="text-xs text-gray-500 mb-2">Winner's best code digit comparison:</div>
        <div class="flex flex-wrap gap-1">
          <?php for ($i = 0; $i < 15; $i++): ?>
          <div class="digit-slot <?= $draw['winner_code'][$i] === $draw['winning_code'][$i] ? 'match' : 'no-match' ?>">
            <?= $draw['winner_code'][$i] ?>
          </div>
          <?php endfor; ?>
        </div>
        <div class="text-xs text-gray-600 mt-1">🟢 position matched &nbsp; 🔴 no match</div>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- ── TOP 3 PODIUM ────────────────────────────────────── -->
    <?php if ($top3): ?>
    <div class="mb-5">
      <h2 class="font-bold text-base mb-3 flex items-center gap-2">🏅 Top 3 Finishers</h2>
      <div class="grid grid-cols-3 gap-3">
        <?php
        $podiumMeta = [
          1 => ['🥇','podium-1','text-yellow-400'],
          2 => ['🥈','podium-2','text-gray-300'],
          3 => ['🥉','podium-3','text-orange-300'],
        ];
        // Desktop order: 2-1-3
        $ordered = [];
        foreach ([2,1,3] as $pos) {
            foreach ($top3 as $r) {
                if ($r['rank_position'] === $pos) { $ordered[] = $r; break; }
            }
        }
        foreach ($ordered as $r):
            $pm    = $podiumMeta[$r['rank_position']] ?? $podiumMeta[3];
            $isMe  = $isLoggedIn && (int)$r['uid'] === (int)$loggedInUserId;
        ?>
        <div class="<?= $pm[1] ?> rounded-2xl p-4 text-center
                    <?= $r['rank_position']===1?'sm:order-2':($r['rank_position']===2?'sm:order-1':'sm:order-3') ?>
                    <?= $isMe?'ring-2 ring-orange-500':'' ?>">
          <?php if ($isMe): ?><div class="text-xs text-orange-400 font-bold mb-1">← You</div><?php endif; ?>
          <div class="text-3xl mb-2"><?= $pm[0] ?></div>
          <div class="font-bold <?= $pm[2] ?> text-sm mb-0.5">
            <?= e(maskName($r['full_name'], $isMe)) ?>
          </div>
          <div class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-bold mt-1"
               style="background:rgba(255,255,255,.06)">
            <span class="<?= $r['matched_digits']>=10?'text-green-400':($r['matched_digits']>=5?'text-yellow-400':'text-gray-400') ?>">
              <?= $r['matched_digits'] ?>/15
            </span>
          </div>
          <div class="text-xs text-gray-600 mt-1"><?= $r['entries_count'] ?> entries</div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- ── ALL PARTICIPANTS TABLE ──────────────────────────── -->
    <div class="card mb-5">
      <div class="flex items-center justify-between px-5 pt-5 pb-3 border-b border-white/5">
        <div>
          <h3 class="font-bold text-sm">📋 All Participants</h3>
          <div class="text-xs text-gray-500 mt-0.5">
            <?= number_format($totalParticipants) ?> participants · sorted by digit match score
            <?php if ($isLoggedIn && $myPosition): ?>
            · <span class="text-orange-400">Your row is highlighted</span>
            <?php endif; ?>
          </div>
        </div>
        <?php if ($tpages > 1): ?>
        <div class="text-xs text-gray-500">Page <?= $tpage ?>/<?= $tpages ?></div>
        <?php endif; ?>
      </div>

      <!-- MY ROW pinned at top when not on page 1 -->
      <?php if ($isLoggedIn && $myPosition && $tpage > 1 && $myPosition > ($tpage-1)*$tper+$tper): ?>
      <div class="px-4 py-2 text-xs text-orange-400 font-semibold"
           style="background:rgba(249,115,22,.06);border-bottom:1px solid rgba(249,115,22,.1)">
        📌 Your position (#<?= $myPosition ?>) is pinned here — scroll down to find it in the full list
      </div>
      <table class="w-full">
        <tbody>
          <?php _renderParticipantRow($myRow, $myPosition, true, $winningCode, $loggedInUserId); ?>
        </tbody>
      </table>
      <div class="border-b border-white/5"></div>
      <?php endif; ?>

      <div class="overflow-x-auto">
        <table class="w-full min-w-[500px]">
          <thead>
            <tr style="background:rgba(255,255,255,.02)">
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider w-12">#</th>
              <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Name</th>
              <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Matched</th>
              <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Entries</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-white/5">
            <?php foreach ($tableRows as $rowIdx => $p):
              $globalPos = $toffset + $rowIdx + 1;
              $isMe      = $isLoggedIn && (int)$p['uid'] === (int)$loggedInUserId;
            ?>
            <tr class="<?= $isMe ? 'my-row' : 'hover:bg-white/2' ?> transition-colors">
              <td class="px-4 py-3">
                <div class="pos-badge <?= $globalPos===1?'bg-yellow-500/20 text-yellow-400':($globalPos===2?'bg-gray-400/15 text-gray-300':($globalPos===3?'bg-orange-700/15 text-orange-400':'bg-white/5 text-gray-500')) ?>">
                  <?= $globalPos <= 3 ? ['🥇','🥈','🥉'][$globalPos-1] : number_format($globalPos) ?>
                </div>
              </td>
              <td class="px-4 py-3">
                <div class="flex items-center gap-2">
                  <div class="font-semibold text-sm <?= $isMe?'text-orange-400':'text-white' ?>">
                    <?= e(maskName($p['full_name'], $isMe)) ?>
                  </div>
                  <?php if ($isMe): ?>
                  <span class="text-xs bg-orange-500/20 border border-orange-500/30 text-orange-400 rounded-full px-1.5 py-0.5 font-bold">You</span>
                  <?php endif; ?>
                  <?php if ($globalPos === 1): ?>
                  <span class="text-xs text-yellow-400">🏆</span>
                  <?php endif; ?>
                </div>
              </td>
              <td class="px-4 py-3 text-right">
                <?php if ($winningCode && $p['matched'] !== null): ?>
                <span class="font-bold text-sm <?= $p['matched']>=10?'text-green-400':($p['matched']>=5?'text-yellow-400':'text-gray-500') ?>">
                  <?= $p['matched'] ?>/15
                </span>
                <?php else: ?>
                <span class="text-gray-600 text-sm">—</span>
                <?php endif; ?>
              </td>
              <td class="px-4 py-3 text-right text-sm text-gray-400">
                <?= number_format($p['entry_count']) ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- Table pagination -->
      <?php if ($tpages > 1): ?>
      <div class="flex items-center justify-between px-4 py-3 border-t border-white/5">
        <div class="text-xs text-gray-500">
          <?= number_format($toffset+1) ?>–<?= number_format(min($toffset+$tper,$totalParticipants)) ?>
          of <?= number_format($totalParticipants) ?>
        </div>
        <div class="flex gap-2">
          <?php if ($tpage > 1): ?>
          <a href="?id=<?= $drawId ?>&tpage=<?= $tpage-1 ?>" class="btn btn-sm btn-secondary text-xs">← Prev</a>
          <?php endif; ?>
          <?php if ($tpage < $tpages): ?>
          <a href="?id=<?= $drawId ?>&tpage=<?= $tpage+1 ?>" class="btn btn-sm btn-secondary text-xs">Next →</a>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>

    <!-- Back link -->
    <div class="text-center">
      <a href="<?= APP_URL ?>/user/draws.php"
         class="btn btn-secondary px-8">← Back to All Draws</a>
    </div>

  </div>
</div>

<script src="<?= APP_URL ?>/assets/js/app.js"></script>
<?php if ($isLoggedIn && $myPosition): ?>
<script>
// Smooth scroll to user's own row on page load
document.addEventListener('DOMContentLoaded', function () {
  var myRow = document.querySelector('.my-row');
  if (myRow) {
    setTimeout(function () {
      myRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }, 500);
  }
});
</script>
<?php endif; ?>
</body>
</html>
<?php
function _maskPhone(string $phone): string {
    $p = preg_replace('/\D/', '', $phone);
    if (strlen($p) < 7) return '***';
    return substr($p,0,4) . '****' . substr($p,-3);
}
?>
<?php
// user/draw-detail.php
// Shows full draw result: winner, top 3, ALL participants ranked,
// logged-in user's row is highlighted and pinned at top.
//
// CHANGE LOG (this revision):
//  - Top 3 Finishers is NO LONGER read from the draw_rankings table (a
//    frozen snapshot written once, at confirm-time, by admin/select-winner.php).
//    That table could drift from reality (e.g. it can only ever show 3 rows,
//    and in the old buggy version even stored a bad user_id). Top 3 is now
//    simply the first 3 entries of $scoredParticipants — the EXACT SAME
//    array the "All Participants" table renders — so the two can never
//    disagree about who's #1/#2/#3 again.
//  - $scoredParticipants comes from includes/draw-scoring.php::
//    getDrawParticipantRanking(), scored directly against draws.winning_code
//    ("the code that was used to select the winner"), the SAME function
//    admin/select-winner.php and admin/winners.php use.
//  - Top 3 boxes render in strict rank order (box 1 = 1st place, box 2 =
//    2nd place, box 3 = 3rd place).
//  - All Participants rows always show a numeric "#N" position; the medal
//    emoji (only once official) is shown ALONGSIDE the number, not instead
//    of it.
//  - Share/Download screenshot: #share-capture now has its own padding
//    (previously it only inherited spacing from its parent, which
//    html2canvas never captures, so exports looked cropped flush against
//    the edges) and a fix for html2canvas silently dropping CSS
//    flexbox/grid `gap`.
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/draw-scoring.php';
startAppSession();
$db = getDB();

$drawId = (int)($_GET['id'] ?? 0);
if (!$drawId) redirect(APP_URL . '/user/past-winners.php');

// NOTE: we intentionally do NOT filter by d.status = 'completed' here anymore.
// A draw that has ended but is awaiting admin winner-selection still needs to
// render this page (participant list + "in progress" notice) instead of
// producing a bool-vs-array fetch failure.
$draw = $db->prepare(
    "SELECT d.*,
            dw.winning_code, dw.matched_digits, dw.tiebreaker_used,
            dw.announced_at, dw.user_code AS winner_code,
            u.full_name AS winner_name, u.phone AS winner_phone,
            u.id        AS winner_uid
     FROM draws d
     LEFT JOIN draw_winners dw ON dw.draw_id = d.id
     LEFT JOIN users u         ON u.id        = dw.user_id
     WHERE d.id = ?"
);
$draw->execute([$drawId]); $draw = $draw->fetch();

// Only a truly nonexistent draw ID should redirect away.
if (!$draw) redirect(APP_URL . '/user/past-winners.php');

$loggedInUserId = $_SESSION['user_id'] ?? null;
$isLoggedIn     = (bool)$loggedInUserId;

// ── Has the admin actually conducted the raffle and confirmed a winner? ────
// This is the ONLY source of truth for "who won" — never inferred from
// participant ranking, entry counts, or digit-match scoring.
$hasOfficialWinner = !empty($draw['winner_uid']);
$officialWinnerId  = $hasOfficialWinner ? (int)$draw['winner_uid'] : null;

// ── ALL participants ranked, scored directly against the winning code ─────
// This single computation feeds BOTH the "Top 3 Finishers" boxes and the
// "All Participants" table below — they can never show different people
// in different orders because they're reading the exact same array.
$winningCode = $draw['winning_code'] ?? null;
$scoredParticipants = getDrawParticipantRanking($db, $drawId, $winningCode);

// Top 3 = first 3 rows of that same ranking (only meaningful once official).
$top3 = $hasOfficialWinner ? array_slice($scoredParticipants, 0, 3) : [];

// Find logged-in user's position.
// IMPORTANT: this "position" is purely an informational leaderboard ranking
// computed from entries/digit matches. It must NEVER be treated
// as equivalent to "this user won" — only $officialWinnerId (from the
// admin-confirmed draw_winners row) determines that.
$myPosition  = null;
$myRow       = null;
foreach ($scoredParticipants as $pos => $p) {
    if ($isLoggedIn && (int)$p['uid'] === (int)$loggedInUserId) {
        $myPosition = $pos + 1; // 1-based
        $myRow      = $p;
        break;
    }
}

// Is the logged-in user THE admin-confirmed winner? This is the only flag
// allowed to trigger "You won" messaging anywhere on this page.
$isDeclaredWinner = $isLoggedIn && $hasOfficialWinner && $officialWinnerId === (int)$loggedInUserId;

$totalParticipants = count($scoredParticipants);
$totalEntries       = array_sum(array_column($scoredParticipants, 'entry_count'));

// ── Pagination for participant table ──────────────────────
$tpage   = max(1, (int)($_GET['tpage'] ?? 1));
$tper    = 20;
$toffset = ($tpage - 1) * $tper;
$tpages  = (int)ceil($totalParticipants / $tper);
$tableRows = array_slice($scoredParticipants, $toffset, $tper);

$currentPage = 'draw-detail';
$pageTitle   = e($draw['title']) . ' — Draw Result';

function maskName(string $name, bool $isMe = false): string {
    if ($isMe) return $name; // Show full name to the logged-in user for their own row
    $parts = explode(' ', trim($name));
    return implode(' ', array_map(function($p) {
        if (mb_strlen($p) <= 1) return $p;
        return mb_substr($p,0,1) . str_repeat('*', min(mb_strlen($p)-1, 4));
    }, $parts));
}

// $hasOfficialWinner is threaded through so medal emoji only ever appears
// once the admin has actually confirmed a winner. The position NUMBER is
// always shown — the medal is an extra decoration next to it, never a
// replacement for it.
function _renderParticipantRow(array $p, int $pos, bool $isMe, ?string $winningCode, bool $hasOfficialWinner): void {
    $showMedal = $hasOfficialWinner && $pos <= 3;
    $badgeClass = $showMedal
        ? ($pos===1?'bg-yellow-500/20 text-yellow-400':($pos===2?'bg-gray-400/15 text-gray-300':'bg-orange-700/15 text-orange-400'))
        : 'bg-white/5 text-gray-500';
    ?>
    <tr class="<?= $isMe ? 'my-row' : '' ?>">
      <td class="px-4 py-3">
        <div class="pos-badge <?= $badgeClass ?>">
          <?php if ($showMedal): ?><span class="pos-badge-medal"><?= ['🥇','🥈','🥉'][$pos-1] ?></span><?php endif; ?>
          <span>#<?= number_format($pos) ?></span>
        </div>
      </td>
      <td class="px-4 py-3">
        <div class="flex items-center gap-2">
          <div class="font-semibold text-sm <?= $isMe ? 'text-orange-400' : 'text-white' ?>">
            <?= e($p['full_name']) ?>
          </div>
          <?php if ($isMe): ?>
          <span class="text-xs bg-orange-500/20 border border-orange-500/30 text-orange-400 rounded-full px-1.5 py-0.5 font-bold">You</span>
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
    <?php
}
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= $pageTitle ?> — <?= APP_NAME ?></title>
  <script src="<?= APP_URL ?>/assets/js/tailwind.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
  <style>
    * { font-family: 'Poppins', sans-serif !important; }
    code { font-family: 'Courier New', monospace !important; }

    /* Digit comparison slots */
    /* NOTE on centering: this uses line-height = height instead of flexbox
       align-items to vertically center the digit. html2canvas (used by the
       Share/Download button below) does not reliably center a raw text
       node inside a `display:flex; align-items:center` box — the text
       renders low, near the bottom of the box, in the exported image even
       though it looks perfectly centered in the live browser. Line-height
       centering is plain text layout (no flexbox involved), so it exports
       correctly. This is a general fix: the same swap should be applied to
       any other small fixed-height box that centers a single line of text
       and needs to survive a screenshot export. */
    .digit-slot {
      display: inline-block;
      width: 28px; height: 34px; line-height: 34px;
      border-radius: 6px; text-align: center; vertical-align: top;
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

    /* Position badge — pill shape so it can hold "#N" alone or "🥇 #N" */
    .pos-badge {
      min-width: 32px; height: 26px;
      padding: 0 8px;
      border-radius: 999px;
      display: inline-flex; align-items: center; justify-content: center; gap: 3px;
      font-size: .7rem; font-weight: 900; flex-shrink: 0; white-space: nowrap;
    }
    .pos-badge-medal { font-size: .85rem; line-height: 1; }

    /* ── Share-capture container ─────────────────────────────
       Its own padding so the exported screenshot has breathing room —
       previously this div had none and only inherited spacing from its
       parent, which html2canvas never sees, making the export look
       cropped flush against the edges. */
    #share-capture {
      padding: 20px;
      background: #0a0f1a;
      border-radius: 16px;
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
    <button type="button" onclick="downloadResultImage()" data-html2canvas-ignore="true"
            class="ml-auto btn btn-secondary text-xs px-3 py-1.5 flex items-center gap-1.5 flex-shrink-0">
      📤 Share
    </button>
  </div>

  <div class="p-4 md:p-6 pb-24 md:pb-6">

    <div id="share-capture">

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
            <span class="badge <?= $hasOfficialWinner ? 'badge-muted' : 'badge-warning' ?> text-xs">
              <?= $hasOfficialWinner ? 'Completed' : e(ucfirst($draw['status'] ?? 'Ended')) ?>
            </span>
            <?php if ($draw['category']): ?>
            <span class="badge badge-info text-xs"><?= e($draw['category']) ?></span>
            <?php endif; ?>
          </div>
          <div class="text-xs text-gray-500 flex flex-wrap gap-4">
            <span>📅 Ended <?= $draw['end_date'] ? date('M j, Y g:i A', strtotime($draw['end_date'])) : 'N/A' ?></span>
            <span>👥 <?= number_format($totalParticipants) ?> participants</span>
            <span>📝 <?= number_format($totalEntries) ?> total entries</span>
            <?php if ($hasOfficialWinner && $draw['announced_at']): ?>
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
        <?php
          // Medal emoji for #2/#3 only makes sense once the draw is official —
          // otherwise this "position" is just an unofficial entries ranking.
          if ($isDeclaredWinner) echo '🏆';
          elseif ($hasOfficialWinner && $myPosition === 2) echo '🥈';
          elseif ($hasOfficialWinner && $myPosition === 3) echo '🥉';
          else echo '📊';
        ?>
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
          <?php if (!$hasOfficialWinner): ?>
          · <span class="text-gray-500">this ranking is unofficial until the admin conducts the draw</span>
          <?php endif; ?>
        </div>
      </div>
      <?php if ($isDeclaredWinner): ?>
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

    <!-- ── WINNER SECTION ──────────────────────────────────── -->
    <?php if ($hasOfficialWinner): ?>

      <!-- ── WINNING CODE ─────────────────────────────────── -->
      <?php if ($draw['winning_code']): ?>
      <div class="card p-5 mb-5 text-center">
        <div class="text-sm text-gray-400 font-semibold mb-3">🎲 Winning Number</div>
        <div class="flex flex-wrap gap-1.5 justify-center mb-2">
          <?php for ($i = 0; $i < 15; $i++): ?>
          <div class="digit-slot winning"><?= e($draw['winning_code'][$i]) ?></div>
          <?php endfor; ?>
        </div>
        <div class="text-xs text-gray-600">
          Entered by admin from the physical draw machine
        </div>
      </div>
      <?php endif; ?>

      <!-- ── WINNER CARD ──────────────────────────────────── -->
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
            <div class="font-bold text-yellow-400 text-lg"><?= e($draw['winner_name']) ?></div>
            <div class="text-sm text-gray-400"><?= e(maskPhoneDigits($draw['winner_phone'])) ?></div>
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
              <?= e($draw['winner_code'][$i]) ?>
            </div>
            <?php endfor; ?>
          </div>
          <div class="text-xs text-gray-600 mt-1">🟢 position matched &nbsp; 🔴 no match</div>
        </div>
        <?php endif; ?>
      </div>

      <!-- ── TOP 3 PODIUM ─────────────────────────────────── -->
      <!-- $top3 = array_slice($scoredParticipants, 0, 3) — the EXACT SAME
           array the "All Participants" table below renders. Box 1 = 1st
           place, box 2 = 2nd place, box 3 = 3rd place, always in that
           strict rank order. -->
      <?php if ($top3): ?>
      <div class="mb-5">
        <h2 class="font-bold text-base mb-3 flex items-center gap-2">🏅 Top 3 Finishers</h2>
        <div class="grid gap-3" style="grid-template-columns: repeat(<?= max(count($top3),1) ?>, minmax(0,1fr));">
          <?php
          $podiumMeta = [
            1 => ['🥇','podium-1','text-yellow-400'],
            2 => ['🥈','podium-2','text-gray-300'],
            3 => ['🥉','podium-3','text-orange-300'],
          ];
          foreach ($top3 as $idx => $r):
              $rankPos = $idx + 1;
              $pm    = $podiumMeta[$rankPos] ?? $podiumMeta[3];
              $isMe  = $isLoggedIn && (int)$r['uid'] === (int)$loggedInUserId;
          ?>
          <div class="<?= $pm[1] ?> rounded-2xl p-4 text-center <?= $isMe?'ring-2 ring-orange-500':'' ?>">
            <?php if ($isMe): ?><div class="text-xs text-orange-400 font-bold mb-1">← You</div><?php endif; ?>
            <div class="text-xs text-gray-500 font-bold mb-1">#<?= $rankPos ?> PLACE</div>
            <div class="text-3xl mb-2"><?= $pm[0] ?></div>
            <div class="font-bold <?= $pm[2] ?> text-sm mb-0.5">
              <?= e($r['full_name']) ?>
            </div>
            <div class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-xs font-bold mt-1"
                 style="background:rgba(255,255,255,.06)">
              <span class="<?= $r['matched']>=10?'text-green-400':($r['matched']>=5?'text-yellow-400':'text-gray-400') ?>">
                <?= $r['matched'] ?>/15
              </span>
            </div>
            <div class="text-xs text-gray-600 mt-1"><?= $r['entry_count'] ?> entries</div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

    <?php else: ?>

      <!-- ── WINNER SELECTION IN PROGRESS ─────────────────── -->
      <div class="card p-6 mb-5 text-center"
           style="border-color:rgba(107,114,128,.3);background:rgba(107,114,128,.05)">
        <div class="text-4xl mb-3">🕒</div>
        <div class="font-bold text-lg text-gray-300 mb-1">Winner Selection In Progress</div>
        <div class="text-sm text-gray-500 max-w-md mx-auto">
          This draw has ended and entries are locked. Our admin will conduct the
          live draw shortly, and the winner will appear here once it's officially confirmed.
        </div>
      </div>

    <?php endif; ?>

    <!-- ── ALL PARTICIPANTS TABLE ──────────────────────────── -->
    <div class="card mb-5">
      <div class="flex items-center justify-between px-5 pt-5 pb-3 border-b border-white/5">
        <div>
          <h3 class="font-bold text-sm">📋 All Participants</h3>
          <div class="text-xs text-gray-500 mt-0.5">
            <?= number_format($totalParticipants) ?> participants
            <?php if ($hasOfficialWinner): ?>
            · sorted by digit match score
            <?php else: ?>
            · order will be finalized once the winning number is drawn — positions below are provisional (based on entries) and are <strong>not</strong> official 2nd/3rd place
            <?php endif; ?>
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
      <?php if ($isLoggedIn && $myPosition && $tpage > 1 && $myPosition <= ($tpage-1)*$tper): ?>
      <div class="px-4 py-2 text-xs text-orange-400 font-semibold"
           style="background:rgba(249,115,22,.06);border-bottom:1px solid rgba(249,115,22,.1)">
        📌 Your position (#<?= $myPosition ?>) is above — showing it here for reference
      </div>
      <table class="w-full">
        <tbody>
          <?php _renderParticipantRow($myRow, $myPosition, true, $winningCode, $hasOfficialWinner); ?>
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
              _renderParticipantRow($p, $globalPos, $isMe, $winningCode, $hasOfficialWinner);
            endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- Table pagination -->
      <?php if ($tpages > 1): ?>
      <div class="flex items-center justify-between px-4 py-3 border-t border-white/5" data-html2canvas-ignore="true">
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

    </div><!-- /#share-capture -->

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

<script>
// ── Share / Download screenshot ─────────────────────────────
// Phone numbers on this page are already masked server-side
// (maskPhoneDigits) wherever they're shown, so no extra client-side
// masking is required before capture.
//
// html2canvas does not support CSS flexbox/grid `gap` — it silently
// renders those containers with zero spacing between children, which is
// why digit boxes/badges looked cramped in exported images even though
// #share-capture now has its own padding. This walks the CLONED document
// html2canvas builds for rendering (never the live page) and converts any
// gap-based flex/grid container into margin-based spacing instead.
function fixGapForCapture(clonedDoc) {
  const view = clonedDoc.defaultView || window;
  const all = clonedDoc.body ? clonedDoc.body.querySelectorAll('*') : [];
  all.forEach(function (el) {
    const cs = view.getComputedStyle(el);
    const display = cs.display;
    if (display !== 'flex' && display !== 'inline-flex' && display !== 'grid' && display !== 'inline-grid') return;

    const rowGap = parseFloat(cs.rowGap) || 0;
    const colGap = parseFloat(cs.columnGap) || 0;
    if (rowGap <= 0 && colGap <= 0) return;

    el.style.gap = '0px';
    el.style.rowGap = '0px';
    el.style.columnGap = '0px';

    Array.from(el.children).forEach(function (child, idx, arr) {
      if (colGap > 0 && idx < arr.length - 1) {
        child.style.marginRight = ((parseFloat(view.getComputedStyle(child).marginRight) || 0) + colGap) + 'px';
      }
      if (rowGap > 0) {
        child.style.marginBottom = ((parseFloat(view.getComputedStyle(child).marginBottom) || 0) + rowGap) + 'px';
      }
    });
  });
}

// Safety net for the SAME html2canvas bug described above, applied
// automatically: any element whose only content is plain text (no child
// elements — so this naturally skips multi-part badges like .pos-badge,
// which has separate medal/number spans that need to stay side-by-side)
// and that uses `display:flex; align-items:center` gets converted to
// line-height centering just for the captured clone. This catches any
// similar box that gets added later without someone remembering to apply
// the CSS-level fix above.
function fixTextVerticalCenterForCapture(clonedDoc) {
  const view = clonedDoc.defaultView || window;
  const all = clonedDoc.body ? clonedDoc.body.querySelectorAll('*') : [];
  all.forEach(function (el) {
    if (el.children.length > 0) return; // only pure-text leaf boxes
    const text = el.textContent.trim();
    if (!text) return;
    const cs = view.getComputedStyle(el);
    if (cs.display.indexOf('flex') === -1) return;
    if (cs.alignItems !== 'center') return;
    const h = el.getBoundingClientRect().height || parseFloat(cs.height) || 0;
    if (!h) return;
    el.style.display = 'block';
    el.style.lineHeight = h + 'px';
    el.style.textAlign = 'center';
  });
}

function downloadResultImage() {
  const target = document.getElementById('share-capture');
  const btn = event ? event.currentTarget : null;
  const originalLabel = btn ? btn.innerHTML : null;
  if (btn) { btn.innerHTML = '⏳ Preparing…'; btn.disabled = true; }

  html2canvas(target, {
    backgroundColor: '#0a0f1a',
    scale: 2,
    useCORS: true,
    letterRendering: true,
    onclone: function (clonedDoc) {
      fixGapForCapture(clonedDoc);
      fixTextVerticalCenterForCapture(clonedDoc);
    }
  }).then(canvas => {
    const link = document.createElement('a');
    link.download = 'draw-result-<?= $drawId ?>.png';
    link.href = canvas.toDataURL('image/png');
    link.click();
    if (btn) { btn.innerHTML = originalLabel; btn.disabled = false; }
  }).catch(err => {
    console.error(err);
    alert('Could not generate the image. Please try again.');
    if (btn) { btn.innerHTML = originalLabel; btn.disabled = false; }
  });
}
</script>
</body>
</html>
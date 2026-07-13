<?php
// admin/select-winner.php
// Admin enters the 15-digit winning number from the physical machine.
// Live table updates as digits are typed showing matching users.
// Confirmation locks the winner permanently.
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../mailer/index.php';
$auth = requireAdmin(); $adminId = $auth['id'];
$db   = getDB();

$drawId = (int)($_GET['id'] ?? 0);
if (!$drawId) redirect(APP_URL . '/admin/draws.php');

$draw = $db->prepare("SELECT * FROM draws WHERE id=?");
$draw->execute([$drawId]); $draw = $draw->fetch();

if (!$draw) redirect(APP_URL . '/admin/draws.php');

// Only allow winner selection on 'ended' draws with no existing winner
if (!in_array($draw['status'], ['ended','completed'])) {
    redirect(APP_URL . '/admin/draw-manage.php?id=' . $drawId);
}

$alreadyHasWinner = (bool)$draw['winner_user_id'];
$msg = $err = '';

// ── AJAX: live digit search ────────────────────────────────
// Called by JS as digits are typed: ?ajax=1&digits=7402...
if (isset($_GET['ajax']) && isset($_GET['digits'])) {
    header('Content-Type: application/json');
    $digits = preg_replace('/\D/', '', $_GET['digits']);
    $len    = min(strlen($digits), 15);

    if ($len === 0) {
        echo json_encode(['users' => [], 'digits_entered' => 0]);
        exit;
    }

    // Find all users who entered this draw
    // Score each of their codes against the partial winning number so far
    $entries = $db->prepare(
        "SELECT u.id, u.full_name, u.phone, c.code,
                de.entered_at
         FROM draw_entries de
         JOIN users u ON u.id = de.user_id
         JOIN codes c ON c.id = de.code_id
         WHERE de.draw_id = ?
         ORDER BY de.entered_at ASC"
    );
    $entries->execute([$drawId]);
    $entries = $entries->fetchAll();

    // Score per user — best code only
    $scores = [];
    foreach ($entries as $entry) {
        $uid     = (int)$entry['id'];
        $code    = $entry['code'];
        $matched = 0;
        for ($i = 0; $i < $len; $i++) {
            if (isset($code[$i], $digits[$i]) && $code[$i] === $digits[$i]) {
                $matched++;
            }
        }
        if (!isset($scores[$uid]) || $matched > $scores[$uid]['matched']) {
            $scores[$uid] = [
                'user_id'     => $uid,
                'full_name'   => $entry['full_name'],
                'phone'       => $entry['phone'],
                'best_code'   => $code,
                'matched'     => $matched,
                'entry_count' => ($scores[$uid]['entry_count'] ?? 0),
            ];
        }
        $scores[$uid]['entry_count'] = ($scores[$uid]['entry_count'] ?? 0) + 1;
    }

    // Sort: matched DESC → entry_count DESC
    usort($scores, function($a, $b) {
        if ($b['matched'] !== $a['matched']) return $b['matched'] - $a['matched'];
        return $b['entry_count'] - $a['entry_count'];
    });

    // Return top 20 for display
    $out = [];
    foreach (array_slice($scores, 0, 20) as $s) {
        // Build highlighted code
        $highlighted = [];
        for ($i = 0; $i < 15; $i++) {
            $highlighted[] = [
                'digit'   => $s['best_code'][$i] ?? '?',
                'match'   => isset($digits[$i], $s['best_code'][$i])
                             && $digits[$i] === ($s['best_code'][$i] ?? '')
                             && $i < $len,
                'checked' => $i < $len,
            ];
        }
        $out[] = [
            'user_id'     => $s['user_id'],
            'full_name'   => $s['full_name'],
            'phone'       => $s['phone'],
            'best_code'   => $s['best_code'],
            'matched'     => $s['matched'],
            'entry_count' => $s['entry_count'],
            'highlighted' => $highlighted,
        ];
    }

    echo json_encode([
        'users'          => $out,
        'digits_entered' => $len,
        'total_entered'  => count($scores),
    ]);
    exit;
}

// ── FINAL SUBMIT: lock in the winner ──────────────────────
if (isPost() && verifyCsrf($_POST[CSRF_TOKEN_NAME] ?? '') && !$alreadyHasWinner) {
    $winningCode = preg_replace('/\D/', '', $_POST['winning_code'] ?? '');
    $winnerId    = (int)($_POST['winner_user_id'] ?? 0);

    if (strlen($winningCode) !== 15) {
        $err = 'Winning code must be exactly 15 digits.';
    } elseif (!$winnerId) {
        $err = 'No winner selected. Please enter all 15 digits first.';
    } else {
        // Verify winner is a real participant
        $check = $db->prepare("SELECT COUNT(*) FROM draw_entries WHERE draw_id=? AND user_id=?");
        $check->execute([$drawId, $winnerId]);
        if (!(int)$check->fetchColumn()) {
            $err = 'Selected winner did not participate in this draw.';
        } else {
            // Get winner best code
            $wEntries = $db->prepare(
                "SELECT c.code FROM draw_entries de JOIN codes c ON c.id=de.code_id WHERE de.draw_id=? AND de.user_id=?"
            );
            $wEntries->execute([$drawId, $winnerId]);
            $wCodes = $wEntries->fetchAll(PDO::FETCH_COLUMN);
            $bestCode = ''; $bestMatch = -1;
            foreach ($wCodes as $wc) {
                $m = 0;
                for ($i = 0; $i < 15; $i++) {
                    if (isset($wc[$i], $winningCode[$i]) && $wc[$i] === $winningCode[$i]) $m++;
                }
                if ($m > $bestMatch) { $bestMatch = $m; $bestCode = $wc; }
            }

            // Get top 3 for rankings
            $allEntries = $db->prepare(
                "SELECT de.user_id, c.code, u.created_at AS user_created_at
                 FROM draw_entries de JOIN codes c ON c.id=de.code_id JOIN users u ON u.id=de.user_id
                 WHERE de.draw_id=?"
            );
            $allEntries->execute([$drawId]);
            $allEntries = $allEntries->fetchAll();

            $allScores = [];
            foreach ($allEntries as $ae) {
                $uid = (int)$ae['user_id'];
                $m   = 0;
                for ($i=0;$i<15;$i++) if(isset($ae['code'][$i],$winningCode[$i])&&$ae['code'][$i]===$winningCode[$i]) $m++;
                if (!isset($allScores[$uid])) {
                    $allScores[$uid] = ['matched'=>$m,'best_code'=>$ae['code'],'entry_count'=>0,'user_created_at'=>$ae['user_created_at']];
                }
                if ($m > $allScores[$uid]['matched']) { $allScores[$uid]['matched']=$m; $allScores[$uid]['best_code']=$ae['code']; }
                $allScores[$uid]['entry_count']++;
            }
            usort($allScores, function($a,$b){
                if($b['matched']!==$a['matched']) return $b['matched']-$a['matched'];
                if($b['entry_count']!==$a['entry_count']) return $b['entry_count']-$a['entry_count'];
                return strtotime($a['user_created_at'])-strtotime($b['user_created_at']);
            });

            // Detect tiebreaker
            $tiebreaker = null;
            if (count($allScores) > 1 && $allScores[0]['matched'] === ($allScores[1]['matched'] ?? -1)) {
                $tiebreaker = ($allScores[0]['entry_count'] !== ($allScores[1]['entry_count'] ?? -1))
                    ? 'most_entries' : 'earliest_registration';
            }

            // Write draw_rankings
            $db->exec("CREATE TABLE IF NOT EXISTS draw_rankings (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                draw_id INT UNSIGNED NOT NULL, user_id INT UNSIGNED NOT NULL,
                rank_position TINYINT UNSIGNED NOT NULL, user_code CHAR(15) NOT NULL,
                matched_digits TINYINT UNSIGNED NOT NULL DEFAULT 0,
                entries_count INT UNSIGNED NOT NULL DEFAULT 0,
                tiebreaker VARCHAR(100) DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_dr (draw_id,rank_position)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            $top3 = array_slice($allScores, 0, 3);
            $uids = array_column($top3, 'user_id', null);
            // Re-attach user_id key properly
            $flat = array_values($allScores);
            foreach (array_slice($flat, 0, 3) as $pos => $ranked) {
                $db->prepare("INSERT INTO draw_rankings (draw_id,user_id,rank_position,user_code,matched_digits,entries_count,tiebreaker) VALUES (?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE user_code=VALUES(user_code),matched_digits=VALUES(matched_digits)")
                   ->execute([$drawId, array_keys($allScores)[$pos], $pos+1, $ranked['best_code'], $ranked['matched'], $ranked['entry_count'], $pos===0?$tiebreaker:null]);
            }

            // Write draw_winners
            $db->prepare("INSERT INTO draw_winners (draw_id,user_id,winning_code,user_code,matched_digits,tiebreaker_used,announced_at) VALUES (?,?,?,?,?,?,NOW())")
               ->execute([$drawId,$winnerId,$winningCode,$bestCode,$bestMatch,$tiebreaker]);

            // Update draw
            $db->prepare("UPDATE draws SET status='completed',winning_code=?,winner_user_id=?,finalized_at=NOW(),finalized_by=?,updated_at=NOW() WHERE id=?")
               ->execute([$winningCode,$winnerId,$adminId,$drawId]);

            // Mark codes used
            $db->prepare("UPDATE codes SET status='used' WHERE id IN (SELECT code_id FROM draw_entries WHERE draw_id=?)")
               ->execute([$drawId]);

            // In-app notification
            if (function_exists('createNotification')) {
                $wUser = $db->prepare("SELECT full_name FROM users WHERE id=?");
                $wUser->execute([$winnerId]); $wUser = $wUser->fetch();
                createNotification($winnerId,'🏆 You Won! — '.$draw['title'],
                    "Congratulations {$wUser['full_name']}! Your code matched $bestMatch/15 digits of the winning number $winningCode. Contact admin to claim your prize.",'draw');
            }

            // Email winner
            $wInfo = $db->prepare("SELECT full_name, email FROM users WHERE id=?");
            $wInfo->execute([$winnerId]); $wInfo = $wInfo->fetch();
            if ($wInfo && $wInfo['email']) {
                $subject = '🏆 You Won — ' . $draw['title'];
                $body = '<p>Hi '.$wInfo['full_name'].',</p>
                         <p>Congratulations! You won the <strong>'.$draw['title'].'</strong> draw.</p>
                         <p>Your code <strong style="font-family:monospace">'.$bestCode.'</strong> matched <strong>'.$bestMatch.'/15</strong> digits of the winning number.</p>
                         <p>Prize: '.($draw['prize_details']??'Contact admin').'</p>
                         <p>Please contact ZoeFeeds admin to claim your prize.</p>';
                smtpmailer($wInfo['email'], $subject, $body);
            }

            auditLog('admin',$adminId,'select_winner',"Winner selected for draw #$drawId — user #$winnerId, code $winningCode",'draw',$drawId);
            redirect(APP_URL.'/admin/winners.php?draw='.$drawId.'&selected=1');
        }
    }
}

// Total entries
$totalEntries = (int)$db->prepare("SELECT COUNT(*) FROM draw_entries WHERE draw_id=?")->execute([$drawId]) ? 0 : 0;
$s = $db->prepare("SELECT COUNT(*) FROM draw_entries WHERE draw_id=?");
$s->execute([$drawId]); $totalEntries = (int)$s->fetchColumn();
$s = $db->prepare("SELECT COUNT(DISTINCT user_id) FROM draw_entries WHERE draw_id=?");
$s->execute([$drawId]); $totalParticipants = (int)$s->fetchColumn();

$aPage = 'draws';
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="csrf-token" content="<?= generateCsrf() ?>">
  <title>Select Winner — <?= APP_NAME ?></title>
  <script src="<?= APP_URL ?>/assets/js/tailwind.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
  <style>
    *{font-family:'Poppins',sans-serif!important}
    code{font-family:'Courier New',monospace!important}

    /* OTP digit boxes */
    .otp-digit {
      width: 52px; height: 64px;
      background: rgba(255,255,255,.04);
      border: 2px solid rgba(255,255,255,.10);
      border-radius: 12px; color: #fff;
      font-size: 1.6rem; font-weight: 900; text-align: center;
      font-family: 'Courier New', monospace;
      transition: border-color .15s, box-shadow .15s, background .15s;
      caret-color: #f97316;
    }
    .otp-digit:focus {
      border-color: #f97316; outline: none;
      box-shadow: 0 0 0 3px rgba(249,115,22,.2);
      background: rgba(249,115,22,.06);
    }
    .otp-digit.filled {
      border-color: rgba(249,115,22,.5);
      background: rgba(249,115,22,.08);
      color: #f97316;
    }
    .otp-digit.confirmed {
      border-color: rgba(34,197,94,.5);
      background: rgba(34,197,94,.08);
      color: #22c55e;
    }

    /* Code digit slots in table */
    .code-slot {
      display: inline-flex; align-items: center; justify-content: center;
      width: 22px; height: 26px; border-radius: 5px;
      font-size: 12px; font-weight: 800;
      font-family: 'Courier New', monospace;
      border: 1px solid rgba(255,255,255,.08);
      background: rgba(255,255,255,.03);
      transition: all .2s;
    }
    .code-slot.match    { background: rgba(34,197,94,.2); border-color: #22c55e; color: #22c55e; }
    .code-slot.no-match { background: rgba(239,68,68,.06); border-color: rgba(239,68,68,.15); color: #6b7280; }
    .code-slot.unchecked{ opacity: .4; }

    /* Winner highlight row */
    tr.winner-row td { background: rgba(234,179,8,.08); }
    tr.winner-row td:first-child { border-left: 3px solid #fbbf24; }
  </style>
</head>
<body class="bg-[#0a0f1a] text-white">
<?php include __DIR__ . '/../components/admin-sidebar.php'; ?>

<div class="main-content">
  <div class="topbar">
    <button onclick="toggleSidebar()" class="md:hidden text-gray-400 text-2xl mr-3">☰</button>
    <div>
      <a href="<?= APP_URL ?>/admin/draws.php" class="text-orange-400 text-sm hover:underline">← Draws</a>
      <h1 class="text-lg font-bold mt-0.5">Select Winner — <?= e($draw['title']) ?></h1>
    </div>
  </div>

  <div class="p-4 md:p-6 max-w-4xl mx-auto">

    <?php if($err): ?>
    <div class="bg-red-500/10 border border-red-500/30 text-red-400 rounded-xl p-4 mb-5 text-sm">❌ <?= e($err) ?></div>
    <?php endif; ?>

    <!-- Already has winner -->
    <?php if($alreadyHasWinner): ?>
    <div class="card p-8 text-center">
      <div class="text-5xl mb-4">🔒</div>
      <h2 class="text-2xl font-black text-yellow-400 mb-2">Winner Already Selected</h2>
      <p class="text-gray-400 mb-5">A winner has already been locked in for this draw. This cannot be changed.</p>
      <a href="<?= APP_URL ?>/admin/winners.php?draw=<?= $drawId ?>" class="btn btn-primary">🏆 View Winner →</a>
    </div>
    <?php else: ?>

    <!-- Draw info -->
    <div class="card p-5 mb-5" style="border-color:rgba(124,58,237,.2)">
      <div class="flex items-center gap-4 flex-wrap">
        <div class="flex-1 min-w-0">
          <div class="font-bold text-lg"><?= e($draw['title']) ?></div>
          <div class="text-xs text-gray-400 flex flex-wrap gap-4 mt-1">
            <span>📝 <?= number_format($totalEntries) ?> entries</span>
            <span>👥 <?= number_format($totalParticipants) ?> participants</span>
            <span>⏹ Ended <?= date('M j, Y g:i A', strtotime($draw['ended_at'] ?? $draw['end_date'])) ?></span>
          </div>
        </div>
        <span class="badge badge-danger">Ended — Awaiting Winner</span>
      </div>
    </div>

    <!-- Instructions -->
    <div class="rounded-xl p-4 mb-6 flex items-start gap-3" style="background:rgba(249,115,22,.06);border:1px solid rgba(249,115,22,.15)">
      <span class="text-2xl flex-shrink-0">🎰</span>
      <div class="text-sm text-gray-300 leading-relaxed">
        Enter the <strong class="text-white">15-digit winning number</strong> from the physical machine below, one digit at a time.
        The table updates live showing which participants match each position.
        When all 15 digits are entered, the system automatically determines the winner (most matched digits).
        <strong class="text-orange-400">This action is permanent and cannot be undone.</strong>
      </div>
    </div>

    <!-- OTP Input -->
    <div class="card p-6 mb-5">
      <div class="flex items-center gap-2 mb-5">
        <span class="text-xl">🔢</span>
        <h2 class="font-bold text-lg">Enter Winning Number</h2>
        <span id="digit-count-badge" class="ml-auto text-xs px-3 py-1 rounded-full font-semibold"
              style="background:rgba(249,115,22,.1);border:1px solid rgba(249,115,22,.2);color:#f97316">
          0 / 15 digits
        </span>
      </div>

      <!-- 15 OTP boxes split into 3 groups of 5 -->
      <div class="flex flex-wrap gap-2 justify-center mb-4" id="otp-container">
        <?php for($i=0;$i<15;$i++): ?>
        <input type="text" maxlength="1" inputmode="numeric" pattern="[0-9]"
               class="otp-digit" id="otp-<?= $i ?>"
               data-index="<?= $i ?>"
               <?= $i===0?'autofocus':'' ?>>
        <?php if($i===4||$i===9): ?><div class="w-3"></div><?php endif; ?>
        <?php endfor; ?>
      </div>

      <!-- Hidden input to hold complete code -->
      <input type="hidden" id="winning-code-hidden" value="">

      <!-- Progress bar -->
      <div class="w-full bg-white/5 rounded-full h-1.5 mb-3">
        <div id="otp-progress" class="h-1.5 rounded-full transition-all duration-300"
             style="width:0%;background:linear-gradient(90deg,#f97316,#fb923c)"></div>
      </div>
      <div class="text-center text-xs text-gray-500" id="otp-hint">Type the first digit from the machine</div>
    </div>

    <!-- Live results table -->
    <div class="card mb-5">
      <div class="flex items-center justify-between px-5 pt-5 pb-3 border-b border-white/5">
        <div>
          <h3 class="font-bold">Live Participant Matching</h3>
          <div class="text-xs text-gray-500 mt-0.5" id="table-subtitle">Enter digits above to see matching users</div>
        </div>
        <div id="match-legend" class="hidden flex items-center gap-3 text-xs text-gray-500">
          <span><span class="code-slot match inline-flex">0</span> match</span>
          <span><span class="code-slot no-match inline-flex">0</span> no match</span>
          <span><span class="code-slot unchecked inline-flex">0</span> not yet entered</span>
        </div>
      </div>

      <div id="results-table-wrap">
        <!-- Empty state -->
        <div id="empty-state" class="p-10 text-center text-gray-500 text-sm">
          <div class="text-3xl mb-2">⌨️</div>
          Start entering digits above to see participants
        </div>

        <!-- Results (hidden until digits typed) -->
        <div id="results-wrap" class="hidden">
          <table id="results-table" class="w-full">
            <thead>
              <tr>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">#</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Name</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Phone</th>
                <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Best Code</th>
                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Matched</th>
                <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Entries</th>
              </tr>
            </thead>
            <tbody id="results-tbody"></tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Confirm form (shown when all 15 digits entered) -->
    <div id="confirm-section" class="hidden">
      <div class="rounded-xl p-5 mb-4" style="background:rgba(234,179,8,.08);border:2px solid rgba(234,179,8,.3)">
        <div class="flex items-start gap-3">
          <span class="text-2xl flex-shrink-0">⚠️</span>
          <div>
            <div class="font-bold text-yellow-400 mb-1">Ready to Lock in Winner?</div>
            <div class="text-sm text-gray-300 leading-relaxed">
              Winning number: <strong id="confirm-code-display" class="font-mono text-orange-400 text-base"></strong><br>
              Winner: <strong id="confirm-winner-name" class="text-white"></strong>
              (<span id="confirm-matched" class="text-green-400"></span>/15 digits matched)<br><br>
              <strong class="text-red-400">This cannot be changed after confirmation.</strong>
              The winner will be notified, codes will be marked used, and the draw will be completed.
            </div>
          </div>
        </div>
      </div>

      <form method="POST" id="confirm-form" onsubmit="return confirmWinner()">
        <?= csrfField() ?>
        <input type="hidden" name="winning_code"   id="form-winning-code">
        <input type="hidden" name="winner_user_id" id="form-winner-id">
        <button type="submit" id="confirm-btn"
                class="btn btn-primary w-full py-4 text-base font-black"
                style="background:linear-gradient(135deg,#7c3aed,#6d28d9)">
          🏆 Confirm &amp; Lock in Winner
        </button>
      </form>
    </div>

    <?php endif; // !alreadyHasWinner ?>
  </div>
</div>

<script src="<?= APP_URL ?>/assets/js/app.js"></script>
<script>
const TOTAL_DIGITS   = 15;
const otpInputs      = Array.from(document.querySelectorAll('.otp-digit'));
const progressBar    = document.getElementById('otp-progress');
const digitBadge     = document.getElementById('digit-count-badge');
const otpHint        = document.getElementById('otp-hint');
const emptyState     = document.getElementById('empty-state');
const resultsWrap    = document.getElementById('results-wrap');
const resultsTbody   = document.getElementById('results-tbody');
const confirmSection = document.getElementById('confirm-section');
const matchLegend    = document.getElementById('match-legend');
const tableSubtitle  = document.getElementById('table-subtitle');

let currentWinnerId   = null;
let currentWinnerName = '';
let currentMatched    = 0;
let fetchTimer        = null;

// ── OTP navigation ─────────────────────────────────────────
otpInputs.forEach((inp, i) => {
  inp.addEventListener('keydown', e => {
    if (e.key === 'Backspace') {
      e.preventDefault();
      if (inp.value) {
        inp.value = '';
        inp.classList.remove('filled');
      } else if (i > 0) {
        otpInputs[i - 1].focus();
        otpInputs[i - 1].value = '';
        otpInputs[i - 1].classList.remove('filled');
      }
      onDigitsChanged();
    } else if (e.key === 'ArrowLeft' && i > 0) {
      e.preventDefault(); otpInputs[i - 1].focus();
    } else if (e.key === 'ArrowRight' && i < TOTAL_DIGITS - 1) {
      e.preventDefault(); otpInputs[i + 1].focus();
    }
  });

  inp.addEventListener('input', () => {
    // Only allow digits
    inp.value = inp.value.replace(/\D/g, '').slice(-1);
    if (inp.value) {
      inp.classList.add('filled');
      if (i < TOTAL_DIGITS - 1) otpInputs[i + 1].focus();
    } else {
      inp.classList.remove('filled');
    }
    onDigitsChanged();
  });

  // Support paste of full 15-digit number
  inp.addEventListener('paste', e => {
    e.preventDefault();
    const pasted = (e.clipboardData.getData('text') || '').replace(/\D/g, '').slice(0, 15);
    if (pasted.length > 0) {
      pasted.split('').forEach((ch, j) => {
        if (otpInputs[i + j]) {
          otpInputs[i + j].value = ch;
          otpInputs[i + j].classList.add('filled');
        }
      });
      const next = Math.min(i + pasted.length, TOTAL_DIGITS - 1);
      otpInputs[next].focus();
      onDigitsChanged();
    }
  });
});

function getEnteredDigits() {
  return otpInputs.map(inp => inp.value).join('');
}

function onDigitsChanged() {
  const digits = getEnteredDigits();
  const filled = digits.replace(/\s/g, '').length;
  // Count only positions that have a value
  const filledCount = otpInputs.filter(i => i.value !== '').length;

  // Update progress
  const pct = (filledCount / TOTAL_DIGITS) * 100;
  progressBar.style.width = pct + '%';
  digitBadge.textContent  = filledCount + ' / ' + TOTAL_DIGITS + ' digits';

  if (filledCount === 0) {
    otpHint.textContent = 'Type the first digit from the machine';
    emptyState.classList.remove('hidden');
    resultsWrap.classList.add('hidden');
    confirmSection.classList.add('hidden');
    return;
  }

  if (filledCount === TOTAL_DIGITS) {
    otpHint.textContent = 'All 15 digits entered — confirming winner below';
    digitBadge.style.background = 'rgba(34,197,94,.15)';
    digitBadge.style.borderColor = 'rgba(34,197,94,.3)';
    digitBadge.style.color = '#22c55e';
  } else {
    otpHint.textContent = 'Keep entering digits…';
    digitBadge.style.background = '';
    digitBadge.style.borderColor = '';
    digitBadge.style.color = '#f97316';
  }

  // Debounce fetch
  clearTimeout(fetchTimer);
  fetchTimer = setTimeout(() => fetchResults(digits, filledCount), 200);
}

function fetchResults(digits, filledCount) {
  // Only send filled portion
  const partialDigits = digits.slice(0, otpInputs.map((inp,i)=>inp.value?i+1:0).reduce((a,b)=>Math.max(a,b),0));

  fetch('<?= APP_URL ?>/admin/select-winner.php?id=<?= $drawId ?>&ajax=1&digits=' + encodeURIComponent(partialDigits))
    .then(r => r.json())
    .then(data => {
      renderResults(data, filledCount);
    })
    .catch(err => console.error(err));
}

function renderResults(data, filledCount) {
  if (!data.users || data.users.length === 0) {
    emptyState.classList.remove('hidden');
    resultsWrap.classList.add('hidden');
    tableSubtitle.textContent = 'No participants found';
    confirmSection.classList.add('hidden');
    return;
  }

  emptyState.classList.add('hidden');
  resultsWrap.classList.remove('hidden');
  matchLegend.classList.remove('hidden');
  tableSubtitle.textContent = 'Showing top ' + data.users.length + ' of ' + data.total_entered + ' participants — sorted by most digit matches';

  let rows = '';
  data.users.forEach((u, idx) => {
    const isTop = idx === 0;
    const rowClass = isTop && filledCount >= 5 ? 'winner-row' : '';

    // Build code display
    let codeHtml = '';
    u.highlighted.forEach((slot, si) => {
      let cls = 'unchecked';
      if (slot.checked) cls = slot.match ? 'match' : 'no-match';
      codeHtml += `<span class="code-slot ${cls}">${slot.digit}</span>`;
      if (si === 4 || si === 9) codeHtml += '<span style="width:4px;display:inline-block"></span>';
    });

    const matchColor = u.matched >= 10 ? '#22c55e' : u.matched >= 5 ? '#fbbf24' : '#9ca3af';

    rows += `<tr class="${rowClass}" style="border-bottom:1px solid rgba(255,255,255,.05)">
      <td class="px-4 py-3 text-sm text-gray-500">${idx === 0 && filledCount >= 5 ? '🏆' : (idx + 1)}</td>
      <td class="px-4 py-3">
        <div class="font-semibold text-sm ${isTop && filledCount >= 5 ? 'text-yellow-400' : 'text-white'}">${escHtml(u.full_name)}</div>
      </td>
      <td class="px-4 py-3 text-sm font-mono text-gray-400">${escHtml(u.phone)}</td>
      <td class="px-4 py-3"><div class="flex flex-wrap gap-0.5">${codeHtml}</div></td>
      <td class="px-4 py-3 text-right">
        <span class="font-black text-base" style="color:${matchColor}">${u.matched}/${data.digits_entered}</span>
      </td>
      <td class="px-4 py-3 text-right text-sm text-gray-400">${u.entry_count}</td>
    </tr>`;
  });
  resultsTbody.innerHTML = rows;

  // Update confirm section when all 15 digits entered
  if (filledCount === TOTAL_DIGITS && data.users.length > 0) {
    const winner = data.users[0];
    currentWinnerId   = winner.user_id;
    currentWinnerName = winner.full_name;
    currentMatched    = winner.matched;

    const code = otpInputs.map(i => i.value).join('');
    document.getElementById('form-winning-code').value   = code;
    document.getElementById('form-winner-id').value      = winner.user_id;
    document.getElementById('confirm-code-display').textContent = code.replace(/(\d{5})(\d{5})(\d{5})/, '$1-$2-$3');
    document.getElementById('confirm-winner-name').textContent  = winner.full_name;
    document.getElementById('confirm-matched').textContent      = winner.matched;
    document.getElementById('winning-code-hidden').value = code;

    // Mark all OTP inputs as confirmed
    otpInputs.forEach(i => i.classList.add('confirmed'));

    confirmSection.classList.remove('hidden');
    confirmSection.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  } else {
    confirmSection.classList.add('hidden');
    otpInputs.forEach(i => i.classList.remove('confirmed'));
  }
}

function confirmWinner() {
  if (!currentWinnerId) { alert('Please enter all 15 digits first.'); return false; }
  const code = otpInputs.map(i => i.value).join('');
  const msg  = `⚠️ FINAL CONFIRMATION\n\nWinning number: ${code}\nWinner: ${currentWinnerName}\nMatched: ${currentMatched}/15 digits\n\nThis CANNOT be changed after confirmation.\nAre you absolutely sure?`;
  if (!confirm(msg)) return false;
  document.getElementById('confirm-btn').textContent = '⏳ Processing…';
  document.getElementById('confirm-btn').disabled = true;
  return true;
}

function escHtml(str) {
  return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
</script>
</body>
</html>

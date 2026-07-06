<?php
// admin/draw-manage.php
// Admin views draw details and results.
// NO pause/resume/activate controls — all handled by cron.
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
$auth = requireAdmin(); $adminId = $auth['id'];
$db   = getDB();

$drawId = (int)($_GET['id'] ?? 0);
if (!$drawId) redirect(APP_URL . '/admin/draws.php');

$draw = $db->prepare("SELECT * FROM draws WHERE id=?");
$draw->execute([$drawId]); $draw = $draw->fetch();
if (!$draw) redirect(APP_URL . '/admin/draws.php');

$msg = $err = '';

// Only allowed POST action: cancel
if (isPost() && verifyCsrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
    $action = $_POST['action'] ?? '';
    if ($action === 'cancel' && in_array($draw['status'], ['pending','active'])) {
        $db->prepare("UPDATE draws SET status='cancelled',updated_at=NOW() WHERE id=?")
           ->execute([$drawId]);
        auditLog('admin',$adminId,'cancel_draw',"Draw #$drawId cancelled",'draw',$drawId);
        redirect(APP_URL."/admin/draw-manage.php?id=$drawId&done=cancelled");
    }
}

if (isset($_GET['done'])) $msg = ucfirst($_GET['done']).' successfully.';

// ── Stats ──────────────────────────────────────────────────
$totalEntries = (int)$db->prepare("SELECT COUNT(*) FROM draw_entries WHERE draw_id=?")->execute([$drawId]) ? 0 : 0;
$s = $db->prepare("SELECT COUNT(*) FROM draw_entries WHERE draw_id=?");
$s->execute([$drawId]); $totalEntries = (int)$s->fetchColumn();

$s = $db->prepare("SELECT COUNT(DISTINCT user_id) FROM draw_entries WHERE draw_id=?");
$s->execute([$drawId]); $uniqueUsers = (int)$s->fetchColumn();

// Entry breakdown: how many users entered with how many codes
$topEntrants = $db->prepare(
    "SELECT u.full_name, u.phone, COUNT(de.id) AS code_count
     FROM draw_entries de JOIN users u ON de.user_id=u.id
     WHERE de.draw_id=?
     GROUP BY de.user_id ORDER BY code_count DESC LIMIT 10"
);
$topEntrants->execute([$drawId]); $topEntrants = $topEntrants->fetchAll();

// Existing winner (if already finalized)
$existingWinner = $db->prepare(
    "SELECT dw.*, u.full_name, u.phone
     FROM draw_winners dw JOIN users u ON dw.user_id=u.id
     WHERE dw.draw_id=?"
);
$existingWinner->execute([$drawId]); $existingWinner = $existingWinner->fetch();

// Official top 3 from draw_rankings
$top3Official = null;
if ($existingWinner) {
    $rk = $db->prepare(
        "SELECT dr.*, u.full_name, u.phone
         FROM draw_rankings dr JOIN users u ON dr.user_id=u.id
         WHERE dr.draw_id=? ORDER BY dr.rank_position ASC"
    );
    $rk->execute([$drawId]);
    $top3Official = $rk->fetchAll();
}

// Recent entries
$recentEntries = $db->prepare(
    "SELECT u.full_name, u.phone, c.code, de.entered_at
     FROM draw_entries de
     JOIN users u ON de.user_id = u.id
     JOIN codes c ON de.code_id = c.id
     WHERE de.draw_id=? ORDER BY de.entered_at DESC LIMIT 10"
);
$recentEntries->execute([$drawId]); $recentEntries = $recentEntries->fetchAll();

// Time calculations
$now        = time();
$startTime  = strtotime($draw['start_date']);
$endTime    = strtotime($draw['end_date']);
$hasEnded   = $endTime <= $now;
$hasStarted = $startTime <= $now;
$timeToEnd  = $endTime - $now;
$timeToStart = $startTime - $now;

$aPage = 'draws';
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="csrf-token" content="<?= generateCsrf() ?>">
  <title>Draw Details — <?= APP_NAME ?> Admin</title>
  <script src="<?= APP_URL ?>/assets/js/tailwind.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
  <style>
    * { font-family: 'Poppins', sans-serif !important; }
    code { font-family: 'Courier New', monospace !important; }

    .digit-slot {
      display: inline-flex; align-items: center; justify-content: center;
      width: 36px; height: 44px; border-radius: 8px;
      font-size: 20px; font-weight: 900;
      font-family: 'Courier New', monospace;
      border: 2px solid rgba(255,255,255,.1);
      background: rgba(255,255,255,.04);
      transition: all .4s;
    }
    .digit-slot.match {
      background: rgba(34,197,94,.2);
      border-color: #22c55e; color: #22c55e;
    }
    .digit-slot.no-match {
      background: rgba(239,68,68,.08);
      border-color: rgba(239,68,68,.25); color: #9ca3af;
    }

    /* Live countdown */
    .countdown-ring {
      background: rgba(6,182,212,.08);
      border: 1px solid rgba(6,182,212,.2);
      border-radius: 12px; padding: .5rem 1rem;
      font-size: .8rem; color: #67e8f9;
      font-weight: 700; font-variant-numeric: tabular-nums;
    }
  </style>
</head>
<body class="bg-[#0a0f1a] text-white">
<?php include __DIR__ . '/../components/admin-sidebar.php'; ?>

<div class="main-content">
  <div class="topbar">
    <button onclick="toggleSidebar()" class="md:hidden text-gray-400 text-2xl mr-3">☰</button>
    <div>
      <a href="<?= APP_URL ?>/admin/draws.php" class="text-orange-400 text-sm hover:underline">← All Draws</a>
      <h1 class="text-lg font-bold mt-0.5"><?= e($draw['title']) ?></h1>
    </div>
    <div class="flex items-center gap-2 flex-wrap">
      <span class="badge <?= match($draw['status']) {
        'active'    => 'badge-success',
        'pending'   => 'badge-warning',
        'completed' => 'badge-muted',
        'cancelled' => 'badge-danger',
        default     => 'badge-muted'
      } ?>"><?= ucfirst($draw['status']) ?></span>
      <?php if ($draw['status'] === 'active'): ?>
      <span class="countdown-ring" id="countdown-display">Loading…</span>
      <?php endif; ?>
    </div>
  </div>

  <div class="p-4 md:p-6 max-w-4xl mx-auto">

    <?php if ($msg): ?>
    <div class="bg-green-500/10 border border-green-500/30 text-green-400 rounded-xl p-4 mb-5 text-sm">✅ <?= e($msg) ?></div>
    <?php endif; ?>
    <?php if ($err): ?>
    <div class="bg-red-500/10 border border-red-500/30 text-red-400 rounded-xl p-4 mb-5 text-sm">❌ <?= e($err) ?></div>
    <?php endif; ?>

    <!-- ── Cron status notices ──────────────────────────── -->
    <?php if ($draw['status'] === 'pending' && !$hasStarted): ?>
    <div class="rounded-xl p-4 mb-5 flex items-center gap-3 text-sm"
         style="background:rgba(234,179,8,.06);border:1px solid rgba(234,179,8,.2)">
      <span class="text-2xl">⏳</span>
      <div>
        <div class="font-semibold text-yellow-400">Awaiting Auto-Activation</div>
        <div class="text-gray-400">
          The cron job will automatically set this draw to <strong class="text-white">Active</strong>
          on <strong class="text-white"><?= date('M j, Y \a\t g:i A', $startTime) ?></strong>.
          <?php if ($timeToStart > 0): $d=floor($timeToStart/86400);$h=floor(($timeToStart%86400)/3600);$m=floor(($timeToStart%3600)/60); ?>
          That's in <?= $d>0?"{$d}d ":'' ?><?= "{$h}h {$m}m" ?>.
          <?php endif; ?>
        </div>
      </div>
    </div>

    <?php elseif ($draw['status'] === 'active' && !$hasEnded): ?>
    <div class="rounded-xl p-4 mb-5 flex items-center gap-3 text-sm"
         style="background:rgba(34,197,94,.06);border:1px solid rgba(34,197,94,.2)">
      <span class="text-2xl flex-shrink-0">🟢</span>
      <div>
        <div class="font-semibold text-green-400">Draw is Live — Running Automatically</div>
        <div class="text-gray-400">
          Users can enter now. The cron job will auto-finalize and select a winner on
          <strong class="text-white"><?= date('M j, Y \a\t g:i A', $endTime) ?></strong>.
        </div>
      </div>
    </div>

    <?php elseif (in_array($draw['status'],['active','pending']) && $hasEnded): ?>
    <div class="rounded-xl p-4 mb-5 flex items-center gap-3 text-sm"
         style="background:rgba(6,182,212,.06);border:1px solid rgba(6,182,212,.2)">
      <span class="text-2xl flex-shrink-0">⏳</span>
      <div>
        <div class="font-semibold text-cyan-400">Draw Has Ended — Awaiting Cron Finalization</div>
        <div class="text-gray-400">
          The end date has passed. The cron job will finalize this draw and select a winner on its next run (every minute).
        </div>
      </div>
    </div>

    <?php elseif ($draw['status'] === 'completed'): ?>
    <div class="rounded-xl p-4 mb-5 flex items-center gap-3 text-sm"
         style="background:rgba(34,197,94,.06);border:1px solid rgba(34,197,94,.2)">
      <span class="text-2xl flex-shrink-0">✅</span>
      <div class="font-semibold text-green-400">Draw Completed — Winner Selected Automatically</div>
    </div>

    <?php elseif ($draw['status'] === 'cancelled'): ?>
    <div class="rounded-xl p-4 mb-5 flex items-center gap-3 text-sm"
         style="background:rgba(239,68,68,.06);border:1px solid rgba(239,68,68,.2)">
      <span class="text-2xl flex-shrink-0">❌</span>
      <div class="font-semibold text-red-400">Draw Cancelled</div>
    </div>
    <?php endif; ?>

    <!-- ── Stats row ────────────────────────────────────── -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-5">
      <?php foreach ([
        [number_format($totalEntries),  'Total Entries',       'text-orange-400'],
        [number_format($uniqueUsers),   'Unique Participants', 'text-blue-400'],
        [date('M j, Y g:i A', $startTime), 'Start Date',      'text-gray-300'],
        [date('M j, Y g:i A', $endTime),   'End Date',        'text-gray-300'],
      ] as $s): ?>
      <div class="card p-4 text-center">
        <div class="text-lg font-black <?= $s[2] ?>"><?= $s[0] ?></div>
        <div class="text-xs text-gray-500 mt-1"><?= $s[1] ?></div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- ── WINNER RESULT ────────────────────────────────── -->
    <?php if ($existingWinner): ?>
    <div class="card p-6 mb-5"
         style="border-color:rgba(234,179,8,.4);background:linear-gradient(135deg,rgba(234,179,8,.06),rgba(0,0,0,0))">
      <div class="flex items-center gap-2 mb-5">
        <div class="text-3xl">🏆</div>
        <div>
          <h2 class="font-bold text-xl text-yellow-400">Winner Selected Automatically</h2>
          <div class="text-xs text-gray-400">
            Finalized by cron on <?= date('M j, Y g:i A', strtotime($existingWinner['announced_at'])) ?>
          </div>
        </div>
      </div>

      <!-- Winning code -->
      <div class="mb-4">
        <div class="text-sm text-gray-400 mb-2">Generated Winning Code</div>
        <div class="flex flex-wrap gap-1.5">
          <?php for ($i = 0; $i < 15; $i++): ?>
          <div class="digit-slot <?= isset($existingWinner['user_code'][$i]) && $existingWinner['user_code'][$i] === $existingWinner['winning_code'][$i] ? 'match' : 'no-match' ?>">
            <?= $existingWinner['winning_code'][$i] ?>
          </div>
          <?php endfor; ?>
        </div>
        <div class="text-xs text-gray-500 mt-1">🟢 matched &nbsp; 🔴 no match</div>
      </div>

      <!-- Winner's best code -->
      <?php if (!empty($existingWinner['user_code'])): ?>
      <div class="mb-4">
        <div class="text-sm text-gray-400 mb-2">
          Winner's Best Code
          <span class="text-yellow-400 font-semibold ml-1">(<?= $existingWinner['matched_digits'] ?>/15 matched)</span>
        </div>
        <div class="flex flex-wrap gap-1.5">
          <?php for ($i = 0; $i < 15; $i++): ?>
          <div class="digit-slot <?= isset($existingWinner['user_code'][$i]) && $existingWinner['user_code'][$i] === $existingWinner['winning_code'][$i] ? 'match' : 'no-match' ?>">
            <?= $existingWinner['user_code'][$i] ?? '?' ?>
          </div>
          <?php endfor; ?>
        </div>
      </div>
      <?php endif; ?>

      <!-- Winner identity card -->
      <div class="p-4 rounded-xl flex items-center gap-4"
           style="background:rgba(234,179,8,.08);border:1px solid rgba(234,179,8,.2)">
        <div class="w-12 h-12 bg-yellow-500/20 rounded-full flex items-center justify-center text-2xl font-black text-yellow-400">
          <?= strtoupper($existingWinner['full_name'][0]) ?>
        </div>
        <div class="flex-1 min-w-0">
          <div class="font-bold text-yellow-400"><?= e($existingWinner['full_name']) ?></div>
          <div class="text-sm text-gray-400"><?= e(formatPhone($existingWinner['phone'])) ?></div>
          <?php if ($existingWinner['tiebreaker_used']): ?>
          <div class="text-xs text-orange-300 mt-1">
            Tiebreaker: <?= e(str_replace('_',' ', $existingWinner['tiebreaker_used'])) ?>
          </div>
          <?php endif; ?>
        </div>
        <div class="text-right flex-shrink-0">
          <div class="text-xs text-gray-500">User ID</div>
          <div class="font-mono text-sm text-white">#<?= $existingWinner['user_id'] ?></div>
        </div>
      </div>
    </div>

    <!-- Top 3 podium -->
    <?php if ($top3Official): ?>
    <div class="card mb-5">
      <div class="px-5 pt-5 pb-3 border-b border-white/5 font-bold flex items-center gap-2">
        🏅 Top 3 Finishers
        <span class="text-xs font-normal text-gray-500">— automatically ranked at finalization</span>
      </div>
      <div class="grid sm:grid-cols-3 gap-0 divide-y sm:divide-y-0 sm:divide-x divide-white/5">
        <?php $rs=[1=>['🥇','text-yellow-400','bg-yellow-500/5'],2=>['🥈','text-gray-300','bg-white/2'],3=>['🥉','text-orange-300','bg-orange-900/5']]; ?>
        <?php foreach ($top3Official as $r): $rsi=$rs[$r['rank_position']]??$rs[3]; ?>
        <div class="p-5 text-center <?= $rsi[2] ?>">
          <div class="text-3xl mb-2"><?= $rsi[0] ?></div>
          <div class="font-bold <?= $rsi[1] ?> mb-0.5 text-sm"><?= e($r['full_name']) ?></div>
          <div class="text-xs text-gray-500 mb-2"><?= e(formatPhone($r['phone'])) ?></div>
          <code class="text-xs text-orange-400 block mb-1"><?= e($r['user_code']) ?></code>
          <div class="text-sm font-bold <?= $r['matched_digits']>=10?'text-green-400':($r['matched_digits']>=5?'text-yellow-400':'text-gray-400') ?>">
            <?= $r['matched_digits'] ?>/15
          </div>
          <div class="text-xs text-gray-600 mt-1"><?= $r['entries_count'] ?? '?' ?> entries</div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php else: ?>

    <!-- ── How the auto-finalization works ──────────────── -->
    <div class="card p-5 mb-5" style="border-color:rgba(6,182,212,.2)">
      <h3 class="font-bold mb-3 flex items-center gap-2">
        <span class="text-cyan-400">⚙️</span> How Automatic Finalization Works
      </h3>
      <div class="grid sm:grid-cols-3 gap-3 text-sm">
        <div class="bg-white/3 rounded-xl p-4">
          <div class="text-orange-400 font-semibold mb-1 text-xs">① GENERATE</div>
          <div class="text-gray-400 text-xs">Cron generates a cryptographically random 15-digit winning code</div>
        </div>
        <div class="bg-white/3 rounded-xl p-4">
          <div class="text-orange-400 font-semibold mb-1 text-xs">② SCORE</div>
          <div class="text-gray-400 text-xs">Every entered code is scored digit-by-digit against the winning code</div>
        </div>
        <div class="bg-white/3 rounded-xl p-4">
          <div class="text-orange-400 font-semibold mb-1 text-xs">③ SELECT</div>
          <div class="text-gray-400 text-xs">Most matched digits wins. Tiebreakers: most entries → earliest registration</div>
        </div>
      </div>
      <?php if ($totalEntries === 0): ?>
      <div class="mt-3 p-3 rounded-lg text-xs text-yellow-300" style="background:rgba(234,179,8,.08);border:1px solid rgba(234,179,8,.15)">
        ⚠️ No entries yet. If no codes are entered before the end date, admin will be notified and the draw will be flagged for review.
      </div>
      <?php endif; ?>
    </div>

    <?php endif; ?>

    <!-- ── Cancel button (only for active/pending) ──────── -->
    <?php if (in_array($draw['status'], ['pending','active'])): ?>
    <div class="card p-5 mb-5" style="border-color:rgba(239,68,68,.2)">
      <h3 class="font-bold text-sm mb-2 text-red-400">⚠️ Cancel Draw</h3>
      <p class="text-gray-400 text-xs mb-4">
        Cancelling stops this draw permanently. Entered codes will remain in participants' wallets
        but will not be marked as used. This cannot be undone.
      </p>
      <form method="POST" onsubmit="return confirm('Cancel this draw permanently? This cannot be undone.')">
        <?= csrfField() ?>
        <input type="hidden" name="action" value="cancel">
        <button class="btn btn-secondary text-xs text-red-400 px-6 py-2">✕ Cancel This Draw</button>
      </form>
    </div>
    <?php endif; ?>

    <!-- ── Top entrants ─────────────────────────────────── -->
    <?php if ($topEntrants): ?>
    <div class="card mb-5">
      <div class="px-5 pt-5 pb-3 border-b border-white/5 font-bold text-sm">
        📊 Top Participants by Entry Count
      </div>
      <div class="divide-y divide-white/5">
        <?php foreach ($topEntrants as $i => $te): ?>
        <div class="flex items-center gap-3 px-5 py-3">
          <div class="w-7 h-7 rounded-lg flex items-center justify-center text-xs font-black flex-shrink-0
                <?= $i===0?'bg-yellow-500/20 text-yellow-400':($i===1?'bg-gray-400/20 text-gray-300':'bg-white/5 text-gray-500') ?>">
            <?= $i+1 ?>
          </div>
          <div class="flex-1 min-w-0">
            <div class="text-sm font-medium truncate"><?= e($te['full_name']) ?></div>
            <div class="text-xs text-gray-500"><?= e(formatPhone($te['phone'])) ?></div>
          </div>
          <div class="text-right flex-shrink-0">
            <div class="font-bold text-orange-400 text-sm"><?= $te['code_count'] ?></div>
            <div class="text-xs text-gray-600">entries</div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- ── Recent entries ───────────────────────────────── -->
    <?php if ($recentEntries && $draw['status'] !== 'completed'): ?>
    <div class="card">
      <div class="px-5 pt-5 pb-3 border-b border-white/5 font-bold text-sm">🕐 Recent Entries</div>
      <div class="divide-y divide-white/5">
        <?php foreach ($recentEntries as $re): ?>
        <div class="flex items-center gap-3 px-5 py-3">
          <div class="w-8 h-8 bg-orange-500/15 rounded-lg flex items-center justify-center font-bold text-orange-400 text-sm flex-shrink-0">
            <?= strtoupper($re['full_name'][0]) ?>
          </div>
          <div class="flex-1 min-w-0">
            <div class="text-sm font-medium truncate"><?= e($re['full_name']) ?></div>
            <code class="text-xs text-orange-400"><?= e($re['code']) ?></code>
          </div>
          <div class="text-xs text-gray-500 flex-shrink-0">
            <?= date('M j, g:ia', strtotime($re['entered_at'])) ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

  </div>
</div>
<script>
  window.APP_URL = '<?= APP_URL ?>';
</script>
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
<script>
// ── Live countdown for active draws ────────────────────────
const endTime = <?= $endTime * 1000 ?>; // JS uses ms
const el = document.getElementById('countdown-display');

function updateCountdown() {
  if (!el) return;
  const diff = Math.max(0, Math.floor((endTime - Date.now()) / 1000));
  if (diff === 0) { el.textContent = 'Ends soon — awaiting cron'; return; }
  const d = Math.floor(diff / 86400);
  const h = Math.floor((diff % 86400) / 3600);
  const m = Math.floor((diff % 3600) / 60);
  const s = diff % 60;
  el.textContent = (d > 0 ? d+'d ' : '') + String(h).padStart(2,'0')+'h '
                 + String(m).padStart(2,'0')+'m ' + String(s).padStart(2,'0')+'s remaining';
}

if (el) { updateCountdown(); setInterval(updateCountdown, 1000); }
</script>
</body>
</html>
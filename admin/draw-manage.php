<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
$auth = requireAdmin(); $adminId = $auth['id'];
$db   = getDB();

$drawId = (int)($_GET['id'] ?? 0);
if (!$drawId) redirect(APP_URL.'/admin/draws.php');

$draw = $db->prepare("SELECT * FROM draws WHERE id=?");
$draw->execute([$drawId]); $draw = $draw->fetch();
if (!$draw) redirect(APP_URL.'/admin/draws.php');

// Quick actions (status changes)
$msg = $err = '';
if (isPost() && verifyCsrf($_POST[CSRF_TOKEN_NAME]??'')) {
  $action = $_POST['action'] ?? '';
  if (in_array($action, ['pause','activate','cancel'])) {
    $map = ['pause'=>'paused','activate'=>'active','cancel'=>'cancelled'];
    $db->prepare("UPDATE draws SET status=?, updated_at=NOW() WHERE id=?")->execute([$map[$action],$drawId]);
    auditLog('admin',$adminId,$action.'_draw',"Draw #$drawId → ".$map[$action],'draw',$drawId);
    redirect(APP_URL."/admin/draw-manage.php?id=$drawId&done=$action");
  }
}
if (isset($_GET['done'])) $msg = ucfirst($_GET['done']).' successful.';

// Stats
$totalEntries = $db->prepare("SELECT COUNT(*) FROM draw_entries WHERE draw_id=?");
$totalEntries->execute([$drawId]); $totalEntries = (int)$totalEntries->fetchColumn();

$uniqueUsers = $db->prepare("SELECT COUNT(DISTINCT user_id) FROM draw_entries WHERE draw_id=?");
$uniqueUsers->execute([$drawId]); $uniqueUsers = (int)$uniqueUsers->fetchColumn();

// Existing winner (if already finalized)
$existingWinner = $db->prepare(
  "SELECT dw.*, u.full_name, u.phone FROM draw_winners dw JOIN users u ON dw.user_id=u.id WHERE dw.draw_id=?"
);
$existingWinner->execute([$drawId]); $existingWinner = $existingWinner->fetch();

// Top 3 official ranking (from draw_rankings table, saved at finalization time)
$top3Official = null;
if ($existingWinner) {
  $rk = $db->prepare(
    "SELECT dr.*, u.full_name, u.phone
     FROM draw_rankings dr JOIN users u ON dr.user_id=u.id
     WHERE dr.draw_id=? ORDER BY dr.rank_position ASC"
  );
  $rk->execute([$drawId]); $top3Official = $rk->fetchAll();
}

// Recent entries (for display)
$recentEntries = $db->prepare(
  "SELECT u.full_name, u.phone, c.code, de.entered_at
   FROM draw_entries de JOIN users u ON de.user_id=u.id JOIN codes c ON de.code_id=c.id
   WHERE de.draw_id=? ORDER BY de.entered_at DESC LIMIT 10"
);
$recentEntries->execute([$drawId]); $recentEntries=$recentEntries->fetchAll();

$aPage = 'draws';
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="csrf-token" content="<?= generateCsrf() ?>">
  <title>Manage Draw — <?= APP_NAME ?> Admin</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
  <style>
    *{font-family:'Poppins',sans-serif!important}
    code{font-family:'Courier New',monospace!important}
    .digit-slot{display:inline-flex;align-items:center;justify-content:center;width:36px;height:44px;border-radius:8px;font-size:20px;font-weight:900;font-family:'Courier New',monospace;border:2px solid rgba(255,255,255,.1);background:rgba(255,255,255,.04);transition:all .4s;}
    .digit-slot.match{background:rgba(34,197,94,.2);border-color:#22c55e;color:#22c55e;}
    .digit-slot.no-match{background:rgba(239,68,68,.1);border-color:rgba(239,68,68,.3);color:#9ca3af;}
    .digit-slot.reveal{animation:revealFlip .5s ease forwards;}
    @keyframes revealFlip{0%{transform:rotateY(90deg);opacity:0}100%{transform:rotateY(0);opacity:1}}
  </style>
</head>
<body class="bg-[#0a0f1a] text-white">
<?php include __DIR__ . '/../components/admin-sidebar.php'; ?>
<div class="main-content">
  <div class="topbar">
    <button onclick="toggleSidebar()" class="md:hidden text-gray-400 text-2xl mr-3">☰</button>
    <div>
      <a href="<?= APP_URL ?>/admin/draws.php" class="text-orange-400 text-sm hover:underline">← Draws</a>
      <h1 class="text-lg font-bold mt-0.5"><?= e($draw['title']) ?></h1>
    </div>
    <span class="badge <?= match($draw['status']){'active'=>'badge-success','paused'=>'badge-warning','completed'=>'badge-info','cancelled'=>'badge-danger',default=>'badge-muted'} ?>">
      <?= ucfirst($draw['status']) ?>
    </span>
  </div>

  <div class="p-4 md:p-6 max-w-4xl mx-auto">
    <?php if($msg): ?><div class="bg-green-500/10 border border-green-500/30 text-green-400 rounded-xl p-4 mb-5 text-sm"><?= e($msg) ?></div><?php endif; ?>
    <?php if($err): ?><div class="bg-red-500/10 border border-red-500/30 text-red-400 rounded-xl p-4 mb-5 text-sm"><?= e($err) ?></div><?php endif; ?>

    <!-- Stats row -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-5">
      <?php foreach([
        [number_format($totalEntries),'Total Entries','text-orange-400'],
        [number_format($uniqueUsers),'Unique Participants','text-blue-400'],
        [date('M j, Y',strtotime($draw['start_date'])),'Start Date','text-gray-400'],
        [date('M j, Y',strtotime($draw['end_date'])),'End Date','text-gray-400'],
      ] as $s): ?>
      <div class="card p-4 text-center">
        <div class="text-xl font-black <?= $s[2] ?>"><?= $s[0] ?></div>
        <div class="text-xs text-gray-500 mt-1"><?= $s[1] ?></div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- ── WINNER RESULT (shown after finalization) ─────── -->
    <?php if ($existingWinner): ?>
    <div class="card p-6 mb-5" style="border-color:rgba(234,179,8,.4);background:linear-gradient(135deg,rgba(234,179,8,.08),var(--bg-card))">
      <div class="flex items-center gap-2 mb-5">
        <div class="text-3xl">🏆</div>
        <div>
          <h2 class="font-bold text-xl text-yellow-400">Draw Finalized — Winner Selected</h2>
          <div class="text-xs text-gray-400">Announced <?= date('M j, Y g:i A',strtotime($existingWinner['announced_at'])) ?></div>
        </div>
      </div>

      <!-- Winning code display -->
      <div class="mb-5">
        <div class="text-sm text-gray-400 mb-2">Generated Winning Code</div>
        <div class="flex flex-wrap gap-1.5">
          <?php for($i=0;$i<15;$i++): ?>
          <div class="digit-slot <?= $existingWinner['user_code'][$i]===$existingWinner['winning_code'][$i]?'match':'no-match' ?>">
            <?= $existingWinner['winning_code'][$i] ?>
          </div>
          <?php endfor; ?>
        </div>
        <div class="text-xs text-gray-500 mt-2">🟢 = digit matched in user's winning code &nbsp; 🔴 = no match</div>
      </div>

      <!-- User's best code -->
      <div class="mb-5">
        <div class="text-sm text-gray-400 mb-2">Winner's Best Code <span class="text-yellow-400">(<?= $existingWinner['matched_digits'] ?>/15 digits matched)</span></div>
        <div class="flex flex-wrap gap-1.5">
          <?php for($i=0;$i<15;$i++): ?>
          <div class="digit-slot <?= $existingWinner['user_code'][$i]===$existingWinner['winning_code'][$i]?'match':'no-match' ?>">
            <?= $existingWinner['user_code'][$i] ?>
          </div>
          <?php endfor; ?>
        </div>
      </div>

      <!-- Winner identity -->
      <div class="p-4 bg-yellow-500/10 border border-yellow-500/20 rounded-xl flex items-center gap-4">
        <div class="w-12 h-12 bg-yellow-500/20 rounded-full flex items-center justify-center text-2xl font-black text-yellow-400">
          <?= strtoupper($existingWinner['full_name'][0]) ?>
        </div>
        <div>
          <div class="font-bold text-yellow-400"><?= e($existingWinner['full_name']) ?></div>
          <div class="text-sm text-gray-400"><?= e(formatPhone($existingWinner['phone'])) ?></div>
          <?php if($existingWinner['tiebreaker_used']): ?>
          <div class="text-xs text-orange-300 mt-1">Tiebreaker used: <?= e(str_replace('_',' ',$existingWinner['tiebreaker_used'])) ?></div>
          <?php endif; ?>
        </div>
        <div class="ml-auto text-right">
          <div class="text-xs text-gray-500">User ID</div>
          <div class="font-mono text-sm">#<?= $existingWinner['user_id'] ?></div>
        </div>
      </div>
    </div>

    <!-- Official Top 3 Podium -->
    <?php if($top3Official): ?>
    <div class="card mb-5">
      <div class="px-5 pt-5 pb-3 border-b border-white/5 font-bold">🏅 Top 3 Finishers</div>
      <div class="grid sm:grid-cols-3 gap-0 divide-y sm:divide-y-0 sm:divide-x divide-white/5">
        <?php
        $rankStyle = [
          1 => ['🥇','text-yellow-400','border-yellow-500/30','bg-yellow-500/5'],
          2 => ['🥈','text-gray-300','border-gray-400/30','bg-white/3'],
          3 => ['🥉','text-orange-300','border-orange-700/30','bg-orange-900/5'],
        ];
        foreach($top3Official as $r): $rs = $rankStyle[$r['rank_position']]; ?>
        <div class="p-5 text-center <?= $rs[3] ?>">
          <div class="text-4xl mb-2"><?= $rs[0] ?></div>
          <div class="font-bold <?= $rs[1] ?> mb-0.5"><?= e($r['full_name']) ?></div>
          <div class="text-xs text-gray-500 mb-3"><?= e(formatPhone($r['phone'])) ?></div>
          <code class="text-xs text-orange-400 block mb-2"><?= e($r['user_code']) ?></code>
          <div class="text-sm">
            <span class="font-bold <?= $r['matched_digits']>=10?'text-green-400':($r['matched_digits']>=5?'text-yellow-400':'text-gray-400') ?>"><?= $r['matched_digits'] ?>/15</span>
            <span class="text-gray-500"> matched</span>
          </div>
          <div class="text-xs text-gray-600 mt-1"><?= $r['entries_count'] ?> entries</div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <!-- ── AUTO-FINALIZE NOTICE (shown when draw has ended but cron hasn't run yet) ── -->
    <?php if (!$existingWinner && in_array($draw['status'],['active','paused']) && strtotime($draw['end_date']) <= time()): ?>
    <div class="card p-5 mb-5 border-cyan-500/30" style="background:linear-gradient(135deg,rgba(6,182,212,.08),var(--bg-card))">
      <div class="flex items-center gap-3">
        <div class="text-2xl">⏳</div>
        <div>
          <div class="font-bold text-cyan-400">This draw has ended and is awaiting automatic finalization</div>
          <div class="text-xs text-gray-400 mt-0.5">The cron job runs every minute and will generate the winning code automatically. You can also finalize it manually right now below.</div>
        </div>
      </div>
    </div>
    <?php endif; ?>

    <?php if (!$existingWinner): ?>
    <div class="card p-6 mb-5 border-orange-500/30">
      <div class="flex items-start gap-4 mb-5">
        <div class="text-3xl flex-shrink-0">🎲</div>
        <div>
          <h2 class="font-bold text-xl mb-1">Finalize This Draw</h2>
          <p class="text-gray-400 text-sm leading-relaxed">
            Finalizing will <strong class="text-white">generate a random 15-digit winning code</strong>, then automatically find the participant whose entered code has the <strong class="text-white">most matching digits</strong> at the same position. Tiebreakers: most entries → earliest registration.
          </p>
        </div>
      </div>

      <!-- How it works -->
      <div class="grid sm:grid-cols-3 gap-3 mb-5 text-sm">
        <div class="bg-white/3 rounded-xl p-4">
          <div class="text-orange-400 font-semibold mb-1">① Generate</div>
          <div class="text-gray-400 text-xs">System generates a random 15-digit winning code</div>
        </div>
        <div class="bg-white/3 rounded-xl p-4">
          <div class="text-orange-400 font-semibold mb-1">② Compare</div>
          <div class="text-gray-400 text-xs">Each user's codes are scored digit-by-digit against the winning code</div>
        </div>
        <div class="bg-white/3 rounded-xl p-4">
          <div class="text-orange-400 font-semibold mb-1">③ Select</div>
          <div class="text-gray-400 text-xs">User with most matching digits wins. Tiebreakers applied if needed</div>
        </div>
      </div>

      <div class="bg-red-500/10 border border-red-500/20 rounded-xl p-4 mb-5 text-sm text-red-300">
        ⚠️ This action is <strong>irreversible</strong>. All entered codes will be marked as used. The draw will be marked as completed and a winner will be recorded.
      </div>

      <div class="flex items-center gap-3 flex-wrap">
        <button id="finalize-btn" onclick="finalizeDrawPrompt()"
                class="btn px-8 py-3 font-bold text-white text-base"
                style="background:linear-gradient(135deg,#f97316,#ea580c);box-shadow:0 0 20px rgba(249,115,22,.4)">
          🎲 Generate Winning Code &amp; Select Winner
        </button>
        <form method="POST" class="inline">
          <?= csrfField() ?>
          <?php if($draw['status']==='active'): ?>
          <input type="hidden" name="action" value="pause">
          <button class="btn btn-secondary px-5 py-3 text-yellow-400">⏸ Pause Draw</button>
          <?php else: ?>
          <input type="hidden" name="action" value="activate">
          <button class="btn btn-secondary px-5 py-3 text-green-400">▶ Resume Draw</button>
          <?php endif; ?>
        </form>
      </div>
    </div>
    <?php endif; ?>

    <!-- Result panel (revealed by JS after finalization) -->
    <div id="result-panel" class="hidden card p-6 mb-5" style="border-color:rgba(234,179,8,.4)">
      <h2 class="font-bold text-xl text-yellow-400 mb-4">🏆 Winner Selected!</h2>
      <div class="mb-4">
        <div class="text-sm text-gray-400 mb-2">Winning Code Generated</div>
        <div id="winning-code-display" class="flex flex-wrap gap-1.5"></div>
      </div>
      <div id="winner-details" class="p-4 bg-yellow-500/10 border border-yellow-500/20 rounded-xl text-sm"></div>
      <a href="" id="result-reload" class="btn btn-primary mt-4 inline-block">View Full Result →</a>
    </div>

    <!-- Recent entries -->
    <?php if ($recentEntries && $draw['status']!=='completed'): ?>
    <div class="card">
      <div class="px-5 pt-5 pb-3 border-b border-white/5 font-bold text-sm">Recent Entries</div>
      <div class="divide-y divide-white/5">
        <?php foreach($recentEntries as $re): ?>
        <div class="flex items-center gap-3 px-5 py-3">
          <div class="w-8 h-8 bg-orange-500/15 rounded-lg flex items-center justify-center font-bold text-orange-400 text-sm flex-shrink-0"><?= strtoupper($re['full_name'][0]) ?></div>
          <div class="flex-1 min-w-0">
            <div class="text-sm font-medium truncate"><?= e($re['full_name']) ?></div>
            <code class="text-xs text-orange-400"><?= e($re['code']) ?></code>
          </div>
          <div class="text-xs text-gray-500 flex-shrink-0"><?= date('M j, g:ia',strtotime($re['entered_at'])) ?></div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

  </div>
</div>

<script src="<?= APP_URL ?>/assets/js/app.js"></script>
<script>
const DRAW_ID   = <?= $drawId ?>;
const CSRF_TOKEN = document.querySelector('meta[name="csrf-token"]').content;

async function finalizeDrawPrompt() {
  const confirmed = confirm(
    'Generate a winning code and select the winner for this draw?\n\n' +
    'This cannot be undone. All entered codes will be marked as used.'
  );
  if (!confirmed) return;

  const btn = document.getElementById('finalize-btn');
  btn.disabled = true;
  btn.textContent = '⏳ Processing…';

  try {
    const res  = await fetch('<?= APP_URL ?>/ajax/finalize-draw.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({ draw_id: DRAW_ID, '<?= CSRF_TOKEN_NAME ?>': CSRF_TOKEN })
    });
    const data = await res.json();

    if (!res.ok || data.error) {
      Toast.error(data.error || 'Finalization failed.');
      btn.disabled = false;
      btn.textContent = '🎲 Generate Winning Code & Select Winner';
      return;
    }

    // Show result panel immediately
    showResult(data);

  } catch(e) {
    Toast.error('Network error. Please try again.');
    btn.disabled = false;
    btn.textContent = '🎲 Generate Winning Code & Select Winner';
  }
}

function showResult(data) {
  // Build digit slots for the winning code
  const container = document.getElementById('winning-code-display');
  container.innerHTML = '';
  [...data.winning_code].forEach(d => {
    const slot = document.createElement('div');
    slot.className = 'digit-slot reveal';
    slot.style.cssText = 'display:inline-flex;align-items:center;justify-content:center;width:36px;height:44px;border-radius:8px;font-size:20px;font-weight:900;font-family:"Courier New",monospace;border:2px solid #f97316;background:rgba(249,115,22,.15);color:#f97316;animation:revealFlip .5s ease forwards';
    slot.textContent = d;
    container.appendChild(slot);
  });

  document.getElementById('winner-details').innerHTML = `
    <div class="font-bold text-yellow-400 text-base mb-1">🏆 Winner: ${escHtml(data.winner_name)}</div>
    <div class="text-gray-300">Matched <strong>${data.matched_digits}/15</strong> digits</div>
    <div class="text-gray-400 text-xs mt-1">${data.total_entries} total entries processed</div>
    ${data.tiebreaker ? `<div class="text-orange-300 text-xs mt-1">Tiebreaker: ${data.tiebreaker.replace(/_/g,' ')}</div>` : ''}
  `;

  document.getElementById('result-reload').href = window.location.href;
  document.getElementById('result-panel').classList.remove('hidden');
  document.getElementById('finalize-btn').closest('.card').style.display = 'none';

  Toast.success('Draw finalized! Winner selected.');
}

function escHtml(t) {
  return t.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}
</script>
</body></html>

<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
$auth = requireUser(); $userId = $auth['id'];
$drawId = (int)($_GET['id'] ?? 0);
if (!$drawId) redirect(APP_URL.'/user/draws.php');
$db = getDB();
$stmt=$db->prepare("SELECT * FROM draws WHERE id=?");$stmt->execute([$drawId]);$draw=$stmt->fetch();
if (!$draw) redirect(APP_URL.'/user/draws.php');

// Get user's entered codes for this draw
$myCodes = $db->prepare("SELECT c.code FROM draw_entries de JOIN codes c ON de.code_id=c.id WHERE de.draw_id=? AND de.user_id=?");
$myCodes->execute([$drawId,$userId]);
$myCodes = array_column($myCodes->fetchAll(),'code');

$currentPage='draws'; $pageTitle='Live Draw';
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="csrf-token" content="<?= generateCsrf() ?>">
  <title>Live Draw — <?= APP_NAME ?></title>
  <script src="<?= APP_URL ?>/assets/js/app.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
  <style>
    *{font-family:'Poppins',sans-serif!important}
    .code-char{display:inline-block;transition:all 0.3s;border-radius:4px;padding:0 2px}
    .code-char.match{color:#22c55e;text-shadow:0 0 10px rgba(34,197,94,0.6);animation:matchPop 0.4s ease}
    @keyframes matchPop{0%,100%{transform:scale(1)}50%{transform:scale(1.3)}}
  </style>
</head>
<body class="bg-[#0a0f1a] text-white">
<?php include __DIR__ . '/../components/user-sidebar.php'; ?>
<div class="main-content">
  <div class="topbar">
    <button onclick="toggleSidebar()" class="md:hidden text-gray-400 text-2xl mr-3">☰</button>
    <div class="flex items-center gap-2">
      <span class="pulse-dot"></span>
      <h1 class="text-lg font-bold">Live Draw — <?= e($draw['title']) ?></h1>
    </div>
  </div>
  <div class="p-6 max-w-3xl mx-auto">

    <!-- Winning code display -->
    <div class="card-glow p-6 mb-6 text-center">
      <div class="text-sm text-gray-400 mb-3 font-medium uppercase tracking-wider">Winning Code — Revealing Live</div>
      <div class="flex justify-center gap-1 flex-wrap" id="winning-slots">
        <?php for($i=0;$i<15;$i++): ?>
        <div class="digit-slot" id="slot-<?= $i ?>">?</div>
        <?php endfor; ?>
      </div>
      <div class="mt-4 text-sm text-gray-500" id="reveal-status">Waiting for draw to begin…</div>
    </div>

    <!-- Your codes -->
    <?php if ($myCodes): ?>
    <div class="card p-6 mb-6">
      <h2 class="font-bold text-lg mb-4">Your Codes in This Draw</h2>
      <div class="space-y-3" id="my-codes-list">
        <?php foreach ($myCodes as $code): ?>
        <div class="code-item" data-code="<?= e($code) ?>">
          <div class="flex items-center justify-between">
            <div class="code-number" id="code-<?= e($code) ?>">
              <?php for($i=0;$i<15;$i++): ?>
              <span class="code-char" data-pos="<?= $i ?>"><?= $code[$i] ?></span>
              <?php endfor; ?>
            </div>
            <div class="badge badge-warning ml-3" id="badge-<?= e($code) ?>">In Draw</div>
          </div>
          <div class="text-xs text-gray-500 mt-1" id="match-<?= e($code) ?>">Matches: 0/0 revealed</div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php else: ?>
    <div class="card p-6 mb-6 text-center text-gray-400">You have no codes entered in this draw.</div>
    <?php endif; ?>

    <!-- Winner announcement -->
    <div id="winner-section" class="hidden card p-6 text-center">
      <div class="text-6xl mb-4">🏆</div>
      <h2 class="text-2xl font-black text-yellow-400 mb-2">Draw Complete!</h2>
      <p class="text-gray-400 mb-4" id="winner-text"></p>
      <div class="font-mono text-orange-400 text-xl tracking-widest bg-black/20 rounded-xl p-3 mb-4" id="winner-code"></div>
      <a href="<?= APP_URL ?>/user/draws.php" class="btn btn-primary">Back to Draws</a>
    </div>
  </div>
</div>
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
<script>
const DRAW_ID = <?= $drawId ?>;
const MY_CODES = <?= json_encode($myCodes) ?>;

const poller = new LiveDrawPoller(DRAW_ID, handleUpdate);
poller.start(2000);

function handleUpdate(data) {
  const revealed = data.revealed || '';
  const len = revealed.length;

  document.getElementById('reveal-status').textContent =
    len === 0 ? 'Waiting for draw to begin…' :
    len < 15  ? `${len}/15 digits revealed…` : 'All digits revealed!';

  // Update winning slots
  for (let i = 0; i < 15; i++) {
    const slot = document.getElementById(`slot-${i}`);
    if (i < len) {
      slot.textContent = revealed[i];
      slot.classList.add('revealed');
    } else {
      slot.textContent = '?';
      slot.classList.remove('revealed','matched');
    }
  }

  // Update user code highlights
  MY_CODES.forEach(code => {
    const codeEl = document.getElementById(`code-${code}`);
    const matchEl = document.getElementById(`match-${code}`);
    if (!codeEl) return;
    let matches = 0;
    for (let i = 0; i < len; i++) {
      const span = codeEl.querySelector(`[data-pos="${i}"]`);
      if (!span) continue;
      if (code[i] === revealed[i]) {
        span.classList.add('match');
        matches++;
      } else {
        span.classList.remove('match');
      }
    }
    // Remove match class from unrevealed positions
    for (let i = len; i < 15; i++) {
      codeEl.querySelector(`[data-pos="${i}"]`)?.classList.remove('match');
    }
    if (matchEl) matchEl.textContent = `Matches: ${matches}/${len} revealed`;

    // Check if this code could be winner
    const badge = document.getElementById(`badge-${code}`);
    if (badge && matches === len && len > 0) {
      badge.className = 'badge badge-success ml-3';
      badge.textContent = '🔥 Matching!';
    } else if (badge) {
      badge.className = 'badge badge-warning ml-3';
      badge.textContent = 'In Draw';
    }
  });

  // Winner announced
  if (data.finalized && data.winner) {
    document.getElementById('winner-section').classList.remove('hidden');
    document.getElementById('winner-text').textContent = data.winner.is_me
      ? '🎉 Congratulations! YOU WON!'
      : `Winner: ${data.winner.name}`;
    document.getElementById('winner-code').textContent = data.winner.code;

    // Highlight all winning slots green
    if (data.winner.code) {
      for (let i = 0; i < 15; i++) {
        const slot = document.getElementById(`slot-${i}`);
        slot.classList.add('matched');
        slot.classList.remove('revealed');
      }
    }
  }
}
</script>
</body></html>

<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
$auth = requireUser(); $userId = $auth['id'];
$drawId = (int)($_GET['id'] ?? 0);
if (!$drawId) redirect(APP_URL.'/user/draws.php');

$db = getDB();
$draw = $db->prepare("SELECT * FROM draws WHERE id=?")->execute([$drawId]) ? null : null;
$stmt=$db->prepare("SELECT * FROM draws WHERE id=?");$stmt->execute([$drawId]);$draw=$stmt->fetch();
if (!$draw) redirect(APP_URL.'/user/draws.php');

// Get user's codes not already entered in this draw
$myCodes = $db->prepare("SELECT c.id,c.code FROM codes c WHERE c.current_owner=? AND c.status='redeemed' AND c.id NOT IN (SELECT code_id FROM draw_entries WHERE draw_id=?) ORDER BY c.generated_at DESC");
$myCodes->execute([$userId,$drawId]);
$myCodes = $myCodes->fetchAll();

// Codes already entered
$entered = $db->prepare("SELECT c.code, de.entered_at FROM draw_entries de JOIN codes c ON de.code_id=c.id WHERE de.draw_id=? AND de.user_id=? ORDER BY de.entered_at DESC");
$entered->execute([$drawId,$userId]);
$entered = $entered->fetchAll();

// Winner if completed
$winner = null;
if ($draw['status']==='completed') {
  $stmt=$db->prepare("SELECT dw.*, u.full_name, u.phone FROM draw_winners dw JOIN users u ON dw.user_id=u.id WHERE dw.draw_id=?");
  $stmt->execute([$drawId]); $winner=$stmt->fetch();
}

$currentPage='draws'; $pageTitle=e($draw['title']);
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="csrf-token" content="<?= generateCsrf() ?>">
  <title><?= e($pageTitle) ?> — <?= APP_NAME ?></title>
  <script src="<?= APP_URL ?>/assets/js/tailwind.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
  <style>*{font-family:'Poppins',sans-serif!important}</style>
</head>
<body class="bg-[#0a0f1a] text-white">
<?php include __DIR__ . '/../components/user-sidebar.php'; ?>
<div class="main-content">
  <div class="topbar">
    <button onclick="toggleSidebar()" class="md:hidden text-gray-400 text-2xl mr-3">☰</button>
    <a href="<?= APP_URL ?>/user/draws.php" class="text-orange-400 hover:underline text-sm mr-3">← Draws</a>
    <h1 class="text-lg font-bold truncate"><?= e($draw['title']) ?></h1>
  </div>
  <div class="p-6 max-w-3xl mx-auto">

    <!-- Draw Header -->
    <div class="card overflow-hidden mb-6">
      <div class="h-48 flex items-center justify-center text-6xl" style="background:linear-gradient(135deg,#1a2235,#0d1118)">
        <?php if($draw['banner_image'] && file_exists(UPLOAD_PATH.$draw['banner_image'])): ?>
          <img src="<?= APP_URL ?>/uploads/<?= e($draw['banner_image']) ?>" class="w-full h-full object-cover" alt="">
        <?php else: ?>🎰<?php endif; ?>
      </div>
      <div class="p-6">
        <div class="flex flex-wrap gap-2 mb-3">
          <?php if($draw['status']==='active'): ?><span class="badge badge-success">● LIVE</span><?php endif; ?>
          <?php if($draw['status']==='completed'): ?><span class="badge badge-muted">Completed</span><?php endif; ?>
          <?php if($draw['category']): ?><span class="badge badge-info"><?= e($draw['category']) ?></span><?php endif; ?>
          <span class="badge badge-muted">📋 <?= count($entered) ?> entries</span>
        </div>
        <h1 class="text-2xl font-bold mb-3"><?= e($draw['title']) ?></h1>
        <?php if($draw['description']): ?><p class="text-gray-400 mb-4"><?= nl2br(e($draw['description'])) ?></p><?php endif; ?>
        <?php if($draw['prize_details']): ?>
        <div class="bg-orange-500/10 border border-orange-500/20 rounded-xl p-4 mb-4">
          <div class="text-sm font-semibold text-orange-400 mb-1">🏆 Prize Details</div>
          <div class="text-gray-300 text-sm"><?= nl2br(e($draw['prize_details'])) ?></div>
        </div>
        <?php endif; ?>
        <?php if($draw['status']==='active'): ?>
        <div class="flex items-center gap-2 text-sm text-gray-400 mb-2">⏰ Draw ends:</div>
        <div class="flex gap-4" data-countdown="<?= e($draw['end_date']) ?>"></div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Winner (if completed) -->
    <?php if ($winner): ?>
    <div class="card p-6 mb-6 bg-gradient-to-r from-yellow-900/30 to-orange-900/20 border-yellow-500/30">
      <div class="text-center">
        <div class="text-5xl mb-3">🏆</div>
        <div class="text-xl font-black text-yellow-400 mb-1">Draw Winner!</div>
        <div class="text-lg font-bold"><?= e($winner['full_name']) ?></div>
        <div class="text-sm text-gray-400 mb-3"><?= e(formatPhone($winner['phone'])) ?></div>
        <div class="font-mono text-orange-400 text-xl font-bold tracking-widest bg-black/20 rounded-xl p-3"><?= e($winner['winning_code']) ?></div>
        <div class="text-sm text-gray-400 mt-2">Matched <?= $winner['matched_digits'] ?>/15 digits</div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Live draw link -->
    <?php if ($draw['status']==='active'): ?>
    <a href="<?= APP_URL ?>/user/live-draw.php?id=<?= $drawId ?>" class="card p-4 flex items-center gap-4 mb-6 hover:border-orange-500/40 transition-colors">
      <div class="w-12 h-12 bg-red-500/15 rounded-xl flex items-center justify-center text-2xl">🔴</div>
      <div class="flex-1">
        <div class="font-semibold">Watch Live Draw</div>
        <div class="text-xs text-gray-400">See digits revealed in real-time</div>
      </div>
      <svg class="w-5 h-5 text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
    </a>
    <?php endif; ?>

    <?php if ($draw['status']==='active'): ?>
    <!-- Code Selection -->
    <div class="card p-6 mb-6">
      <h2 class="font-bold text-lg mb-4">Enter Draw — Select Codes</h2>
      <?php if ($myCodes): ?>
      <div class="space-y-2 max-h-64 overflow-y-auto mb-4" id="code-list">
        <?php foreach ($myCodes as $c): ?>
        <label class="flex items-center gap-3 p-3 rounded-xl border border-white/5 hover:border-orange-500/30 cursor-pointer transition-colors">
          <input type="checkbox" class="code-check w-4 h-4 accent-orange-500" value="<?= $c['id'] ?>">
          <span class="font-mono text-orange-400 font-semibold tracking-widest"><?= e($c['code']) ?></span>
        </label>
        <?php endforeach; ?>
      </div>
      <div class="flex items-center justify-between text-sm text-gray-400 mb-4">
        <span><span id="sel-count">0</span> selected</span>
        <button onclick="selectAll()" class="text-orange-400 hover:underline">Select All</button>
      </div>
      <button onclick="confirmEntry()" id="entry-btn" class="btn btn-primary w-full py-3" disabled>Enter Draw with Selected Codes</button>
      <?php else: ?>
      <div class="text-center py-6">
        <div class="text-4xl mb-3">🎟️</div>
        <div class="text-gray-400 mb-4">No available codes. <a href="<?= APP_URL ?>/user/redeem.php" class="text-orange-400 hover:underline">Redeem a code</a></div>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Already Entered Codes -->
    <?php if ($entered): ?>
    <div class="card p-6">
      <h2 class="font-bold text-lg mb-4">Your Entered Codes (<?= count($entered) ?>)</h2>
      <div class="space-y-2">
        <?php foreach ($entered as $e): ?>
        <div class="flex items-center justify-between p-3 bg-black/20 rounded-xl">
          <span class="font-mono text-orange-400 tracking-widest font-semibold"><?= e($e['code']) ?></span>
          <span class="badge badge-warning">In Draw</span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Confirm Modal -->
    <div class="modal-overlay" id="confirm-modal">
      <div class="modal-box">
        <h3 class="text-xl font-bold mb-3">Confirm Draw Entry</h3>
        <p class="text-gray-400 mb-4">You are about to enter <strong id="entry-count" class="text-white"></strong> code(s) into this draw. This action cannot be undone — codes will be marked as reserved.</p>
        <div class="bg-orange-500/10 rounded-xl p-3 mb-5">
          <div class="text-xs text-orange-300 font-semibold mb-2">⚠️ Important</div>
          <div class="text-xs text-gray-400">After the draw, ALL entered codes will be consumed regardless of win/loss.</div>
        </div>
        <div class="flex gap-3">
          <button data-close-modal="confirm-modal" class="btn btn-secondary flex-1">Cancel</button>
          <button onclick="submitEntry()" class="btn btn-primary flex-1" id="submit-btn">Confirm Entry</button>
        </div>
      </div>
    </div>
  </div>
</div>
<script>
  window.APP_URL = '<?= APP_URL ?>';
</script>
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
<script>
let selectedCodes = [];

document.addEventListener('change', e => {
  if (e.target.classList.contains('code-check')) {
    selectedCodes = Array.from(document.querySelectorAll('.code-check:checked')).map(c => c.value);
    document.getElementById('sel-count').textContent = selectedCodes.length;
    document.getElementById('entry-btn').disabled = selectedCodes.length === 0;
  }
});

function selectAll() {
  document.querySelectorAll('.code-check').forEach(c => c.checked = true);
  selectedCodes = Array.from(document.querySelectorAll('.code-check')).map(c => c.value);
  document.getElementById('sel-count').textContent = selectedCodes.length;
  document.getElementById('entry-btn').disabled = false;
}

function confirmEntry() {
  if (!selectedCodes.length) return;
  document.getElementById('entry-count').textContent = selectedCodes.length;
  Modal.open('confirm-modal');
}

async function submitEntry() {
  const btn = document.getElementById('submit-btn');
  btn.disabled = true; btn.textContent = 'Submitting…';
  try {
    const data = await ZF.post('<?= APP_URL ?>/ajax/draw-enter.php', { draw_id: <?= $drawId ?>, code_ids: selectedCodes });
    Modal.close('confirm-modal');
    Toast.success(`Successfully entered ${selectedCodes.length} code(s) into the draw!`);
    setTimeout(() => location.reload(), 1500);
  } catch(e) {
    Toast.error(e.message);
    btn.disabled = false; btn.textContent = 'Confirm Entry';
  }
}
</script>
</body></html>

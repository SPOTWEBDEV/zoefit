<?php
// user/enter-draw.php
// User selects one of their available codes and enters a specific live draw.
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
$auth = requireUser(); $userId = $auth['id'];
$db = getDB();

$drawId = (int)($_GET['id'] ?? 0);
if (!$drawId) redirect(APP_URL . '/user/draws.php');

// Load draw — must be active
$draw = $db->prepare("SELECT * FROM draws WHERE id=? AND status='active'");
$draw->execute([$drawId]); $draw = $draw->fetch();
if (!$draw) {
    // Draw may have ended or doesn't exist
    redirect(APP_URL . '/user/draws.php');
}

$msg = $err = '';

// ── POST: enter the draw ───────────────────────────────────
if (isPost() && verifyCsrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
    $codeId = (int)($_POST['code_id'] ?? 0);

    if (!$codeId) {
        $err = 'Please select a code to enter.';
    } else {
        // Verify code belongs to this user and is redeemed (eligible)
        $code = $db->prepare(
            "SELECT * FROM codes WHERE id=? AND current_owner=? AND status='redeemed'"
        );
        $code->execute([$codeId, $userId]); $code = $code->fetch();

        if (!$code) {
            $err = 'Invalid code or code is not eligible for entry.';
        } else {
            // Check not already entered this draw with this code
            $alreadyIn = $db->prepare(
                "SELECT id FROM draw_entries WHERE draw_id=? AND code_id=?"
            );
            $alreadyIn->execute([$drawId, $codeId]);
            if ($alreadyIn->fetch()) {
                $err = 'You have already entered this draw with that code.';
            } else {
                // Enter the draw
                $db->prepare(
                    "INSERT INTO draw_entries (draw_id, user_id, code_id, entered_at)
                     VALUES (?,?,?,NOW())"
                )->execute([$drawId, $userId, $codeId]);

                // Mark code as reserved
                $db->prepare(
                    "UPDATE codes SET status='reserved' WHERE id=?"
                )->execute([$codeId]);

                createNotification($userId, '🎯 Entered Draw',
                    'Your code has been entered into "'.($draw['title']).'"', 'draw');

                auditLog('user', $userId, 'enter_draw',
                    "User entered draw #{$drawId} with code #{$codeId}", 'draw', $drawId);

                $msg = 'success';
            }
        }
    }
}

// ── Load user's eligible codes ─────────────────────────────
// Redeemed codes that are NOT already reserved in any draw
$myCodes = $db->prepare(
    "SELECT c.* FROM codes c
     WHERE c.current_owner = ?
       AND c.status = 'redeemed'
     ORDER BY c.redeemed_at DESC"
);
$myCodes->execute([$userId]); $myCodes = $myCodes->fetchAll();

// ── Codes already entered in THIS draw ─────────────────────
$enteredCodes = $db->prepare(
    "SELECT de.code_id, c.code FROM draw_entries de
     JOIN codes c ON c.id = de.code_id
     WHERE de.draw_id=? AND de.user_id=?"
);
$enteredCodes->execute([$drawId, $userId]);
$enteredCodes = $enteredCodes->fetchAll();
$enteredIds   = array_column($enteredCodes, 'code_id');
$myEntryCount = count($enteredCodes);

// ── Time left ─────────────────────────────────────────────
$endTime = strtotime($draw['end_date']);
$diff    = $endTime - time();
$dd = floor($diff/86400); $hh = floor(($diff%86400)/3600); $mm = floor(($diff%3600)/60);

// ── Total entries in this draw ─────────────────────────────
$totalEntries = (int)$db->prepare("SELECT COUNT(*) FROM draw_entries WHERE draw_id=?")
    ->execute([$drawId]) ? 0 : 0;
$s = $db->prepare("SELECT COUNT(*) FROM draw_entries WHERE draw_id=?");
$s->execute([$drawId]); $totalEntries = (int)$s->fetchColumn();

$currentPage = 'draws';
$pageTitle   = 'Enter Draw';
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="csrf-token" content="<?= generateCsrf() ?>">
  <title><?= e($pageTitle) ?> — <?= APP_NAME ?></title>
  <script src="<?= APP_URL ?>/assets/js/tailwind.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
  <style>
    *{font-family:'Poppins',sans-serif!important}
    code{font-family:'Courier New',monospace!important}
    .code-option{
      border:1.5px solid rgba(255,255,255,.08);
      border-radius:14px;
      padding:14px 16px;
      cursor:pointer;
      transition:all .2s;
      background:rgba(255,255,255,.03);
    }
    .code-option:hover{border-color:rgba(249,115,22,.4);background:rgba(249,115,22,.06);}
    .code-option input[type=radio]:checked ~ .code-option-inner,
    .code-option.selected{border-color:#f97316;background:rgba(249,115,22,.1);}
    .code-option input{position:absolute;opacity:0;width:0;height:0;}
    .code-option-label{display:flex;align-items:center;gap:3px;}
  </style>
</head>
<body class="bg-[#0a0f1a] text-white">
<?php include __DIR__ . '/../components/user-sidebar.php'; ?>

<div class="main-content">
  <div class="topbar">
    <button id="sidebar-hamburger-btn" onclick="toggleSidebar()" class="md:hidden text-gray-400 text-2xl mr-3">☰</button>
    <div>
      <a href="<?= APP_URL ?>/user/draws.php" class="text-orange-400 text-sm hover:underline">← Draws</a>
      <h1 class="text-base font-bold mt-0.5 truncate"><?= e($draw['title']) ?></h1>
    </div>
  </div>

  <div class="p-4 md:p-6 pb-24 md:pb-6 max-w-xl mx-auto">

    <!-- Success state -->
    <?php if ($msg === 'success'): ?>
    <div class="card p-8 text-center mb-5"
         style="border-color:rgba(34,197,94,.3);background:rgba(34,197,94,.05)">
      <div class="text-5xl mb-4">🎉</div>
      <h2 class="text-xl font-black text-green-400 mb-2">You're In!</h2>
      <p class="text-gray-400 text-sm mb-5">
        Your code has been entered into <strong class="text-white"><?= e($draw['title']) ?></strong>.
        Good luck! 🍀
      </p>
      <div class="flex gap-3 justify-center flex-wrap">
        <a href="<?= APP_URL ?>/user/enter-draw.php?id=<?= $drawId ?>"
           class="btn btn-secondary">+ Enter Another Code</a>
        <a href="<?= APP_URL ?>/user/draws.php"
           class="btn btn-primary">← Back to Draws</a>
      </div>
    </div>
    <?php endif; ?>

    <!-- Error -->
    <?php if ($err): ?>
    <div class="bg-red-500/10 border border-red-500/30 text-red-400 rounded-xl p-4 mb-5 text-sm">
      ❌ <?= e($err) ?>
    </div>
    <?php endif; ?>

    <!-- Draw info card -->
    <div class="card p-5 mb-5">
      <div class="flex items-start gap-4">
        <div class="w-16 h-16 rounded-xl flex-shrink-0 overflow-hidden flex items-center justify-center text-3xl"
             style="background:linear-gradient(135deg,#1a2235,#0d1118)">
          <?php if ($draw['banner_image'] && file_exists(UPLOAD_PATH.$draw['banner_image'])): ?>
          <img src="<?= APP_URL ?>/uploads/<?= e($draw['banner_image']) ?>" class="w-full h-full object-cover" alt="">
          <?php else: ?>🎯<?php endif; ?>
        </div>
        <div class="flex-1 min-w-0">
          <div class="flex items-center gap-2 flex-wrap mb-1">
            <h2 class="font-bold text-base"><?= e($draw['title']) ?></h2>
            <span class="badge badge-success flex items-center gap-1">
              <span class="pulse-dot w-1.5 h-1.5"></span> LIVE
            </span>
            <?php if ($draw['category']): ?>
            <span class="badge badge-info"><?= e($draw['category']) ?></span>
            <?php endif; ?>
          </div>
          <?php if ($draw['prize_details']): ?>
          <div class="text-sm text-orange-400 font-semibold mb-2">🏆 <?= e($draw['prize_details']) ?></div>
          <?php endif; ?>
          <div class="flex flex-wrap gap-4 text-xs text-gray-500">
            <span>📝 <?= number_format($totalEntries) ?> total entries</span>
            <?php if ($myEntryCount > 0): ?>
            <span class="text-green-400">✓ You have <?= $myEntryCount ?> entr<?= $myEntryCount===1?'y':'ies' ?></span>
            <?php endif; ?>
            <span>⏱ <span id="countdown-timer" class="font-mono text-white font-bold">
              <?= ($dd>0?"{$dd}d ":"{$hh}h {$mm}m") ?>
            </span> left</span>
          </div>
        </div>
      </div>

      <?php if ($draw['description']): ?>
      <div class="mt-3 pt-3 border-t border-white/5 text-xs text-gray-400 leading-relaxed">
        <?= e($draw['description']) ?>
      </div>
      <?php endif; ?>

      <?php if (!empty($draw['rules'])): ?>
      <div class="mt-3 pt-3 border-t border-white/5">
        <div class="text-xs font-semibold text-gray-300 mb-1.5 flex items-center gap-1.5">📋 Draw Rules</div>
        <div class="text-xs text-gray-400 leading-relaxed whitespace-pre-line"><?= e($draw['rules']) ?></div>
      </div>
      <?php endif; ?>
    </div>

    <!-- Already entered codes -->
    <?php if ($enteredCodes): ?>
    <div class="card p-4 mb-5" style="border-color:rgba(34,197,94,.2)">
      <div class="text-sm font-semibold text-green-400 mb-3">
        ✓ Your <?= count($enteredCodes) > 1 ? count($enteredCodes).' codes' : 'code' ?> in this draw:
      </div>
      <div class="space-y-2">
        <?php foreach ($enteredCodes as $ec): ?>
        <div class="flex items-center gap-2 text-sm">
          <span class="w-5 h-5 bg-green-500/20 rounded flex items-center justify-center text-green-400 text-xs flex-shrink-0">✓</span>
          <code class="text-green-300 tracking-widest"><?= e($ec['code']) ?></code>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Enter form -->
    <?php if ($msg !== 'success'): ?>
    <?php if ($myCodes): ?>
    <div class="card p-5">
      <h3 class="font-bold mb-1">Select a Code to Enter</h3>
      <p class="text-gray-400 text-xs mb-4">
        Choose one of your redeemed codes. Each code is one entry.
        <?php if ($myEntryCount > 0): ?>
        You can enter multiple codes to increase your chances.
        <?php endif; ?>
      </p>

      <form method="POST" id="enter-form">
        <?= csrfField() ?>
        <div class="space-y-2 mb-5 max-h-72 overflow-y-auto pr-1" id="code-list">
          <?php foreach ($myCodes as $c):
            $alreadyUsedThisCode = in_array($c['id'], $enteredIds);
          ?>
          <label class="code-option block <?= $alreadyUsedThisCode ? 'opacity-50 cursor-not-allowed' : '' ?>">
            <input type="radio" name="code_id" value="<?= $c['id'] ?>"
                   <?= $alreadyUsedThisCode ? 'disabled' : '' ?>
                   onchange="document.getElementById('submit-btn').disabled=false;
                             document.querySelectorAll('.code-option').forEach(el=>el.classList.remove('selected'));
                             this.closest('.code-option').classList.add('selected')">
            <div class="flex items-center gap-3">
              <div class="w-5 h-5 rounded-full border-2 border-white/20 flex items-center justify-center flex-shrink-0 radio-indicator">
                <div class="w-2.5 h-2.5 bg-orange-500 rounded-full hidden check-dot"></div>
              </div>
              <div class="flex-1 min-w-0">
                <code class="text-sm font-bold tracking-wider <?= $alreadyUsedThisCode ? 'text-gray-500' : 'text-orange-400' ?>">
                  <?= e($c['code']) ?>
                </code>
                <?php if ($alreadyUsedThisCode): ?>
                <span class="ml-2 text-xs text-green-400">✓ Already entered</span>
                <?php else: ?>
                <div class="text-xs text-gray-500 mt-0.5">
                  Redeemed <?= date('M j, Y', strtotime($c['redeemed_at'] ?? $c['generated_at'])) ?>
                </div>
                <?php endif; ?>
              </div>
            </div>
          </label>
          <?php endforeach; ?>
        </div>

        <button type="submit" id="submit-btn" disabled
                class="btn btn-primary w-full py-3 text-base font-bold"
                style="opacity:.5;cursor:not-allowed"
                onmouseenter="if(!this.disabled)this.style.opacity='1'"
                onclick="this.style.opacity='1'">
          🎯 Enter This Draw
        </button>
      </form>

      <p class="text-xs text-gray-600 text-center mt-3">
        Your selected code will be marked as <strong>reserved</strong> and cannot be transferred or used elsewhere while in an active draw.
      </p>
    </div>

    <?php else: ?>
    <!-- No eligible codes -->
    <div class="card p-8 text-center">
      <div class="text-4xl mb-3">🎟️</div>
      <h3 class="font-bold text-base mb-2">No Eligible Codes</h3>
      <p class="text-gray-400 text-sm mb-5">
        You need at least one redeemed code in your wallet to enter a draw.
        Redeem a code first, then come back to enter.
      </p>
      <div class="flex gap-3 justify-center flex-wrap">
        <a href="<?= APP_URL ?>/user/redeem.php" class="btn btn-primary">🎟️ Redeem a Code</a>
        <a href="<?= APP_URL ?>/user/draws.php"  class="btn btn-secondary">← Back to Draws</a>
      </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>

  </div>
</div>

<script src="<?= APP_URL ?>/assets/js/app.js"></script>
<script>
// Countdown timer
(function () {
  const end = new Date('<?= $draw['end_date'] ?>').getTime();
  const el  = document.getElementById('countdown-timer');
  if (!el) return;
  function tick() {
    const diff = Math.max(0, Math.floor((end - Date.now()) / 1000));
    if (diff === 0) { el.textContent = 'Ending soon'; return; }
    const d = Math.floor(diff/86400), h = Math.floor((diff%86400)/3600),
          m = Math.floor((diff%3600)/60), s = diff%60;
    el.textContent = (d>0?d+'d ':'')+String(h).padStart(2,'0')+'h '
                    +String(m).padStart(2,'0')+'m '+String(s).padStart(2,'0')+'s';
  }
  tick(); setInterval(tick, 1000);
})();

// Radio visual feedback
document.querySelectorAll('.code-option input[type=radio]').forEach(radio => {
  radio.addEventListener('change', function () {
    document.querySelectorAll('.radio-indicator').forEach(ri => {
      ri.classList.remove('border-orange-500');
      ri.classList.add('border-white/20');
      ri.querySelector('.check-dot').classList.add('hidden');
    });
    const indicator = this.closest('.code-option').querySelector('.radio-indicator');
    indicator.classList.add('border-orange-500');
    indicator.classList.remove('border-white/20');
    indicator.querySelector('.check-dot').classList.remove('hidden');

    const btn = document.getElementById('submit-btn');
    btn.disabled = false;
    btn.style.opacity = '1';
    btn.style.cursor  = 'pointer';
  });
});

// Confirm before submit
document.getElementById('enter-form')?.addEventListener('submit', function (e) {
  const selected = this.querySelector('input[name=code_id]:checked');
  if (!selected) { e.preventDefault(); return; }
  const code = selected.closest('.code-option').querySelector('code').textContent.trim();
  if (!confirm('Enter draw with code:\n' + code + '\n\nYour code will be reserved until the draw ends.')) {
    e.preventDefault();
  }
});
</script>
</body>
</html>
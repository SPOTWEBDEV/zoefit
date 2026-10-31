<?php
// user/enter-draw.php
// User selects one or more of their available codes and enters a specific live draw.
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../lib/draw_trigger.php';   // drawHasDeadline(), sweepEntryTargetDraws()
$auth = requireUser(); $userId = $auth['id'];
$db = getDB();

$drawId = (int)($_GET['id'] ?? 0);
if (!$drawId) redirect(APP_URL . '/user/draws.php');

// Safety net: close any entry draw that already reached its target BEFORE we load this one,
// so a full draw can never be entered.
sweepEntryTargetDraws($db);

// Load draw — must be active
$draw = $db->prepare("SELECT * FROM draws WHERE id=? AND status='active'");
$draw->execute([$drawId]); $draw = $draw->fetch();
if (!$draw) {
    // Draw may have ended or doesn't exist
    redirect(APP_URL . '/user/draws.php');
}

// ── Draw type ──────────────────────────────────────────────
$isEntries   = ($draw['draw_type'] ?? 'timed') === 'entries';
$target      = (int)($draw['target_entries'] ?? 0);
$hasDeadline = drawHasDeadline($draw['end_date']);     // entry draws without a deadline store a placeholder date: never show it
$endTime     = $hasDeadline ? strtotime($draw['end_date']) : null;
$countEntries = function () use ($db, $drawId): int {
    $s = $db->prepare("SELECT COUNT(*) FROM draw_entries WHERE draw_id=?");
    $s->execute([$drawId]); return (int)$s->fetchColumn();
};

$msg = $err = '';
$enteredCountMsg = 0;
$skippedMsg = [];
$closedNow = false;   // true when this very entry filled an entry-triggered draw

// ── POST: enter the draw with one or more codes ────────────
if (isPost() && verifyCsrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
    $codeIds = $_POST['code_id'] ?? [];
    if (!is_array($codeIds)) $codeIds = [$codeIds];
    $codeIds = array_values(array_unique(array_filter(array_map('intval', $codeIds))));

    if (!$codeIds) {
        $err = 'Please select at least one code to enter.';
    } else {
        $enteredCount = 0;
        $skipped = [];

        foreach ($codeIds as $codeId) {
            // Verify code belongs to this user and is redeemed (eligible)
            $code = $db->prepare(
                "SELECT * FROM codes WHERE id=? AND current_owner=? AND status='redeemed'"
            );
            $code->execute([$codeId, $userId]); $code = $code->fetch();

            if (!$code) {
                $skipped[] = "Code #$codeId (invalid or not eligible)";
                continue;
            }

            // Entry-triggered draw: never go past the target
            if ($isEntries && $target > 0 && $countEntries() >= $target) {
                $skipped[] = $code['code'].' (draw is full)';
                continue;
            }

            // Check not already entered this draw with this code
            $alreadyIn = $db->prepare(
                "SELECT id FROM draw_entries WHERE draw_id=? AND code_id=?"
            );
            $alreadyIn->execute([$drawId, $codeId]);
            if ($alreadyIn->fetch()) {
                $skipped[] = $code['code'].' (already entered)';
                continue;
            }

            // Enter the draw
            $db->prepare(
                "INSERT INTO draw_entries (draw_id, user_id, code_id, entered_at)
                 VALUES (?,?,?,NOW())"
            )->execute([$drawId, $userId, $codeId]);

            // Mark code as reserved
            $db->prepare(
                "UPDATE codes SET status='reserved' WHERE id=?"
            )->execute([$codeId]);

            auditLog('user', $userId, 'enter_draw',
                "User entered draw #{$drawId} with code #{$codeId}", 'draw', $drawId);

            $enteredCount++;
        }

        if ($enteredCount > 0) {
            // Entry-triggered draws close themselves the moment they hit the target
            if ($isEntries) {
                sweepEntryTargetDraws($db);
                $st = $db->prepare("SELECT status FROM draws WHERE id=?");
                $st->execute([$drawId]);
                $closedNow = ($st->fetchColumn() !== 'active');
            }

            createNotification($userId, '🎯 Entered Draw',
                $enteredCount > 1
                    ? "You entered \"{$draw['title']}\" with {$enteredCount} codes."
                    : 'Your code has been entered into "'.$draw['title'].'"',
                'draw');
            $msg = 'success';
            $enteredCountMsg = $enteredCount;
            $skippedMsg = $skipped;
        } else {
            $err = 'None of the selected codes could be entered: '.implode(', ', $skipped);
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

// ── Total entries + progress (after any POST above) ────────
$totalEntries = $countEntries();
$pct     = ($isEntries && $target > 0) ? min(100, (int)floor($totalEntries / $target * 100)) : 0;
$reached = $isEntries && $target > 0 && $totalEntries >= $target;
$left    = max(0, $target - $totalEntries);

// ── Time left (only for draws that really have a deadline) ─
$timeLeftText = '';
if ($hasDeadline) {
    $diff = $endTime - time();
    $dd = floor($diff/86400); $hh = floor(($diff%86400)/3600); $mm = floor(($diff%3600)/60);
    $timeLeftText = $dd > 0 ? "{$dd}d " : "{$hh}h {$mm}m";
}

$currentPage = 'draws';
$pageTitle   = 'Enter Draw';
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="csrf-token" content="<?= generateCsrf() ?>">
  <title><?= e($pageTitle) ?> — <?= APP_NAME ?></title>
  <script src="<?= APP_URL ?>/assets/js/tailwind.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
  <style>
    .cd-text { white-space: pre-line; }
    .code-option{
      border:1px solid rgba(255,255,255,.08);
      border-radius:12px;
      padding:12px 14px;
      cursor:pointer;
      transition:border-color .15s, background .15s;
      background:rgba(255,255,255,.025);
    }
    .code-option:hover{border-color:rgba(249,115,22,.4);background:rgba(249,115,22,.05);}
    .code-option.selected{border-color:#f97316;background:rgba(249,115,22,.09);}
    .code-option input{position:absolute;opacity:0;width:0;height:0;}
    .check-indicator{transition:all .15s;}
  </style>
</head>
<body class="bg-[#0a0f1a] text-white antialiased">
<?php include __DIR__ . '/../components/saas-ui.php'; ?>
<?php include __DIR__ . '/../components/user-sidebar.php'; ?>

<div class="main-content">
  <div class="topbar">
    <button id="sidebar-hamburger-btn" onclick="toggleSidebar()" class="md:hidden text-gray-400 text-2xl mr-3" aria-label="Menu">☰</button>
    <div class="min-w-0">
      <a href="<?= APP_URL ?>/user/draws.php" class="inline-flex items-center gap-1 text-xs text-slate-400 hover:text-orange-300 transition"><?= ico('arrow-left', 'w-3.5 h-3.5') ?> Draws</a>
      <h1 class="text-base font-semibold mt-0.5 truncate"><?= e($draw['title']) ?></h1>
    </div>
  </div>

  <div class="p-4 md:p-6 pb-24 md:pb-6 max-w-xl mx-auto space-y-5">

    <!-- Success state -->
    <?php if ($msg === 'success'): ?>
    <div class="s-card p-8 text-center" style="border-color:rgba(34,197,94,.3);background:rgba(34,197,94,.05)">
      <div class="w-14 h-14 mx-auto mb-4 rounded-full bg-green-500/15 text-green-400 flex items-center justify-center"><?= ico('check-circle', 'w-7 h-7') ?></div>
      <h2 class="text-xl font-semibold text-green-400 mb-2">You're in!</h2>
      <p class="text-slate-400 text-sm mb-2">
        <?= $enteredCountMsg > 1
            ? "<strong class='text-white'>{$enteredCountMsg} codes</strong> have been entered into"
            : 'Your code has been entered into' ?>
        <strong class="text-white"><?= e($draw['title']) ?></strong>. Good luck!
      </p>
      <?php if ($closedNow): ?>
      <p class="text-sm text-orange-300 mt-3">This draw has just reached its entry target and is now closed. The winner will be selected shortly.</p>
      <?php endif; ?>
      <?php if ($skippedMsg): ?>
      <p class="text-xs text-slate-500 mt-2">
        (<?= count($skippedMsg) ?> code<?= count($skippedMsg)===1?'':'s' ?> skipped: <?= e(implode(', ', $skippedMsg)) ?>)
      </p>
      <?php endif; ?>
      <div class="flex gap-3 justify-center flex-wrap mt-5">
        <?php if (!$closedNow): ?>
        <a href="<?= APP_URL ?>/user/enter-draw.php?id=<?= $drawId ?>" class="s-btn s-btn-ghost"><?= ico('plus') ?> Enter more codes</a>
        <?php endif; ?>
        <a href="<?= APP_URL ?>/user/draws.php" class="s-btn s-btn-primary"><?= ico('arrow-left') ?> Back to draws</a>
      </div>
    </div>
    <?php endif; ?>

    <!-- Error -->
    <?php if ($err): ?>
    <div class="s-alert s-alert-error" role="alert"><?= ico('alert', 'w-5 h-5 flex-shrink-0 mt-px') ?><div><?= e($err) ?></div></div>
    <?php endif; ?>

    <!-- Draw info card -->
    <div class="s-card p-5">
      <div class="flex items-start gap-4">
        <div class="s-thumb" style="width:64px;height:64px">
          <?php if ($draw['banner_image'] && file_exists(UPLOAD_PATH.$draw['banner_image'])): ?>
          <img src="<?= APP_URL ?>/uploads/<?= e($draw['banner_image']) ?>" class="w-full h-full object-cover" alt="">
          <?php else: ?><?= ico($isEntries ? 'target' : 'trophy', 'w-7 h-7') ?><?php endif; ?>
        </div>
        <div class="flex-1 min-w-0">
          <div class="flex items-center gap-1.5 flex-wrap mb-1.5">
            <span class="s-pill s-pill-green dot">Live</span>
            <?php if ($isEntries): ?><span class="s-pill s-pill-purple"><?= ico('target', 'w-3 h-3') ?> Entry-triggered</span><?php endif; ?>
            <?php if ($draw['category']): ?><span class="s-pill s-pill-blue"><?= e($draw['category']) ?></span><?php endif; ?>
          </div>
          <h2 class="font-semibold text-base leading-snug"><?= e($draw['title']) ?></h2>
          <?php if ($draw['prize_details']): ?>
          <div class="text-sm text-orange-400 font-medium mt-1 flex items-start gap-1.5"><?= ico('trophy', 'w-4 h-4 mt-0.5 flex-shrink-0') ?><span><?= e($draw['prize_details']) ?></span></div>
          <?php endif; ?>
          <div class="s-meta mt-2">
            <span class="s-meta-item"><?= ico('hash', 'w-3.5 h-3.5') ?> <?= number_format($totalEntries) ?> total entries</span>
            <?php if ($myEntryCount > 0): ?>
            <span class="s-meta-item text-green-400"><?= ico('check', 'w-3.5 h-3.5') ?> You have <?= $myEntryCount ?> entr<?= $myEntryCount===1?'y':'ies' ?></span>
            <?php endif; ?>
            <?php if ($hasDeadline): ?>
            <!-- Countdown only when the draw has a real deadline -->
            <span class="s-meta-item"><?= ico('clock', 'w-3.5 h-3.5') ?>
              <span id="countdown-timer" class="font-mono text-white font-semibold"><?= $timeLeftText ?></span> left
            </span>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <?php if ($isEntries && $target > 0): ?>
      <!-- Entry progress: this is what ends an entry-triggered draw -->
      <div class="mt-4 pt-4 border-t border-white/[0.06]">
        <div class="flex items-center justify-between text-xs mb-1.5">
          <span class="font-medium <?= $reached ? 'text-green-400' : 'text-slate-300' ?>"><?= number_format($totalEntries) ?> / <?= number_format($target) ?> entries</span>
          <span class="<?= $reached ? 'text-green-400' : 'text-slate-500' ?>"><?= $reached ? 'Target reached' : $pct . '%' ?></span>
        </div>
        <div class="s-progress"><div class="<?= $reached ? 'done' : '' ?>" style="width:<?= $pct ?>%"></div></div>
        <?php if (!$reached): ?>
        <div class="text-[11.5px] text-slate-500 mt-1.5">
          The draw closes automatically after <?= number_format($left) ?> more <?= $left === 1 ? 'entry' : 'entries' ?><?= $hasDeadline ? ', or at the deadline' : '' ?>.
        </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <?php if ($draw['description']): ?>
      <div class="mt-4 pt-4 border-t border-white/[0.06] text-xs text-slate-400 leading-relaxed"><?= e($draw['description']) ?></div>
      <?php endif; ?>

      <?php if (!empty($draw['rules'])): ?>
      <div class="mt-4 pt-4 border-t border-white/[0.06]">
        <div class="text-xs font-semibold text-slate-300 mb-1.5">Draw rules</div>
        <div class="text-xs text-slate-400 leading-relaxed whitespace-pre-line"><?= e($draw['rules']) ?></div>
      </div>
      <?php endif; ?>
    </div>

    <!-- Already entered codes -->
    <?php if ($enteredCodes): ?>
    <div class="s-card p-4" style="border-color:rgba(34,197,94,.2)">
      <div class="text-sm font-medium text-green-400 mb-3 flex items-center gap-2">
        <?= ico('check-circle', 'w-4 h-4') ?> Your <?= count($enteredCodes) > 1 ? count($enteredCodes).' codes' : 'code' ?> in this draw
      </div>
      <div class="space-y-2">
        <?php foreach ($enteredCodes as $ec): ?>
        <div class="flex items-center gap-2 text-sm">
          <span class="w-5 h-5 bg-green-500/15 rounded flex items-center justify-center text-green-400 flex-shrink-0"><?= ico('check', 'w-3 h-3') ?></span>
          <code class="text-green-300 tracking-widest"><?= e($ec['code']) ?></code>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Enter form -->
    <?php if ($msg !== 'success'): ?>
    <?php if ($myCodes): ?>
    <div class="s-card p-5">
      <div class="flex items-center justify-between gap-3 mb-1">
        <h3 class="font-semibold">Select codes to enter</h3>
        <button type="button" id="select-all-btn" class="text-xs text-orange-400 hover:underline flex-shrink-0">Select all</button>
      </div>
      <p class="text-slate-400 text-xs mb-4">
        Choose one or more of your redeemed codes. Each code counts as a separate entry —
        selecting more codes increases your chances of winning.
        <?php if ($isEntries && $target > 0 && !$reached): ?>
        <span class="block mt-1 text-slate-500">Only <?= number_format($left) ?> more <?= $left === 1 ? 'entry fits' : 'entries fit' ?> in this draw, so if it fills up first, your remaining codes stay in your wallet.</span>
        <?php endif; ?>
      </p>

      <form method="POST" id="enter-form">
        <?= csrfField() ?>
        <div class="space-y-2 mb-3 max-h-72 overflow-y-auto pr-1" id="code-list">
          <?php foreach ($myCodes as $c):
            $alreadyUsedThisCode = in_array($c['id'], $enteredIds);
          ?>
          <label class="code-option block <?= $alreadyUsedThisCode ? 'opacity-50 cursor-not-allowed' : '' ?>">
            <input type="checkbox" name="code_id[]" value="<?= $c['id'] ?>" <?= $alreadyUsedThisCode ? 'disabled' : '' ?>>
            <div class="flex items-center gap-3">
              <div class="w-5 h-5 rounded-md border-2 border-white/20 flex items-center justify-center flex-shrink-0 check-indicator">
                <span class="text-white hidden check-mark"><?= ico('check', 'w-3 h-3') ?></span>
              </div>
              <div class="flex-1 min-w-0">
                <code class="text-sm font-bold tracking-wider <?= $alreadyUsedThisCode ? 'text-slate-500' : 'text-orange-400' ?>"><?= e($c['code']) ?></code>
                <?php if ($alreadyUsedThisCode): ?>
                <span class="ml-2 text-xs text-green-400">Already entered</span>
                <?php else: ?>
                <div class="text-xs text-slate-500 mt-0.5">Redeemed <?= date('M j, Y', strtotime($c['redeemed_at'] ?? $c['generated_at'])) ?></div>
                <?php endif; ?>
              </div>
            </div>
          </label>
          <?php endforeach; ?>
        </div>

        <div class="text-xs text-slate-500 mb-3" id="selected-count-label">0 codes selected</div>

        <button type="submit" id="submit-btn" disabled class="s-btn s-btn-primary w-full py-3 text-sm" style="opacity:.5;cursor:not-allowed">
          <?= ico('target') ?> Enter this draw
        </button>
      </form>

      <p class="text-xs text-slate-600 text-center mt-3">
        Selected codes will be marked as <strong>reserved</strong> and cannot be transferred or used elsewhere while in an active draw.
      </p>
    </div>

    <?php else: ?>
    <!-- No eligible codes -->
    <div class="s-card p-8 text-center">
      <div class="w-12 h-12 mx-auto mb-3 rounded-full bg-white/5 flex items-center justify-center text-slate-400"><?= ico('inbox', 'w-6 h-6') ?></div>
      <h3 class="font-semibold text-base mb-2">No eligible codes</h3>
      <p class="text-slate-400 text-sm mb-5">
        You need at least one redeemed code in your wallet to enter a draw.
        Redeem a code first, then come back to enter.
      </p>
      <div class="flex gap-3 justify-center flex-wrap">
        <a href="<?= APP_URL ?>/user/redeem.php" class="s-btn s-btn-primary">Redeem a code</a>
        <a href="<?= APP_URL ?>/user/draws.php"  class="s-btn s-btn-ghost"><?= ico('arrow-left') ?> Back to draws</a>
      </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>

  </div>
</div>

<script>window.APP_URL = '<?= APP_URL ?>';</script>
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
<?php if ($hasDeadline): ?>
<script>
// Countdown timer: only rendered for draws that actually have a deadline
(function () {
  const end = <?= (int)$endTime * 1000 ?>;
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
</script>
<?php endif; ?>
<script>
// ── Multi-select checkbox behavior ─────────────────────────
(function () {
  const form        = document.getElementById('enter-form');
  const checkboxes  = document.querySelectorAll('.code-option input[type=checkbox]');
  const submitBtn   = document.getElementById('submit-btn');
  const countLabel  = document.getElementById('selected-count-label');
  const selectAllBtn = document.getElementById('select-all-btn');
  if (!checkboxes.length) return;

  function syncVisual(cb) {
    const wrap = cb.closest('.code-option');
    const indicator = wrap.querySelector('.check-indicator');
    const mark = wrap.querySelector('.check-mark');
    if (cb.checked) {
      wrap.classList.add('selected');
      indicator.classList.add('border-orange-500');
      indicator.style.background = '#f97316';
      mark.classList.remove('hidden');
    } else {
      wrap.classList.remove('selected');
      indicator.classList.remove('border-orange-500');
      indicator.style.background = 'transparent';
      mark.classList.add('hidden');
    }
  }

  // Keep the custom confirmation dialog's text in sync with the selected codes
  function updateConfirm(codes) {
    if (!codes.length) { form.removeAttribute('data-confirm'); return; }
    form.setAttribute('data-confirm', '1');
    form.dataset.confirmTone  = 'success';
    form.dataset.confirmTitle = codes.length === 1 ? 'Enter this draw with 1 code?' : 'Enter this draw with ' + codes.length + ' codes?';
    form.dataset.confirmText  = codes.join('\n') + '\n\n' + (codes.length === 1
      ? 'Your code will be reserved until the draw ends.'
      : 'All selected codes will be reserved until the draw ends.');
    form.dataset.confirmLabel = 'Enter draw';
  }

  function refreshState() {
    const checked = document.querySelectorAll('.code-option input[type=checkbox]:checked');
    const n = checked.length;
    countLabel.textContent = n === 0 ? '0 codes selected' : (n + (n === 1 ? ' code selected' : ' codes selected'));
    submitBtn.disabled = n === 0;
    submitBtn.style.opacity = n === 0 ? '.5' : '1';
    submitBtn.style.cursor  = n === 0 ? 'not-allowed' : 'pointer';
    updateConfirm(Array.from(checked).map(cb => cb.closest('.code-option').querySelector('code').textContent.trim()));
  }

  checkboxes.forEach(cb => {
    cb.addEventListener('change', function () { syncVisual(this); refreshState(); });
  });

  selectAllBtn?.addEventListener('click', function () {
    const enabled = Array.from(checkboxes).filter(cb => !cb.disabled);
    const allChecked = enabled.length > 0 && enabled.every(cb => cb.checked);
    enabled.forEach(cb => { cb.checked = !allChecked; syncVisual(cb); });
    this.textContent = allChecked ? 'Select all' : 'Deselect all';
    refreshState();
  });

  refreshState();
})();
// The confirmation itself is handled by components/saas-ui.php (any <form data-confirm> asks first).
</script>
</body>
</html>
<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
$auth = requireUser(); $userId = $auth['id'];
$currentPage = 'redeem'; $pageTitle = 'Redeem Code';
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="csrf-token" content="<?= generateCsrf() ?>">
  <title><?= e($pageTitle) ?> — <?= APP_NAME ?></title>
  <script src="<?= APP_URL ?>/assets/js/tailwind.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
  <style>
    .gen-code-box {
      font-family: 'Space Grotesk', monospace;
      letter-spacing: .15em;
    }
  </style>
</head>
<body class="bg-[#0a0f1a] text-white font-sans">
<?php include __DIR__ . '/../components/user-sidebar.php'; ?>
<div class="main-content">
  <div class="topbar">
    <button onclick="toggleSidebar()" class="md:hidden text-gray-400 text-2xl mr-3">☰</button>
    <h1 class="text-xl font-bold">Redeem Code</h1>
  </div>
  <div class="p-6 max-w-lg mx-auto">
    <div class="card-glow p-8 text-center mb-6">
      <div class="text-5xl mb-4">🎟️</div>
      <h2 class="text-xl font-bold mb-2">Enter Your Raffle Code</h2>
      <p class="text-gray-400 text-sm">Enter your 15-digit raffle code to add it to your wallet</p>
    </div>

    <!-- ── GENERATE A CODE ─────────────────────────────────── -->
    <div class="card p-6 mb-6">
      <div class="flex items-start justify-between gap-3 mb-1">
        <div>
          <h3 class="font-bold text-base flex items-center gap-2">🎲 Generate a Code</h3>
          <p class="text-gray-400 text-xs mt-1">Get up to 2 free raffle codes every day — no purchase needed.</p>
        </div>
        <span id="gen-remaining-badge"
              class="flex-shrink-0 text-xs px-2.5 py-1 rounded-full font-semibold whitespace-nowrap"
              style="background:rgba(249,115,22,.1);border:1px solid rgba(249,115,22,.25);color:#f97316">
          … left today
        </span>
      </div>

      <button onclick="generateCode()" id="generate-btn"
              class="btn btn-secondary w-full py-2.5 text-sm font-semibold mt-3" disabled>
        🎲 Generate Code
      </button>

      <!-- Freshly generated code reveal -->
      <div id="new-code-box" class="hidden mt-4 rounded-xl p-4 text-center"
           style="background:rgba(34,197,94,.08);border:1px solid rgba(34,197,94,.25)">
        <div class="text-xs text-green-400 font-semibold mb-2">✨ New code generated!</div>
        <div id="new-code-value" class="gen-code-box text-lg font-bold text-white mb-3"></div>
        <div class="flex gap-2">
          <button type="button" id="new-code-redeem-btn" class="btn btn-primary flex-1 py-2 text-sm">Redeem Now →</button>
          <button type="button" onclick="copyGeneratedCode()" class="btn btn-secondary px-4 py-2 text-sm">Copy</button>
        </div>
      </div>
    </div>

    <!-- ── YOUR PENDING SELF-GENERATED CODES ───────────────── -->
    <div class="card p-6 mb-6">
      <h3 class="font-bold text-base mb-3 flex items-center gap-2">📜 Your Self-Generated Codes</h3>
      <div id="generated-codes-list"></div>
      <div id="generated-codes-empty" class="text-center text-gray-500 text-sm py-4">
        No pending self-generated codes yet — generate one above.
      </div>
    </div>

    <!-- ── MANUAL REDEEM ────────────────────────────────────── -->
    <div class="card p-6">
      <div class="form-group">
        <label class="form-label">15-Digit Raffle Code</label>
        <input type="text" id="code-input" class="form-control text-center text-2xl tracking-widest font-display"
               placeholder="000000000000000" maxlength="15" autocomplete="off"
               oninput="this.value=this.value.replace(/\D/g,'').slice(0,15); updateCodeDisplay()">
        <div id="code-preview" class="mt-3 font-display text-center text-orange-400 tracking-widest text-xl font-bold min-h-[32px]"></div>
      </div>
      <button onclick="redeemCode()" id="redeem-btn" class="btn btn-primary w-full py-3 text-base" disabled>
        Redeem Code
      </button>
    </div>

    <!-- Success Modal -->
    <div class="modal-overlay" id="success-modal">
      <div class="modal-box text-center">
        <div class="text-6xl mb-4" id="result-icon">✓</div>
        <h3 class="text-2xl font-bold mb-2" id="result-title">Code Redeemed!</h3>
        <p class="text-gray-400 mb-2" id="result-msg"></p>
        <div class="bg-orange-500/10 border border-orange-500/20 rounded-xl p-4 my-4 font-display text-orange-400 text-xl tracking-widest" id="result-code"></div>
        <button onclick="Modal.close('success-modal'); location.reload()" class="btn btn-primary w-full">Done</button>
      </div>
    </div>
  </div>
</div>
<script>
  window.APP_URL = '<?= APP_URL ?>';
</script>
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
<script>
let lastGeneratedCode = '';

document.addEventListener('DOMContentLoaded', loadGeneratedCodes);

// ── Generate Code ───────────────────────────────────────────
async function loadGeneratedCodes() {
  try {
    const data = await ZF.post('<?= APP_URL ?>/ajax/my-generated-codes.php', {});
    renderGeneratedState(data);
  } catch (e) {
    console.error(e);
  }
}

function renderGeneratedState(data) {
  const remaining = data.remaining ?? 0;
  const limit = data.limit ?? 2;

  const badge = document.getElementById('gen-remaining-badge');
  badge.textContent = remaining + ' of ' + limit + ' left today';

  const genBtn = document.getElementById('generate-btn');
  genBtn.disabled = remaining <= 0;
  genBtn.textContent = remaining <= 0 ? '🎲 Limit reached — come back tomorrow' : '🎲 Generate Code';

  const wrap  = document.getElementById('generated-codes-list');
  const empty = document.getElementById('generated-codes-empty');
  const codes = data.codes || [];

  if (codes.length === 0) {
    wrap.innerHTML = '';
    empty.classList.remove('hidden');
    return;
  }
  empty.classList.add('hidden');
  wrap.innerHTML = codes.map(c => `
    <div class="flex items-center justify-between gap-3 py-2.5 border-b border-white/5 last:border-0">
      <div class="min-w-0">
        <div class="font-mono text-sm text-orange-400 tracking-wide truncate">${escHtml(c.code)}</div>
        <div class="text-xs text-gray-500">Generated ${formatGenDate(c.generated_at)}</div>
      </div>
      <button onclick="redeemCode('${escJs(c.code)}')" class="btn btn-secondary btn-sm text-xs flex-shrink-0">Redeem →</button>
    </div>
  `).join('');
}

async function generateCode() {
  const btn = document.getElementById('generate-btn');
  const originalText = btn.textContent;
  btn.disabled = true; btn.textContent = 'Generating…';
  try {
    const data = await ZF.post('<?= APP_URL ?>/ajax/generate-code.php', {});
    lastGeneratedCode = data.code;
    document.getElementById('new-code-box').classList.remove('hidden');
    document.getElementById('new-code-value').textContent = data.code;
    document.getElementById('new-code-redeem-btn').onclick = function () { redeemCode(data.code); };
    if (window.Toast && Toast.success) Toast.success('New code generated!');
    await loadGeneratedCodes();
  } catch (e) {
    if (window.Toast) Toast.error(e.message || 'Could not generate a code');
    btn.textContent = originalText;
    btn.disabled = false;
  }
}

function copyGeneratedCode() {
  if (!lastGeneratedCode) return;
  navigator.clipboard?.writeText(lastGeneratedCode).catch(() => {});
  if (window.Toast && Toast.success) Toast.success('Code copied!');
}

function formatGenDate(iso) {
  try { return new Date(iso.replace(' ', 'T')).toLocaleDateString(undefined, { month: 'short', day: 'numeric' }); }
  catch (e) { return iso; }
}

function escHtml(str) {
  return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function escJs(str) {
  return String(str).replace(/\\/g,'\\\\').replace(/'/g,"\\'");
}

// ── Manual redeem input ─────────────────────────────────────
function updateCodeDisplay() {
  const val = document.getElementById('code-input').value;
  const btn = document.getElementById('redeem-btn');
  const preview = document.getElementById('code-preview');
  preview.textContent = val.padEnd(15, '·').split('').join(' ');
  btn.disabled = val.length !== 15;
}

// redeemCode(code) — pass a code explicitly to redeem a self-generated
// code straight from its "Redeem →" button; called with no argument, it
// reads the manual input field instead (existing behavior unchanged).
async function redeemCode(prefillCode) {
  const input = document.getElementById('code-input');
  const code = (typeof prefillCode === 'string' && prefillCode) ? prefillCode : input.value;
  if (code.length !== 15) return;

  const btn = document.getElementById('redeem-btn');
  const originalBtnText = btn.textContent;
  btn.disabled = true; btn.textContent = 'Verifying...';
  try {
    const data = await ZF.post('<?= APP_URL ?>/ajax/redeem.php', { code });
    document.getElementById('result-icon').textContent = '🎉';
    document.getElementById('result-title').textContent = 'Code Redeemed!';
    document.getElementById('result-msg').textContent = 'Your raffle code has been added to your wallet.';
    document.getElementById('result-code').textContent = code;
    Modal.open('success-modal');
    input.value = '';
    updateCodeDisplay();

    // Referral reward check: if this is the referred user's redemption
    // that unlocks their referrer's free code, this grants it and logs it
    // to audit_logs. No-op if not applicable — never blocks the redeem
    // flow above, so its result/errors are intentionally ignored here.
    ZF.post('<?= APP_URL ?>/ajax/referral-credit.php', {}).catch(() => {});

    // Refresh the self-generated list in case the redeemed code was one
    // of these — it'll now disappear since it's no longer 'assigned'.
    loadGeneratedCodes();
  } catch (e) {
    Toast.error(e.message || 'Redemption failed');
  } finally {
    btn.disabled = false; btn.textContent = originalBtnText;
  }
}
</script>
</body></html>
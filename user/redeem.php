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
  <script src="<?= APP_URL ?>/assets/js/tailwindcss.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
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
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
<script>
function updateCodeDisplay() {
  const val = document.getElementById('code-input').value;
  const btn = document.getElementById('redeem-btn');
  const preview = document.getElementById('code-preview');
  preview.textContent = val.padEnd(15, '·').split('').join(' ');
  btn.disabled = val.length !== 15;
}

async function redeemCode() {
  const code = document.getElementById('code-input').value;
  if (code.length !== 15) return;
  const btn = document.getElementById('redeem-btn');
  btn.disabled = true; btn.textContent = 'Verifying...';
  try {
    const data = await ZF.post('<?= APP_URL ?>/ajax/redeem.php', { code });
    document.getElementById('result-icon').textContent = '🎉';
    document.getElementById('result-title').textContent = 'Code Redeemed!';
    document.getElementById('result-msg').textContent = 'Your raffle code has been added to your wallet.';
    document.getElementById('result-code').textContent = code;
    Modal.open('success-modal');
    document.getElementById('code-input').value = '';
    updateCodeDisplay();
  } catch (e) {
    Toast.error(e.message || 'Redemption failed');
  } finally {
    btn.disabled = false; btn.textContent = 'Redeem Code';
  }
}
</script>
</body></html>

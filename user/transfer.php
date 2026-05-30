<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
$auth = requireUser(); $userId = $auth['id'];
$currentPage = 'transfer'; $pageTitle = 'Transfer Code';
$db = getDB();
$user = $db->prepare("SELECT * FROM users WHERE id=?")->execute([$userId]) ? null : null;
$stmt=$db->prepare("SELECT * FROM users WHERE id=?");$stmt->execute([$userId]);$user=$stmt->fetch();
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="csrf-token" content="<?= generateCsrf() ?>">
  <title><?= e($pageTitle) ?> — <?= APP_NAME ?></title>
  <script src="<?= APP_URL ?>/assets/js/tailwindcss.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
  <style>*{font-family:'Poppins',sans-serif!important}</style>
</head>
<body class="bg-[#0a0f1a] text-white">
<?php include __DIR__ . '/../components/user-sidebar.php'; ?>
<div class="main-content">
  <div class="topbar">
    <button onclick="toggleSidebar()" class="md:hidden text-gray-400 text-2xl mr-3">☰</button>
    <h1 class="text-xl font-bold">Transfer Code</h1>
  </div>
  <div class="p-6 max-w-lg mx-auto">
    <!-- Balance -->
    <div class="card p-5 mb-6 flex items-center gap-4">
      <div class="w-12 h-12 bg-orange-500/15 rounded-xl flex items-center justify-center text-2xl">🎟️</div>
      <div>
        <div class="text-sm text-gray-400">Available Codes</div>
        <div class="text-2xl font-bold text-orange-400"><?= $user['balance'] ?></div>
      </div>
    </div>

    <!-- Step 1: Find recipient -->
    <div id="step1" class="card p-6">
      <h2 class="font-bold text-lg mb-5">Step 1 — Find Recipient</h2>
      <div class="form-group">
        <label class="form-label">Recipient Phone Number</label>
        <div class="flex gap-3">
          <input type="tel" id="recipient-phone" class="form-control" placeholder="08012345678" data-phone>
          <button onclick="lookupRecipient()" class="btn btn-primary px-5">Find</button>
        </div>
      </div>
      <div id="recipient-result" class="hidden mt-4"></div>
    </div>

    <!-- Step 2: Select code + PIN (hidden initially) -->
    <div id="step2" class="hidden card p-6 mt-4">
      <h2 class="font-bold text-lg mb-5">Step 2 — Transfer Details</h2>
      <div class="form-group">
        <label class="form-label">Select Code to Transfer</label>
        <select id="code-select" class="form-control">
          <option value="">Loading codes…</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Transfer PIN</label>
        <div class="flex gap-3 justify-center" id="pin-inputs">
          <?php for($i=0;$i<4;$i++): ?>
          <input type="password" maxlength="1" class="pin-digit w-14 h-14 text-center text-2xl font-bold form-control" inputmode="numeric">
          <?php endfor; ?>
        </div>
        <div class="text-xs text-gray-500 mt-2 text-center">
          No PIN set? <a href="<?= APP_URL ?>/user/profile.php" class="text-orange-400 hover:underline">Set one now →</a>
        </div>
      </div>
      <button onclick="doTransfer()" id="transfer-btn" class="btn btn-primary w-full py-3">Confirm Transfer</button>
    </div>

    <!-- Success Modal -->
    <div class="modal-overlay" id="transfer-modal">
      <div class="modal-box text-center">
        <div class="text-6xl mb-4">✅</div>
        <h3 class="text-xl font-bold mb-2">Transfer Successful!</h3>
        <p class="text-gray-400 mb-2" id="transfer-msg"></p>
        <button onclick="location.reload()" class="btn btn-primary w-full mt-4">Done</button>
      </div>
    </div>
  </div>
</div>
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
<script>
let recipientId = null;
let getPin = null;

async function lookupRecipient() {
  const phone = document.getElementById('recipient-phone').value.trim();
  if (!phone) return Toast.error('Enter a phone number');
  const res = document.getElementById('recipient-result');
  res.innerHTML = '<div class="text-gray-400 text-sm">Searching…</div>';
  res.classList.remove('hidden');
  try {
    const data = await ZF.get('<?= APP_URL ?>/ajax/lookup-user.php', { phone });
    if (data.id == <?= $userId ?>) { res.innerHTML = '<div class="text-red-400 text-sm">You cannot transfer to yourself.</div>'; return; }
    recipientId = data.id;
    res.innerHTML = `<div class="flex items-center gap-3 p-4 bg-green-500/10 border border-green-500/20 rounded-xl">
      <div class="w-10 h-10 bg-green-500/20 rounded-full flex items-center justify-center font-bold text-green-400">${data.name[0]}</div>
      <div><div class="font-semibold">${data.name}</div><div class="text-xs text-gray-400">${data.phone}</div></div>
      <div class="ml-auto badge badge-success">Found ✓</div>
    </div>`;
    await loadCodes();
    document.getElementById('step2').classList.remove('hidden');
    getPin = initPinInput('pin-inputs');
  } catch(e) { res.innerHTML = `<div class="text-red-400 text-sm">${e.message}</div>`; }
}

async function loadCodes() {
  const data = await ZF.get('<?= APP_URL ?>/ajax/codes.php', { filter: 'redeemed', page: 1, limit: 100 });
  const sel = document.getElementById('code-select');
  sel.innerHTML = '';
  if (!data.items?.length) { sel.innerHTML = '<option>No active codes available</option>'; return; }
  data.items.forEach(c => {
    const opt = document.createElement('option');
    opt.value = c.id; opt.textContent = c.code;
    sel.appendChild(opt);
  });
}

async function doTransfer() {
  const codeId = document.getElementById('code-select').value;
  const pin = getPin ? getPin() : '';
  if (!codeId) return Toast.error('Select a code');
  if (pin.length !== 4) return Toast.error('Enter your 4-digit PIN');
  const btn = document.getElementById('transfer-btn');
  btn.disabled = true; btn.textContent = 'Processing…';
  try {
    const data = await ZF.post('<?= APP_URL ?>/ajax/transfer.php', { code_id: codeId, to_user: recipientId, pin });
    document.getElementById('transfer-msg').textContent = `Code ${data.code} sent successfully.`;
    Modal.open('transfer-modal');
  } catch(e) {
    Toast.error(e.message);
  } finally { btn.disabled=false; btn.textContent='Confirm Transfer'; }
}
</script>
</body></html>

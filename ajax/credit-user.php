<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
$auth=requireVendor();$vendorId=$auth['id'];
$vPage='credit';
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="csrf-token" content="<?= generateCsrf() ?>">
  <title>Credit User — <?= APP_NAME ?></title>
  <script src="<?= APP_URL ?>/assets/js/app.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
  <style>*{font-family:'Poppins',sans-serif!important}</style>
</head>
<body class="bg-[#0a0f1a] text-white">
<?php include __DIR__ . '/../components/vendor-sidebar.php'; ?>
<div class="main-content">
  <div class="topbar">
    <button onclick="toggleSidebar()" class="md:hidden text-gray-400 text-2xl mr-3">☰</button>
    <h1 class="text-xl font-bold">Credit User</h1>
  </div>
  <div class="p-6 max-w-lg mx-auto">
    <div class="card-glow p-8">
      <div class="text-4xl mb-4 text-center">🎟️</div>
      <h2 class="text-xl font-bold mb-6 text-center">Distribute Raffle Code</h2>

      <!-- Step 1 -->
      <div id="step1">
        <div class="form-group">
          <label class="form-label">Customer Phone Number</label>
          <div class="flex gap-3">
            <input type="tel" id="phone-input" class="form-control" placeholder="08012345678" data-phone>
            <button onclick="findUser()" class="btn btn-primary px-5">Find</button>
          </div>
        </div>
        <div id="user-result" class="hidden mt-4"></div>
      </div>

      <!-- Step 2 hidden -->
      <div id="step2" class="hidden mt-6">
        <div class="form-group">
          <label class="form-label">Select Code to Distribute</label>
          <select id="code-select" class="form-control"><option>Loading…</option></select>
        </div>
        <button onclick="distributeCode()" id="dist-btn" class="btn btn-primary w-full py-3">Distribute Code to User</button>
      </div>
    </div>

    <!-- Success modal -->
    <div class="modal-overlay" id="success-modal">
      <div class="modal-box text-center">
        <div class="text-6xl mb-4">✅</div>
        <h3 class="text-xl font-bold mb-2">Code Distributed!</h3>
        <p class="text-gray-400" id="success-msg"></p>
        <button onclick="location.reload()" class="btn btn-primary w-full mt-5">Done</button>
      </div>
    </div>
  </div>
</div>
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
<script>
let targetUser=null;

async function findUser() {
  const phone=document.getElementById('phone-input').value;
  const res=document.getElementById('user-result');
  res.classList.remove('hidden');
  res.innerHTML='<div class="text-gray-400 text-sm">Searching…</div>';
  try {
    const data=await ZF.get('<?= APP_URL ?>/ajax/lookup-user.php',{phone});
    targetUser=data;
    res.innerHTML=`<div class="p-4 bg-green-500/10 border border-green-500/20 rounded-xl flex items-center gap-3">
      <div class="w-10 h-10 bg-green-500/20 rounded-full flex items-center justify-center font-bold text-green-400 text-lg">${data.name[0]}</div>
      <div><div class="font-semibold">${data.name}</div><div class="text-xs text-gray-400">${data.phone}</div></div>
      <span class="badge badge-success ml-auto">Found ✓</span>
    </div>`;
    await loadCodes();
    document.getElementById('step2').classList.remove('hidden');
  } catch(e) {
    res.innerHTML=`<div class="text-red-400 text-sm p-3 bg-red-500/10 rounded-xl">${e.message}</div>`;
    document.getElementById('step2').classList.add('hidden');
  }
}

async function loadCodes() {
  const sel=document.getElementById('code-select');
  sel.innerHTML='<option>Loading…</option>';
  try {
    const data=await ZF.get('<?= APP_URL ?>/ajax/vendor-codes.php',{filter:'assigned',page:1,limit:100});
    sel.innerHTML='';
    if (!data.items?.length) { sel.innerHTML='<option>No codes in inventory</option>'; return; }
    data.items.forEach(c=>{ const o=document.createElement('option'); o.value=c.id; o.textContent=c.code; sel.appendChild(o); });
  } catch(e) { sel.innerHTML='<option>Error loading codes</option>'; }
}

async function distributeCode() {
  if (!targetUser) return Toast.error('Find a user first');
  const codeId=document.getElementById('code-select').value;
  if (!codeId) return Toast.error('Select a code');
  const btn=document.getElementById('dist-btn');
  btn.disabled=true; btn.textContent='Distributing…';
  try {
    const data=await ZF.post('<?= APP_URL ?>/ajax/vendor-credit.php',{code_id:codeId,user_id:targetUser.id});
    document.getElementById('success-msg').textContent=`Code ${data.code} sent to ${targetUser.name}`;
    Modal.open('success-modal');
  } catch(e) { Toast.error(e.message); }
  finally { btn.disabled=false; btn.textContent='Distribute Code to User'; }
}
</script>
</body></html>

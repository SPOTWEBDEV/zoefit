<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
$auth=requireVendor();$vendorId=$auth['id'];
$db=getDB();
$stmt=$db->prepare("SELECT * FROM vendors WHERE id=?");$stmt->execute([$vendorId]);$vendor=$stmt->fetch();

$total    =$db->prepare("SELECT COUNT(*) FROM codes WHERE assigned_vendor=?");$total->execute([$vendorId]);$total=$total->fetchColumn();
$remaining=$db->prepare("SELECT COUNT(*) FROM codes WHERE assigned_vendor=? AND status='assigned'");$remaining->execute([$vendorId]);$remaining=$remaining->fetchColumn();
$distributed=$db->prepare("SELECT COUNT(*) FROM codes WHERE assigned_vendor=? AND status IN ('distributed','redeemed','reserved','used')");$distributed->execute([$vendorId]);$distributed=$distributed->fetchColumn();

$recent=$db->prepare("SELECT r.*, u.full_name, u.phone, c.code FROM code_redemptions r JOIN users u ON r.user_id=u.id JOIN codes c ON r.code_id=c.id WHERE c.assigned_vendor=? ORDER BY r.redeemed_at DESC LIMIT 10");
$recent->execute([$vendorId]);$recent=$recent->fetchAll();

$vPage='dashboard';
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Vendor Dashboard — <?= APP_NAME ?></title>
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
    <div>
      <div class="font-semibold"><?= e($vendor['full_name']) ?></div>
      <div class="text-xs text-gray-400"><?= e($vendor['business_name']??'Vendor') ?></div>
    </div>
  </div>
  <div class="p-6">
    <!-- Stats -->
    <div class="grid grid-cols-2 md:grid-cols-3 gap-4 mb-6">
      <div class="balance-card">
        <div class="text-sm text-orange-200">Code Inventory</div>
        <div class="text-4xl font-black mt-2"><?= $vendor['code_balance'] ?></div>
        <div class="text-xs text-orange-200 mt-1">Ready to distribute</div>
      </div>
      <div class="card p-5">
        <div class="text-sm text-gray-400">Total Assigned</div>
        <div class="text-3xl font-bold text-blue-400 mt-2"><?= $total ?></div>
      </div>
      <div class="card p-5">
        <div class="text-sm text-gray-400">Distributed</div>
        <div class="text-3xl font-bold text-green-400 mt-2"><?= $distributed ?></div>
      </div>
    </div>

    <!-- Quick Action -->
    <div class="card p-6 mb-6">
      <h2 class="font-bold text-lg mb-4">Quick Credit User</h2>
      <div class="flex gap-3">
        <input type="tel" id="quick-phone" class="form-control" placeholder="Customer phone: 0812345678" data-phone>
        <button onclick="quickLookup()" class="btn btn-primary px-6">Find User</button>
      </div>
      <div id="quick-result" class="mt-4 hidden"></div>
    </div>

    <!-- Recent Activity -->
    <div class="card">
      <div class="p-5 border-b border-white/5 font-bold">Recent Distributions</div>
      <?php if($recent): foreach($recent as $r): ?>
      <div class="flex items-center gap-4 p-4 border-b border-white/5 last:border-0">
        <div class="w-9 h-9 bg-orange-500/15 rounded-xl flex items-center justify-center font-bold text-orange-400 text-sm"><?= strtoupper($r['full_name'][0]) ?></div>
        <div class="flex-1">
          <div class="font-medium text-sm"><?= e($r['full_name']) ?></div>
          <div class="text-xs text-gray-400"><?= e(formatPhone($r['phone'])) ?> · <span class="font-mono text-orange-400"><?= e($r['code']) ?></span></div>
        </div>
        <div class="text-xs text-gray-500"><?= date('M j, g:ia',strtotime($r['redeemed_at'])) ?></div>
      </div>
      <?php endforeach; else: ?>
      <div class="p-8 text-center text-gray-500">No distributions yet</div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Credit Modal -->
<div class="modal-overlay" id="credit-modal">
  <div class="modal-box">
    <h3 class="text-xl font-bold mb-4">Credit User</h3>
    <div id="credit-user-info" class="mb-4"></div>
    <div class="form-group">
      <label class="form-label">Select Code to Credit</label>
      <select id="credit-code-sel" class="form-control"><option>Loading…</option></select>
    </div>
    <div class="flex gap-3">
      <button data-close-modal="credit-modal" class="btn btn-secondary flex-1">Cancel</button>
      <button onclick="creditUser()" class="btn btn-primary flex-1">Credit Code</button>
    </div>
  </div>
</div>

<script src="<?= APP_URL ?>/assets/js/app.js"></script>
<script>
let targetUserId=null;

async function quickLookup() {
  const phone=document.getElementById('quick-phone').value.trim();
  if (!phone) return Toast.error('Enter a phone number');
  const res=document.getElementById('quick-result');
  res.classList.remove('hidden');
  res.innerHTML='<div class="text-gray-400 text-sm">Searching…</div>';
  try {
    const data=await ZF.get('<?= APP_URL ?>/ajax/lookup-user.php',{phone});
    targetUserId=data.id;
    res.innerHTML=`<div class="flex items-center gap-3 p-4 bg-green-500/10 border border-green-500/20 rounded-xl">
      <div class="w-10 h-10 bg-green-500/20 rounded-full flex items-center justify-center font-bold text-green-400 text-lg">${data.name[0]}</div>
      <div class="flex-1"><div class="font-semibold">${data.name}</div><div class="text-xs text-gray-400">${data.phone}</div></div>
      <button onclick="openCreditModal(${data.id},'${data.name}')" class="btn btn-primary btn-sm">Credit →</button>
    </div>`;
  } catch(e) { res.innerHTML=`<div class="text-red-400 text-sm p-3 bg-red-500/10 rounded-xl">${e.message}</div>`; }
}

async function openCreditModal(userId,name) {
  document.getElementById('credit-user-info').innerHTML=`<div class="flex items-center gap-3 p-3 bg-white/5 rounded-xl"><div class="w-10 h-10 bg-orange-500/20 rounded-full flex items-center justify-center font-bold text-orange-400">${name[0]}</div><div class="font-semibold">${name}</div></div>`;
  targetUserId=userId;
  // Load available codes
  const sel=document.getElementById('credit-code-sel');
  sel.innerHTML='<option>Loading…</option>';
  const data=await ZF.get('<?= APP_URL ?>/ajax/vendor-codes.php',{filter:'assigned',page:1,limit:50});
  sel.innerHTML='';
  if (!data.items?.length) { sel.innerHTML='<option>No codes available</option>'; }
  else data.items.forEach(c=>{ const o=document.createElement('option'); o.value=c.id; o.textContent=c.code; sel.appendChild(o); });
  Modal.open('credit-modal');
}

async function creditUser() {
  const codeId=document.getElementById('credit-code-sel').value;
  if (!codeId||!targetUserId) return Toast.error('Missing info');
  try {
    const data=await ZF.post('<?= APP_URL ?>/ajax/vendor-credit.php',{code_id:codeId,user_id:targetUserId});
    Modal.close('credit-modal');
    Toast.success('Code credited successfully!');
    setTimeout(()=>location.reload(),1200);
  } catch(e) { Toast.error(e.message); }
}
</script>
</body></html>

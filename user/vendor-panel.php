<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
$auth = requireUser(); $userId = $auth['id'];
$db   = getDB();
$stmt = $db->prepare("SELECT * FROM users WHERE id=? AND is_vendor=1 AND vendor_status='active'");
$stmt->execute([$userId]); $user = $stmt->fetch();
if (!$user) redirect(APP_URL.'/user/vendor-apply.php');

$tab = $_GET['tab'] ?? 'overview';
$msg = $err = '';

// ── Generate API Keys ─────────────────────────────────────
if (isPost() && verifyCsrf($_POST[CSRF_TOKEN_NAME]??'') && ($_POST['action']??'')==='generate_keys') {
  $pubKey = 'zf_pub_' . bin2hex(random_bytes(16));        // 39 chars
  $secKey = 'zf_sec_' . bin2hex(random_bytes(32));        // 71 chars
  $db->prepare("UPDATE users SET vendor_public_key=?, vendor_secret_key=? WHERE id=?")
     ->execute([$pubKey, password_hash($secKey, PASSWORD_BCRYPT), $userId]);
  // Show secret key ONCE — store in session temporarily
  $_SESSION['vendor_new_secret'] = $secKey;
  auditLog('user',$userId,'generate_api_keys','Vendor generated new API keys');
  $msg = 'New API keys generated. Copy your secret key now — it will not be shown again.';
  $tab = 'keys';
  $stmt=$db->prepare("SELECT * FROM users WHERE id=?");$stmt->execute([$userId]);$user=$stmt->fetch();
}

// ── Credit a customer ─────────────────────────────────────
if (isPost() && verifyCsrf($_POST[CSRF_TOKEN_NAME]??'') && ($_POST['action']??'')==='credit') {
  $phone  = trim($_POST['phone']??'');
  $codeId = (int)($_POST['code_id']??0);
  $normalized = normalizePhone($phone);
  $recip = $db->prepare("SELECT id,full_name,status FROM users WHERE phone=?");
  $recip->execute([$normalized]); $recip=$recip->fetch();
  if (!$recip||$recip['status']!=='active') { $err='User not found or inactive.'; }
  elseif (!$codeId) { $err='Select a code.'; }
  else {
    $code=$db->prepare("SELECT * FROM codes WHERE id=? AND assigned_vendor=? AND status='assigned' FOR UPDATE");
    // need transaction
    $db->beginTransaction();
    try {
      $code->execute([$codeId,$userId]); $code=$code->fetch();
      if (!$code) throw new \Exception('Code not available.');
      $db->prepare("UPDATE codes SET status='redeemed',current_owner=?,redeemed_at=NOW() WHERE id=?")->execute([$recip['id'],$codeId]);
      $db->prepare("INSERT INTO code_redemptions (code_id,user_id,vendor_id) VALUES (?,?,?)")->execute([$codeId,$recip['id'],$userId]);
      $db->prepare("UPDATE users SET balance=balance+1 WHERE id=?")->execute([$recip['id']]);
      $db->prepare("UPDATE users SET vendor_code_balance=vendor_code_balance-1 WHERE id=?")->execute([$userId]);
      $db->prepare("INSERT INTO transactions (user_id,type,category,amount,code_id,description) VALUES (?,?,?,?,?,?)")
         ->execute([$recip['id'],'credit','vendor_credit',1,$codeId,'Code credited by vendor '.$user['full_name']]);
      createNotification($recip['id'],'Code Received','A vendor credited a raffle code to your wallet.','vendor');
      auditLog('user',$userId,'vendor_credit_customer','Code '.$code['code'].' credited to user '.$recip['id'],'code',$codeId);
      $db->commit();
      $msg = '✅ Code credited to '.$recip['full_name'].' successfully.';
      $tab='credit';
      $stmt=$db->prepare("SELECT * FROM users WHERE id=?");$stmt->execute([$userId]);$user=$stmt->fetch();
    } catch(\Exception $e) { $db->rollBack(); $err=$e->getMessage(); }
  }
}

// ── Inventory ─────────────────────────────────────────────
$invStmt = $db->prepare("SELECT status, COUNT(*) as cnt FROM codes WHERE assigned_vendor=? GROUP BY status");
$invStmt->execute([$userId]); $inv=['assigned'=>0,'distributed'=>0,'redeemed'=>0,'used'=>0];
foreach($invStmt->fetchAll() as $r) $inv[$r['status']] = $r['cnt'];

// ── Recent distributions ──────────────────────────────────
$recent=$db->prepare("SELECT r.redeemed_at, u.full_name, u.phone, c.code FROM code_redemptions r JOIN users u ON r.user_id=u.id JOIN codes c ON r.code_id=c.id WHERE r.vendor_id=? ORDER BY r.redeemed_at DESC LIMIT 8");
$recent->execute([$userId]); $recent=$recent->fetchAll();

// ── Available codes for dropdown ─────────────────────────
$avail=$db->prepare("SELECT id,code FROM codes WHERE assigned_vendor=? AND status='assigned' LIMIT 200");
$avail->execute([$userId]); $avail=$avail->fetchAll();

$newSecret = $_SESSION['vendor_new_secret'] ?? null;
unset($_SESSION['vendor_new_secret']);

$currentPage='vendor-panel'; $pageTitle='Vendor Panel';
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="csrf-token" content="<?= generateCsrf() ?>">
  <title><?= e($pageTitle) ?> — <?= APP_NAME ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
  <style>*{font-family:'Poppins',sans-serif!important}.key-box{font-family:'Courier New',monospace!important;word-break:break-all;}</style>
</head>
<body class="bg-[#0a0f1a] text-white">
<?php include __DIR__ . '/../components/user-sidebar.php'; ?>
<div class="main-content">
  <div class="topbar">
    <button onclick="toggleSidebar()" class="md:hidden text-gray-400 text-2xl mr-3">☰</button>
    <div>
      <h1 class="text-lg font-bold">Vendor Panel</h1>
      <div class="text-xs text-purple-400"><?= e($user['vendor_business_name']??'') ?></div>
    </div>
  </div>
  <div class="p-4 md:p-6 pb-24 md:pb-6">

    <?php if($msg): ?><div class="bg-green-500/10 border border-green-500/30 text-green-400 rounded-xl p-4 mb-5 text-sm"><?= e($msg) ?></div><?php endif; ?>
    <?php if($err): ?><div class="bg-red-500/10 border border-red-500/30 text-red-400 rounded-xl p-4 mb-5 text-sm"><?= e($err) ?></div><?php endif; ?>

    <!-- Tab Nav -->
    <div class="flex gap-2 mb-5 overflow-x-auto pb-1">
      <?php foreach(['overview'=>'📊 Overview','credit'=>'🎟️ Credit User','keys'=>'🔑 API Keys','history'=>'📋 History'] as $t=>$l): ?>
      <a href="?tab=<?= $t ?>" class="btn btn-sm <?= $tab===$t?'btn-primary':'btn-secondary' ?> whitespace-nowrap"><?= $l ?></a>
      <?php endforeach; ?>
    </div>

    <!-- ── OVERVIEW ───────────────────────────────────────── -->
    <?php if($tab==='overview'): ?>
    <div class="balance-card mb-5">
      <div class="text-sm text-orange-200">Code Inventory</div>
      <div class="text-4xl font-black mt-1"><?= $user['vendor_code_balance'] ?></div>
      <div class="text-xs text-orange-200 mt-1">Available to distribute</div>
    </div>
    <div class="grid grid-cols-2 gap-3 mb-5">
      <?php $ics=[['Assigned to You','assigned','text-blue-400'],['Distributed','redeemed','text-green-400'],['In Draws','reserved','text-yellow-400'],['Consumed','used','text-gray-400']]; ?>
      <?php foreach($ics as $ic): ?>
      <div class="card p-4 text-center">
        <div class="text-2xl font-black <?= $ic[2] ?>"><?= $inv[$ic[1]]??0 ?></div>
        <div class="text-xs text-gray-500 mt-1"><?= $ic[0] ?></div>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="card">
      <div class="p-4 border-b border-white/5 font-semibold text-sm">Recent Distributions</div>
      <?php foreach(array_slice($recent,0,5) as $r): ?>
      <div class="flex items-center gap-3 p-4 border-b border-white/5 last:border-0">
        <div class="w-9 h-9 bg-orange-500/15 rounded-xl flex items-center justify-center font-bold text-orange-400"><?= strtoupper($r['full_name'][0]) ?></div>
        <div class="flex-1 min-w-0">
          <div class="text-sm font-medium"><?= e($r['full_name']) ?></div>
          <div class="text-xs text-gray-400 font-mono"><?= e($r['code']) ?></div>
        </div>
        <div class="text-xs text-gray-500"><?= date('M j, g:ia',strtotime($r['redeemed_at'])) ?></div>
      </div>
      <?php endforeach; ?>
      <?php if(!$recent): ?><div class="p-8 text-center text-gray-500 text-sm">No distributions yet</div><?php endif; ?>
    </div>

    <!-- ── CREDIT USER ────────────────────────────────────── -->
    <?php elseif($tab==='credit'): ?>
    <div class="card p-6 max-w-lg mx-auto">
      <h2 class="font-bold text-lg mb-5">Credit Raffle Code to Customer</h2>
      <form method="POST">
        <?= csrfField() ?><input type="hidden" name="action" value="credit">
        <div class="form-group">
          <label class="form-label">Customer Phone Number</label>
          <div class="flex gap-3">
            <input type="tel" name="phone" id="credit-phone" class="form-control" placeholder="08012345678" data-phone>
            <button type="button" onclick="lookupCustomer()" class="btn btn-secondary px-4 flex-shrink-0">Find</button>
          </div>
          <div id="customer-info" class="mt-3 hidden"></div>
        </div>
        <div class="form-group">
          <label class="form-label">Select Code to Distribute</label>
          <select name="code_id" class="form-control">
            <option value="">Select a code…</option>
            <?php foreach($avail as $a): ?>
            <option value="<?= $a['id'] ?>"><?= e($a['code']) ?></option>
            <?php endforeach; ?>
          </select>
          <div class="text-xs text-gray-500 mt-1"><?= count($avail) ?> codes available</div>
        </div>
        <button type="submit" class="btn btn-primary w-full py-3">Credit Code to Customer</button>
      </form>
    </div>

    <!-- ── API KEYS ───────────────────────────────────────── -->
    <?php elseif($tab==='keys'): ?>
    <div class="max-w-xl mx-auto space-y-4">
      <?php if($newSecret): ?>
      <div class="bg-green-500/10 border-2 border-green-500/40 rounded-xl p-5">
        <div class="flex items-center gap-2 text-green-400 font-bold mb-3">⚠️ Copy Your Secret Key NOW</div>
        <div class="text-xs text-gray-300 mb-3">This is the <strong class="text-white">only time</strong> your secret key will be displayed. Store it securely.</div>
        <div class="key-box bg-black/40 rounded-lg p-3 text-green-400 text-xs break-all select-all mb-3"><?= e($newSecret) ?></div>
        <button onclick="copyToClipboard('<?= e($newSecret) ?>')" class="btn btn-success btn-sm w-full">📋 Copy Secret Key</button>
      </div>
      <?php endif; ?>

      <div class="card p-6">
        <h2 class="font-bold text-lg mb-5">🔑 API Keys</h2>
        <?php if($user['vendor_public_key']): ?>
        <div class="space-y-4">
          <div>
            <label class="form-label">Public Key</label>
            <div class="flex gap-2">
              <div class="key-box flex-1 bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-sm text-cyan-400 break-all"><?= e($user['vendor_public_key']) ?></div>
              <button onclick="copyToClipboard('<?= e($user['vendor_public_key']) ?>')" class="btn btn-secondary btn-sm flex-shrink-0">📋</button>
            </div>
            <div class="text-xs text-gray-500 mt-1">Share this publicly — used to identify your vendor account</div>
          </div>
          <div>
            <label class="form-label">Secret Key</label>
            <div class="key-box bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-sm text-gray-500">
              <?= $newSecret ? e($newSecret) : '••••••••••••••••••••••••••••••••••••' ?>
            </div>
            <div class="text-xs text-gray-500 mt-1">Never share this key. Used for authenticated API calls.</div>
          </div>
        </div>
        <div class="mt-5 p-4 bg-yellow-500/10 border border-yellow-500/20 rounded-xl text-xs text-yellow-300">
          ⚠️ Generating new keys will <strong>invalidate your existing keys</strong> immediately.
        </div>
        <?php else: ?>
        <div class="text-center py-6">
          <div class="text-4xl mb-3">🔑</div>
          <div class="text-gray-400 mb-4">No API keys generated yet. Generate a key pair to integrate ZoeFeeds with your systems.</div>
        </div>
        <?php endif; ?>
        <form method="POST" class="mt-4" onsubmit="return confirm('<?= $user['vendor_public_key']?'Regenerate keys? Your existing keys will stop working immediately.':'Generate API keys?' ?>')">
          <?= csrfField() ?><input type="hidden" name="action" value="generate_keys">
          <button type="submit" class="btn btn-primary w-full py-3">
            <?= $user['vendor_public_key'] ? '🔄 Regenerate Keys' : '⚡ Generate API Keys' ?>
          </button>
        </form>
      </div>

      <!-- API Usage Guide -->
      <div class="card p-6">
        <h3 class="font-bold mb-3">📖 API Usage</h3>
        <div class="space-y-3 text-sm text-gray-400">
          <div><strong class="text-white">Authenticate:</strong> Send <code class="text-orange-400">X-ZF-Public-Key</code> and <code class="text-orange-400">X-ZF-Secret-Key</code> headers</div>
          <div><strong class="text-white">Credit endpoint:</strong> <code class="text-cyan-400">POST <?= APP_URL ?>/api/vendor/credit</code></div>
          <div><strong class="text-white">Inventory:</strong> <code class="text-cyan-400">GET <?= APP_URL ?>/api/vendor/inventory</code></div>
        </div>
      </div>
    </div>

    <!-- ── HISTORY ─────────────────────────────────────────── -->
    <?php elseif($tab==='history'): ?>
    <div id="hist-container" class="space-y-3"></div>
    <div id="hist-loader">
      <?php for($i=0;$i<4;$i++): ?><div class="skeleton h-16 rounded-xl mb-2"></div><?php endfor; ?>
    </div>
    <?php endif; ?>

  </div>
</div>
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
<script>
async function lookupCustomer() {
  const phone = document.getElementById('credit-phone').value;
  const el = document.getElementById('customer-info');
  el.classList.remove('hidden');
  el.innerHTML = '<div class="text-gray-400 text-sm">Searching…</div>';
  try {
    const d = await ZF.get('<?= APP_URL ?>/ajax/lookup-user.php', {phone});
    el.innerHTML = `<div class="p-3 bg-green-500/10 border border-green-500/20 rounded-xl flex items-center gap-3">
      <div class="w-9 h-9 bg-green-500/20 rounded-full flex items-center justify-center font-bold text-green-400">${d.name[0]}</div>
      <div><div class="font-semibold text-sm">${d.name}</div><div class="text-xs text-gray-400">${d.phone}</div></div>
      <span class="badge badge-success ml-auto">Found ✓</span>
    </div>`;
  } catch(e) { el.innerHTML=`<div class="text-red-400 text-sm p-3 bg-red-500/10 rounded-xl">${e.message}</div>`; }
}

function copyToClipboard(text) {
  navigator.clipboard.writeText(text).then(() => Toast.success('Copied to clipboard!'));
}

<?php if ($tab==='history'): ?>
new InfiniteScroll({
  container: document.getElementById('hist-container'),
  loader: document.getElementById('hist-loader'),
  fetchUrl: '<?= APP_URL ?>/ajax/vendor-history.php',
  params: { tab: 'distribution' },
  renderItem: r => `<div class="card p-4 flex items-center gap-4">
    <div class="w-10 h-10 rounded-xl bg-orange-500/10 flex items-center justify-center text-xl">🎟️</div>
    <div class="flex-1 min-w-0"><div class="font-medium text-sm">${r.user_name}</div><div class="text-xs text-gray-400">${r.phone} · <span class="font-mono text-orange-400">${r.code}</span></div></div>
    <div class="text-xs text-gray-500 flex-shrink-0">${r.date}</div>
  </div>`
});
<?php endif; ?>
</script>
</body></html>

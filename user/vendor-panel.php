<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
$auth = requireUser(); $userId = $auth['id'];
$db   = getDB();

// Only active vendors can access this page
$stmt = $db->prepare("SELECT * FROM users WHERE id=? AND is_vendor=1 AND vendor_status='active'");
$stmt->execute([$userId]); $user = $stmt->fetch();
if (!$user) redirect(APP_URL.'/user/vendor-apply.php');

$tab = $_GET['tab'] ?? 'overview';
$msg = $err = '';

// ── Generate API Keys ──────────────────────────────────────
if (isPost() && verifyCsrf($_POST[CSRF_TOKEN_NAME]??'') && ($_POST['action']??'')==='generate_keys') {
  $pubKey = 'zf_pub_'.bin2hex(random_bytes(16));
  $secKey = 'zf_sec_'.bin2hex(random_bytes(32));
  $db->prepare("UPDATE users SET vendor_public_key=?, vendor_secret_key=? WHERE id=?")
     ->execute([$pubKey, password_hash($secKey, PASSWORD_BCRYPT), $userId]);
  $_SESSION['vendor_new_secret'] = $secKey;
  auditLog('user', $userId, 'generate_api_keys', 'Vendor API keys generated');
  $msg = 'New API keys generated. Copy your secret key now — it will not be shown again.';
  $tab = 'keys';
  $stmt = $db->prepare("SELECT * FROM users WHERE id=?"); $stmt->execute([$userId]); $user = $stmt->fetch();
}

// ── Inventory stats ────────────────────────────────────────
$invRaw = $db->prepare("SELECT status, COUNT(*) cnt FROM codes WHERE assigned_vendor=? GROUP BY status");
$invRaw->execute([$userId]);
$inv = ['assigned'=>0,'redeemed'=>0,'reserved'=>0,'used'=>0];
foreach ($invRaw->fetchAll() as $r) { if (isset($inv[$r['status']])) $inv[$r['status']] = $r['cnt']; }

// ── Recent distributions ───────────────────────────────────
$recent = $db->prepare("SELECT r.redeemed_at, u.full_name, u.phone, c.code
  FROM code_redemptions r
  JOIN users u ON r.user_id=u.id
  JOIN codes c ON r.code_id=c.id
  WHERE r.vendor_id=? ORDER BY r.redeemed_at DESC LIMIT 6");
$recent->execute([$userId]); $recent = $recent->fetchAll();

$newSecret = $_SESSION['vendor_new_secret'] ?? null;
unset($_SESSION['vendor_new_secret']);

$currentPage = 'vendor-panel'; $pageTitle = 'Vendor Panel';
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="csrf-token" content="<?= generateCsrf() ?>">
  <title><?= e($pageTitle) ?> — <?= APP_NAME ?></title>
  <script src="<?= APP_URL ?>/assets/js/tailwind.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
  <style>*{font-family:'Poppins',sans-serif!important}code,pre{font-family:'Courier New',monospace!important}
  @media print{body>*:not(#vendor-print-area){display:none!important}#vendor-print-area{display:block!important}@page{margin:10mm;size:A4}}
  </style>
</head>
<body class="bg-[#0a0f1a] text-white">
<?php include __DIR__ . '/../components/user-sidebar.php'; ?>
<div class="main-content">
  <div class="topbar">
    <button onclick="toggleSidebar()" class="md:hidden text-gray-400 text-2xl mr-3">☰</button>
    <div>
      <h1 class="text-lg font-bold">Vendor Panel</h1>
      <div class="text-xs text-purple-400 font-medium"><?= e($user['vendor_business_name'] ?? '') ?></div>
    </div>
  </div>

  <div class="p-4 md:p-6 pb-24 md:pb-6">

    <?php if($msg): ?><div class="bg-green-500/10 border border-green-500/30 text-green-400 rounded-xl p-4 mb-5 text-sm"><?= e($msg) ?></div><?php endif; ?>
    <?php if($err): ?><div class="bg-red-500/10 border border-red-500/30 text-red-400 rounded-xl p-4 mb-5 text-sm"><?= e($err) ?></div><?php endif; ?>

    <!-- Tab Nav -->
    <div class="flex gap-2 mb-5 overflow-x-auto pb-1">
      <?php foreach(['overview'=>'📊 Overview','codes'=>'🎟️ My Codes','keys'=>'🔑 API Keys','history'=>'📋 History'] as $t=>$l): ?>
      <a href="?tab=<?= $t ?>" class="btn btn-sm <?= $tab===$t?'btn-primary':'btn-secondary' ?> whitespace-nowrap"><?= $l ?></a>
      <?php endforeach; ?>
    </div>

    <!-- ── OVERVIEW ──────────────────────────────────────── -->
    <?php if ($tab === 'overview'): ?>

    <div class="balance-card mb-5">
      <div class="text-sm text-orange-200">Available Code Inventory</div>
      <div class="text-5xl font-black mt-1"><?= number_format($user['vendor_code_balance']) ?></div>
      <div class="text-xs text-orange-200 mt-1">codes assigned to your account</div>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-5">
      <?php foreach([
        ['Available', $user['vendor_code_balance'], 'text-orange-400'],
        ['Distributed', $inv['redeemed'],            'text-green-400'],
        ['In Draws',    $inv['reserved'],             'text-yellow-400'],
        ['Used Up',     $inv['used'],                 'text-gray-500'],
      ] as $ic): ?>
      <div class="card p-4 text-center">
        <div class="text-2xl font-black <?= $ic[2] ?>"><?= number_format($ic[1]) ?></div>
        <div class="text-xs text-gray-500 mt-1"><?= $ic[0] ?></div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Quick action cards -->
    <div class="grid sm:grid-cols-3 gap-3 mb-5">
      <a href="?tab=codes" class="card p-4 flex items-center gap-3 hover:border-orange-500/30 transition-colors">
        <div class="w-10 h-10 bg-orange-500/15 rounded-xl flex items-center justify-center text-xl">🖨️</div>
        <div><div class="font-semibold text-sm">View &amp; Print Codes</div><div class="text-xs text-gray-500">Select &amp; print ticket slips</div></div>
      </a>
      <a href="<?= APP_URL ?>/user/vendor-transfer.php" class="card p-4 flex items-center gap-3 hover:border-cyan-500/30 transition-colors">
        <div class="w-10 h-10 bg-cyan-500/15 rounded-xl flex items-center justify-center text-xl">↔️</div>
        <div><div class="font-semibold text-sm">Transfer to Vendor</div><div class="text-xs text-gray-500">Send codes to another vendor</div></div>
      </a>
      <a href="?tab=keys" class="card p-4 flex items-center gap-3 hover:border-purple-500/30 transition-colors">
        <div class="w-10 h-10 bg-purple-500/15 rounded-xl flex items-center justify-center text-xl">🔑</div>
        <div><div class="font-semibold text-sm">API Keys</div><div class="text-xs text-gray-500">Integration access</div></div>
      </a>
    </div>

    <!-- Recent distributions -->
    <div class="card">
      <div class="flex items-center justify-between px-5 pt-5 pb-3 border-b border-white/5">
        <h2 class="font-bold text-sm">Recent Distributions</h2>
        <a href="?tab=history" class="text-orange-400 text-xs hover:underline">View all →</a>
      </div>
      <?php foreach ($recent as $r): ?>
      <div class="flex items-center gap-3 px-5 py-3 border-b border-white/5 last:border-0">
        <div class="w-9 h-9 bg-orange-500/15 rounded-xl flex items-center justify-center font-bold text-orange-400 flex-shrink-0">
          <?= strtoupper($r['full_name'][0]) ?>
        </div>
        <div class="flex-1 min-w-0">
          <div class="text-sm font-medium truncate"><?= e($r['full_name']) ?></div>
          <code class="text-xs text-orange-400"><?= e($r['code']) ?></code>
        </div>
        <div class="text-xs text-gray-500 flex-shrink-0"><?= date('M j, g:ia', strtotime($r['redeemed_at'])) ?></div>
      </div>
      <?php endforeach; ?>
      <?php if (!$recent): ?>
      <div class="p-8 text-center text-gray-500 text-sm">No distributions yet. Codes are distributed when users redeem them.</div>
      <?php endif; ?>
    </div>

    <!-- ── MY CODES (download) ───────────────────────────── -->
    <?php elseif ($tab === 'codes'): ?>

    <?php
    // Pagination for codes tab
    $cpg = max(1,(int)($_GET['pg']??1)); $cper=50; $coffset=($cpg-1)*$cper;
    $totalCodes = $db->prepare("SELECT COUNT(*) FROM codes WHERE assigned_vendor=? AND status='assigned'");
    $totalCodes->execute([$userId]); $totalCodes=(int)$totalCodes->fetchColumn();
    $totalCodePages = ceil($totalCodes/$cper);
    $codeRows = $db->prepare("SELECT id,code,batch_id,generated_at FROM codes WHERE assigned_vendor=? AND status='assigned' ORDER BY generated_at DESC LIMIT $cper OFFSET $coffset");
    $codeRows->execute([$userId]); $codeRows=$codeRows->fetchAll();
    ?>

    <!-- Hidden print area — same design as admin ticket slips -->
    <div id="vendor-print-area" style="display:none">
      <div style="font-family:'Arial',sans-serif;font-size:10px;color:#333;padding:6px 8px;border-bottom:2px solid #ea580c;margin-bottom:8px;display:flex;justify-content:space-between;align-items:center;">
        <div><strong style="font-size:14px;color:#ea580c;">ZOEFEEDS</strong> &nbsp;— Raffle Code Tickets</div>
        <div>Printed: <?= date('M j, Y g:i A') ?> &nbsp;·&nbsp; Cut along dotted lines &amp; distribute to customers</div>
      </div>
      <div id="vendor-ticket-grid" style="display:grid;grid-template-columns:repeat(4,1fr);gap:6px;padding:8px;"></div>
    </div>

    <div class="mb-4 flex items-center justify-between gap-3 flex-wrap">
      <div>
        <h2 class="font-bold text-lg">Unredeemed Codes</h2>
        <div class="text-sm text-gray-400"><?= number_format($totalCodes) ?> available codes</div>
      </div>
      <div class="flex gap-2 flex-wrap">
        <button onclick="selectAllCodes()" class="btn btn-secondary btn-sm">☑ Select All</button>
        <button onclick="deselectAllCodes()" class="btn btn-secondary btn-sm">☐ Clear</button>
        <button onclick="copyCodes()"       class="btn btn-secondary btn-sm text-green-400">📋 Copy</button>
        <button onclick="printVendorTickets()" class="btn btn-primary btn-sm">🖨️ Print Tickets</button>
      </div>
    </div>

    <!-- Selection bar -->
    <div id="code-sel-bar" class="hidden mb-4 p-3 bg-orange-500/10 border border-orange-500/20 rounded-xl flex items-center justify-between gap-3 flex-wrap">
      <span class="text-sm text-orange-300 font-semibold"><span id="code-sel-count">0</span> selected</span>
      <div class="flex gap-2">
        <button onclick="copyCodes()"          class="btn btn-sm btn-secondary text-xs text-green-400">📋 Copy</button>
        <button onclick="printVendorTickets()" class="btn btn-sm btn-primary text-xs">🖨️ Print Tickets</button>
      </div>
    </div>

    <div class="card">
      <!-- Table header -->
      <div class="flex items-center gap-3 px-4 py-3 border-b border-white/5 bg-white/2">
        <input type="checkbox" id="chk-all-codes" class="w-4 h-4 accent-orange-500" onchange="toggleAllCodes(this)">
        <div class="flex-1 grid grid-cols-3 gap-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">
          <span>Code</span><span class="hidden sm:block">Batch</span><span class="hidden md:block">Generated</span>
        </div>
      </div>
      <?php if ($codeRows): ?>
      <div class="divide-y divide-white/5 max-h-[520px] overflow-y-auto">
        <?php foreach($codeRows as $cr): ?>
        <label class="flex items-center gap-3 px-4 py-3 hover:bg-white/3 cursor-pointer transition-colors">
          <input type="checkbox" class="code-pick w-4 h-4 accent-orange-500" value="<?= e($cr['code']) ?>" onchange="updateCodeSel()">
          <div class="flex-1 grid grid-cols-3 gap-4 min-w-0">
            <code class="text-orange-400 font-bold tracking-widest text-sm"><?= e($cr['code']) ?></code>
            <span class="text-xs text-gray-500 truncate hidden sm:block"><?= e(substr($cr['batch_id']??'—',0,22)) ?></span>
            <span class="text-xs text-gray-500 hidden md:block"><?= date('M j, Y', strtotime($cr['generated_at'])) ?></span>
          </div>
        </label>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
      <div class="p-12 text-center"><div class="text-4xl mb-3">📭</div><div class="text-gray-500 text-sm">No unredeemed codes. Contact admin to assign codes to your account.</div></div>
      <?php endif; ?>
      <?php if ($totalCodePages > 1): ?>
      <div class="flex items-center justify-between px-4 py-3 border-t border-white/5">
        <div class="text-xs text-gray-500">Page <?= $cpg ?>/<?= $totalCodePages ?></div>
        <div class="flex gap-2">
          <?php if($cpg>1): ?><a href="?tab=codes&pg=<?=$cpg-1?>" class="btn btn-sm btn-secondary text-xs">← Prev</a><?php endif; ?>
          <?php if($cpg<$totalCodePages): ?><a href="?tab=codes&pg=<?=$cpg+1?>" class="btn btn-sm btn-secondary text-xs">Next →</a><?php endif; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>

    <!-- ── API KEYS ──────────────────────────────────────── -->
    <?php elseif ($tab === 'keys'): ?>

    <div class="max-w-xl mx-auto space-y-4">
      <?php if ($newSecret): ?>
      <div class="bg-green-500/10 border-2 border-green-500/40 rounded-xl p-5">
        <div class="text-green-400 font-bold mb-2 flex items-center gap-2">⚠️ Copy Your Secret Key NOW</div>
        <p class="text-xs text-gray-300 mb-3">This is shown <strong class="text-white">only once</strong>. Store it somewhere safe immediately.</p>
        <div class="bg-black/40 rounded-lg p-3 text-green-300 text-xs break-all select-all font-mono mb-3"><?= e($newSecret) ?></div>
        <button onclick="copyText('<?= e($newSecret) ?>')" class="btn btn-sm w-full" style="background:#22c55e;color:white">📋 Copy Secret Key</button>
      </div>
      <?php endif; ?>

      <div class="card p-6">
        <h2 class="font-bold text-lg mb-5">🔑 API Keys</h2>
        <?php if ($user['vendor_public_key']): ?>
        <div class="space-y-4 mb-5">
          <div>
            <label class="form-label">Public Key</label>
            <div class="flex gap-2">
              <div class="flex-1 bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-sm text-cyan-400 break-all font-mono"><?= e($user['vendor_public_key']) ?></div>
              <button onclick="copyText('<?= e($user['vendor_public_key']) ?>')" class="btn btn-secondary btn-sm flex-shrink-0">📋</button>
            </div>
            <div class="text-xs text-gray-500 mt-1">Safe to share. Used in API request headers as <code>X-ZF-Public-Key</code>.</div>
          </div>
          <div>
            <label class="form-label">Secret Key</label>
            <div class="bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-sm text-gray-500 font-mono">
              ••••••••••••••••••••••••••••••••••••••••
            </div>
            <div class="text-xs text-gray-500 mt-1">Never share this. Used as <code>X-ZF-Secret-Key</code> in API requests.</div>
          </div>
        </div>
        <div class="p-3 bg-yellow-500/10 border border-yellow-500/20 rounded-xl text-xs text-yellow-300 mb-4">
          ⚠️ Regenerating keys will instantly invalidate your current keys. Any system using them will stop working immediately.
        </div>
        <?php else: ?>
        <div class="text-center py-8 mb-4">
          <div class="text-5xl mb-3">🔑</div>
          <div class="text-gray-400 text-sm mb-2">No API keys generated yet.</div>
          <div class="text-gray-500 text-xs">Generate a key pair to integrate ZoeFeeds with your systems.</div>
        </div>
        <?php endif; ?>
        <form method="POST" onsubmit="return confirm('<?= $user['vendor_public_key']?'Regenerate keys? Your current keys will stop working.':'Generate API keys?' ?>')">
          <?= csrfField() ?><input type="hidden" name="action" value="generate_keys">
          <button type="submit" class="btn btn-primary w-full py-3">
            <?= $user['vendor_public_key'] ? '🔄 Regenerate API Keys' : '⚡ Generate API Keys' ?>
          </button>
        </form>
      </div>

      <div class="card p-5">
        <h3 class="font-bold mb-3 text-sm">📖 Quick API Reference</h3>
        <div class="space-y-2 text-xs text-gray-400">
          <div><strong class="text-white">Base URL:</strong> <code class="text-orange-400"><?= APP_URL ?>/api/vendor/</code></div>
          <div><strong class="text-white">Auth headers:</strong> <code class="text-cyan-400">X-ZF-Public-Key</code> + <code class="text-cyan-400">X-ZF-Secret-Key</code></div>
          <div><strong class="text-white">List inventory:</strong> <code class="text-green-400">GET inventory</code></div>
          <div><strong class="text-white">Transfer to vendor:</strong> <code class="text-green-400">POST transfer-vendor</code></div>
        </div>
        <a href="<?= APP_URL ?>/docs/api.html" target="_blank" class="btn btn-secondary btn-sm w-full mt-3 text-center">📄 Full API Docs →</a>
      </div>
    </div>

    <!-- ── HISTORY ────────────────────────────────────────── -->
    <?php elseif ($tab === 'history'): ?>

    <?php
    $hpg=max(1,(int)($_GET['hpg']??1)); $hper=20; $hoffset=($hpg-1)*$hper;
    $hist=$db->prepare("SELECT r.redeemed_at, u.full_name, u.phone, c.code FROM code_redemptions r JOIN users u ON r.user_id=u.id JOIN codes c ON r.code_id=c.id WHERE r.vendor_id=? ORDER BY r.redeemed_at DESC LIMIT $hper OFFSET $hoffset");
    $hist->execute([$userId]); $hist=$hist->fetchAll();
    $histTotal=$db->prepare("SELECT COUNT(*) FROM code_redemptions WHERE vendor_id=?");
    $histTotal->execute([$userId]); $histTotal=(int)$histTotal->fetchColumn();
    $histPages=ceil($histTotal/$hper);
    ?>
    <div class="flex items-center justify-between mb-4">
      <div><h2 class="font-bold">Distribution History</h2><div class="text-sm text-gray-400"><?= number_format($histTotal) ?> total distributions</div></div>
    </div>
    <div class="card divide-y divide-white/5">
      <?php foreach($hist as $h): ?>
      <div class="flex items-center gap-3 p-4">
        <div class="w-9 h-9 bg-orange-500/15 rounded-xl flex items-center justify-center font-bold text-orange-400 flex-shrink-0"><?= strtoupper($h['full_name'][0]) ?></div>
        <div class="flex-1 min-w-0">
          <div class="font-medium text-sm truncate"><?= e($h['full_name']) ?></div>
          <div class="text-xs text-gray-400"><?= e(formatPhone($h['phone'])) ?> · <code class="text-orange-400"><?= e($h['code']) ?></code></div>
        </div>
        <div class="text-xs text-gray-500 flex-shrink-0"><?= date('M j, g:ia', strtotime($h['redeemed_at'])) ?></div>
      </div>
      <?php endforeach; ?>
      <?php if(!$hist): ?><div class="p-10 text-center text-gray-500 text-sm">No distributions recorded yet.</div><?php endif; ?>
    </div>
    <?php if($histPages>1): ?>
    <div class="flex justify-between items-center mt-4">
      <div class="text-sm text-gray-400">Page <?=$hpg?>/<?=$histPages?></div>
      <div class="flex gap-2">
        <?php if($hpg>1): ?><a href="?tab=history&hpg=<?=$hpg-1?>" class="btn btn-sm btn-secondary">← Prev</a><?php endif; ?>
        <?php if($hpg<$histPages): ?><a href="?tab=history&hpg=<?=$hpg+1?>" class="btn btn-sm btn-secondary">Next →</a><?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php endif; ?>

  </div>
</div>

<script src="<?= APP_URL ?>/assets/js/app.js"></script>
<script>
// ── Code selection ──────────────────────────────────────────
let pickedCodes = new Set();

function updateCodeSel() {
  pickedCodes.clear();
  document.querySelectorAll('.code-pick:checked').forEach(c => pickedCodes.add(c.value));
  const n = pickedCodes.size;
  document.getElementById('code-sel-count').textContent = n;
  document.getElementById('code-sel-bar').classList.toggle('hidden', n === 0);
  const all = document.getElementById('chk-all-codes');
  const total = document.querySelectorAll('.code-pick').length;
  if (all) { all.checked = n === total && total > 0; all.indeterminate = n > 0 && n < total; }
}
function toggleAllCodes(m) {
  document.querySelectorAll('.code-pick').forEach(c => { c.checked = m.checked; m.checked ? pickedCodes.add(c.value) : pickedCodes.delete(c.value); });
  updateCodeSel();
}
function selectAllCodes() {
  document.querySelectorAll('.code-pick').forEach(c => { c.checked = true; pickedCodes.add(c.value); });
  const all = document.getElementById('chk-all-codes'); if(all) all.checked = true;
  updateCodeSel();
}
function deselectAllCodes() {
  document.querySelectorAll('.code-pick').forEach(c => c.checked = false);
  pickedCodes.clear(); updateCodeSel();
  const all = document.getElementById('chk-all-codes'); if(all) all.checked = false;
}
function copyCodes() {
  if (!pickedCodes.size) { Toast.error('Select codes first.'); return; }
  navigator.clipboard.writeText([...pickedCodes].join('\n')).then(() => Toast.success(`${pickedCodes.size} code(s) copied!`));
}

// Print tickets — same slip design as admin (dashed border, brand, formatted code, cut line)
function printVendorTickets() {
  if (!pickedCodes.size) { Toast.error('Select codes to print first.'); return; }
  const grid = document.getElementById('vendor-ticket-grid');
  grid.innerHTML = '';
  [...pickedCodes].forEach(code => {
    // Format 742081935627401 → 74208-19356-27401
    const f = code.replace(/(\d{5})(\d{5})(\d{5})/, '$1-$2-$3');
    grid.insertAdjacentHTML('beforeend', `
      <div style="border:1.5px dashed #555;border-radius:6px;padding:10px 8px;text-align:center;background:#fff;color:#000;page-break-inside:avoid;">
        <div style="font-size:9px;font-weight:700;letter-spacing:1px;color:#ea580c;margin-bottom:4px;font-family:Arial,sans-serif;">ZOEFEEDS</div>
        <div style="font-size:15px;font-weight:900;letter-spacing:3px;font-family:'Courier New',monospace;color:#1a1a1a;line-height:1.2;">${f}</div>
        <div style="font-size:7px;color:#666;margin-top:4px;font-family:Arial,sans-serif;">Raffle Code — Redeem at zoefeeds.com</div>
        <div style="font-size:7px;color:#999;margin-top:5px;border-top:1px dotted #ccc;padding-top:4px;font-family:Arial,sans-serif;">✂ Cut here · Do not sell · Free gift only</div>
      </div>
    `);
  });
  const area = document.getElementById('vendor-print-area');
  area.style.display = 'block';
  window.print();
  setTimeout(() => area.style.display = 'none', 1500);
}
function copyText(t) {
  navigator.clipboard.writeText(t).then(() => Toast.success('Copied!'));
}
</script>
</body></html>

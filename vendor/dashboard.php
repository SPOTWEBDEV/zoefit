<?php
// vendor/dashboard.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

startAppSession();
if (empty($_SESSION['vendor_id'])) {
    redirect(APP_URL . '/vendor/login.php');
}
$vendorId = (int)$_SESSION['vendor_id'];
$db = getDB();

$stmt = $db->prepare("SELECT * FROM vendors WHERE id = ? AND status = 'active'");
$stmt->execute([$vendorId]);
$vendor = $stmt->fetch();

if (!$vendor) {
    $chk = $db->prepare("SELECT status FROM vendors WHERE id = ?");
    $chk->execute([$vendorId]);
    $chk = $chk->fetch();
    $pendingStatus = $chk['status'] ?? 'unknown';
    $_SESSION['vendor_status'] = $pendingStatus;
    ?><!DOCTYPE html>
    <html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Account Status — <?= APP_NAME ?></title>
    <script src="<?= APP_URL ?>/assets/js/tailwind.js"></script>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>*{font-family:'Poppins',sans-serif!important}</style>
    </head>
    <body class="bg-[#0a0f1a] text-white min-h-screen flex items-center justify-center p-4">
    <div class="text-center max-w-md">
      <?php if ($pendingStatus === 'pending'): ?>
        <div class="text-6xl mb-4">⏳</div>
        <h1 class="text-2xl font-black mb-2">Application Under Review</h1>
        <p class="text-gray-400 mb-6">Your vendor application is being reviewed. You'll be notified within 24 hours once approved.</p>
      <?php elseif ($pendingStatus === 'suspended'): ?>
        <div class="text-6xl mb-4">🚫</div>
        <h1 class="text-2xl font-black mb-2">Account Suspended</h1>
        <p class="text-gray-400 mb-6">Your vendor account has been suspended. Please contact admin support.</p>
      <?php elseif ($pendingStatus === 'rejected'): ?>
        <div class="text-6xl mb-4">❌</div>
        <h1 class="text-2xl font-black mb-2">Application Rejected</h1>
        <p class="text-gray-400 mb-6">Your application was not approved. You may re-apply with updated information.</p>
        <a href="<?= APP_URL ?>/vendor/register.php" class="inline-block px-6 py-3 rounded-xl font-bold text-white" style="background:#7c3aed">Re-apply →</a>
      <?php else: ?>
        <div class="text-6xl mb-4">❓</div>
        <h1 class="text-2xl font-black mb-2">Status Unknown</h1>
        <p class="text-gray-400 mb-6">Please contact admin support.</p>
      <?php endif; ?>
      <div class="mt-4">
        <a href="<?= APP_URL ?>/user/dashboard.php" class="text-sm text-gray-500 hover:text-gray-300">← Back to Customer Dashboard</a>
      </div>
    </div></body></html>
    <?php exit;
}

$_SESSION['vendor_name']   = $vendor['full_name'];
$_SESSION['vendor_status'] = $vendor['status'];

$tab = $_GET['tab'] ?? 'overview';
$msg = $err = '';

if (isPost() && verifyCsrf($_POST[CSRF_TOKEN_NAME] ?? '') && ($_POST['action'] ?? '') === 'generate_keys') {
    $pubKey    = 'zf_pub_' . bin2hex(random_bytes(16));
    $rawSecret = 'zf_sec_' . bin2hex(random_bytes(32));
    $db->prepare("UPDATE vendors SET public_key=?, secret_key=?, updated_at=NOW() WHERE id=?")
       ->execute([$pubKey, password_hash($rawSecret, PASSWORD_BCRYPT), $vendorId]);
    $_SESSION['vendor_new_secret'] = $rawSecret;
    auditLog('vendor', $vendorId, 'generate_api_keys', 'Vendor regenerated API keys');
    $msg = 'New API keys generated. Copy your secret key now — it will not be shown again.';
    $tab = 'keys';
    $stmt = $db->prepare("SELECT * FROM vendors WHERE id=?");
    $stmt->execute([$vendorId]);
    $vendor = $stmt->fetch();
}

$invRaw = $db->prepare("SELECT status, COUNT(*) cnt FROM codes WHERE assigned_vendor=? GROUP BY status");
$invRaw->execute([$vendorId]);
$inv = ['assigned' => 0, 'redeemed' => 0, 'reserved' => 0, 'used' => 0];
foreach ($invRaw->fetchAll() as $r)
    if (isset($inv[$r['status']])) $inv[$r['status']] = (int)$r['cnt'];

$recent = $db->prepare(
    "SELECT r.redeemed_at, u.full_name, u.phone, c.code
     FROM code_redemptions r
     JOIN users u ON r.user_id = u.id
     JOIN codes c ON r.code_id = c.id
     WHERE r.vendor_id=? ORDER BY r.redeemed_at DESC LIMIT 6"
);
$recent->execute([$vendorId]);
$recent = $recent->fetchAll();

$newSecret = $_SESSION['vendor_new_secret'] ?? null;
unset($_SESSION['vendor_new_secret']);

$currentPage = 'dashboard';
$pageTitle   = 'Vendor Dashboard';
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
    * { font-family: 'Poppins', sans-serif !important; }
    code, pre { font-family: 'Courier New', monospace !important; }
    .vtab-active { background: #7c3aed !important; color: #fff !important; }

    /* ── Print styles ── */
    /*
      IMPORTANT: #vendor-print-area is hidden on screen via inline style.
      During printing we show ONLY it. The ticket cards inside must be
      styled with inline styles so they are not affected by Tailwind purge.
    */
    #vendor-print-area {
      display: none;  /* hidden on screen — shown only when printing */
    }

    @media print {
      /* Hide everything on the page */
      body > * { display: none !important; }

      /* Show only the print area */
      #vendor-print-area {
        display: block !important;
        position: fixed !important;
        top: 0 !important;
        left: 0 !important;
        width: 100% !important;
        z-index: 9999 !important;
        background: #fff !important;
      }

      @page { margin: 10mm; size: A4; }
    }
  </style>
</head>
<body class="bg-[#0a0f1a] text-white">

<!-- ── PRINT AREA — lives at body level so @media print can target it cleanly ── -->
<div id="vendor-print-area">
  <div style="font-family:Arial,sans-serif;font-size:10px;color:#333;padding:6px 8px;border-bottom:2px solid #7c3aed;margin-bottom:8px;display:flex;justify-content:space-between;align-items:center;">
    <div><strong style="font-size:14px;color:#7c3aed;">ZOEFEEDS</strong> &nbsp;— Raffle Code Tickets</div>
    <div>Printed: <?= date('M j, Y g:i A') ?> &nbsp;·&nbsp; Cut along dotted lines &amp; distribute to customers</div>
  </div>
  <!-- Grid is populated by JS before print is triggered -->
  <div id="vendor-ticket-grid" style="display:grid;grid-template-columns:repeat(4,1fr);gap:6px;padding:8px;"></div>
</div>

<?php include __DIR__ . '/../components/vendor-sidebar.php'; ?>

<div class="main-content">
  <div class="topbar">
    <button onclick="toggleSidebar()" class="md:hidden text-gray-400 text-2xl mr-3">☰</button>
    <div>
      <h1 class="text-lg font-bold">Vendor Dashboard</h1>
      <div class="text-xs font-medium" style="color:#a78bfa"><?= e($vendor['business_name'] ?? $vendor['full_name']) ?></div>
    </div>
  </div>

  <div class="p-4 md:p-6 pb-24 md:pb-6">

    <?php if ($msg): ?>
    <div class="bg-green-500/10 border border-green-500/30 text-green-400 rounded-xl p-4 mb-5 text-sm"><?= e($msg) ?></div>
    <?php endif; ?>
    <?php if ($err): ?>
    <div class="bg-red-500/10 border border-red-500/30 text-red-400 rounded-xl p-4 mb-5 text-sm"><?= e($err) ?></div>
    <?php endif; ?>

    <!-- Tab Nav -->
    <div class="flex gap-2 mb-5 overflow-x-auto pb-1">
      <?php foreach (['overview' => '📊 Overview', 'codes' => '🎟️ My Codes', 'keys' => '🔑 API Keys', 'history' => '📋 History'] as $t => $l): ?>
      <a href="?tab=<?= $t ?>"
         class="btn btn-sm whitespace-nowrap <?= $tab === $t ? 'vtab-active' : 'btn-secondary' ?>">
        <?= $l ?>
      </a>
      <?php endforeach; ?>
    </div>

    <!-- ════════════════ OVERVIEW ════════════════ -->
    <?php if ($tab === 'overview'): ?>

    <div class="balance-card mb-5" style="background:linear-gradient(135deg,rgba(124,58,237,.35),rgba(109,40,217,.2))">
      <div class="text-sm" style="color:rgba(196,181,253,.8)">Available Code Inventory</div>
      <div class="text-5xl font-black mt-1" style="color:#e9d5ff"><?= number_format((int)$vendor['code_balance']) ?></div>
      <div class="text-xs mt-1" style="color:rgba(196,181,253,.7)">codes assigned to your account</div>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-5">
      <?php foreach ([
        ['Available',   $vendor['code_balance'], 'text-purple-400'],
        ['Distributed', $inv['redeemed'],        'text-green-400'],
        ['In Draws',    $inv['reserved'],         'text-yellow-400'],
        ['Used Up',     $inv['used'],             'text-gray-500'],
      ] as $ic): ?>
      <div class="card p-4 text-center">
        <div class="text-2xl font-black <?= $ic[2] ?>"><?= number_format((int)$ic[1]) ?></div>
        <div class="text-xs text-gray-500 mt-1"><?= $ic[0] ?></div>
      </div>
      <?php endforeach; ?>
    </div>

    <div class="grid sm:grid-cols-3 gap-3 mb-5">
      <a href="?tab=codes" class="card p-4 flex items-center gap-3 transition-colors" style="border-color:rgba(124,58,237,.15)" onmouseover="this.style.borderColor='rgba(124,58,237,.4)'" onmouseout="this.style.borderColor='rgba(124,58,237,.15)'">
        <div class="w-10 h-10 rounded-xl flex items-center justify-center text-xl" style="background:rgba(124,58,237,.15)">🖨️</div>
        <div><div class="font-semibold text-sm">View &amp; Print Codes</div><div class="text-xs text-gray-500">Select &amp; print ticket slips</div></div>
      </a>
      
      <a href="?tab=keys" class="card p-4 flex items-center gap-3 transition-colors" style="border-color:rgba(124,58,237,.15)" onmouseover="this.style.borderColor='rgba(124,58,237,.4)'" onmouseout="this.style.borderColor='rgba(124,58,237,.15)'">
        <div class="w-10 h-10 rounded-xl flex items-center justify-center text-xl" style="background:rgba(124,58,237,.15)">🔑</div>
        <div><div class="font-semibold text-sm">API Keys</div><div class="text-xs text-gray-500">Integration access</div></div>
      </a>
    </div>

    <div class="card">
      <div class="flex items-center justify-between px-5 pt-5 pb-3 border-b border-white/5">
        <h2 class="font-bold text-sm">Recent Distributions</h2>
        <a href="?tab=history" class="text-xs hover:underline" style="color:#a78bfa">View all →</a>
      </div>
      <?php foreach ($recent as $r): ?>
      <div class="flex items-center gap-3 px-5 py-3 border-b border-white/5 last:border-0">
        <div class="w-9 h-9 rounded-xl flex items-center justify-center font-bold flex-shrink-0" style="background:rgba(124,58,237,.15);color:#a78bfa">
          <?= strtoupper($r['full_name'][0]) ?>
        </div>
        <div class="flex-1 min-w-0">
          <div class="text-sm font-medium truncate"><?= e($r['full_name']) ?></div>
          <code class="text-xs text-purple-400"><?= e($r['code']) ?></code>
        </div>
        <div class="text-xs text-gray-500 flex-shrink-0"><?= date('M j, g:ia', strtotime($r['redeemed_at'])) ?></div>
      </div>
      <?php endforeach; ?>
      <?php if (!$recent): ?>
      <div class="p-8 text-center text-gray-500 text-sm">No distributions yet. Codes are distributed when customers redeem them.</div>
      <?php endif; ?>
    </div>

    <!-- ════════════════ MY CODES ════════════════ -->
    <?php elseif ($tab === 'codes'): ?>

    <?php
    $cpg     = max(1, (int)($_GET['pg'] ?? 1));
    $cper    = 50;
    $coffset = ($cpg - 1) * $cper;
    $tcStmt  = $db->prepare("SELECT COUNT(*) FROM codes WHERE assigned_vendor=? AND status='assigned'");
    $tcStmt->execute([$vendorId]);
    $totalCodes     = (int)$tcStmt->fetchColumn();
    $totalCodePages = (int)ceil($totalCodes / $cper);
    $codeRows = $db->prepare("SELECT id,code,batch_id,generated_at FROM codes WHERE assigned_vendor=? AND status='assigned' ORDER BY generated_at DESC LIMIT $cper OFFSET $coffset");
    $codeRows->execute([$vendorId]);
    $codeRows = $codeRows->fetchAll();
    ?>

    <div class="mb-4 flex items-center justify-between gap-3 flex-wrap">
      <div>
        <h2 class="font-bold text-lg">Unredeemed Codes</h2>
        <div class="text-sm text-gray-400"><?= number_format($totalCodes) ?> available codes</div>
      </div>
      <div class="flex gap-2 flex-wrap">
        <button onclick="selectAllCodes()"     class="btn btn-secondary btn-sm">☑ Select All</button>
        <button onclick="deselectAllCodes()"   class="btn btn-secondary btn-sm">☐ Clear</button>
        <button onclick="copyCodes()"          class="btn btn-secondary btn-sm text-green-400">📋 Copy</button>
        <button onclick="printVendorTickets()" class="btn btn-sm text-white" style="background:#7c3aed">🖨️ Print Tickets</button>
      </div>
    </div>

    <div id="code-sel-bar" class="hidden mb-4 p-3 rounded-xl flex items-center justify-between gap-3 flex-wrap" style="background:rgba(124,58,237,.1);border:1px solid rgba(124,58,237,.25)">
      <span class="text-sm font-semibold" style="color:#c4b5fd"><span id="code-sel-count">0</span> selected</span>
      <div class="flex gap-2">
        <button onclick="copyCodes()"          class="btn btn-sm btn-secondary text-xs text-green-400">📋 Copy</button>
        <button onclick="printVendorTickets()" class="btn btn-sm text-xs text-white" style="background:#7c3aed">🖨️ Print</button>
      </div>
    </div>

    <div class="card">
      <div class="flex items-center gap-3 px-4 py-3 border-b border-white/5 bg-white/2">
        <input type="checkbox" id="chk-all-codes" class="w-4 h-4" style="accent-color:#7c3aed" onchange="toggleAllCodes(this)">
        <div class="flex-1 grid grid-cols-3 gap-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">
          <span>Code</span><span class="hidden sm:block">Batch</span><span class="hidden md:block">Generated</span>
        </div>
      </div>
      <?php if ($codeRows): ?>
      <div class="divide-y divide-white/5 max-h-[520px] overflow-y-auto">
        <?php foreach ($codeRows as $cr): ?>
        <label class="flex items-center gap-3 px-4 py-3 hover:bg-white/3 cursor-pointer transition-colors">
          <input type="checkbox" class="code-pick w-4 h-4" style="accent-color:#7c3aed" value="<?= e($cr['code']) ?>" onchange="updateCodeSel()">
          <div class="flex-1 grid grid-cols-3 gap-4 min-w-0">
            <code class="font-bold tracking-widest text-sm" style="color:#a78bfa"><?= e($cr['code']) ?></code>
            <span class="text-xs text-gray-500 truncate hidden sm:block"><?= e(substr($cr['batch_id'] ?? '—', 0, 22)) ?></span>
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
          <?php if ($cpg > 1): ?><a href="?tab=codes&pg=<?= $cpg-1 ?>" class="btn btn-sm btn-secondary text-xs">← Prev</a><?php endif; ?>
          <?php if ($cpg < $totalCodePages): ?><a href="?tab=codes&pg=<?= $cpg+1 ?>" class="btn btn-sm btn-secondary text-xs">Next →</a><?php endif; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>

    <!-- ════════════════ API KEYS ════════════════ -->
    <?php elseif ($tab === 'keys'): ?>

    <div class="max-w-xl mx-auto space-y-4">
      <?php if ($newSecret): ?>
      <div class="rounded-xl p-5" style="background:rgba(34,197,94,.08);border:2px solid rgba(34,197,94,.35)">
        <div class="text-green-400 font-bold mb-2">⚠️ Copy Your Secret Key NOW</div>
        <p class="text-xs text-gray-300 mb-3">Shown <strong class="text-white">only once</strong>. Store it immediately.</p>
        <div class="bg-black/40 rounded-lg p-3 text-green-300 text-xs break-all select-all font-mono mb-3"><?= e($newSecret) ?></div>
        <button onclick="copyText('<?= e($newSecret) ?>')" class="btn btn-sm w-full text-white font-bold" style="background:#22c55e">📋 Copy Secret Key</button>
      </div>
      <?php endif; ?>
      <div class="card p-6">
        <h2 class="font-bold text-lg mb-5">🔑 API Keys</h2>
        <?php if ($vendor['public_key']): ?>
        <div class="space-y-4 mb-5">
          <div>
            <label class="form-label">Public Key</label>
            <div class="flex gap-2">
              <div class="flex-1 bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-sm break-all font-mono" style="color:#67e8f9"><?= e($vendor['public_key']) ?></div>
              <button onclick="copyText('<?= e($vendor['public_key']) ?>')" class="btn btn-secondary btn-sm flex-shrink-0">📋</button>
            </div>
            <div class="text-xs text-gray-500 mt-1">Safe to share. Send as <code>X-ZF-Public-Key</code> header.</div>
          </div>
          <div>
            <label class="form-label">Secret Key</label>
            <div class="bg-white/5 border border-white/10 rounded-xl px-4 py-3 text-sm text-gray-500 font-mono">••••••••••••••••••••••••••••••••••••••••</div>
            <div class="text-xs text-gray-500 mt-1">Never share. Send as <code>X-ZF-Secret-Key</code> header.</div>
          </div>
        </div>
        <div class="p-3 rounded-xl text-xs text-yellow-300 mb-4" style="background:rgba(234,179,8,.08);border:1px solid rgba(234,179,8,.2)">
          ⚠️ Regenerating keys instantly invalidates your current keys.
        </div>
        <?php else: ?>
        <div class="text-center py-8 mb-4"><div class="text-5xl mb-3">🔑</div><div class="text-gray-400 text-sm mb-2">No API keys yet.</div><div class="text-gray-500 text-xs">Keys were auto-generated on registration. If missing, regenerate below.</div></div>
        <?php endif; ?>
        <form method="POST" onsubmit="return confirm('<?= $vendor['public_key'] ? 'Regenerate keys? Current keys will stop working immediately.' : 'Generate API keys?' ?>')">
          <?= csrfField() ?><input type="hidden" name="action" value="generate_keys">
          <button type="submit" class="btn btn-primary w-full py-3" style="background:#7c3aed;border-color:#7c3aed">
            <?= $vendor['public_key'] ? '🔄 Regenerate API Keys' : '⚡ Generate API Keys' ?>
          </button>
        </form>
      </div>
      <div class="card p-5">
        <h3 class="font-bold mb-3 text-sm">📖 Quick API Reference</h3>
        <div class="space-y-2 text-xs text-gray-400">
          <div><strong class="text-white">Base URL:</strong> <code class="text-purple-400"><?= APP_URL ?>/api/vendor/</code></div>
          <div><strong class="text-white">Auth headers:</strong> <code style="color:#67e8f9">X-ZF-Public-Key</code> + <code style="color:#67e8f9">X-ZF-Secret-Key</code></div>
          <div><strong class="text-white">List inventory:</strong> <code class="text-green-400">GET inventory</code></div>
          <div><strong class="text-white">Transfer to vendor:</strong> <code class="text-green-400">POST transfer-vendor</code></div>
        </div>
        <a href="<?= APP_URL ?>/docs/api.html" target="_blank" class="btn btn-secondary btn-sm w-full mt-3 text-center">📄 Full API Docs →</a>
      </div>
    </div>

    <!-- ════════════════ HISTORY ════════════════ -->
    <?php elseif ($tab === 'history'): ?>

    <?php
    $hpg     = max(1, (int)($_GET['hpg'] ?? 1));
    $hper    = 20;
    $hoffset = ($hpg - 1) * $hper;
    $hist    = $db->prepare("SELECT r.redeemed_at, u.full_name, u.phone, c.code FROM code_redemptions r JOIN users u ON r.user_id=u.id JOIN codes c ON r.code_id=c.id WHERE r.vendor_id=? ORDER BY r.redeemed_at DESC LIMIT $hper OFFSET $hoffset");
    $hist->execute([$vendorId]); $hist = $hist->fetchAll();
    $htStmt = $db->prepare("SELECT COUNT(*) FROM code_redemptions WHERE vendor_id=?");
    $htStmt->execute([$vendorId]); $histTotal = (int)$htStmt->fetchColumn();
    $histPages = (int)ceil($histTotal / $hper);
    ?>
    <div class="flex items-center justify-between mb-4">
      <div><h2 class="font-bold">Distribution History</h2><div class="text-sm text-gray-400"><?= number_format($histTotal) ?> total distributions</div></div>
    </div>
    <div class="card divide-y divide-white/5">
      <?php foreach ($hist as $h): ?>
      <div class="flex items-center gap-3 p-4">
        <div class="w-9 h-9 rounded-xl flex items-center justify-center font-bold flex-shrink-0" style="background:rgba(124,58,237,.15);color:#a78bfa"><?= strtoupper($h['full_name'][0]) ?></div>
        <div class="flex-1 min-w-0">
          <div class="font-medium text-sm truncate"><?= e($h['full_name']) ?></div>
          <div class="text-xs text-gray-400"><?= e(formatPhone($h['phone'])) ?> · <code class="text-purple-400"><?= e($h['code']) ?></code></div>
        </div>
        <div class="text-xs text-gray-500 flex-shrink-0"><?= date('M j, g:ia', strtotime($h['redeemed_at'])) ?></div>
      </div>
      <?php endforeach; ?>
      <?php if (!$hist): ?><div class="p-10 text-center text-gray-500 text-sm">No distributions recorded yet.</div><?php endif; ?>
    </div>
    <?php if ($histPages > 1): ?>
    <div class="flex justify-between items-center mt-4">
      <div class="text-sm text-gray-400">Page <?= $hpg ?>/<?= $histPages ?></div>
      <div class="flex gap-2">
        <?php if ($hpg > 1): ?><a href="?tab=history&hpg=<?= $hpg-1 ?>" class="btn btn-sm btn-secondary">← Prev</a><?php endif; ?>
        <?php if ($hpg < $histPages): ?><a href="?tab=history&hpg=<?= $hpg+1 ?>" class="btn btn-sm btn-secondary">Next →</a><?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php endif; ?>

  </div>
</div>

<script src="<?= APP_URL ?>/assets/js/app.js"></script>
<script>
let pickedCodes = new Set();

function updateCodeSel() {
  pickedCodes.clear();
  document.querySelectorAll('.code-pick:checked').forEach(c => pickedCodes.add(c.value));
  const n = pickedCodes.size, total = document.querySelectorAll('.code-pick').length;
  document.getElementById('code-sel-count').textContent = n;
  document.getElementById('code-sel-bar').classList.toggle('hidden', n === 0);
  const all = document.getElementById('chk-all-codes');
  if (all) { all.checked = n === total && total > 0; all.indeterminate = n > 0 && n < total; }
}
function toggleAllCodes(m) {
  document.querySelectorAll('.code-pick').forEach(c => { c.checked = m.checked; m.checked ? pickedCodes.add(c.value) : pickedCodes.delete(c.value); });
  updateCodeSel();
}
function selectAllCodes() {
  document.querySelectorAll('.code-pick').forEach(c => { c.checked = true; pickedCodes.add(c.value); });
  const all = document.getElementById('chk-all-codes'); if (all) all.checked = true;
  updateCodeSel();
}
function deselectAllCodes() {
  document.querySelectorAll('.code-pick').forEach(c => c.checked = false);
  pickedCodes.clear(); updateCodeSel();
  const all = document.getElementById('chk-all-codes'); if (all) all.checked = false;
}
function copyCodes() {
  if (!pickedCodes.size) { Toast.error('Select codes first.'); return; }
  navigator.clipboard.writeText([...pickedCodes].join('\n')).then(() => Toast.success(`${pickedCodes.size} code(s) copied!`));
}

// ── PRINT FIX ────────────────────────────────────────────────
// Root cause: window.print() was called synchronously right after
// insertAdjacentHTML(). The browser hadn't painted the new nodes yet,
// so the print dialog captured an empty grid.
//
// Fix: build the grid → force a reflow (offsetHeight) → use
// requestAnimationFrame inside setTimeout to ensure at least one
// paint cycle completes before opening the print dialog.
function printVendorTickets() {
  if (!pickedCodes.size) { Toast.error('Select codes to print first.'); return; }

  const grid = document.getElementById('vendor-ticket-grid');
  const area = document.getElementById('vendor-print-area');

  // 1. Build ticket HTML
  grid.innerHTML = '';
  [...pickedCodes].forEach(code => {
    const f = code.replace(/(\d{5})(\d{5})(\d{5})/, '$1-$2-$3');
    grid.insertAdjacentHTML('beforeend', `
      <div style="border:1.5px dashed #555;border-radius:6px;padding:10px 8px;text-align:center;background:#fff;color:#000;page-break-inside:avoid;">
        <div style="font-size:9px;font-weight:700;letter-spacing:1px;color:#7c3aed;margin-bottom:4px;font-family:Arial,sans-serif;">ZOEFEEDS</div>
        <div style="font-size:15px;font-weight:900;letter-spacing:3px;font-family:'Courier New',monospace;color:#1a1a1a;line-height:1.2;">${f}</div>
        <div style="font-size:7px;color:#666;margin-top:4px;font-family:Arial,sans-serif;">Raffle Code — Redeem at zoefeeds.com</div>
        <div style="font-size:7px;color:#999;margin-top:5px;border-top:1px dotted #ccc;padding-top:4px;font-family:Arial,sans-serif;">✂ Cut here · Do not sell · Free gift only</div>
      </div>
    `);
  });

  // 2. Make print area visible in the DOM (still off-screen for normal view)
  area.style.display = 'block';

  // 3. Force a reflow so the browser registers the new nodes
  void area.offsetHeight;

  // 4. Defer print until after at least one paint frame
  //    requestAnimationFrame fires before paint; the nested setTimeout
  //    fires after, guaranteeing the DOM is fully rendered.
  requestAnimationFrame(() => {
    setTimeout(() => {
      window.print();
      // Hide print area after dialog closes
      setTimeout(() => { area.style.display = 'none'; }, 500);
    }, 100);
  });
}

function copyText(t) {
  navigator.clipboard.writeText(t).then(() => Toast.success('Copied!'));
}
</script>
</body>
</html>
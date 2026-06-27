<?php
// admin/vendor-requests.php — fully rewritten to use vendors table
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
$auth = requireAdmin(); $adminId = $auth['id'];
$db = getDB();
$msg = $err = '';

// ── Quick approve/reject from dashboard links ──────────────
if (isset($_GET['quick_approve']) && ($vid = (int)$_GET['quick_approve'])) {
    $db->prepare(
        "UPDATE vendors SET status='active', approved_by=?, approved_at=NOW(), updated_at=NOW() WHERE id=?"
    )->execute([$adminId, $vid]);
    $db->prepare(
        "UPDATE vendor_applications SET status='approved', reviewed_by=?, reviewed_at=NOW() WHERE vendor_id=? AND status='pending'"
    )->execute([$adminId, $vid]);
    $v = $db->prepare("SELECT full_name FROM vendors WHERE id=?");
    $v->execute([$vid]); $v = $v->fetch();
    // Notify — vendors don't have a notifications row yet so skip or adapt createNotification for vendors
    auditLog('admin', $adminId, 'approve_vendor_request', 'Vendor approved: '.($v['full_name'] ?? ''), 'vendor', $vid);
    $msg = 'Vendor approved successfully.';
}

if (isset($_GET['quick_reject']) && ($vid = (int)$_GET['quick_reject'])) {
    $db->prepare(
        "UPDATE vendors SET status='rejected', updated_at=NOW() WHERE id=?"
    )->execute([$vid]);
    $db->prepare(
        "UPDATE vendor_applications SET status='rejected', reviewed_by=?, reviewed_at=NOW() WHERE vendor_id=? AND status='pending'"
    )->execute([$adminId, $vid]);
    auditLog('admin', $adminId, 'reject_vendor_request', 'Vendor rejected', 'vendor', $vid);
    $msg = 'Application rejected.';
}

// ── Full form actions ──────────────────────────────────────
if (isPost() && verifyCsrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
    $action = $_POST['action'] ?? '';
    $vid    = (int)($_POST['vendor_id'] ?? 0);
    $note   = trim($_POST['review_note'] ?? '');

    if ($vid && $action === 'approve') {
        $db->prepare(
            "UPDATE vendors SET status='active', approved_by=?, approved_at=NOW(), updated_at=NOW() WHERE id=?"
        )->execute([$adminId, $vid]);
        $db->prepare(
            "UPDATE vendor_applications SET status='approved', reviewed_by=?, review_note=?, reviewed_at=NOW() WHERE vendor_id=? AND status='pending'"
        )->execute([$adminId, $note, $vid]);
        $v = $db->prepare("SELECT full_name FROM vendors WHERE id=?");
        $v->execute([$vid]); $v = $v->fetch();
        auditLog('admin', $adminId, 'approve_vendor', 'Approved vendor: '.($v['full_name'] ?? $vid), 'vendor', $vid);
        $msg = 'Vendor account activated.';

    } elseif ($vid && $action === 'reject') {
        $db->prepare(
            "UPDATE vendors SET status='rejected', updated_at=NOW() WHERE id=?"
        )->execute([$vid]);
        $db->prepare(
            "UPDATE vendor_applications SET status='rejected', reviewed_by=?, review_note=?, reviewed_at=NOW() WHERE vendor_id=? AND status='pending'"
        )->execute([$adminId, $note, $vid]);
        $v = $db->prepare("SELECT full_name FROM vendors WHERE id=?");
        $v->execute([$vid]); $v = $v->fetch();
        auditLog('admin', $adminId, 'reject_vendor', 'Rejected vendor: '.($v['full_name'] ?? $vid), 'vendor', $vid);
        $msg = 'Application rejected.';

    } elseif ($vid && $action === 'suspend_vendor') {
        $db->prepare("UPDATE vendors SET status='suspended', updated_at=NOW() WHERE id=?")->execute([$vid]);
        auditLog('admin', $adminId, 'suspend_vendor', "Vendor $vid suspended", 'vendor', $vid);
        $msg = 'Vendor suspended.';

    } elseif ($vid && $action === 'reactivate_vendor') {
        $db->prepare(
            "UPDATE vendors SET status='active', approved_by=?, approved_at=NOW(), updated_at=NOW() WHERE id=?"
        )->execute([$adminId, $vid]);
        auditLog('admin', $adminId, 'reactivate_vendor', "Vendor $vid reactivated", 'vendor', $vid);
        $msg = 'Vendor reactivated.';

    } elseif ($vid && $action === 'assign_codes') {
        $qty    = min(10000, max(1, (int)($_POST['code_count'] ?? 0)));
        $codes  = $db->prepare("SELECT id FROM codes WHERE status='unassigned' LIMIT $qty");
        $codes->execute();
        $codes  = $codes->fetchAll();
        $assigned = 0;
        foreach ($codes as $c) {
            $db->prepare(
                "UPDATE codes SET assigned_vendor=?, status='assigned', assigned_at=NOW() WHERE id=?"
            )->execute([$vid, $c['id']]);
            $assigned++;
        }
        if ($assigned) {
            $db->prepare(
                "UPDATE vendors SET code_balance = code_balance + ?, updated_at=NOW() WHERE id=?"
            )->execute([$assigned, $vid]);
        }
        auditLog('admin', $adminId, 'assign_codes_vendor', "$assigned codes → vendor $vid", 'vendor', $vid);
        $msg = "$assigned codes assigned to vendor.";
    }
}

// ── Fetch records ──────────────────────────────────────────
$tab     = $_GET['tab'] ?? 'pending';
$allowed = ['pending', 'active', 'rejected', 'suspended', 'all'];
if (!in_array($tab, $allowed)) $tab = 'pending';

$page   = max(1, (int)($_GET['page'] ?? 1));
$per    = 20;
$offset = ($page - 1) * $per;
$q      = trim($_GET['q'] ?? '');

$where  = "1=1";
if ($tab !== 'all') $where .= " AND status = '$tab'";
if ($q)             $where .= " AND (full_name LIKE ? OR phone LIKE ? OR business_name LIKE ?)";
$params = $q ? ["%$q%", "%$q%", "%$q%"] : [];

$orderBy = ($tab === 'pending') ? 'applied_at ASC' : 'approved_at DESC';

$rows = $db->prepare(
    "SELECT * FROM vendors WHERE $where ORDER BY $orderBy LIMIT $per OFFSET $offset"
);
$rows->execute($params);
$rows = $rows->fetchAll();

$cnt = $db->prepare("SELECT COUNT(*) FROM vendors WHERE $where");
$cnt->execute($params);
$total = (int)$cnt->fetchColumn();
$pages = (int)ceil($total / $per);

// Tab counts from vendors table
$tabCounts = [];
foreach (['pending', 'active', 'rejected', 'suspended'] as $t) {
    $s = $db->query("SELECT COUNT(*) FROM vendors WHERE status='$t'");
    $tabCounts[$t] = (int)$s->fetchColumn();
}

$unassignedCodes = (int)$db->query("SELECT COUNT(*) FROM codes WHERE status='unassigned'")->fetchColumn();

$aPage = 'vendor-requests';
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="csrf-token" content="<?= generateCsrf() ?>">
  <title>Vendor Requests — <?= APP_NAME ?> Admin</title>
  <script src="<?= APP_URL ?>/assets/js/tailwind.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
  <style>*{font-family:'Poppins',sans-serif!important}</style>
</head>
<body class="bg-[#0a0f1a] text-white">
<?php include __DIR__ . '/../components/admin-sidebar.php'; ?>
<div class="main-content">
  <div class="topbar">
    <button onclick="toggleSidebar()" class="md:hidden text-gray-400 text-2xl mr-3">☰</button>
    <h1 class="text-xl font-bold">Vendor Requests</h1>
    <div class="text-sm text-gray-400"><?= number_format($total) ?> records</div>
  </div>

  <div class="p-4 md:p-6">

    <?php if ($msg): ?>
    <div class="bg-green-500/10 border border-green-500/30 text-green-400 rounded-xl p-4 mb-5 text-sm flex items-center gap-2">
      <span class="text-lg">✅</span><?= e($msg) ?>
    </div>
    <?php endif; ?>
    <?php if ($err): ?>
    <div class="bg-red-500/10 border border-red-500/30 text-red-400 rounded-xl p-4 mb-5 text-sm"><?= e($err) ?></div>
    <?php endif; ?>

    <!-- Tabs -->
    <div class="flex flex-wrap gap-2 mb-5">
      <?php $tabLabels = ['pending' => '⏳ Pending', 'active' => '✅ Active', 'rejected' => '❌ Rejected', 'suspended' => '🚫 Suspended', 'all' => 'All']; ?>
      <?php foreach ($tabLabels as $t => $l): ?>
      <a href="?tab=<?= $t ?>" class="btn btn-sm <?= $tab === $t ? 'btn-primary' : 'btn-secondary' ?> flex items-center gap-1.5">
        <?= $l ?>
        <?php if ($t !== 'all' && isset($tabCounts[$t]) && $tabCounts[$t] > 0): ?>
        <span class="<?= $tab === $t ? 'bg-white/30' : 'bg-orange-500' ?> text-white text-xs rounded-full w-5 h-5 flex items-center justify-center font-bold">
          <?= $tabCounts[$t] ?>
        </span>
        <?php endif; ?>
      </a>
      <?php endforeach; ?>
    </div>

    <!-- Search -->
    <form method="GET" class="flex gap-3 mb-5">
      <input type="hidden" name="tab" value="<?= e($tab) ?>">
      <input type="text" name="q" class="form-control flex-1 max-w-md"
             placeholder="Search name, phone, business…" value="<?= e($q) ?>">
      <button class="btn btn-primary px-5">Search</button>
      <?php if ($q): ?>
      <a href="?tab=<?= $tab ?>" class="btn btn-secondary px-4">Clear</a>
      <?php endif; ?>
    </form>

    <!-- Results -->
    <?php if ($rows): ?>
    <div class="space-y-4">
      <?php foreach ($rows as $r):
        // Get vendor_application record (vendor_id now references vendors.id)
        $app = $db->prepare("SELECT * FROM vendor_applications WHERE vendor_id=? ORDER BY applied_at DESC LIMIT 1");
        $app->execute([$r['id']]); $app = $app->fetch();

        // Codes assigned to this vendor
        $codeCount = $db->prepare("SELECT COUNT(*) FROM codes WHERE assigned_vendor=?");
        $codeCount->execute([$r['id']]); $codeCount = (int)$codeCount->fetchColumn();
      ?>
      <div class="card p-5">
        <div class="flex flex-col md:flex-row md:items-start gap-4">

          <!-- Vendor info -->
          <div class="flex items-start gap-3 flex-1 min-w-0">
            <div class="w-12 h-12 rounded-2xl flex items-center justify-center text-xl font-black flex-shrink-0
              <?= match($r['status']) {
                'active'    => 'bg-green-500/20 text-green-400',
                'pending'   => 'bg-yellow-500/20 text-yellow-400',
                'rejected'  => 'bg-red-500/20 text-red-400',
                'suspended' => 'bg-gray-500/20 text-gray-400',
                default     => 'bg-orange-500/15 text-orange-400'
              } ?>">
              <?= strtoupper($r['full_name'][0]) ?>
            </div>
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2 flex-wrap">
                <span class="font-bold text-base"><?= e($r['full_name']) ?></span>
                <span class="badge <?= match($r['status']) {
                  'active'    => 'badge-success',
                  'pending'   => 'badge-warning',
                  'rejected'  => 'badge-danger',
                  'suspended' => 'badge-muted',
                  default     => 'badge-muted'
                } ?>">
                  <?= ucfirst($r['status']) ?>
                </span>
              </div>
              <div class="text-sm text-gray-400 mt-0.5">
                <?= e(formatPhone($r['phone'])) ?>
                <?= $r['email'] ? ' · '.e($r['email']) : '' ?>
              </div>
              <div class="flex flex-wrap gap-x-5 gap-y-1 mt-2 text-xs text-gray-500">
                <span>🏪 <?= e($r['business_name'] ?? 'No business name') ?></span>
                <span>🎟️ <?= $codeCount ?> codes assigned</span>
                <span>💼 Balance: <?= number_format((int)$r['code_balance']) ?></span>
                <?php if ($r['applied_at']): ?>
                <span>📅 Applied <?= date('M j, Y', strtotime($r['applied_at'])) ?></span>
                <?php endif; ?>
                <?php if ($r['approved_at']): ?>
                <span>✅ Approved <?= date('M j, Y', strtotime($r['approved_at'])) ?></span>
                <?php endif; ?>
              </div>

              <?php if ($r['reason']): ?>
              <div class="mt-2 p-3 bg-white/3 rounded-lg text-xs text-gray-400 border border-white/5">
                <span class="text-gray-500 font-medium">Application reason:</span> <?= e($r['reason']) ?>
              </div>
              <?php endif; ?>

              <?php if ($app && $app['review_note'] && $r['status'] !== 'pending'): ?>
              <div class="mt-2 p-3 bg-white/3 rounded-lg text-xs text-gray-400 border border-white/5">
                <span class="text-gray-500 font-medium">Admin note:</span> <?= e($app['review_note']) ?>
              </div>
              <?php endif; ?>
            </div>
          </div>

          <!-- Action buttons -->
          <div class="flex-shrink-0 flex flex-col gap-2 min-w-[160px]">
            <?php if ($r['status'] === 'pending'): ?>
            <button onclick="openAction(<?= $r['id'] ?>, 'approve', '<?= e(addslashes($r['full_name'])) ?>')"
                    class="btn btn-sm w-full py-2" style="background:#22c55e;color:white">
              ✓ Approve Vendor
            </button>
            <button onclick="openAction(<?= $r['id'] ?>, 'reject', '<?= e(addslashes($r['full_name'])) ?>')"
                    class="btn btn-sm btn-secondary w-full py-2 text-red-400">
              ✕ Reject
            </button>

            <?php elseif ($r['status'] === 'active'): ?>
            <button onclick="openAssign(<?= $r['id'] ?>, '<?= e(addslashes($r['full_name'])) ?>')"
                    class="btn btn-sm btn-primary w-full py-2">
              🎟️ Assign Codes
            </button>
            <form method="POST">
              <?= csrfField() ?>
              <input type="hidden" name="action" value="suspend_vendor">
              <input type="hidden" name="vendor_id" value="<?= $r['id'] ?>">
              <button class="btn btn-sm btn-secondary w-full py-2 text-yellow-400">⚠️ Suspend</button>
            </form>

            <?php elseif (in_array($r['status'], ['suspended', 'rejected'])): ?>
            <form method="POST">
              <?= csrfField() ?>
              <input type="hidden" name="action" value="reactivate_vendor">
              <input type="hidden" name="vendor_id" value="<?= $r['id'] ?>">
              <button class="btn btn-sm w-full py-2" style="background:#22c55e;color:white">▶ Reactivate</button>
            </form>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if ($pages > 1): ?>
    <div class="flex items-center justify-between mt-5">
      <div class="text-sm text-gray-400">Page <?= $page ?>/<?= $pages ?> (<?= number_format($total) ?>)</div>
      <div class="flex gap-2">
        <?php if ($page > 1): ?>
        <a href="?tab=<?= $tab ?>&page=<?= $page-1 ?>&q=<?= urlencode($q) ?>" class="btn btn-sm btn-secondary">← Prev</a>
        <?php endif; ?>
        <?php if ($page < $pages): ?>
        <a href="?tab=<?= $tab ?>&page=<?= $page+1 ?>&q=<?= urlencode($q) ?>" class="btn btn-sm btn-secondary">Next →</a>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php else: ?>
    <div class="card p-12 text-center">
      <div class="text-5xl mb-4"><?= $tab === 'pending' ? '⏳' : '🏪' ?></div>
      <div class="text-gray-400">
        No <?= $tab === 'all' ? '' : $tab ?> vendor applications found<?= $q ? ' for "'.e($q).'"' : '' ?>
      </div>
    </div>
    <?php endif; ?>

  </div>
</div>

<!-- Approve / Reject Modal -->
<div class="modal-overlay" id="action-modal">
  <div class="modal-box">
    <h3 class="text-xl font-bold mb-1" id="modal-title">Review Application</h3>
    <p class="text-gray-400 text-sm mb-5" id="modal-subtitle"></p>
    <form method="POST" id="action-form">
      <?= csrfField() ?>
      <input type="hidden" name="action"    id="action-field">
      <input type="hidden" name="vendor_id" id="action-vendor-id">
      <div class="form-group">
        <label class="form-label">Review Note <span class="text-gray-500 font-normal">(optional)</span></label>
        <textarea name="review_note" class="form-control" rows="3"
                  placeholder="Add a note to the applicant…"></textarea>
      </div>
      <div class="flex gap-3" id="modal-buttons"></div>
    </form>
  </div>
</div>

<!-- Assign Codes Modal -->
<div class="modal-overlay" id="assign-modal">
  <div class="modal-box">
    <h3 class="text-xl font-bold mb-2">Assign Codes to Vendor</h3>
    <p class="text-gray-400 text-sm mb-5">
      Vendor: <strong id="assign-name" class="text-white"></strong>
    </p>
    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="action"    value="assign_codes">
      <input type="hidden" name="vendor_id" id="assign-vid">
      <div class="form-group">
        <label class="form-label">Number of Codes</label>
        <input type="number" name="code_count" class="form-control"
               min="1" max="10000" value="100" required>
        <div class="text-xs text-gray-500 mt-1">
          Available unassigned: <strong class="text-orange-400"><?= number_format($unassignedCodes) ?></strong> codes
        </div>
      </div>
      <div class="flex gap-3">
        <button type="button" data-close-modal="assign-modal" class="btn btn-secondary flex-1">Cancel</button>
        <button type="submit" class="btn btn-primary flex-1">🎟️ Assign</button>
      </div>
    </form>
  </div>
</div>

<script src="<?= APP_URL ?>/assets/js/app.js"></script>
<script>
function openAction(vid, action, name) {
  const titles   = { approve: '✅ Approve Vendor Application', reject: '❌ Reject Application' };
  const subs     = {
    approve: `Approving ${name} as a ZoeFeeds vendor. They will gain full vendor dashboard access.`,
    reject:  `Rejecting ${name}'s vendor application. Optionally add a note explaining why.`
  };
  const buttons  = {
    approve: `<button type="button" data-close-modal="action-modal" class="btn btn-secondary flex-1">Cancel</button>
              <button type="submit" class="btn flex-1 py-3 font-bold" style="background:#22c55e;color:white">✓ Approve</button>`,
    reject:  `<button type="button" data-close-modal="action-modal" class="btn btn-secondary flex-1">Cancel</button>
              <button type="submit" class="btn btn-danger flex-1 py-3 font-bold">✕ Reject</button>`,
  };
  document.getElementById('modal-title').textContent    = titles[action];
  document.getElementById('modal-subtitle').textContent = subs[action];
  document.getElementById('action-field').value         = action;
  document.getElementById('action-vendor-id').value     = vid;
  document.getElementById('modal-buttons').innerHTML    = buttons[action];
  Modal.open('action-modal');
}

function openAssign(vid, name) {
  document.getElementById('assign-vid').value            = vid;
  document.getElementById('assign-name').textContent     = name;
  Modal.open('assign-modal');
}
</script>
</body>
</html>
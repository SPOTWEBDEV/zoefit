<?php
// admin/users.php — shows customers (users table) AND vendors (vendors table) as separate rows
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
$auth = requireAdmin(); $adminId = $auth['id'];
$db   = getDB();
$msg  = '';

// ── Actions ────────────────────────────────────────────────
if (isPost() && verifyCsrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
    $action     = $_POST['action']      ?? '';
    $recordType = $_POST['record_type'] ?? 'user'; // 'user' or 'vendor'
    $rid        = (int)($_POST['record_id'] ?? 0);

    if ($rid && in_array($action, ['suspend','activate','ban'])) {
        $map = ['suspend' => 'suspended', 'activate' => 'active', 'ban' => 'banned'];

        if ($recordType === 'vendor') {
            // vendors table uses: active | pending | suspended | rejected
            $vendorStatus = ($action === 'ban') ? 'rejected' : $map[$action];
            $db->prepare("UPDATE vendors SET status=?, updated_at=NOW() WHERE id=?")
               ->execute([$vendorStatus, $rid]);
            auditLog('admin', $adminId, $action.'_vendor', "Vendor #$rid → $vendorStatus", 'vendor', $rid);
            $msg = "Vendor #$rid updated.";
        } else {
            $db->prepare("UPDATE users SET status=?, updated_at=NOW() WHERE id=?")
               ->execute([$map[$action], $rid]);
            auditLog('admin', $adminId, $action.'_user', "User #$rid → ".$map[$action], 'user', $rid);
            $msg = "User #$rid updated.";
        }
    }
}

// ── Filters ────────────────────────────────────────────────
$search = trim($_GET['q']      ?? '');
$status = $_GET['status']      ?? 'all';
$type   = $_GET['type']        ?? 'all'; // all | customer | vendor
$page   = max(1, (int)($_GET['page'] ?? 1));
$per    = 25;
$offset = ($page - 1) * $per;

// ── Build UNION query: customers + vendors ─────────────────
// We normalise the columns so both result sets look the same:
//   record_type, id, full_name, phone, email, display_status,
//   balance/code_balance → shown as "balance", created_at

$userWhere   = "1=1";
$vendorWhere = "1=1";
$uParams     = [];
$vParams     = [];

// Status filter
if ($status !== 'all') {
    $userWhere   .= " AND u.status = ?";
    $uParams[]    = $status;

    // Map user status values to vendor equivalents
    $vendorStatusMap = ['active' => 'active', 'suspended' => 'suspended', 'banned' => 'rejected'];
    $vs = $vendorStatusMap[$status] ?? $status;
    $vendorWhere .= " AND v.status = ?";
    $vParams[]    = $vs;
}

// Search filter
if ($search) {
    $s = "%$search%";
    $userWhere   .= " AND (u.full_name LIKE ? OR u.phone LIKE ? OR u.email LIKE ?)";
    $uParams      = array_merge($uParams, [$s, $s, $s]);
    $vendorWhere .= " AND (v.full_name LIKE ? OR v.phone LIKE ? OR v.email LIKE ? OR v.business_name LIKE ?)";
    $vParams      = array_merge($vParams, [$s, $s, $s, $s]);
}

// Type filter — skip one leg of the UNION if filtered
$includeUsers   = ($type !== 'vendor');
$includeVendors = ($type !== 'customer');

// Build the UNION SQL
$unionParts  = [];
$unionParams = [];

if ($includeUsers) {
    $unionParts[]  = "SELECT
        'customer'          AS record_type,
        u.id                AS id,
        u.full_name         AS full_name,
        u.phone             AS phone,
        u.email             AS email,
        u.status            AS display_status,
        u.balance           AS balance,
        NULL                AS business_name,
        u.created_at        AS created_at
    FROM users u
    WHERE $userWhere";
    $unionParams = array_merge($unionParams, $uParams);
}

if ($includeVendors) {
    $unionParts[]  = "SELECT
        'vendor'            AS record_type,
        v.id                AS id,
        v.full_name         AS full_name,
        v.phone             AS phone,
        v.email             AS email,
        v.status            AS display_status,
        v.code_balance      AS balance,
        v.business_name     AS business_name,
        v.created_at        AS created_at
    FROM vendors v
    WHERE $vendorWhere";
    $unionParams = array_merge($unionParams, $vParams);
}

// Count total
$countSql = "SELECT COUNT(*) FROM (".implode(" UNION ALL ", $unionParts).") sub";
$cntStmt  = $db->prepare($countSql);
$cntStmt->execute($unionParams);
$total    = (int)$cntStmt->fetchColumn();
$pages    = (int)ceil($total / $per);

// Fetch page
$dataSql  = "SELECT * FROM (".implode(" UNION ALL ", $unionParts).") sub ORDER BY created_at DESC LIMIT $per OFFSET $offset";
$dataStmt = $db->prepare($dataSql);
$dataStmt->execute($unionParams);
$records  = $dataStmt->fetchAll();

// Quick counts for filter pills
$totalUsers   = (int)$db->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalVendors = (int)$db->query("SELECT COUNT(*) FROM vendors")->fetchColumn();

$aPage = 'users';
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="csrf-token" content="<?= generateCsrf() ?>">
  <title>Users & Vendors — <?= APP_NAME ?> Admin</title>
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
    <h1 class="text-xl font-bold">Users &amp; Vendors</h1>
    <div class="text-sm text-gray-400"><?= number_format($total) ?> records</div>
  </div>

  <div class="p-4 md:p-6">

    <?php if ($msg): ?>
    <div class="bg-green-500/10 border border-green-500/30 text-green-400 rounded-xl p-4 mb-5 text-sm">
      ✅ <?= e($msg) ?>
    </div>
    <?php endif; ?>

    <!-- Quick summary pills -->
    <div class="flex gap-3 mb-5 flex-wrap">
      <div class="card px-4 py-2 text-sm flex items-center gap-2">
        <span class="text-orange-400 font-black text-lg"><?= number_format($totalUsers) ?></span>
        <span class="text-gray-400">Customers</span>
      </div>
      <div class="card px-4 py-2 text-sm flex items-center gap-2">
        <span class="text-purple-400 font-black text-lg"><?= number_format($totalVendors) ?></span>
        <span class="text-gray-400">Vendors</span>
      </div>
    </div>

    <!-- Search -->
    <div class="flex flex-wrap gap-3 mb-4">
      <form class="flex gap-2 flex-1 min-w-48" method="GET">
        <input type="text" name="q" class="form-control"
               placeholder="Name, phone, email, business…" value="<?= e($search) ?>">
        <input type="hidden" name="status" value="<?= e($status) ?>">
        <input type="hidden" name="type"   value="<?= e($type) ?>">
        <button class="btn btn-primary px-5">Search</button>
        <?php if ($search): ?>
        <a href="?status=<?= e($status) ?>&type=<?= e($type) ?>" class="btn btn-secondary px-4">Clear</a>
        <?php endif; ?>
      </form>
    </div>

    <!-- Filters -->
    <div class="flex flex-wrap gap-2 mb-5">
      <!-- Status -->
      <?php foreach (['all' => 'All Status', 'active' => 'Active', 'suspended' => 'Suspended', 'banned' => 'Banned'] as $v => $l): ?>
      <a href="?status=<?= $v ?>&type=<?= e($type) ?>&q=<?= urlencode($search) ?>"
         class="btn btn-sm <?= $status === $v ? 'btn-primary' : 'btn-secondary' ?>">
        <?= $l ?>
      </a>
      <?php endforeach; ?>

      <span class="border-l border-white/10 mx-1"></span>

      <!-- Type -->
      <a href="?type=all&status=<?= e($status) ?>&q=<?= urlencode($search) ?>"
         class="btn btn-sm <?= $type === 'all' ? 'btn-primary' : 'btn-secondary' ?>">
        All Types
      </a>
      <a href="?type=customer&status=<?= e($status) ?>&q=<?= urlencode($search) ?>"
         class="btn btn-sm <?= $type === 'customer' ? 'btn-primary' : 'btn-secondary' ?>">
        👤 Customers only
      </a>
      <a href="?type=vendor&status=<?= e($status) ?>&q=<?= urlencode($search) ?>"
         class="btn btn-sm <?= $type === 'vendor' ? 'btn-primary' : 'btn-secondary' ?>">
        🏪 Vendors only
      </a>
    </div>

    <!-- Table -->
    <div class="card">
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Name</th>
              <th>Phone</th>
              <th>Type</th>
              <th>Balance</th>
              <th>Status</th>
              <th>Joined</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($records as $r):
              $isVendor = ($r['record_type'] === 'vendor');
            ?>
            <tr>
              <!-- Name + email -->
              <td>
                <div class="flex items-center gap-2">
                  <div class="w-8 h-8 rounded-lg flex items-center justify-center font-bold text-xs flex-shrink-0
                    <?= $isVendor ? 'bg-purple-500/20 text-purple-400' : 'bg-orange-500/15 text-orange-400' ?>">
                    <?= strtoupper($r['full_name'][0]) ?>
                  </div>
                  <div class="min-w-0">
                    <div class="text-sm font-medium truncate"><?= e($r['full_name']) ?></div>
                    <div class="text-xs text-gray-500 truncate">
                      <?= $isVendor && $r['business_name']
                          ? '🏪 '.e($r['business_name'])
                          : e($r['email'] ?? '—') ?>
                    </div>
                  </div>
                </div>
              </td>

              <!-- Phone -->
              <td class="font-mono text-sm"><?= e(formatPhone($r['phone'])) ?></td>

              <!-- Type badge -->
              <td>
                <?php if ($isVendor): ?>
                <span class="badge text-xs" style="background:rgba(168,85,247,.2);color:#a855f7">🏪 Vendor</span>
                <?php else: ?>
                <span class="badge badge-muted text-xs">👤 Customer</span>
                <?php endif; ?>
              </td>

              <!-- Balance -->
              <td>
                <span class="font-semibold <?= $isVendor ? 'text-purple-400' : 'text-orange-400' ?>">
                  <?= number_format((int)$r['balance']) ?>
                </span>
                <span class="text-xs text-gray-600 ml-0.5"><?= $isVendor ? 'codes' : 'codes' ?></span>
              </td>

              <!-- Status -->
              <td>
                <?php
                  $ds = $r['display_status'];
                  $badgeClass = match($ds) {
                    'active'    => 'badge-success',
                    'suspended' => 'badge-warning',
                    'banned','rejected' => 'badge-danger',
                    'pending'   => 'badge-warning',
                    default     => 'badge-muted'
                  };
                ?>
                <span class="badge <?= $badgeClass ?>"><?= ucfirst($ds) ?></span>
              </td>

              <!-- Joined -->
              <td class="text-xs text-gray-500"><?= date('M j, Y', strtotime($r['created_at'])) ?></td>

              <!-- Actions -->
              <td>
                <div class="flex gap-1.5 flex-wrap">
                  <?php if ($isVendor): ?>
                  <a href="<?= APP_URL ?>/admin/vendors.php"
                     class="btn btn-sm btn-secondary text-xs text-purple-400">🏪 View</a>
                  <?php else: ?>
                  <a href="<?= APP_URL ?>/admin/user-detail.php?id=<?= $r['id'] ?>"
                     class="btn btn-sm btn-secondary text-xs">View</a>
                  <?php endif; ?>

                  <?php if ($ds === 'active'): ?>
                  <button onclick="doAction(<?= $r['id'] ?>, '<?= $r['record_type'] ?>', 'suspend')"
                          class="btn btn-sm btn-secondary text-xs text-yellow-400">Suspend</button>
                  <?php elseif (in_array($ds, ['suspended','rejected','pending'])): ?>
                  <button onclick="doAction(<?= $r['id'] ?>, '<?= $r['record_type'] ?>', 'activate')"
                          class="btn btn-sm btn-secondary text-xs text-green-400">Activate</button>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>

            <?php if (!$records): ?>
            <tr>
              <td colspan="7" class="text-center text-gray-500 py-10">
                No records found<?= $search ? ' for "'.e($search).'"' : '' ?>
              </td>
            </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <?php if ($pages > 1): ?>
      <div class="flex items-center justify-between p-4 border-t border-white/5">
        <div class="text-sm text-gray-400">
          Page <?= $page ?>/<?= $pages ?> · <?= number_format($total) ?> records
        </div>
        <div class="flex gap-2">
          <?php if ($page > 1): ?>
          <a href="?page=<?= $page-1 ?>&status=<?= e($status) ?>&type=<?= e($type) ?>&q=<?= urlencode($search) ?>"
             class="btn btn-sm btn-secondary">← Prev</a>
          <?php endif; ?>
          <?php if ($page < $pages): ?>
          <a href="?page=<?= $page+1 ?>&status=<?= e($status) ?>&type=<?= e($type) ?>&q=<?= urlencode($search) ?>"
             class="btn btn-sm btn-secondary">Next →</a>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>

  </div>
</div>

<!-- Hidden action form -->
<form id="af" method="POST" style="display:none">
  <?= csrfField() ?>
  <input type="hidden" name="action"      id="af-action">
  <input type="hidden" name="record_id"   id="af-rid">
  <input type="hidden" name="record_type" id="af-rtype">
</form>

<script>
  window.APP_URL = '<?= APP_URL ?>';
</script>
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
<script>
function doAction(id, type, action) {
  const label = type === 'vendor' ? 'vendor' : 'user';
  if (!confirm(action + ' this ' + label + '?')) return;
  document.getElementById('af-action').value = action;
  document.getElementById('af-rid').value    = id;
  document.getElementById('af-rtype').value  = type;
  document.getElementById('af').submit();
}
</script>
</body>
</html>
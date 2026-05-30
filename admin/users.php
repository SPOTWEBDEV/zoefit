<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
$auth = requireAdmin(); $adminId = $auth['id'];
$db = getDB();

$msg = $err = '';
// Handle actions
if (isPost() && verifyCsrf($_POST[CSRF_TOKEN_NAME]??'')) {
  $action = $_POST['action']??'';
  $uid    = (int)($_POST['user_id']??0);
  if ($uid && in_array($action,['suspend','activate','ban'])) {
    $statusMap = ['suspend'=>'suspended','activate'=>'active','ban'=>'banned'];
    $db->prepare("UPDATE users SET status=? WHERE id=?")->execute([$statusMap[$action],$uid]);
    auditLog('admin',$adminId,$action.'_user',"User $uid status changed to ".$statusMap[$action],'user',$uid);
    $msg = "User #$uid status updated.";
  }
}

$search  = trim($_GET['q']??'');
$status  = $_GET['status']??'all';
$page    = max(1,(int)($_GET['page']??1));
$perPage = 20; $offset = ($page-1)*$perPage;

$where = "1=1"; $params = [];
if ($search) { $where .= " AND (full_name LIKE ? OR phone LIKE ? OR email LIKE ?)"; $s="%$search%"; $params=array_merge($params,[$s,$s,$s]); }
if ($status!=='all') { $where .= " AND status=?"; $params[]=$status; }

$users = $db->prepare("SELECT * FROM users WHERE $where ORDER BY created_at DESC LIMIT $perPage OFFSET $offset");
$users->execute($params); $users = $users->fetchAll();
$total = $db->prepare("SELECT COUNT(*) FROM users WHERE $where"); $total->execute($params); $total=$total->fetchColumn();
$pages = ceil($total/$perPage);

$aPage = 'users'; $pageTitle = 'Users';
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="csrf-token" content="<?= generateCsrf() ?>">
  <title><?= e($pageTitle) ?> — <?= APP_NAME ?> Admin</title>
  <script src="<?= APP_URL ?>/assets/js/app.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
  <style>*{font-family:'Poppins',sans-serif!important}</style>
</head>
<body class="bg-[#0a0f1a] text-white">
<?php include __DIR__ . '/../components/admin-sidebar.php'; ?>
<div class="main-content">
  <div class="topbar">
    <button onclick="toggleSidebar()" class="md:hidden text-gray-400 text-2xl mr-3">☰</button>
    <h1 class="text-xl font-bold">Users</h1>
    <div class="text-sm text-gray-400"><?= number_format($total) ?> total</div>
  </div>
  <div class="p-6">
    <?php if($msg): ?><div class="bg-green-500/10 border border-green-500/30 text-green-400 rounded-xl p-4 mb-5 text-sm"><?= e($msg) ?></div><?php endif; ?>

    <!-- Search & Filters -->
    <div class="flex flex-wrap gap-3 mb-5">
      <form class="flex gap-2 flex-1 min-w-64" method="GET">
        <input type="text" name="q" class="form-control" placeholder="Search name, phone, email…" value="<?= e($search) ?>">
        <input type="hidden" name="status" value="<?= e($status) ?>">
        <button class="btn btn-primary px-5">Search</button>
      </form>
      <div class="flex gap-2">
        <?php foreach(['all'=>'All','active'=>'Active','suspended'=>'Suspended','banned'=>'Banned'] as $v=>$l): ?>
        <a href="?status=<?= $v ?>&q=<?= urlencode($search) ?>" class="btn btn-sm <?= $status===$v?'btn-primary':'btn-secondary' ?>"><?= $l ?></a>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Table -->
    <div class="card">
      <div class="table-wrap">
        <table>
          <thead><tr><th>User</th><th>Phone</th><th>Balance</th><th>Status</th><th>Joined</th><th>Actions</th></tr></thead>
          <tbody>
            <?php foreach ($users as $u): ?>
            <tr>
              <td>
                <div class="flex items-center gap-3">
                  <div class="w-8 h-8 bg-orange-500/15 rounded-lg flex items-center justify-center text-sm font-bold text-orange-400"><?= strtoupper($u['full_name'][0]) ?></div>
                  <div>
                    <div class="font-medium text-sm"><?= e($u['full_name']) ?></div>
                    <div class="text-xs text-gray-500"><?= e($u['email']??'—') ?></div>
                  </div>
                </div>
              </td>
              <td class="text-sm font-mono"><?= e(formatPhone($u['phone'])) ?></td>
              <td><span class="font-semibold text-orange-400"><?= $u['balance'] ?></span></td>
              <td>
                <span class="badge <?= match($u['status']){'active'=>'badge-success','suspended'=>'badge-warning','banned'=>'badge-danger',default=>'badge-muted'} ?>">
                  <?= ucfirst($u['status']) ?>
                </span>
              </td>
              <td class="text-xs text-gray-500"><?= date('M j, Y',strtotime($u['created_at'])) ?></td>
              <td>
                <div class="flex items-center gap-2">
                  <a href="<?= APP_URL ?>/admin/user-detail.php?id=<?= $u['id'] ?>" class="btn btn-sm btn-secondary text-xs">View</a>
                  <?php if($u['status']==='active'): ?>
                    <button onclick="userAction(<?= $u['id'] ?>,'suspend')" class="btn btn-sm btn-secondary text-xs text-yellow-400">Suspend</button>
                  <?php else: ?>
                    <button onclick="userAction(<?= $u['id'] ?>,'activate')" class="btn btn-sm btn-secondary text-xs text-green-400">Activate</button>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if(!$users): ?><tr><td colspan="6" class="text-center text-gray-500 py-8">No users found</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
      <!-- Pagination -->
      <?php if($pages>1): ?>
      <div class="flex items-center justify-between p-4 border-t border-white/5">
        <div class="text-sm text-gray-400">Page <?= $page ?> of <?= $pages ?></div>
        <div class="flex gap-2">
          <?php if($page>1): ?><a href="?page=<?= $page-1 ?>&q=<?= urlencode($search) ?>&status=<?= $status ?>" class="btn btn-sm btn-secondary">← Prev</a><?php endif; ?>
          <?php if($page<$pages): ?><a href="?page=<?= $page+1 ?>&q=<?= urlencode($search) ?>&status=<?= $status ?>" class="btn btn-sm btn-secondary">Next →</a><?php endif; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<!-- Action form (hidden) -->
<form id="action-form" method="POST" style="display:none">
  <?= csrfField() ?>
  <input type="hidden" name="action" id="action-field">
  <input type="hidden" name="user_id" id="action-uid">
</form>
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
<script>
function userAction(id, action) {
  if (!confirm(`Are you sure you want to ${action} this user?`)) return;
  document.getElementById('action-field').value = action;
  document.getElementById('action-uid').value   = id;
  document.getElementById('action-form').submit();
}
</script>
</body></html>

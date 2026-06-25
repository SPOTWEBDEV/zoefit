<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
$auth = requireAdmin(); $adminId = $auth['id'];
$db   = getDB();
$msg  = '';

if (isPost() && verifyCsrf($_POST[CSRF_TOKEN_NAME]??'')) {
  $action = $_POST['action']??'';
  $uid    = (int)($_POST['user_id']??0);
  if ($uid && in_array($action,['suspend','activate','ban'])) {
    $map=['suspend'=>'suspended','activate'=>'active','ban'=>'banned'];
    $db->prepare("UPDATE users SET status=? WHERE id=?")->execute([$map[$action],$uid]);
    auditLog('admin',$adminId,$action.'_user',"User $uid → ".$map[$action],'user',$uid);
    $msg="User #$uid updated.";
  }
}

$search = trim($_GET['q']??'');
$status = $_GET['status']??'all';
$type   = $_GET['type']??'all';   // all | user | vendor
$page   = max(1,(int)($_GET['page']??1));
$per    = 25; $offset=($page-1)*$per;

$where='1=1'; $params=[];
if ($status!=='all') { $where.=" AND status=?"; $params[]=$status; }
if ($type==='user')   { $where.=" AND is_vendor=0"; }
if ($type==='vendor') { $where.=" AND is_vendor=1 AND vendor_status='active'"; }
if ($search) {
  $where.=" AND (full_name LIKE ? OR phone LIKE ? OR email LIKE ?)";
  $s="%$search%"; $params=array_merge($params,[$s,$s,$s]);
}

$users=$db->prepare("SELECT * FROM users WHERE $where ORDER BY created_at DESC LIMIT $per OFFSET $offset");
$users->execute($params); $users=$users->fetchAll();
$cnt=$db->prepare("SELECT COUNT(*) FROM users WHERE $where"); $cnt->execute($params); $total=$cnt->fetchColumn();
$pages=ceil($total/$per);

$aPage='users';
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="csrf-token" content="<?= generateCsrf() ?>">
  <title>Users — <?= APP_NAME ?> Admin</title>
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
    <h1 class="text-xl font-bold">Users</h1>
    <div class="text-sm text-gray-400"><?= number_format($total) ?> records</div>
  </div>
  <div class="p-4 md:p-6">
    <?php if($msg): ?><div class="bg-green-500/10 border border-green-500/30 text-green-400 rounded-xl p-4 mb-5 text-sm"><?= e($msg) ?></div><?php endif; ?>

    <div class="flex flex-wrap gap-3 mb-4">
      <form class="flex gap-2 flex-1 min-w-48" method="GET">
        <input type="text" name="q" class="form-control" placeholder="Name, phone, email…" value="<?= e($search) ?>">
        <input type="hidden" name="status" value="<?= e($status) ?>">
        <input type="hidden" name="type" value="<?= e($type) ?>">
        <button class="btn btn-primary px-5">Search</button>
      </form>
    </div>
    <div class="flex flex-wrap gap-2 mb-5">
      <?php foreach(['all'=>'All','active'=>'Active','suspended'=>'Suspended','banned'=>'Banned'] as $v=>$l): ?>
      <a href="?status=<?=$v?>&type=<?=e($type)?>&q=<?=urlencode($search)?>" class="btn btn-sm <?=$status===$v?'btn-primary':'btn-secondary'?>"><?=$l?></a>
      <?php endforeach; ?>
      <span class="border-l border-white/10 mx-1"></span>
      <?php foreach(['all'=>'All Types','user'=>'Users Only','vendor'=>'Vendors Only'] as $v=>$l): ?>
      <a href="?type=<?=$v?>&status=<?=e($status)?>&q=<?=urlencode($search)?>" class="btn btn-sm <?=$type===$v?'btn-primary':'btn-secondary'?>"><?=$l?></a>
      <?php endforeach; ?>
    </div>

    <div class="card">
      <div class="table-wrap">
        <table>
          <thead><tr><th>User</th><th>Phone</th><th>Type</th><th>Balance</th><th>Status</th><th>Joined</th><th>Actions</th></tr></thead>
          <tbody>
            <?php foreach($users as $u): ?>
            <tr>
              <td>
                <div class="flex items-center gap-2">
                  <div class="w-8 h-8 rounded-lg flex items-center justify-center font-bold text-xs flex-shrink-0 <?= $u['is_vendor']?'bg-purple-500/20 text-purple-400':'bg-orange-500/15 text-orange-400' ?>"><?= strtoupper($u['full_name'][0]) ?></div>
                  <div class="min-w-0"><div class="text-sm font-medium truncate"><?= e($u['full_name']) ?></div><div class="text-xs text-gray-500 truncate"><?= e($u['email']??'—') ?></div></div>
                </div>
              </td>
              <td class="font-mono text-sm"><?= e(formatPhone($u['phone'])) ?></td>
              <td>
                <?php if($u['is_vendor']): ?>
                <span class="badge text-xs" style="background:rgba(168,85,247,.2);color:#a855f7">🏪 Vendor</span>
                <?php else: ?>
                <span class="badge badge-muted text-xs">👤 User</span>
                <?php endif; ?>
              </td>
              <td><span class="font-semibold text-orange-400"><?= $u['balance'] ?></span></td>
              <td><span class="badge <?= match($u['status']){'active'=>'badge-success','suspended'=>'badge-warning','banned'=>'badge-danger',default=>'badge-muted'} ?>"><?= ucfirst($u['status']) ?></span></td>
              <td class="text-xs text-gray-500"><?= date('M j, Y',strtotime($u['created_at'])) ?></td>
              <td>
                <div class="flex gap-1.5">
                  <a href="<?= APP_URL ?>/admin/user-detail.php?id=<?= $u['id'] ?>" class="btn btn-sm btn-secondary text-xs">View</a>
                  <?php if($u['status']==='active'): ?>
                  <button onclick="doAction(<?=$u['id']?>,'suspend')" class="btn btn-sm btn-secondary text-xs text-yellow-400">Suspend</button>
                  <?php else: ?>
                  <button onclick="doAction(<?=$u['id']?>,'activate')" class="btn btn-sm btn-secondary text-xs text-green-400">Activate</button>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if(!$users): ?><tr><td colspan="7" class="text-center text-gray-500 py-10">No users found</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
      <?php if($pages>1): ?>
      <div class="flex items-center justify-between p-4 border-t border-white/5">
        <div class="text-sm text-gray-400">Page <?=$page?>/<?=$pages?></div>
        <div class="flex gap-2">
          <?php if($page>1): ?><a href="?page=<?=$page-1?>&status=<?=e($status)?>&type=<?=e($type)?>&q=<?=urlencode($search)?>" class="btn btn-sm btn-secondary">← Prev</a><?php endif; ?>
          <?php if($page<$pages): ?><a href="?page=<?=$page+1?>&status=<?=e($status)?>&type=<?=e($type)?>&q=<?=urlencode($search)?>" class="btn btn-sm btn-secondary">Next →</a><?php endif; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<form id="af" method="POST" style="display:none"><?= csrfField() ?><input type="hidden" name="action" id="af-action"><input type="hidden" name="user_id" id="af-uid"></form>
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
<script>function doAction(id,a){if(!confirm(a+' this user?'))return;document.getElementById('af-action').value=a;document.getElementById('af-uid').value=id;document.getElementById('af').submit();}</script>
</body></html>

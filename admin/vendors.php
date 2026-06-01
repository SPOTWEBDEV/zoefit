
<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
$auth = requireAdmin(); $adminId = $auth['id'];
$db   = getDB();
$msg  = $err = '';

// Actions
if (isPost() && verifyCsrf($_POST[CSRF_TOKEN_NAME]??'')) {
  $action = $_POST['action']??'';
  $uid    = (int)($_POST['user_id']??0);

  if ($uid && $action === 'assign_codes') {
    $qty = min(10000,max(1,(int)($_POST['code_count']??0)));
    $rows=$db->prepare("SELECT id FROM codes WHERE status='unassigned' LIMIT $qty");
    $rows->execute(); $rows=$rows->fetchAll();
    $assigned=0;
    foreach($rows as $r){
      $db->prepare("UPDATE codes SET assigned_vendor=?,status='assigned',assigned_at=NOW() WHERE id=?")->execute([$uid,$r['id']]);
      $assigned++;
    }
    if ($assigned) $db->prepare("UPDATE users SET vendor_code_balance=vendor_code_balance+? WHERE id=?")->execute([$assigned,$uid]);
    auditLog('admin',$adminId,'assign_codes_vendor',"$assigned codes → vendor $uid",'user',$uid);
    $msg="$assigned codes assigned.";
  } elseif ($uid && in_array($action,['suspend','activate','reactivate'])) {
    $map=['suspend'=>'suspended','activate'=>'active','reactivate'=>'active'];
    $db->prepare("UPDATE users SET vendor_status=? WHERE id=? AND is_vendor=1")->execute([$map[$action],$uid]);
    auditLog('admin',$adminId,$action.'_vendor',"Vendor $uid $action",'user',$uid);
    $msg='Vendor updated.';
  }
}

$tab='active'; if (in_array($_GET['tab']??'',['active','suspended'])) $tab=$_GET['tab'];
$q=trim($_GET['q']??'');
$page=max(1,(int)($_GET['page']??1)); $per=20; $offset=($page-1)*$per;

$where="is_vendor=1 AND vendor_status='$tab'";
$params=[];
if ($q){ $where.=" AND (full_name LIKE ? OR phone LIKE ? OR vendor_business_name LIKE ?)"; $s="%$q%"; $params=[$s,$s,$s]; }

$vendors=$db->prepare("SELECT *,(SELECT COUNT(*) FROM codes WHERE assigned_vendor=u.id) as total_codes FROM users u WHERE $where ORDER BY vendor_approved_at DESC LIMIT $per OFFSET $offset");
$vendors->execute($params); $vendors=$vendors->fetchAll();
$cnt=$db->prepare("SELECT COUNT(*) FROM users u WHERE $where"); $cnt->execute($params); $total=$cnt->fetchColumn();
$pages=ceil($total/$per);

$unassignedCodes=(int)$db->query("SELECT COUNT(*) FROM codes WHERE status='unassigned'")->fetchColumn();
$aPage='vendors';
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="csrf-token" content="<?= generateCsrf() ?>">
  <title>Vendors — <?= APP_NAME ?> Admin</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
  <style>*{font-family:'Poppins',sans-serif!important}</style>
</head>
<body class="bg-[#0a0f1a] text-white">
<?php include __DIR__ . '/../components/admin-sidebar.php'; ?>
<div class="main-content">
  <div class="topbar">
    <button onclick="toggleSidebar()" class="md:hidden text-gray-400 text-2xl mr-3">☰</button>
    <h1 class="text-xl font-bold">Active Vendors</h1>
    <a href="<?= APP_URL ?>/admin/vendor-requests.php" class="btn btn-primary btn-sm">+ Vendor Requests</a>
  </div>
  <div class="p-4 md:p-6">
    <?php if($msg): ?><div class="bg-green-500/10 border border-green-500/30 text-green-400 rounded-xl p-4 mb-5 text-sm"><?= e($msg) ?></div><?php endif; ?>

    <div class="flex flex-wrap gap-3 mb-5">
      <form class="flex gap-2 flex-1 min-w-48" method="GET">
        <input type="text" name="q" class="form-control" placeholder="Search vendor…" value="<?= e($q) ?>">
        <input type="hidden" name="tab" value="<?= e($tab) ?>">
        <button class="btn btn-primary px-5">Search</button>
      </form>
      <div class="flex gap-2">
        <a href="?tab=active"    class="btn btn-sm <?= $tab==='active'?'btn-primary':'btn-secondary' ?>">Active</a>
        <a href="?tab=suspended" class="btn btn-sm <?= $tab==='suspended'?'btn-primary':'btn-secondary' ?>">Suspended</a>
      </div>
    </div>

    <div class="space-y-4">
      <?php foreach($vendors as $v): ?>
      <div class="card p-5">
        <div class="flex flex-col md:flex-row md:items-start gap-4">
          <div class="flex items-start gap-3 flex-1 min-w-0">
            <div class="w-12 h-12 bg-purple-500/20 rounded-2xl flex items-center justify-center text-xl font-black text-purple-400 flex-shrink-0">
              <?= strtoupper($v['full_name'][0]) ?>
            </div>
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2 flex-wrap">
                <a href="<?= APP_URL ?>/admin/user-detail.php?id=<?= $v['id'] ?>" class="font-bold hover:text-orange-400"><?= e($v['full_name']) ?></a>
                <span class="badge <?= $v['vendor_status']==='active'?'badge-success':'badge-danger' ?>"><?= ucfirst($v['vendor_status']) ?></span>
              </div>
              <div class="text-sm text-gray-400 mt-0.5"><?= e(formatPhone($v['phone'])) ?> · <?= e($v['email']) ?></div>
              <div class="text-sm text-gray-400"><?= e($v['vendor_business_name']??'No business name') ?></div>
              <div class="flex flex-wrap gap-4 mt-2 text-xs text-gray-500">
                <span>💼 <strong class="text-green-400"><?= $v['vendor_code_balance'] ?></strong> available</span>
                <span>📦 <?= $v['total_codes'] ?> total assigned</span>
                <span>📅 Approved <?= $v['vendor_approved_at']?date('M j, Y',strtotime($v['vendor_approved_at'])):'—' ?></span>
              </div>
            </div>
          </div>
          <div class="flex gap-2 flex-shrink-0">
            <button onclick="openAssign(<?= $v['id'] ?>,'<?= e(addslashes($v['full_name'])) ?>')" class="btn btn-sm btn-primary text-xs">🎟️ Assign Codes</button>
            <?php if($v['vendor_status']==='active'): ?>
            <form method="POST"><<?= csrfField() ?><input type="hidden" name="action" value="suspend"><input type="hidden" name="user_id" value="<?= $v['id'] ?>">
              <button class="btn btn-sm btn-secondary text-xs text-yellow-400">Suspend</button></form>
            <?php else: ?>
            <form method="POST"><?= csrfField() ?><input type="hidden" name="action" value="reactivate"><input type="hidden" name="user_id" value="<?= $v['id'] ?>">
              <button class="btn btn-sm text-xs py-1.5 px-3" style="background:#22c55e;color:white">Reactivate</button></form>
            <?php endif; ?>
            <a href="<?= APP_URL ?>/admin/user-detail.php?id=<?= $v['id'] ?>" class="btn btn-sm btn-secondary text-xs">👤 Profile</a>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
      <?php if(!$vendors): ?><div class="card p-12 text-center"><div class="text-4xl mb-3">🏪</div><div class="text-gray-500">No <?= $tab ?> vendors. <a href="<?= APP_URL ?>/admin/vendor-requests.php" class="text-orange-400 underline">Review applications →</a></div></div><?php endif; ?>
    </div>

    <?php if($pages>1): ?>
    <div class="flex justify-between items-center mt-5">
      <div class="text-sm text-gray-400">Page <?= $page ?>/<?= $pages ?></div>
      <div class="flex gap-2">
        <?php if($page>1): ?><a href="?tab=<?=$tab?>&page=<?=$page-1?>&q=<?=urlencode($q)?>" class="btn btn-sm btn-secondary">← Prev</a><?php endif; ?>
        <?php if($page<$pages): ?><a href="?tab=<?=$tab?>&page=<?=$page+1?>&q=<?=urlencode($q)?>" class="btn btn-sm btn-secondary">Next →</a><?php endif; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Assign Codes Modal -->
<div class="modal-overlay" id="assign-modal">
  <div class="modal-box">
    <h3 class="text-xl font-bold mb-2">Assign Codes to Vendor</h3>
    <p class="text-gray-400 text-sm mb-4">Vendor: <strong id="assign-name" class="text-white"></strong></p>
    <div class="text-sm text-gray-400 mb-4">Unassigned codes in pool: <strong class="text-orange-400"><?= number_format($unassignedCodes) ?></strong></div>
    <form method="POST">
      <?= csrfField() ?><input type="hidden" name="action" value="assign_codes"><input type="hidden" name="user_id" id="assign-uid">
      <div class="form-group"><label class="form-label">Number of Codes</label><input type="number" name="code_count" class="form-control" min="1" max="<?= $unassignedCodes ?>" value="100" required></div>
      <div class="flex gap-3">
        <button type="button" data-close-modal="assign-modal" class="btn btn-secondary flex-1">Cancel</button>
        <button type="submit" class="btn btn-primary flex-1">Assign</button>
      </div>
    </form>
  </div>
</div>
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
<script>
function openAssign(id,name){ document.getElementById('assign-uid').value=id; document.getElementById('assign-name').textContent=name; Modal.open('assign-modal'); }
</script>
</body></html>

<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
$auth = requireAdmin(); $adminId = $auth['id'];
$db = getDB();
$msg=$err='';

// Handle POST actions
if (isPost() && verifyCsrf($_POST[CSRF_TOKEN_NAME]??'')) {
  $action = $_POST['action']??'';

  if ($action === 'create') {
    $name     = trim($_POST['full_name']??'');
    $bname    = trim($_POST['business_name']??'');
    $phone    = normalizePhone(trim($_POST['phone']??''));
    $email    = trim($_POST['email']??'');
    $password = $_POST['password']??'';
    if (!$name||!$phone||!$email||!$password) { $err='All fields required.'; }
    else {
      $check=$db->prepare("SELECT id FROM vendors WHERE phone=? OR email=?");$check->execute([$phone,$email]);
      if ($check->fetch()) { $err='Phone or email already exists.'; }
      else {
        $db->prepare("INSERT INTO vendors (full_name,business_name,phone,email,password,status,created_by) VALUES (?,?,?,?,?,?,?)")
           ->execute([$name,$bname,$phone,$email,password_hash($password,PASSWORD_BCRYPT),'active',$adminId]);
        $vid=$db->lastInsertId();
        auditLog('admin',$adminId,'create_vendor',"Vendor $name created",'vendor',$vid);
        $msg="Vendor '$name' created successfully.";
      }
    }
  } elseif (in_array($action,['suspend','activate']) && ($vid=(int)($_POST['vendor_id']??0))) {
    $status = $action==='activate'?'active':'suspended';
    $db->prepare("UPDATE vendors SET status=? WHERE id=?")->execute([$status,$vid]);
    auditLog('admin',$adminId,$action.'_vendor',"Vendor $vid $action-d",'vendor',$vid);
    $msg="Vendor updated.";
  } elseif ($action==='assign_codes' && ($vid=(int)($_POST['vendor_id']??0))) {
    $count=(int)($_POST['code_count']??0);
    if ($count>0) {
      // Get unassigned codes
      $codes=$db->prepare("SELECT id FROM codes WHERE status='unassigned' LIMIT $count");$codes->execute();$codes=$codes->fetchAll();
      foreach ($codes as $c) {
        $db->prepare("UPDATE codes SET assigned_vendor=?,status='assigned',assigned_at=NOW() WHERE id=?")->execute([$vid,$c['id']]);
      }
      $db->prepare("UPDATE vendors SET code_balance=code_balance+? WHERE id=?")->execute([count($codes),$vid]);
      auditLog('admin',$adminId,'assign_codes',count($codes)." codes assigned to vendor $vid",'vendor',$vid);
      $msg=count($codes)." codes assigned to vendor.";
    }
  }
}

$vendors = $db->query("SELECT v.*, (SELECT COUNT(*) FROM codes WHERE assigned_vendor=v.id) as total_codes FROM vendors v ORDER BY v.created_at DESC")->fetchAll();
$aPage='vendors';
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="csrf-token" content="<?= generateCsrf() ?>">
  <title>Vendors — <?= APP_NAME ?> Admin</title>
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
    <h1 class="text-xl font-bold">Vendors</h1>
    <button onclick="Modal.open('create-vendor-modal')" class="btn btn-primary btn-sm">+ Add Vendor</button>
  </div>
  <div class="p-6">
    <?php if($msg): ?><div class="bg-green-500/10 border border-green-500/30 text-green-400 rounded-xl p-4 mb-5 text-sm"><?= e($msg) ?></div><?php endif; ?>
    <?php if($err): ?><div class="bg-red-500/10 border border-red-500/30 text-red-400 rounded-xl p-4 mb-5 text-sm"><?= e($err) ?></div><?php endif; ?>

    <div class="card">
      <div class="table-wrap">
        <table>
          <thead><tr><th>Vendor</th><th>Phone</th><th>Inventory</th><th>Total Assigned</th><th>Status</th><th>Actions</th></tr></thead>
          <tbody>
            <?php foreach($vendors as $v): ?>
            <tr>
              <td>
                <div class="font-medium"><?= e($v['full_name']) ?></div>
                <div class="text-xs text-gray-500"><?= e($v['business_name']??'—') ?> · <?= e($v['email']) ?></div>
              </td>
              <td class="text-sm font-mono"><?= e(formatPhone($v['phone'])) ?></td>
              <td><span class="font-bold text-green-400"><?= $v['code_balance'] ?></span> available</td>
              <td><?= $v['total_codes'] ?></td>
              <td><span class="badge <?= $v['status']==='active'?'badge-success':($v['status']==='pending'?'badge-warning':'badge-danger') ?>"><?= ucfirst($v['status']) ?></span></td>
              <td>
                <div class="flex items-center gap-2">
                  <button onclick="openAssign(<?= $v['id'] ?>, '<?= e($v['full_name']) ?>')" class="btn btn-sm btn-primary text-xs">Assign Codes</button>
                  <?php if($v['status']==='active'): ?>
                  <form method="POST" class="inline"><<?= csrfField() ?> <input type="hidden" name="action" value="suspend"><input type="hidden" name="vendor_id" value="<?= $v['id'] ?>"><button class="btn btn-sm btn-secondary text-xs text-yellow-400">Suspend</button></form>
                  <?php else: ?>
                  <form method="POST" class="inline"><?= csrfField() ?><input type="hidden" name="action" value="activate"><input type="hidden" name="vendor_id" value="<?= $v['id'] ?>"><button class="btn btn-sm btn-secondary text-xs text-green-400">Activate</button></form>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
            <?php endforeach; if(!$vendors): ?>
            <tr><td colspan="6" class="text-center text-gray-500 py-8">No vendors yet. <button onclick="Modal.open('create-vendor-modal')" class="text-orange-400 underline">Create one</button></td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Create Vendor Modal -->
<div class="modal-overlay" id="create-vendor-modal">
  <div class="modal-box">
    <h3 class="text-xl font-bold mb-5">Create Vendor Account</h3>
    <form method="POST">
      <?= csrfField() ?><input type="hidden" name="action" value="create">
      <div class="form-group"><label class="form-label">Full Name</label><input type="text" name="full_name" class="form-control" required></div>
      <div class="form-group"><label class="form-label">Business Name <span class="text-gray-600">(optional)</span></label><input type="text" name="business_name" class="form-control"></div>
      <div class="form-group"><label class="form-label">Phone</label><input type="tel" name="phone" class="form-control" data-phone required></div>
      <div class="form-group"><label class="form-label">Email</label><input type="email" name="email" class="form-control" required></div>
      <div class="form-group"><label class="form-label">Password</label><input type="password" name="password" class="form-control" required minlength="6"></div>
      <div class="flex gap-3">
        <button type="button" data-close-modal="create-vendor-modal" class="btn btn-secondary flex-1">Cancel</button>
        <button type="submit" class="btn btn-primary flex-1">Create Vendor</button>
      </div>
    </form>
  </div>
</div>

<!-- Assign Codes Modal -->
<div class="modal-overlay" id="assign-modal">
  <div class="modal-box">
    <h3 class="text-xl font-bold mb-2">Assign Codes</h3>
    <p class="text-gray-400 text-sm mb-5">Assigning to: <strong id="assign-vendor-name" class="text-white"></strong></p>
    <form method="POST">
      <?= csrfField() ?><input type="hidden" name="action" value="assign_codes"><input type="hidden" name="vendor_id" id="assign-vendor-id">
      <div class="form-group"><label class="form-label">Number of Codes to Assign</label><input type="number" name="code_count" class="form-control" min="1" max="10000" placeholder="e.g. 100" required></div>
      <div class="flex gap-3">
        <button type="button" data-close-modal="assign-modal" class="btn btn-secondary flex-1">Cancel</button>
        <button type="submit" class="btn btn-primary flex-1">Assign Codes</button>
      </div>
    </form>
  </div>
</div>

<script src="<?= APP_URL ?>/assets/js/app.js"></script>
<script>
function openAssign(id, name) {
  document.getElementById('assign-vendor-id').value = id;
  document.getElementById('assign-vendor-name').textContent = name;
  Modal.open('assign-modal');
}
</script>
</body></html>

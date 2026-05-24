<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
$auth = requireSuperAdmin(); $saId = $auth['id'];
$db = getDB(); $msg=$err='';

if (isPost() && verifyCsrf($_POST[CSRF_TOKEN_NAME]??'')) {
  $action = $_POST['action']??'';
  $aid    = (int)($_POST['admin_id']??0);

  if ($action === 'approve' && $aid) {
    $db->prepare("UPDATE admins SET status='active', approved_by=? WHERE id=?")->execute([$saId,$aid]);
    auditLog('super_admin',$saId,'approve_admin',"Admin $aid approved",'admin',$aid);
    $msg = "Admin approved.";
  } elseif ($action === 'suspend' && $aid) {
    $db->prepare("UPDATE admins SET status='suspended' WHERE id=?")->execute([$aid]);
    auditLog('super_admin',$saId,'suspend_admin',"Admin $aid suspended",'admin',$aid);
    $msg = "Admin suspended.";
  } elseif ($action === 'activate' && $aid) {
    $db->prepare("UPDATE admins SET status='active' WHERE id=?")->execute([$aid]);
    auditLog('super_admin',$saId,'activate_admin',"Admin $aid activated",'admin',$aid);
    $msg = "Admin activated.";
  } elseif ($action === 'delete' && $aid) {
    $db->prepare("DELETE FROM admins WHERE id=?")->execute([$aid]);
    auditLog('super_admin',$saId,'delete_admin',"Admin $aid deleted");
    $msg = "Admin deleted.";
  } elseif ($action === 'create') {
    $name     = trim($_POST['full_name']??'');
    $email    = trim($_POST['email']??'');
    $phone    = normalizePhone(trim($_POST['phone']??''));
    $password = $_POST['password']??'';
    if (!$name||!$email||!$phone||!$password) { $err='All fields required.'; }
    else {
      $check=$db->prepare("SELECT id FROM admins WHERE email=?");$check->execute([$email]);
      if ($check->fetch()) { $err='Email already registered.'; }
      else {
        $db->prepare("INSERT INTO admins (full_name,email,phone,password,status,approved_by) VALUES (?,?,?,?,?,?)")
           ->execute([$name,$email,$phone,password_hash($password,PASSWORD_BCRYPT),'active',$saId]);
        $newId=$db->lastInsertId();
        auditLog('super_admin',$saId,'create_admin',"Admin '$name' created",'admin',$newId);
        $msg = "Admin '$name' created.";
      }
    }
  }
}

$admins = $db->query("SELECT a.*, sa.full_name as approved_by_name FROM admins a LEFT JOIN super_admins sa ON a.approved_by=sa.id ORDER BY a.created_at DESC")->fetchAll();
$saPage = 'admins';
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="csrf-token" content="<?= generateCsrf() ?>">
  <title>Manage Admins — <?= APP_NAME ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
  <style>*{font-family:'Poppins',sans-serif!important}</style>
</head>
<body class="bg-[#060b12] text-white">
<?php include __DIR__ . '/../components/super-sidebar.php'; ?>
<div class="main-content">
  <div class="topbar" style="background:#0a0f1a">
    <button onclick="toggleSidebar()" class="md:hidden text-gray-400 text-2xl mr-3">☰</button>
    <h1 class="text-xl font-bold">Manage Admins</h1>
    <button onclick="Modal.open('create-admin-modal')" class="btn btn-primary btn-sm">+ Create Admin</button>
  </div>
  <div class="p-6">
    <?php if($msg): ?><div class="bg-green-500/10 border border-green-500/30 text-green-400 rounded-xl p-4 mb-5 text-sm"><?= e($msg) ?></div><?php endif; ?>
    <?php if($err): ?><div class="bg-red-500/10 border border-red-500/30 text-red-400 rounded-xl p-4 mb-5 text-sm"><?= e($err) ?></div><?php endif; ?>

    <div class="card">
      <div class="table-wrap">
        <table>
          <thead><tr><th>Admin</th><th>Phone</th><th>Status</th><th>Approved By</th><th>Created</th><th>Actions</th></tr></thead>
          <tbody>
            <?php foreach ($admins as $a): ?>
            <tr>
              <td>
                <div class="flex items-center gap-3">
                  <div class="w-8 h-8 bg-orange-500/15 rounded-lg flex items-center justify-center font-bold text-orange-400 text-sm"><?= strtoupper($a['full_name'][0]) ?></div>
                  <div>
                    <div class="font-medium text-sm"><?= e($a['full_name']) ?></div>
                    <div class="text-xs text-gray-500"><?= e($a['email']) ?></div>
                  </div>
                </div>
              </td>
              <td class="font-mono text-sm"><?= e(formatPhone($a['phone'])) ?></td>
              <td>
                <span class="badge <?= match($a['status']){'active'=>'badge-success','pending'=>'badge-warning','suspended'=>'badge-danger',default=>'badge-muted'} ?>">
                  <?= ucfirst($a['status']) ?>
                </span>
              </td>
              <td class="text-xs text-gray-400"><?= e($a['approved_by_name']??'—') ?></td>
              <td class="text-xs text-gray-500"><?= date('M j, Y',strtotime($a['created_at'])) ?></td>
              <td>
                <div class="flex items-center gap-2">
                  <?php if ($a['status']==='pending'): ?>
                    <form method="POST" class="inline"><?= csrfField() ?><input type="hidden" name="action" value="approve"><input type="hidden" name="admin_id" value="<?= $a['id'] ?>"><button class="btn btn-sm btn-success text-xs">✓ Approve</button></form>
                  <?php elseif ($a['status']==='active'): ?>
                    <form method="POST" class="inline"><?= csrfField() ?><input type="hidden" name="action" value="suspend"><input type="hidden" name="admin_id" value="<?= $a['id'] ?>"><button class="btn btn-sm btn-secondary text-xs text-yellow-400">Suspend</button></form>
                  <?php else: ?>
                    <form method="POST" class="inline"><?= csrfField() ?><input type="hidden" name="action" value="activate"><input type="hidden" name="admin_id" value="<?= $a['id'] ?>"><button class="btn btn-sm btn-secondary text-xs text-green-400">Activate</button></form>
                  <?php endif; ?>
                  <form method="POST" class="inline" onsubmit="return confirm('Delete this admin?')"><?= csrfField() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="admin_id" value="<?= $a['id'] ?>"><button class="btn btn-sm btn-danger text-xs">🗑</button></form>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if(!$admins): ?><tr><td colspan="6" class="text-center text-gray-500 py-8">No admins yet</td></tr><?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<!-- Create Admin Modal -->
<div class="modal-overlay" id="create-admin-modal">
  <div class="modal-box">
    <h3 class="text-xl font-bold mb-5">Create Admin Account</h3>
    <form method="POST">
      <?= csrfField() ?><input type="hidden" name="action" value="create">
      <div class="form-group"><label class="form-label">Full Name</label><input type="text" name="full_name" class="form-control" required></div>
      <div class="form-group"><label class="form-label">Email</label><input type="email" name="email" class="form-control" required></div>
      <div class="form-group"><label class="form-label">Phone</label><input type="tel" name="phone" class="form-control" data-phone required></div>
      <div class="form-group"><label class="form-label">Password</label><input type="password" name="password" class="form-control" required minlength="6"></div>
      <div class="flex gap-3">
        <button type="button" data-close-modal="create-admin-modal" class="btn btn-secondary flex-1">Cancel</button>
        <button type="submit" class="btn btn-primary flex-1">Create Admin</button>
      </div>
    </form>
  </div>
</div>
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
</body></html>

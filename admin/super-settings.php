<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
$auth = requireSuperAdmin(); $saId = $auth['id'];
$db = getDB(); $msg=$err='';

// Change super admin password
if (isPost() && verifyCsrf($_POST[CSRF_TOKEN_NAME]??'') && ($_POST['action']??'')==='change_password') {
  $cur=$_POST['current_password']??''; $new=$_POST['new_password']??''; $conf=$_POST['confirm']??'';
  $sa=$db->prepare("SELECT password FROM super_admins WHERE id=?");$sa->execute([$saId]);$sa=$sa->fetch();
  if (!password_verify($cur,$sa['password'])) { $err='Current password incorrect.'; }
  elseif (strlen($new)<8) { $err='New password must be at least 8 characters.'; }
  elseif ($new!==$conf) { $err='Passwords do not match.'; }
  else {
    $db->prepare("UPDATE super_admins SET password=? WHERE id=?")->execute([password_hash($new,PASSWORD_BCRYPT),$saId]);
    auditLog('super_admin',$saId,'change_password','Super admin changed password');
    $msg='Password updated.';
  }
}

$saPage='settings';
$sa=$db->prepare("SELECT * FROM super_admins WHERE id=?");$sa->execute([$saId]);$sa=$sa->fetch();
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="csrf-token" content="<?= generateCsrf() ?>">
  <title>Settings — <?= APP_NAME ?> Super Admin</title>
  <script src="<?= APP_URL ?>/assets/js/app.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
  <style>*{font-family:'Poppins',sans-serif!important}</style>
</head>
<body class="bg-[#060b12] text-white">
<?php include __DIR__ . '/../components/super-sidebar.php'; ?>
<div class="main-content">
  <div class="topbar" style="background:#0a0f1a">
    <button onclick="toggleSidebar()" class="md:hidden text-gray-400 text-2xl mr-3">☰</button>
    <h1 class="text-xl font-bold">Super Admin Settings</h1>
  </div>
  <div class="p-6 max-w-xl mx-auto">
    <?php if($msg): ?><div class="bg-green-500/10 border border-green-500/30 text-green-400 rounded-xl p-4 mb-5"><?= e($msg) ?></div><?php endif; ?>
    <?php if($err): ?><div class="bg-red-500/10 border border-red-500/30 text-red-400 rounded-xl p-4 mb-5"><?= e($err) ?></div><?php endif; ?>

    <!-- Profile Info -->
    <div class="card p-6 mb-6">
      <div class="flex items-center gap-4 mb-5">
        <div class="w-14 h-14 bg-red-500/20 rounded-2xl flex items-center justify-center text-2xl font-black text-red-400"><?= strtoupper($sa['full_name'][0]) ?></div>
        <div>
          <div class="font-bold text-lg"><?= e($sa['full_name']) ?></div>
          <div class="text-gray-400 text-sm"><?= e($sa['email']) ?></div>
          <div class="badge badge-danger mt-1">Super Administrator</div>
        </div>
      </div>
    </div>

    <!-- Change Password -->
    <div class="card p-6 mb-6">
      <h2 class="font-bold text-lg mb-5">Change Password</h2>
      <form method="POST">
        <?= csrfField() ?><input type="hidden" name="action" value="change_password">
        <div class="form-group"><label class="form-label">Current Password</label><input type="password" name="current_password" class="form-control" required></div>
        <div class="form-group"><label class="form-label">New Password <span class="text-gray-500 font-normal">(min 8 chars)</span></label><input type="password" name="new_password" class="form-control" required minlength="8"></div>
        <div class="form-group"><label class="form-label">Confirm New Password</label><input type="password" name="confirm" class="form-control" required></div>
        <button type="submit" class="btn btn-primary w-full">Update Password</button>
      </form>
    </div>

    <!-- Platform Info -->
    <div class="card p-6">
      <h2 class="font-bold mb-4">Platform Information</h2>
      <div class="space-y-3 text-sm">
        <div class="flex justify-between"><span class="text-gray-400">App Name</span><span><?= APP_NAME ?></span></div>
        <div class="flex justify-between"><span class="text-gray-400">Version</span><span><?= APP_VERSION ?></span></div>
        <div class="flex justify-between"><span class="text-gray-400">PHP Version</span><span><?= PHP_VERSION ?></span></div>
        <div class="flex justify-between"><span class="text-gray-400">Server Time</span><span><?= date('Y-m-d H:i:s') ?></span></div>
        <div class="flex justify-between"><span class="text-gray-400">Timezone</span><span>Africa/Lagos</span></div>
      </div>
    </div>
  </div>
</div>
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
</body></html>

<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
startAppSession();
if (!empty($_SESSION['admin_id'])) redirect(APP_URL.'/admin/dashboard.php');
$error = '';
if (isPost()) {
  if (!verifyCsrf($_POST[CSRF_TOKEN_NAME]??'')) { $error='Invalid request.'; }
  else {
    $email = trim($_POST['email']??''); $password = $_POST['password']??'';
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM admins WHERE email=? AND status='active'");
    $stmt->execute([$email]); $admin = $stmt->fetch();
    if ($admin && password_verify($password, $admin['password'])) {
      $_SESSION['admin_id']   = $admin['id'];
      $_SESSION['admin_name'] = $admin['full_name'];
      session_regenerate_id(true);
      auditLog('admin', $admin['id'], 'login', 'Admin logged in');
      redirect(APP_URL.'/admin/dashboard.php');
    } else { $error = 'Invalid credentials or account not active.'; }
  }
}



?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Admin Login — <?= APP_NAME ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
  <style>*{font-family:'Poppins',sans-serif!important}</style>
</head>
<body class="bg-[#0a0f1a] text-white min-h-screen flex items-center justify-center p-4">
  <div class="w-full max-w-md">
    <div class="text-center mb-8">
      <div class="w-16 h-16 bg-orange-500/20 border-2 border-orange-500/30 rounded-2xl flex items-center justify-center text-3xl mx-auto mb-4">🛡️</div>
      <div class="text-2xl font-bold">Zoe<span class="text-orange-500">Feeds</span></div>
      <div class="text-sm text-gray-400 mt-1">Admin Control Panel</div>
    </div>
    <div class="card p-8">
      <h1 class="text-xl font-bold mb-6">Administrator Login</h1>
      <?php if($error): ?><div class="bg-red-500/10 border border-red-500/30 text-red-400 rounded-xl p-4 mb-5 text-sm"><?= e($error) ?></div><?php endif; ?>
      <form method="POST">
        <?= csrfField() ?>
        <div class="form-group">
          <label class="form-label">Admin Email</label>
          <input type="email" name="email" class="form-control" value="<?= e($_POST['email']??'') ?>" autofocus required>
        </div>
        <div class="form-group">
          <label class="form-label">Password</label>
          <div class="relative">
            <input type="password" name="password" id="pw" class="form-control pr-12" required>
            <button type="button" class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400" onclick="document.getElementById('pw').type==='password'?(document.getElementById('pw').type='text'):(document.getElementById('pw').type='password')">👁</button>
          </div>
        </div>
        <button type="submit" class="btn btn-primary w-full py-3 mt-2">Sign In to Admin Panel</button>
      </form>
    </div>
    <div class="text-center mt-4 text-sm text-gray-500 space-x-4">
      <a href="<?= APP_URL ?>/user/login.php" class="hover:text-orange-400">User Login</a>
      <span>|</span>
      <a href="<?= APP_URL ?>/vendor/login.php" class="hover:text-orange-400">Vendor Login</a>
      <span>|</span>
      <a href="<?= APP_URL ?>/admin/super-login.php" class="hover:text-orange-400">Super Admin</a>
    </div>
  </div>
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
</body></html>

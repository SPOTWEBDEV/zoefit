<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
startAppSession();
if (!empty($_SESSION['vendor_id'])) redirect(APP_URL.'/vendor/dashboard.php');
$error='';
if (isPost()) {
  if (!verifyCsrf($_POST[CSRF_TOKEN_NAME]??'')) { $error='Invalid request.'; }
  else {
    $email=trim($_POST['email']??'');$password=$_POST['password']??'';
    $db=getDB();
    $stmt=$db->prepare("SELECT * FROM vendors WHERE email=?");$stmt->execute([$email]);$v=$stmt->fetch();
    if ($v && password_verify($password,$v['password'])) {
      if ($v['status']!=='active') { $error='Your vendor account is not active. Contact the administrator.'; }
      else {
        $_SESSION['vendor_id']=$v['id'];$_SESSION['vendor_name']=$v['full_name'];
        session_regenerate_id(true);
        auditLog('vendor',$v['id'],'login','Vendor logged in');
        redirect(APP_URL.'/vendor/dashboard.php');
      }
    } else { $error='Invalid email or password.'; }
  }
}
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Vendor Login — <?= APP_NAME ?></title>
  <script src="<?= APP_URL ?>/assets/js/app.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
  <style>*{font-family:'Poppins',sans-serif!important}</style>
</head>
<body class="bg-[#0a0f1a] text-white min-h-screen flex items-center justify-center p-4">
  <div class="w-full max-w-md">
    <div class="text-center mb-8">
      <div class="w-14 h-14 bg-blue-500/20 border border-blue-500/30 rounded-2xl flex items-center justify-center text-3xl mx-auto mb-4">🏪</div>
      <div class="text-2xl font-bold">Zoe<span class="text-orange-500">Feeds</span></div>
      <div class="text-sm text-gray-400">Vendor Portal</div>
    </div>
    <div class="card p-8">
      <h1 class="text-xl font-bold mb-6">Vendor Sign In</h1>
      <?php if($error): ?><div class="bg-red-500/10 border border-red-500/30 text-red-400 rounded-xl p-4 mb-5 text-sm"><?= e($error) ?></div><?php endif; ?>
      <form method="POST">
        <?= csrfField() ?>
        <div class="form-group">
          <label class="form-label">Email Address</label>
          <input type="email" name="email" class="form-control" value="<?= e($_POST['email']??'') ?>" autofocus required>
        </div>
        <div class="form-group">
          <label class="form-label">Password</label>
          <input type="password" name="password" class="form-control" required>
        </div>
        <button type="submit" class="btn btn-primary w-full py-3">Sign In as Vendor</button>
      </form>
    </div>
    <div class="text-center mt-4 text-sm text-gray-500">
      <a href="<?= APP_URL ?>/user/login.php" class="hover:text-orange-400">← User Login</a> &nbsp;|&nbsp;
      <a href="<?= APP_URL ?>/admin/login.php" class="hover:text-orange-400">Admin Login →</a>
    </div>
  </div>
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
</body></html>

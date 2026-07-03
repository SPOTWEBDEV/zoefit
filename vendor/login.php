
<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
startAppSession();
if (!empty($_SESSION['vendor_id'])) redirect(APP_URL.'/vendor/dashboard.php');

$error = '';
if (isPost()) {
  if (!verifyCsrf($_POST[CSRF_TOKEN_NAME]??'')) {
    $error = 'Invalid request.';
  } else {
    $email    = strtolower(trim($_POST['email']??''));
    $password = $_POST['password']??'';
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM vendors WHERE email=?");
    $stmt->execute([$email]); $v = $stmt->fetch();
    if ($v && password_verify($password, $v['password'])) {
      if ($v['status'] === 'pending') {
        $error = 'Your vendor account is awaiting admin approval. You will be notified by email once activated.';
      } elseif ($v['status'] === 'suspended') {
        $error = 'Your vendor account has been suspended. Contact support.';
      } else {
        $_SESSION['vendor_id']   = $v['id'];
        session_regenerate_id(true);
        auditLog('vendor', $v['id'], 'login', 'Vendor logged in');
        redirect(APP_URL.'/vendor/dashboard.php');
      }
    } else {
      $error = 'Invalid email or password.';
    }
  }
}
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Vendor Login — <?= APP_NAME ?></title>
  <script src="<?= APP_URL ?>/assets/js/tailwind.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
  <style>*{font-family:'Poppins',sans-serif!important}</style>
</head>
<body class="bg-[#0a0f1a] text-white min-h-screen flex items-center justify-center p-4">
<div class="w-full max-w-md">

  <div class="text-center mb-8">
    <a href="<?= APP_URL ?>" class="inline-flex items-center gap-2 mb-3">
      <div class="w-10 h-10 bg-orange-500 rounded-xl flex items-center justify-center font-black text-white text-xl">Z</div>
      <span class="font-bold text-2xl">Zoe<span class="text-orange-500">Feeds</span></span>
    </a>
    <div class="inline-flex items-center gap-2 bg-purple-500/10 border border-purple-500/20 rounded-full px-4 py-1.5 text-sm font-semibold text-purple-400">
      🏪 Vendor Portal
    </div>
  </div>

  <div class="card p-8" style="border-color:rgba(139,92,246,.25);background:linear-gradient(135deg,rgba(139,92,246,.08),var(--bg-card))">
    <h1 class="text-2xl font-bold mb-1">Vendor Sign In</h1>
    <p class="text-gray-400 text-sm mb-6">Sign in to your vendor dashboard</p>

    <?php if ($error): ?>
    <div class="bg-red-500/10 border border-red-500/30 text-red-400 rounded-xl p-4 mb-5 text-sm"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="POST">
      <?= csrfField() ?>
      <div class="form-group">
        <label class="form-label">Email Address</label>
        <input type="email" name="email" class="form-control" placeholder="business@example.com"
               value="<?= e($_POST['email']??'') ?>" autofocus required>
      </div>
      <div class="form-group">
        <label class="form-label">Password</label>
        <div class="relative">
          <input type="password" name="password" id="pw" class="form-control pr-10" required>
          <button type="button" onclick="document.getElementById('pw').type==='password'?(document.getElementById('pw').type='text'):(document.getElementById('pw').type='password')" class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-white">👁</button>
        </div>
      </div>
      <button type="submit" class="btn w-full py-3 font-bold" style="background:linear-gradient(135deg,#8b5cf6,#7c3aed);color:white">
        Sign In as Vendor →
      </button>
    </form>

    <div class="text-center mt-5 space-y-2 text-sm text-gray-500">
      <div>New vendor? <a href="<?= APP_URL ?>/vendor/register.php" class="text-purple-400 hover:underline font-semibold">Apply to register →</a></div>
      <div>
         Don't remember your password  <a href="<?= APP_URL ?>/vendor/forgot-password.php" class="text-purple-400 hover:underline font-semibold">Click Here →</a>
      </div>
    </div>
  </div>

  <div class="flex items-center justify-center gap-5 mt-5 text-xs text-gray-600">
    <a href="<?= APP_URL ?>/user/login.php" class="hover:text-orange-400">👤 Customer Login</a>
    <span>·</span>
    <a href="<?= APP_URL ?>" class="hover:text-orange-400">← Home</a>
  </div>
</div>
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
</body></html>

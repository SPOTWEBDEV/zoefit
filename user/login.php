<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
startAppSession();
if (!empty($_SESSION['user_id'])) redirect(APP_URL . '/user/dashboard.php');

$error = '';

if (isPost()) {
    if (!verifyCsrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $error = 'Invalid request.';
    } else {
        $phone    = trim($_POST['phone'] ?? '');
        $password = $_POST['password'] ?? '';
        $normalizedPhone = normalizePhone($phone);

        // Rate limiting via session
        $key = 'login_attempts_' . $normalizedPhone;
        if (isset($_SESSION[$key]) && $_SESSION[$key]['count'] >= LOGIN_MAX_ATTEMPTS) {
            $locked = $_SESSION[$key]['time'];
            if (time() - $locked < LOGIN_LOCKOUT_MINUTES * 60) {
                $remaining = ceil((LOGIN_LOCKOUT_MINUTES * 60 - (time() - $locked)) / 60);
                $error = "Too many attempts. Try again in {$remaining} minute(s).";
            } else {
                unset($_SESSION[$key]);
            }
        }

        if (!$error) {
            $db = getDB();
            $stmt = $db->prepare("SELECT id, full_name, password, status FROM users WHERE phone = ?");
            $stmt->execute([$normalizedPhone]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                if ($user['status'] === 'suspended') {
                    $error = 'Your account has been suspended. Contact support.';
                } else {
                    unset($_SESSION[$key]);
                    $_SESSION['user_id']   = $user['id'];
                    $_SESSION['user_name'] = $user['full_name'];
                    session_regenerate_id(true);
                    auditLog('user', $user['id'], 'login', 'User logged in');
                    redirect(APP_URL . '/user/dashboard.php');
                }
            } else {
                $_SESSION[$key]['count'] = ($_SESSION[$key]['count'] ?? 0) + 1;
                $_SESSION[$key]['time']  = time();
                $error = 'Invalid phone number or password.';
            }
        }
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Login — <?= APP_NAME ?></title>
  <script src="<?= APP_URL ?>/assets/js/tailwind.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
</head>
<body class="bg-[#0a0f1a] text-white font-sans min-h-screen flex items-center justify-center p-4">
  <div class="w-full max-w-md">
    <div class="text-center mb-8">
      <a href="<?= APP_URL ?>" class="inline-block">
        <div class="text-3xl font-display font-bold text-orange-500">ZOE<span class="text-white">FEEDS</span></div>
        <div class="text-sm text-gray-400 mt-1">Loyalty Reward Platform</div>
      </a>
    </div>
    <div class="card p-8">
      <h1 class="text-2xl font-bold mb-2">Welcome Back</h1>
      <p class="text-gray-400 text-sm mb-6">Sign in to your ZoeFeeds account</p>

      <?php if ($error): ?>
        <div class="bg-red-500/10 border border-red-500/30 text-red-400 rounded-xl p-4 mb-5 text-sm"><?= e($error) ?></div>
      <?php endif; ?>

      <form method="POST">
        <?= csrfField() ?>
        <div class="form-group">
          <label class="form-label">Phone Number</label>
          <input type="tel" name="phone" class="form-control" placeholder="08012345678" data-phone value="<?= e($_POST['phone'] ?? '') ?>" autofocus required>
        </div>
        <div class="form-group">
          <label class="form-label">Password</label>
          <div class="relative">
            <input type="password" name="password" id="pw" class="form-control pr-12" placeholder="Your password" required>
            <button type="button" class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-white text-lg" onclick="togglePw()">👁</button>
          </div>
        </div>
        <button type="submit" class="btn btn-primary w-full py-3 text-base mt-2">Sign In</button>
      </form>
      <p class="text-center text-sm text-gray-400 mt-6">New to ZoeFeeds? <a href="<?= APP_URL ?>/user/register.php" class="text-orange-400 hover:underline font-semibold">Create account</a></p>
      <div class="text-center mt-5 space-y-2 text-sm text-gray-500">
      <div>New User? <a href="<?= APP_URL ?>/user/register.php" class="text-purple-400 hover:underline font-semibold">Apply to register →</a></div>
      <div>
         Do't remember your password  <a href="<?= APP_URL ?>/user/forgot-password.php" class="text-purple-400 hover:underline font-semibold">Click Here →</a>
      </div>
    </div>
    </div>
  </div>
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
<script>
function togglePw() {
  const el = document.getElementById('pw');
  el.type = el.type === 'password' ? 'text' : 'password';
}
</script>
</body></html>

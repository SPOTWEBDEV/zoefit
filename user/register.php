<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
startAppSession();
if (!empty($_SESSION['user_id'])) redirect(APP_URL . '/user/dashboard.php');

$error = '';
$success = '';

if (isPost()) {
    if (!verifyCsrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $error = 'Invalid request. Please refresh and try again.';
    } else {
        $fullName = trim($_POST['full_name'] ?? '');
        $phone    = trim($_POST['phone'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['confirm_password'] ?? '';

        if (!$fullName || !$phone || !$password) {
            $error = 'Full name, phone, and password are required.';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters.';
        } elseif ($password !== $confirm) {
            $error = 'Passwords do not match.';
        } else {
            $normalizedPhone = normalizePhone($phone);
            if (strlen($normalizedPhone) < 12) {
                $error = 'Please enter a valid phone number.';
            } else {
                $db = getDB();
                $stmt = $db->prepare("SELECT id FROM users WHERE phone = ?");
                $stmt->execute([$normalizedPhone]);
                if ($stmt->fetch()) {
                    $error = 'This phone number is already registered.';
                } else {
                    $hashed = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                    $db->prepare("INSERT INTO users (phone, full_name, email, password) VALUES (?,?,?,?)")
                       ->execute([$normalizedPhone, $fullName, $email ?: null, $hashed]);
                    $userId = $db->lastInsertId();
                    auditLog('user', $userId, 'register', 'New user registered');
                    $success = 'Account created! You can now log in.';
                }
            }
        }
    }
}

$pageTitle = 'Create Account';
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= e($pageTitle) ?> — <?= APP_NAME ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Space+Grotesk:wght@600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
</head>
<body class="bg-[#0a0f1a] text-white font-sans min-h-screen flex items-center justify-center p-4">
  <div class="w-full max-w-md">
    <!-- Logo -->
    <div class="text-center mb-8">
      <a href="<?= APP_URL ?>" class="inline-block">
        <div class="text-3xl font-display font-bold text-orange-500">ZOE<span class="text-white">FEEDS</span></div>
        <div class="text-sm text-gray-400 mt-1">Loyalty Reward Platform</div>
      </a>
    </div>

    <div class="card p-8">
      <h1 class="text-2xl font-bold mb-2">Create Account</h1>
      <p class="text-gray-400 text-sm mb-6">Join ZoeFeeds and start winning rewards</p>

      <?php if ($error): ?>
        <div class="bg-red-500/10 border border-red-500/30 text-red-400 rounded-xl p-4 mb-5 text-sm"><?= e($error) ?></div>
      <?php endif; ?>
      <?php if ($success): ?>
        <div class="bg-green-500/10 border border-green-500/30 text-green-400 rounded-xl p-4 mb-5 text-sm">
          <?= e($success) ?> <a href="<?= APP_URL ?>/user/login.php" class="underline font-semibold">Login now →</a>
        </div>
      <?php endif; ?>

      <form method="POST" autocomplete="off">
        <?= csrfField() ?>
        <div class="form-group">
          <label class="form-label">Full Name</label>
          <input type="text" name="full_name" class="form-control" placeholder="John Doe" value="<?= e($_POST['full_name'] ?? '') ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label">Phone Number</label>
          <input type="tel" name="phone" class="form-control" placeholder="08012345678" data-phone value="<?= e($_POST['phone'] ?? '') ?>" required>
          <div class="text-xs text-gray-500 mt-1">Accepts 080xxxxxxxx, +234xxxxxxxxxx, etc.</div>
        </div>
        <div class="form-group">
          <label class="form-label">Email <span class="text-gray-600">(optional)</span></label>
          <input type="email" name="email" class="form-control" placeholder="john@example.com" value="<?= e($_POST['email'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Password</label>
          <div class="relative">
            <input type="password" name="password" id="pw" class="form-control pr-12" placeholder="Min. 6 characters" required>
            <button type="button" class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-white" onclick="togglePw('pw',this)">👁</button>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Confirm Password</label>
          <input type="password" name="confirm_password" class="form-control" placeholder="Repeat password" required>
        </div>
        <button type="submit" class="btn btn-primary w-full py-3 text-base mt-2">Create Account</button>
      </form>
      <p class="text-center text-sm text-gray-400 mt-6">Already have an account? <a href="<?= APP_URL ?>/user/login.php" class="text-orange-400 hover:underline font-semibold">Log in</a></p>
    </div>
  </div>
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
<script>
function togglePw(id, btn) {
  const el = document.getElementById(id);
  el.type = el.type === 'password' ? 'text' : 'password';
  btn.textContent = el.type === 'password' ? '👁' : '🙈';
}
</script>
</body></html>

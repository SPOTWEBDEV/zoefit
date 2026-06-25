<?php
// user/register.php — Customer registration only.
// Blocks any phone or email already used in the vendors table.
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
startAppSession();
if (!empty($_SESSION['user_id'])) redirect(APP_URL.'/user/dashboard.php');

$redirect = trim($_GET['redirect'] ?? $_POST['redirect'] ?? '');
$redirect = (str_starts_with($redirect, APP_URL) || str_starts_with($redirect, '/')) ? $redirect : '';

$error = $success = '';

if (isPost() && verifyCsrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
    $fullName = trim($_POST['full_name']  ?? '');
    $phone    = trim($_POST['phone']      ?? '');
    $email    = strtolower(trim($_POST['email'] ?? ''));
    $password = $_POST['password']        ?? '';
    $confirm  = $_POST['confirm_password']?? '';
    $age18    = !empty($_POST['age18']);

    if (!$fullName || !$phone || !$password) {
        $error = 'Full name, phone and password are required.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } elseif (!$age18) {
        $error = 'You must be 18 years or older to participate in ZoeFeeds draws.';
    } else {
        $normalizedPhone = normalizePhone($phone);
        if (strlen($normalizedPhone) < 12) {
            $error = 'Please enter a valid Nigerian phone number.';
        } else {
            $db = getDB();

            // ── CRITICAL: block reuse of vendor credentials ───────
            $s = $db->prepare("SELECT id FROM vendors WHERE phone=?");
            $s->execute([$normalizedPhone]);
            if ($s->fetch()) {
                $error = 'This phone number is already registered as a vendor account. Customer and vendor accounts must use different credentials. Please use a different phone number, or <a href="'.APP_URL.'/vendor/login.php" class="underline">log in as a vendor →</a>';
            } elseif ($email) {
                $s = $db->prepare("SELECT id FROM vendors WHERE email=?");
                $s->execute([$email]);
                if ($s->fetch()) {
                    $error = 'This email address is already linked to a vendor account. Please use a different email for your customer account.';
                }
            }

            if (!$error) {
                // Block duplicate within users table
                $s = $db->prepare("SELECT id FROM users WHERE phone=?");
                $s->execute([$normalizedPhone]);
                if ($s->fetch()) {
                    $error = 'This phone number is already registered. <a href="'.APP_URL.'/user/login.php" class="underline">Log in instead →</a>';
                }
            }

            if (!$error) {
                $hashed = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                $db->prepare("INSERT INTO users (phone, full_name, email, password) VALUES (?,?,?,?)")
                   ->execute([$normalizedPhone, $fullName, $email ?: null, $hashed]);
                $userId = (int)$db->lastInsertId();
                auditLog('user', $userId, 'register', 'New customer registered');
                createNotification($userId, '👋 Welcome to ZoeFeeds!',
                    'Your account has been created. Redeem your first raffle code to get started!', 'info');

                // Auto-login then redirect
                $_SESSION['user_id']       = $userId;
                $_SESSION['user_name']     = $fullName;
                $_SESSION['is_vendor']     = false;
                $_SESSION['vendor_status'] = null;
                session_regenerate_id(true);

                $dest = $redirect ?: APP_URL.'/user/dashboard.php';
                redirect($dest);
            }
        }
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="csrf-token" content="<?= generateCsrf() ?>">
  <title>Create Account — <?= APP_NAME ?></title>
  <script src="<?= APP_URL ?>/assets/js/tailwind.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
  <style>*{font-family:'Poppins',sans-serif!important}</style>
</head>
<body class="bg-[#0a0f1a] text-white min-h-screen flex items-center justify-center p-4">
<div class="w-full max-w-md py-8">

  <!-- Logo -->
  <div class="text-center mb-8">
    <a href="<?= APP_URL ?>" class="inline-flex items-center gap-2 mb-3">
      <div class="w-10 h-10 bg-orange-500 rounded-xl flex items-center justify-center font-black text-white text-xl">Z</div>
      <span class="font-bold text-2xl">Zoe<span class="text-orange-500">Feeds</span></span>
    </a>
    <div class="text-sm text-gray-400">Loyalty Reward Platform</div>
  </div>

  <div class="card p-8">
    <h1 class="text-2xl font-bold mb-1">Create Customer Account</h1>
    <p class="text-gray-400 text-sm mb-6">Join ZoeFeeds and start entering draws to win prizes</p>

    <?php if ($redirect): ?>
    <div class="bg-orange-500/10 border border-orange-500/20 rounded-xl p-3 mb-5 flex items-center gap-2 text-sm">
      <span class="text-orange-400">🎯</span>
      <span class="text-gray-300">Create an account to continue to your selected draw.</span>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="bg-red-500/10 border border-red-500/30 text-red-400 rounded-xl p-4 mb-5 text-sm"><?= $error ?></div>
    <?php endif; ?>

    <form method="POST">
      <?= csrfField() ?>
      <?php if ($redirect): ?><input type="hidden" name="redirect" value="<?= e($redirect) ?>"><?php endif; ?>

      <div class="form-group">
        <label class="form-label">Full Name <span class="text-red-400">*</span></label>
        <input type="text" name="full_name" class="form-control" placeholder="Your full name"
               value="<?= e($_POST['full_name'] ?? '') ?>" required autofocus>
      </div>

      <div class="form-group">
        <label class="form-label">Phone Number <span class="text-red-400">*</span></label>
        <input type="tel" name="phone" data-phone class="form-control" placeholder="08012345678"
               value="<?= e($_POST['phone'] ?? '') ?>" required>
        <div class="text-xs text-gray-500 mt-1">Used for login and winner notifications</div>
      </div>

      <div class="form-group">
        <label class="form-label">Email Address <span class="text-gray-500 font-normal">(optional)</span></label>
        <input type="email" name="email" class="form-control" placeholder="you@example.com"
               value="<?= e($_POST['email'] ?? '') ?>">
      </div>

      <div class="form-group">
        <label class="form-label">Password <span class="text-red-400">*</span></label>
        <div class="relative">
          <input type="password" name="password" id="pw" class="form-control pr-10"
                 placeholder="Min. 6 characters" required minlength="6">
          <button type="button" onclick="togglePw('pw')"
                  class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-white">👁</button>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Confirm Password <span class="text-red-400">*</span></label>
        <input type="password" name="confirm_password" class="form-control"
               placeholder="Repeat password" required>
      </div>

      <!-- Age confirmation (T&C §4) -->
      <label class="flex items-start gap-3 mb-5 cursor-pointer">
        <input type="checkbox" name="age18" class="mt-0.5 w-4 h-4 accent-orange-500 flex-shrink-0"
               <?= !empty($_POST['age18'])?'checked':'' ?> required>
        <span class="text-sm text-gray-300">
          I confirm I am <strong class="text-white">18 years or older</strong> and I agree to the
          <a href="<?= APP_URL ?>/user/terms.php" target="_blank" class="text-orange-400 hover:underline">Terms &amp; Conditions</a>.
        </span>
      </label>

      <button type="submit" class="btn btn-primary w-full py-3 text-base font-bold">
        🎟️ Create Free Account
      </button>
    </form>

    <p class="text-center text-sm text-gray-400 mt-6">
      Already have an account?
      <a href="<?= APP_URL ?>/user/login.php<?= $redirect?'?redirect='.urlencode($redirect):'' ?>"
         class="text-orange-400 hover:underline font-semibold">Log in →</a>
    </p>
  </div>

  <!-- Vendor separator -->
  <div class="mt-5 p-4 rounded-xl border border-white/5 text-center text-sm text-gray-500">
    🏪 Looking to distribute codes as a merchant?
    <a href="<?= APP_URL ?>/vendor/register" class="text-purple-400 hover:underline font-semibold">Become a Vendor →</a>
  </div>

</div>
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
<script>function togglePw(id){const e=document.getElementById(id);e.type=e.type==='password'?'text':'password';}</script>
</body></html>

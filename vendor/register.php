<?php
// vendor/register.php — Standalone vendor registration.
// API keys (public + secret) are generated automatically on successful registration.
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
startAppSession();
if (!empty($_SESSION['vendor_id'])) redirect(APP_URL.'/vendor/dashboard.php');

$redirect = trim($_GET['redirect'] ?? $_POST['redirect'] ?? '');
$redirect = (str_starts_with($redirect, APP_URL) || str_starts_with($redirect, '/')) ? $redirect : '';

$error = '';

if (isPost() && verifyCsrf($_POST[CSRF_TOKEN_NAME] ?? '')) {
    $businessName = trim($_POST['business_name']   ?? '');
    $fullName     = trim($_POST['full_name']        ?? '');
    $phone        = trim($_POST['phone']            ?? '');
    $email        = strtolower(trim($_POST['email'] ?? ''));
    $password     = $_POST['password']              ?? '';
    $confirm      = $_POST['confirm_password']      ?? '';
    $reason       = trim($_POST['reason']           ?? '');
    $age18        = !empty($_POST['age18']);

    if (!$businessName || !$fullName || !$phone || !$password) {
        $error = 'Business name, full name, phone and password are required.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } elseif (!$age18) {
        $error = 'You must be 18 years or older to become a ZoeFeeds vendor.';
    } else {
        $normalizedPhone = normalizePhone($phone);
        if (strlen($normalizedPhone) < 12) {
            $error = 'Please enter a valid Nigerian phone number.';
        } else {
            $db = getDB();

            // Block phone already in users (customer) table
            $s = $db->prepare("SELECT id FROM users WHERE phone=?");
            $s->execute([$normalizedPhone]);
            if ($s->fetch()) {
                $error = 'This phone number is already registered as a customer account. Vendor accounts must use different credentials. Please use a different phone number, or <a href="'.APP_URL.'/user/login.php" class="underline">log in as a customer →</a>';
            }

            // Block duplicate within vendors table
            if (!$error) {
                $s = $db->prepare("SELECT id FROM vendors WHERE phone=?");
                $s->execute([$normalizedPhone]);
                if ($s->fetch()) {
                    $error = 'This phone number is already registered as a vendor. <a href="'.APP_URL.'/vendor/login.php" class="underline">Log in instead →</a>';
                }
            }

            // Block email collision with customer accounts
            if (!$error && $email) {
                $s = $db->prepare("SELECT id FROM users WHERE email=?");
                $s->execute([$email]);
                if ($s->fetch()) {
                    $error = 'This email is already linked to a customer account. Please use a different email.';
                }
            }

            // Block email collision within vendors
            if (!$error && $email) {
                $s = $db->prepare("SELECT id FROM vendors WHERE email=?");
                $s->execute([$email]);
                if ($s->fetch()) {
                    $error = 'This email is already registered as a vendor. <a href="'.APP_URL.'/vendor/login.php" class="underline">Log in instead →</a>';
                }
            }

            if (!$error) {
                // ── Auto-generate API key pair on registration ──────────────
                $pubKey    = 'zf_pub_' . bin2hex(random_bytes(16));
                $rawSecret = 'zf_sec_' . bin2hex(random_bytes(32));
                $hashedSecret = password_hash($rawSecret, PASSWORD_BCRYPT);

                $hashed = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

                $db->prepare(
                    "INSERT INTO vendors
                       (phone, full_name, email, password, business_name, reason,
                        status, public_key, secret_key)
                     VALUES (?,?,?,?,?,?, 'pending', ?,?)"
                )->execute([
                    $normalizedPhone, $fullName, $email ?: null,
                    $hashed, $businessName, $reason,
                    $pubKey, $hashedSecret,
                ]);

                $vendorId = (int)$db->lastInsertId();
                auditLog('vendor', $vendorId, 'register', 'New vendor application: ' . $businessName);

                // Also insert into vendor_applications for admin review queue
                $db->prepare(
                    "INSERT INTO vendor_applications (vendor_id, business_name, reason, status)
                     VALUES (?,?,?,'pending')"
                )->execute([$vendorId, $businessName, $reason]);

                // Store the raw secret in session so we can show it once on the dashboard
                $_SESSION['vendor_id']          = $vendorId;
                $_SESSION['vendor_name']        = $fullName;
                $_SESSION['vendor_status']      = 'pending';
                $_SESSION['vendor_new_secret']  = $rawSecret;   // shown once then cleared
                session_regenerate_id(true);

                $dest = $redirect ?: APP_URL . '/vendor/dashboard.php';
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
  <title>Vendor Application — <?= APP_NAME ?></title>
  <script src="<?= APP_URL ?>/assets/js/tailwind.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
  <style>
    * { font-family: 'Poppins', sans-serif !important; }
    :root { --vp: #7c3aed; --va: #a78bfa; --vb: rgba(124,58,237,.22); }
    body  { background: #0a0f1a; }

    .v-card  { background: rgba(124,58,237,.04); border: 1px solid var(--vb); border-radius: 20px; }
    .v-input {
      width: 100%;
      background: rgba(255,255,255,.04) !important;
      border: 1px solid rgba(255,255,255,.10) !important;
      border-radius: 12px !important;
      color: #fff !important;
      padding: .7rem 1rem;
      font-size: .875rem;
      transition: border-color .2s, box-shadow .2s;
    }
    .v-input:focus {
      border-color: var(--va) !important;
      outline: none !important;
      box-shadow: 0 0 0 3px rgba(124,58,237,.15) !important;
    }
    .v-input::placeholder { color: #6b7280 !important; }
    textarea.v-input { resize: vertical; }

    .v-label { display: block; font-size: .8rem; font-weight: 600; color: #d1d5db; margin-bottom: .4rem; }
    .v-hint  { font-size: .7rem; color: #6b7280; margin-top: .3rem; }
    .v-group { margin-bottom: 1.1rem; }

    .btn-v {
      display: inline-flex; align-items: center; justify-content: center; gap: .5rem;
      background: linear-gradient(135deg, #7c3aed, #6d28d9);
      color: #fff; border: none; border-radius: 14px;
      font-weight: 700; font-size: .95rem; cursor: pointer;
      transition: all .2s; padding: .85rem 1.5rem;
    }
    .btn-v:hover { background: linear-gradient(135deg,#6d28d9,#5b21b6); transform: translateY(-1px); box-shadow: 0 8px 24px rgba(124,58,237,.4); }

    .step-badge {
      width: 26px; height: 26px; border-radius: 50%;
      background: rgba(124,58,237,.2); border: 1.5px solid var(--vb);
      display: flex; align-items: center; justify-content: center;
      font-size: .68rem; font-weight: 800; color: var(--va); flex-shrink: 0;
    }
    .divider { border: none; border-top: 1px solid rgba(124,58,237,.15); margin: 1.4rem 0; }

    .info-box    { background: rgba(124,58,237,.06); border: 1px solid var(--vb); border-radius: 12px; padding: .85rem 1rem; }
    .warning-box { background: rgba(239,68,68,.06);  border: 1px solid rgba(239,68,68,.2); border-radius: 12px; padding: .85rem 1rem; }

    /* API key preview badge */
    .key-preview {
      background: rgba(0,0,0,.35); border: 1px solid rgba(124,58,237,.2);
      border-radius: 10px; padding: .6rem .9rem;
      font-size: .72rem; font-family: 'Courier New', monospace;
      color: #a78bfa; word-break: break-all;
    }
  </style>
</head>
<body class="text-white min-h-screen flex items-center justify-center p-4">
<div class="w-full max-w-lg py-8">

  <!-- Logo -->
  <div class="text-center mb-7">
    <a href="<?= APP_URL ?>/vendor" class="inline-flex items-center gap-2 mb-2">
      <div class="w-10 h-10 bg-purple-600 rounded-xl flex items-center justify-center font-black text-white text-xl">Z</div>
      <span class="font-bold text-2xl">Zoe<span class="text-purple-400">Feeds</span></span>
      <span class="text-xs text-purple-300 bg-purple-500/20 border border-purple-500/30 rounded-full px-2 py-0.5 font-semibold">Vendors</span>
    </a>
    <div class="text-sm text-gray-400">Vendor Application Portal</div>
  </div>

  <!-- Progress steps -->
  <div class="flex items-center justify-center gap-2 mb-6 text-xs text-gray-500">
    <div class="flex items-center gap-1.5"><div class="step-badge" style="background:rgba(124,58,237,.4);border-color:var(--va)">1</div><span class="text-purple-400 font-semibold">Apply</span></div>
    <div class="w-8 h-px bg-white/10"></div>
    <div class="flex items-center gap-1.5"><div class="step-badge" style="opacity:.4">2</div><span style="opacity:.4">Review</span></div>
    <div class="w-8 h-px bg-white/10"></div>
    <div class="flex items-center gap-1.5"><div class="step-badge" style="opacity:.4">3</div><span style="opacity:.4">Approved</span></div>
  </div>

  <div class="v-card p-8">
    <h1 class="text-2xl font-bold mb-1">Vendor Application</h1>
    <p class="text-gray-400 text-sm mb-5">Join the ZoeFeeds vendor network and distribute raffle codes to your customers.</p>


    <div class="info-box mb-5 flex items-start gap-2.5 text-sm">
      <span class="text-purple-400 flex-shrink-0 mt-0.5">ℹ️</span>
      <span class="text-gray-300">Use a <strong class="text-white">different phone &amp; email</strong> from any customer account. Applications are reviewed within 24 hours.</span>
    </div>

    <?php if ($error): ?>
    <div class="warning-box text-red-400 mb-5 text-sm"><?= $error ?></div>
    <?php endif; ?>

    <form method="POST">
      <?= csrfField() ?>
      <?php if ($redirect): ?><input type="hidden" name="redirect" value="<?= e($redirect) ?>"><?php endif; ?>

      <!-- Section 1 -->
      <div class="flex items-center gap-2 mb-4">
        <div class="step-badge">1</div>
        <span class="font-semibold text-sm text-purple-300">Business Information</span>
      </div>

      <div class="v-group">
        <label class="v-label">Business / Brand Name <span class="text-red-400">*</span></label>
        <input type="text" name="business_name" class="v-input" placeholder="e.g. Daniel Services, SPOTWEB COM"
               value="<?= e($_POST['business_name'] ?? '') ?>" required autofocus>
        <div class="v-hint">The name customers will associate with your distributed codes.</div>
      </div>

      <div class="v-group">
        <label class="v-label">Why do you want to be a vendor? <span class="text-gray-500 font-normal">(recommended)</span></label>
        <textarea name="reason" class="v-input" rows="3"
          placeholder="e.g. I run a small shop and want to reward customers who buy from me..."><?= e($_POST['reason'] ?? '') ?></textarea>
        <div class="v-hint">A clear reason improves your approval chances.</div>
      </div>

      <hr class="divider">

      <!-- Section 2 -->
      <div class="flex items-center gap-2 mb-4">
        <div class="step-badge">2</div>
        <span class="font-semibold text-sm text-purple-300">Your Personal Details</span>
      </div>

      <div class="v-group">
        <label class="v-label">Full Name <span class="text-red-400">*</span></label>
        <input type="text" name="full_name" class="v-input" placeholder="Your full legal name"
               value="<?= e($_POST['full_name'] ?? '') ?>" required>
      </div>

      <div class="v-group">
        <label class="v-label">Phone Number <span class="text-red-400">*</span></label>
        <input type="tel" name="phone" data-phone class="v-input" placeholder="08012345678"
               value="<?= e($_POST['phone'] ?? '') ?>" required>
        <div class="v-hint">Must be <strong class="text-yellow-400">different</strong> from your customer account phone. Used for vendor login.</div>
      </div>

      <div class="v-group">
        <label class="v-label">Email Address <span class="text-gray-500 font-normal">(optional but recommended)</span></label>
        <input type="email" name="email" class="v-input" placeholder="vendor@yourbusiness.com"
               value="<?= e($_POST['email'] ?? '') ?>">
        <div class="v-hint">Must be <strong class="text-yellow-400">different</strong> from your customer account email.</div>
      </div>

      <hr class="divider">

      <!-- Section 3 -->
      <div class="flex items-center gap-2 mb-4">
        <div class="step-badge">3</div>
        <span class="font-semibold text-sm text-purple-300">Account Security</span>
      </div>

      <div class="v-group">
        <label class="v-label">Password <span class="text-red-400">*</span></label>
        <div class="relative">
          <input type="password" name="password" id="pw" class="v-input" style="padding-right:2.8rem"
                 placeholder="Min. 6 characters" required minlength="6">
          <button type="button" onclick="togglePw('pw')"
                  class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-white text-lg">👁</button>
        </div>
      </div>

      <div class="v-group">
        <label class="v-label">Confirm Password <span class="text-red-400">*</span></label>
        <input type="password" name="confirm_password" class="v-input" placeholder="Repeat password" required>
      </div>

      <hr class="divider">



      <!-- Warning -->
      <div class="warning-box mb-5">
        <div class="font-bold text-red-400 text-sm mb-1.5">⚠️ Critical Vendor Rule</div>
        <p class="text-xs text-gray-300 leading-relaxed">
          ZoeFeeds raffle codes are <strong class="text-white">always free</strong>. As a vendor you must
          <strong class="text-red-400 underline">never sell codes</strong> to customers. Violation results in
          immediate permanent suspension.
        </p>
      </div>

      <!-- Age + Terms -->
      <label class="flex items-start gap-3 mb-5 cursor-pointer">
        <input type="checkbox" name="age18" class="mt-0.5 w-4 h-4 flex-shrink-0" style="accent-color:#7c3aed"
               <?= !empty($_POST['age18']) ? 'checked' : '' ?> required>
        <span class="text-sm text-gray-300">
          I confirm I am <strong class="text-white">18 years or older</strong>, I agree to the
          <a href="<?= APP_URL ?>/user/terms.php" target="_blank" class="text-purple-400 hover:underline">Terms &amp; Conditions</a>,
          and I understand that <strong class="text-white">selling codes is strictly prohibited</strong>.
        </span>
      </label>

      <button type="submit" class="btn-v w-full">
        🏪 Submit Application &amp; Generate API Keys
      </button>
    </form>

    <p class="text-center text-sm text-gray-400 mt-6">
      Already applied?
      <a href="<?= APP_URL ?>/vendor/login.php" class="text-purple-400 hover:underline font-semibold">Vendor Login →</a>
    </p>
  </div>

  <!-- Customer separator -->
  <div class="mt-5 p-4 rounded-xl border border-white/5 text-center text-sm text-gray-500">
    🎟️ Looking to enter draws as a customer?
    <a href="<?= APP_URL ?>/user/register.php" class="text-orange-400 hover:underline font-semibold">Customer Register →</a>
  </div>
  <div class="text-center mt-3">
    <a href="<?= APP_URL ?>/vendor" class="text-xs text-gray-600 hover:text-gray-400 transition-colors">← Back to Vendor Info</a>
  </div>
</div>
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
<script>function togglePw(id){const e=document.getElementById(id);e.type=e.type==='password'?'text':'password';}</script>
</body>
</html>
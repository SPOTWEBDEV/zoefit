<?php
// vendor/register.php — Standalone vendor registration (separate from users table)
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
startAppSession();
if (!empty($_SESSION['vendor_id'])) redirect(APP_URL.'/vendor/dashboard.php');

$redirect = trim($_GET['redirect'] ?? $_POST['redirect'] ?? '');
$redirect = (str_starts_with($redirect, APP_URL) || str_starts_with($redirect, '/')) ? $redirect : '';

$error = $success = '';

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

            // Block phone already used in users table
            $s = $db->prepare("SELECT id FROM users WHERE phone=?");
            $s->execute([$normalizedPhone]);
            if ($s->fetch()) {
                $error = 'This phone number is already registered as a customer account. Vendor accounts must use different credentials. Please use a different phone number, or <a href="'.APP_URL.'/user/login.php" class="underline">log in as a customer →</a>';
            }

            // Block phone already used in vendors table
            if (!$error) {
                $s = $db->prepare("SELECT id FROM vendors WHERE phone=?");
                $s->execute([$normalizedPhone]);
                if ($s->fetch()) {
                    $error = 'This phone number is already registered as a vendor. <a href="'.APP_URL.'/vendor/login.php" class="underline">Log in instead →</a>';
                }
            }

            // Block email collision with users
            if (!$error && $email) {
                $s = $db->prepare("SELECT id FROM users WHERE email=?");
                $s->execute([$email]);
                if ($s->fetch()) {
                    $error = 'This email address is already linked to a customer account. Please use a different email for your vendor account.';
                }
            }

            // Block email collision within vendors
            if (!$error && $email) {
                $s = $db->prepare("SELECT id FROM vendors WHERE email=?");
                $s->execute([$email]);
                if ($s->fetch()) {
                    $error = 'This email address is already registered as a vendor. <a href="'.APP_URL.'/vendor/login.php" class="underline">Log in instead →</a>';
                }
            }

            if (!$error) {
                $hashed = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                $db->prepare(
                    "INSERT INTO vendors (phone, full_name, email, password, business_name, reason, status)
                     VALUES (?,?,?,?,?,?,'pending')"
                )->execute([$normalizedPhone, $fullName, $email ?: null, $hashed, $businessName, $reason]);

                $vendorId = (int)$db->lastInsertId();
                auditLog('vendor', $vendorId, 'register', 'New vendor application: '.$businessName);

                // Auto-login vendor session
                $_SESSION['vendor_id']     = $vendorId;
                $_SESSION['vendor_name']   = $fullName;
                $_SESSION['vendor_status'] = 'pending';
                session_regenerate_id(true);

                $dest = $redirect ?: APP_URL.'/vendor/dashboard.php';
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

    :root {
      --v-primary: #7c3aed;
      --v-accent:  #a78bfa;
      --v-border:  rgba(124,58,237,.22);
      --v-glow:    rgba(124,58,237,.15);
    }

    body { background: #0a0f1a; }

    .vendor-card {
      background: rgba(124,58,237,.04);
      border: 1px solid var(--v-border);
      border-radius: 20px;
    }

    .form-control {
      background: rgba(255,255,255,.04) !important;
      border: 1px solid rgba(255,255,255,.1) !important;
      border-radius: 12px !important;
      color: #fff !important;
      transition: border-color .2s !important;
    }
    .form-control:focus {
      border-color: var(--v-accent) !important;
      outline: none !important;
      box-shadow: 0 0 0 3px rgba(124,58,237,.15) !important;
    }
    .form-control::placeholder { color: #6b7280 !important; }

    .btn-vendor {
      background: linear-gradient(135deg, #7c3aed, #6d28d9);
      color: #fff;
      border: none;
      border-radius: 14px;
      font-weight: 700;
      cursor: pointer;
      transition: all .2s;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: .5rem;
    }
    .btn-vendor:hover {
      background: linear-gradient(135deg, #6d28d9, #5b21b6);
      transform: translateY(-1px);
      box-shadow: 0 8px 24px rgba(124,58,237,.4);
    }

    .step-badge {
      width: 28px; height: 28px;
      background: rgba(124,58,237,.2);
      border: 1.5px solid var(--v-border);
      border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      font-size: .7rem; font-weight: 800;
      color: var(--v-accent);
      flex-shrink: 0;
    }

    .section-divider {
      border: none;
      border-top: 1px solid rgba(124,58,237,.15);
      margin: 1.5rem 0;
    }

    .info-box {
      background: rgba(124,58,237,.06);
      border: 1px solid var(--v-border);
      border-radius: 12px;
    }

    .warning-box {
      background: rgba(239,68,68,.06);
      border: 1px solid rgba(239,68,68,.2);
      border-radius: 12px;
    }
  </style>
</head>
<body class="text-white min-h-screen flex items-center justify-center p-4">
<div class="w-full max-w-lg py-8">

  <!-- Logo -->
  <div class="text-center mb-8">
    <a href="<?= APP_URL ?>/vendor" class="inline-flex items-center gap-2 mb-3">
      <div class="w-10 h-10 bg-purple-600 rounded-xl flex items-center justify-center font-black text-white text-xl">Z</div>
      <span class="font-bold text-2xl">Zoe<span class="text-purple-400">Feeds</span></span>
      <span class="text-xs text-purple-300 bg-purple-500/20 border border-purple-500/30 rounded-full px-2 py-0.5 font-semibold">Vendors</span>
    </a>
    <div class="text-sm text-gray-400">Vendor Application Portal</div>
  </div>

  <!-- Progress indicator -->
  <div class="flex items-center justify-center gap-2 mb-6 text-xs text-gray-500">
    <div class="flex items-center gap-1.5">
      <div class="step-badge">1</div>
      <span class="text-purple-400 font-semibold">Apply</span>
    </div>
    <div class="w-8 h-px bg-white/10"></div>
    <div class="flex items-center gap-1.5">
      <div class="step-badge" style="opacity:.4">2</div>
      <span style="opacity:.4">Review</span>
    </div>
    <div class="w-8 h-px bg-white/10"></div>
    <div class="flex items-center gap-1.5">
      <div class="step-badge" style="opacity:.4">3</div>
      <span style="opacity:.4">Approved</span>
    </div>
  </div>

  <div class="vendor-card p-8">
    <h1 class="text-2xl font-bold mb-1">Vendor Application</h1>
    <p class="text-gray-400 text-sm mb-6">Apply to join the ZoeFeeds vendor network and distribute raffle codes to your customers.</p>

    <!-- Info notice -->
    <div class="info-box p-3 mb-5 flex items-start gap-2.5 text-sm">
      <span class="text-purple-400 mt-0.5 flex-shrink-0">ℹ️</span>
      <span class="text-gray-300">
        This is a <strong class="text-white">separate account</strong> from your customer account.
        Use a <strong class="text-white">different phone number and email</strong> from your customer login.
        Applications are reviewed within 24 hours.
      </span>
    </div>

    <?php if ($redirect): ?>
    <div class="info-box p-3 mb-5 flex items-center gap-2 text-sm">
      <span class="text-purple-400">🎯</span>
      <span class="text-gray-300">Complete your application to continue.</span>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="warning-box text-red-400 p-4 mb-5 text-sm"><?= $error ?></div>
    <?php endif; ?>

    <form method="POST">
      <?= csrfField() ?>
      <?php if ($redirect): ?><input type="hidden" name="redirect" value="<?= e($redirect) ?>"><?php endif; ?>

      <!-- Section: Business Info -->
      <div class="flex items-center gap-2 mb-4">
        <div class="step-badge">1</div>
        <span class="font-semibold text-sm text-purple-300">Business Information</span>
      </div>

      <div class="form-group">
        <label class="form-label">Business / Brand Name <span class="text-red-400">*</span></label>
        <input type="text" name="business_name" class="form-control" placeholder="e.g. Daniel Services, SPOTWEB COM"
               value="<?= e($_POST['business_name'] ?? '') ?>" required autofocus>
        <div class="text-xs text-gray-500 mt-1">The name customers will associate with your distributed codes.</div>
      </div>

      <div class="form-group">
        <label class="form-label">Why do you want to be a vendor? <span class="text-gray-500 font-normal">(recommended)</span></label>
        <textarea name="reason" class="form-control" rows="3"
                  placeholder="e.g. I run a small shop and want to reward customers who buy from me with raffle codes..."
                  style="resize:vertical"><?= e($_POST['reason'] ?? '') ?></textarea>
        <div class="text-xs text-gray-500 mt-1">A clear reason improves your chances of approval.</div>
      </div>

      <hr class="section-divider">

      <!-- Section: Personal Info -->
      <div class="flex items-center gap-2 mb-4">
        <div class="step-badge">2</div>
        <span class="font-semibold text-sm text-purple-300">Your Personal Details</span>
      </div>

      <div class="form-group">
        <label class="form-label">Full Name <span class="text-red-400">*</span></label>
        <input type="text" name="full_name" class="form-control" placeholder="Your full legal name"
               value="<?= e($_POST['full_name'] ?? '') ?>" required>
      </div>

      <div class="form-group">
        <label class="form-label">Phone Number <span class="text-red-400">*</span></label>
        <input type="tel" name="phone" data-phone class="form-control" placeholder="08012345678"
               value="<?= e($_POST['phone'] ?? '') ?>" required>
        <div class="text-xs text-gray-500 mt-1">Must be <strong class="text-yellow-400">different</strong> from your customer account phone. Used for vendor login.</div>
      </div>

      <div class="form-group">
        <label class="form-label">Email Address <span class="text-gray-500 font-normal">(optional but recommended)</span></label>
        <input type="email" name="email" class="form-control" placeholder="vendor@yourbusiness.com"
               value="<?= e($_POST['email'] ?? '') ?>">
        <div class="text-xs text-gray-500 mt-1">Must be <strong class="text-yellow-400">different</strong> from your customer account email.</div>
      </div>

      <hr class="section-divider">

      <!-- Section: Security -->
      <div class="flex items-center gap-2 mb-4">
        <div class="step-badge">3</div>
        <span class="font-semibold text-sm text-purple-300">Account Security</span>
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

      <hr class="section-divider">

      <!-- Warning box -->
      <div class="warning-box p-4 mb-5 text-sm">
        <div class="font-bold text-red-400 mb-2">⚠️ Critical Vendor Rule</div>
        <p class="text-gray-300 text-xs leading-relaxed">
          ZoeFeeds raffle codes are <strong class="text-white">always free</strong>. As a vendor, you must <strong class="text-red-400 underline">never sell codes</strong> to customers under any circumstances. Violation results in immediate and permanent suspension.
        </p>
      </div>

      <!-- Age + Terms -->
      <label class="flex items-start gap-3 mb-5 cursor-pointer">
        <input type="checkbox" name="age18" class="mt-0.5 w-4 h-4 flex-shrink-0"
               style="accent-color:#7c3aed"
               <?= !empty($_POST['age18']) ? 'checked' : '' ?> required>
        <span class="text-sm text-gray-300">
          I confirm I am <strong class="text-white">18 years or older</strong>, I have read and agree to the
          <a href="<?= APP_URL ?>/user/terms.php" target="_blank" class="text-purple-400 hover:underline">Terms &amp; Conditions</a>,
          and I understand that <strong class="text-white">selling raffle codes is strictly prohibited</strong>.
        </span>
      </label>

      <button type="submit" class="btn-vendor w-full py-3.5 text-base">
        🏪 Submit Vendor Application
      </button>
    </form>

    <p class="text-center text-sm text-gray-400 mt-6">
      Already applied?
      <a href="<?= APP_URL ?>/vendor/login.php"
         class="text-purple-400 hover:underline font-semibold">Vendor Login →</a>
    </p>
  </div>

  <!-- Customer separator -->
  <div class="mt-5 p-4 rounded-xl border border-white/5 text-center text-sm text-gray-500">
    🎟️ Looking to enter draws as a customer?
    <a href="<?= APP_URL ?>/user/register.php" class="text-orange-400 hover:underline font-semibold">Customer Register →</a>
  </div>

  <div class="text-center mt-4">
    <a href="<?= APP_URL ?>/vendor" class="text-xs text-gray-600 hover:text-gray-400 transition-colors">← Back to Vendor Info Page</a>
  </div>

</div>
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
<script>function togglePw(id){const e=document.getElementById(id);e.type=e.type==='password'?'text':'password';}</script>
</body>
</html>

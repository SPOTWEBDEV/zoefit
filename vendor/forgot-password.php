<?php
// vendor/forgot-password.php
// 3-step flow — mirrors user version but:
//   • Purple vendor identity
//   • Queries vendors table, not users
//   • password_resets.user_type = 'vendor'
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
startAppSession();

if (!empty($_SESSION['vendor_id'])) redirect(APP_URL . '/vendor/dashboard.php');

$db    = getDB();
$error = $success = '';
$step  = (int)($_SESSION['fp_vendor_step'] ?? 1);

// Ensure password_resets table exists
$db->exec("CREATE TABLE IF NOT EXISTS password_resets (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_type   ENUM('user','vendor') NOT NULL DEFAULT 'user',
    record_id   INT UNSIGNED NOT NULL,
    email       VARCHAR(200) NOT NULL,
    pin_hash    VARCHAR(255) NOT NULL,
    expires_at  DATETIME NOT NULL,
    used        TINYINT(1) NOT NULL DEFAULT 0,
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ── STEP 1: Email submission ───────────────────────────────
if (isPost() && ($_POST['form'] ?? '') === 'request') {
    if (!verifyCsrf($_POST[CSRF_TOKEN_NAME] ?? '')) { $error = 'Invalid request.'; }
    else {
        $email = strtolower(trim($_POST['email'] ?? ''));
        if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } else {
            $v = $db->prepare("SELECT id, full_name, email FROM vendors WHERE email = ?");
            $v->execute([$email]); $v = $v->fetch();

            if (!$v) {
                $success = 'If that email is registered, a reset PIN has been sent to it.';
            } else {
                $pin     = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                $expires = date('Y-m-d H:i:s', time() + 900);

                $db->prepare(
                    "UPDATE password_resets SET used=1 WHERE user_type='vendor' AND record_id=? AND used=0"
                )->execute([$v['id']]);

                $db->prepare(
                    "INSERT INTO password_resets (user_type, record_id, email, pin_hash, expires_at)
                     VALUES ('vendor', ?, ?, ?, ?)"
                )->execute([$v['id'], $email, password_hash($pin, PASSWORD_BCRYPT), $expires]);

                $subject = 'Your ZoeFeeds Vendor Password Reset PIN';
                $body    = "Hi {$v['full_name']},\n\n"
                         . "Your vendor account password reset PIN is:\n\n"
                         . "  $pin\n\n"
                         . "This PIN expires in 15 minutes. Do not share it with anyone.\n\n"
                         . "If you did not request a password reset, ignore this email.\n\n"
                         . "— ZoeFeeds Vendor Team";

                mail($email, $subject, $body,
                    "From: noreply@zoefeeds.com\r\nContent-Type: text/plain; charset=UTF-8");

                $_SESSION['fp_vendor_step']  = 2;
                $_SESSION['fp_vendor_email'] = $email;
                $step    = 2;
                $success = "A 6-digit PIN has been sent to <strong>".e($email)."</strong>. Check your inbox.";
            }
        }
    }
}

// ── STEP 2: PIN verification ───────────────────────────────
if (isPost() && ($_POST['form'] ?? '') === 'verify') {
    if (!verifyCsrf($_POST[CSRF_TOKEN_NAME] ?? '')) { $error = 'Invalid request.'; }
    else {
        $pin   = trim($_POST['pin'] ?? '');
        $email = $_SESSION['fp_vendor_email'] ?? '';

        if (!$pin || !$email) {
            $error = 'Session expired. Please start again.';
            $_SESSION['fp_vendor_step'] = 1; $step = 1;
        } else {
            $r = $db->prepare(
                "SELECT * FROM password_resets
                 WHERE user_type='vendor' AND email=? AND used=0 AND expires_at > NOW()
                 ORDER BY created_at DESC LIMIT 1"
            );
            $r->execute([$email]); $r = $r->fetch();

            if (!$r || !password_verify($pin, $r['pin_hash'])) {
                $error = 'Incorrect or expired PIN. Please try again.';
            } else {
                $_SESSION['fp_vendor_step']     = 3;
                $_SESSION['fp_vendor_reset_id'] = $r['id'];
                $_SESSION['fp_vendor_uid']      = $r['record_id'];
                $step = 3;
            }
        }
    }
}

// ── STEP 3: New password ───────────────────────────────────
if (isPost() && ($_POST['form'] ?? '') === 'reset') {
    if (!verifyCsrf($_POST[CSRF_TOKEN_NAME] ?? '')) { $error = 'Invalid request.'; }
    else {
        $newPw  = $_POST['new_password']     ?? '';
        $confPw = $_POST['confirm_password'] ?? '';
        $uid    = (int)($_SESSION['fp_vendor_uid']      ?? 0);
        $rid    = (int)($_SESSION['fp_vendor_reset_id'] ?? 0);

        if (!$uid || !$rid) {
            $error = 'Session expired. Please start again.';
            $_SESSION['fp_vendor_step'] = 1; $step = 1;
        } elseif (strlen($newPw) < 6) {
            $error = 'Password must be at least 6 characters.'; $step = 3;
        } elseif ($newPw !== $confPw) {
            $error = 'Passwords do not match.'; $step = 3;
        } else {
            // Update vendors table
            $db->prepare("UPDATE vendors SET password=?, updated_at=NOW() WHERE id=?")
               ->execute([password_hash($newPw, PASSWORD_BCRYPT), $uid]);
            $db->prepare("UPDATE password_resets SET used=1 WHERE id=?")->execute([$rid]);
            auditLog('vendor', $uid, 'password_reset', 'Vendor password reset via email PIN');

            unset($_SESSION['fp_vendor_step'], $_SESSION['fp_vendor_email'],
                  $_SESSION['fp_vendor_reset_id'], $_SESSION['fp_vendor_uid']);

            $success = 'Password changed successfully! You can now log in to your vendor account.';
            $step    = 1;
        }
    }
}

if (isset($_GET['resend']) && $step === 2) {
    unset($_SESSION['fp_vendor_step'], $_SESSION['fp_vendor_email']);
    $step = 1;
}
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="csrf-token" content="<?= generateCsrf() ?>">
  <title>Vendor — Reset Password — <?= APP_NAME ?></title>
  <script src="<?= APP_URL ?>/assets/js/tailwind.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
  <style>
    * { font-family: 'Poppins', sans-serif !important; }
    body { background: #0a0f1a; }

    /* Purple vendor palette */
    :root { --vp: #7c3aed; --va: #a78bfa; --vb: rgba(124,58,237,.22); }

    .step-ring {
      width: 36px; height: 36px; border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      font-size: .75rem; font-weight: 800; flex-shrink: 0;
      transition: all .3s;
    }
    .step-ring.done   { background: #22c55e; color: #fff; }
    .step-ring.active { background: var(--vp); color: #fff; box-shadow: 0 0 0 4px rgba(124,58,237,.2); }
    .step-ring.idle   { background: rgba(255,255,255,.06); color: #6b7280; border: 1px solid rgba(255,255,255,.08); }
    .step-connector   { flex: 1; height: 2px; background: rgba(255,255,255,.07); margin: 0 6px; }
    .step-connector.done { background: linear-gradient(90deg, #22c55e, rgba(255,255,255,.1)); }

    .fp-card {
      background: rgba(124,58,237,.04);
      border: 1px solid var(--vb);
      border-radius: 20px;
    }

    .fp-input {
      width: 100%;
      background: rgba(255,255,255,.04);
      border: 1px solid rgba(255,255,255,.10);
      border-radius: 12px;
      color: #fff;
      padding: .75rem 1rem;
      font-size: .9rem;
      transition: border-color .2s, box-shadow .2s;
    }
    .fp-input:focus {
      border-color: var(--va);
      outline: none;
      box-shadow: 0 0 0 3px rgba(124,58,237,.15);
    }
    .fp-input::placeholder { color: #6b7280; }
    .fp-label { display: block; font-size: .8rem; font-weight: 600; color: #d1d5db; margin-bottom: .4rem; }

    .pin-box {
      width: 52px; height: 60px;
      background: rgba(255,255,255,.04);
      border: 1.5px solid rgba(255,255,255,.10);
      border-radius: 12px;
      color: #fff;
      font-size: 1.5rem;
      font-weight: 800;
      text-align: center;
      transition: border-color .2s, box-shadow .2s;
    }
    .pin-box:focus {
      border-color: var(--va);
      outline: none;
      box-shadow: 0 0 0 3px rgba(124,58,237,.15);
    }

    /* Modal */
    .pw-modal-overlay {
      position: fixed; inset: 0; z-index: 999;
      background: rgba(0,0,0,.75);
      backdrop-filter: blur(6px);
      display: flex; align-items: center; justify-content: center; padding: 1rem;
    }
    .pw-modal-box {
      background: #111827;
      border: 1px solid var(--vb);
      border-radius: 20px;
      padding: 2rem;
      width: 100%; max-width: 420px;
      animation: modalIn .25s ease;
    }
    @keyframes modalIn { from { opacity:0; transform:translateY(16px); } to { opacity:1; transform:translateY(0); } }

    .btn-vendor {
      display: inline-flex; align-items: center; justify-content: center;
      width: 100%; padding: .85rem 1.5rem;
      background: linear-gradient(135deg, #7c3aed, #6d28d9);
      color: #fff; border: none; border-radius: 14px;
      font-weight: 700; font-size: .95rem; cursor: pointer;
      transition: all .2s;
    }
    .btn-vendor:hover { opacity: .9; transform: translateY(-1px); box-shadow: 0 8px 24px rgba(124,58,237,.4); }

    .btn-ghost {
      display: inline-flex; align-items: center; justify-content: center;
      width: 100%; padding: .75rem 1.5rem;
      background: transparent; color: #9ca3af;
      border: 1px solid var(--vb); border-radius: 14px;
      font-weight: 600; font-size: .875rem; cursor: pointer; text-decoration: none;
      transition: all .2s;
    }
    .btn-ghost:hover { background: rgba(124,58,237,.06); color: #fff; }
  </style>
</head>
<body class="text-white min-h-screen flex items-center justify-center p-4">

<?php if ($step === 3): ?>
<!-- ── PASSWORD RESET MODAL ──────────────────────────────── -->
<div class="pw-modal-overlay">
  <div class="pw-modal-box">
    <div class="text-center mb-6">
      <div class="w-14 h-14 rounded-2xl flex items-center justify-center text-3xl mx-auto mb-3"
           style="background:rgba(124,58,237,.15)">🔐</div>
      <h2 class="text-xl font-black">Set New Password</h2>
      <p class="text-gray-400 text-sm mt-1">Choose a strong password for your vendor account.</p>
    </div>

    <?php if ($error): ?>
    <div class="bg-red-500/10 border border-red-500/30 text-red-400 rounded-xl p-3 mb-4 text-sm"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="form" value="reset">

      <div class="mb-4">
        <label class="fp-label">New Password</label>
        <div class="relative">
          <input type="password" name="new_password" id="pw-new" class="fp-input pr-12"
                 placeholder="Min. 6 characters" required minlength="6" autofocus>
          <button type="button" onclick="togglePw('pw-new')"
                  class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-white text-lg">👁</button>
        </div>
      </div>

      <div class="mb-6">
        <label class="fp-label">Confirm New Password</label>
        <div class="relative">
          <input type="password" name="confirm_password" id="pw-conf" class="fp-input pr-12"
                 placeholder="Repeat password" required>
          <button type="button" onclick="togglePw('pw-conf')"
                  class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-white text-lg">👁</button>
        </div>
      </div>

      <!-- Strength meter — purple bars -->
      <div class="mb-5">
        <div class="flex gap-1 mb-1" id="strength-bars">
          <?php for ($i=0;$i<4;$i++): ?>
          <div class="h-1.5 flex-1 rounded-full" style="background:rgba(124,58,237,.15)" id="sb<?= $i ?>"></div>
          <?php endfor; ?>
        </div>
        <div class="text-xs text-gray-500" id="strength-label">Enter a password to check strength</div>
      </div>

      <button type="submit" class="btn-vendor">🔑 Change Password</button>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- ── MAIN CARD ──────────────────────────────────────────── -->
<div class="w-full max-w-md py-8">

  <!-- Logo — purple vendor variant -->
  <div class="text-center mb-8">
    <a href="<?= APP_URL ?>/vendor" class="inline-flex items-center gap-2 mb-3">
      <div class="w-10 h-10 rounded-xl flex items-center justify-center font-black text-white text-xl"
           style="background:#7c3aed">Z</div>
      <span class="font-bold text-2xl">Zoe<span style="color:#a78bfa">Feeds</span></span>
      <span class="text-xs rounded-full px-2 py-0.5 font-semibold"
            style="background:rgba(124,58,237,.2);border:1px solid var(--vb);color:#c4b5fd;font-size:.6rem">
        VENDOR
      </span>
    </a>
    <div class="text-sm text-gray-400">Vendor Account Recovery</div>
  </div>

  <!-- Step indicator -->
  <div class="flex items-center mb-7 px-2">
    <div class="step-ring <?= $step >= 1 ? ($step > 1 ? 'done' : 'active') : 'idle' ?>">
      <?= $step > 1 ? '✓' : '1' ?>
    </div>
    <div class="step-connector <?= $step > 1 ? 'done' : '' ?>"></div>
    <div class="step-ring <?= $step === 2 ? 'active' : ($step > 2 ? 'done' : 'idle') ?>">
      <?= $step > 2 ? '✓' : '2' ?>
    </div>
    <div class="step-connector <?= $step > 2 ? 'done' : '' ?>"></div>
    <div class="step-ring <?= $step === 3 ? 'active' : 'idle' ?>">3</div>
  </div>

  <div class="fp-card p-8">

    <?php if ($success): ?>
    <div class="bg-green-500/10 border border-green-500/30 text-green-400 rounded-xl p-4 mb-5 text-sm">
      ✅ <?= $success ?>
    </div>
    <?php endif; ?>
    <?php if ($error && $step !== 3): ?>
    <div class="bg-red-500/10 border border-red-500/30 text-red-400 rounded-xl p-4 mb-5 text-sm">
      ❌ <?= e($error) ?>
    </div>
    <?php endif; ?>

    <!-- ── STEP 1 ─────────────────────────────────────────── -->
    <?php if ($step === 1 && !$success): ?>
    <h1 class="text-2xl font-black mb-1">Forgot Password?</h1>
    <p class="text-gray-400 text-sm mb-6">Enter your vendor account email and we'll send a reset PIN.</p>

    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="form" value="request">
      <div class="mb-5">
        <label class="fp-label">Vendor Email Address</label>
        <input type="email" name="email" class="fp-input"
               placeholder="vendor@yourbusiness.com"
               value="<?= e($_POST['email'] ?? '') ?>" required autofocus>
      </div>
      <button type="submit" class="btn-vendor mb-4">📧 Send Reset PIN</button>
    </form>

    <?php elseif ($step === 1 && $success): ?>
    <h1 class="text-2xl font-black mb-2">Password Changed!</h1>
    <p class="text-gray-400 text-sm mb-6">Your vendor password has been updated. Log in with your new password.</p>
    <a href="<?= APP_URL ?>/vendor/login.php" class="btn-vendor mb-4">→ Vendor Login</a>

    <!-- ── STEP 2 ─────────────────────────────────────────── -->
    <?php elseif ($step === 2): ?>
    <h1 class="text-2xl font-black mb-1">Enter Your PIN</h1>
    <p class="text-gray-400 text-sm mb-1">
      We sent a 6-digit PIN to <strong class="text-white"><?= e($_SESSION['fp_vendor_email'] ?? '') ?></strong>.
    </p>
    <p class="text-xs text-gray-500 mb-6">Check your spam folder. PIN expires in 15 minutes.</p>

    <form method="POST" id="pin-form">
      <?= csrfField() ?>
      <input type="hidden" name="form"  value="verify">
      <input type="hidden" name="pin"   id="pin-hidden">

      <div class="flex gap-2 justify-center mb-6">
        <?php for ($i = 0; $i < 6; $i++): ?>
        <input type="text" maxlength="1" inputmode="numeric" pattern="[0-9]"
               class="pin-box" id="pin-<?= $i ?>" autocomplete="off">
        <?php endfor; ?>
      </div>

      <button type="submit" id="pin-submit" class="btn-vendor mb-4" disabled style="opacity:.5;cursor:not-allowed">
        ✓ Verify PIN
      </button>
    </form>

    <div class="text-center">
      <a href="?resend=1" class="text-sm hover:underline" style="color:#a78bfa">← Use a different email</a>
    </div>

    <!-- ── STEP 3 ─────────────────────────────────────────── -->
    <?php elseif ($step === 3): ?>
    <h1 class="text-2xl font-black mb-1">PIN Verified ✓</h1>
    <p class="text-gray-400 text-sm">Set your new vendor password in the panel that appeared.</p>

    <?php endif; ?>

    <div class="border-t border-white/5 mt-6 pt-5 flex justify-between text-sm text-gray-500">
      <a href="<?= APP_URL ?>/vendor/login.php" class="hover:text-purple-400 transition-colors">← Vendor Login</a>
      <a href="<?= APP_URL ?>/user/forgot-password.php" class="hover:text-orange-400 transition-colors">Customer reset →</a>
    </div>
  </div>

</div>

<script>
// ── PIN boxes ───────────────────────────────────────────────
const pinInputs = document.querySelectorAll('.pin-box');
const pinHidden = document.getElementById('pin-hidden');
const pinSubmit = document.getElementById('pin-submit');

function syncPin() {
  const val = [...pinInputs].map(i => i.value).join('');
  if (pinHidden) pinHidden.value = val;
  if (pinSubmit) {
    const full = val.length === 6 && /^\d{6}$/.test(val);
    pinSubmit.disabled = !full;
    pinSubmit.style.opacity = full ? '1' : '.5';
    pinSubmit.style.cursor  = full ? 'pointer' : 'not-allowed';
  }
}

pinInputs.forEach((inp, i) => {
  inp.addEventListener('input', () => {
    inp.value = inp.value.replace(/\D/g, '').slice(-1);
    if (inp.value && i < pinInputs.length - 1) pinInputs[i + 1].focus();
    syncPin();
  });
  inp.addEventListener('keydown', e => {
    if (e.key === 'Backspace' && !inp.value && i > 0) {
      pinInputs[i - 1].focus();
      pinInputs[i - 1].value = '';
      syncPin();
    }
  });
  inp.addEventListener('paste', e => {
    e.preventDefault();
    const pasted = (e.clipboardData.getData('text') || '').replace(/\D/g,'').slice(0,6);
    [...pasted].forEach((ch, j) => { if (pinInputs[j]) pinInputs[j].value = ch; });
    if (pinInputs[Math.min(pasted.length, 5)]) pinInputs[Math.min(pasted.length, 5)].focus();
    syncPin();
  });
});

// ── Strength meter ──────────────────────────────────────────
const pwNew = document.getElementById('pw-new');
if (pwNew) {
  pwNew.addEventListener('input', () => {
    const v = pwNew.value;
    let score = 0;
    if (v.length >= 6)  score++;
    if (v.length >= 10) score++;
    if (/[A-Z]/.test(v) && /[a-z]/.test(v)) score++;
    if (/[0-9]/.test(v) && /[^a-zA-Z0-9]/.test(v)) score++;

    const colors  = ['#ef4444','#f97316','#eab308','#22c55e'];
    const labels  = ['Weak','Fair','Good','Strong'];
    for (let i = 0; i < 4; i++) {
      const bar = document.getElementById('sb'+i);
      if (!bar) continue;
      bar.style.background = i < score ? colors[score-1] : 'rgba(124,58,237,.12)';
    }
    const lbl = document.getElementById('strength-label');
    if (lbl) lbl.textContent = v ? labels[score-1] + ' password' : 'Enter a password to check strength';
  });
}

function togglePw(id) {
  const el = document.getElementById(id);
  if (el) el.type = el.type === 'password' ? 'text' : 'password';
}

const pwConf = document.getElementById('pw-conf');
if (pwConf && pwNew) {
  pwConf.addEventListener('input', () => {
    pwConf.style.borderColor = (pwConf.value && pwConf.value !== pwNew.value)
      ? '#ef4444' : '';
  });
}
</script>
</body>
</html>

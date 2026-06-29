<?php
// user/forgot-password.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../mailer/index.php'; // ← smtpmailer()
startAppSession();

if (!empty($_SESSION['user_id'])) redirect(APP_URL . '/user/dashboard.php');

$db    = getDB();
$error = $success = '';
$step  = (int)($_SESSION['fp_user_step'] ?? 1);

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
            $u = $db->prepare("SELECT id, full_name, email FROM users WHERE email = ?");
            $u->execute([$email]); $u = $u->fetch();

            if (!$u) {
                // Vague message — don't reveal if email exists
                $success = 'If that email is registered, a reset PIN has been sent to it.';
            } else {
                $pin     = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                $expires = date('Y-m-d H:i:s', time() + 900); // 15 min

                // Invalidate previous unused PINs
                $db->prepare(
                    "UPDATE password_resets SET used=1
                     WHERE user_type='user' AND record_id=? AND used=0"
                )->execute([$u['id']]);

                // Insert new PIN
                $db->prepare(
                    "INSERT INTO password_resets (user_type, record_id, email, pin_hash, expires_at)
                     VALUES ('user', ?, ?, ?, ?)"
                )->execute([$u['id'], $email, password_hash($pin, PASSWORD_BCRYPT), $expires]);

                // ── HTML email via smtpmailer() ──────────────
                $subject = 'Your ZoeFeeds Password Reset PIN';
                $body    = '
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
</head>
<body style="margin:0;padding:0;background:#0a0f1a;font-family:Poppins,Arial,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#0a0f1a;padding:40px 20px;">
    <tr><td align="center">
      <table width="100%" style="max-width:520px;background:#111827;border-radius:16px;border:1px solid rgba(255,255,255,0.08);overflow:hidden;">

        <!-- Header -->
        <tr>
          <td style="background:linear-gradient(135deg,#f97316,#ea580c);padding:28px 32px;text-align:center;">
            <div style="font-size:28px;font-weight:900;color:#fff;letter-spacing:-0.5px;">
              Zoe<span style="color:#fff0e0;">Feeds</span>
            </div>
            <div style="font-size:12px;color:rgba(255,255,255,0.8);margin-top:4px;letter-spacing:1px;">
              CUSTOMER ACCOUNT
            </div>
          </td>
        </tr>

        <!-- Body -->
        <tr>
          <td style="padding:32px;">
            <p style="margin:0 0 8px;font-size:15px;color:#d1d5db;">Hi <strong style="color:#fff;">'.htmlspecialchars($u['full_name']).'</strong>,</p>
            <p style="margin:0 0 24px;font-size:14px;color:#9ca3af;line-height:1.6;">
              We received a request to reset your ZoeFeeds customer account password.
              Use the PIN below to continue. It expires in <strong style="color:#f97316;">15 minutes</strong>.
            </p>

            <!-- PIN box -->
            <div style="background:#0a0f1a;border:2px dashed rgba(249,115,22,0.4);border-radius:12px;padding:24px;text-align:center;margin-bottom:24px;">
              <div style="font-size:11px;color:#6b7280;letter-spacing:2px;text-transform:uppercase;margin-bottom:10px;">Your Reset PIN</div>
              <div style="font-size:42px;font-weight:900;letter-spacing:12px;color:#f97316;font-family:\'Courier New\',monospace;">
                '.htmlspecialchars($pin).'
              </div>
            </div>

            <p style="margin:0 0 8px;font-size:13px;color:#6b7280;line-height:1.6;">
              Enter this PIN on the ZoeFeeds password reset page to set a new password.
            </p>
            <p style="margin:0 0 24px;font-size:13px;color:#6b7280;line-height:1.6;">
              If you did not request this reset, you can safely ignore this email — your account has not been changed.
            </p>

            <!-- CTA button -->
            <div style="text-align:center;margin-bottom:24px;">
              <a href="'.APP_URL.'/user/forgot-password.php"
                 style="display:inline-block;background:linear-gradient(135deg,#f97316,#ea580c);color:#fff;text-decoration:none;font-weight:700;font-size:14px;padding:14px 32px;border-radius:12px;">
                → Go to Reset Page
              </a>
            </div>
          </td>
        </tr>

        <!-- Footer -->
        <tr>
          <td style="padding:16px 32px 24px;border-top:1px solid rgba(255,255,255,0.06);text-align:center;">
            <p style="margin:0;font-size:11px;color:#4b5563;">
              This email was sent by ZoeFeeds · <a href="'.APP_URL.'" style="color:#f97316;text-decoration:none;">www.zoefeeds.com</a><br>
              Do not share your PIN with anyone. ZoeFeeds will never ask for your PIN by phone or chat.
            </p>
          </td>
        </tr>

      </table>
    </td></tr>
  </table>
</body>
</html>';

                $sent = smtpmailer($email, $subject, $body);

                if (!$sent) {
                    $error = 'Failed to send email. Please try again or contact support.';
                } else {
                    $_SESSION['fp_user_step']  = 2;
                    $_SESSION['fp_user_email'] = $email;
                    $step    = 2;
                    $success = 'A 6-digit PIN has been sent to <strong>'.htmlspecialchars($email).'</strong>. Check your inbox (and spam folder).';
                }
            }
        }
    }
}

// ── STEP 2: PIN verification ───────────────────────────────
if (isPost() && ($_POST['form'] ?? '') === 'verify') {
    if (!verifyCsrf($_POST[CSRF_TOKEN_NAME] ?? '')) { $error = 'Invalid request.'; }
    else {
        $pin   = trim($_POST['pin'] ?? '');
        $email = $_SESSION['fp_user_email'] ?? '';

        if (!$pin || !$email) {
            $error = 'Session expired. Please start again.';
            $_SESSION['fp_user_step'] = 1; $step = 1;
        } else {
            $r = $db->prepare(
                "SELECT * FROM password_resets
                 WHERE user_type='user' AND email=? AND used=0 AND expires_at > NOW()
                 ORDER BY created_at DESC LIMIT 1"
            );
            $r->execute([$email]); $r = $r->fetch();

            if (!$r || !password_verify($pin, $r['pin_hash'])) {
                $error = 'Incorrect or expired PIN. Please check and try again.';
            } else {
                $_SESSION['fp_user_step']     = 3;
                $_SESSION['fp_user_reset_id'] = $r['id'];
                $_SESSION['fp_user_uid']      = $r['record_id'];
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
        $uid    = (int)($_SESSION['fp_user_uid']      ?? 0);
        $rid    = (int)($_SESSION['fp_user_reset_id'] ?? 0);

        if (!$uid || !$rid) {
            $error = 'Session expired. Please start again.';
            $_SESSION['fp_user_step'] = 1; $step = 1;
        } elseif (strlen($newPw) < 6) {
            $error = 'Password must be at least 6 characters.'; $step = 3;
        } elseif ($newPw !== $confPw) {
            $error = 'Passwords do not match.'; $step = 3;
        } else {
            $db->prepare("UPDATE users SET password=?, updated_at=NOW() WHERE id=?")
               ->execute([password_hash($newPw, PASSWORD_BCRYPT), $uid]);
            $db->prepare("UPDATE password_resets SET used=1 WHERE id=?")->execute([$rid]);
            auditLog('user', $uid, 'password_reset', 'Password reset via email PIN');

            unset(
                $_SESSION['fp_user_step'],
                $_SESSION['fp_user_email'],
                $_SESSION['fp_user_reset_id'],
                $_SESSION['fp_user_uid']
            );

            $success = 'Your password has been changed. You can now log in.';
            $step    = 1;
        }
    }
}

if (isset($_GET['resend']) && $step === 2) {
    unset($_SESSION['fp_user_step'], $_SESSION['fp_user_email']);
    $step = 1;
}
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="csrf-token" content="<?= generateCsrf() ?>">
  <title>Reset Password — <?= APP_NAME ?></title>
  <script src="<?= APP_URL ?>/assets/js/tailwind.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
  <style>
    * { font-family: 'Poppins', sans-serif !important; }
    body { background: #0a0f1a; }

    .step-ring {
      width: 36px; height: 36px; border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      font-size: .75rem; font-weight: 800; flex-shrink: 0; transition: all .3s;
    }
    .step-ring.done   { background: #22c55e; color: #fff; }
    .step-ring.active { background: #f97316; color: #fff; box-shadow: 0 0 0 4px rgba(249,115,22,.2); }
    .step-ring.idle   { background: rgba(255,255,255,.06); color: #6b7280; border: 1px solid rgba(255,255,255,.08); }
    .step-connector   { flex: 1; height: 2px; background: rgba(255,255,255,.07); margin: 0 6px; }
    .step-connector.done { background: linear-gradient(90deg, #22c55e, rgba(255,255,255,.1)); }

    .fp-card {
      background: rgba(255,255,255,.03);
      border: 1px solid rgba(255,255,255,.08);
      border-radius: 20px;
    }

    .fp-input {
      width: 100%; background: rgba(255,255,255,.04);
      border: 1px solid rgba(255,255,255,.10); border-radius: 12px;
      color: #fff; padding: .75rem 1rem; font-size: .9rem;
      transition: border-color .2s, box-shadow .2s;
    }
    .fp-input:focus {
      border-color: #f97316; outline: none;
      box-shadow: 0 0 0 3px rgba(249,115,22,.15);
    }
    .fp-input::placeholder { color: #6b7280; }
    .fp-label { display: block; font-size: .8rem; font-weight: 600; color: #d1d5db; margin-bottom: .4rem; }

    .pin-box {
      width: 52px; height: 60px;
      background: rgba(255,255,255,.04);
      border: 1.5px solid rgba(255,255,255,.10);
      border-radius: 12px; color: #fff;
      font-size: 1.5rem; font-weight: 800; text-align: center;
      transition: border-color .2s, box-shadow .2s;
    }
    .pin-box:focus {
      border-color: #f97316; outline: none;
      box-shadow: 0 0 0 3px rgba(249,115,22,.15);
    }

    /* Modal */
    .pw-modal-overlay {
      position: fixed; inset: 0; z-index: 999;
      background: rgba(0,0,0,.75); backdrop-filter: blur(6px);
      display: flex; align-items: center; justify-content: center; padding: 1rem;
    }
    .pw-modal-box {
      background: #111827; border: 1px solid rgba(255,255,255,.1);
      border-radius: 20px; padding: 2rem;
      width: 100%; max-width: 420px;
      animation: modalIn .25s ease;
    }
    @keyframes modalIn { from{opacity:0;transform:translateY(16px)} to{opacity:1;transform:translateY(0)} }

    .btn-orange {
      display: inline-flex; align-items: center; justify-content: center;
      width: 100%; padding: .85rem 1.5rem;
      background: linear-gradient(135deg, #f97316, #ea580c);
      color: #fff; border: none; border-radius: 14px;
      font-weight: 700; font-size: .95rem; cursor: pointer; transition: all .2s;
    }
    .btn-orange:hover { opacity:.9; transform:translateY(-1px); box-shadow:0 8px 24px rgba(249,115,22,.35); }
    .btn-orange:disabled { opacity:.5; cursor:not-allowed; transform:none; box-shadow:none; }
  </style>
</head>
<body class="text-white min-h-screen flex items-center justify-center p-4">

<?php if ($step === 3): ?>
<!-- ── PASSWORD RESET MODAL ──────────────────────────────── -->
<div class="pw-modal-overlay">
  <div class="pw-modal-box">
    <div class="text-center mb-6">
      <div class="w-14 h-14 bg-green-500/15 rounded-2xl flex items-center justify-center text-3xl mx-auto mb-3">🔐</div>
      <h2 class="text-xl font-black">Set New Password</h2>
      <p class="text-gray-400 text-sm mt-1">Choose a strong password for your account.</p>
    </div>

    <?php if ($error): ?>
    <div class="bg-red-500/10 border border-red-500/30 text-red-400 rounded-xl p-3 mb-4 text-sm">❌ <?= e($error) ?></div>
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

      <div class="mb-5">
        <label class="fp-label">Confirm New Password</label>
        <div class="relative">
          <input type="password" name="confirm_password" id="pw-conf" class="fp-input pr-12"
                 placeholder="Repeat password" required>
          <button type="button" onclick="togglePw('pw-conf')"
                  class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 hover:text-white text-lg">👁</button>
        </div>
        <div class="text-xs text-red-400 mt-1 hidden" id="pw-mismatch">Passwords do not match</div>
      </div>

      <!-- Strength meter -->
      <div class="mb-5">
        <div class="flex gap-1 mb-1">
          <?php for ($i=0;$i<4;$i++): ?>
          <div class="h-1.5 flex-1 rounded-full bg-white/10" id="sb<?= $i ?>"></div>
          <?php endfor; ?>
        </div>
        <div class="text-xs text-gray-500" id="strength-label">Enter a password to check strength</div>
      </div>

      <button type="submit" class="btn-orange">🔑 Change Password</button>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- ── MAIN CARD ──────────────────────────────────────────── -->
<div class="w-full max-w-md py-8">

  <div class="text-center mb-8">
    <a href="<?= APP_URL ?>" class="inline-flex items-center gap-2 mb-3">
      <div class="w-10 h-10 bg-orange-500 rounded-xl flex items-center justify-center font-black text-white text-xl">Z</div>
      <span class="font-bold text-2xl">Zoe<span class="text-orange-500">Feeds</span></span>
    </a>
    <div class="text-sm text-gray-400">Customer Account Recovery</div>
  </div>

  <!-- Step indicator -->
  <div class="flex items-center mb-7 px-2">
    <div class="step-ring <?= $step >= 1 ? ($step > 1 ? 'done' : 'active') : 'idle' ?>"><?= $step > 1 ? '✓' : '1' ?></div>
    <div class="step-connector <?= $step > 1 ? 'done' : '' ?>"></div>
    <div class="step-ring <?= $step === 2 ? 'active' : ($step > 2 ? 'done' : 'idle') ?>"><?= $step > 2 ? '✓' : '2' ?></div>
    <div class="step-connector <?= $step > 2 ? 'done' : '' ?>"></div>
    <div class="step-ring <?= $step === 3 ? 'active' : 'idle' ?>">3</div>
  </div>

  <div class="fp-card p-8">

    <?php if ($success): ?>
    <div class="bg-green-500/10 border border-green-500/30 text-green-400 rounded-xl p-4 mb-5 text-sm">✅ <?= $success ?></div>
    <?php endif; ?>
    <?php if ($error && $step !== 3): ?>
    <div class="bg-red-500/10 border border-red-500/30 text-red-400 rounded-xl p-4 mb-5 text-sm">❌ <?= e($error) ?></div>
    <?php endif; ?>

    <!-- STEP 1 -->
    <?php if ($step === 1 && !$success): ?>
    <h1 class="text-2xl font-black mb-1">Forgot Password?</h1>
    <p class="text-gray-400 text-sm mb-6">Enter the email address on your account and we'll send you a 6-digit reset PIN.</p>
    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="form" value="request">
      <div class="mb-5">
        <label class="fp-label">Email Address</label>
        <input type="email" name="email" class="fp-input" placeholder="you@example.com"
               value="<?= e($_POST['email'] ?? '') ?>" required autofocus>
      </div>
      <button type="submit" class="btn-orange">📧 Send Reset PIN</button>
    </form>

    <?php elseif ($step === 1 && $success): ?>
    <h1 class="text-2xl font-black mb-2">Password Changed! 🎉</h1>
    <p class="text-gray-400 text-sm mb-6">Your password has been updated. Log in with your new password.</p>
    <a href="<?= APP_URL ?>/user/login.php" class="btn-orange" style="text-decoration:none">→ Log In Now</a>

    <!-- STEP 2 -->
    <?php elseif ($step === 2): ?>
    <h1 class="text-2xl font-black mb-1">Enter Your PIN</h1>
    <p class="text-gray-400 text-sm mb-1">
      We sent a 6-digit PIN to <strong class="text-white"><?= e($_SESSION['fp_user_email'] ?? '') ?></strong>.
    </p>
    <p class="text-xs text-gray-500 mb-6">Check your spam folder too. PIN expires in 15 minutes.</p>

    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="form" value="verify">
      <input type="hidden" name="pin" id="pin-hidden">

      <div class="flex gap-2 justify-center mb-6">
        <?php for ($i=0; $i<6; $i++): ?>
        <input type="text" maxlength="1" inputmode="numeric" pattern="[0-9]"
               class="pin-box" id="pin-<?= $i ?>" autocomplete="off">
        <?php endfor; ?>
      </div>

      <button type="submit" id="pin-submit" class="btn-orange mb-4" disabled>✓ Verify PIN</button>
    </form>

    <div class="text-center text-sm">
      <a href="?resend=1" class="text-orange-400 hover:underline">← Use a different email</a>
    </div>

    <!-- STEP 3 waiting state -->
    <?php elseif ($step === 3): ?>
    <h1 class="text-2xl font-black mb-1">PIN Verified ✓</h1>
    <p class="text-gray-400 text-sm">Complete your password reset in the panel that appeared.</p>

    <?php endif; ?>

    <div class="border-t border-white/5 mt-6 pt-5 flex justify-between text-sm text-gray-500">
      <a href="<?= APP_URL ?>/user/login.php" class="hover:text-orange-400 transition-colors">← Back to Login</a>
      
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
    const pasted = (e.clipboardData.getData('text') || '').replace(/\D/g, '').slice(0, 6);
    [...pasted].forEach((ch, j) => { if (pinInputs[j]) pinInputs[j].value = ch; });
    if (pinInputs[Math.min(pasted.length, 5)]) pinInputs[Math.min(pasted.length, 5)].focus();
    syncPin();
  });
});

// ── Password strength meter ─────────────────────────────────
const pwNew = document.getElementById('pw-new');
const pwConf = document.getElementById('pw-conf');
const mismatch = document.getElementById('pw-mismatch');

if (pwNew) {
  pwNew.addEventListener('input', () => {
    const v = pwNew.value;
    let score = 0;
    if (v.length >= 6) score++;
    if (v.length >= 10) score++;
    if (/[A-Z]/.test(v) && /[a-z]/.test(v)) score++;
    if (/[0-9]/.test(v) && /[^a-zA-Z0-9]/.test(v)) score++;
    const colors = ['#ef4444','#f97316','#eab308','#22c55e'];
    const labels = ['Weak','Fair','Good','Strong'];
    for (let i = 0; i < 4; i++) {
      const b = document.getElementById('sb'+i);
      if (b) b.style.background = i < score ? colors[score-1] : 'rgba(255,255,255,.1)';
    }
    const lbl = document.getElementById('strength-label');
    if (lbl) lbl.textContent = v ? labels[score-1]+' password' : 'Enter a password to check strength';
  });
}

if (pwConf && pwNew && mismatch) {
  pwConf.addEventListener('input', () => {
    const bad = pwConf.value && pwConf.value !== pwNew.value;
    pwConf.style.borderColor = bad ? '#ef4444' : '';
    mismatch.classList.toggle('hidden', !bad);
  });
}

function togglePw(id) {
  const el = document.getElementById(id);
  if (el) el.type = el.type === 'password' ? 'text' : 'password';
}
</script>
</body>
</html>
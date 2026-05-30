<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
$auth = requireUser(); $userId = $auth['id'];
$db   = getDB();
$stmt = $db->prepare("SELECT * FROM users WHERE id=?");
$stmt->execute([$userId]); $user = $stmt->fetch();

// Sync session vendor fields
$_SESSION['is_vendor']     = (bool)$user['is_vendor'];
$_SESSION['vendor_status'] = $user['vendor_status'];

$success = $error = '';

if (isPost()) {
  if (!verifyCsrf($_POST[CSRF_TOKEN_NAME]??'')) { $error='Invalid request.'; }
  else {
    $action = $_POST['action'] ?? '';

    // ── Update basic profile ──────────────────────────────
    if ($action === 'profile') {
      $name  = trim($_POST['full_name']??'');
      $email = trim($_POST['email']??'');
      if (!$name) { $error='Name is required.'; }
      else {
        $db->prepare("UPDATE users SET full_name=?,email=?,updated_at=NOW() WHERE id=?")
           ->execute([$name, $email?:null, $userId]);
        $_SESSION['user_name'] = $name;
        auditLog('user',$userId,'update_profile','Profile updated');
        $success = 'Profile updated successfully.';
        $stmt=$db->prepare("SELECT * FROM users WHERE id=?");$stmt->execute([$userId]);$user=$stmt->fetch();
      }
    }

    // ── Set / update transfer PIN ─────────────────────────
    elseif ($action === 'pin') {
      $pin     = trim($_POST['pin']??'');
      $confirm = trim($_POST['pin_confirm']??'');
      $curPw   = $_POST['current_password']??'';
      if (!password_verify($curPw, $user['password'])) { $error='Current password incorrect.'; }
      elseif (!preg_match('/^\d{4}$/', $pin))           { $error='PIN must be exactly 4 digits.'; }
      elseif ($pin !== $confirm)                         { $error='PINs do not match.'; }
      else {
        $db->prepare("UPDATE users SET transfer_pin=?,updated_at=NOW() WHERE id=?")
           ->execute([password_hash($pin, PASSWORD_BCRYPT), $userId]);
        auditLog('user',$userId,'set_pin','Transfer PIN updated');
        $success = 'Transfer PIN updated successfully.';
      }
    }

    // ── Change password ───────────────────────────────────
    elseif ($action === 'password') {
      $cur  = $_POST['current_password']??'';
      $new  = $_POST['new_password']??'';
      $conf = $_POST['confirm_password']??'';
      if (!password_verify($cur, $user['password'])) { $error='Current password incorrect.'; }
      elseif (strlen($new) < 6)                       { $error='New password must be at least 6 characters.'; }
      elseif ($new !== $conf)                         { $error='Passwords do not match.'; }
      else {
        $db->prepare("UPDATE users SET password=?,updated_at=NOW() WHERE id=?")
           ->execute([password_hash($new, PASSWORD_BCRYPT), $userId]);
        auditLog('user',$userId,'change_password','Password changed');
        $success = 'Password changed successfully.';
      }
    }
  }
}

// Stats
$totalCodes   = $db->prepare("SELECT COUNT(*) FROM codes WHERE current_owner=? AND status NOT IN('used')")->execute([$userId]) ? 0 : 0;
$s=$db->prepare("SELECT COUNT(*) FROM codes WHERE current_owner=? AND status NOT IN('used')");$s->execute([$userId]);$totalCodes=$s->fetchColumn();
$s=$db->prepare("SELECT COUNT(*) FROM draw_entries WHERE user_id=?");$s->execute([$userId]);$totalEntries=$s->fetchColumn();
$s=$db->prepare("SELECT COUNT(*) FROM draw_winners WHERE user_id=?");$s->execute([$userId]);$totalWins=$s->fetchColumn();

$currentPage = 'profile'; $pageTitle = 'My Profile';
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="csrf-token" content="<?= generateCsrf() ?>">
  <title><?= e($pageTitle) ?> — <?= APP_NAME ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
  <style>
    *{font-family:'Poppins',sans-serif!important}
    .avatar-ring{background:conic-gradient(#f97316 0%,#fb923c 50%,rgba(255,255,255,0.1) 50%);padding:3px;border-radius:50%;}
    .tab-btn{transition:all .2s}
  </style>
</head>
<body class="bg-[#0a0f1a] text-white">
<?php include __DIR__ . '/../components/user-sidebar.php'; ?>

<div class="main-content">
  <!-- Topbar -->
  <div class="topbar">
    <button onclick="toggleSidebar()" class="md:hidden text-gray-400 hover:text-white text-2xl mr-3">☰</button>
    <h1 class="text-xl font-bold">My Profile</h1>
  </div>

  <div class="p-4 md:p-6 max-w-2xl mx-auto pb-24 md:pb-6">

    <?php if($success): ?>
    <div class="bg-green-500/10 border border-green-500/30 text-green-400 rounded-xl p-4 mb-5 text-sm flex items-center gap-2">
      <span class="text-xl">✅</span> <?= e($success) ?>
    </div>
    <?php endif; ?>
    <?php if($error): ?>
    <div class="bg-red-500/10 border border-red-500/30 text-red-400 rounded-xl p-4 mb-5 text-sm flex items-center gap-2">
      <span class="text-xl">❌</span> <?= e($error) ?>
    </div>
    <?php endif; ?>

    <!-- Profile Hero Card -->
    <div class="balance-card mb-5 relative overflow-hidden">
      <div class="flex items-center gap-5">
        <!-- Avatar -->
        <div class="avatar-ring flex-shrink-0">
          <div class="w-16 h-16 bg-[#1a2235] rounded-full flex items-center justify-center text-2xl font-black text-orange-400">
            <?= strtoupper(mb_substr($user['full_name'],0,1)) ?>
          </div>
        </div>
        <div class="flex-1 min-w-0">
          <div class="font-bold text-xl truncate"><?= e($user['full_name']) ?></div>
          <div class="text-orange-200 text-sm"><?= e(formatPhone($user['phone'])) ?></div>
          <div class="flex items-center gap-2 mt-2 flex-wrap">
            <span class="badge badge-success"><?= ucfirst($user['status']) ?></span>
            <?php if($user['is_vendor']): ?>
            <span class="badge" style="background:rgba(168,85,247,0.2);color:#a855f7">
              🏪 <?= ucfirst($user['vendor_status']??'Vendor') ?>
            </span>
            <?php endif; ?>
            <span class="text-xs text-orange-200">Member since <?= date('M Y',strtotime($user['created_at'])) ?></span>
          </div>
        </div>
      </div>
    </div>

    <!-- Stats Row -->
    <div class="grid grid-cols-3 gap-3 mb-5">
      <?php $statCards=[
        [$totalCodes,'Codes','text-orange-400'],
        [$totalEntries,'Entries','text-blue-400'],
        [$totalWins,'Wins','text-green-400'],
      ]; foreach($statCards as $sc): ?>
      <div class="card p-4 text-center">
        <div class="text-2xl font-black <?= $sc[2] ?>"><?= $sc[0] ?></div>
        <div class="text-xs text-gray-500 mt-1"><?= $sc[1] ?></div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Tab nav -->
    <div class="flex gap-2 mb-5 p-1 bg-white/5 rounded-xl">
      <?php foreach(['profile'=>'👤 Profile','pin'=>'🔒 PIN','password'=>'🔑 Password'] as $t=>$l): ?>
      <button onclick="showTab('<?= $t ?>')" id="tab-<?= $t ?>"
        class="tab-btn flex-1 py-2.5 rounded-lg text-sm font-medium <?= $t==='profile'?'bg-orange-500 text-white':'text-gray-400 hover:text-white' ?>">
        <?= $l ?>
      </button>
      <?php endforeach; ?>
    </div>

    <!-- ── Profile Tab ────────────────────────────────────── -->
    <form method="POST" id="section-profile" class="card p-5">
      <?= csrfField() ?><input type="hidden" name="action" value="profile">
      <div class="form-group">
        <label class="form-label">Full Name</label>
        <input type="text" name="full_name" class="form-control" value="<?= e($user['full_name']) ?>" required>
      </div>
      <div class="form-group">
        <label class="form-label">Email Address <span class="text-gray-600 font-normal">(optional)</span></label>
        <input type="email" name="email" class="form-control" value="<?= e($user['email']??'') ?>" placeholder="you@example.com">
      </div>
      <div class="form-group">
        <label class="form-label">Phone Number</label>
        <div class="flex items-center gap-3">
          <input type="text" class="form-control opacity-50 cursor-not-allowed" value="<?= e(formatPhone($user['phone'])) ?>" readonly>
          <span class="badge badge-muted whitespace-nowrap">Locked</span>
        </div>
        <div class="text-xs text-gray-600 mt-1">Phone number cannot be changed after registration</div>
      </div>
      <div class="form-group">
        <label class="form-label">Account Created</label>
        <input type="text" class="form-control opacity-50 cursor-not-allowed" value="<?= date('F j, Y \a\t g:i A', strtotime($user['created_at'])) ?>" readonly>
      </div>
      <button type="submit" class="btn btn-primary w-full py-3">Save Profile</button>
    </form>

    <!-- ── PIN Tab ─────────────────────────────────────────── -->
    <form method="POST" id="section-pin" class="card p-5 hidden">
      <?= csrfField() ?><input type="hidden" name="action" value="pin">
      <div class="bg-blue-500/10 border border-blue-500/20 rounded-xl p-4 mb-5 text-sm text-blue-300">
        🔒 Your Transfer PIN protects code transfers. Required every time you send a code to another user.
      </div>
      <div class="form-group">
        <label class="form-label">Current Password <span class="text-gray-500">(verify identity)</span></label>
        <input type="password" name="current_password" class="form-control" required>
      </div>
      <div class="form-group">
        <label class="form-label">New 4-Digit PIN</label>
        <div class="flex gap-3 justify-center mt-2">
          <?php for($i=0;$i<4;$i++): ?>
          <input type="password" maxlength="1" inputmode="numeric"
                 class="pin-char w-14 h-14 text-center text-2xl font-bold rounded-xl border border-white/10 bg-white/5 text-white focus:border-orange-500 focus:outline-none focus:ring-2 focus:ring-orange-500/20">
          <?php endfor; ?>
        </div>
        <input type="hidden" name="pin" id="pin-hidden">
      </div>
      <div class="form-group">
        <label class="form-label">Confirm PIN</label>
        <div class="flex gap-3 justify-center mt-2">
          <?php for($i=0;$i<4;$i++): ?>
          <input type="password" maxlength="1" inputmode="numeric"
                 class="pin-confirm-char w-14 h-14 text-center text-2xl font-bold rounded-xl border border-white/10 bg-white/5 text-white focus:border-orange-500 focus:outline-none focus:ring-2 focus:ring-orange-500/20">
          <?php endfor; ?>
        </div>
        <input type="hidden" name="pin_confirm" id="pin-confirm-hidden">
      </div>
      <div class="text-xs text-gray-500 text-center mb-4">
        <?= $user['transfer_pin'] ? '✅ PIN already set — enter new PIN to update' : '⚠️ No PIN set yet — set one to enable transfers' ?>
      </div>
      <button type="submit" id="pin-submit" class="btn btn-primary w-full py-3">
        <?= $user['transfer_pin'] ? 'Update Transfer PIN' : 'Set Transfer PIN' ?>
      </button>
    </form>

    <!-- ── Password Tab ───────────────────────────────────── -->
    <form method="POST" id="section-password" class="card p-5 hidden">
      <?= csrfField() ?><input type="hidden" name="action" value="password">
      <div class="form-group">
        <label class="form-label">Current Password</label>
        <div class="relative">
          <input type="password" name="current_password" id="pw-cur" class="form-control pr-12" required>
          <button type="button" onclick="togglePw('pw-cur')" class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 text-lg">👁</button>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">New Password <span class="text-gray-500 font-normal">(min 6 chars)</span></label>
        <div class="relative">
          <input type="password" name="new_password" id="pw-new" class="form-control pr-12" required minlength="6">
          <button type="button" onclick="togglePw('pw-new')" class="absolute right-4 top-1/2 -translate-y-1/2 text-gray-400 text-lg">👁</button>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Confirm New Password</label>
        <input type="password" name="confirm_password" class="form-control" required>
      </div>
      <button type="submit" class="btn btn-primary w-full py-3">Change Password</button>
    </form>

    <!-- Quick links -->
    <div class="grid grid-cols-2 gap-3 mt-5">
      <a href="<?= APP_URL ?>/user/transactions.php" class="card p-4 flex items-center gap-3 hover:border-orange-500/30">
        <div class="w-10 h-10 bg-blue-500/15 rounded-xl flex items-center justify-center text-xl">📊</div>
        <div><div class="font-semibold text-sm">History</div><div class="text-xs text-gray-500">All transactions</div></div>
      </a>
      <a href="<?= APP_URL ?>/user/notifications.php" class="card p-4 flex items-center gap-3 hover:border-orange-500/30">
        <div class="w-10 h-10 bg-orange-500/15 rounded-xl flex items-center justify-center text-xl">🔔</div>
        <div><div class="font-semibold text-sm">Notifications</div><div class="text-xs text-gray-500">Alerts & updates</div></div>
      </a>
      <?php if(!$user['is_vendor']): ?>
      <a href="<?= APP_URL ?>/user/vendor-apply.php" class="card p-4 flex items-center gap-3 hover:border-purple-500/30 col-span-2">
        <div class="w-10 h-10 bg-purple-500/15 rounded-xl flex items-center justify-center text-xl">🏪</div>
        <div><div class="font-semibold text-sm text-purple-400">Become a Vendor</div><div class="text-xs text-gray-500">Apply to distribute raffle codes</div></div>
      </a>
      <?php endif; ?>
    </div>
  </div>
</div>

<script src="<?= APP_URL ?>/assets/js/app.js"></script>
<script>
// Tab switching
function showTab(t) {
  ['profile','pin','password'].forEach(s => {
    document.getElementById('section-'+s).classList.toggle('hidden', s!==t);
    const btn = document.getElementById('tab-'+s);
    btn.className = 'tab-btn flex-1 py-2.5 rounded-lg text-sm font-medium '+(s===t?'bg-orange-500 text-white':'text-gray-400 hover:text-white');
  });
}

// PIN inputs
function setupPinInputs(selector, hiddenId) {
  const chars = document.querySelectorAll(selector);
  chars.forEach((inp, i) => {
    inp.addEventListener('input', () => {
      inp.value = inp.value.replace(/\D/g,'').slice(-1);
      if (inp.value && i < chars.length-1) chars[i+1].focus();
      document.getElementById(hiddenId).value = Array.from(chars).map(c=>c.value).join('');
    });
    inp.addEventListener('keydown', e => {
      if (e.key==='Backspace' && !inp.value && i>0) chars[i-1].focus();
    });
  });
}
setupPinInputs('.pin-char',         'pin-hidden');
setupPinInputs('.pin-confirm-char', 'pin-confirm-hidden');

// Password toggle
function togglePw(id) {
  const el = document.getElementById(id);
  el.type = el.type==='password' ? 'text' : 'password';
}
</script>
</body></html>

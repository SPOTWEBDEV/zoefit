<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
$auth = requireVendor(); $vendorId = $auth['id'];
$db = getDB();
$stmt=$db->prepare("SELECT * FROM vendors WHERE id=?");$stmt->execute([$vendorId]);$vendor=$stmt->fetch();

$msg=$err='';
if (isPost() && verifyCsrf($_POST[CSRF_TOKEN_NAME]??'')) {
  $action=$_POST['action']??'';
  if ($action==='profile') {
    $name=trim($_POST['full_name']??'');
    $bname=trim($_POST['business_name']??'');
    if (!$name) { $err='Name required.'; }
    else {
      $db->prepare("UPDATE vendors SET full_name=?,business_name=?,updated_at=NOW() WHERE id=?")->execute([$name,$bname,$vendorId]);
      $_SESSION['vendor_name']=$name;
      auditLog('vendor',$vendorId,'update_profile','Vendor profile updated');
      $msg='Profile updated.';
      $stmt=$db->prepare("SELECT * FROM vendors WHERE id=?");$stmt->execute([$vendorId]);$vendor=$stmt->fetch();
    }
  } elseif ($action==='password') {
    $cur=$_POST['current_password']??''; $new=$_POST['new_password']??''; $conf=$_POST['confirm']??'';
    if (!password_verify($cur,$vendor['password'])) { $err='Current password incorrect.'; }
    elseif (strlen($new)<6) { $err='Password must be at least 6 chars.'; }
    elseif ($new!==$conf) { $err='Passwords do not match.'; }
    else {
      $db->prepare("UPDATE vendors SET password=?,updated_at=NOW() WHERE id=?")->execute([password_hash($new,PASSWORD_BCRYPT),$vendorId]);
      auditLog('vendor',$vendorId,'change_password','Vendor changed password');
      $msg='Password changed.';
    }
  }
}

$vPage='';
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="csrf-token" content="<?= generateCsrf() ?>">
  <title>Profile — <?= APP_NAME ?> Vendor</title>
  <script src="<?= APP_URL ?>/assets/js/tailwind.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
  <style>*{font-family:'Poppins',sans-serif!important}</style>
</head>
<body class="bg-[#0a0f1a] text-white">
<?php include __DIR__ . '/../components/vendor-sidebar.php'; ?>
<div class="main-content">
  <div class="topbar">
    <button onclick="toggleSidebar()" class="md:hidden text-gray-400 text-2xl mr-3">☰</button>
    <h1 class="text-xl font-bold">Vendor Profile</h1>
  </div>
  <div class="p-6 w-[80%] mx-auto">
    <?php if($msg): ?><div class="bg-green-500/10 border border-green-500/30 text-green-400 rounded-xl p-4 mb-5 text-sm"><?= e($msg) ?></div><?php endif; ?>
    <?php if($err): ?><div class="bg-red-500/10 border border-red-500/30 text-red-400 rounded-xl p-4 mb-5 text-sm"><?= e($err) ?></div><?php endif; ?>

    <!-- Avatar Card -->
    <div class="card p-6 flex items-center gap-5 mb-6">
      <div class="w-16 h-16 bg-blue-500/20 rounded-2xl flex items-center justify-center text-3xl font-black text-blue-400"><?= strtoupper($vendor['full_name'][0]) ?></div>
      <div>
        <div class="font-bold text-lg"><?= e($vendor['full_name']) ?></div>
        <div class="text-gray-400 text-sm"><?= e($vendor['business_name']??'No business name') ?></div>
        <div class="text-gray-500 text-xs mt-1"><?= e(formatPhone($vendor['phone'])) ?> · <?= e($vendor['email']) ?></div>
        <span class="badge badge-success mt-2"><?= ucfirst($vendor['status']) ?></span>
      </div>
    </div>

    <!-- Tabs -->
    <div class="flex gap-2 mb-5">
      <?php foreach(['profile'=>'Profile','password'=>'Password'] as $t=>$l): ?>
      <button onclick="showTab('<?= $t ?>')" id="tab-<?= $t ?>" class="tab-btn btn btn-sm <?= $t==='profile'?'btn-primary':'btn-secondary' ?>"><?= $l ?></button>
      <?php endforeach; ?>
    </div>

    <form method="POST" id="section-profile" class="card p-6">
      <?= csrfField() ?><input type="hidden" name="action" value="profile">
      <div class="form-group"><label class="form-label">Full Name</label><input type="text" name="full_name" class="form-control" value="<?= e($vendor['full_name']) ?>" required></div>
      <div class="form-group"><label class="form-label">Business Name</label><input type="text" name="business_name" class="form-control" value="<?= e($vendor['business_name']??'') ?>"></div>
      <div class="form-group"><label class="form-label">Phone (read only)</label><input type="text" class="form-control opacity-50" value="<?= e(formatPhone($vendor['phone'])) ?>" readonly></div>
      <div class="form-group"><label class="form-label">Email (read only)</label><input type="text" class="form-control opacity-50" value="<?= e($vendor['email']) ?>" readonly></div>
      <button type="submit" class="btn btn-primary w-full">Save Changes</button>
    </form>

    <form method="POST" id="section-password" class="card p-6 hidden">
      <?= csrfField() ?><input type="hidden" name="action" value="password">
      <div class="form-group"><label class="form-label">Current Password</label><input type="password" name="current_password" class="form-control" required></div>
      <div class="form-group"><label class="form-label">New Password</label><input type="password" name="new_password" class="form-control" required minlength="6"></div>
      <div class="form-group"><label class="form-label">Confirm Password</label><input type="password" name="confirm" class="form-control" required></div>
      <button type="submit" class="btn btn-primary w-full">Change Password</button>
    </form>
  </div>
</div>
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
<script>
function showTab(t) {
  ['profile','password'].forEach(s => {
    document.getElementById('section-'+s).classList.toggle('hidden', s!==t);
    document.getElementById('tab-'+s).className='tab-btn btn btn-sm '+(s===t?'btn-primary':'btn-secondary');
  });
}
</script>
</body></html>

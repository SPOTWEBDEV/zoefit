<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
$auth = requireUser(); $userId = $auth['id'];
$db   = getDB();
$stmt = $db->prepare("SELECT * FROM users WHERE id=?");
$stmt->execute([$userId]); $user = $stmt->fetch();

// Already a vendor or has pending application
if ($user['is_vendor'] && $user['vendor_status']==='active') redirect(APP_URL.'/user/vendor-panel.php');

$success = $error = '';
$existing = null;
$stmt2=$db->prepare("SELECT * FROM vendor_applications WHERE user_id=? ORDER BY applied_at DESC LIMIT 1");
$stmt2->execute([$userId]); $existing=$stmt2->fetch();

if (isPost() && verifyCsrf($_POST[CSRF_TOKEN_NAME]??'')) {
  if ($user['vendor_status']==='pending') { $error='You already have a pending application.'; }
  else {
    $bname  = trim($_POST['business_name']??'');
    $reason = trim($_POST['reason']??'');
    if (!$bname||!$reason) { $error='Please fill in all fields.'; }
    else {
      $db->prepare("UPDATE users SET is_vendor=1, vendor_status='pending', vendor_business_name=?, vendor_applied_at=NOW() WHERE id=?")
         ->execute([$bname, $userId]);
      $db->prepare("INSERT INTO vendor_applications (user_id,business_name,reason,status,applied_at) VALUES (?,?,?,'pending',NOW())")
         ->execute([$userId,$bname,$reason]);
      $_SESSION['is_vendor']     = true;
      $_SESSION['vendor_status'] = 'pending';
      auditLog('user',$userId,'vendor_apply',"Vendor application: $bname");
      createNotification($userId,'Application Received','Your vendor application is under review. We\'ll notify you within 24 hours.','vendor');
      $success='Application submitted! You\'ll be notified once approved.';
      $stmt=$db->prepare("SELECT * FROM users WHERE id=?");$stmt->execute([$userId]);$user=$stmt->fetch();
    }
  }
}
$currentPage='vendor-apply'; $pageTitle='Become a Vendor';
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="csrf-token" content="<?= generateCsrf() ?>">
  <title><?= e($pageTitle) ?> — <?= APP_NAME ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
  <style>*{font-family:'Poppins',sans-serif!important}</style>
</head>
<body class="bg-[#0a0f1a] text-white">
<?php include __DIR__ . '/../components/user-sidebar.php'; ?>
<div class="main-content">
  <div class="topbar">
    <button onclick="toggleSidebar()" class="md:hidden text-gray-400 text-2xl mr-3">☰</button>
    <h1 class="text-xl font-bold">Become a Vendor</h1>
  </div>
  <div class="p-4 md:p-6 max-w-xl mx-auto pb-24 md:pb-6">

    <?php if ($user['vendor_status']==='pending'): ?>
    <div class="card-glow p-8 text-center mb-6">
      <div class="text-6xl mb-4">⏳</div>
      <h2 class="text-xl font-bold text-yellow-400 mb-2">Application Under Review</h2>
      <p class="text-gray-400 text-sm">Your vendor application is being reviewed by our admin team. You'll receive a notification once it's processed.</p>
      <div class="mt-5 p-4 bg-white/5 rounded-xl text-left text-sm">
        <div class="text-gray-400">Business Name</div>
        <div class="font-semibold mt-1"><?= e($user['vendor_business_name']??'—') ?></div>
        <div class="text-gray-400 mt-3">Applied</div>
        <div class="font-semibold mt-1"><?= $user['vendor_applied_at']?date('F j, Y',strtotime($user['vendor_applied_at'])):'—' ?></div>
      </div>
    </div>

    <?php elseif($user['vendor_status']==='rejected'): ?>
    <div class="bg-red-500/10 border border-red-500/20 rounded-xl p-5 mb-5">
      <div class="font-semibold text-red-400">Previous Application Rejected</div>
      <?php if($existing&&$existing['review_note']): ?>
      <div class="text-sm text-gray-400 mt-1"><?= e($existing['review_note']) ?></div>
      <?php endif; ?>
      <div class="text-xs text-gray-500 mt-2">You may reapply below.</div>
    </div>
    <?php endif; ?>

    <?php if($success): ?>
    <div class="bg-green-500/10 border border-green-500/30 text-green-400 rounded-xl p-4 mb-5 text-sm"><?= e($success) ?></div>
    <?php endif; ?>
    <?php if($error): ?>
    <div class="bg-red-500/10 border border-red-500/30 text-red-400 rounded-xl p-4 mb-5 text-sm"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if (!$user['is_vendor'] || $user['vendor_status']==='rejected'): ?>
    <!-- Benefits -->
    <div class="grid grid-cols-2 gap-3 mb-6">
      <?php $perks=[['🎟️','Distribute Codes','Assign raffle codes to customers'],['💰','Earn Commissions','Earn from every code distributed'],['📊','Track Sales','Real-time distribution analytics'],['🔑','API Access','Public & secret key integration']]; ?>
      <?php foreach($perks as $p): ?>
      <div class="card p-4">
        <div class="text-2xl mb-2"><?= $p[0] ?></div>
        <div class="font-semibold text-sm"><?= $p[1] ?></div>
        <div class="text-xs text-gray-500 mt-1"><?= $p[2] ?></div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Application Form -->
    <form method="POST" class="card p-6">
      <?= csrfField() ?>
      <h2 class="font-bold text-lg mb-5">Vendor Application</h2>
      <div class="form-group">
        <label class="form-label">Business / Brand Name</label>
        <input type="text" name="business_name" class="form-control" placeholder="e.g. Emeka Stores, Blessing Ventures" required>
      </div>
      <div class="form-group">
        <label class="form-label">Why do you want to be a vendor?</label>
        <textarea name="reason" class="form-control" rows="4"
          placeholder="Tell us about your business, how many customers you reach, and how you plan to distribute raffle codes…" required></textarea>
        <div class="text-xs text-gray-500 mt-1">Min 50 characters</div>
      </div>
      <div class="bg-orange-500/10 border border-orange-500/20 rounded-xl p-4 mb-5 text-sm text-orange-300">
        ⚠️ By applying you agree to ZoeFeeds vendor terms. Only internal distribution through the ZoeFeeds platform is permitted.
      </div>
      <button type="submit" class="btn btn-primary w-full py-3">Submit Application</button>
    </form>
    <?php endif; ?>
  </div>
</div>
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
</body></html>

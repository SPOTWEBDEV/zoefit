<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
$auth = requireUser(); $userId = $auth['id'];
$db   = getDB();

$stmt = $db->prepare("SELECT * FROM users WHERE id=?");
$stmt->execute([$userId]); $user = $stmt->fetch();

// Already an active vendor — send to panel
if ($user['is_vendor'] && $user['vendor_status'] === 'active') {
  redirect(APP_URL.'/user/vendor-panel.php');
}

$msg = $err = '';

// Get last application record
$lastApp = $db->prepare("SELECT * FROM vendor_applications WHERE user_id=? ORDER BY applied_at DESC LIMIT 1");
$lastApp->execute([$userId]); $lastApp = $lastApp->fetch();

if (isPost() && verifyCsrf($_POST[CSRF_TOKEN_NAME]??'')) {
  if ($user['is_vendor'] && $user['vendor_status'] === 'pending') {
    $err = 'You already have a pending application. Please wait for admin review.';
  } else {
    $bname  = trim($_POST['business_name'] ?? '');
    $reason = trim($_POST['reason']        ?? '');
    if (strlen($bname) < 2)   { $err = 'Please enter your business or brand name.'; }
    elseif (strlen($reason) < 30) { $err = 'Please write at least 30 characters explaining your application.'; }
    else {
      $db->beginTransaction();
      try {
        // Update user row
        $db->prepare("UPDATE users SET is_vendor=1, vendor_status='pending', vendor_business_name=?, vendor_applied_at=NOW() WHERE id=?")
           ->execute([$bname, $userId]);

        // Record application
        $db->prepare("INSERT INTO vendor_applications (user_id,business_name,reason,status,applied_at) VALUES (?,?,?,'pending',NOW())")
           ->execute([$userId, $bname, $reason]);

        // Update session
        $_SESSION['is_vendor']     = true;
        $_SESSION['vendor_status'] = 'pending';

        createNotification($userId, '📋 Application Submitted',
          'Your vendor application has been received. We will review it and notify you within 24 hours.', 'vendor');

        auditLog('user', $userId, 'vendor_apply', "Vendor application submitted: $bname");
        $db->commit();
        $msg = 'Application submitted successfully! You\'ll be notified once reviewed.';

        // Refresh user
        $stmt = $db->prepare("SELECT * FROM users WHERE id=?"); $stmt->execute([$userId]); $user = $stmt->fetch();
      } catch(\Exception $e) {
        $db->rollBack(); $err = 'Something went wrong. Please try again.';
      }
    }
  }
}

$currentPage = 'vendor-apply'; $pageTitle = 'Become a Vendor';
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="csrf-token" content="<?= generateCsrf() ?>">
  <title><?= e($pageTitle) ?> — <?= APP_NAME ?></title>
  <script src="<?= APP_URL ?>/assets/js/tailwind.js"></script>
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

  <div class="p-4 md:p-6 pb-24 md:pb-6 max-w-xl mx-auto">

    <?php if($msg): ?>
    <div class="bg-green-500/10 border border-green-500/30 text-green-400 rounded-xl p-4 mb-5 text-sm flex items-center gap-2">
      ✅ <?= e($msg) ?>
    </div>
    <?php endif; ?>
    <?php if($err): ?>
    <div class="bg-red-500/10 border border-red-500/30 text-red-400 rounded-xl p-4 mb-5 text-sm flex items-center gap-2">
      ⚠️ <?= e($err) ?>
    </div>
    <?php endif; ?>

    <!-- Pending state -->
    <?php if ($user['is_vendor'] && $user['vendor_status'] === 'pending'): ?>
    <div class="card p-8 text-center mb-5">
      <div class="text-6xl mb-4">⏳</div>
      <h2 class="text-xl font-bold text-yellow-400 mb-2">Application Under Review</h2>
      <p class="text-gray-400 text-sm mb-5">Your vendor application is being reviewed by our admin team. You will receive a notification once it has been processed — usually within 24 hours.</p>
      <div class="bg-white/5 rounded-xl p-4 text-left text-sm space-y-2">
        <div class="flex justify-between">
          <span class="text-gray-500">Business Name</span>
          <span class="font-semibold"><?= e($user['vendor_business_name'] ?? '—') ?></span>
        </div>
        <div class="flex justify-between">
          <span class="text-gray-500">Applied</span>
          <span class="font-semibold"><?= $user['vendor_applied_at'] ? date('M j, Y g:i A', strtotime($user['vendor_applied_at'])) : '—' ?></span>
        </div>
        <div class="flex justify-between">
          <span class="text-gray-500">Status</span>
          <span class="badge badge-warning">Pending Review</span>
        </div>
      </div>
    </div>

    <!-- Rejected state — can re-apply -->
    <?php elseif ($user['is_vendor'] && $user['vendor_status'] === 'rejected'): ?>
    <div class="bg-red-500/10 border border-red-500/20 rounded-xl p-5 mb-5">
      <div class="font-semibold text-red-400 mb-1">Previous Application Rejected</div>
      <?php if($lastApp && $lastApp['review_note']): ?>
      <div class="text-sm text-gray-400">Admin note: <?= e($lastApp['review_note']) ?></div>
      <?php endif; ?>
      <div class="text-xs text-gray-500 mt-2">You may submit a new application below.</div>
    </div>
    <?php endif; ?>

    <!-- Application form — show when not pending -->
    <?php if (!$user['is_vendor'] || in_array($user['vendor_status'], ['rejected', null])): ?>

    <!-- Benefits -->
    <div class="grid grid-cols-2 gap-3 mb-6">
      <?php foreach([
        ['🎟️','Distribute Codes','Assign raffle codes to customers'],
        ['📊','Track Sales','Real-time distribution analytics'],
        ['🔑','API Access','Public &amp; secret key integration'],
        ['💼','Vendor Tools','Download codes, manage inventory'],
      ] as $p): ?>
      <div class="card p-4">
        <div class="text-2xl mb-2"><?= $p[0] ?></div>
        <div class="font-semibold text-sm mb-1"><?= $p[1] ?></div>
        <div class="text-xs text-gray-500"><?= $p[2] ?></div>
      </div>
      <?php endforeach; ?>
    </div>

    <form method="POST" class="card p-6">
      <?= csrfField() ?>
      <h2 class="font-bold text-lg mb-5">Vendor Application</h2>

      <div class="form-group">
        <label class="form-label">Business / Brand Name <span class="text-red-400">*</span></label>
        <input type="text" name="business_name" class="form-control"
               placeholder="e.g. Emeka Stores, Blessing Ventures…"
               value="<?= e($_POST['business_name']??'') ?>" required>
      </div>

      <div class="form-group">
        <label class="form-label">Why do you want to become a ZoeFeeds vendor? <span class="text-red-400">*</span></label>
        <textarea name="reason" class="form-control" rows="5"
                  placeholder="Tell us about your business, how many customers you reach, and how you plan to distribute raffle codes to them…"
                  required><?= e($_POST['reason']??'') ?></textarea>
        <div class="text-xs text-gray-500 mt-1">Minimum 30 characters</div>
      </div>

      <div class="bg-orange-500/10 border border-orange-500/20 rounded-xl p-4 mb-5 text-xs text-orange-300 leading-relaxed">
        ⚠️ By applying you agree to ZoeFeeds vendor terms. You may only distribute codes — never sell them. ZoeFeeds admin will review and approve your application.
      </div>

      <button type="submit" class="btn btn-primary w-full py-3 font-bold">
        Submit Vendor Application →
      </button>
    </form>

    <?php endif; ?>

  </div>
</div>
<script>
  window.APP_URL = '<?= APP_URL ?>';
</script>
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
</body></html>

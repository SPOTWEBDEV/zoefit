<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
$auth = requireAdmin(); $adminId = $auth['id'];
$db = getDB();
$msg = $err = '';

// ── Quick actions from dashboard links ─────────────────────
if (isset($_GET['quick_approve']) && ($uid=(int)$_GET['quick_approve'])) {
  $db->prepare("UPDATE users SET vendor_status='active',vendor_approved_at=NOW(),vendor_approved_by=? WHERE id=? AND is_vendor=1")
     ->execute([$adminId,$uid]);
  $db->prepare("UPDATE vendor_applications SET status='approved',reviewed_by=?,reviewed_at=NOW() WHERE user_id=? AND status='pending'")
     ->execute([$adminId,$uid]);
  $u=$db->prepare("SELECT full_name FROM users WHERE id=?");$u->execute([$uid]);$u=$u->fetch();
  createNotification($uid,'🏪 Vendor Approved','Congratulations! Your vendor application has been approved. You can now distribute raffle codes.','vendor');
  auditLog('admin',$adminId,'approve_vendor_request','Vendor approved: '.($u['full_name']??''),'user',$uid);
  $msg = 'Vendor approved successfully.';
}
if (isset($_GET['quick_reject']) && ($uid=(int)$_GET['quick_reject'])) {
  $db->prepare("UPDATE users SET vendor_status='rejected' WHERE id=? AND is_vendor=1")->execute([$uid]);
  $db->prepare("UPDATE vendor_applications SET status='rejected',reviewed_by=?,reviewed_at=NOW() WHERE user_id=? AND status='pending'")
     ->execute([$adminId,$uid]);
  createNotification($uid,'Vendor Application Update','Your vendor application was not approved at this time. You may reapply later.','vendor');
  auditLog('admin',$adminId,'reject_vendor_request','Vendor rejected','user',$uid);
  $msg = 'Application rejected.';
}

// ── Full form actions ──────────────────────────────────────
if (isPost() && verifyCsrf($_POST[CSRF_TOKEN_NAME]??'')) {
  $action  = $_POST['action']??'';
  $uid     = (int)($_POST['user_id']??0);
  $note    = trim($_POST['review_note']??'');

  if ($uid && $action === 'approve') {
    $db->prepare("UPDATE users SET vendor_status='active',vendor_approved_at=NOW(),vendor_approved_by=? WHERE id=?")
       ->execute([$adminId,$uid]);
    $db->prepare("UPDATE vendor_applications SET status='approved',reviewed_by=?,review_note=?,reviewed_at=NOW() WHERE user_id=? AND status='pending'")
       ->execute([$adminId,$note,$uid]);
    $u=$db->prepare("SELECT full_name FROM users WHERE id=?");$u->execute([$uid]);$u=$u->fetch();
    createNotification($uid,'🏪 Vendor Approved!','Your vendor application has been approved. Visit the Vendor Panel in your dashboard to get started.','vendor');
    auditLog('admin',$adminId,'approve_vendor','Approved vendor: '.($u['full_name']??$uid),'user',$uid);
    $msg = 'Vendor account activated.';
  } elseif ($uid && $action === 'reject') {
    $db->prepare("UPDATE users SET vendor_status='rejected' WHERE id=?")->execute([$uid]);
    $db->prepare("UPDATE vendor_applications SET status='rejected',reviewed_by=?,review_note=?,reviewed_at=NOW() WHERE user_id=? AND status='pending'")
       ->execute([$adminId,$note,$uid]);
    $u=$db->prepare("SELECT full_name FROM users WHERE id=?");$u->execute([$uid]);$u=$u->fetch();
    createNotification($uid,'Vendor Application Update',$note?:'Your vendor application was not approved at this time.','vendor');
    auditLog('admin',$adminId,'reject_vendor','Rejected vendor: '.($u['full_name']??$uid),'user',$uid);
    $msg = 'Application rejected.';
  } elseif ($uid && $action === 'suspend_vendor') {
    $db->prepare("UPDATE users SET vendor_status='suspended' WHERE id=?")->execute([$uid]);
    auditLog('admin',$adminId,'suspend_vendor',"Vendor $uid suspended",'user',$uid);
    $msg = 'Vendor suspended.';
  } elseif ($uid && $action === 'reactivate_vendor') {
    $db->prepare("UPDATE users SET vendor_status='active',vendor_approved_by=?,vendor_approved_at=NOW() WHERE id=?")->execute([$adminId,$uid]);
    auditLog('admin',$adminId,'reactivate_vendor',"Vendor $uid reactivated",'user',$uid);
    $msg = 'Vendor reactivated.';
  } elseif ($uid && $action === 'assign_codes') {
    $qty = min(10000,max(1,(int)($_POST['code_count']??0)));
    $codes=$db->prepare("SELECT id FROM codes WHERE status='unassigned' LIMIT $qty");
    $codes->execute(); $codes=$codes->fetchAll();
    $assigned=0;
    foreach($codes as $c){
      $db->prepare("UPDATE codes SET assigned_vendor=?,status='assigned',assigned_at=NOW() WHERE id=?")->execute([$uid,$c['id']]);
      $assigned++;
    }
    $db->prepare("UPDATE users SET vendor_code_balance=vendor_code_balance+? WHERE id=?")->execute([$assigned,$uid]);
    auditLog('admin',$adminId,'assign_codes_vendor',"$assigned codes assigned to vendor $uid",'user',$uid);
    $msg = "$assigned codes assigned to vendor.";
  }
}

// ── Fetch all requests/vendors by tab ─────────────────────
$tab = $_GET['tab'] ?? 'pending';
$allowed = ['pending','approved','rejected','suspended','all'];
if (!in_array($tab,$allowed)) $tab='pending';

$page = max(1,(int)($_GET['page']??1)); $per=20; $offset=($page-1)*$per;
$q = trim($_GET['q']??'');

$where = "is_vendor=1";
if ($tab!=='all') $where .= " AND vendor_status='$tab'";
if ($q) { $where .= " AND (full_name LIKE ? OR phone LIKE ? OR vendor_business_name LIKE ?)"; }
$params = $q ? ["%$q%","%$q%","%$q%"] : [];

$rows = $db->prepare("SELECT * FROM users WHERE $where ORDER BY ".
  ($tab==='pending'?'vendor_applied_at ASC':'vendor_approved_at DESC')." LIMIT $per OFFSET $offset");
$rows->execute($params); $rows=$rows->fetchAll();

$cnt = $db->prepare("SELECT COUNT(*) FROM users WHERE $where"); $cnt->execute($params); $total=$cnt->fetchColumn();
$pages = ceil($total/$per);

// Tab counts
$tabCounts=[];
foreach(['pending','approved','rejected','suspended'] as $t){
  $s=$db->query("SELECT COUNT(*) FROM users WHERE is_vendor=1 AND vendor_status='$t'");
  $tabCounts[$t]=(int)$s->fetchColumn();
}

$aPage='vendor-requests';
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="csrf-token" content="<?= generateCsrf() ?>">
  <title>Vendor Requests — <?= APP_NAME ?> Admin</title>
  <script src="<?= APP_URL ?>/assets/js/tailwind.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
  <style>*{font-family:'Poppins',sans-serif!important}</style>
</head>
<body class="bg-[#0a0f1a] text-white">
<?php include __DIR__ . '/../components/admin-sidebar.php'; ?>
<div class="main-content">
  <div class="topbar">
    <button onclick="toggleSidebar()" class="md:hidden text-gray-400 text-2xl mr-3">☰</button>
    <h1 class="text-xl font-bold">Vendor Requests</h1>
    <div class="text-sm text-gray-400"><?= number_format($total) ?> records</div>
  </div>
  <div class="p-4 md:p-6">

    <?php if($msg): ?><div class="bg-green-500/10 border border-green-500/30 text-green-400 rounded-xl p-4 mb-5 text-sm flex items-center gap-2"><span class="text-lg">✅</span><?= e($msg) ?></div><?php endif; ?>
    <?php if($err): ?><div class="bg-red-500/10 border border-red-500/30 text-red-400 rounded-xl p-4 mb-5 text-sm"><?= e($err) ?></div><?php endif; ?>

    <!-- Tabs with counts -->
    <div class="flex flex-wrap gap-2 mb-5">
      <?php $tabLabels=['pending'=>'⏳ Pending','approved'=>'✅ Approved','rejected'=>'❌ Rejected','suspended'=>'🚫 Suspended','all'=>'All']; ?>
      <?php foreach($tabLabels as $t=>$l): ?>
      <a href="?tab=<?= $t ?>" class="btn btn-sm <?= $tab===$t?'btn-primary':'btn-secondary' ?> flex items-center gap-1.5">
        <?= $l ?>
        <?php if($t!=='all'&&isset($tabCounts[$t])&&$tabCounts[$t]>0): ?>
        <span class="<?= $tab===$t?'bg-white/30':'bg-orange-500' ?> text-white text-xs rounded-full w-5 h-5 flex items-center justify-center font-bold"><?= $tabCounts[$t] ?></span>
        <?php endif; ?>
      </a>
      <?php endforeach; ?>
    </div>

    <!-- Search -->
    <form method="GET" class="flex gap-3 mb-5">
      <input type="hidden" name="tab" value="<?= e($tab) ?>">
      <input type="text" name="q" class="form-control flex-1 max-w-md" placeholder="Search name, phone, business…" value="<?= e($q) ?>">
      <button class="btn btn-primary px-5">Search</button>
      <?php if($q): ?><a href="?tab=<?= $tab ?>" class="btn btn-secondary px-4">Clear</a><?php endif; ?>
    </form>

    <!-- Results -->
    <?php if($rows): ?>
    <div class="space-y-4">
      <?php foreach($rows as $r): ?>
      <?php
        // Get the vendor application record
        $app=$db->prepare("SELECT * FROM vendor_applications WHERE user_id=? ORDER BY applied_at DESC LIMIT 1");
        $app->execute([$r['id']]); $app=$app->fetch();
        // Count codes assigned
        $codeCount=$db->prepare("SELECT COUNT(*) FROM codes WHERE assigned_vendor=?");
        $codeCount->execute([$r['id']]); $codeCount=$codeCount->fetchColumn();
      ?>
      <div class="card p-5">
        <div class="flex flex-col md:flex-row md:items-start gap-4">
          <!-- User info -->
          <div class="flex items-start gap-3 flex-1 min-w-0">
            <div class="w-12 h-12 rounded-2xl flex items-center justify-center text-xl font-black flex-shrink-0
              <?= match($r['vendor_status']){'active'=>'bg-green-500/20 text-green-400','pending'=>'bg-yellow-500/20 text-yellow-400','rejected'=>'bg-red-500/20 text-red-400','suspended'=>'bg-gray-500/20 text-gray-400',default=>'bg-orange-500/15 text-orange-400'} ?>">
              <?= strtoupper($r['full_name'][0]) ?>
            </div>
            <div class="flex-1 min-w-0">
              <div class="flex items-center gap-2 flex-wrap">
                <a href="<?= APP_URL ?>/admin/user-detail.php?id=<?= $r['id'] ?>" class="font-bold text-base hover:text-orange-400 transition-colors">
                  <?= e($r['full_name']) ?>
                </a>
                <span class="badge <?= match($r['vendor_status']){'active'=>'badge-success','pending'=>'badge-warning','rejected'=>'badge-danger','suspended'=>'badge-muted',default=>'badge-muted'} ?>">
                  <?= ucfirst($r['vendor_status']??'unknown') ?>
                </span>
              </div>
              <div class="text-sm text-gray-400 mt-0.5"><?= e(formatPhone($r['phone'])) ?> <?= $r['email']?'· '.e($r['email']):'' ?></div>
              <div class="flex flex-wrap gap-x-5 gap-y-1 mt-2 text-xs text-gray-500">
                <span>🏪 <?= e($r['vendor_business_name']??'No business name') ?></span>
                <span>🎟️ <?= $codeCount ?> codes assigned</span>
                <span>💼 Balance: <?= $r['vendor_code_balance'] ?></span>
                <?php if($r['vendor_applied_at']): ?><span>📅 Applied <?= date('M j, Y', strtotime($r['vendor_applied_at'])) ?></span><?php endif; ?>
                <?php if($r['vendor_approved_at']): ?><span>✅ Approved <?= date('M j, Y', strtotime($r['vendor_approved_at'])) ?></span><?php endif; ?>
              </div>
              <?php if($app&&$app['reason']): ?>
              <div class="mt-2 p-3 bg-white/3 rounded-lg text-xs text-gray-400 border border-white/5">
                <span class="text-gray-500 font-medium">Application reason:</span> <?= e($app['reason']) ?>
              </div>
              <?php endif; ?>
              <?php if($app&&$app['review_note']&&$r['vendor_status']!=='pending'): ?>
              <div class="mt-2 p-3 bg-white/3 rounded-lg text-xs text-gray-400 border border-white/5">
                <span class="text-gray-500 font-medium">Admin note:</span> <?= e($app['review_note']) ?>
              </div>
              <?php endif; ?>
            </div>
          </div>

          <!-- Action buttons -->
          <div class="flex-shrink-0 flex flex-col gap-2 min-w-[160px]">
            <?php if($r['vendor_status']==='pending'): ?>
            <button onclick="openAction(<?= $r['id'] ?>,'approve','<?= e(addslashes($r['full_name'])) ?>')"
                    class="btn btn-sm w-full py-2" style="background:#22c55e;color:white">✓ Approve Vendor</button>
            <button onclick="openAction(<?= $r['id'] ?>,'reject','<?= e(addslashes($r['full_name'])) ?>')"
                    class="btn btn-sm btn-secondary w-full py-2 text-red-400">✕ Reject</button>
            <?php elseif($r['vendor_status']==='active'): ?>
            <button onclick="openAssign(<?= $r['id'] ?>,'<?= e(addslashes($r['full_name'])) ?>')"
                    class="btn btn-sm btn-primary w-full py-2">🎟️ Assign Codes</button>
            <form method="POST"><<?= csrfField() ?><input type="hidden" name="action" value="suspend_vendor"><input type="hidden" name="user_id" value="<?= $r['id'] ?>">
              <button class="btn btn-sm btn-secondary w-full py-2 text-yellow-400">⚠️ Suspend</button></form>
            <?php elseif($r['vendor_status']==='suspended'||$r['vendor_status']==='rejected'): ?>
            <form method="POST"><?= csrfField() ?><input type="hidden" name="action" value="reactivate_vendor"><input type="hidden" name="user_id" value="<?= $r['id'] ?>">
              <button class="btn btn-sm w-full py-2" style="background:#22c55e;color:white">▶ Reactivate</button></form>
            <?php endif; ?>
            <a href="<?= APP_URL ?>/admin/user-detail.php?id=<?= $r['id'] ?>" class="btn btn-sm btn-secondary w-full py-2 text-center">👤 View Profile</a>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- Pagination -->
    <?php if($pages>1): ?>
    <div class="flex items-center justify-between mt-5">
      <div class="text-sm text-gray-400">Page <?= $page ?>/<?= $pages ?> (<?= number_format($total) ?>)</div>
      <div class="flex gap-2">
        <?php if($page>1): ?><a href="?tab=<?= $tab ?>&page=<?= $page-1 ?>&q=<?= urlencode($q) ?>" class="btn btn-sm btn-secondary">← Prev</a><?php endif; ?>
        <?php if($page<$pages): ?><a href="?tab=<?= $tab ?>&page=<?= $page+1 ?>&q=<?= urlencode($q) ?>" class="btn btn-sm btn-secondary">Next →</a><?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <?php else: ?>
    <div class="card p-12 text-center">
      <div class="text-5xl mb-4"><?= $tab==='pending'?'⏳':'🏪' ?></div>
      <div class="text-gray-400">No <?= $tab==='all'?'':$tab ?> vendor applications found<?= $q?' for "'.$q.'"':'' ?></div>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Approve/Reject Modal -->
<div class="modal-overlay" id="action-modal">
  <div class="modal-box">
    <h3 class="text-xl font-bold mb-1" id="modal-title">Review Application</h3>
    <p class="text-gray-400 text-sm mb-5" id="modal-subtitle"></p>
    <form method="POST" id="action-form">
      <?= csrfField() ?>
      <input type="hidden" name="action" id="action-field">
      <input type="hidden" name="user_id" id="action-user-id">
      <div class="form-group">
        <label class="form-label">Review Note <span class="text-gray-500 font-normal">(optional)</span></label>
        <textarea name="review_note" class="form-control" rows="3" placeholder="Add a note to the applicant…"></textarea>
      </div>
      <div class="flex gap-3" id="modal-buttons"></div>
    </form>
  </div>
</div>

<!-- Assign Codes Modal -->
<div class="modal-overlay" id="assign-modal">
  <div class="modal-box">
    <h3 class="text-xl font-bold mb-2">Assign Codes to Vendor</h3>
    <p class="text-gray-400 text-sm mb-5">Vendor: <strong id="assign-name" class="text-white"></strong></p>
    <form method="POST">
      <?= csrfField() ?>
      <input type="hidden" name="action" value="assign_codes">
      <input type="hidden" name="user_id" id="assign-uid">
      <div class="form-group">
        <label class="form-label">Number of Codes</label>
        <input type="number" name="code_count" class="form-control" min="1" max="10000" value="100" required>
        <div class="text-xs text-gray-500 mt-1">Available unassigned: <?= $db->query("SELECT COUNT(*) FROM codes WHERE status='unassigned'")->fetchColumn() ?> codes</div>
      </div>
      <div class="flex gap-3">
        <button type="button" data-close-modal="assign-modal" class="btn btn-secondary flex-1">Cancel</button>
        <button type="submit" class="btn btn-primary flex-1">🎟️ Assign</button>
      </div>
    </form>
  </div>
</div>

<script src="<?= APP_URL ?>/assets/js/app.js"></script>
<script>
function openAction(uid, action, name) {
  const titles = { approve: '✅ Approve Vendor Application', reject: '❌ Reject Application' };
  const subs   = {
    approve: `Approving ${name} as a ZoeFeeds vendor. They will gain access to the vendor panel.`,
    reject:  `Rejecting ${name}'s vendor application. Add a note explaining why (optional).`
  };
  const buttons = {
    approve: `<button type="button" data-close-modal="action-modal" class="btn btn-secondary flex-1">Cancel</button>
              <button type="submit" class="btn flex-1 py-3 font-bold" style="background:#22c55e;color:white">✓ Approve</button>`,
    reject:  `<button type="button" data-close-modal="action-modal" class="btn btn-secondary flex-1">Cancel</button>
              <button type="submit" class="btn btn-danger flex-1 py-3 font-bold">✕ Reject</button>`,
  };
  document.getElementById('modal-title').textContent   = titles[action];
  document.getElementById('modal-subtitle').textContent = subs[action];
  document.getElementById('action-field').value        = action;
  document.getElementById('action-user-id').value      = uid;
  document.getElementById('modal-buttons').innerHTML   = buttons[action];
  Modal.open('action-modal');
}

function openAssign(uid, name) {
  document.getElementById('assign-uid').value   = uid;
  document.getElementById('assign-name').textContent = name;
  Modal.open('assign-modal');
}
</script>
</body></html>

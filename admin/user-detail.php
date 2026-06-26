<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
$auth = requireAdmin(); $adminId = $auth['id'];
$uid = (int)($_GET['id']??0); if(!$uid) redirect(APP_URL.'/admin/users.php');
$db = getDB();
$stmt=$db->prepare("SELECT * FROM users WHERE id=?");$stmt->execute([$uid]);$user=$stmt->fetch();
if(!$user) redirect(APP_URL.'/admin/users.php');

$codes  = $db->prepare("SELECT * FROM codes WHERE current_owner=? ORDER BY redeemed_at DESC LIMIT 20"); $codes->execute([$uid]); $codes=$codes->fetchAll();
$txns   = $db->prepare("SELECT * FROM transactions WHERE user_id=? ORDER BY created_at DESC LIMIT 15"); $txns->execute([$uid]); $txns=$txns->fetchAll();
$draws  = $db->prepare("SELECT d.title, COUNT(de.id) as cnt FROM draw_entries de JOIN draws d ON de.draw_id=d.id WHERE de.user_id=? GROUP BY de.draw_id ORDER BY MAX(de.entered_at) DESC LIMIT 5"); $draws->execute([$uid]); $draws=$draws->fetchAll();
$wins   = $db->prepare("SELECT dw.*, d.title FROM draw_winners dw JOIN draws d ON dw.draw_id=d.id WHERE dw.user_id=?"); $wins->execute([$uid]); $wins=$wins->fetchAll();

$aPage='users';
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="csrf-token" content="<?= generateCsrf() ?>">
  <title>User #<?= $uid ?> — <?= APP_NAME ?> Admin</title>
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
    <a href="<?= APP_URL ?>/admin/users.php" class="text-orange-400 text-sm hover:underline mr-3">← Users</a>
    <h1 class="text-xl font-bold">User Detail</h1>
  </div>
  <div class="p-6">
    <div class="grid lg:grid-cols-3 gap-6">
      <!-- Left: Profile -->
      <div class="space-y-4">
        <div class="card p-6 text-center">
          <div class="w-16 h-16 bg-orange-500/20 rounded-2xl flex items-center justify-center text-3xl font-black text-orange-400 mx-auto mb-4"><?= strtoupper($user['full_name'][0]) ?></div>
          <div class="font-bold text-lg"><?= e($user['full_name']) ?></div>
          <div class="text-gray-400 text-sm mt-1"><?= e(formatPhone($user['phone'])) ?></div>
          <div class="text-gray-400 text-xs mt-1"><?= e($user['email']??'No email') ?></div>
          <span class="badge <?= $user['status']==='active'?'badge-success':($user['status']==='suspended'?'badge-warning':'badge-danger') ?> mt-3"><?= ucfirst($user['status']) ?></span>
        </div>
        <div class="card p-5 space-y-3">
          <div class="flex justify-between text-sm"><span class="text-gray-400">Balance</span><span class="font-bold text-orange-400"><?= $user['balance'] ?> codes</span></div>
          <div class="flex justify-between text-sm"><span class="text-gray-400">Total Codes</span><span><?= count($codes) ?>+</span></div>
          <div class="flex justify-between text-sm"><span class="text-gray-400">Draw Entries</span><span><?= count($draws) ?> draws</span></div>
          <div class="flex justify-between text-sm"><span class="text-gray-400">Wins</span><span class="text-yellow-400"><?= count($wins) ?></span></div>
          <div class="flex justify-between text-sm"><span class="text-gray-400">Member Since</span><span class="text-xs"><?= date('M j, Y',strtotime($user['created_at'])) ?></span></div>
        </div>
        <div class="card p-5 space-y-2">
          <div class="font-semibold text-sm mb-3">Actions</div>
          <?php if($user['status']==='active'): ?>
          <form method="POST" action="<?= APP_URL ?>/admin/users.php" onsubmit="return confirm('Suspend this user?')">
            <?= csrfField() ?><input type="hidden" name="action" value="suspend"><input type="hidden" name="user_id" value="<?= $uid ?>">
            <button class="btn btn-sm w-full text-yellow-400 btn-secondary">⚠️ Suspend User</button>
          </form>
          <?php else: ?>
          <form method="POST" action="<?= APP_URL ?>/admin/users.php">
            <?= csrfField() ?><input type="hidden" name="action" value="activate"><input type="hidden" name="user_id" value="<?= $uid ?>">
            <button class="btn btn-sm w-full text-green-400 btn-secondary">✓ Activate User</button>
          </form>
          <?php endif; ?>
        </div>
      </div>

      <!-- Right: Tabs -->
      <div class="lg:col-span-2 space-y-4">
        <!-- Codes -->
        <div class="card">
          <div class="p-4 border-b border-white/5 font-bold text-sm">Recent Codes (<?= count($codes) ?>+)</div>
          <div class="divide-y divide-white/5 max-h-64 overflow-y-auto">
            <?php foreach(array_slice($codes,0,10) as $c): ?>
            <div class="flex items-center justify-between p-3">
              <span class="font-mono text-orange-400 text-sm tracking-wider"><?= e($c['code']) ?></span>
              <span class="badge <?= match($c['status']){'redeemed'=>'badge-success','reserved'=>'badge-warning','used'=>'badge-muted',default=>'badge-info'} ?>"><?= $c['status'] ?></span>
            </div>
            <?php endforeach; if(!$codes): ?><div class="p-6 text-center text-gray-500 text-sm">No codes</div><?php endif; ?>
          </div>
        </div>

        <!-- Recent Transactions -->
        <div class="card">
          <div class="p-4 border-b border-white/5 font-bold text-sm">Recent Transactions</div>
          <div class="divide-y divide-white/5 max-h-64 overflow-y-auto">
            <?php foreach($txns as $t): ?>
            <div class="flex items-center justify-between p-3">
              <div>
                <div class="text-sm"><?= ucfirst(str_replace('_',' ',$t['category'])) ?></div>
                <div class="text-xs text-gray-500"><?= date('M j, g:ia',strtotime($t['created_at'])) ?></div>
              </div>
              <span class="font-semibold <?= $t['type']==='credit'?'text-green-400':'text-red-400' ?>"><?= $t['type']==='credit'?'+':'-' ?><?= abs($t['amount']) ?></span>
            </div>
            <?php endforeach; if(!$txns): ?><div class="p-6 text-center text-gray-500 text-sm">No transactions</div><?php endif; ?>
          </div>
        </div>

        <!-- Wins -->
        <?php if($wins): ?>
        <div class="card">
          <div class="p-4 border-b border-white/5 font-bold text-sm">🏆 Wins</div>
          <?php foreach($wins as $w): ?>
          <div class="p-4 border-b border-white/5">
            <div class="font-medium text-sm"><?= e($w['title']) ?></div>
            <div class="font-mono text-orange-400 text-sm mt-1"><?= e($w['winning_code']) ?></div>
            <div class="text-xs text-gray-400">Matched <?= $w['matched_digits'] ?>/15 · <?= date('M j, Y',strtotime($w['announced_at'])) ?></div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
</body></html>

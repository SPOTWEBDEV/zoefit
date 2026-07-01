<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/draw-engine.php';

$auth = requireAdmin(); $adminId = $auth['id'];
$db   = getDB();
$msg  = $err = '';

// Manual "Run Now" trigger — useful while testing or if the host cron isn't set up yet
if (isPost() && verifyCsrf($_POST[CSRF_TOKEN_NAME]??'') && ($_POST['action']??'')==='run_now') {
  $results = finalize_all_expired_draws($db, 'admin', $adminId);
  $ok = count(array_filter($results, fn($r)=>!empty($r['success'])));
  $failed = count($results) - $ok;

  try {
    $db->prepare("INSERT INTO cron_logs (job_name,draws_found,draws_ok,draws_failed,detail,run_at) VALUES (?,?,?,?,?,NOW())")
       ->execute(['finalize-expired-draws (manual)', count($results), $ok, $failed,
         implode("\n", array_map(fn($r)=> !empty($r['success'])
           ? "✅ Draw #{$r['draw_id']} \"{$r['draw_title']}\" finalized. Code: {$r['winning_code']}"
           : "❌ Draw #{$r['draw_id']} \"{$r['draw_title']}\" FAILED: {$r['error']}", $results))
       ]);
  } catch(\Exception $e) {}

  $msg = count($results)===0
    ? 'No expired draws found. Nothing to finalize.'
    : "Processed ".count($results)." draw(s). $ok succeeded, $failed failed.";
}

// Pull recent cron history
$logs = [];
try {
  $logs = $db->query("SELECT * FROM cron_logs ORDER BY run_at DESC LIMIT 30")->fetchAll();
} catch(\Exception $e) {}

// Currently expired draws awaiting finalization
$pendingExpired = $db->query(
  "SELECT id,title,end_date,(SELECT COUNT(*) FROM draw_entries WHERE draw_id=draws.id) entry_count
   FROM draws WHERE status IN ('active','paused') AND end_date <= NOW() ORDER BY end_date ASC"
)->fetchAll();

$lastRun = $logs[0] ?? null;
$aPage = 'draws';
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="csrf-token" content="<?= generateCsrf() ?>">
  <title>Cron Status — <?= APP_NAME ?> Admin</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
  <style>*{font-family:'Poppins',sans-serif!important}pre,code{font-family:'Courier New',monospace!important}</style>
</head>
<body class="bg-[#0a0f1a] text-white">
<?php include __DIR__ . '/../components/admin-sidebar.php'; ?>
<div class="main-content">
  <div class="topbar">
    <button onclick="toggleSidebar()" class="md:hidden text-gray-400 text-2xl mr-3">☰</button>
    <h1 class="text-xl font-bold">Auto-Finalize / Cron Status</h1>
  </div>

  <div class="p-4 md:p-6 max-w-4xl mx-auto">
    <?php if($msg): ?><div class="bg-green-500/10 border border-green-500/30 text-green-400 rounded-xl p-4 mb-5 text-sm"><?= e($msg) ?></div><?php endif; ?>
    <?php if($err): ?><div class="bg-red-500/10 border border-red-500/30 text-red-400 rounded-xl p-4 mb-5 text-sm"><?= e($err) ?></div><?php endif; ?>

    <!-- Status overview -->
    <div class="grid sm:grid-cols-3 gap-3 mb-5">
      <div class="card p-4 text-center">
        <div class="text-2xl font-black <?= $pendingExpired ? 'text-yellow-400' : 'text-green-400' ?>"><?= count($pendingExpired) ?></div>
        <div class="text-xs text-gray-500 mt-1">Draws Awaiting Finalization</div>
      </div>
      <div class="card p-4 text-center">
        <div class="text-sm font-bold text-gray-300"><?= $lastRun ? date('M j, g:i:s A',strtotime($lastRun['run_at'])) : 'Never run' ?></div>
        <div class="text-xs text-gray-500 mt-1">Last Cron Run</div>
      </div>
      <div class="card p-4 text-center">
        <div class="text-2xl font-black text-orange-400"><?= count($logs) ?></div>
        <div class="text-xs text-gray-500 mt-1">Logged Runs (last 30)</div>
      </div>
    </div>

    <!-- Pending expired draws -->
    <?php if ($pendingExpired): ?>
    <div class="card p-5 mb-5 border-yellow-500/30" style="background:linear-gradient(135deg,rgba(234,179,8,.08),var(--bg-card))">
      <div class="flex items-center justify-between mb-4">
        <div>
          <div class="font-bold text-yellow-400">⏳ <?= count($pendingExpired) ?> Draw(s) Awaiting Auto-Finalization</div>
          <div class="text-xs text-gray-400 mt-0.5">These have passed their end date. The cron job will pick them up on its next run (every minute).</div>
        </div>
        <form method="POST" onsubmit="return confirm('Manually finalize all expired draws right now?')">
          <?= csrfField() ?><input type="hidden" name="action" value="run_now">
          <button class="btn btn-primary px-5">⚡ Run Now</button>
        </form>
      </div>
      <div class="space-y-2">
        <?php foreach($pendingExpired as $p): ?>
        <div class="flex items-center justify-between bg-white/3 rounded-lg px-4 py-2.5">
          <div>
            <a href="<?= APP_URL ?>/admin/draw-manage.php?id=<?= $p['id'] ?>" class="font-medium text-sm hover:text-orange-400"><?= e($p['title']) ?></a>
            <div class="text-xs text-gray-500">Ended <?= date('M j, Y g:ia',strtotime($p['end_date'])) ?> · <?= $p['entry_count'] ?> entries</div>
          </div>
          <span class="badge badge-warning text-xs">Pending</span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php else: ?>
    <div class="card p-5 mb-5 text-center border-green-500/20">
      <div class="text-3xl mb-2">✅</div>
      <div class="font-semibold text-green-400">All caught up — no draws awaiting finalization</div>
    </div>
    <?php endif; ?>

    <!-- Setup instructions -->
    <div class="card p-5 mb-5">
      <h2 class="font-bold mb-3">⚙️ Cron Setup Instructions</h2>
      <p class="text-sm text-gray-400 mb-3">For draws to auto-finalize the instant they expire, schedule this script to run every minute on your server:</p>
      <div class="bg-black/40 rounded-lg p-3 text-xs text-green-400 font-mono overflow-x-auto mb-3">
        * * * * * /usr/bin/php <?= realpath(__DIR__.'/..') ?>/cron/finalize-expired-draws.php >> <?= realpath(__DIR__.'/..') ?>/logs/cron.log 2>&1
      </div>
      <p class="text-xs text-gray-500">In cPanel: go to <strong>Cron Jobs</strong>, set interval to "Every Minute", and paste the command above (adjust the PHP path if needed). Full instructions are documented inside <code class="text-orange-400">cron/finalize-expired-draws.php</code>.</p>
    </div>

    <!-- Run history -->
    <div class="card">
      <div class="px-5 pt-5 pb-3 border-b border-white/5 font-bold">Run History</div>
      <?php if($logs): ?>
      <div class="divide-y divide-white/5">
        <?php foreach($logs as $log): ?>
        <div class="px-5 py-3">
          <div class="flex items-center justify-between gap-3 mb-1">
            <span class="text-sm font-medium"><?= e($log['job_name']) ?></span>
            <span class="text-xs text-gray-500"><?= date('M j, Y g:i:s A',strtotime($log['run_at'])) ?></span>
          </div>
          <div class="flex gap-3 text-xs">
            <span class="text-gray-400">Found: <?= $log['draws_found'] ?></span>
            <span class="text-green-400">OK: <?= $log['draws_ok'] ?></span>
            <?php if($log['draws_failed']>0): ?><span class="text-red-400">Failed: <?= $log['draws_failed'] ?></span><?php endif; ?>
          </div>
          <?php if($log['detail']): ?>
          <pre class="text-xs text-gray-500 mt-2 whitespace-pre-wrap bg-black/20 rounded-lg p-2"><?= e($log['detail']) ?></pre>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
      <div class="p-8 text-center text-gray-500 text-sm">No cron runs logged yet. Set up the cron job or click "Run Now" above to test.</div>
      <?php endif; ?>
    </div>

  </div>
</div>
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
</body></html>

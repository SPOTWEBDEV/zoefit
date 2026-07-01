<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
$auth = requireUser(); $userId = $auth['id'];
$db   = getDB();

$drawId = (int)($_GET['id'] ?? 0);
if (!$drawId) redirect(APP_URL.'/user/past-winners.php');

$draw = $db->prepare("SELECT * FROM draws WHERE id=? AND status='completed'");
$draw->execute([$drawId]); $draw = $draw->fetch();
if (!$draw) redirect(APP_URL.'/user/past-winners.php');

$top3 = $db->prepare(
  "SELECT dr.*, u.full_name FROM draw_rankings dr JOIN users u ON dr.user_id=u.id
   WHERE dr.draw_id=? ORDER BY dr.rank_position ASC"
);
$top3->execute([$drawId]); $top3 = $top3->fetchAll();

$myEntries = $db->prepare(
  "SELECT c.code, de.entered_at FROM draw_entries de JOIN codes c ON de.code_id=c.id
   WHERE de.draw_id=? AND de.user_id=? ORDER BY de.entered_at ASC"
);
$myEntries->execute([$drawId, $userId]); $myEntries = $myEntries->fetchAll();

$myRank = null;
foreach ($top3 as $r) if ($r['user_id'] == $userId) { $myRank = $r['rank_position']; break; }

$currentPage = 'past-winners'; $pageTitle = $draw['title'];
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= e($pageTitle) ?> Result — <?= APP_NAME ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
  <style>
    *{font-family:'Poppins',sans-serif!important}
    code{font-family:'Courier New',monospace!important}
    .digit-slot{display:inline-flex;align-items:center;justify-content:center;width:30px;height:38px;border-radius:7px;font-size:16px;font-weight:900;font-family:'Courier New',monospace;border:2px solid rgba(255,255,255,.1);background:rgba(255,255,255,.04);}
    .digit-slot.match{background:rgba(34,197,94,.2);border-color:#22c55e;color:#22c55e;}
    .digit-slot.no-match{background:rgba(239,68,68,.08);border-color:rgba(239,68,68,.25);color:#9ca3af;}
    .podium{border-radius:18px;padding:20px;text-align:center;}
  </style>
</head>
<body class="bg-[#0a0f1a] text-white">
<?php include __DIR__ . '/../components/user-sidebar.php'; ?>
<div class="main-content">
  <div class="topbar">
    <button onclick="toggleSidebar()" class="md:hidden text-gray-400 text-2xl mr-3">☰</button>
    <a href="<?= APP_URL ?>/user/past-winners.php" class="text-orange-400 text-sm hover:underline mr-3">← Past Winners</a>
    <h1 class="text-lg font-bold truncate"><?= e($draw['title']) ?></h1>
  </div>

  <div class="p-4 md:p-6 pb-24 md:pb-6 max-w-3xl mx-auto">

    <?php if ($myRank): ?>
    <div class="bg-green-500/10 border border-green-500/25 rounded-2xl p-5 mb-5 text-center">
      <div class="text-4xl mb-2"><?= $myRank===1?'🏆':($myRank===2?'🥈':'🥉') ?></div>
      <div class="font-bold text-green-400">You finished #<?= $myRank ?> in this draw!</div>
    </div>
    <?php endif; ?>

    <!-- Winning code reveal -->
    <div class="card p-6 mb-5 text-center">
      <div class="text-sm text-gray-400 mb-3">Official Winning Code</div>
      <div class="flex flex-wrap justify-center gap-1.5 mb-2">
        <?php foreach(str_split($draw['winning_code']) as $d): ?>
        <div class="digit-slot" style="background:rgba(249,115,22,.15);border-color:#f97316;color:#f97316"><?= $d ?></div>
        <?php endforeach; ?>
      </div>
      <div class="text-xs text-gray-500 mt-2">Generated at finalization · <?= date('M j, Y g:i A', strtotime($draw['finalized_at'])) ?></div>
    </div>

    <!-- Top 3 Podium -->
    <?php if ($top3): ?>
    <div class="grid sm:grid-cols-3 gap-3 mb-5">
      <?php
      $styles = [
        1 => ['🥇','#eab308','rgba(234,179,8,.08)','rgba(234,179,8,.3)'],
        2 => ['🥈','#d1d5db','rgba(255,255,255,.03)','rgba(255,255,255,.15)'],
        3 => ['🥉','#fb923c','rgba(194,65,12,.06)','rgba(194,65,12,.25)'],
      ];
      foreach($top3 as $r): $s = $styles[$r['rank_position']];
        $isMe = $r['user_id'] == $userId;
      ?>
      <div class="podium" style="background:<?= $s[2] ?>;border:1.5px solid <?= $isMe?'#22c55e':$s[3] ?>">
        <div class="text-3xl mb-2"><?= $s[0] ?></div>
        <div class="font-bold mb-1" style="color:<?= $s[1] ?>"><?= $isMe ? 'You' : e($r['full_name']) ?></div>
        <code class="text-xs text-orange-400 block mb-2"><?= e($r['user_code']) ?></code>
        <div class="text-sm"><span class="font-bold text-white"><?= $r['matched_digits'] ?>/15</span> <span class="text-gray-500">matched</span></div>
        <div class="text-xs text-gray-600 mt-1"><?= $r['entries_count'] ?> entries</div>
      </div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- My entries in this draw -->
    <?php if ($myEntries): ?>
    <div class="card">
      <div class="px-5 pt-5 pb-3 border-b border-white/5 font-bold text-sm">Your Entries in This Draw</div>
      <div class="divide-y divide-white/5">
        <?php foreach($myEntries as $me):
          $matches = 0;
          for($i=0;$i<15;$i++) if($me['code'][$i]===$draw['winning_code'][$i]) $matches++;
        ?>
        <div class="flex items-center justify-between px-5 py-3">
          <code class="text-sm text-orange-400"><?= e($me['code']) ?></code>
          <span class="text-sm font-semibold <?= $matches>=10?'text-green-400':($matches>=5?'text-yellow-400':'text-gray-500') ?>"><?= $matches ?>/15 matched</span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

  </div>
</div>
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
</body></html>

<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
$auth = requireAdmin(); $adminId = $auth['id'];
$drawId = (int)($_GET['id']??0); if(!$drawId) redirect(APP_URL.'/admin/draws.php');
$db = getDB();
$stmt=$db->prepare("SELECT * FROM draws WHERE id=?");$stmt->execute([$drawId]);$draw=$stmt->fetch();
if(!$draw) redirect(APP_URL.'/admin/draws.php');

$msg=$err='';

if (isPost() && verifyCsrf($_POST[CSRF_TOKEN_NAME]??'')) {
  $action=$_POST['action']??'';

  if ($action==='reveal_digit') {
    $digit=preg_replace('/\D/','',$_POST['digit']??'');
    if (strlen($digit)===1) {
      // Get or create reveal row
      $rev=$db->prepare("SELECT * FROM draw_reveal WHERE draw_id=?");$rev->execute([$drawId]);$rev=$rev->fetch();
      if (!$rev) { $db->prepare("INSERT INTO draw_reveal (draw_id,revealed_digits) VALUES (?,?)")->execute([$drawId,$digit]); }
      else {
        if (strlen($rev['revealed_digits'])<15) {
          $db->prepare("UPDATE draw_reveal SET revealed_digits=CONCAT(revealed_digits,?) WHERE draw_id=?")->execute([$digit,$drawId]);
        }
      }
      auditLog('admin',$adminId,'reveal_digit',"Draw $drawId: digit '$digit' revealed",'draw',$drawId);
    }
  } elseif ($action==='set_winning_code') {
    $wcode=preg_replace('/\D/','',$_POST['winning_code']??'');
    if (strlen($wcode)===15) {
      $db->prepare("UPDATE draws SET winning_code=? WHERE id=?")->execute([$wcode,$drawId]);
      // Ensure reveal is set to full code
      $rev=$db->prepare("SELECT id FROM draw_reveal WHERE draw_id=?");$rev->execute([$drawId]);$rev=$rev->fetch();
      if ($rev) { $db->prepare("UPDATE draw_reveal SET revealed_digits=? WHERE draw_id=?")->execute([$wcode,$drawId]); }
      else { $db->prepare("INSERT INTO draw_reveal (draw_id,revealed_digits) VALUES (?,?)")->execute([$drawId,$wcode]); }
      auditLog('admin',$adminId,'set_winning_code',"Draw $drawId winning code set: $wcode",'draw',$drawId);
      $msg="Winning code set. Now run the finalization to determine winner.";
    } else { $err="Winning code must be exactly 15 digits."; }
  } elseif ($action==='finalize') {
    $wcode=$draw['winning_code'];
    if (!$wcode) { $err="Set the winning code first."; }
    else {
      // Winner calculation engine
      $entries=$db->query("SELECT de.user_id, de.code_id, c.code, de.entered_at,
        (SELECT COUNT(*) FROM draw_entries WHERE draw_id=$drawId AND user_id=de.user_id) as entry_count,
        u.balance as wallet_balance,
        (SELECT COUNT(DISTINCT draw_id) FROM draw_entries WHERE user_id=de.user_id) as participation
        FROM draw_entries de JOIN codes c ON de.code_id=c.id JOIN users u ON de.user_id=u.id
        WHERE de.draw_id=$drawId")->fetchAll();

      if (!$entries) { $err="No entries in this draw."; }
      else {
        // Count matching digits per user (best code per user)
        $userBest=[];
        foreach($entries as $e) {
          $matched=0;
          for($i=0;$i<15;$i++) { if(isset($e['code'][$i])&&isset($wcode[$i])&&$e['code'][$i]===$wcode[$i]) $matched++; }
          if (!isset($userBest[$e['user_id']])||$matched>$userBest[$e['user_id']]['matched']) {
            $userBest[$e['user_id']]=['matched'=>$matched,'entry'=>$e];
          }
        }
        // Sort: matched DESC, then tie-breakers
        usort($userBest,function($a,$b){
          if($a['matched']!==$b['matched']) return $b['matched']-$a['matched'];
          // T1: earliest entry time
          if($a['entry']['entered_at']!==$b['entry']['entered_at']) return strcmp($a['entry']['entered_at'],$b['entry']['entered_at']);
          // T2: most entries
          if($a['entry']['entry_count']!==$b['entry']['entry_count']) return $b['entry']['entry_count']-$a['entry']['entry_count'];
          // T3: wallet balance
          if($a['entry']['wallet_balance']!==$b['entry']['wallet_balance']) return $b['entry']['wallet_balance']-$a['entry']['wallet_balance'];
          // T4: participation
          if($a['entry']['participation']!==$b['entry']['participation']) return $b['entry']['participation']-$a['entry']['participation'];
          // T5: random
          return rand(-1,1);
        });
        $winner=$userBest[0];
        $winnerId=$winner['entry']['user_id'];
        $tiebreaker=$winner['matched']===$userBest[1]['matched']??-1?'tiebreaker':'highest_match';

        $db->beginTransaction();
        try {
          // Save winner
          $db->prepare("INSERT INTO draw_winners (draw_id,user_id,winning_code,matched_digits,tiebreaker_used) VALUES (?,?,?,?,?)")
             ->execute([$drawId,$winnerId,$wcode,$winner['matched'],$tiebreaker]);
          // Update draw
          $db->prepare("UPDATE draws SET status='completed',winner_user_id=?,finalized_by=?,finalized_at=NOW() WHERE id=?")->execute([$winnerId,$adminId,$drawId]);
          // Consume all entered codes (post-draw rule)
          $db->prepare("UPDATE codes c JOIN draw_entries de ON c.id=de.code_id SET c.status='used' WHERE de.draw_id=?")->execute([$drawId]);
          // Zero balance for all participants
          $pids=array_column($entries,'user_id');
          $pids=array_unique($pids);
          foreach($pids as $pid) {
            // Recalculate balance from remaining active codes
            $bal=$db->prepare("SELECT COUNT(*) FROM codes WHERE current_owner=? AND status='redeemed'");
            $bal->execute([$pid]);
            $db->prepare("UPDATE users SET balance=? WHERE id=?")->execute([$bal->fetchColumn(),$pid]);
          }
          createNotification($winnerId,'🏆 You Won!','Congratulations! You won the draw: '.$draw['title'],'draw');
          auditLog('admin',$adminId,'finalize_draw',"Draw $drawId finalized. Winner: user $winnerId, matched: ".$winner['matched'],'draw',$drawId);
          $db->commit();
          $msg="✅ Draw finalized! Winner: User #$winnerId with ".$winner['matched']." matching digits.";
          // Reload draw
          $stmt=$db->prepare("SELECT * FROM draws WHERE id=?");$stmt->execute([$drawId]);$draw=$stmt->fetch();
        } catch(\Exception $e) { $db->rollBack(); $err="Error: ".$e->getMessage(); }
      }
    }
  } elseif ($action==='reset_reveal') {
    $db->prepare("UPDATE draw_reveal SET revealed_digits='' WHERE draw_id=?")->execute([$drawId]);
    auditLog('admin',$adminId,'reset_reveal',"Draw $drawId reveal reset",'draw',$drawId);
    $msg="Reveal reset.";
  }
}

// Get current state
$rev=$db->prepare("SELECT * FROM draw_reveal WHERE draw_id=?");$rev->execute([$drawId]);$rev=$rev->fetch();
$revealed=$rev['revealed_digits']??'';
$entries=$db->prepare("SELECT COUNT(*) FROM draw_entries WHERE draw_id=?");$entries->execute([$drawId]);$entries=$entries->fetchColumn();
$participants=$db->prepare("SELECT COUNT(DISTINCT user_id) FROM draw_entries WHERE draw_id=?");$participants->execute([$drawId]);$participants=$participants->fetchColumn();
$topCodes=$db->prepare("SELECT de.*, u.full_name, c.code FROM draw_entries de JOIN users u ON de.user_id=u.id JOIN codes c ON de.code_id=c.id WHERE de.draw_id=? ORDER BY de.entered_at DESC LIMIT 10");
$topCodes->execute([$drawId]);$topCodes=$topCodes->fetchAll();

$aPage='draws';
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="csrf-token" content="<?= generateCsrf() ?>">
  <title>Live Draw — <?= APP_NAME ?> Admin</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
  <style>*{font-family:'Poppins',sans-serif!important}</style>
</head>
<body class="bg-[#0a0f1a] text-white">
<?php include __DIR__ . '/../components/admin-sidebar.php'; ?>
<div class="main-content">
  <div class="topbar">
    <button onclick="toggleSidebar()" class="md:hidden text-gray-400 text-2xl mr-3">☰</button>
    <div class="flex items-center gap-2"><span class="pulse-dot"></span><h1 class="text-lg font-bold">Live Draw Control — <?= e($draw['title']) ?></h1></div>
    <a href="<?= APP_URL ?>/admin/draws.php" class="btn btn-secondary btn-sm">← Draws</a>
  </div>
  <div class="p-6 max-w-4xl mx-auto">
    <?php if($msg): ?><div class="bg-green-500/10 border border-green-500/30 text-green-400 rounded-xl p-4 mb-5"><?= e($msg) ?></div><?php endif; ?>
    <?php if($err): ?><div class="bg-red-500/10 border border-red-500/30 text-red-400 rounded-xl p-4 mb-5"><?= e($err) ?></div><?php endif; ?>

    <div class="grid md:grid-cols-2 gap-6 mb-6">
      <!-- Stats -->
      <div class="card p-5 grid grid-cols-2 gap-4">
        <div class="text-center"><div class="text-2xl font-black text-orange-400"><?= $entries ?></div><div class="text-xs text-gray-400">Total Entries</div></div>
        <div class="text-center"><div class="text-2xl font-black text-blue-400"><?= $participants ?></div><div class="text-xs text-gray-400">Participants</div></div>
        <div class="text-center"><div class="text-2xl font-black text-green-400"><?= strlen($revealed) ?>/15</div><div class="text-xs text-gray-400">Digits Revealed</div></div>
        <div class="text-center"><div class="text-2xl font-black <?= $draw['status']==='completed'?'text-yellow-400':'text-gray-400' ?>"><?= ucfirst($draw['status']) ?></div><div class="text-xs text-gray-400">Status</div></div>
      </div>

      <!-- Live digit display -->
      <div class="card-glow p-5">
        <div class="text-sm text-gray-400 mb-3 font-medium">Revealed Winning Code</div>
        <div class="flex gap-1 flex-wrap justify-center">
          <?php for($i=0;$i<15;$i++): ?>
          <div class="digit-slot <?= $i<strlen($revealed)?'revealed':'' ?>" id="admin-slot-<?= $i ?>">
            <?= $i<strlen($revealed)?$revealed[$i]:'?' ?>
          </div>
          <?php endfor; ?>
        </div>
        <?php if($draw['winning_code']): ?>
        <div class="mt-3 text-center text-sm text-gray-400">Full code: <span class="font-mono text-orange-400"><?= e($draw['winning_code']) ?></span></div>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($draw['status']!=='completed'): ?>
    <div class="grid md:grid-cols-2 gap-6 mb-6">
      <!-- Digit-by-digit reveal -->
      <div class="card p-6">
        <h2 class="font-bold mb-4">🎯 Reveal Next Digit</h2>
        <form method="POST" class="flex gap-3">
          <?= csrfField() ?><input type="hidden" name="action" value="reveal_digit">
          <input type="text" name="digit" class="form-control text-center text-2xl font-mono w-20" maxlength="1" inputmode="numeric" placeholder="0" required>
          <button type="submit" class="btn btn-primary flex-1" <?= strlen($revealed)>=15?'disabled':'' ?>>Reveal →</button>
        </form>
        <form method="POST" class="mt-3">
          <?= csrfField() ?><input type="hidden" name="action" value="reset_reveal">
          <button type="submit" class="btn btn-secondary btn-sm w-full text-red-400" onclick="return confirm('Reset all revealed digits?')">↺ Reset Reveal</button>
        </form>
      </div>

      <!-- Set full winning code -->
      <div class="card p-6">
        <h2 class="font-bold mb-4">🏆 Set Winning Code</h2>
        <form method="POST">
          <?= csrfField() ?><input type="hidden" name="action" value="set_winning_code">
          <div class="form-group">
            <input type="text" name="winning_code" class="form-control font-mono tracking-widest text-center" maxlength="15" placeholder="000000000000000" inputmode="numeric" value="<?= e($draw['winning_code']??'') ?>" required>
          </div>
          <button type="submit" class="btn btn-primary w-full">Set Winning Code</button>
        </form>
      </div>
    </div>

    <!-- Finalize -->
    <?php if ($draw['winning_code']): ?>
    <div class="card p-6 border-yellow-500/30 mb-6">
      <h2 class="font-bold text-yellow-400 mb-2">⚡ Finalize Draw</h2>
      <p class="text-gray-400 text-sm mb-4">This will run the winner selection engine, announce the winner, consume all entries, and mark the draw as completed. This action is <strong class="text-red-400">irreversible</strong>.</p>
      <form method="POST" onsubmit="return confirm('FINALIZE DRAW? This cannot be undone. All entered codes will be consumed.')">
        <?= csrfField() ?><input type="hidden" name="action" value="finalize">
        <button type="submit" class="btn btn-primary w-full py-4 text-base font-bold">🏆 Run Winner Engine &amp; Finalize Draw</button>
      </form>
    </div>
    <?php endif; ?>
    <?php else: ?>
    <!-- Winner display -->
    <?php $winner=$db->prepare("SELECT dw.*,u.full_name,u.phone FROM draw_winners dw JOIN users u ON dw.user_id=u.id WHERE dw.draw_id=?");$winner->execute([$drawId]);$winner=$winner->fetch(); ?>
    <?php if($winner): ?>
    <div class="card p-8 text-center border-yellow-500/30" style="background:linear-gradient(135deg,rgba(234,179,8,0.1),rgba(0,0,0,0))">
      <div class="text-6xl mb-4">🏆</div>
      <div class="text-2xl font-black text-yellow-400 mb-2">WINNER ANNOUNCED</div>
      <div class="text-xl font-bold mb-1"><?= e($winner['full_name']) ?></div>
      <div class="text-gray-400 mb-3"><?= e(formatPhone($winner['phone'])) ?></div>
      <div class="font-mono text-orange-400 text-2xl tracking-widest bg-black/20 rounded-xl p-4 mb-3"><?= e($winner['winning_code']) ?></div>
      <div class="text-sm text-gray-400">Matched <strong class="text-white"><?= $winner['matched_digits'] ?>/15</strong> digits · Tiebreaker: <?= e($winner['tiebreaker_used']??'N/A') ?></div>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <!-- Recent Entries -->
    <div class="card">
      <div class="p-4 border-b border-white/5 font-bold">Recent Entries</div>
      <div class="table-wrap">
        <table>
          <thead><tr><th>User</th><th>Code</th><th>Entered</th></tr></thead>
          <tbody>
            <?php foreach($topCodes as $e): ?>
            <tr>
              <td class="text-sm"><?= e($e['full_name']) ?></td>
              <td><span class="font-mono text-orange-400 text-sm tracking-wider"><?= e($e['code']) ?></span></td>
              <td class="text-xs text-gray-500"><?= date('M j, g:ia',strtotime($e['entered_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
</body></html>

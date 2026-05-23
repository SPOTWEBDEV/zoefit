<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
if (!isAjax() || !isPost()) jsonResponse(['error'=>'Bad request'],400);
$auth = requireUser(); $userId = $auth['id'];

$body = json_decode(file_get_contents('php://input'), true);
if (!verifyCsrf($body[CSRF_TOKEN_NAME]??'')) jsonResponse(['error'=>'Invalid CSRF token'],403);

$code = trim($body['code'] ?? '');
if (!preg_match('/^\d{15}$/', $code)) jsonResponse(['error'=>'Code must be exactly 15 digits'],400);

$db = getDB();
$db->beginTransaction();
try {
  // Lock and fetch code
  $stmt = $db->prepare("SELECT * FROM codes WHERE code=? FOR UPDATE");
  $stmt->execute([$code]);
  $row = $stmt->fetch();

  if (!$row) throw new Exception('Code not found.');
  if ($row['status'] !== 'distributed' && $row['status'] !== 'assigned') {
    $map = ['redeemed'=>'already redeemed','reserved'=>'currently in a draw','used'=>'already used','transferred'=>'already transferred'];
    throw new Exception('This code has been ' . ($map[$row['status']] ?? 'used') . '.');
  }
  if ($row['current_owner'] && $row['current_owner'] != $userId) throw new Exception('This code belongs to another user.');

  // Redeem
  $db->prepare("UPDATE codes SET status='redeemed', current_owner=?, redeemed_at=NOW() WHERE id=?")->execute([$userId,$row['id']]);
  $db->prepare("INSERT INTO code_redemptions (code_id,user_id) VALUES (?,?)")->execute([$row['id'],$userId]);
  $db->prepare("UPDATE users SET balance=balance+1 WHERE id=?")->execute([$userId]);
  $db->prepare("INSERT INTO transactions (user_id,type,category,amount,code_id,description) VALUES (?,?,?,?,?,?)")
     ->execute([$userId,'credit','redemption',1,$row['id'],'Code redeemed: '.$code]);
  createNotification($userId,'Code Redeemed','Code '.$code.' added to your wallet.','redemption');
  auditLog('user',$userId,'redeem_code','Code redeemed: '.$code,'code',$row['id']);
  $db->commit();
  jsonResponse(['success'=>true,'code'=>$code,'message'=>'Code redeemed successfully!']);
} catch (Exception $e) {
  $db->rollBack();
  jsonResponse(['error'=>$e->getMessage()],400);
}

<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
if (!isAjax()||!isPost()) jsonResponse(['error'=>'Bad request'],400);
$auth = requireVendor(); $vendorId = $auth['id'];
$body = json_decode(file_get_contents('php://input'),true);
if (!verifyCsrf($body[CSRF_TOKEN_NAME]??'')) jsonResponse(['error'=>'Invalid CSRF'],403);

$codeId = (int)($body['code_id']??0);
$userId = (int)($body['user_id']??0);
if (!$codeId||!$userId) jsonResponse(['error'=>'Missing fields'],400);

$db = getDB();
$db->beginTransaction();
try {
  $code = $db->prepare("SELECT * FROM codes WHERE id=? AND assigned_vendor=? AND status='assigned' FOR UPDATE");
  $code->execute([$codeId,$vendorId]); $code = $code->fetch();
  if (!$code) throw new Exception('Code not found or already distributed.');

  $user = $db->prepare("SELECT id,full_name,status FROM users WHERE id=?");
  $user->execute([$userId]); $user = $user->fetch();
  if (!$user||$user['status']!=='active') throw new Exception('User not found or inactive.');

  $db->prepare("UPDATE codes SET status='distributed', current_owner=?, assigned_at=NOW() WHERE id=?")->execute([$userId,$codeId]);
  $db->prepare("UPDATE vendors SET code_balance=code_balance-1 WHERE id=? AND code_balance>0")->execute([$vendorId]);
  // Also mark as redeemed so user can use it immediately
  $db->prepare("UPDATE codes SET status='redeemed', redeemed_at=NOW() WHERE id=?")->execute([$codeId]);
  $db->prepare("INSERT INTO code_redemptions (code_id,user_id,vendor_id) VALUES (?,?,?)")->execute([$codeId,$userId,$vendorId]);
  $db->prepare("UPDATE users SET balance=balance+1 WHERE id=?")->execute([$userId]);
  $db->prepare("INSERT INTO transactions (user_id,type,category,amount,code_id,description) VALUES (?,?,?,?,?,?)")
     ->execute([$userId,'credit','vendor_credit',1,$codeId,'Code credited by vendor']);
  createNotification($userId,'Code Received','A vendor has credited a raffle code to your wallet.','redemption');
  auditLog('vendor',$vendorId,'credit_user','Credited code '.$code['code'].' to user '.$userId,'code',$codeId);
  $db->commit();
  jsonResponse(['success'=>true,'code'=>$code['code'],'user'=>$user['full_name']]);
} catch (Exception $e) {
  $db->rollBack();
  jsonResponse(['error'=>$e->getMessage()],400);
}

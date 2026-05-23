<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
if (!isAjax()||!isPost()) jsonResponse(['error'=>'Bad request'],400);
$auth = requireUser(); $userId = $auth['id'];
$body = json_decode(file_get_contents('php://input'),true);
if (!verifyCsrf($body[CSRF_TOKEN_NAME]??'')) jsonResponse(['error'=>'Invalid CSRF token'],403);

$codeId  = (int)($body['code_id']??0);
$toUser  = (int)($body['to_user']??0);
$pin     = trim($body['pin']??'');

if (!$codeId||!$toUser||strlen($pin)!==4) jsonResponse(['error'=>'Missing required fields'],400);
if ($toUser===$userId) jsonResponse(['error'=>'Cannot transfer to yourself'],400);

$db = getDB();
// Verify PIN
$me = $db->prepare("SELECT transfer_pin FROM users WHERE id=?");
$me->execute([$userId]); $me=$me->fetch();
if (!$me['transfer_pin']) jsonResponse(['error'=>'You have not set a transfer PIN. Please set one in your profile.'],400);
if (!password_verify($pin,$me['transfer_pin'])) jsonResponse(['error'=>'Incorrect transfer PIN'],403);

$db->beginTransaction();
try {
  // Get code
  $stmt=$db->prepare("SELECT * FROM codes WHERE id=? FOR UPDATE");
  $stmt->execute([$codeId]); $code=$stmt->fetch();
  if (!$code) throw new Exception('Code not found.');
  if ($code['current_owner']!=$userId) throw new Exception('This code does not belong to you.');
  if ($code['status']!=='redeemed') throw new Exception('Only active codes can be transferred.');

  // Get recipient
  $recip=$db->prepare("SELECT id,full_name,status FROM users WHERE id=?");
  $recip->execute([$toUser]); $recip=$recip->fetch();
  if (!$recip) throw new Exception('Recipient not found.');
  if ($recip['status']!=='active') throw new Exception('Recipient account is not active.');

  // Transfer
  $db->prepare("UPDATE codes SET current_owner=?,status='redeemed' WHERE id=?")->execute([$toUser,$codeId]);
  $db->prepare("INSERT INTO code_transfers (code_id,from_user_id,to_user_id) VALUES (?,?,?)")->execute([$codeId,$userId,$toUser]);
  $db->prepare("UPDATE users SET balance=balance-1 WHERE id=?")->execute([$userId]);
  $db->prepare("UPDATE users SET balance=balance+1 WHERE id=?")->execute([$toUser]);
  $db->prepare("INSERT INTO transactions (user_id,type,category,amount,code_id,description) VALUES (?,?,?,?,?,?)")
     ->execute([$userId,'debit','transfer_out',1,$codeId,'Transfer to: '.$recip['full_name']]);
  $db->prepare("INSERT INTO transactions (user_id,type,category,amount,code_id,description) VALUES (?,?,?,?,?,?)")
     ->execute([$toUser,'credit','transfer_in',1,$codeId,'Transfer from: '.$_SESSION['user_name']]);
  createNotification($toUser,'Code Received','You received a code from '.$_SESSION['user_name'].'.','transfer');
  auditLog('user',$userId,'transfer_code','Code '.$code['code'].' transferred to user '.$toUser,'code',$codeId);
  $db->commit();
  jsonResponse(['success'=>true,'code'=>$code['code'],'to'=>$recip['full_name']]);
} catch (Exception $e) {
  $db->rollBack();
  jsonResponse(['error'=>$e->getMessage()],400);
}

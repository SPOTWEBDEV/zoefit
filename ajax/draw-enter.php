<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
if (!isAjax()||!isPost()) jsonResponse(['error'=>'Bad request'],400);
$auth = requireUser(); $userId = $auth['id'];
$body = json_decode(file_get_contents('php://input'),true);
if (!verifyCsrf($body[CSRF_TOKEN_NAME]??'')) jsonResponse(['error'=>'Invalid CSRF token'],403);

$drawId  = (int)($body['draw_id']??0);
$codeIds = array_map('intval',(array)($body['code_ids']??[]));
if (!$drawId||!$codeIds) jsonResponse(['error'=>'Missing data'],400);

$db = getDB();
$draw=$db->prepare("SELECT * FROM draws WHERE id=? AND status='active'");
$draw->execute([$drawId]);$draw=$draw->fetch();
if (!$draw) jsonResponse(['error'=>'Draw not found or not active'],400);
if (new DateTime() > new DateTime($draw['end_date'])) jsonResponse(['error'=>'Draw has ended'],400);

$db->beginTransaction();
try {
  $entered=0;
  foreach ($codeIds as $cid) {
    $code=$db->prepare("SELECT * FROM codes WHERE id=? AND current_owner=? AND status='redeemed' FOR UPDATE");
    $code->execute([$cid,$userId]);$code=$code->fetch();
    if (!$code) continue;
    // Check not already in this draw
    $dup=$db->prepare("SELECT id FROM draw_entries WHERE draw_id=? AND code_id=?");
    $dup->execute([$drawId,$cid]);
    if ($dup->fetch()) continue;
    $db->prepare("INSERT INTO draw_entries (draw_id,user_id,code_id) VALUES (?,?,?)")->execute([$drawId,$userId,$cid]);
    $db->prepare("UPDATE codes SET status='reserved' WHERE id=?")->execute([$cid]);
    $db->prepare("INSERT INTO transactions (user_id,type,category,amount,code_id,description) VALUES (?,?,?,?,?,?)")
       ->execute([$userId,'debit','draw_entry',1,$cid,'Draw entry: '.$draw['title']]);
    $db->prepare("UPDATE users SET balance=balance-1 WHERE id=?")->execute([$userId]);
    $entered++;
  }
  if (!$entered) throw new Exception('No valid codes to enter.');
  createNotification($userId,'Draw Entry','Entered '.$entered.' code(s) into: '.$draw['title'],'draw');
  auditLog('user',$userId,'draw_entry','Entered '.$entered.' codes in draw '.$drawId,'draw',$drawId);
  $db->commit();
  jsonResponse(['success'=>true,'entered'=>$entered]);
} catch (Exception $e) {
  $db->rollBack();
  jsonResponse(['error'=>$e->getMessage()],400);
}

<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
if (!isAjax()||!isPost()) jsonResponse(['error'=>'Bad request'],400);
$auth = requireAdmin(); $adminId = $auth['id'];
$body = json_decode(file_get_contents('php://input'),true);
if (!verifyCsrf($body[CSRF_TOKEN_NAME]??'')) jsonResponse(['error'=>'Invalid CSRF'],403);

$drawId = (int)($body['draw_id']??0);
$digit  = preg_replace('/\D/','',$body['digit']??'');

if (!$drawId||strlen($digit)!==1) jsonResponse(['error'=>'Invalid input'],400);

$db = getDB();
$draw = $db->prepare("SELECT * FROM draws WHERE id=? AND status='active'");
$draw->execute([$drawId]); $draw=$draw->fetch();
if (!$draw) jsonResponse(['error'=>'Draw not found or not active'],400);

$rev = $db->prepare("SELECT * FROM draw_reveal WHERE draw_id=?");
$rev->execute([$drawId]); $rev=$rev->fetch();

if (!$rev) {
  $db->prepare("INSERT INTO draw_reveal (draw_id,revealed_digits) VALUES (?,?)")->execute([$drawId,$digit]);
  $revealed=$digit;
} else {
  if (strlen($rev['revealed_digits'])>=15) jsonResponse(['error'=>'All 15 digits already revealed'],400);
  $revealed=$rev['revealed_digits'].$digit;
  $db->prepare("UPDATE draw_reveal SET revealed_digits=? WHERE draw_id=?")->execute([$revealed,$drawId]);
}

auditLog('admin',$adminId,'reveal_digit_ajax',"Draw $drawId: revealed '$digit'",'draw',$drawId);
jsonResponse(['success'=>true,'revealed'=>$revealed,'position'=>strlen($revealed)]);

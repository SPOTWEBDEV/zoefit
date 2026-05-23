<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
$drawId=(int)($_GET['draw_id']??0);
if (!$drawId) jsonResponse(['error'=>'Missing draw_id'],400);

$db=getDB();
$draw=$db->prepare("SELECT d.*, dr.revealed_digits FROM draws d LEFT JOIN draw_reveal dr ON dr.draw_id=d.id WHERE d.id=?");
$draw->execute([$drawId]);$draw=$draw->fetch();
if (!$draw) jsonResponse(['error'=>'Not found'],404);

$response=[
  'revealed'  => $draw['revealed_digits']??'',
  'status'    => $draw['status'],
  'finalized' => $draw['status']==='completed',
  'winner'    => null,
];

if ($draw['status']==='completed' && $draw['winner_user_id']) {
  // Get session if user is logged in
  startAppSession();
  $isMe = !empty($_SESSION['user_id']) && $_SESSION['user_id']==$draw['winner_user_id'];
  $w=$db->prepare("SELECT u.full_name FROM users u WHERE u.id=?");
  $w->execute([$draw['winner_user_id']]);$w=$w->fetch();
  $response['winner']=[
    'name'  => $isMe ? 'YOU' : ($w['full_name']??'Unknown'),
    'code'  => $draw['winning_code'],
    'is_me' => $isMe,
  ];
}
jsonResponse($response);

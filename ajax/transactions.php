<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
$auth=requireUser();$userId=$auth['id'];
$page=max(1,(int)($_GET['page']??1));
$limit=20;$offset=($page-1)*$limit;
$db=getDB();
$stmt=$db->prepare("SELECT t.*,c.code FROM transactions t LEFT JOIN codes c ON t.code_id=c.id WHERE t.user_id=? ORDER BY t.created_at DESC LIMIT $limit OFFSET $offset");
$stmt->execute([$userId]);$rows=$stmt->fetchAll();
$total=$db->prepare("SELECT COUNT(*) FROM transactions WHERE user_id=?");
$total->execute([$userId]);$total=$total->fetchColumn();
$items=array_map(fn($r)=>[
  'id'=>$r['id'],'type'=>$r['type'],'category'=>$r['category'],
  'amount'=>$r['amount'],'code'=>$r['code']??'','description'=>$r['description']??'',
  'date'=>date('M j, Y g:ia',strtotime($r['created_at']))
],$rows);
jsonResponse(['items'=>$items,'hasMore'=>($offset+$limit)<$total]);

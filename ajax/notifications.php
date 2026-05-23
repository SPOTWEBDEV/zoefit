<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
$auth=requireUser();$userId=$auth['id'];

// Unread count (for badge polling)
if (!isset($_GET['page_load'])) {
  $cnt=$db=getDB();
  $cnt=$db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0");
  $cnt->execute([$userId]);
  jsonResponse(['unread'=>(int)$cnt->fetchColumn()]);
}

// Full list with pagination
$page=max(1,(int)($_GET['page']??1));
$limit=20;$offset=($page-1)*$limit;
$db=getDB();
$stmt=$db->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT $limit OFFSET $offset");
$stmt->execute([$userId]);$rows=$stmt->fetchAll();
$total=$db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=?");
$total->execute([$userId]);$total=$total->fetchColumn();
$items=array_map(fn($r)=>[
  'id'=>$r['id'],'title'=>$r['title'],'message'=>$r['message'],
  'type'=>$r['type'],'is_read'=>(bool)$r['is_read'],
  'date'=>date('M j, Y g:ia',strtotime($r['created_at']))
],$rows);
jsonResponse(['items'=>$items,'hasMore'=>($offset+$limit)<$total,'unread'=>0]);

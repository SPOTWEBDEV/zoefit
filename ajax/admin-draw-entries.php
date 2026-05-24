<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
requireAdmin();
$drawId = (int)($_GET['draw_id']??0);
$page   = max(1,(int)($_GET['page']??1));
$limit  = 25; $offset=($page-1)*$limit;
$db = getDB();

$stmt = $db->prepare("SELECT de.entered_at, u.full_name, u.phone, c.code
  FROM draw_entries de JOIN users u ON de.user_id=u.id JOIN codes c ON de.code_id=c.id
  WHERE de.draw_id=? ORDER BY de.entered_at DESC LIMIT $limit OFFSET $offset");
$stmt->execute([$drawId]); $rows=$stmt->fetchAll();

$cnt=$db->prepare("SELECT COUNT(*) FROM draw_entries WHERE draw_id=?");
$cnt->execute([$drawId]); $total=$cnt->fetchColumn();

jsonResponse([
  'items' => array_map(fn($r)=>[
    'name'  => $r['full_name'],
    'phone' => formatPhone($r['phone']),
    'code'  => $r['code'],
    'time'  => date('M j, g:ia',strtotime($r['entered_at'])),
  ],$rows),
  'hasMore' => ($offset+$limit)<$total,
  'total'   => $total,
]);

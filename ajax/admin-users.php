<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
requireAdmin();
$q = trim($_GET['q']??'');
$page = max(1,(int)($_GET['page']??1));
$limit = 20; $offset = ($page-1)*$limit;
$db = getDB();

if (!$q) {
  $stmt = $db->prepare("SELECT id,full_name,phone,email,balance,status,created_at FROM users ORDER BY created_at DESC LIMIT $limit OFFSET $offset");
  $stmt->execute();
  $cnt = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
} else {
  $s = "%$q%";
  $stmt = $db->prepare("SELECT id,full_name,phone,email,balance,status,created_at FROM users WHERE full_name LIKE ? OR phone LIKE ? OR email LIKE ? ORDER BY created_at DESC LIMIT $limit OFFSET $offset");
  $stmt->execute([$s,$s,$s]);
  $cntS = $db->prepare("SELECT COUNT(*) FROM users WHERE full_name LIKE ? OR phone LIKE ? OR email LIKE ?");
  $cntS->execute([$s,$s,$s]); $cnt=$cntS->fetchColumn();
}

$rows = $stmt->fetchAll();
jsonResponse([
  'items' => array_map(fn($u)=>[
    'id'       => $u['id'],
    'name'     => $u['full_name'],
    'phone'    => formatPhone($u['phone']),
    'email'    => $u['email']??'',
    'balance'  => $u['balance'],
    'status'   => $u['status'],
    'joined'   => date('M j, Y',strtotime($u['created_at'])),
  ], $rows),
  'hasMore' => ($offset+$limit) < $cnt,
  'total'   => $cnt,
]);

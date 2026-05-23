<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
$auth = requireVendor(); $vendorId = $auth['id'];

$page   = max(1,(int)($_GET['page']??1));
$filter = $_GET['filter'] ?? 'all';
$limit  = (int)($_GET['limit'] ?? 20);
$offset = ($page-1)*$limit;

$db = getDB();
$where = "assigned_vendor=?"; $params = [$vendorId];
if ($filter !== 'all') { $where .= " AND status=?"; $params[] = $filter; }

$stmt = $db->prepare("SELECT id,code,status,assigned_at FROM codes WHERE $where ORDER BY assigned_at DESC LIMIT $limit OFFSET $offset");
$stmt->execute($params);
$rows = $stmt->fetchAll();

$cnt = $db->prepare("SELECT COUNT(*) FROM codes WHERE $where"); $cnt->execute($params);
$total = $cnt->fetchColumn();

jsonResponse([
  'items'   => array_map(fn($r) => [
    'id'   => $r['id'], 'code' => $r['code'], 'status' => $r['status'],
    'date' => $r['assigned_at'] ? date('M j, Y', strtotime($r['assigned_at'])) : '—',
  ], $rows),
  'hasMore' => ($offset + $limit) < $total,
]);

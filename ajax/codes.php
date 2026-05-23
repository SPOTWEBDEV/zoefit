<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
$auth = requireUser();
$userId = $auth['id'];

$page   = max(1,(int)($_GET['page']??1));
$filter = $_GET['filter'] ?? 'all';
$limit  = (int)($_GET['limit'] ?? CODES_PER_PAGE);
$offset = ($page-1)*$limit;

$db = getDB();
$allowed = ['all','redeemed','reserved','used','transferred'];
if (!in_array($filter,$allowed)) $filter='all';

$where = "c.current_owner=?";
$params = [$userId];
if ($filter !== 'all') { $where .= " AND c.status=?"; $params[] = $filter; }

$stmt = $db->prepare("SELECT c.id, c.code, c.status, c.redeemed_at as date_added FROM codes c WHERE $where ORDER BY c.redeemed_at DESC LIMIT $limit OFFSET $offset");
$stmt->execute($params);
$codes = $stmt->fetchAll();

$countStmt = $db->prepare("SELECT COUNT(*) FROM codes c WHERE $where");
$countStmt->execute($params);
$total = $countStmt->fetchColumn();

$items = array_map(function($c) {
  return [
    'id'         => $c['id'],
    'code'       => $c['code'],
    'status'     => $c['status'],
    'date_added' => $c['date_added'] ? date('M j, Y', strtotime($c['date_added'])) : '—',
  ];
}, $codes);

jsonResponse([
  'items'   => $items,
  'hasMore' => ($offset + $limit) < $total,
  'total'   => $total,
]);

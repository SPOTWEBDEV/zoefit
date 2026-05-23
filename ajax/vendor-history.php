<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
$auth = requireVendor(); $vendorId = $auth['id'];
$page  = max(1,(int)($_GET['page']??1));
$tab   = $_GET['tab'] ?? 'distribution';
$limit = 20; $offset = ($page-1)*$limit;
$db = getDB();

if ($tab === 'redemption') {
  $stmt = $db->prepare("SELECT r.redeemed_at as date, u.full_name as user_name, u.phone, c.code
    FROM code_redemptions r JOIN users u ON r.user_id=u.id JOIN codes c ON r.code_id=c.id
    WHERE c.assigned_vendor=? ORDER BY r.redeemed_at DESC LIMIT $limit OFFSET $offset");
  $stmt->execute([$vendorId]);
  $cnt = $db->prepare("SELECT COUNT(*) FROM code_redemptions r JOIN codes c ON r.code_id=c.id WHERE c.assigned_vendor=?");
  $cnt->execute([$vendorId]);
} else {
  $stmt = $db->prepare("SELECT c.assigned_at as date, u.full_name as user_name, u.phone, c.code
    FROM codes c LEFT JOIN users u ON c.current_owner=u.id
    WHERE c.assigned_vendor=? AND c.status NOT IN ('assigned','unassigned')
    ORDER BY c.assigned_at DESC LIMIT $limit OFFSET $offset");
  $stmt->execute([$vendorId]);
  $cnt = $db->prepare("SELECT COUNT(*) FROM codes WHERE assigned_vendor=? AND status NOT IN ('assigned','unassigned')");
  $cnt->execute([$vendorId]);
}

$rows = $stmt->fetchAll();
$total = $cnt->fetchColumn();

jsonResponse([
  'items' => array_map(fn($r)=>[
    'user_name' => $r['user_name']??'Unknown',
    'phone'     => isset($r['phone']) ? formatPhone($r['phone']) : '—',
    'code'      => $r['code'],
    'date'      => $r['date'] ? date('M j, Y g:ia', strtotime($r['date'])) : '—',
  ], $rows),
  'hasMore' => ($offset+$limit) < $total,
]);

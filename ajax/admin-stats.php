<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
requireAdmin();
$db = getDB();
$type = $_GET['type']??'overview';

if ($type==='overview') {
  jsonResponse([
    'users'        => (int)$db->query("SELECT COUNT(*) FROM users")->fetchColumn(),
    'active_users' => (int)$db->query("SELECT COUNT(*) FROM users WHERE status='active'")->fetchColumn(),
    'codes_total'  => (int)$db->query("SELECT COUNT(*) FROM codes")->fetchColumn(),
    'codes_unused' => (int)$db->query("SELECT COUNT(*) FROM codes WHERE status='unassigned'")->fetchColumn(),
    'draws_active' => (int)$db->query("SELECT COUNT(*) FROM draws WHERE status='active'")->fetchColumn(),
    'entries_today'=> (int)$db->query("SELECT COUNT(*) FROM draw_entries WHERE DATE(entered_at)=CURDATE()")->fetchColumn(),
    'redemptions_today'=>(int)$db->query("SELECT COUNT(*) FROM code_redemptions WHERE DATE(redeemed_at)=CURDATE()")->fetchColumn(),
  ]);
} elseif ($type==='users_chart') {
  // Users registered per day last 7 days
  $rows=$db->query("SELECT DATE(created_at) as d, COUNT(*) as c FROM users WHERE created_at>=DATE_SUB(NOW(),INTERVAL 7 DAY) GROUP BY DATE(created_at) ORDER BY d")->fetchAll();
  jsonResponse(['data'=>$rows]);
} elseif ($type==='code_status') {
  $rows=$db->query("SELECT status, COUNT(*) as cnt FROM codes GROUP BY status")->fetchAll();
  jsonResponse(['data'=>$rows]);
}

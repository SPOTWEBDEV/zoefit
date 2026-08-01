<?php
// ajax/my-generated-codes.php
// Returns the logged-in user's own self-generated codes that haven't been
// redeemed yet (status still 'assigned'), so user/redeem-code.php can list
// them each with a one-click Redeem button. Also returns today's usage
// against the daily cap so the UI can show "N of 2 left today" and disable
// the Generate button once the limit is hit.
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

// Keep in sync with ajax/generate-code.php
const SELF_GEN_DAILY_LIMIT = 2;

$auth   = requireUser();
$userId = (int)$auth['id'];
$db     = getDB();

$prefix = 'SELFGEN-' . $userId . '-';

$stmt = $db->prepare(
    "SELECT code, generated_at FROM codes
     WHERE batch_id LIKE ? AND status = 'assigned'
     ORDER BY generated_at DESC"
);
$stmt->execute([$prefix . '%']);
$rows = $stmt->fetchAll();

$countStmt = $db->prepare(
    "SELECT COUNT(*) FROM codes WHERE batch_id LIKE ? AND DATE(generated_at) = CURDATE()"
);
$countStmt->execute([$prefix . '%']);
$usedToday = (int)$countStmt->fetchColumn();

echo json_encode([
    'success'    => true,
    'codes'      => $rows,
    'used_today' => $usedToday,
    'limit'      => SELF_GEN_DAILY_LIMIT,
    'remaining'  => max(0, SELF_GEN_DAILY_LIMIT - $usedToday),
]);

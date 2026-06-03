<?php
// ajax/redeem.php
// Correction: A code does NOT need to be assigned to a vendor to be redeemed.
// Any code with status 'unassigned', 'assigned', or 'distributed' can be redeemed
// by ANY user who enters the correct 15-digit code.
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
if (!isAjax() || !isPost()) jsonResponse(['error'=>'Bad request'], 400);
$auth   = requireUser(); $userId = $auth['id'];
$body   = json_decode(file_get_contents('php://input'), true);
if (!verifyCsrf($body[CSRF_TOKEN_NAME]??'')) jsonResponse(['error'=>'Invalid request'], 403);

$code = trim($body['code'] ?? '');
if (!preg_match('/^\d{15}$/', $code)) jsonResponse(['error'=>'Code must be exactly 15 digits.'], 400);

$db = getDB();
$db->beginTransaction();
try {
  // Lock the row for update (prevents race conditions)
  $stmt = $db->prepare("SELECT * FROM codes WHERE code=? FOR UPDATE");
  $stmt->execute([$code]);
  $row = $stmt->fetch();

  if (!$row) {
    throw new \Exception('Code not found. Please check and try again.');
  }

  // ── Statuses that are redeemable ─────────────────────────
  // unassigned = never assigned to any vendor — still valid
  // assigned   = assigned to a vendor but not yet distributed — still valid
  // distributed = vendor gave it out — valid
  // All other statuses are NOT redeemable
  $redeemable = ['unassigned', 'assigned', 'distributed'];

  if (!in_array($row['status'], $redeemable)) {
    $reasons = [
      'redeemed'    => 'already been redeemed.',
      'reserved'    => 'currently entered in a draw and cannot be redeemed again.',
      'used'        => 'already been used in a completed draw.',
      'transferred' => 'been transferred to another account.',
    ];
    $reason = $reasons[$row['status']] ?? 'not available for redemption.';
    throw new \Exception("This code has $reason");
  }

  // If code already has an owner that is NOT this user, block it
  if ($row['current_owner'] && $row['current_owner'] != $userId) {
    throw new \Exception('This code is already linked to another account.');
  }

  // ── Redeem ────────────────────────────────────────────────
  $db->prepare(
    "UPDATE codes SET status='redeemed', current_owner=?, redeemed_at=NOW() WHERE id=?"
  )->execute([$userId, $row['id']]);

  $db->prepare(
    "INSERT INTO code_redemptions (code_id, user_id, vendor_id) VALUES (?,?,?)"
  )->execute([$row['id'], $userId, $row['assigned_vendor'] ?? null]);

  $db->prepare(
    "UPDATE users SET balance=balance+1 WHERE id=?"
  )->execute([$userId]);

  $db->prepare(
    "INSERT INTO transactions (user_id,type,category,amount,code_id,description)
     VALUES (?,?,?,?,?,?)"
  )->execute([$userId, 'credit', 'redemption', 1, $row['id'], 'Code redeemed: '.$code]);

  // If this was a vendor's code, reduce their inventory count
  if ($row['assigned_vendor']) {
    $db->prepare(
      "UPDATE users SET vendor_code_balance=GREATEST(0,vendor_code_balance-1) WHERE id=?"
    )->execute([$row['assigned_vendor']]);
  }

  createNotification($userId, '🎟️ Code Redeemed',
    'Code '.$code.' has been added to your wallet successfully.', 'redemption');

  auditLog('user', $userId, 'redeem_code',
    'Code redeemed: '.$code.' (was: '.$row['status'].')', 'code', $row['id']);

  $db->commit();
  jsonResponse(['success'=>true, 'code'=>$code, 'message'=>'Code redeemed successfully!']);

} catch (\Exception $e) {
  $db->rollBack();
  jsonResponse(['error'=>$e->getMessage()], 400);
}
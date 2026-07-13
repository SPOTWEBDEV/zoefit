<?php
// ajax/redeem.php
// Fixed: vendor inventory now deducted from vendors.code_balance
//        (not users.vendor_code_balance which no longer exists).
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

if (!isAjax() || !isPost()) jsonResponse(['error' => 'Bad request'], 400);

$auth   = requireUser();
$userId = $auth['id'];

$body = json_decode(file_get_contents('php://input'), true);
if (!verifyCsrf($body[CSRF_TOKEN_NAME] ?? '')) {
    jsonResponse(['error' => 'Invalid request'], 403);
}

$code = trim($body['code'] ?? '');
if (!preg_match('/^\d{15}$/', $code)) {
    jsonResponse(['error' => 'Code must be exactly 15 digits.'], 400);
}

$db = getDB();
$db->beginTransaction();

try {
    // Lock the row — prevents two users redeeming the same code simultaneously
    $stmt = $db->prepare("SELECT * FROM codes WHERE code = ? FOR UPDATE");
    $stmt->execute([$code]);
    $row = $stmt->fetch();

    if (!$row) {
        throw new \Exception('Code not found. Please check and try again.');
    }

    // ── Statuses that are redeemable ──────────────────────
    // unassigned  = generated but not assigned to any vendor — still valid
    // assigned    = assigned to a vendor, not yet distributed — still valid
    // distributed = vendor gave it to a customer — valid
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

    // Block if already owned by a different user
    if ($row['current_owner'] && (int)$row['current_owner'] !== $userId) {
        throw new \Exception('This code is already linked to another account.');
    }

    // ── Redeem the code ───────────────────────────────────
    $db->prepare(
        "UPDATE codes
         SET status='redeemed', current_owner=?, redeemed_at=NOW()
         WHERE id=?"
    )->execute([$userId, $row['id']]);

    // ── Record redemption ─────────────────────────────────
    // vendor_id references vendors.id (NULL if no vendor was assigned)
    $db->prepare(
        "INSERT INTO code_redemptions (code_id, user_id, vendor_id)
         VALUES (?, ?, ?)"
    )->execute([
        $row['id'],
        $userId,
        $row['assigned_vendor'] ?? null,  // vendors.id or NULL
    ]);

    // ── Credit user balance ───────────────────────────────
    $db->prepare(
        "UPDATE users SET balance = balance + 1 WHERE id = ?"
    )->execute([$userId]);

    // ── Transaction log ───────────────────────────────────
    $db->prepare(
        "INSERT INTO transactions
           (user_id, type, category, amount, code_id, description)
         VALUES (?, 'credit', 'redemption', 1, ?, ?)"
    )->execute([
        $userId,
        $row['id'],
        'Code redeemed: ' . $code,
    ]);

    // ── Deduct from vendor inventory ──────────────────────
    // FIX: update vendors.code_balance, NOT users.vendor_code_balance
    if (!empty($row['assigned_vendor'])) {
        $db->prepare(
            "UPDATE vendors
             SET code_balance = GREATEST(0, code_balance - 1),
                 updated_at   = NOW()
             WHERE id = ?"
        )->execute([$row['assigned_vendor']]);
    }

    // ── In-app notification ───────────────────────────────
    createNotification(
        $userId,
        '🎟️ Code Redeemed',
        'Code ' . $code . ' has been added to your wallet successfully.',
        'redemption'
    );

    // ── Audit log ─────────────────────────────────────────
    auditLog(
        'user', $userId,
        'redeem_code',
        'Code redeemed: ' . $code . ' (was: ' . $row['status'] . ')',
        'code', $row['id']
    );

    $db->commit();

    jsonResponse([
        'success' => true,
        'code'    => $code,
        'message' => 'Code redeemed successfully!',
    ]);

} catch (\Exception $e) {
    $db->rollBack();
    jsonResponse(['error' => $e->getMessage()], 400);
}
<?php
// ajax/generate-code.php
// Lets a logged-in user self-generate a fresh 15-digit raffle code, capped
// at SELF_GEN_DAILY_LIMIT per calendar day. Each generated code behaves
// exactly like a normal vendor-distributed code (status='assigned', not
// yet owned/redeemed by anyone) so the existing ajax/redeem.php flow
// redeems it exactly the same way a code from a vendor would be redeemed —
// no changes needed there. Ownership for "whose self-generated codes are
// these" is tracked via a "SELFGEN-{userId}-..." batch_id prefix rather
// than a schema change, so no migration is required.
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: application/json');

const SELF_GEN_DAILY_LIMIT     = 2;
const SELF_GEN_SYSTEM_ADMIN_ID = 1; // attributed as codes.generated_by — that column has no FK constraint, any valid admin id works here

$auth   = requireUser();
$userId = (int)$auth['id'];
$db     = getDB();

try {
    $prefix = 'SELFGEN-' . $userId . '-';

    $countStmt = $db->prepare(
        "SELECT COUNT(*) FROM codes WHERE batch_id LIKE ? AND DATE(generated_at) = CURDATE()"
    );
    $countStmt->execute([$prefix . '%']);
    $usedToday = (int)$countStmt->fetchColumn();

    if ($usedToday >= SELF_GEN_DAILY_LIMIT) {
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'message' => 'You\'ve reached today\'s limit of ' . SELF_GEN_DAILY_LIMIT . ' self-generated codes. Come back tomorrow!',
        ]);
        exit;
    }

    // Generate a unique, unused 15-digit numeric code
    do {
        $code = '';
        for ($i = 0; $i < 15; $i++) $code .= random_int(0, 9);
        $dup = $db->prepare("SELECT COUNT(*) FROM codes WHERE code=?");
        $dup->execute([$code]);
    } while ((int)$dup->fetchColumn() > 0);

    $batchId = $prefix . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));

    $db->prepare(
        "INSERT INTO codes (code, status, generated_by, batch_id, generated_at)
         VALUES (?, 'assigned', ?, ?, NOW())"
    )->execute([$code, SELF_GEN_SYSTEM_ADMIN_ID, $batchId]);
    $codeId = (int)$db->lastInsertId();

    if (function_exists('createNotification')) {
        createNotification(
            $userId,
            '🎲 Self Generated Code',
            "You generated a new raffle code: $code. Redeem it any time from the Redeem Code page.",
            'info'
        );
    }

    auditLog(
        'user', $userId, 'self_generate_code',
        "Self Generated Code — user #$userId generated code $code",
        'code', $codeId
    );

    echo json_encode([
        'success'   => true,
        'code'      => $code,
        'used'      => $usedToday + 1,
        'limit'     => SELF_GEN_DAILY_LIMIT,
        'remaining' => SELF_GEN_DAILY_LIMIT - ($usedToday + 1),
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not generate a code right now. Please try again.']);
}

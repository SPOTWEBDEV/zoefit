<?php
/**
 * includes/referral.php
 *
 * Referral program logic. Requires sql/referral_migration.sql to have been
 * run first (adds users.referral_code, users.referred_by, referral_rewards).
 *
 * INTEGRATION POINTS (existing files this project doesn't currently expose
 * to me, so wire these in manually):
 *
 *   1. user/register.php — after a new user row is inserted:
 *        require_once __DIR__ . '/../includes/referral.php';
 *        $newUserId = $db->lastInsertId();
 *        assignReferralCode($db, $newUserId);
 *        if (!empty($_POST['ref'])) {
 *            attachReferrer($db, $newUserId, $_POST['ref']);
 *        }
 *      And on the registration FORM, read a `?ref=CODE` query param into a
 *      hidden <input name="ref"> field so it survives the POST.
 *
 *   2. user/redeem-code.php (or wherever a code status flips to 'redeemed'
 *      for the FIRST time for a given user) — right after a successful
 *      redemption:
 *        require_once __DIR__ . '/../includes/referral.php';
 *        maybeGrantReferralReward($db, $userId, $adminIdForCodeGen = 1);
 *
 * Both functions are idempotent / no-ops if not applicable, so they're safe
 * to call defensively even if the conditions aren't met.
 */

/**
 * Generate and store a unique referral code for a user (e.g. "ZF7K3PQ2").
 * No-op if the user already has one.
 */
function assignReferralCode(PDO $db, int $userId): string
{
    $existing = $db->prepare("SELECT referral_code FROM users WHERE id=?");
    $existing->execute([$userId]);
    $code = $existing->fetchColumn();
    if ($code) return $code;

    do {
        $code = 'ZF' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
        $check = $db->prepare("SELECT COUNT(*) FROM users WHERE referral_code=?");
        $check->execute([$code]);
    } while ((int)$check->fetchColumn() > 0);

    $db->prepare("UPDATE users SET referral_code=? WHERE id=?")->execute([$code, $userId]);
    return $code;
}

/**
 * One-time backfill for users created before the referral system existed.
 * Safe to run repeatedly — only fills NULLs.
 */
function backfillMissingReferralCodes(PDO $db): int
{
    $stmt = $db->query("SELECT id FROM users WHERE referral_code IS NULL");
    $count = 0;
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $uid) {
        assignReferralCode($db, (int)$uid);
        $count++;
    }
    return $count;
}

/**
 * Record who referred a brand-new user, by referral code. Call this once,
 * right after the new user's row is inserted at registration.
 * Silently ignores self-referral, unknown codes, and users who already
 * have a referrer set.
 */
function attachReferrer(PDO $db, int $newUserId, string $referralCode): bool
{
    $referralCode = trim($referralCode);
    if ($referralCode === '') return false;

    $ref = $db->prepare("SELECT id FROM users WHERE referral_code = ?");
    $ref->execute([$referralCode]);
    $referrerId = (int)$ref->fetchColumn();

    if (!$referrerId || $referrerId === $newUserId) return false;

    $already = $db->prepare("SELECT referred_by FROM users WHERE id=?");
    $already->execute([$newUserId]);
    if ($already->fetchColumn()) return false; // already has a referrer

    $db->prepare("UPDATE users SET referred_by=? WHERE id=?")->execute([$referrerId, $newUserId]);

    // Row starts as 'pending' — the reward itself is only granted once the
    // referred user actually redeems a code (see maybeGrantReferralReward),
    // to discourage fake/empty signups from paying out free codes.
    $db->prepare(
        "INSERT INTO referral_rewards (referrer_id, referred_user_id, status)
         VALUES (?, ?, 'pending')
         ON DUPLICATE KEY UPDATE referrer_id = VALUES(referrer_id)"
    )->execute([$referrerId, $newUserId]);

    auditLog('user', $newUserId, 'referral_signup',
        "Signed up using referral code $referralCode (referrer #$referrerId)",
        'user', $newUserId);

    return true;
}

/**
 * Call this right after a user's redemption is recorded. If this user was
 * referred by someone AND hasn't already triggered a reward, generate one
 * free 15-digit code for the referrer, credit it to their wallet, and log
 * it to audit_logs.
 *
 * @param PDO $db
 * @param int $redeemedByUserId  The user who just redeemed a code
 * @param int $systemAdminId     admin id to attribute the generated code to
 *                                (codes.generated_by is NOT NULL in the schema)
 */
function maybeGrantReferralReward(PDO $db, int $redeemedByUserId, int $systemAdminId = 1): void
{
    $row = $db->prepare(
        "SELECT id, referrer_id FROM referral_rewards
         WHERE referred_user_id = ? AND status = 'pending'"
    );
    $row->execute([$redeemedByUserId]);
    $reward = $row->fetch();
    if (!$reward) return; // not referred, or already granted

    $referrerId = (int)$reward['referrer_id'];

    // Generate a fresh, unique 15-digit numeric code and hand it straight
    // to the referrer's wallet as a reward.
    do {
        $newCode = '';
        for ($i = 0; $i < 15; $i++) $newCode .= random_int(0, 9);
        $dup = $db->prepare("SELECT COUNT(*) FROM codes WHERE code=?");
        $dup->execute([$newCode]);
    } while ((int)$dup->fetchColumn() > 0);

    $batchId = 'REFERRAL-' . date('YmdHis') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));

    $db->prepare(
        "INSERT INTO codes (code, status, generated_by, current_owner, batch_id, generated_at, assigned_at, redeemed_at)
         VALUES (?, 'redeemed', ?, ?, ?, NOW(), NOW(), NOW())"
    )->execute([$newCode, $systemAdminId, $referrerId, $batchId]);
    $newCodeId = (int)$db->lastInsertId();

    $db->prepare("UPDATE users SET balance = balance + 1 WHERE id = ?")->execute([$referrerId]);

    $db->prepare(
        "UPDATE referral_rewards SET status='granted', reward_code_id=?, granted_at=NOW() WHERE id=?"
    )->execute([$newCodeId, $reward['id']]);

    $db->prepare(
        "INSERT INTO transactions (user_id, type, category, amount, code_id, description) VALUES (?,?,?,?,?,?)"
    )->execute([$referrerId, 'credit', 'vendor_credit', 1, $newCodeId, "Referral reward — code $newCode"]);

    if (function_exists('createNotification')) {
        createNotification($referrerId, '🎁 Referral Reward!',
            "Your referral just redeemed their first code — you've earned a free raffle code ($newCode)!",
            'redemption');
    }

    auditLog('system', $referrerId, 'referral_reward',
        "Referral reward granted: user #$referrerId received code $newCode for referring user #$redeemedByUserId",
        'code', $newCodeId);
}

/**
 * Data for the referral dashboard: this user's code/link + everyone
 * they've referred (name, joined date, reward status).
 */
function getReferralDashboardData(PDO $db, int $userId): array
{
    $code = $db->prepare("SELECT referral_code FROM users WHERE id=?");
    $code->execute([$userId]);
    $referralCode = $code->fetchColumn();
    if (!$referralCode) {
        $referralCode = assignReferralCode($db, $userId);
    }

    $stmt = $db->prepare(
        "SELECT u.full_name, u.created_at AS joined_at,
                rr.status, rr.granted_at
         FROM users u
         JOIN referral_rewards rr ON rr.referred_user_id = u.id
         WHERE rr.referrer_id = ?
         ORDER BY u.created_at DESC"
    );
    $stmt->execute([$userId]);
    $referred = $stmt->fetchAll();

    $granted = array_filter($referred, fn($r) => $r['status'] === 'granted');

    return [
        'referral_code'    => $referralCode,
        'referred'         => $referred,
        'total_referred'   => count($referred),
        'total_rewards'    => count($granted),
    ];
}

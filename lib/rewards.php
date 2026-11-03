<?php
require_once __DIR__ . '/settings.php';

if (!defined('REWARD_MAX_CODES_PER_ORDER')) define('REWARD_MAX_CODES_PER_ORDER', 500);
if (!defined('REWARD_SYSTEM_ADMIN_ID'))     define('REWARD_SYSTEM_ADMIN_ID', 1);       // codes.generated_by (no FK), same as generate-code.php

function rewardUnitKobo(): int {
    return getRewardNairaPerCode() * 100;
}

/**
 * Create ONE raffle code owned by the user, identical to what ajax/generate-code.php creates
 * (status 'assigned', SELFGEN-{userId}- batch prefix) so it shows up in "Your Self-Generated Codes"
 * and redeems through the normal ajax/redeem.php flow. The extra "RWD-" segment marks it as a
 * reward so it does NOT count against the 2-per-day free generation limit (see README).
 */
function createRewardCodeForUser(PDO $db, int $userId, int $orderId): string {
    $dup = $db->prepare("SELECT COUNT(*) FROM codes WHERE code = ?");
    do {
        $code = '';
        for ($i = 0; $i < 15; $i++) $code .= random_int(0, 9);
        $dup->execute([$code]);
    } while ((int) $dup->fetchColumn() > 0);

    $batchId = 'SELFGEN-' . $userId . '-RWD-' . $orderId . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));

    $db->prepare("INSERT INTO codes (code, status, generated_by, batch_id, generated_at)
                  VALUES (?, 'assigned', ?, ?, NOW())")
       ->execute([$code, REWARD_SYSTEM_ADMIN_ID, $batchId]);

    return $code;
}

/**
 * Give the user $tickets raffle codes for this order. The number is decided by the caller from the
 * admin's pricing (data: the plan's tickets; airtime: airtimeTicketsFor()). No carry-over any more.
 * Returns the number of codes granted (0 if none, or if the order was already processed).
 */
function grantPurchaseReward(PDO $db, int $userId, int $orderId, int $amountKobo, int $tickets): int {
    $count = max(0, min($tickets, REWARD_MAX_CODES_PER_ORDER));
    if ($count === 0) return 0;

    try {
        $db->beginTransaction();

        // Claim the order. rowCount() 0 => already processed (never grant twice for one order).
        $claim = $db->prepare("INSERT IGNORE INTO purchase_rewards (order_id, user_id, amount, codes_awarded)
                               VALUES (?,?,?,0)");
        $claim->execute([$orderId, $userId, $amountKobo]);
        if ($claim->rowCount() === 0) {
            $db->rollBack();
            return 0;
        }

        for ($i = 0; $i < $count; $i++) {
            createRewardCodeForUser($db, $userId, $orderId);
        }

        $db->prepare("UPDATE purchase_rewards SET codes_awarded = ? WHERE order_id = ?")->execute([$count, $orderId]);

        // Keep the lifetime counter if the table is still there (harmless if it is not used any more).
        try {
            $db->prepare("INSERT INTO reward_balances (user_id, progress_kobo, lifetime_codes) VALUES (?,0,?)
                          ON DUPLICATE KEY UPDATE lifetime_codes = lifetime_codes + VALUES(lifetime_codes)")
               ->execute([$userId, $count]);
        } catch (\Throwable $e) { /* optional */ }

        $db->commit();
    } catch (\Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        error_log("[REWARD] Failed for order $orderId (user $userId): " . $e->getMessage());
        return 0;
    }

    try {
        createNotification($userId, 'You earned raffle tickets! 🎟️',
            "You earned $count free raffle ticket" . ($count > 1 ? 's' : '') . ' for your purchase. Find ' . ($count > 1 ? 'them' : 'it') . ' on the Redeem Code page.', 'success');
        auditLog('system', $userId, 'purchase_reward_granted', "$count code(s) for order $orderId", 'order', $orderId);
    } catch (\Throwable $e) {
        error_log('[REWARD] Notification/audit failed: ' . $e->getMessage());
    }

    return $count;
}
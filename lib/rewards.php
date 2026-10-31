<?php
/**
 * Purchase rewards: users earn raffle codes for airtime/data purchases.
 *
 *   ₦100 spent = 1 raffle code by default. The admin sets the amount at admin/pricing.php
 *   (stored in site_settings, read with getRewardNairaPerCode()).
 *
 * RUNNING BALANCE: spend that doesn't reach a full ₦100 is carried forward per user in
 * reward_balances.progress_kobo. ₦60 + ₦60 => 1 code on the second order, ₦20 carried.
 *
 * Guarantees:
 *  - Only call AFTER the provider confirmed delivery, never for refunded/pending orders.
 *  - Idempotent per order (UNIQUE(order_id) in purchase_rewards): a reload, retry or
 *    reconciliation script can never pay out or count the same order twice.
 *  - Atomic: claim + balance update + code creation commit together or not at all.
 *  - Never throws: a reward problem must not break a purchase that already succeeded.
 *    Failures are logged; the order stays un-rewarded and the backfill query picks it up.
 */

require_once __DIR__ . '/settings.php';

if (!defined('REWARD_MAX_CODES_PER_ORDER')) define('REWARD_MAX_CODES_PER_ORDER', 100); // safety cap; excess carries forward
if (!defined('REWARD_SYSTEM_ADMIN_ID'))     define('REWARD_SYSTEM_ADMIN_ID', 1);       // codes.generated_by (no FK), same as generate-code.php

function rewardUnitKobo(): int {
    return getRewardNairaPerCode() * 100;
}

/** Kobo the user has spent toward their next code (always < one unit unless the cap carried excess). */
function getRewardProgressKobo(PDO $db, int $userId): int {
    try {
        $s = $db->prepare("SELECT progress_kobo FROM reward_balances WHERE user_id = ?");
        $s->execute([$userId]);
        return (int) $s->fetchColumn();
    } catch (\Throwable $e) {
        error_log('[REWARD] progress read failed: ' . $e->getMessage());
        return 0;
    }
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
 * Add this order's spend to the user's running balance and grant any codes it unlocks.
 * Returns the number of codes granted (0 if none, or if the order was already processed).
 */
function grantPurchaseReward(PDO $db, int $userId, int $orderId, int $amountKobo): int {
    if ($amountKobo <= 0) return 0;
    $unit  = rewardUnitKobo();
    $count = 0;

    try {
        $db->beginTransaction();

        // 1. Claim the order. rowCount() 0 => already processed by someone else.
        $claim = $db->prepare("INSERT IGNORE INTO purchase_rewards (order_id, user_id, amount, codes_awarded)
                               VALUES (?,?,?,0)");
        $claim->execute([$orderId, $userId, $amountKobo]);
        if ($claim->rowCount() === 0) {
            $db->rollBack();
            return 0;
        }

        // 2. Lock the user's running balance (create it on first purchase).
        $db->prepare("INSERT IGNORE INTO reward_balances (user_id, progress_kobo, lifetime_codes) VALUES (?,0,0)")
           ->execute([$userId]);
        $sel = $db->prepare("SELECT progress_kobo FROM reward_balances WHERE user_id = ? FOR UPDATE");
        $sel->execute([$userId]);
        $progress = (int) $sel->fetchColumn();

        // 3. Work out codes earned and the new carry-over.
        $total     = $progress + $amountKobo;
        $count     = min(intdiv($total, $unit), REWARD_MAX_CODES_PER_ORDER);
        $remainder = $total - ($count * $unit);

        for ($i = 0; $i < $count; $i++) {
            createRewardCodeForUser($db, $userId, $orderId);
        }

        $db->prepare("UPDATE purchase_rewards SET codes_awarded = ? WHERE order_id = ?")->execute([$count, $orderId]);
        $db->prepare("UPDATE reward_balances SET progress_kobo = ?, lifetime_codes = lifetime_codes + ? WHERE user_id = ?")
           ->execute([$remainder, $count, $userId]);

        $db->commit();
    } catch (\Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        error_log("[REWARD] Failed for order $orderId (user $userId): " . $e->getMessage());
        return 0;
    }

    if ($count > 0) {
        try {
            createNotification($userId, 'You earned raffle codes! 🎟️',
                "You earned $count free raffle code" . ($count > 1 ? 's' : '') . ' for your purchase. Find ' . ($count > 1 ? 'them' : 'it') . ' on the Redeem Code page.', 'success');
            auditLog('system', $userId, 'purchase_reward_granted', "$count code(s) for order $orderId", 'order', $orderId);
        } catch (\Throwable $e) {
            error_log('[REWARD] Notification/audit failed: ' . $e->getMessage());
        }
    }

    return $count;
}
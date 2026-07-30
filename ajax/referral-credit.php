<?php
// ajax/referral-credit.php
// Called client-side immediately after a code redemption succeeds (see
// user/redeem-code.php). Checks whether the logged-in user was referred by
// someone and hasn't already triggered that referrer's reward — if so,
// grants it (one free 15-digit code to the referrer, logged to audit_logs).
// Safe to call on every redemption: it's a no-op if the user wasn't
// referred, or if the reward was already granted (referral_rewards has a
// UNIQUE key on referred_user_id, and maybeGrantReferralReward() only acts
// on rows still in 'pending' status).
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/referral.php';

header('Content-Type: application/json');

$auth   = requireUser();
$userId = (int)$auth['id'];
$db     = getDB();

try {
    maybeGrantReferralReward($db, $userId);
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    // A referral hiccup should never surface as an error to the person who
    // just successfully redeemed a code — fail quietly, log nothing user-
    // facing, and let them keep going.
    echo json_encode(['success' => false]);
}
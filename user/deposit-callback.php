<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../lib/paystack.php';

$auth   = requireUser();
$userId = $auth['id'];
$db     = getDB();

$reference = $_GET['reference'] ?? $_GET['trxref'] ?? '';
$flashType = 'error';
$flashMsg  = 'We could not confirm your payment. If you were debited, please contact support with your reference.';

if ($reference !== '') {
    $stmt = $db->prepare("SELECT * FROM deposits WHERE reference = ? AND user_id = ? LIMIT 1");
    $stmt->execute([$reference, $userId]);
    $deposit = $stmt->fetch();

    if ($deposit) {

        if ($deposit['status'] === 'success') {
            // Already credited earlier (e.g. user hit back/refresh)
            $flashType = 'success';
            $flashMsg  = 'Payment already confirmed. Your wallet was credited.';

        } else {
            // Always re-verify with Paystack directly. Never trust query params alone.
            $verify = paystackVerifyTransaction($reference);

            if (!empty($verify['status']) && ($verify['data']['status'] ?? '') === 'success') {
                $paidKobo = (int) ($verify['data']['amount'] ?? 0);

                if ($paidKobo !== (int) $deposit['amount']) {
                    $db->prepare("UPDATE deposits SET status='failed', gateway_response='Amount mismatch' WHERE id=?")
                       ->execute([$deposit['id']]);
                    $flashMsg = 'Payment amount did not match our records. Please contact support with reference ' . $reference . '.';

                } else {
                    try {
                        $db->beginTransaction();

                        // Row-lock to guard against double-credit if a webhook
                        // and this callback both arrive around the same time.
                        $lock = $db->prepare("SELECT status FROM deposits WHERE id = ? FOR UPDATE");
                        $lock->execute([$deposit['id']]);
                        $current = $lock->fetchColumn();

                        if ($current === 'success') {
                            $db->commit();
                            $flashType = 'success';
                            $flashMsg  = 'Payment already confirmed. Your wallet was credited.';
                        } else {
                            $db->prepare("UPDATE deposits SET status='success', channel=?, gateway_response=?, paystack_data=?, paid_at=NOW() WHERE id=?")
                               ->execute([
                                   $verify['data']['channel'] ?? null,
                                   $verify['data']['gateway_response'] ?? null,
                                   json_encode($verify['data']),
                                   $deposit['id'],
                               ]);

                            $db->prepare("UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?")
                               ->execute([$paidKobo, $userId]);

                            $balStmt = $db->prepare("SELECT wallet_balance FROM users WHERE id = ?");
                            $balStmt->execute([$userId]);
                            $newBalance = (int) $balStmt->fetchColumn();

                            $db->prepare("INSERT INTO wallet_transactions
                                (user_id, type, category, amount, balance_after, reference_type, reference_id, description)
                                VALUES (?,?,?,?,?,?,?,?)")
                               ->execute([$userId, 'credit', 'deposit', $paidKobo, $newBalance, 'deposit', $deposit['id'], 'Wallet funding via Paystack']);

                            $db->commit();

                            createNotification($userId, 'Deposit Successful', 'Your wallet was credited with ' . formatNaira($paidKobo) . '.', 'success');
                            auditLog('user', $userId, 'deposit_success', "Deposit of {$paidKobo} kobo confirmed, ref {$reference}", 'deposit', $deposit['id']);

                            $flashType = 'success';
                            $flashMsg  = 'Payment confirmed! Your wallet has been credited with ' . formatNaira($paidKobo) . '.';
                        }
                    } catch (\Exception $ex) {
                        if ($db->inTransaction()) $db->rollBack();
                        $flashMsg = 'Something went wrong while crediting your wallet. Please contact support with reference ' . $reference . '.';
                    }
                }

            } else {
                $db->prepare("UPDATE deposits SET status='failed', gateway_response=? WHERE id=?")
                   ->execute([$verify['data']['gateway_response'] ?? ($verify['message'] ?? 'Verification failed'), $deposit['id']]);
                $flashMsg = 'Payment was not successful.';
            }
        }
    }
}

$_SESSION['flash_type'] = $flashType;
$_SESSION['flash_msg']  = $flashMsg;
redirect(APP_URL . '/user/dashboard.php');
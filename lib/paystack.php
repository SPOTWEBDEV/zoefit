<?php
/**
 * Paystack API helper.
 * Uses PAYSTACK_SECRET_KEY defined in config/config.php.
 * Include this after config/config.php is loaded.
 */

require_once __DIR__ . '/../config/config.php';

/**
 * Low-level cURL wrapper for Paystack API calls.
 */
function paystackCurl(string $url, string $method = 'GET', ?array $payload = null): array {
    $ch = curl_init($url);

    $headers = [
        'Authorization: Bearer ' . PAYSTACK_SECRET_KEY,
        'Content-Type: application/json',
        'Cache-Control: no-cache',
    ];

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload ?? []));
    }

    $response = curl_exec($ch);
    $errNo    = curl_errno($ch);
    $errMsg   = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errNo) {
        return ['status' => false, 'message' => 'Connection error: ' . $errMsg, 'http_code' => 0];
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        return ['status' => false, 'message' => 'Invalid response from payment gateway', 'http_code' => $httpCode];
    }

    $decoded['http_code'] = $httpCode;
    return $decoded;
}

/**
 * Start a Paystack transaction (redirect flow).
 * Returns Paystack's decoded response; on success,
 * $resp['data']['authorization_url'] is where to redirect the user.
 */
function paystackInitializeTransaction(string $email, int $amountKobo, string $reference, string $callbackUrl, array $metadata = []): array {
    return paystackCurl('https://api.paystack.co/transaction/initialize', 'POST', [
        'email'        => $email,
        'amount'       => $amountKobo,
        'reference'    => $reference,
        'callback_url' => $callbackUrl,
        'metadata'     => $metadata,
    ]);
}

/**
 * Verify a transaction by reference. Always call this server-side
 * before crediting a wallet — never trust the callback URL alone.
 */
function paystackVerifyTransaction(string $reference): array {
    return paystackCurl('https://api.paystack.co/transaction/verify/' . rawurlencode($reference), 'GET');
}

/**
 * Generate a unique, traceable deposit reference.
 */
function generateDepositReference(int $userId): string {
    return 'ZF-DEP-' . $userId . '-' . time() . '-' . strtoupper(bin2hex(random_bytes(3)));
}

/**
 * Format a kobo integer as a Naira display string.
 */
function formatNaira($kobo): string {
    return '₦' . number_format(((int) $kobo) / 100, 2);
}
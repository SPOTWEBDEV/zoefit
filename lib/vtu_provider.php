<?php
/**
 * VTU (airtime/data) provider integration — InstantMobile
 * Docs: https://instantmobile.com.ng/api-docs.php
 *
 * Config required (config/config.php or .env):
 *   INSTANTMOBILE_API_KEY   = your API key (Profile > API, needs the API plan)
 *   INSTANTMOBILE_BASE_URL  = optional, defaults to https://instantmobile.com.ng/api/v1.php
 * Pricing is NOT config: admins set the airtime fee and the data profit at admin/pricing.php
 *   (stored in site_settings, see lib/settings.php).
 *
 * Every dispatch function returns:
 *   success            bool    delivered, confirmed by provider
 *   pending            bool    OUTCOME UNKNOWN (timeout / 5xx / garbled reply). Do NOT refund,
 *                              do NOT retry. Leave the order 'pending' and reconcile manually.
 *   message            string  safe to show to the user
 *   provider, provider_reference, raw
 *
 * success=false AND pending=false means the provider definitely did not deliver -> safe to refund.
 */

require_once __DIR__ . '/../config/config.php';

if (!defined('INSTANTMOBILE_BASE_URL')) define('INSTANTMOBILE_BASE_URL', 'https://instantmobile.com.ng/api/v1.php');
// Airtime fee and data profit live in the database, edited at admin/pricing.php (see lib/settings.php).
require_once __DIR__ . '/settings.php';

define('IM_PLANS_CACHE_FILE', __DIR__ . '/../storage/cache/instantmobile_data_plans.json');
define('IM_PLANS_CACHE_TTL', 15 * 60); // plan IDs can change, so keep this short

// -----------------------------------------------------------
// Low-level HTTP
// -----------------------------------------------------------
function imRequest(string $action, string $method = 'GET', ?array $payload = null): array {
    if (!defined('INSTANTMOBILE_API_KEY') || INSTANTMOBILE_API_KEY === '') {
        error_log('[IM] INSTANTMOBILE_API_KEY is not configured');
        return ['ok' => false, 'definite' => true, 'http_code' => 0, 'raw' => null, 'error' => 'not configured'];
    }

    $ch = curl_init(INSTANTMOBILE_BASE_URL . '?action=' . urlencode($action));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . INSTANTMOBILE_API_KEY,
            'Accept: application/json',
            'Content-Type: application/json',
        ],
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => 45,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload ?? []));
    }

    $response = curl_exec($ch);
    $errNo    = curl_errno($ch);
    $errMsg   = curl_error($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errNo) {
        error_log("[IM] cURL error on action=$action: $errMsg");
        // Couldn't even reach the server => nothing was bought. Anything else (timeout, reset
        // mid-response) is ambiguous: the purchase may have gone through.
        $definite = in_array($errNo, [CURLE_COULDNT_RESOLVE_HOST, CURLE_COULDNT_CONNECT], true);
        return ['ok' => false, 'definite' => $definite, 'http_code' => 0, 'raw' => null, 'error' => $errMsg];
    }

    $decoded = json_decode((string) $response, true);
    if (!is_array($decoded)) {
        error_log("[IM] Non-JSON reply on action=$action (HTTP $httpCode): " . substr((string) $response, 0, 300));
        return ['ok' => false, 'definite' => ($httpCode >= 400 && $httpCode < 500), 'http_code' => $httpCode,
                'raw' => $response, 'error' => 'invalid response'];
    }

    return ['ok' => true, 'http_code' => $httpCode, 'raw' => $response, 'data' => $decoded];
}

/** Turn a raw purchase response into our standard result shape. */
function imInterpretPurchase(array $r, string $reference): array {
    $out = [
        'success' => false, 'pending' => false, 'message' => '',
        'provider' => 'instantmobile', 'provider_reference' => null, 'raw' => $r['raw'] ?? null,
    ];

    $unconfirmedMsg = 'We could not confirm your order yet. Please do not retry — check your order history shortly, '
                    . 'or contact support with your reference.';

    if (!$r['ok']) {
        if ($r['definite']) {
            $out['message'] = 'Service is temporarily unavailable. Please try again shortly.';
        } else {
            $out['pending'] = true;
            $out['message'] = $unconfirmedMsg;
        }
        return $out;
    }

    $http   = $r['http_code'];
    $d      = $r['data'];
    $status = $d['status'] ?? null;
    $msg    = trim((string) ($d['message'] ?? ''));

    if ($http === 200 && $status === 'success') {
        $out['success']            = true;
        $out['message']            = $msg !== '' ? $msg : 'Delivered';
        $out['provider_reference'] = $d['reference'] ?? $reference;
        return $out;
    }

    // Server-side trouble or an unrecognised status: outcome unknown.
    if ($http >= 500 || ($status !== 'error' && $status !== 'success')) {
        error_log("[IM] Ambiguous purchase outcome (HTTP $http) ref $reference: " . substr((string) $r['raw'], 0, 300));
        $out['pending'] = true;
        $out['message'] = $unconfirmedMsg;
        return $out;
    }

    // Definite failure. Don't leak OUR wallet/key problems to customers.
    if (in_array($http, [401, 402, 429], true) || stripos($msg, 'insufficient') !== false) {
        error_log("[IM] Provider-side failure (HTTP $http) ref $reference: $msg  <-- check API key / provider wallet");
        $out['message'] = 'Service is temporarily unavailable. Please try again shortly.';
        return $out;
    }

    $out['message'] = $msg !== '' ? $msg : 'Purchase failed.';
    return $out;
}

function imLocalPhone(string $phone): string {
    // We store 234XXXXXXXXXX; the API wants 0XXXXXXXXXX.
    return preg_match('/^234(\d{10})$/', $phone, $m) ? '0' . $m[1] : $phone;
}

// -----------------------------------------------------------
// Airtime
// -----------------------------------------------------------
function dispatchAirtimeOrder(string $network, string $phone, int $amountKobo, string $reference): array {
    if (!in_array($network, ['mtn', 'glo', 'airtel', '9mobile'], true)) {
        return ['success' => false, 'pending' => false, 'message' => 'Unsupported network for airtime purchase.',
                'provider' => null, 'provider_reference' => null, 'raw' => null];
    }
    if ($amountKobo % 100 !== 0) {
        return ['success' => false, 'pending' => false, 'message' => 'Please enter a whole-naira amount.',
                'provider' => null, 'provider_reference' => null, 'raw' => null];
    }

    $r = imRequest('airtime', 'POST', [
        'network'   => $network,
        'phone'     => imLocalPhone($phone),
        'amount'    => intdiv($amountKobo, 100),
        'reference' => $reference,
    ]);

    return imInterpretPurchase($r, $reference);
}

// -----------------------------------------------------------
// Data
// -----------------------------------------------------------
function dispatchDataOrder(string $network, string $phone, string $planCode, int $amountKobo, string $reference): array {
    if (!in_array($network, ['mtn', 'glo', 'airtel', '9mobile'], true)) {
        return ['success' => false, 'pending' => false, 'message' => 'Unsupported network for data purchase.',
                'provider' => null, 'provider_reference' => null, 'raw' => null];
    }

    $r = imRequest('data', 'POST', [
        'network'   => $network,
        'phone'     => imLocalPhone($phone),
        'plan_id'   => (int) $planCode,
        'reference' => $reference,
    ]);

    return imInterpretPurchase($r, $reference);
}

/**
 * Flat service fee (kobo) added on top of an airtime purchase. The USER SEES this fee.
 * Order is stored as: amount = airtime + fee (total charged), cost_amount = airtime sent to the provider,
 * profit_amount = fee. (Any discount InstantMobile gives you on airtime is extra profit not tracked here.)
 */
function airtimeFeeKobo(): int {
    return (int) round(getAirtimeFeeNaira() * 100);
}

// -----------------------------------------------------------
// Provider wallet balance (for admin low-balance alerts)
// -----------------------------------------------------------
function imGetBalanceNaira(): ?float {
    $r = imRequest('balance');
    if (!$r['ok'] || ($r['data']['status'] ?? '') !== 'success') return null;
    return (float) ($r['data']['balance'] ?? 0);
}

// -----------------------------------------------------------
// Data plan catalogue (cached, with your own markup applied on read)
// -----------------------------------------------------------
function imNormalizeNetwork($n): string {
    $n = str_replace([' ', '-', '_'], '', strtolower(trim((string) $n)));
    return in_array($n, ['etisalat', '9mobile', 'nmobile'], true) ? '9mobile' : $n;
}

/** Fetch plans from provider -> [network => [ [code,name,type,cost(naira)], ... ]] */
function imFetchPlans(): array {
    $r = imRequest('data-plans');
    if (!$r['ok'] || ($r['data']['status'] ?? '') !== 'success' || !is_array($r['data']['data'] ?? null)) {
        error_log('[IM] Failed to fetch data plans');
        return [];
    }

    $plans = [];
    foreach ($r['data']['data'] as $item) {
        if (!is_array($item) || !isset($item['id'], $item['network'], $item['name'], $item['price'])) continue;
        $plans[imNormalizeNetwork($item['network'])][] = [
            'code' => (string) $item['id'],
            'name' => (string) $item['name'],
            'type' => (string) ($item['type'] ?? ''),
            'cost' => (float) $item['price'],
        ];
    }
    return $plans;
}

/** What the USER pays for a data plan, in kobo: provider price + the flat data profit, rounded up to a whole naira. */
function imSellingPriceKobo(float $costNaira): int {
    return (int) ceil($costNaira + getDataProfitNaira()) * 100;
}

function getDataPlans(): array {
    $file = IM_PLANS_CACHE_FILE;
    $cost = null;

    if (is_file($file) && (time() - filemtime($file)) < IM_PLANS_CACHE_TTL) {
        $cost = json_decode((string) file_get_contents($file), true);
    }

    if (!is_array($cost)) {
        $fresh = imFetchPlans();
        if ($fresh) {
            $dir = dirname($file);
            if (!is_dir($dir)) @mkdir($dir, 0775, true);
            $tmp = $file . '.' . bin2hex(random_bytes(4)) . '.tmp';
            if (@file_put_contents($tmp, json_encode($fresh)) !== false) @rename($tmp, $file);
            $cost = $fresh;
        } elseif (is_file($file)) {
            $cost = json_decode((string) file_get_contents($file), true); // stale beats empty
        }
    }
    if (!is_array($cost)) return [];

    $plans = [];
    foreach ($cost as $network => $list) {
        foreach ($list as $p) {
            $plans[$network][] = [
                'code'  => $p['code'],
                'name'  => $p['name'],
                'type'  => $p['type'] ?? '',
                'price'  => imSellingPriceKobo((float) $p['cost']),  // what the USER pays (cost + profit), kobo
                'cost'   => (int) round(((float) $p['cost']) * 100),    // what YOU pay the provider, kobo
                'profit' => imSellingPriceKobo((float) $p['cost']) - (int) round(((float) $p['cost']) * 100),
            ];
        }
        usort($plans[$network], fn($a, $b) => $a['price'] <=> $b['price']);
    }
    return $plans;
}

function getDataPlanByCode(string $network, string $code): ?array {
    foreach ((getDataPlans()[$network] ?? []) as $p) {
        if ($p['code'] === $code) return $p;
    }
    return null;
}
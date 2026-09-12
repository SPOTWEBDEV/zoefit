<?php
/**
 * VTU (airtime/data) provider integration — ePins (https://epins.com.ng)
 *
 * Docs referenced:
 *  - Airtime: POST {{baseurl}}/airtime/   body: {network, phone, amount, ref}
 *  - Data:    POST {{baseurl}}/data/      body: {networkId, MobileNumber, DataPlan, ref}
 *  - Data plan list: GET {{baseurl}}/autho/variations/?service=data
 *
 * Config required in .env (see config/config.php for how these are read):
 *   EPINS_API_KEY   = your bearer token
 *   EPINS_BASE_URL  = https://api.epins.com.ng/v2   (live)
 *                     — set to ePins' sandbox base URL instead while testing,
 *                       if they've given you one; it wasn't included in the
 *                       docs you shared, so confirm it with ePins directly.
 */

require_once __DIR__ . '/../config/config.php';

// -----------------------------------------------------------
// Network name mapping
// -----------------------------------------------------------
// Airtime endpoint wants: mtn, airtel, glo, etisalat
const EPINS_AIRTIME_NETWORK_MAP = [
    'mtn'     => 'mtn',
    'airtel'  => 'airtel',
    'glo'     => 'glo',
    '9mobile' => 'etisalat',
];

// Data endpoint wants a numeric networkId instead of a name
const EPINS_DATA_NETWORK_ID_MAP = [
    'mtn'     => '01',
    'glo'     => '02',
    '9mobile' => '03',
    'airtel'  => '04',
];

// -----------------------------------------------------------
// Low-level HTTP helper
// -----------------------------------------------------------
function epinsCurl(string $url, string $method = 'GET', ?array $payload = null): array {
    $ch = curl_init($url);

    $headers = [
        'Authorization: Bearer ' . EPINS_API_KEY,
        'Content-Type: application/json',
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
        error_log("[EPINS] cURL error calling $url: $errMsg");
        return ['ok' => false, 'http_code' => 0, 'raw' => null, 'error' => "Connection error: $errMsg"];
    }

    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        error_log("[EPINS] Non-JSON response from $url (HTTP $httpCode): " . substr((string)$response, 0, 500));
        return ['ok' => false, 'http_code' => $httpCode, 'raw' => $response, 'error' => 'Invalid response from provider'];
    }

    return ['ok' => true, 'http_code' => $httpCode, 'raw' => $response, 'data' => $decoded];
}

/**
 * ePins responses follow {"code":101,"description":{...or string...}}.
 * 101 = success, based on the sample in their docs. Anything else is
 * treated as failure. If you find other success codes in testing
 * (e.g. sometimes providers use 100 AND 101), add them to this list.
 */
function epinsIsSuccessCode($code): bool {
    return in_array((int) $code, [101], true);
}

function epinsExtractMessage(array $decoded): string {
    $desc = $decoded['description'] ?? null;
    if (is_array($desc)) {
        return $desc['response_description'] ?? ($decoded['message'] ?? 'Unknown response from provider');
    }
    if (is_string($desc)) {
        return $desc;
    }
    return $decoded['message'] ?? 'Unknown response from provider';
}

// -----------------------------------------------------------
// Airtime
// -----------------------------------------------------------
function dispatchAirtimeOrder(string $network, string $phone, int $amountKobo, string $reference): array {
    $epinsNetwork = EPINS_AIRTIME_NETWORK_MAP[$network] ?? null;
    if (!$epinsNetwork) {
        return [
            'success' => false, 'message' => 'Unsupported network for airtime purchase.',
            'provider' => null, 'provider_reference' => null, 'raw' => null,
        ];
    }

    $amountNaira = round($amountKobo / 100, 2);

    $result = epinsCurl(rtrim(EPINS_BASE_URL, '/') . '/airtime/', 'POST', [
        'network' => $epinsNetwork,
        'phone'   => $phone,
        'amount'  => $amountNaira,
        'ref'     => $reference,
    ]);

    if (!$result['ok']) {
        return [
            'success' => false, 'message' => $result['error'],
            'provider' => 'epins', 'provider_reference' => null, 'raw' => $result['raw'],
        ];
    }

    $decoded = $result['data'];
    $success = isset($decoded['code']) && epinsIsSuccessCode($decoded['code']);
    $desc    = $decoded['description'] ?? [];

    return [
        'success'            => $success,
        'message'            => epinsExtractMessage($decoded),
        'provider'           => 'epins',
        'provider_reference' => is_array($desc) ? ($desc['ref'] ?? $reference) : $reference,
        'raw'                => json_encode($decoded),
    ];
}

// -----------------------------------------------------------
// Data
// -----------------------------------------------------------
function dispatchDataOrder(string $network, string $phone, string $planCode, int $amountKobo, string $reference): array {
    $networkId = EPINS_DATA_NETWORK_ID_MAP[$network] ?? null;
    if (!$networkId) {
        return [
            'success' => false, 'message' => 'Unsupported network for data purchase.',
            'provider' => null, 'provider_reference' => null, 'raw' => null,
        ];
    }

    $result = epinsCurl(rtrim(EPINS_BASE_URL, '/') . '/data/', 'POST', [
        'networkId'    => $networkId,
        'MobileNumber' => $phone,
        'DataPlan'     => $planCode,
        'ref'          => $reference,
    ]);

    if (!$result['ok']) {
        return [
            'success' => false, 'message' => $result['error'],
            'provider' => 'epins', 'provider_reference' => null, 'raw' => $result['raw'],
        ];
    }

    $decoded = $result['data'];
    $success = isset($decoded['code']) && epinsIsSuccessCode($decoded['code']);
    $desc    = $decoded['description'] ?? [];

    return [
        'success'            => $success,
        'message'            => epinsExtractMessage($decoded),
        'provider'           => 'epins',
        'provider_reference' => is_array($desc) ? ($desc['ref'] ?? $reference) : $reference,
        'raw'                => json_encode($decoded),
    ];
}

// -----------------------------------------------------------
// Data plan catalogue — fetched live from ePins, cached to a file
// so we're not hitting their API on every single page load.
//
// ⚠ The parser below is a BEST-EFFORT guess at field names, since
// the actual JSON shape of /autho/variations/?service=data wasn't
// available to verify. Run tools/inspect-epins-variations.php once
// (see below), check the raw output, and adjust normalizeEpinsVariation()
// if the field names differ from what's guessed here.
// -----------------------------------------------------------

define('EPINS_VARIATIONS_CACHE_FILE', __DIR__ . '/../storage/cache/epins_data_variations.json');
define('EPINS_VARIATIONS_CACHE_TTL', 12 * 3600); // 12 hours

function fetchEpinsDataVariationsRaw(): array {
    $url = 'https://api.epins.com.ng/v2/autho/variations/?service=data';
    $result = epinsCurl($url, 'GET');

    if (!$result['ok']) {
        error_log('[EPINS] Failed to fetch data variations: ' . $result['error']);
        return [];
    }

    return $result['data'];
}

/**
 * Normalize one raw variation item into ['code','name','price','network'].
 * GUESSED field names — verify against the real response and adjust.
 */
function normalizeEpinsVariation(array $item): ?array {
    $code = $item['variation_id'] ?? $item['variation_code'] ?? $item['id'] ?? $item['code'] ?? null;
    $name = $item['name'] ?? $item['variation_name'] ?? $item['plan'] ?? $item['description'] ?? null;
    $price = $item['variation_amount'] ?? $item['price'] ?? $item['amount'] ?? null;
    $networkRaw = $item['network'] ?? $item['networkId'] ?? $item['network_id'] ?? null;

    if ($code === null || $name === null || $price === null || $networkRaw === null) {
        return null; // shape didn't match what we expected — skip rather than guess wrong
    }

    // networkId (01-04) or a name (mtn/glo/...) — normalize to our internal keys
    $idToNetwork = array_flip(EPINS_DATA_NETWORK_ID_MAP);
    $network = $idToNetwork[$networkRaw] ?? strtolower((string) $networkRaw);
    if ($network === 'etisalat') $network = '9mobile';

    return [
        'code'    => (string) $code,
        'name'    => (string) $name,
        'price'   => (int) round(((float) $price) * 100), // store in kobo, consistent with rest of app
        'network' => $network,
    ];
}

function getDataPlans(): array {
    $cacheFile = EPINS_VARIATIONS_CACHE_FILE;
    $cacheDir  = dirname($cacheFile);

    $fromCache = null;
    if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < EPINS_VARIATIONS_CACHE_TTL) {
        $fromCache = json_decode(file_get_contents($cacheFile), true);
    }

    if ($fromCache === null) {
        $raw = fetchEpinsDataVariationsRaw();

        // Best-effort: the list of variation items might be at the top level,
        // or nested under a key like 'data'/'variations'/'description'.
        $items = $raw['data'] ?? $raw['variations'] ?? $raw['description'] ?? (is_array($raw) && array_is_list($raw) ? $raw : null);

        $plans = [];
        if (is_array($items)) {
            foreach ($items as $item) {
                if (!is_array($item)) continue;
                $normalized = normalizeEpinsVariation($item);
                if ($normalized) {
                    $plans[$normalized['network']][] = [
                        'code'  => $normalized['code'],
                        'name'  => $normalized['name'],
                        'price' => $normalized['price'],
                    ];
                }
            }
        }

        if ($plans) {
            if (!is_dir($cacheDir)) @mkdir($cacheDir, 0775, true);
            @file_put_contents($cacheFile, json_encode($plans));
            return $plans;
        }

        error_log('[EPINS] Could not parse data variations response — check normalizeEpinsVariation() against the real API shape.');

        // Fall back to stale cache if we have one, rather than showing nothing.
        if (is_file($cacheFile)) {
            $stale = json_decode(file_get_contents($cacheFile), true);
            if (is_array($stale)) return $stale;
        }

        return [];
    }

    return $fromCache;
}

function getDataPlanByCode(string $network, string $code): ?array {
    $plans = getDataPlans()[$network] ?? [];
    foreach ($plans as $p) {
        if ($p['code'] === $code) return $p;
    }
    return null;
}
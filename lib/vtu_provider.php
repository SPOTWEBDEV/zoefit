<?php
/**
 * VTU (airtime/data) provider integration.
 *
 * ⚠ NOT YET CONNECTED.
 *
 * The SimHostNG documentation (simhostng.com/api.pdf) covers only:
 * API key issuance, wallet balance, bulk SMS, and USSD session
 * dialing/callbacks. It does not expose any airtime or data-bundle
 * purchase endpoint, so there's nothing to wire up from it yet.
 *
 * Once a real VTU provider is chosen (e.g. VTpass, ClubKonnect,
 * Baxi, iRecharge, EbillsAfrica, Flutterwave Bills), replace the
 * body of dispatchAirtimeOrder() and dispatchDataOrder() below with
 * a real HTTP call to that provider's API. Nothing else in
 * airtime.php / data.php needs to change — they already handle the
 * wallet debit, refund-on-failure, and order bookkeeping correctly
 * around whatever these two functions return.
 */

require_once __DIR__ . '/../config/config.php';

/**
 * Attempt to deliver airtime to a phone number.
 *
 * @return array{success:bool, message:string, provider:?string, provider_reference:?string, raw:?string}
 */
function dispatchAirtimeOrder(string $network, string $phone, int $amountKobo, string $reference): array {
    // --- TODO: real integration goes here ---
    // Example shape once wired up:
    // $resp = paystackStyleCurl('https://provider.example.com/api/airtime', 'POST', [
    //     'network'    => $network,
    //     'phone'      => $phone,
    //     'amount'     => $amountKobo / 100,
    //     'request_id' => $reference,
    // ]);
    // return [
    //     'success'            => $resp['status'] === 'success',
    //     'message'            => $resp['message'] ?? '',
    //     'provider'           => 'provider_name',
    //     'provider_reference' => $resp['transaction_id'] ?? null,
    //     'raw'                => json_encode($resp),
    // ];

    return [
        'success'            => false,
        'message'            => 'Airtime provider is not yet configured. No charge has been made.',
        'provider'           => null,
        'provider_reference' => null,
        'raw'                => null,
    ];
}

/**
 * Attempt to deliver a data bundle to a phone number.
 *
 * @return array{success:bool, message:string, provider:?string, provider_reference:?string, raw:?string}
 */
function dispatchDataOrder(string $network, string $phone, string $planCode, int $amountKobo, string $reference): array {
    // --- TODO: real integration goes here (see dispatchAirtimeOrder above) ---

    return [
        'success'            => false,
        'message'            => 'Data provider is not yet configured. No charge has been made.',
        'provider'           => null,
        'provider_reference' => null,
        'raw'                => null,
    ];
}

/**
 * Placeholder data plan catalogue, in kobo.
 * Replace with the real provider's plan list (most VTU providers
 * expose a "list variations/plans" endpoint) once integrated.
 */
function getDataPlans(): array {
    return [
        'mtn' => [
            ['code' => 'mtn_1gb_30d', 'name' => '1GB - 30 Days', 'price' => 35000],
            ['code' => 'mtn_2gb_30d', 'name' => '2GB - 30 Days', 'price' => 60000],
            ['code' => 'mtn_5gb_30d', 'name' => '5GB - 30 Days', 'price' => 150000],
        ],
        'glo' => [
            ['code' => 'glo_1gb_30d', 'name' => '1GB - 30 Days', 'price' => 30000],
            ['code' => 'glo_2_5gb_30d', 'name' => '2.5GB - 30 Days', 'price' => 50000],
        ],
        'airtel' => [
            ['code' => 'airtel_1gb_30d', 'name' => '1GB - 30 Days', 'price' => 32000],
            ['code' => 'airtel_3gb_30d', 'name' => '3GB - 30 Days', 'price' => 90000],
        ],
        '9mobile' => [
            ['code' => '9mobile_1gb_30d', 'name' => '1GB - 30 Days', 'price' => 30000],
        ],
    ];
}

function getDataPlanByCode(string $network, string $code): ?array {
    $plans = getDataPlans()[$network] ?? [];
    foreach ($plans as $p) {
        if ($p['code'] === $code) return $p;
    }
    return null;
}

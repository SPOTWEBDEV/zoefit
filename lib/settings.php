<?php
if (!defined('AIRTIME_FEE_NAIRA_DEFAULT')) define('AIRTIME_FEE_NAIRA_DEFAULT', 0);
if (!defined('DATA_PROFIT_NAIRA_DEFAULT')) define('DATA_PROFIT_NAIRA_DEFAULT', 0);
if (!defined('REWARD_NAIRA_PER_CODE_DEFAULT')) define('REWARD_NAIRA_PER_CODE_DEFAULT', 100); // only used as a fallback before the admin saves tiers
if (!defined('REWARD_MAX_CODES_PER_ORDER')) define('REWARD_MAX_CODES_PER_ORDER', 500);       // max tickets one purchase can give (admin input is capped to this)

/** All settings as key => string, loaded once per request. Pass true to reload after a save. */
function siteSettings(bool $refresh = false): array {
    static $cache = null;
    if ($cache !== null && !$refresh) return $cache;

    $cache = [];
    try {
        foreach (getDB()->query("SELECT setting_key, setting_value FROM site_settings") as $row) {
            $cache[$row['setting_key']] = $row['setting_value'];
        }
    } catch (\Throwable $e) {
        error_log('[SETTINGS] Could not read site_settings (using defaults): ' . $e->getMessage());
    }
    return $cache;
}

function saveSiteSetting(string $key, string $value, ?int $adminId = null): void {
    getDB()->prepare(
        "INSERT INTO site_settings (setting_key, setting_value, updated_by)
         VALUES (?,?,?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by)"
    )->execute([$key, $value, $adminId]);
}

/** True once an admin has saved this setting at least once. */
function siteSettingIsSet(string $key): bool {
    return array_key_exists($key, siteSettings());
}

function readNairaSetting(string $key, float $default): float {
    $v = siteSettings()[$key] ?? null;
    return ($v !== null && is_numeric($v) && $v >= 0) ? (float) $v : $default;
}

/** Flat service fee (naira) added to EVERY airtime purchase, whatever the amount. Shown to the user. */
function getAirtimeFeeNaira(): float {
    return readNairaSetting('airtime_fee_naira', (float) AIRTIME_FEE_NAIRA_DEFAULT);
}

/** DEFAULT profit (naira) added to a data plan that has no gain of its own. Hidden from the user. */
function getDataProfitNaira(): float {
    return readNairaSetting('data_profit_naira', (float) DATA_PROFIT_NAIRA_DEFAULT);
}

/** Legacy "spend ₦X for 1 ticket" rate. Now only a fallback for plans/amounts the admin has not configured yet. */
function getRewardNairaPerCode(): int {
    $v = siteSettings()['reward_naira_per_code'] ?? null;
    return ($v !== null && ctype_digit((string) $v) && (int) $v >= 1) ? (int) $v : (int) REWARD_NAIRA_PER_CODE_DEFAULT;
}

// -----------------------------------------------------------
// Data: per-plan gain + tickets (table data_plan_pricing)
// -----------------------------------------------------------

/** [network][plan_code] => ['gain' => ?int naira, 'tickets' => int]. Only plans the admin has saved. */
function dataPlanOverrides(bool $refresh = false): array {
    static $cache = null;
    if ($cache !== null && !$refresh) return $cache;

    $cache = [];
    try {
        foreach (getDB()->query("SELECT network, plan_code, gain_naira, tickets FROM data_plan_pricing") as $r) {
            $cache[$r['network']][(string) $r['plan_code']] = [
                'gain'    => $r['gain_naira'] === null ? null : (int) $r['gain_naira'],
                'tickets' => (int) $r['tickets'],
            ];
        }
    } catch (\Throwable $e) {
        error_log('[SETTINGS] Could not read data_plan_pricing (run the SQL migration): ' . $e->getMessage());
    }
    return $cache;
}

/** Tickets a data plan gives when the admin hasn't set it yet: the old "₦ per ticket" rule. */
function dataPlanDefaultTickets(int $priceKobo): int {
    return min(REWARD_MAX_CODES_PER_ORDER, intdiv($priceKobo, getRewardNairaPerCode() * 100));
}

/**
 * @param array $rows list of ['network' => string, 'code' => string, 'gain' => ?int, 'tickets' => int]
 */
function saveDataPlanPricing(array $rows, ?int $adminId = null): void {
    $db = getDB();
    $stmt = $db->prepare(
        "INSERT INTO data_plan_pricing (network, plan_code, gain_naira, tickets, updated_by)
         VALUES (?,?,?,?,?)
         ON DUPLICATE KEY UPDATE gain_naira = VALUES(gain_naira), tickets = VALUES(tickets), updated_by = VALUES(updated_by)"
    );
    $db->beginTransaction();
    try {
        foreach ($rows as $r) {
            $stmt->execute([$r['network'], $r['code'], $r['gain'], $r['tickets'], $adminId]);
        }
        $db->commit();
    } catch (\Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }
    dataPlanOverrides(true);
}

// -----------------------------------------------------------
// Airtime: ticket tiers (table airtime_ticket_tiers)
// -----------------------------------------------------------

/** [amount_naira => tickets], sorted by amount ascending. Empty until the admin saves tiers. */
function airtimeTicketTiers(bool $refresh = false): array {
    static $cache = null;
    if ($cache !== null && !$refresh) return $cache;

    $cache = [];
    try {
        foreach (getDB()->query("SELECT amount_naira, tickets FROM airtime_ticket_tiers ORDER BY amount_naira ASC") as $r) {
            $cache[(int) $r['amount_naira']] = (int) $r['tickets'];
        }
    } catch (\Throwable $e) {
        error_log('[SETTINGS] Could not read airtime_ticket_tiers (run the SQL migration): ' . $e->getMessage());
    }
    ksort($cache);
    return $cache;
}

/**
 * Tickets for an airtime purchase of $naira (the airtime value, NOT including the fee):
 * the tickets of the highest tier that is <= $naira, so ₦350 earns the ₦300 tier. Below the lowest tier = 0.
 */
function airtimeTicketsFor(int $naira): int {
    $tiers = airtimeTicketTiers();
    if (!$tiers) {
        return min(REWARD_MAX_CODES_PER_ORDER, intdiv(max(0, $naira), getRewardNairaPerCode()));   // not configured yet: old rule
    }
    $t = 0;
    foreach ($tiers as $amount => $tickets) {
        if ($amount <= $naira) $t = $tickets; else break;
    }
    return $t;
}

/** @param array $tiers [amount_naira => tickets]. Replaces the whole set. */
function saveAirtimeTiers(array $tiers): void {
    $db = getDB();
    $db->beginTransaction();
    try {
        $db->exec("DELETE FROM airtime_ticket_tiers");
        $ins = $db->prepare("INSERT INTO airtime_ticket_tiers (amount_naira, tickets) VALUES (?,?)");
        foreach ($tiers as $amount => $tickets) $ins->execute([(int) $amount, (int) $tickets]);
        $db->commit();
    } catch (\Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        throw $e;
    }
    airtimeTicketTiers(true);
}
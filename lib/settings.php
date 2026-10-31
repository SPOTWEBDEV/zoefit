<?php
/**
 * Site settings stored in the database (table: site_settings), edited by admins at admin/pricing.php.
 *
 * Three values, all whole naira:
 *   airtime_fee_naira   Service fee added ON TOP of the airtime amount. The user SEES it.
 *                       Airtime ₦200 + fee ₦30 => user pays ₦230.
 *   data_profit_naira   Profit added to EVERY data plan's provider price. The user does NOT see it,
 *                       only the total.  Provider price ₦430 + profit ₦30 => user pays ₦460.
 *   reward_naira_per_code  Spend this much to earn 1 raffle ticket (default 100: ₦100 = 1 ticket).
 *                       Leftover spend carries forward, see lib/rewards.php.
 *
 * Until an admin saves a value the default is 0 (no fee / no profit); the admin page warns about this.
 * If the table can't be read, it also falls back to 0 so purchases never break.
 */

if (!defined('AIRTIME_FEE_NAIRA_DEFAULT')) define('AIRTIME_FEE_NAIRA_DEFAULT', 0);
if (!defined('DATA_PROFIT_NAIRA_DEFAULT')) define('DATA_PROFIT_NAIRA_DEFAULT', 0);
if (!defined('REWARD_NAIRA_PER_CODE_DEFAULT')) define('REWARD_NAIRA_PER_CODE_DEFAULT', 100);

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

/** Flat service fee (naira) added to every airtime purchase. Shown to the user. */
function getAirtimeFeeNaira(): float {
    return readNairaSetting('airtime_fee_naira', (float) AIRTIME_FEE_NAIRA_DEFAULT);
}

/** Flat profit (naira) added to every data plan. Hidden from the user. */
function getDataProfitNaira(): float {
    return readNairaSetting('data_profit_naira', (float) DATA_PROFIT_NAIRA_DEFAULT);
}

/** Naira a customer must spend to earn 1 raffle ticket. Always a whole number of at least 1. */
function getRewardNairaPerCode(): int {
    $v = siteSettings()['reward_naira_per_code'] ?? null;
    return ($v !== null && ctype_digit((string) $v) && (int) $v >= 1) ? (int) $v : (int) REWARD_NAIRA_PER_CODE_DEFAULT;
}
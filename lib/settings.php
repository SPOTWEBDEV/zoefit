<?php
/**
 * Site settings stored in the database (table: site_settings), editable by admins
 * from admin/pricing.php. Falls back to the defaults below if a setting was never saved
 * or the table can't be read, so pricing never breaks.
 *
 * Keys used:
 *   data_profit_tiers      JSON {"tiers":[[200,20],[500,40],...],"percent_above":5}
 *   data_profit_overrides  JSON {"<providerPlanId>": profitNaira, ...}
 *   airtime_profit_percent number, % of the airtime face value you keep
 */

// ---- Defaults (used until an admin saves their own) -------------------------
if (!defined('DATA_PROFIT_TIERS_DEFAULT')) define('DATA_PROFIT_TIERS_DEFAULT', [
    //  plan price up to (₦), your profit (₦)
    [  200,  20 ],
    [  500,  40 ],
    [ 1000,  70 ],
    [ 2000, 120 ],
    [ 5000, 250 ],
]);
if (!defined('DATA_PROFIT_PERCENT_ABOVE_DEFAULT')) define('DATA_PROFIT_PERCENT_ABOVE_DEFAULT', 5);
if (!defined('AIRTIME_PROFIT_PERCENT_DEFAULT'))    define('AIRTIME_PROFIT_PERCENT_DEFAULT', 2);

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

/**
 * Data profit rules.
 * @return array{tiers: list<array{0:float,1:float}>, percent_above: float, overrides: array<string,float>}
 */
function getDataProfitConfig(): array {
    $s = siteSettings();

    $tiers   = DATA_PROFIT_TIERS_DEFAULT;
    $percent = (float) DATA_PROFIT_PERCENT_ABOVE_DEFAULT;
    if (isset($s['data_profit_tiers'])) {
        $d = json_decode($s['data_profit_tiers'], true);
        if (is_array($d) && !empty($d['tiers']) && is_array($d['tiers'])) {
            $clean = [];
            foreach ($d['tiers'] as $t) {
                if (is_array($t) && count($t) === 2 && is_numeric($t[0]) && is_numeric($t[1]) && $t[0] > 0 && $t[1] >= 0) {
                    $clean[] = [(float) $t[0], (float) $t[1]];
                }
            }
            if ($clean) {
                usort($clean, fn($a, $b) => $a[0] <=> $b[0]);
                $tiers = $clean;
            }
            if (isset($d['percent_above']) && is_numeric($d['percent_above']) && $d['percent_above'] >= 0) {
                $percent = (float) $d['percent_above'];
            }
        }
    }

    $overrides = [];
    if (isset($s['data_profit_overrides'])) {
        $o = json_decode($s['data_profit_overrides'], true);
        if (is_array($o)) {
            foreach ($o as $planId => $profit) {
                if (is_numeric($profit) && $profit >= 0) $overrides[(string) $planId] = (float) $profit;
            }
        }
    }

    return ['tiers' => $tiers, 'percent_above' => $percent, 'overrides' => $overrides];
}

/** % of airtime face value you keep as profit. */
function getAirtimeProfitPercent(): float {
    $v = siteSettings()['airtime_profit_percent'] ?? null;
    return ($v !== null && is_numeric($v) && $v >= 0) ? (float) $v : (float) AIRTIME_PROFIT_PERCENT_DEFAULT;
}
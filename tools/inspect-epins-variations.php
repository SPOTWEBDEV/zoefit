<?php
/**
 * ONE-TIME DEBUG SCRIPT — delete this file after use.
 *
 * Fetches the raw ePins data-plan variations response and prints it
 * so we can confirm the actual field names (variation code, name,
 * price, network) and fix normalizeEpinsVariation() in
 * lib/vtu_provider.php if it guessed wrong.
 *
 * Restricted to logged-in admins so the API response (and nothing
 * sensitive like the API key itself) isn't publicly exposed.
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../lib/vtu_provider.php';

requireAdmin(); // remove this file once you're done — don't leave it reachable

header('Content-Type: application/json; charset=utf-8');

$raw = fetchEpinsDataVariationsRaw();

echo json_encode($raw, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
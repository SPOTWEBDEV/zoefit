#!/usr/bin/env php
<?php
/**
 * cron/finalize-expired-draws.php
 *
 * AUTOMATIC DRAW FINALIZATION — run this via cron every minute.
 *
 * What it does, every time it runs:
 *   1. Finds every draw with status 'active' or 'paused' whose end_date has passed.
 *   2. For each one: generates a random 15-digit winning code, scores every
 *      participant's best code against it, ranks the TOP 3, saves the winner
 *      + runners-up to draw_rankings, marks the draw 'completed', consumes
 *      all entered codes, and sends notifications.
 *   3. Logs the run to the cron_logs table (and to logs/cron.log on disk).
 *
 * This script is idempotent — running it twice in the same minute does
 * nothing extra, because finalize_draw() throws if a draw is no longer
 * 'active'/'paused' (already completed draws are simply skipped).
 *
 * ── HOW TO SCHEDULE ─────────────────────────────────────────
 *
 * cPanel / shared hosting — Cron Jobs section, add:
 *   * * * * *  /usr/bin/php /home/USER/public_html/zoefeeds/cron/finalize-expired-draws.php >> /home/USER/public_html/zoefeeds/logs/cron.log 2>&1
 *
 * VPS / Linux crontab (crontab -e):
 *   * * * * *  php /var/www/zoefeeds/cron/finalize-expired-draws.php >> /var/www/zoefeeds/logs/cron.log 2>&1
 *
 * Windows Task Scheduler (run every 1 minute):
 *   Program:  C:\php\php.exe
 *   Args:     C:\path\to\zoefeeds\cron\finalize-expired-draws.php
 *
 * You can also trigger it manually for testing:
 *   php cron/finalize-expired-draws.php
 *
 * Or hit it over HTTP from an external cron service (cron-job.org, EasyCron)
 * by visiting cron/finalize-expired-draws.php?key=YOUR_SECRET — see the
 * CRON_HTTP_SECRET check below. Recommended only if shell/CLI cron is
 * unavailable on your host.
 * ============================================================
 */

// Allow both CLI execution and authenticated HTTP execution
$isCli = (php_sapi_name() === 'cli');

define('CRON_HTTP_SECRET', 'change-this-to-a-long-random-string'); // used only for HTTP trigger fallback

if (!$isCli) {
    // HTTP fallback mode — require a secret key to prevent abuse
    $key = $_GET['key'] ?? '';
    if (!hash_equals(CRON_HTTP_SECRET, $key)) {
        http_response_code(403);
        die("Forbidden.\n");
    }
    header('Content-Type: text/plain');
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/draw-engine.php';

$startTime = microtime(true);
$db = getDB();

function cron_echo(string $msg): void {
    echo '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
}

cron_echo('ZoeFeeds draw finalization cron — starting run…');

// Run the engine on all expired active/paused draws
$results = finalize_all_expired_draws($db, 'system', 0);

$found  = count($results);
$ok     = 0;
$failed = 0;
$detailLines = [];

if ($found === 0) {
    cron_echo('No expired draws found. Nothing to do.');
} else {
    cron_echo("Found {$found} expired draw(s). Processing…");

    foreach ($results as $r) {
        if (!empty($r['success'])) {
            $ok++;
            $topNames = array_map(fn($t) => "#{$t['rank']} {$t['name']} ({$t['matched_digits']}/15)", $r['top3']);
            $line = "✅ Draw #{$r['draw_id']} \"{$r['draw_title']}\" finalized. " .
                    "Winning code: {$r['winning_code']}. " .
                    "Entries: {$r['total_entries']}. " .
                    "Top 3: " . implode(' | ', $topNames);
            cron_echo($line);
            $detailLines[] = $line;
        } else {
            $failed++;
            $line = "❌ Draw #{$r['draw_id']} \"{$r['draw_title']}\" FAILED: {$r['error']}";
            cron_echo($line);
            $detailLines[] = $line;
        }
    }
}

// Log this run to the database for the admin dashboard / debugging
try {
    $db->prepare(
        "INSERT INTO cron_logs (job_name, draws_found, draws_ok, draws_failed, detail, run_at)
         VALUES (?,?,?,?,?,NOW())"
    )->execute([
        'finalize-expired-draws',
        $found,
        $ok,
        $failed,
        implode("\n", $detailLines),
    ]);
} catch (\Exception $e) {
    cron_echo('WARNING: could not write to cron_logs table: ' . $e->getMessage());
}

$elapsed = round(microtime(true) - $startTime, 2);
cron_echo("Run complete in {$elapsed}s. Found: {$found}, OK: {$ok}, Failed: {$failed}.");

if (!$isCli) {
    echo "\nDone.\n";
}

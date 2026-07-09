#!/usr/bin/env php
<?php
/**
 * cron/finalize-expired-draws.php
 *
 * THE ONLY CRON FILE YOU NEED — run every minute.
 *
 * What it does each run:
 *   1. Acquires a lock file so two runs never overlap
 *   2. Auto-activates any PENDING draws whose start_date has arrived
 *   3. Auto-finalizes any ACTIVE draws whose end_date has passed:
 *        a. Generates a random 15-digit winning code
 *        b. Scores every participant's best code digit-by-digit
 *        c. Ranks TOP 3, saves to draw_rankings
 *        d. Records winner in draw_winners
 *        e. Marks draw 'completed', marks entered codes 'used'
 *        f. Sends winner email + in-app notification
 *        g. Emails all admins with result
 *        h. If no entries → marks completed, emails admin warning
 *   4. Logs the run to the cron_logs DB table
 *
 * Idempotent — running twice in the same minute is safe.
 *
 * ── HOW TO SCHEDULE ─────────────────────────────────────────
 *
 * cPanel Cron Jobs (shared hosting):
 *   * * * * *  /usr/bin/php /home/USER/public_html/cron/finalize-expired-draws.php >> /home/USER/public_html/logs/cron.log 2>&1
 *
 * VPS / Linux (crontab -e):
 *   * * * * *  php /var/www/zoefeeds/cron/finalize-expired-draws.php >> /var/www/zoefeeds/logs/cron.log 2>&1
 *
 * Windows Task Scheduler (every 1 minute):
 *   Program:  C:\php\php.exe
 *   Args:     C:\path\to\zoefeeds\cron\finalize-expired-draws.php
 *
 * HTTP fallback (cron-job.org / EasyCron — only if CLI cron unavailable):
 *   https://yoursite.com/cron/finalize-expired-draws.php?key=YOUR_SECRET
 *   Set CRON_HTTP_SECRET below to a long random string first.
 * ============================================================
 */

// ── HTTP vs CLI ────────────────────────────────────────────
$isCli = (php_sapi_name() === 'cli');

// Change this to a long random string if using HTTP trigger
define('CRON_HTTP_SECRET', 'change-this-to-a-long-random-string');

if (!$isCli) {
    $key = $_GET['key'] ?? '';
    if (!hash_equals(CRON_HTTP_SECRET, $key)) {
        http_response_code(403);
        die("Forbidden.\n");
    }
    header('Content-Type: text/plain');
}

// ── Bootstrap ──────────────────────────────────────────────
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/draw-engine.php'; // finalize_all_expired_draws()

// ── Lock file — prevent overlapping runs ───────────────────
$lockFile = sys_get_temp_dir() . '/zoefeeds_draws_cron.lock';
$lock     = fopen($lockFile, 'c');
if (!flock($lock, LOCK_EX | LOCK_NB)) {
    // Another instance is already running — exit silently
    exit(0);
}

// ── Logger ─────────────────────────────────────────────────
function cron_echo(string $msg): void {
    echo '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
}

$startTime = microtime(true);
$db        = getDB();

cron_echo('ZoeFeeds draw cron — starting run…');

// ══════════════════════════════════════════════════════════
// STEP 1 — Auto-activate PENDING draws whose start_date
//           has arrived. draw-engine handles this internally
//           but we also log each activation here.
// ══════════════════════════════════════════════════════════
$pendingToActivate = $db->query(
    "SELECT id, title FROM draws
     WHERE status = 'pending' AND start_date <= NOW()"
)->fetchAll();

foreach ($pendingToActivate as $p) {
    cron_echo("Will activate draw #{$p['id']}: {$p['title']}");
}

// ══════════════════════════════════════════════════════════
// STEP 2 — Run the draw engine (activates + finalizes)
// ══════════════════════════════════════════════════════════
$results = finalize_all_expired_draws($db, 'system', 0);

$found       = count($results);
$ok          = 0;
$failed      = 0;
$detailLines = [];

if ($found === 0 && empty($pendingToActivate)) {
    cron_echo('Nothing to do — no pending activations, no expired draws.');
} else {
    if (!empty($pendingToActivate)) {
        cron_echo('Activated ' . count($pendingToActivate) . ' pending draw(s).');
    }

    if ($found > 0) {
        cron_echo("Finalized {$found} expired draw(s).");

        foreach ($results as $r) {
            if (!empty($r['success'])) {
                $ok++;

                if (!empty($r['top3'])) {
                    $topNames = array_map(
                        fn($t) => "#{$t['rank']} {$t['name']} ({$t['matched_digits']}/15)",
                        $r['top3']
                    );
                    $top3str = implode(' | ', $topNames);
                } else {
                    $top3str = 'no entries';
                }

                $note = !empty($r['note']) ? " [{$r['note']}]" : '';
                $line = "✅ Draw #{$r['draw_id']} \"{$r['draw_title']}\""
                      . " | winning code: " . ($r['winning_code'] ?? 'n/a')
                      . " | entries: {$r['total_entries']}"
                      . " | top3: {$top3str}{$note}";

                cron_echo($line);
                $detailLines[] = $line;

            } else {
                $failed++;
                $line = "❌ Draw #{$r['draw_id']} \"{$r['draw_title']}\" — {$r['error']}";
                cron_echo($line);
                $detailLines[] = $line;
            }
        }
    }
}

// ══════════════════════════════════════════════════════════
// STEP 3 — Write run record to cron_logs table
// ══════════════════════════════════════════════════════════
try {
    // Auto-create table if it doesn't exist yet
    $db->exec("CREATE TABLE IF NOT EXISTS `cron_logs` (
        `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `job_name`     VARCHAR(100) NOT NULL,
        `draws_found`  SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        `draws_ok`     SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        `draws_failed` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
        `detail`       TEXT DEFAULT NULL,
        `run_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        KEY `idx_job`  (`job_name`),
        KEY `idx_run`  (`run_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $db->prepare(
        "INSERT INTO cron_logs
           (job_name, draws_found, draws_ok, draws_failed, detail, run_at)
         VALUES (?,?,?,?,?,NOW())"
    )->execute([
        'finalize-expired-draws',
        $found,
        $ok,
        $failed,
        $detailLines ? implode("\n", $detailLines) : null,
    ]);

} catch (\Exception $e) {
    cron_echo('WARNING: could not write to cron_logs: ' . $e->getMessage());
}

// ── Done ───────────────────────────────────────────────────
$elapsed = round(microtime(true) - $startTime, 3);
cron_echo("Run complete in {$elapsed}s — activated: " . count($pendingToActivate)
        . ", finalized: {$found}, ok: {$ok}, failed: {$failed}.");

// Release lock
flock($lock, LOCK_UN);
fclose($lock);

if (!$isCli) {
    echo "\nDone.\n";
}
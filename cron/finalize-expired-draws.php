#!/usr/bin/env php
<?php
/**
 * cron/finalize-expired-draws.php
 *
 * Runs every minute via cron.
 * Does TWO things only:
 *   1. Auto-activates PENDING draws whose start_date has arrived
 *   2. Auto-ends ACTIVE draws whose end_date has passed (status → 'ended')
 *
 * Does NOT select winners — that is done manually by admin on
 * admin/select-winner.php using the physical machine number.
 *
 * ── CRON SETUP ──────────────────────────────────────────────
 * cPanel:
 *   * * * * * /usr/bin/php /home/USER/public_html/cron/finalize-expired-draws.php >> /home/USER/public_html/logs/cron.log 2>&1
 *
 * Linux VPS (crontab -e):
 *   * * * * * php /var/www/zoefeeds/cron/finalize-expired-draws.php >> /var/www/zoefeeds/logs/cron.log 2>&1
 *
 * HTTP fallback (cron-job.org / EasyCron):
 *   https://yoursite.com/cron/finalize-expired-draws.php?key=YOUR_SECRET
 *   Set CRON_HTTP_SECRET below first.
 * ────────────────────────────────────────────────────────────
 */

// ── HTTP vs CLI ────────────────────────────────────────────
$isCli = (php_sapi_name() === 'cli');

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
require_once __DIR__ . '/../mailer/index.php';

// ── Lock file — prevent overlapping runs ───────────────────
$lockFile = sys_get_temp_dir() . '/zoefeeds_draws_cron.lock';
$lock     = fopen($lockFile, 'c');
if (!flock($lock, LOCK_EX | LOCK_NB)) {
    // Another instance already running — exit silently
    exit(0);
}

// ── Logger ─────────────────────────────────────────────────
function cronLog(string $msg): void {
    echo '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
}

$startTime  = microtime(true);
$db         = getDB();
$activated  = 0;
$ended      = 0;
$logLines   = [];

cronLog('ZoeFeeds draw cron — starting run…');

// ── Ensure cron_logs table exists ─────────────────────────
$db->exec("CREATE TABLE IF NOT EXISTS `cron_logs` (
    `id`           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `job_name`     VARCHAR(100) NOT NULL,
    `activated`    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `ended`        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `detail`       TEXT DEFAULT NULL,
    `run_at`       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY `idx_job`  (`job_name`),
    KEY `idx_run`  (`run_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// ── Ensure draws table has required columns ────────────────
// These may not exist on older installs
try {
    $db->exec("ALTER TABLE `draws`
        ADD COLUMN IF NOT EXISTS `activated_by`  INT UNSIGNED DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS `activated_at`  TIMESTAMP NULL DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS `ended_by`      INT UNSIGNED DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS `ended_at`      TIMESTAMP NULL DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS `finalized_at`  TIMESTAMP NULL DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS `finalized_by`  INT UNSIGNED DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS `winning_code`  CHAR(15) DEFAULT NULL,
        ADD COLUMN IF NOT EXISTS `winner_user_id` INT UNSIGNED DEFAULT NULL
    ");
} catch (\Exception $e) {
    // Columns already exist — fine
}

// ── Ensure 'ended' status exists in draws enum ────────────
// Converts any existing 'paused' draws back to 'active' first
try {
    $db->exec("UPDATE `draws` SET status='active' WHERE status='paused'");
    $db->exec("ALTER TABLE `draws`
        MODIFY COLUMN `status`
        ENUM('pending','active','ended','completed','cancelled')
        NOT NULL DEFAULT 'pending'");
} catch (\Exception $e) {
    // Already updated — fine
}


// ══════════════════════════════════════════════════════════
// STEP 1 — Auto-activate PENDING draws whose start_date
//           has arrived and no admin has manually activated
// ══════════════════════════════════════════════════════════
$toActivate = $db->query(
    "SELECT id, title FROM draws
     WHERE status = 'pending'
       AND start_date <= NOW()"
)->fetchAll();

foreach ($toActivate as $d) {
    $db->prepare(
        "UPDATE draws
         SET status='active', activated_at=NOW(), updated_at=NOW()
         WHERE id=? AND status='pending'"
    )->execute([$d['id']]);

    if ($db->rowCount() > 0) {
        $activated++;
        $line = "ACTIVATED draw #{$d['id']}: {$d['title']}";
        cronLog("  → $line");
        $logLines[] = $line;

        auditLog('system', 0, 'cron_activate_draw',
            "Draw #{$d['id']} '{$d['title']}' auto-activated by cron",
            'draw', $d['id']);

        // Notify admins that draw is now live
        _notifyAdminsDrawActivated($db, $d);
    }
}

if ($activated === 0 && empty($toActivate)) {
    cronLog('  → No pending draws to activate.');
}


// ══════════════════════════════════════════════════════════
// STEP 2 — Auto-end ACTIVE draws whose end_date has passed
//           Status → 'ended' — awaiting admin winner entry
// ══════════════════════════════════════════════════════════
$toEnd = $db->query(
    "SELECT * FROM draws
     WHERE status = 'active'
       AND end_date <= NOW()"
)->fetchAll();

foreach ($toEnd as $draw) {
    $drawId = (int)$draw['id'];

    // Guard: skip if already has a winner selected
    if (!empty($draw['winner_user_id'])) {
        cronLog("  → Draw #{$drawId} already has a winner — marking completed.");
        $db->prepare(
            "UPDATE draws SET status='completed', updated_at=NOW() WHERE id=?"
        )->execute([$drawId]);
        continue;
    }

    $db->prepare(
        "UPDATE draws
         SET status='ended', ended_at=NOW(), updated_at=NOW()
         WHERE id=? AND status='active'"
    )->execute([$drawId]);

    if ($db->rowCount() > 0) {
        $ended++;
        $line = "ENDED draw #{$drawId}: {$draw['title']}";
        cronLog("  → $line");
        $logLines[] = $line;

        // Count entries
        $s = $db->prepare("SELECT COUNT(*) FROM draw_entries WHERE draw_id=?");
        $s->execute([$drawId]);
        $entryCount = (int)$s->fetchColumn();

        auditLog('system', 0, 'cron_end_draw',
            "Draw #{$drawId} '{$draw['title']}' auto-ended by cron. Entries: {$entryCount}",
            'draw', $drawId);

        // Notify admins — draw has ended, winner selection required
        _notifyAdminsDrawEnded($db, $draw, $entryCount);
    }
}

if ($ended === 0 && empty($toEnd)) {
    cronLog('  → No active draws have expired.');
}

if ($activated === 0 && $ended === 0) {
    cronLog('Nothing to do this run.');
}


// ══════════════════════════════════════════════════════════
// STEP 3 — Log run to cron_logs table
// ══════════════════════════════════════════════════════════
try {
    $db->prepare(
        "INSERT INTO cron_logs
           (job_name, activated, ended, detail, run_at)
         VALUES (?,?,?,?,NOW())"
    )->execute([
        'finalize-expired-draws',
        $activated,
        $ended,
        $logLines ? implode("\n", $logLines) : null,
    ]);
} catch (\Exception $e) {
    cronLog('WARNING: could not write to cron_logs — ' . $e->getMessage());
}


// ── Done ───────────────────────────────────────────────────
$elapsed = round(microtime(true) - $startTime, 3);
cronLog("Run complete in {$elapsed}s — activated: {$activated}, ended: {$ended}.");

flock($lock, LOCK_UN);
fclose($lock);

if (!$isCli) echo "\nDone.\n";


// ══════════════════════════════════════════════════════════
// EMAIL HELPERS
// ══════════════════════════════════════════════════════════

/**
 * Tell admins a draw has gone live automatically.
 */
function _notifyAdminsDrawActivated(PDO $db, array $draw): void
{
    $admins = $db->query(
        "SELECT email, full_name FROM admins WHERE status='active' AND email != ''"
    )->fetchAll();

    foreach ($admins as $admin) {
        $subject = "🟢 Draw Now Live — {$draw['title']}";
        $body    = "
<p>Hi {$admin['full_name']},</p>
<p>The draw <strong>{$draw['title']}</strong> has been automatically activated by the ZoeFeeds cron system and is now accepting entries.</p>
<p>
  <a href='".APP_URL."/admin/draw-manage.php?id={$draw['id']}'
     style='background:#f97316;color:#fff;padding:10px 24px;border-radius:8px;text-decoration:none;font-weight:bold;display:inline-block'>
    View Draw →
  </a>
</p>
<p style='color:#6b7280;font-size:12px;margin-top:16px;'>— ZoeFeeds Cron System</p>";
        smtpmailer($admin['email'], $subject, $body);
    }
}

/**
 * Tell admins a draw has ended and winner selection is needed.
 */
function _notifyAdminsDrawEnded(PDO $db, array $draw, int $entryCount): void
{
    $admins = $db->query(
        "SELECT email, full_name FROM admins WHERE status='active' AND email != ''"
    )->fetchAll();

    foreach ($admins as $admin) {
        $subject = "🔴 Draw Ended — Winner Selection Required: {$draw['title']}";
        $body    = "
<p>Hi {$admin['full_name']},</p>
<p>The draw <strong>{$draw['title']}</strong> has ended automatically.</p>
<table style='border-collapse:collapse;font-size:14px;margin:12px 0;'>
  <tr><td style='padding:4px 16px 4px 0;color:#6b7280;'>Total entries</td><td><strong>{$entryCount}</strong></td></tr>
  <tr><td style='padding:4px 16px 4px 0;color:#6b7280;'>Draw ended</td><td>".date('M j, Y g:i A')."</td></tr>
</table>
" . ($entryCount === 0
    ? "<p style='color:#f87171;'><strong>⚠️ Warning:</strong> This draw ended with no entries. No winner can be selected.</p>"
    : "<p>Please go to the <strong>Select Winner</strong> page to enter the winning number from the physical machine.</p>
       <p>
         <a href='".APP_URL."/admin/select-winner.php?id={$draw['id']}'
            style='background:#7c3aed;color:#fff;padding:10px 24px;border-radius:8px;text-decoration:none;font-weight:bold;display:inline-block'>
           🏆 Select Winner →
         </a>
       </p>"
) . "
<p style='color:#6b7280;font-size:12px;margin-top:16px;'>— ZoeFeeds Cron System</p>";
        smtpmailer($admin['email'], $subject, $body);
    }
}

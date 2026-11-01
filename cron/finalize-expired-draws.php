#!/usr/bin/env php
<?php
/**
 * cron/finalize-expired-draws.php
 *
 * Runs every minute via cron.
 *   1. Auto-activates PENDING draws whose start_date has arrived
 *   2. Entry-triggered draws: ends any ACTIVE draw that already reached its entry target
 *      (safety net: the enter-draw page normally ends them instantly) and emails the admins
 *   3. Auto-ends ACTIVE draws whose end_date has passed (status → 'ended').
 *      Timed draws always end here. Entry-triggered draws are only ended here when the admin set a real
 *      deadline — entry draws with NO deadline (placeholder end date) are never ended by the clock.
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
require_once __DIR__ . '/../lib/draw_trigger.php';   // sweepEntryTargetDraws()
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

// ── Column that remembers the admins were emailed about an entry-target draw ──
// (checked through information_schema so it works on both MySQL and MariaDB)
try {
    $has = $db->query("SELECT COUNT(*) FROM information_schema.COLUMNS
                       WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'draws' AND COLUMN_NAME = 'ended_notified_at'")->fetchColumn();
    if (!$has) {
        $db->exec("ALTER TABLE `draws` ADD COLUMN `ended_notified_at` TIMESTAMP NULL DEFAULT NULL");
    }
} catch (\Exception $e) {
    cronLog('WARNING: could not ensure ended_notified_at column — ' . $e->getMessage());
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
    $st = $db->prepare(
        "UPDATE draws
         SET status='active', activated_at=NOW(), updated_at=NOW()
         WHERE id=? AND status='pending'"
    );
    $st->execute([$d['id']]);

    if ($st->rowCount() > 0) {
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
// STEP 2 — Entry-triggered draws that reached their target
//           Normally ended instantly by enter-draw.php; this
//           is the safety net (e.g. admin lowered the target,
//           or entries were added by another path).
//           Runs BEFORE the deadline step so the draw keeps
//           the reason 'target_reached'.
// ══════════════════════════════════════════════════════════
$targetEnded = sweepEntryTargetDraws($db);
if ($targetEnded > 0) {
    $ended += $targetEnded;
    $line = "ENDED {$targetEnded} entry-triggered draw(s) that reached their target";
    cronLog("  → $line");
    $logLines[] = $line;
}

// Email the admins once for every entry-triggered draw that ended on its target and still waits for a winner
// (this also catches draws ended instantly by the enter-draw page, which does not send email).
try {
    $targetDraws = $db->query(
        "SELECT * FROM draws
         WHERE status = 'ended'
           AND ended_reason = 'target_reached'
           AND ended_notified_at IS NULL
           AND winner_user_id IS NULL"
    )->fetchAll();

    foreach ($targetDraws as $td) {
        $s = $db->prepare("SELECT COUNT(*) FROM draw_entries WHERE draw_id=?");
        $s->execute([$td['id']]);
        $entryCount = (int)$s->fetchColumn();

        // Mark first, so a mail failure can never cause a flood of repeat emails every minute
        $db->prepare("UPDATE draws SET ended_notified_at = NOW() WHERE id = ? AND ended_notified_at IS NULL")
           ->execute([$td['id']]);

        try {
            _notifyAdminsDrawEnded($db, $td, $entryCount);
        } catch (\Throwable $e) {
            cronLog("  WARNING: email for draw #{$td['id']} failed — " . $e->getMessage());
        }
        $line = "NOTIFIED admins: draw #{$td['id']} '{$td['title']}' reached its entry target ({$entryCount} entries)";
        cronLog("  → $line");
        $logLines[] = $line;
    }
} catch (\Throwable $e) {
    cronLog('WARNING: target-reached notification step skipped — ' . $e->getMessage());
}


// ══════════════════════════════════════════════════════════
// STEP 3 — Auto-end ACTIVE draws whose end_date has passed
//           Status → 'ended' — awaiting admin winner entry
// ══════════════════════════════════════════════════════════
// Only draws that really END BY DATE are picked up here:
//   • timed draws (and old draws with no type)                       → always end at end_date
//   • entry-triggered draws                                          → ONLY if the admin set a real deadline.
// An entry-triggered draw with no deadline has a placeholder end_date filled in (2099-12-31), or an
// empty/zero date — it must NEVER be ended by the clock, only by reaching its entry target.
$toEnd = $db->query(
    "SELECT * FROM draws
     WHERE status = 'active'
       AND end_date <= NOW()
       AND (
             draw_type IS NULL
          OR draw_type <> 'entries'
          OR (end_date >= '2000-01-01' AND end_date < '2099-01-01')
       )"
)->fetchAll();

foreach ($toEnd as $draw) {
    $drawId = (int)$draw['id'];

    // Second line of defence (in PHP): never end an entry-triggered draw that has no real deadline
    if (($draw['draw_type'] ?? 'timed') === 'entries'
        && (!drawHasDeadline($draw['end_date']) || substr((string)$draw['end_date'], 0, 4) < '2000')) {
        cronLog("  → Draw #{$drawId} is entry-triggered with no deadline — skipped (ends only at its entry target).");
        continue;
    }

    // Guard: skip if already has a winner selected
    if (!empty($draw['winner_user_id'])) {
        cronLog("  → Draw #{$drawId} already has a winner — marking completed.");
        $db->prepare(
            "UPDATE draws SET status='completed', updated_at=NOW() WHERE id=?"
        )->execute([$drawId]);
        continue;
    }

    $st = $db->prepare(
        "UPDATE draws
         SET status='ended', ended_at=NOW(), updated_at=NOW()
         WHERE id=? AND status='active'"
    );
    $st->execute([$drawId]);

    if ($st->rowCount() > 0) {
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
// STEP 4 — Log run to cron_logs table
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
<?php
// cron/process-draws.php
// ─────────────────────────────────────────────────────────────
// Run this via cron every minute:
//   * * * * * /usr/bin/php /path/to/your/site/cron/process-draws.php >> /path/to/logs/draws-cron.log 2>&1
//
// What it does:
//   1. Activates any PENDING draws whose start_date has arrived
//   2. Finalizes any ACTIVE draws whose end_date has passed:
//        a. Generates a random 15-digit winning code
//        b. Scores every entered code digit-by-digit
//        c. Picks the user with the most matched digits
//        d. Tiebreaker 1: most entries in that draw
//        e. Tiebreaker 2: earliest registration date
//        f. Records winner in draw_winners + draw_rankings
//        g. Marks entered codes as 'used'
//        h. Sends winner notification
//        i. If no entries → flags draw as 'no_entries' and notifies admin
// ─────────────────────────────────────────────────────────────

define('CRON_RUN', true); // Guard so this can't be called via browser

// Bootstrap — go two levels up from cron/ to reach config/
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../mailer/index.php'; // smtpmailer()

// ── Logging helper ─────────────────────────────────────────
function cronLog(string $msg): void {
    $line = '[' . date('Y-m-d H:i:s') . '] ' . $msg;
    echo $line . PHP_EOL;
}

// ── Lock file — prevent overlapping cron runs ──────────────
$lockFile = sys_get_temp_dir() . '/zoefeeds_draws_cron.lock';
$lock     = fopen($lockFile, 'c');
if (!flock($lock, LOCK_EX | LOCK_NB)) {
    cronLog('Another cron process is already running. Exiting.');
    exit(0);
}

cronLog('─── Draw Cron Started ───────────────────────────');

$db = getDB();

// ===========================================================
// STEP 1 — Activate pending draws whose start_date has arrived
// ===========================================================
$pending = $db->prepare(
    "SELECT id, title FROM draws
     WHERE status = 'pending' AND start_date <= NOW()"
);
$pending->execute();
$pendingDraws = $pending->fetchAll();

foreach ($pendingDraws as $d) {
    $db->prepare(
        "UPDATE draws SET status='active', updated_at=NOW() WHERE id=?"
    )->execute([$d['id']]);

    auditLog('system', 0, 'auto_activate_draw',
        "Draw #{$d['id']} '{$d['title']}' auto-activated by cron", 'draw', $d['id']);

    cronLog("ACTIVATED draw #{$d['id']}: {$d['title']}");
}

// ===========================================================
// STEP 2 — Finalize active draws whose end_date has passed
// ===========================================================
$ended = $db->prepare(
    "SELECT * FROM draws
     WHERE status = 'active' AND end_date <= NOW()"
);
$ended->execute();
$endedDraws = $ended->fetchAll();

foreach ($endedDraws as $draw) {
    $drawId = (int)$draw['id'];
    cronLog("Processing draw #{$drawId}: {$draw['title']}");

    // ── Guard: already has a winner? ──────────────────────
    $alreadyDone = $db->prepare("SELECT id FROM draw_winners WHERE draw_id=?");
    $alreadyDone->execute([$drawId]);
    if ($alreadyDone->fetch()) {
        cronLog("  → draw #{$drawId} already finalized. Marking completed and skipping.");
        $db->prepare("UPDATE draws SET status='completed',updated_at=NOW() WHERE id=?")
           ->execute([$drawId]);
        continue;
    }

    // ── Count entries ─────────────────────────────────────
    $entryCount = $db->prepare("SELECT COUNT(*) FROM draw_entries WHERE draw_id=?");
    $entryCount->execute([$drawId]);
    $totalEntries = (int)$entryCount->fetchColumn();

    if ($totalEntries === 0) {
        // No entries — mark as completed with no winner, notify admin
        cronLog("  → draw #{$drawId} has NO entries. Marking completed (no winner).");
        $db->prepare(
            "UPDATE draws SET status='completed', updated_at=NOW() WHERE id=?"
        )->execute([$drawId]);
        auditLog('system', 0, 'draw_no_entries',
            "Draw #{$drawId} ended with no entries", 'draw', $drawId);

        // Notify all admins
        $admins = $db->query("SELECT email, full_name FROM admins WHERE status='active'")->fetchAll();
        foreach ($admins as $admin) {
            if (!$admin['email']) continue;
            $subject = "ZoeFeeds: Draw Ended With No Entries — #{$drawId}";
            $body    = "
<p>Hi {$admin['full_name']},</p>
<p>Draw <strong>#{$drawId}: {$draw['title']}</strong> has ended but received <strong>no entries</strong>.</p>
<p>The draw has been marked as completed with no winner. Please review and take action if needed.</p>
<p><a href='".APP_URL."/admin/draw-manage.php?id={$drawId}'>View Draw →</a></p>
<p>— ZoeFeeds Cron System</p>";
            smtpmailer($admin['email'], $subject, $body);
        }
        continue;
    }

    // ── Generate random 15-digit winning code ─────────────
    $winningCode = '';
    for ($i = 0; $i < 15; $i++) {
        $winningCode .= (string)random_int(0, 9);
    }
    cronLog("  → winning code generated: {$winningCode}");

    // ── Score every entered code ──────────────────────────
    // Fetch all entries: user_id, code, entered_at, user created_at
    $entries = $db->prepare(
        "SELECT de.user_id, de.code_id, c.code, de.entered_at,
                u.created_at AS user_created_at
         FROM draw_entries de
         JOIN codes c  ON c.id  = de.code_id
         JOIN users u  ON u.id  = de.user_id
         WHERE de.draw_id = ?"
    );
    $entries->execute([$drawId]);
    $entries = $entries->fetchAll();

    // Build per-user score map
    // $scores[userId] = ['best_code'=>'...', 'matched'=>int, 'entry_count'=>int, 'created_at'=>'...']
    $scores = [];
    foreach ($entries as $entry) {
        $uid        = (int)$entry['user_id'];
        $entryCode  = $entry['code'];
        $matched    = 0;

        for ($i = 0; $i < 15; $i++) {
            if (isset($entryCode[$i], $winningCode[$i]) && $entryCode[$i] === $winningCode[$i]) {
                $matched++;
            }
        }

        if (!isset($scores[$uid])) {
            $scores[$uid] = [
                'user_id'         => $uid,
                'best_code'       => $entryCode,
                'matched'         => $matched,
                'entry_count'     => 0,
                'user_created_at' => $entry['user_created_at'],
            ];
        }

        // Keep best code (most matched digits)
        if ($matched > $scores[$uid]['matched']) {
            $scores[$uid]['matched']   = $matched;
            $scores[$uid]['best_code'] = $entryCode;
        }

        $scores[$uid]['entry_count']++;
    }

    // ── Sort: matched DESC → entry_count DESC → user_created_at ASC ──
    usort($scores, function ($a, $b) {
        if ($b['matched']      !== $a['matched'])      return $b['matched']      - $a['matched'];
        if ($b['entry_count']  !== $a['entry_count'])  return $b['entry_count']  - $a['entry_count'];
        return strtotime($a['user_created_at']) - strtotime($b['user_created_at']);
    });

    $winner       = $scores[0];
    $tiebreaker   = null;

    // Detect which tiebreaker was used
    if (count($scores) > 1 && $scores[0]['matched'] === $scores[1]['matched']) {
        if ($scores[0]['entry_count'] !== $scores[1]['entry_count']) {
            $tiebreaker = 'most_entries';
        } else {
            $tiebreaker = 'earliest_registration';
        }
    }

    cronLog("  → winner user_id #{$winner['user_id']} matched {$winner['matched']}/15"
        . ($tiebreaker ? " (tiebreaker: {$tiebreaker})" : ''));

    // ── Ensure draw_rankings table exists ─────────────────
    $db->exec("CREATE TABLE IF NOT EXISTS draw_rankings (
        id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        draw_id        INT UNSIGNED NOT NULL,
        user_id        INT UNSIGNED NOT NULL,
        rank_position  TINYINT UNSIGNED NOT NULL,
        user_code      CHAR(15) NOT NULL,
        matched_digits TINYINT UNSIGNED NOT NULL DEFAULT 0,
        entries_count  INT UNSIGNED NOT NULL DEFAULT 0,
        tiebreaker     VARCHAR(100) DEFAULT NULL,
        created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_draw_rank (draw_id, rank_position),
        KEY idx_draw (draw_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // ── Write top 3 to draw_rankings ─────────────────────
    $top3 = array_slice($scores, 0, 3);
    foreach ($top3 as $pos => $ranked) {
        $db->prepare(
            "INSERT INTO draw_rankings
               (draw_id, user_id, rank_position, user_code, matched_digits, entries_count, tiebreaker)
             VALUES (?,?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE
               user_code=VALUES(user_code), matched_digits=VALUES(matched_digits)"
        )->execute([
            $drawId,
            $ranked['user_id'],
            $pos + 1,
            $ranked['best_code'],
            $ranked['matched'],
            $ranked['entry_count'],
            $pos === 0 ? $tiebreaker : null,
        ]);
    }

    // ── Insert winner into draw_winners ───────────────────
    $db->prepare(
        "INSERT INTO draw_winners
           (draw_id, user_id, winning_code, user_code, matched_digits, tiebreaker_used, announced_at)
         VALUES (?,?,?,?,?,?,NOW())"
    )->execute([
        $drawId,
        $winner['user_id'],
        $winningCode,
        $winner['best_code'],
        $winner['matched'],
        $tiebreaker,
    ]);

    // ── Update draw record ────────────────────────────────
    $db->prepare(
        "UPDATE draws
         SET status='completed', winning_code=?, winner_user_id=?,
             finalized_at=NOW(), updated_at=NOW()
         WHERE id=?"
    )->execute([$winningCode, $winner['user_id'], $drawId]);

    // ── Mark all entered codes in this draw as 'used' ─────
    $db->prepare(
        "UPDATE codes SET status='used'
         WHERE id IN (SELECT code_id FROM draw_entries WHERE draw_id=?)"
    )->execute([$drawId]);

    // ── Create winner notification (in-app) ───────────────
    createNotification(
        $winner['user_id'],
        '🏆 You Won! — ' . $draw['title'],
        "Congratulations! Your code {$winner['best_code']} matched {$winner['matched']}/15 digits of the winning code {$winningCode}. "
        . "Please contact ZoeFeeds admin to claim your prize.",
        'draw'
    );

    // ── Send winner email ─────────────────────────────────
    $winnerUser = $db->prepare("SELECT full_name, email FROM users WHERE id=?");
    $winnerUser->execute([$winner['user_id']]);
    $winnerUser = $winnerUser->fetch();

    if ($winnerUser && $winnerUser['email']) {
        $subject = '🏆 You Won the ' . $draw['title'] . ' Draw!';
        $emailBody = '
<!DOCTYPE html><html><head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#0a0f1a;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#0a0f1a;padding:40px 20px;">
  <tr><td align="center">
    <table width="100%" style="max-width:520px;background:#111827;border-radius:16px;border:1px solid rgba(234,179,8,0.3);overflow:hidden;">
      <tr>
        <td style="background:linear-gradient(135deg,#f97316,#ea580c);padding:28px 32px;text-align:center;">
          <div style="font-size:28px;font-weight:900;color:#fff;">ZoeFeeds</div>
          <div style="font-size:12px;color:rgba(255,255,255,0.8);margin-top:4px;letter-spacing:1px;">WINNER ANNOUNCEMENT</div>
        </td>
      </tr>
      <tr>
        <td style="padding:32px;">
          <div style="text-align:center;font-size:56px;margin-bottom:16px;">🏆</div>
          <h2 style="color:#fbbf24;text-align:center;margin:0 0 8px;">Congratulations, '.htmlspecialchars($winnerUser['full_name']).'!</h2>
          <p style="color:#9ca3af;text-align:center;font-size:14px;margin:0 0 24px;">You are the winner of the <strong style="color:#fff;">'.htmlspecialchars($draw['title']).'</strong> draw!</p>

          <div style="background:#0a0f1a;border:1px solid rgba(234,179,8,0.25);border-radius:12px;padding:20px;margin-bottom:20px;">
            <div style="font-size:11px;color:#6b7280;letter-spacing:2px;text-transform:uppercase;margin-bottom:8px;">Your Winning Code</div>
            <div style="font-size:22px;font-weight:900;letter-spacing:6px;color:#f97316;font-family:Courier New,monospace;">'.htmlspecialchars($winner['best_code']).'</div>
            <div style="font-size:12px;color:#6b7280;margin-top:8px;">Matched <strong style="color:#fbbf24;">'.$winner['matched'].'/15</strong> digits of the winning number</div>
          </div>

          <div style="background:#0a0f1a;border:1px solid rgba(255,255,255,0.06);border-radius:12px;padding:20px;margin-bottom:24px;">
            <div style="font-size:11px;color:#6b7280;letter-spacing:2px;text-transform:uppercase;margin-bottom:8px;">Prize</div>
            <div style="font-size:14px;color:#fff;">'.htmlspecialchars($draw['prize_details'] ?? 'Contact admin for prize details').'</div>
          </div>

          <p style="color:#9ca3af;font-size:13px;line-height:1.6;">
            To claim your prize, please contact ZoeFeeds admin through the official website or support channels. Have your account details ready for verification.
          </p>

          <div style="text-align:center;margin-top:20px;">
            <a href="'.APP_URL.'/user/dashboard.php"
               style="display:inline-block;background:linear-gradient(135deg,#f97316,#ea580c);color:#fff;text-decoration:none;font-weight:700;font-size:14px;padding:14px 32px;border-radius:12px;">
              Go to My Dashboard →
            </a>
          </div>
        </td>
      </tr>
      <tr>
        <td style="padding:16px 32px 24px;border-top:1px solid rgba(255,255,255,0.06);text-align:center;">
          <p style="margin:0;font-size:11px;color:#4b5563;">
            ZoeFeeds Loyalty Reward Platform ·
            <a href="'.APP_URL.'" style="color:#f97316;text-decoration:none;">www.zoefeeds.com</a>
          </p>
        </td>
      </tr>
    </table>
  </td></tr>
</table>
</body></html>';
        smtpmailer($winnerUser['email'], $subject, $emailBody);
        cronLog("  → winner email sent to {$winnerUser['email']}");
    }

    // ── Notify admin of completion ────────────────────────
    $admins = $db->query("SELECT email, full_name FROM admins WHERE status='active'")->fetchAll();
    foreach ($admins as $admin) {
        if (!$admin['email']) continue;
        $subject = "Draw Completed: {$draw['title']} — Winner Selected";
        $body    = "
<p>Hi {$admin['full_name']},</p>
<p>Draw <strong>#{$drawId}: {$draw['title']}</strong> has been automatically finalized.</p>
<ul>
  <li>Winning code: <code>{$winningCode}</code></li>
  <li>Winner: User #{$winner['user_id']}</li>
  <li>Matched: {$winner['matched']}/15 digits</li>
  <li>Total entries: {$totalEntries}</li>
  " . ($tiebreaker ? "<li>Tiebreaker used: {$tiebreaker}</li>" : '') . "
</ul>
<p><a href='".APP_URL."/admin/draw-manage.php?id={$drawId}'>View Full Result →</a></p>
<p>— ZoeFeeds Cron System</p>";
        smtpmailer($admin['email'], $subject, $body);
    }

    // ── Audit log ─────────────────────────────────────────
    auditLog('system', 0, 'auto_finalize_draw',
        "Draw #{$drawId} '{$draw['title']}' finalized. Winner: user #{$winner['user_id']}, matched {$winner['matched']}/15",
        'draw', $drawId);

    cronLog("  → draw #{$drawId} COMPLETED. Winner: user #{$winner['user_id']}");
}

cronLog('─── Draw Cron Finished ──────────────────────────');

// Release lock
flock($lock, LOCK_UN);
fclose($lock);
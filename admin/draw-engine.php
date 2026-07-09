<?php
/**
 * includes/draw-engine.php
 *
 * Core draw finalization engine.
 * Called by cron/finalize-expired-draws.php — never called directly by the browser.
 *
 * Public API:
 *   finalize_all_expired_draws(PDO $db, string $actorType, int $actorId): array
 *   finalize_single_draw(PDO $db, int $drawId, string $actorType, int $actorId): array
 *
 * Both return an array of result records:
 *   ['draw_id', 'draw_title', 'success', 'winning_code', 'total_entries',
 *    'top3', 'error']
 */

if (!defined('APP_URL')) {
    // Safety — should never be loaded standalone
    exit('Direct access not permitted.');
}

// ── mailer is already loaded by the cron script ────────────
// require_once is safe to call again if needed
if (!function_exists('smtpmailer')) {
    require_once __DIR__ . '/../mailer/index.php';
}

/**
 * Auto-activates pending draws whose start_date has arrived,
 * then finalizes all active draws whose end_date has passed.
 *
 * @return array  One result record per draw that was processed.
 */
function finalize_all_expired_draws(PDO $db, string $actorType = 'system', int $actorId = 0): array
{
    // ── Step 1: activate pending draws whose start_date has arrived ──
    $pending = $db->query(
        "SELECT id, title FROM draws
         WHERE status = 'pending' AND start_date <= NOW()"
    )->fetchAll();

    foreach ($pending as $p) {
        $db->prepare(
            "UPDATE draws SET status='active', updated_at=NOW() WHERE id=?"
        )->execute([$p['id']]);

        _draw_audit($db, $actorType, $actorId, 'auto_activate_draw',
            "Draw #{$p['id']} '{$p['title']}' auto-activated", 'draw', $p['id']);

        error_log("[draw-engine] AUTO-ACTIVATED draw #{$p['id']}: {$p['title']}");
    }

    // ── Step 2: finalize active/paused draws that have ended ──
    $expired = $db->query(
        "SELECT * FROM draws
         WHERE status IN ('active','paused') AND end_date <= NOW()"
    )->fetchAll();

    $results = [];
    foreach ($expired as $draw) {
        $results[] = finalize_single_draw($db, (int)$draw['id'], $actorType, $actorId, $draw);
    }

    return $results;
}

/**
 * Finalizes one specific draw.
 * Safe to call even if the draw is already completed — returns an error record.
 *
 * @param  array|null $drawRow  Optional pre-fetched draw row (avoids extra query).
 */
function finalize_single_draw(
    PDO    $db,
    int    $drawId,
    string $actorType = 'system',
    int    $actorId   = 0,
    ?array $drawRow   = null
): array {

    // ── Load draw ─────────────────────────────────────────
    if ($drawRow === null) {
        $s = $db->prepare("SELECT * FROM draws WHERE id=?");
        $s->execute([$drawId]);
        $drawRow = $s->fetch();
    }

    if (!$drawRow) {
        return _err($drawId, 'unknown', "Draw #{$drawId} not found.");
    }

    $draw = $drawRow;

    // ── Guard: skip if already completed / cancelled ──────
    if (!in_array($draw['status'], ['active','paused'])) {
        return _err($drawId, $draw['title'], "Draw is already {$draw['status']} — skipping.");
    }

    // ── Guard: skip if already has a winner ───────────────
    $existing = $db->prepare("SELECT id FROM draw_winners WHERE draw_id=?");
    $existing->execute([$drawId]);
    if ($existing->fetch()) {
        // Ensure status is set to completed in case something drifted
        $db->prepare("UPDATE draws SET status='completed',updated_at=NOW() WHERE id=?")->execute([$drawId]);
        return _err($drawId, $draw['title'], "Draw already has a winner — marked completed.");
    }

    // ── Count entries ─────────────────────────────────────
    $s = $db->prepare("SELECT COUNT(*) FROM draw_entries WHERE draw_id=?");
    $s->execute([$drawId]);
    $totalEntries = (int)$s->fetchColumn();

    // ── No entries case ───────────────────────────────────
    if ($totalEntries === 0) {
        $db->prepare(
            "UPDATE draws SET status='completed', updated_at=NOW() WHERE id=?"
        )->execute([$drawId]);

        _draw_audit($db, $actorType, $actorId, 'draw_no_entries',
            "Draw #{$drawId} ended with no entries", 'draw', $drawId);

        _notify_admins_no_entries($db, $draw);

        return [
            'draw_id'      => $drawId,
            'draw_title'   => $draw['title'],
            'success'      => true,
            'winning_code' => null,
            'total_entries'=> 0,
            'top3'         => [],
            'error'        => null,
            'note'         => 'no_entries',
        ];
    }

    // ── Generate 15-digit random winning code ─────────────
    $winningCode = '';
    for ($i = 0; $i < 15; $i++) {
        $winningCode .= (string)random_int(0, 9);
    }

    // ── Score every entered code ──────────────────────────
    $entries = $db->prepare(
        "SELECT de.user_id, de.code_id, c.code,
                de.entered_at, u.created_at AS user_created_at,
                u.full_name,  u.email,      u.phone
         FROM draw_entries de
         JOIN codes c ON c.id = de.code_id
         JOIN users u ON u.id = de.user_id
         WHERE de.draw_id = ?"
    );
    $entries->execute([$drawId]);
    $entries = $entries->fetchAll();

    // Build per-user score: keep best code, count entries
    $scores = [];
    foreach ($entries as $entry) {
        $uid       = (int)$entry['user_id'];
        $code      = $entry['code'];
        $matched   = _count_matched($code, $winningCode);

        if (!isset($scores[$uid])) {
            $scores[$uid] = [
                'user_id'         => $uid,
                'full_name'       => $entry['full_name'],
                'email'           => $entry['email'],
                'phone'           => $entry['phone'],
                'best_code'       => $code,
                'matched'         => $matched,
                'entry_count'     => 0,
                'user_created_at' => $entry['user_created_at'],
            ];
        }

        if ($matched > $scores[$uid]['matched']) {
            $scores[$uid]['matched']   = $matched;
            $scores[$uid]['best_code'] = $code;
        }

        $scores[$uid]['entry_count']++;
    }

    // ── Sort: matched DESC → entry_count DESC → created_at ASC ──
    usort($scores, function ($a, $b) {
        if ($b['matched']      !== $a['matched'])     return $b['matched']     - $a['matched'];
        if ($b['entry_count']  !== $a['entry_count']) return $b['entry_count'] - $a['entry_count'];
        return strtotime($a['user_created_at']) - strtotime($b['user_created_at']);
    });

    $winner     = $scores[0];
    $tiebreaker = null;

    if (count($scores) > 1 && $scores[0]['matched'] === $scores[1]['matched']) {
        $tiebreaker = ($scores[0]['entry_count'] !== $scores[1]['entry_count'])
            ? 'most_entries'
            : 'earliest_registration';
    }

    // ── Ensure draw_rankings table exists ─────────────────
    $db->exec("CREATE TABLE IF NOT EXISTS `draw_rankings` (
        `id`             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        `draw_id`        INT UNSIGNED NOT NULL,
        `user_id`        INT UNSIGNED NOT NULL,
        `rank_position`  TINYINT UNSIGNED NOT NULL,
        `user_code`      CHAR(15) NOT NULL,
        `matched_digits` TINYINT UNSIGNED NOT NULL DEFAULT 0,
        `entries_count`  INT UNSIGNED NOT NULL DEFAULT 0,
        `tiebreaker`     VARCHAR(100) DEFAULT NULL,
        `created_at`     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY `uq_draw_rank` (`draw_id`,`rank_position`),
        KEY `idx_draw` (`draw_id`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    // ── Save top 3 to draw_rankings ───────────────────────
    $top3      = array_slice($scores, 0, 3);
    $top3Out   = [];
    foreach ($top3 as $pos => $ranked) {
        $db->prepare(
            "INSERT INTO draw_rankings
               (draw_id, user_id, rank_position, user_code, matched_digits, entries_count, tiebreaker)
             VALUES (?,?,?,?,?,?,?)
             ON DUPLICATE KEY UPDATE
               user_code=VALUES(user_code),
               matched_digits=VALUES(matched_digits),
               entries_count=VALUES(entries_count)"
        )->execute([
            $drawId,
            $ranked['user_id'],
            $pos + 1,
            $ranked['best_code'],
            $ranked['matched'],
            $ranked['entry_count'],
            $pos === 0 ? $tiebreaker : null,
        ]);

        $top3Out[] = [
            'rank'           => $pos + 1,
            'user_id'        => $ranked['user_id'],
            'name'           => $ranked['full_name'],
            'code'           => $ranked['best_code'],
            'matched_digits' => $ranked['matched'],
            'entry_count'    => $ranked['entry_count'],
        ];
    }

    // ── Insert into draw_winners ──────────────────────────
    // Ensure user_code column exists (added in migration)
    try {
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
    } catch (\PDOException $e) {
        // Fallback: user_code column may not exist yet on older installs
        $db->prepare(
            "INSERT INTO draw_winners
               (draw_id, user_id, winning_code, matched_digits, tiebreaker_used, announced_at)
             VALUES (?,?,?,?,?,NOW())"
        )->execute([
            $drawId,
            $winner['user_id'],
            $winningCode,
            $winner['matched'],
            $tiebreaker,
        ]);
    }

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

    // ── In-app notification to winner ─────────────────────
    if (function_exists('createNotification')) {
        createNotification(
            $winner['user_id'],
            '🏆 You Won! — ' . $draw['title'],
            "Congratulations! Your code {$winner['best_code']} matched "
            . "{$winner['matched']}/15 digits of the winning code {$winningCode}. "
            . "Contact ZoeFeeds admin to claim your prize.",
            'draw'
        );
    }

    // ── Email winner ──────────────────────────────────────
    if (!empty($winner['email'])) {
        _send_winner_email($winner, $draw, $winningCode);
    }

    // ── Email admins ──────────────────────────────────────
    _notify_admins_completed($db, $draw, $winningCode, $winner, $tiebreaker, $totalEntries);

    // ── Audit log ─────────────────────────────────────────
    _draw_audit($db, $actorType, $actorId, 'auto_finalize_draw',
        "Draw #{$drawId} '{$draw['title']}' finalized. "
        . "Winner: user #{$winner['user_id']}, matched {$winner['matched']}/15",
        'draw', $drawId);

    return [
        'draw_id'       => $drawId,
        'draw_title'    => $draw['title'],
        'success'       => true,
        'winning_code'  => $winningCode,
        'total_entries' => $totalEntries,
        'top3'          => $top3Out,
        'error'         => null,
        'tiebreaker'    => $tiebreaker,
        'winner_id'     => $winner['user_id'],
        'winner_name'   => $winner['full_name'],
        'matched_digits'=> $winner['matched'],
    ];
}


// ============================================================
// PRIVATE HELPERS
// ============================================================

/** Count digit-position matches between two 15-char strings */
function _count_matched(string $code, string $winning): int {
    $matched = 0;
    $len     = min(strlen($code), strlen($winning), 15);
    for ($i = 0; $i < $len; $i++) {
        if ($code[$i] === $winning[$i]) $matched++;
    }
    return $matched;
}

/** Build a standard error result record */
function _err(int $drawId, string $title, string $msg): array {
    error_log("[draw-engine] SKIP draw #{$drawId}: {$msg}");
    return [
        'draw_id'      => $drawId,
        'draw_title'   => $title,
        'success'      => false,
        'winning_code' => null,
        'total_entries'=> 0,
        'top3'         => [],
        'error'        => $msg,
    ];
}

/** Write to audit_logs (mirrors the auditLog() helper in config) */
function _draw_audit(PDO $db, string $actorType, int $actorId,
                     string $action, string $desc,
                     string $entityType = '', int $entityId = 0): void {
    try {
        $db->prepare(
            "INSERT INTO audit_logs
               (actor_type, actor_id, action, description, entity_type, entity_id,
                ip_address, created_at)
             VALUES (?,?,?,?,?,?,?,NOW())"
        )->execute([$actorType, $actorId, $action, $desc,
                    $entityType ?: null, $entityId ?: null, '127.0.0.1']);
    } catch (\Exception $e) {
        error_log("[draw-engine] audit_log error: " . $e->getMessage());
    }
}

/** Send winner congratulations email */
function _send_winner_email(array $winner, array $draw, string $winningCode): void {
    $subject = '🏆 You Won — ' . $draw['title'];
    $body    = '
<!DOCTYPE html><html><head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#0a0f1a;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#0a0f1a;padding:40px 20px;">
<tr><td align="center">
<table width="100%" style="max-width:520px;background:#111827;border-radius:16px;border:1px solid rgba(234,179,8,0.3);overflow:hidden;">
  <!-- Header -->
  <tr>
    <td style="background:linear-gradient(135deg,#f97316,#ea580c);padding:28px 32px;text-align:center;">
      <div style="font-size:28px;font-weight:900;color:#fff;">ZoeFeeds</div>
      <div style="font-size:12px;color:rgba(255,255,255,0.8);margin-top:4px;letter-spacing:1px;">WINNER ANNOUNCEMENT</div>
    </td>
  </tr>
  <!-- Body -->
  <tr>
    <td style="padding:32px;">
      <div style="text-align:center;font-size:56px;margin-bottom:16px;">🏆</div>
      <h2 style="color:#fbbf24;text-align:center;margin:0 0 8px;">Congratulations, '.htmlspecialchars($winner['full_name']).'!</h2>
      <p style="color:#9ca3af;text-align:center;font-size:14px;margin:0 0 24px;">
        You are the winner of the <strong style="color:#fff;">'.htmlspecialchars($draw['title']).'</strong> draw!
      </p>

      <div style="background:#0a0f1a;border:1px solid rgba(234,179,8,0.2);border-radius:12px;padding:20px;margin-bottom:16px;">
        <div style="font-size:11px;color:#6b7280;letter-spacing:2px;text-transform:uppercase;margin-bottom:6px;">Your Winning Code</div>
        <div style="font-size:22px;font-weight:900;letter-spacing:6px;color:#f97316;font-family:Courier New,monospace;">'.htmlspecialchars($winner['best_code']).'</div>
        <div style="font-size:12px;color:#6b7280;margin-top:8px;">
          Matched <strong style="color:#fbbf24;">'.$winner['matched'].'/15</strong> digits of winning code
          <span style="font-family:Courier New,monospace;color:#9ca3af;">'.htmlspecialchars($winningCode).'</span>
        </div>
      </div>

      <div style="background:#0a0f1a;border:1px solid rgba(255,255,255,0.06);border-radius:12px;padding:16px;margin-bottom:20px;">
        <div style="font-size:11px;color:#6b7280;letter-spacing:2px;text-transform:uppercase;margin-bottom:6px;">Prize</div>
        <div style="font-size:14px;color:#fff;">'.htmlspecialchars($draw['prize_details'] ?? 'Contact admin for prize details').'</div>
      </div>

      <p style="color:#9ca3af;font-size:13px;line-height:1.6;">
        To claim your prize please contact ZoeFeeds through our official website. Have your account details ready for identity verification.
      </p>

      <div style="text-align:center;margin-top:24px;">
        <a href="'.APP_URL.'/user/dashboard.php"
           style="display:inline-block;background:linear-gradient(135deg,#f97316,#ea580c);color:#fff;text-decoration:none;font-weight:700;font-size:14px;padding:14px 32px;border-radius:12px;">
          Go to My Dashboard →
        </a>
      </div>
    </td>
  </tr>
  <!-- Footer -->
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

    smtpmailer($winner['email'], $subject, $body);
}

/** Notify all active admins that a draw completed */
function _notify_admins_completed(PDO $db, array $draw, string $winningCode,
                                   array $winner, ?string $tiebreaker, int $totalEntries): void {
    $admins = $db->query(
        "SELECT email, full_name FROM admins WHERE status='active' AND email != ''"
    )->fetchAll();

    foreach ($admins as $admin) {
        $subject = "Draw Completed: {$draw['title']} — Winner Selected";
        $body    = "
<p>Hi {$admin['full_name']},</p>
<p>Draw <strong>#{$draw['id']}: {$draw['title']}</strong> has been automatically finalized.</p>
<table style='border-collapse:collapse;font-size:14px;'>
  <tr><td style='padding:4px 12px 4px 0;color:#6b7280;'>Winning code</td><td style='font-family:monospace;'>{$winningCode}</td></tr>
  <tr><td style='padding:4px 12px 4px 0;color:#6b7280;'>Winner</td><td>{$winner['full_name']} (User #{$winner['user_id']})</td></tr>
  <tr><td style='padding:4px 12px 4px 0;color:#6b7280;'>Matched</td><td>{$winner['matched']}/15 digits</td></tr>
  <tr><td style='padding:4px 12px 4px 0;color:#6b7280;'>Total entries</td><td>{$totalEntries}</td></tr>
  " . ($tiebreaker ? "<tr><td style='padding:4px 12px 4px 0;color:#6b7280;'>Tiebreaker</td><td>{$tiebreaker}</td></tr>" : '') . "
</table>
<p style='margin-top:16px;'>
  <a href='".APP_URL."/admin/draw-manage.php?id={$draw['id']}'
     style='background:#f97316;color:#fff;padding:10px 20px;border-radius:8px;text-decoration:none;font-weight:bold;'>
    View Full Result →
  </a>
</p>
<p style='color:#6b7280;font-size:12px;margin-top:16px;'>— ZoeFeeds Automatic Draw System</p>";

        smtpmailer($admin['email'], $subject, $body);
    }
}

/** Notify admins when a draw ended with no entries */
function _notify_admins_no_entries(PDO $db, array $draw): void {
    $admins = $db->query(
        "SELECT email, full_name FROM admins WHERE status='active' AND email != ''"
    )->fetchAll();

    foreach ($admins as $admin) {
        $subject = "⚠️ Draw Ended With No Entries — {$draw['title']}";
        $body    = "
<p>Hi {$admin['full_name']},</p>
<p>Draw <strong>#{$draw['id']}: {$draw['title']}</strong> has ended but received <strong>no entries</strong>.</p>
<p>The draw has been marked as completed with no winner. Please review.</p>
<p>
  <a href='".APP_URL."/admin/draw-manage.php?id={$draw['id']}'
     style='background:#f97316;color:#fff;padding:10px 20px;border-radius:8px;text-decoration:none;font-weight:bold;'>
    View Draw →
  </a>
</p>
<p style='color:#6b7280;font-size:12px;'>— ZoeFeeds Automatic Draw System</p>";

        smtpmailer($admin['email'], $subject, $body);
    }
}

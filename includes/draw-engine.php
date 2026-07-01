<?php
/**
 * includes/draw-engine.php
 *
 * Core draw-finalization logic shared by:
 *   - ajax/finalize-draw.php   (admin manual "Finalize Now" button)
 *   - cron/finalize-expired-draws.php (automatic cron run every minute)
 *
 * finalize_draw($db, $drawId, $actorType, $actorId): array
 *   Generates a random 15-digit winning code, scores every participant's
 *   best code against it, ranks the top 3, saves the winner + runners-up,
 *   marks the draw completed, consumes all entered codes, and notifies
 *   the winner. Throws \Exception on failure (caller should catch).
 */

if (!function_exists('finalize_draw')) {

function finalize_draw(PDO $db, int $drawId, string $actorType = 'system', int $actorId = 0): array {

    // Lock and fetch draw
    $draw = $db->prepare("SELECT * FROM draws WHERE id=? FOR UPDATE");
    $draw->execute([$drawId]);
    $draw = $draw->fetch();

    if (!$draw)                                   throw new \Exception('Draw not found');
    if ($draw['status'] === 'completed')          throw new \Exception('Draw already finalized');
    if (!in_array($draw['status'], ['active','paused'])) throw new \Exception('Draw is not active');

    // Fetch all entries
    $entries = $db->prepare(
        "SELECT de.id as entry_id, de.user_id, de.code_id, de.entered_at,
                c.code,
                u.created_at as user_registered_at
         FROM draw_entries de
         JOIN codes c ON de.code_id = c.id
         JOIN users u ON de.user_id = u.id
         WHERE de.draw_id = ?"
    );
    $entries->execute([$drawId]);
    $entries = $entries->fetchAll();

    if (empty($entries)) {
        // No entries — mark draw completed with no winner, so it stops being "active"
        $db->prepare("UPDATE draws SET status='completed', finalized_by=?, finalized_at=NOW() WHERE id=?")
           ->execute([$actorId ?: null, $drawId]);
        auditLog($actorType, $actorId, 'finalize_draw_no_entries', "Draw #{$drawId} finalized with zero entries — no winner.", 'draw', $drawId);
        throw new \Exception('No entries in this draw. Marked completed with no winner.');
    }

    $db->beginTransaction();
    try {
        // ── STEP 1: Generate the 15-digit winning code ───────────
        $winningCode = '';
        for ($i = 0; $i < 15; $i++) $winningCode .= random_int(0, 9);

        // ── STEP 2: Score each participant ────────────────────────
        $userScores = [];
        foreach ($entries as $e) {
            $uid  = (int)$e['user_id'];
            $code = $e['code'];

            $matches = 0;
            for ($pos = 0; $pos < 15; $pos++) {
                if ($code[$pos] === $winningCode[$pos]) $matches++;
            }

            if (!isset($userScores[$uid])) {
                $userScores[$uid] = [
                    'user_id'        => $uid,
                    'best_matches'   => 0,
                    'best_code'      => $code,
                    'entries'        => 0,
                    'earliest_entry' => $e['entered_at'],
                    'registered_at'  => $e['user_registered_at'],
                ];
            }

            if ($matches > $userScores[$uid]['best_matches']) {
                $userScores[$uid]['best_matches'] = $matches;
                $userScores[$uid]['best_code']    = $code;
            }
            $userScores[$uid]['entries']++;

            if (strtotime($e['entered_at']) < strtotime($userScores[$uid]['earliest_entry'])) {
                $userScores[$uid]['earliest_entry'] = $e['entered_at'];
            }
        }

        // ── STEP 3: Rank by tiebreaker hierarchy ──────────────────
        // 1. Most matching digits (desc)
        // 2. Most entries (desc)
        // 3. Earliest registration on platform (asc)
        usort($userScores, function($a, $b) {
            if ($a['best_matches'] !== $b['best_matches'])
                return $b['best_matches'] <=> $a['best_matches'];
            if ($a['entries'] !== $b['entries'])
                return $b['entries'] <=> $a['entries'];
            return strtotime($a['registered_at']) <=> strtotime($b['registered_at']);
        });

        $ranked = array_values($userScores);
        $top3   = array_slice($ranked, 0, 3);

        $winner     = $top3[0];
        $winnerId   = $winner['user_id'];
        $winnerCode = $winner['best_code'];
        $matchCount = $winner['best_matches'];

        $tiebreaker = null;
        if (count($ranked) > 1 && $ranked[0]['best_matches'] === $ranked[1]['best_matches']) {
            $tiebreaker = ($ranked[0]['entries'] !== $ranked[1]['entries'])
                ? 'most_entries' : 'earliest_registration';
        }

        // ── STEP 4: Save winner (rank 1) ───────────────────────────
        $db->prepare(
            "INSERT INTO draw_winners
             (draw_id, user_id, winning_code, user_code, matched_digits, tiebreaker_used, announced_at)
             VALUES (?,?,?,?,?,?,NOW())"
        )->execute([$drawId, $winnerId, $winningCode, $winnerCode, $matchCount, $tiebreaker]);

        // ── STEP 5: Save top-3 ranking table (1st, 2nd, 3rd) ──────
        foreach ($top3 as $rank => $entrant) {
            $db->prepare(
                "INSERT INTO draw_rankings
                 (draw_id, user_id, rank_position, user_code, matched_digits, entries_count)
                 VALUES (?,?,?,?,?,?)"
            )->execute([
                $drawId,
                $entrant['user_id'],
                $rank + 1,
                $entrant['best_code'],
                $entrant['best_matches'],
                $entrant['entries'],
            ]);
        }

        // ── STEP 6: Update draw row ────────────────────────────────
        $db->prepare(
            "UPDATE draws SET status='completed', winning_code=?, winner_user_id=?,
             finalized_by=?, finalized_at=NOW() WHERE id=?"
        )->execute([$winningCode, $winnerId, $actorId ?: null, $drawId]);

        // ── STEP 7: Consume all entered codes, deduct balances ────
        foreach ($entries as $e) {
            $db->prepare("UPDATE codes SET status='used' WHERE id=?")->execute([$e['code_id']]);
            $db->prepare("UPDATE users SET balance=GREATEST(0,balance-1) WHERE id=?")->execute([$e['user_id']]);
        }

        // ── STEP 8: Notify winner + runners-up ────────────────────
        $namesStmt = $db->prepare("SELECT id, full_name FROM users WHERE id IN (" . implode(',', array_fill(0, count($top3), '?')) . ")");
        $namesStmt->execute(array_column($top3, 'user_id'));
        $names = [];
        foreach ($namesStmt->fetchAll() as $n) $names[$n['id']] = $n['full_name'];

        createNotification(
            $winnerId,
            '🏆 You Won! Congratulations!',
            "You won the draw \"{$draw['title']}\"! Your code {$winnerCode} matched {$matchCount} digit(s) of the winning number {$winningCode}. ZoeFeeds will contact you soon.",
            'draw'
        );

        $rankLabels = [2 => '2nd Place 🥈', 3 => '3rd Place 🥉'];
        foreach ($top3 as $rank => $entrant) {
            if ($rank === 0) continue; // winner already notified above
            $pos = $rank + 1;
            createNotification(
                $entrant['user_id'],
                "🎖️ You Finished {$rankLabels[$pos]}!",
                "The draw \"{$draw['title']}\" has ended. You finished in {$rankLabels[$pos]} with {$entrant['best_matches']}/15 matching digits. Thanks for participating!",
                'draw'
            );
        }

        auditLog($actorType, $actorId, 'finalize_draw',
            "Draw #{$drawId} finalized. Winning code: {$winningCode}. " .
            "Winner: user #{$winnerId} ({$names[$winnerId]}) with {$matchCount} matches. " .
            "Top 3 recorded.",
            'draw', $drawId
        );

        $db->commit();

        return [
            'success'        => true,
            'draw_id'        => $drawId,
            'draw_title'     => $draw['title'],
            'winning_code'   => $winningCode,
            'total_entries'  => count($entries),
            'tiebreaker'     => $tiebreaker,
            'top3'           => array_map(function($e, $rank) use ($names) {
                return [
                    'rank'           => $rank + 1,
                    'user_id'        => $e['user_id'],
                    'name'           => $names[$e['user_id']] ?? 'Unknown',
                    'code'           => $e['best_code'],
                    'matched_digits' => $e['best_matches'],
                    'entries'        => $e['entries'],
                ];
            }, $top3, array_keys($top3)),
        ];

    } catch (\Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

}

if (!function_exists('finalize_all_expired_draws')) {
    /**
     * Finds every draw with status IN ('active','paused') whose end_date has passed,
     * and finalizes each one. Returns an array of results (including errors per-draw).
     * Designed to be safe to call repeatedly (idempotent — already-completed draws are skipped).
     */
    function finalize_all_expired_draws(PDO $db, string $actorType = 'system', int $actorId = 0): array {
        $expired = $db->query(
            "SELECT id, title FROM draws
             WHERE status IN ('active','paused') AND end_date <= NOW()
             ORDER BY end_date ASC"
        )->fetchAll();

        $results = [];
        foreach ($expired as $row) {
            try {
                $result = finalize_draw($db, (int)$row['id'], $actorType, $actorId);
                $results[] = $result;
            } catch (\Exception $e) {
                $results[] = [
                    'success'    => false,
                    'draw_id'    => (int)$row['id'],
                    'draw_title' => $row['title'],
                    'error'      => $e->getMessage(),
                ];
            }
        }
        return $results;
    }
}

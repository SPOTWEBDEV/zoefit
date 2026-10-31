<?php
/**
 * Entry-triggered draws.
 *
 * A draw has a type:
 *   'timed'   the original type: runs between start and end date (cron/admin ends it).
 *   'entries' ends ITSELF the moment it reaches target_entries. Status becomes 'ended'
 *             (= "awaiting winner"), exactly like a draw the admin ended by hand, so the
 *             existing admin/select-winner.php flow picks it up unchanged.
 *
 * Entries are counted as rows in draw_entries for the draw (the same count admin/draws.php shows).
 *
 * WHERE TO CALL THESE: wherever a user's entry is inserted into draw_entries (your enter-draw
 * endpoint). Inside ONE database transaction:
 *
 *     $db->beginTransaction();
 *     $slot = reserveDrawEntrySlot($db, $drawId, $howManyEntriesThisSubmission);
 *     if (!$slot['ok']) { $db->rollBack(); // then show $slot['message'] to the user }
 *     ... your existing INSERT INTO draw_entries ...
 *     $triggered = finalizeDrawAfterEntry($db, $drawId);   // true => target reached, draw just ended
 *     $db->commit();
 *
 * reserveDrawEntrySlot() locks the draw row, so two people entering at the same instant can never
 * push a draw past its target (the 21st entry of a 20-entry draw is refused, not accepted).
 */

/** Sentinel end date stored for entry draws that have no deadline (end_date is NOT NULL in the schema). */
if (!defined('DRAW_NO_DEADLINE')) define('DRAW_NO_DEADLINE', '2099-12-31 23:59:00');

function drawHasDeadline(?string $endDate): bool {
    return $endDate !== null && $endDate !== '' && substr($endDate, 0, 4) < '2099';
}

function drawEntryCount(PDO $db, int $drawId): int {
    $s = $db->prepare("SELECT COUNT(*) FROM draw_entries WHERE draw_id = ?");
    $s->execute([$drawId]);
    return (int) $s->fetchColumn();
}

/**
 * Call inside a transaction BEFORE inserting entries. Locks the draw row.
 * @return array{ok: bool, message: string, draw: ?array, remaining: ?int}
 */
function reserveDrawEntrySlot(PDO $db, int $drawId, int $entries = 1): array {
    $s = $db->prepare("SELECT * FROM draws WHERE id = ? FOR UPDATE");
    $s->execute([$drawId]);
    $draw = $s->fetch();

    if (!$draw || $draw['status'] !== 'active') {
        return ['ok' => false, 'message' => 'This draw is not open for entries.', 'draw' => $draw ?: null, 'remaining' => null];
    }
    if (($draw['draw_type'] ?? 'timed') !== 'entries' || empty($draw['target_entries'])) {
        return ['ok' => true, 'message' => '', 'draw' => $draw, 'remaining' => null];
    }

    $remaining = (int) $draw['target_entries'] - drawEntryCount($db, $drawId);
    if ($remaining <= 0) {
        return ['ok' => false, 'message' => 'This draw is full. Its entry target has been reached.', 'draw' => $draw, 'remaining' => 0];
    }
    if ($entries > $remaining) {
        return ['ok' => false,
                'message' => "Only $remaining entry slot" . ($remaining === 1 ? '' : 's') . " left in this draw. Please enter with fewer codes.",
                'draw' => $draw, 'remaining' => $remaining];
    }
    return ['ok' => true, 'message' => '', 'draw' => $draw, 'remaining' => $remaining];
}

/**
 * Call inside the same transaction AFTER inserting entries. Ends the draw if its target is now reached.
 * @return bool true if THIS call ended the draw
 */
function finalizeDrawAfterEntry(PDO $db, int $drawId): bool {
    $s = $db->prepare("SELECT draw_type, target_entries, title FROM draws WHERE id = ? AND status = 'active'");
    $s->execute([$drawId]);
    $d = $s->fetch();
    if (!$d || ($d['draw_type'] ?? 'timed') !== 'entries' || empty($d['target_entries'])) return false;
    if (drawEntryCount($db, $drawId) < (int) $d['target_entries']) return false;

    $u = $db->prepare("UPDATE draws SET status = 'ended', ended_at = NOW(), ended_reason = 'target_reached', updated_at = NOW()
                       WHERE id = ? AND status = 'active'");
    $u->execute([$drawId]);
    if ($u->rowCount() < 1) return false;

    try {
        auditLog('system', 0, 'draw_target_reached', "Draw #$drawId '{$d['title']}' reached its entry target and ended automatically", 'draw', $drawId);
    } catch (\Throwable $e) {
        error_log('[DRAW] audit failed: ' . $e->getMessage());
    }
    return true;
}

/**
 * Safety net: ends any active entry draw that already has >= target entries (e.g. entries added by a
 * path that doesn't call the helpers, or an admin lowering the target). Safe to run any time.
 * admin/draws.php runs it on every load; you can also call it from your cron.
 * @return int number of draws ended
 */
function sweepEntryTargetDraws(PDO $db): int {
    try {
        $u = $db->prepare("UPDATE draws d
            SET d.status = 'ended', d.ended_at = NOW(), d.ended_reason = 'target_reached', d.updated_at = NOW()
            WHERE d.status = 'active' AND d.draw_type = 'entries' AND d.target_entries IS NOT NULL
              AND (SELECT COUNT(*) FROM draw_entries e WHERE e.draw_id = d.id) >= d.target_entries");
        $u->execute();
        return $u->rowCount();
    } catch (\Throwable $e) {
        error_log('[DRAW] sweep failed: ' . $e->getMessage());
        return 0;
    }
}
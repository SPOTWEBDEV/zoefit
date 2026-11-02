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
 * ENTRY DRAWS RUN AS A QUEUE (one at a time, in the order they were created):
 *   - They are NEVER started by their start date.
 *   - When an entry draw is created and no other entry draw is running, it starts immediately.
 *   - Otherwise it waits as 'pending'. The moment the running entry draw leaves 'active'
 *     (target reached, ended or cancelled by an admin) the oldest pending entry draw is
 *     activated automatically. Nobody has to select the winner of the previous draw first.
 *   - start_date is only a reference: it is set to the real time the draw actually started.
 *   See advanceEntryDrawQueue().
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
 * ENTRY-DRAW QUEUE.
 * If no entry-triggered draw is currently active, activates the OLDEST pending one (lowest id).
 * Idempotent and cheap: safe to call after create / end / cancel, on page loads and from cron.
 * The start_date is overwritten with the real start time (it is only a record, never a trigger).
 *
 * @return int|null id of the draw that was just started, or null if nothing was started
 */
function advanceEntryDrawQueue(PDO $db): ?int {
    try {
        // Named lock so two requests can never start two draws at once
        $got = (int) $db->query("SELECT GET_LOCK('zf_entry_draw_queue', 5)")->fetchColumn();
        if (!$got) return null;
        try {
            $running = (int) $db->query("SELECT COUNT(*) FROM draws WHERE draw_type = 'entries' AND status = 'active'")->fetchColumn();
            if ($running > 0) return null;

            $next = $db->query("SELECT id, title FROM draws WHERE draw_type = 'entries' AND status = 'pending' ORDER BY id ASC LIMIT 1")->fetch();
            if (!$next) return null;

            $u = $db->prepare("UPDATE draws SET status = 'active', start_date = NOW(), activated_at = NOW(), updated_at = NOW()
                               WHERE id = ? AND status = 'pending'");
            $u->execute([$next['id']]);
            if ($u->rowCount() < 1) return null;

            try {
                auditLog('system', 0, 'queue_activate_draw',
                    "Entry draw #{$next['id']} '{$next['title']}' started automatically (previous entry draw completed)", 'draw', (int)$next['id']);
            } catch (\Throwable $e) {
                error_log('[DRAW] audit failed: ' . $e->getMessage());
            }
            return (int) $next['id'];
        } finally {
            $db->query("SELECT RELEASE_LOCK('zf_entry_draw_queue')")->fetchColumn();
        }
    } catch (\Throwable $e) {
        error_log('[DRAW] queue advance failed: ' . $e->getMessage());
        return null;
    }
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
 * Call inside the same transaction AFTER inserting entries. Ends the draw if its target is now reached,
 * and immediately starts the next queued entry draw.
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

    // This entry draw is done: start the next one in the queue straight away
    advanceEntryDrawQueue($db);
    return true;
}

/**
 * Safety net: ends any active entry draw that already has >= target entries (e.g. entries added by a
 * path that doesn't call the helpers, or an admin lowering the target), then makes sure the queue is
 * moving (starts the next entry draw if none is running). Safe to run any time.
 * admin/draws.php runs it on every load; the cron runs it every minute.
 * @return int number of draws ended
 */
function sweepEntryTargetDraws(PDO $db): int {
    $ended = 0;
    try {
        $u = $db->prepare("UPDATE draws d
            SET d.status = 'ended', d.ended_at = NOW(), d.ended_reason = 'target_reached', d.updated_at = NOW()
            WHERE d.status = 'active' AND d.draw_type = 'entries' AND d.target_entries IS NOT NULL
              AND (SELECT COUNT(*) FROM draw_entries e WHERE e.draw_id = d.id) >= d.target_entries");
        $u->execute();
        $ended = $u->rowCount();
    } catch (\Throwable $e) {
        error_log('[DRAW] sweep failed: ' . $e->getMessage());
    }
    advanceEntryDrawQueue($db);   // idempotent: only starts something if no entry draw is running
    return $ended;
}
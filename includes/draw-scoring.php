<?php
/**
 * includes/draw-scoring.php
 *
 * SINGLE SOURCE OF TRUTH for how draw participants are scored and ranked.
 *
 * Before this file existed, three separate places each implemented their own
 * copy of the "best code vs winning code" matching + sorting logic:
 *   1. admin/select-winner.php  -> AJAX live-typing preview table
 *   2. admin/select-winner.php  -> final POST submit (writes draw_rankings/draw_winners)
 *   3. user/draw-detail.php     -> "All Participants" table
 *
 * The three copies used slightly different tiebreakers (one used the user's
 * account registration date, another used first entry time), which is why
 * the admin's live table could show a different #1/#2/#3 than what users
 * ultimately saw on the result page. Every place that needs a ranking should
 * now call getDrawParticipantRanking() from here instead of rolling its own.
 */

/**
 * Rank every participant of a draw against a (possibly partial) winning code.
 *
 * @param PDO         $db
 * @param int         $drawId
 * @param string|null $winningCode  The digits entered so far (or the full
 *                                  15-digit winning code). Pass null (or '')
 *                                  when no winner has been drawn yet — every
 *                                  participant will simply score 0 matches
 *                                  and be ordered by entries / earliest entry.
 * @param int|null    $compareLen   How many leading digits of $winningCode to
 *                                  compare. Defaults to strlen($winningCode).
 *                                  Used by the admin live-typing view to score
 *                                  against only the digits typed so far.
 *
 * @return array<int, array{
 *   uid:int, full_name:string, phone:string, best_code:string,
 *   matched:int, entry_count:int, first_entry:string
 * }> Sorted: matched DESC, entry_count DESC, first_entry ASC (earlier wins ties)
 */
function getDrawParticipantRanking(PDO $db, int $drawId, ?string $winningCode = null, ?int $compareLen = null): array
{
    $stmt = $db->prepare(
        "SELECT u.id AS uid, u.full_name, u.phone, c.code, de.entered_at
         FROM draw_entries de
         JOIN users u ON u.id = de.user_id
         JOIN codes c ON c.id = de.code_id
         WHERE de.draw_id = ?
         ORDER BY de.entered_at ASC"
    );
    $stmt->execute([$drawId]);
    $entries = $stmt->fetchAll();

    $winningCode = $winningCode !== null ? preg_replace('/\D/', '', $winningCode) : null;
    $len = ($winningCode !== null && $winningCode !== '')
        ? ($compareLen ?? strlen($winningCode))
        : 0;

    $scores = [];
    foreach ($entries as $e) {
        $uid  = (int)$e['uid'];
        $code = $e['code'];

        $matched = 0;
        if ($len > 0) {
            for ($i = 0; $i < $len; $i++) {
                if (isset($code[$i], $winningCode[$i]) && $code[$i] === $winningCode[$i]) {
                    $matched++;
                }
            }
        }

        if (!isset($scores[$uid])) {
            $scores[$uid] = [
                'uid'         => $uid,
                'full_name'   => $e['full_name'],
                'phone'       => $e['phone'],
                'best_code'   => $code,
                'matched'     => $matched,
                'entry_count' => 0,
                'first_entry' => $e['entered_at'],
            ];
        }

        // Keep the best-matching code as this user's representative code
        if ($matched > $scores[$uid]['matched']) {
            $scores[$uid]['matched']   = $matched;
            $scores[$uid]['best_code'] = $code;
        }

        $scores[$uid]['entry_count']++;

        if (strtotime($e['entered_at']) < strtotime($scores[$uid]['first_entry'])) {
            $scores[$uid]['first_entry'] = $e['entered_at'];
        }
    }

    $scores = array_values($scores);

    usort($scores, function ($a, $b) {
        if ($b['matched'] !== $a['matched']) {
            return $b['matched'] - $a['matched'];
        }
        if ($b['entry_count'] !== $a['entry_count']) {
            return $b['entry_count'] - $a['entry_count'];
        }
        return strtotime($a['first_entry']) - strtotime($b['first_entry']);
    });

    return $scores;
}

/**
 * Given an already-sorted ranking (from getDrawParticipantRanking), figure out
 * whether 1st place was decided by a tiebreaker, and which one.
 *
 * @return string|null 'most_entries' | 'earliest_entry' | null (no tie)
 */
function getDrawTiebreaker(array $ranking): ?string
{
    if (count($ranking) > 1 && $ranking[0]['matched'] === $ranking[1]['matched']) {
        return $ranking[0]['entry_count'] !== $ranking[1]['entry_count']
            ? 'most_entries'
            : 'earliest_entry';
    }
    return null;
}

/**
 * Mask a phone number for display: keep first 4 and last 3 digits visible.
 * Shared so admin screenshots/exports and user-facing pages mask consistently.
 */
function maskPhoneDigits(string $phone): string
{
    $p = preg_replace('/\D/', '', $phone);
    if (strlen($p) < 7) return '***';
    return substr($p, 0, 4) . '****' . substr($p, -3);
}

<?php
// ajax/finalize-draw.php
// Called by admin when finalizing a completed draw.
// 1. Generates a cryptographically random 15-digit winning code
// 2. Scores every entered user's codes against it (count of matching digits at same position)
// 3. Applies tiebreakers: most entries → earliest registration
// 4. Saves winner + winning code to draw_winners, updates draw status → completed
// 5. Marks all draw entry codes as status='used', recalculates user balances
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

$auth = requireAdmin(); $adminId = $auth['id'];
$db   = getDB();

if (!isPost()) jsonResponse(['error'=>'POST required'], 405);
$body = json_decode(file_get_contents('php://input'), true) ?: [];
if (!verifyCsrf($body[CSRF_TOKEN_NAME] ?? '')) jsonResponse(['error'=>'Invalid CSRF'], 403);

$drawId = (int)($body['draw_id'] ?? 0);
if (!$drawId) jsonResponse(['error'=>'draw_id required'], 400);

// Lock and fetch draw
$draw = $db->prepare("SELECT * FROM draws WHERE id=? FOR UPDATE");
$draw->execute([$drawId]); $draw = $draw->fetch();
if (!$draw)                                 jsonResponse(['error'=>'Draw not found'], 404);
if ($draw['status'] === 'completed')        jsonResponse(['error'=>'Draw already finalized'], 409);
if (!in_array($draw['status'],['active','paused'])) jsonResponse(['error'=>'Draw is not active'], 400);

// Fetch all entries: user_id, code, entry time, user registration time
$entries = $db->prepare(
  "SELECT de.id as entry_id, de.user_id, de.code_id, de.entered_at,
          c.code,
          u.created_at as user_registered_at
   FROM draw_entries de
   JOIN codes c ON de.code_id = c.id
   JOIN users u ON de.user_id = u.id
   WHERE de.draw_id = ?"
);
$entries->execute([$drawId]); $entries = $entries->fetchAll();

if (empty($entries)) jsonResponse(['error'=>'No entries in this draw. Cannot finalize.'], 400);

$db->beginTransaction();
try {
  // ── STEP 1: Generate the 15-digit winning code ───────────
  $winningCode = '';
  for ($i = 0; $i < 15; $i++) $winningCode .= random_int(0, 9);

  // ── STEP 2: Score each user ───────────────────────────────
  // For each user, find their best-scoring code (most digits matching at same position)
  $userScores = []; // [user_id => ['best_matches'=>int,'entries'=>int,'registered'=>timestamp,'best_code'=>str,'earliest_entry'=>timestamp]]

  foreach ($entries as $e) {
    $uid  = (int)$e['user_id'];
    $code = $e['code'];

    // Count matching digits at same position (0-15)
    $matches = 0;
    for ($pos = 0; $pos < 15; $pos++) {
      if ($code[$pos] === $winningCode[$pos]) $matches++;
    }

    if (!isset($userScores[$uid])) {
      $userScores[$uid] = [
        'user_id'         => $uid,
        'best_matches'    => 0,
        'best_code'       => $code,
        'entries'         => 0,
        'earliest_entry'  => $e['entered_at'],
        'registered_at'   => $e['user_registered_at'],
      ];
    }

    // Update best matching code for this user
    if ($matches > $userScores[$uid]['best_matches']) {
      $userScores[$uid]['best_matches'] = $matches;
      $userScores[$uid]['best_code']    = $code;
    }
    $userScores[$uid]['entries']++;

    // Track earliest entry time
    if (strtotime($e['entered_at']) < strtotime($userScores[$uid]['earliest_entry'])) {
      $userScores[$uid]['earliest_entry'] = $e['entered_at'];
    }
  }

  // ── STEP 3: Sort users by tiebreaker hierarchy ─────────────
  // 1. Most matching digits (desc)
  // 2. Most entries (desc)
  // 3. Earliest registration on platform (asc)
  usort($userScores, function($a, $b) {
    // 1. Best match count
    if ($a['best_matches'] !== $b['best_matches'])
      return $b['best_matches'] <=> $a['best_matches'];
    // 2. Most entries
    if ($a['entries'] !== $b['entries'])
      return $b['entries'] <=> $a['entries'];
    // 3. Earliest registered (older user wins)
    return strtotime($a['registered_at']) <=> strtotime($b['registered_at']);
  });

  $winner      = $userScores[0];
  $winnerId    = $winner['user_id'];
  $winnerCode  = $winner['best_code'];
  $matchCount  = $winner['best_matches'];

  // Determine which tiebreaker was used (for transparency)
  $tiebreaker = null;
  if (count($userScores) > 1 && $userScores[0]['best_matches'] === $userScores[1]['best_matches']) {
    if ($userScores[0]['entries'] !== $userScores[1]['entries']) {
      $tiebreaker = 'most_entries';
    } else {
      $tiebreaker = 'earliest_registration';
    }
  }

  // ── STEP 4: Save winner record ────────────────────────────
  $db->prepare(
    "INSERT INTO draw_winners
     (draw_id, user_id, winning_code, user_code, matched_digits, tiebreaker_used, announced_at)
     VALUES (?,?,?,?,?,?,NOW())"
  )->execute([$drawId, $winnerId, $winningCode, $winnerCode, $matchCount, $tiebreaker]);

  // ── STEP 5: Update draw row ───────────────────────────────
  $db->prepare(
    "UPDATE draws SET status='completed', winning_code=?, winner_user_id=?,
     finalized_by=?, finalized_at=NOW() WHERE id=?"
  )->execute([$winningCode, $winnerId, $adminId, $drawId]);

  // ── STEP 6: Mark all entered codes as 'used' ─────────────
  // and deduct from user balances
  foreach ($entries as $e) {
    $db->prepare("UPDATE codes SET status='used' WHERE id=?")->execute([$e['code_id']]);
    $db->prepare("UPDATE users SET balance=GREATEST(0,balance-1) WHERE id=?")->execute([$e['user_id']]);
  }

  // ── STEP 7: Notify the winner ─────────────────────────────
  $winnerUser = $db->prepare("SELECT full_name FROM users WHERE id=?");
  $winnerUser->execute([$winnerId]); $winnerUser = $winnerUser->fetch();

  createNotification(
    $winnerId,
    '🏆 You Won! Congratulations!',
    "You won the draw \"{$draw['title']}\"! Your code {$winnerCode} matched {$matchCount} digit(s) of the winning number {$winningCode}. ZoeFeeds will contact you soon.",
    'draw'
  );

  auditLog('admin', $adminId, 'finalize_draw',
    "Draw #{$drawId} finalized. Winning code: {$winningCode}. Winner: user #{$winnerId} ({$winnerUser['full_name']}) with {$matchCount} matches.",
    'draw', $drawId
  );

  $db->commit();

  jsonResponse([
    'success'       => true,
    'winning_code'  => $winningCode,
    'winner_id'     => $winnerId,
    'winner_name'   => $winnerUser['full_name'],
    'matched_digits'=> $matchCount,
    'tiebreaker'    => $tiebreaker,
    'total_entries' => count($entries),
    'message'       => "Draw finalized. Winning code: {$winningCode}. Winner: {$winnerUser['full_name']} ({$matchCount} matching digits)."
  ]);

} catch (\Exception $e) {
  $db->rollBack();
  jsonResponse(['error' => 'Finalization failed: '.$e->getMessage()], 500);
}

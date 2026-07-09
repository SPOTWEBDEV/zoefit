<?php
// admin/winners.php
// Admin view of all draw results — shows full names, phone numbers AND codes.
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
$auth = requireAdmin(); $adminId = $auth['id'];
$db   = getDB();

// ── Filters ────────────────────────────────────────────────
$page   = max(1, (int)($_GET['page'] ?? 1));
$per    = 10;
$offset = ($page - 1) * $per;
$search = trim($_GET['q'] ?? '');
$drawId = (int)($_GET['draw'] ?? 0);

// ── Single draw view ───────────────────────────────────────
if ($drawId) {
    $draw = $db->prepare(
        "SELECT d.*, dw.winning_code, dw.user_code, dw.matched_digits,
                dw.tiebreaker_used, dw.announced_at,
                u.full_name AS winner_name, u.phone AS winner_phone,
                u.email AS winner_email, u.id AS winner_uid
         FROM draws d
         LEFT JOIN draw_winners dw ON dw.draw_id = d.id
         LEFT JOIN users u ON u.id = dw.user_id
         WHERE d.id = ? AND d.status = 'completed'"
    );
    $draw->execute([$drawId]);
    $draw = $draw->fetch();

    if (!$draw) redirect(APP_URL . '/admin/winners.php');

    // Full top 3 with codes (admin sees everything)
    $rankings = $db->prepare(
        "SELECT dr.*, u.full_name, u.phone, u.email, u.id AS uid
         FROM draw_rankings dr
         JOIN users u ON u.id = dr.user_id
         WHERE dr.draw_id = ?
         ORDER BY dr.rank_position ASC"
    );
    $rankings->execute([$drawId]);
    $rankings = $rankings->fetchAll();

    // All participants with entry count + their best scoring code
    $participants = $db->prepare(
        "SELECT u.id AS uid, u.full_name, u.phone, u.email,
                COUNT(de.id) AS entry_count,
                MAX(c.code) AS sample_code
         FROM draw_entries de
         JOIN users u ON u.id = de.user_id
         JOIN codes c ON c.id = de.code_id
         WHERE de.draw_id = ?
         GROUP BY de.user_id
         ORDER BY entry_count DESC"
    );
    $participants->execute([$drawId]);
    $participants = $participants->fetchAll();

    $pageMode = 'single';
} else {
    $pageMode = 'list';
}

// ── List of all completed draws ────────────────────────────
if ($pageMode === 'list') {
    $where  = "d.status = 'completed'";
    $params = [];
    if ($search) {
        $where   .= " AND (d.title LIKE ? OR d.category LIKE ? OR u.full_name LIKE ?)";
        $s        = "%$search%";
        $params   = [$s, $s, $s];
    }

    $draws = $db->prepare(
        "SELECT d.*,
                dw.winning_code, dw.user_code, dw.matched_digits,
                dw.announced_at, dw.tiebreaker_used,
                u.full_name AS winner_name, u.phone AS winner_phone,
                u.id AS winner_uid,
                (SELECT COUNT(*) FROM draw_entries WHERE draw_id=d.id) AS total_entries,
                (SELECT COUNT(DISTINCT user_id) FROM draw_entries WHERE draw_id=d.id) AS total_participants
         FROM draws d
         LEFT JOIN draw_winners dw ON dw.draw_id = d.id
         LEFT JOIN users u ON u.id = dw.user_id
         WHERE $where
         ORDER BY dw.announced_at DESC
         LIMIT $per OFFSET $offset"
    );
    $draws->execute($params);
    $draws = $draws->fetchAll();

    $cntS = $db->prepare("SELECT COUNT(*) FROM draws d LEFT JOIN draw_winners dw ON dw.draw_id=d.id LEFT JOIN users u ON u.id=dw.user_id WHERE $where");
    $cntS->execute($params);
    $total = (int)$cntS->fetchColumn();
    $pages = (int)ceil($total / $per);
}

// Stats
$totalWinners   = (int)$db->query("SELECT COUNT(*) FROM draw_winners")->fetchColumn();
$totalCompleted = (int)$db->query("SELECT COUNT(*) FROM draws WHERE status='completed'")->fetchColumn();

$aPage = 'winners';
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="csrf-token" content="<?= generateCsrf() ?>">
  <title>Winners — <?= APP_NAME ?> Admin</title>
  <script src="<?= APP_URL ?>/assets/js/tailwind.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
  <style>
    * { font-family: 'Poppins', sans-serif !important; }
    code { font-family: 'Courier New', monospace !important; }

    .winner-glow {
      background: linear-gradient(135deg,rgba(234,179,8,.1),rgba(0,0,0,0));
      border: 1px solid rgba(234,179,8,.3);
      border-radius: 20px;
    }

    .digit-slot {
      display: inline-flex; align-items: center; justify-content: center;
      width: 30px; height: 38px; border-radius: 7px;
      font-size: 16px; font-weight: 900;
      font-family: 'Courier New', monospace;
      border: 1.5px solid rgba(255,255,255,.08);
      background: rgba(255,255,255,.04);
    }
    .digit-slot.match    { background:rgba(34,197,94,.18); border-color:#22c55e; color:#22c55e; }
    .digit-slot.no-match { background:rgba(239,68,68,.08); border-color:rgba(239,68,68,.2); color:#6b7280; }
    .digit-slot.neutral  { background:rgba(249,115,22,.1);  border-color:rgba(249,115,22,.3); color:#f97316; }
  </style>
</head>
<body class="bg-[#0a0f1a] text-white">
<?php include __DIR__ . '/../components/admin-sidebar.php'; ?>

<div class="main-content">
  <div class="topbar">
    <button onclick="toggleSidebar()" class="md:hidden text-gray-400 text-2xl mr-3">☰</button>
    <?php if ($drawId): ?>
    <div>
      <a href="<?= APP_URL ?>/admin/winners.php" class="text-orange-400 text-sm hover:underline">← All Winners</a>
      <h1 class="text-lg font-bold mt-0.5"><?= e($draw['title']) ?></h1>
    </div>
    <?php else: ?>
    <h1 class="text-xl font-bold">🏆 Draw Winners</h1>
    <?php endif; ?>
    <div class="text-sm text-gray-400"><?= number_format($totalCompleted) ?> draws completed</div>
  </div>

  <div class="p-4 md:p-6 max-w-5xl mx-auto">

    <!-- ════════════════════════════════════════════════════
         SINGLE DRAW — ADMIN FULL VIEW
    ════════════════════════════════════════════════════════ -->
    <?php if ($pageMode === 'single'): ?>

    <!-- Draw header -->
    <div class="winner-glow p-6 mb-5">
      <div class="flex items-start gap-4">
        <div class="w-16 h-16 rounded-2xl flex-shrink-0 flex items-center justify-center text-3xl overflow-hidden"
             style="background:linear-gradient(135deg,#1a2235,#0d1118)">
          <?php if ($draw['banner_image'] && file_exists(UPLOAD_PATH.$draw['banner_image'])): ?>
          <img src="<?= APP_URL ?>/uploads/<?= e($draw['banner_image']) ?>" class="w-full h-full object-cover" alt="">
          <?php else: ?>🏆<?php endif; ?>
        </div>
        <div class="flex-1 min-w-0">
          <h1 class="font-black text-xl text-yellow-400 mb-1"><?= e($draw['title']) ?></h1>
          <div class="text-xs text-gray-500 flex flex-wrap gap-4">
            <span>📅 Ended <?= date('M j, Y g:i A', strtotime($draw['end_date'])) ?></span>
            <span>👥 <?= number_format(count($participants)) ?> participants</span>
            <?php if ($draw['announced_at']): ?>
            <span>🤖 Auto-finalized <?= date('M j, Y g:i A', strtotime($draw['announced_at'])) ?></span>
            <?php endif; ?>
          </div>
          <?php if ($draw['prize_details']): ?>
          <div class="mt-1 text-sm text-orange-400">🎁 <?= e($draw['prize_details']) ?></div>
          <?php endif; ?>
        </div>
        <a href="<?= APP_URL ?>/admin/draw-manage.php?id=<?= $drawId ?>"
           class="btn btn-secondary btn-sm flex-shrink-0">📊 Draw Details</a>
      </div>
    </div>

    <!-- Winning code display -->
    <?php if ($draw['winning_code']): ?>
    <div class="card p-5 mb-5">
      <div class="text-sm text-gray-400 mb-3 font-semibold">🎲 Generated Winning Code</div>
      <div class="flex flex-wrap gap-1.5 mb-2">
        <?php for ($i = 0; $i < 15; $i++): ?>
        <div class="digit-slot neutral"><?= $draw['winning_code'][$i] ?></div>
        <?php endfor; ?>
      </div>
      <code class="text-xs text-orange-400"><?= e($draw['winning_code']) ?></code>
    </div>
    <?php endif; ?>

    <!-- Winner card (full details for admin) -->
    <?php if ($draw['winner_name']): ?>
    <div class="card p-6 mb-5" style="border-color:rgba(234,179,8,.4)">
      <div class="flex items-center gap-2 mb-5">
        <span class="text-3xl">🏆</span>
        <h2 class="font-black text-xl text-yellow-400">Winner</h2>
      </div>

      <div class="flex items-start gap-4 mb-5">
        <div class="w-14 h-14 bg-yellow-500/20 rounded-2xl flex items-center justify-center text-2xl font-black text-yellow-400 flex-shrink-0">
          <?= strtoupper($draw['winner_name'][0]) ?>
        </div>
        <div class="flex-1">
          <div class="font-bold text-yellow-400 text-lg"><?= e($draw['winner_name']) ?></div>
          <div class="text-sm text-gray-400"><?= e(formatPhone($draw['winner_phone'])) ?></div>
          <?php if ($draw['winner_email']): ?>
          <div class="text-sm text-gray-400"><?= e($draw['winner_email']) ?></div>
          <?php endif; ?>
          <div class="text-xs text-gray-600 mt-1">User #<?= $draw['winner_uid'] ?></div>
        </div>
        <div class="text-right flex-shrink-0">
          <div class="text-2xl font-black text-yellow-400"><?= $draw['matched_digits'] ?>/15</div>
          <div class="text-xs text-gray-500">digits matched</div>
          <?php if ($draw['tiebreaker_used']): ?>
          <div class="text-xs text-orange-400 mt-1">via <?= e(str_replace('_',' ',$draw['tiebreaker_used'])) ?></div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Winner's code vs winning code comparison -->
      <?php if ($draw['user_code'] && $draw['winning_code']): ?>
      <div class="grid md:grid-cols-2 gap-4">
        <div>
          <div class="text-xs text-gray-500 mb-2 font-semibold uppercase tracking-wider">Winning Code</div>
          <div class="flex flex-wrap gap-1">
            <?php for ($i = 0; $i < 15; $i++): ?>
            <div class="digit-slot <?= $draw['user_code'][$i] === $draw['winning_code'][$i] ? 'match' : 'no-match' ?>">
              <?= $draw['winning_code'][$i] ?>
            </div>
            <?php endfor; ?>
          </div>
        </div>
        <div>
          <div class="text-xs text-gray-500 mb-2 font-semibold uppercase tracking-wider">Winner's Code</div>
          <div class="flex flex-wrap gap-1">
            <?php for ($i = 0; $i < 15; $i++): ?>
            <div class="digit-slot <?= $draw['user_code'][$i] === $draw['winning_code'][$i] ? 'match' : 'no-match' ?>">
              <?= $draw['user_code'][$i] ?>
            </div>
            <?php endfor; ?>
          </div>
        </div>
      </div>
      <div class="text-xs text-gray-600 mt-2">🟢 = position matched &nbsp; 🔴 = no match</div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Top 3 podium — admin sees codes -->
    <?php if ($rankings): ?>
    <div class="card mb-5">
      <div class="px-5 pt-5 pb-3 border-b border-white/5 font-bold flex items-center gap-2">
        🏅 Top 3 — Full Details
        <span class="text-xs font-normal text-gray-500">(codes visible to admin only)</span>
      </div>
      <div class="divide-y divide-white/5">
        <?php
        $rankIcons = [1 => '🥇', 2 => '🥈', 3 => '🥉'];
        foreach ($rankings as $r):
        ?>
        <div class="p-5">
          <div class="flex items-start gap-3 flex-wrap mb-3">
            <div class="text-2xl flex-shrink-0"><?= $rankIcons[$r['rank_position']] ?? '#'.$r['rank_position'] ?></div>
            <div class="flex-1 min-w-0">
              <div class="font-bold text-sm"><?= e($r['full_name']) ?></div>
              <div class="text-xs text-gray-400"><?= e(formatPhone($r['phone'])) ?> · User #<?= $r['uid'] ?></div>
            </div>
            <div class="text-right flex-shrink-0">
              <div class="font-black text-base <?= $r['matched_digits']>=10?'text-green-400':($r['matched_digits']>=5?'text-yellow-400':'text-gray-400') ?>">
                <?= $r['matched_digits'] ?>/15
              </div>
              <div class="text-xs text-gray-600"><?= $r['entries_count'] ?> entries</div>
            </div>
          </div>
          <!-- Code comparison — admin only -->
          <?php if ($draw['winning_code'] && $r['user_code']): ?>
          <div class="grid md:grid-cols-2 gap-3 mt-2">
            <div>
              <div class="text-xs text-gray-600 mb-1">Winning code</div>
              <div class="flex flex-wrap gap-0.5">
                <?php for ($i=0;$i<15;$i++): ?>
                <div class="digit-slot <?= $r['user_code'][$i]===$draw['winning_code'][$i]?'match':'no-match' ?>" style="width:24px;height:30px;font-size:13px">
                  <?= $draw['winning_code'][$i] ?>
                </div>
                <?php endfor; ?>
              </div>
            </div>
            <div>
              <div class="text-xs text-gray-600 mb-1">Their code</div>
              <div class="flex flex-wrap gap-0.5">
                <?php for ($i=0;$i<15;$i++): ?>
                <div class="digit-slot <?= $r['user_code'][$i]===$draw['winning_code'][$i]?'match':'no-match' ?>" style="width:24px;height:30px;font-size:13px">
                  <?= $r['user_code'][$i] ?>
                </div>
                <?php endfor; ?>
              </div>
            </div>
          </div>
          <?php endif; ?>
          <?php if ($r['tiebreaker']): ?>
          <div class="text-xs text-orange-400 mt-2">Tiebreaker: <?= e(str_replace('_',' ',$r['tiebreaker'])) ?></div>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- Full participant table — admin sees all codes -->
    <?php if ($participants): ?>
    <div class="card">
      <div class="px-5 pt-5 pb-3 border-b border-white/5 flex items-center justify-between">
        <h3 class="font-bold text-sm">📋 All Participants</h3>
        <span class="text-xs text-gray-500"><?= number_format(count($participants)) ?> total</span>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th style="width:40px">#</th>
              <th>Name</th>
              <th>Phone</th>
              <th>Entries</th>
              <th>Sample Code</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($participants as $i => $p): ?>
            <tr>
              <td class="text-gray-500 text-sm"><?= $i+1 ?></td>
              <td>
                <a href="<?= APP_URL ?>/admin/user-detail.php?id=<?= $p['uid'] ?>"
                   class="text-sm font-medium hover:text-orange-400 transition-colors">
                  <?= e($p['full_name']) ?>
                </a>
              </td>
              <td class="font-mono text-sm text-gray-400"><?= e(formatPhone($p['phone'])) ?></td>
              <td>
                <span class="font-bold text-orange-400"><?= $p['entry_count'] ?></span>
              </td>
              <td>
                <code class="text-xs text-gray-400"><?= e($p['sample_code']) ?></code>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    <?php endif; ?>


    <!-- ════════════════════════════════════════════════════
         LIST VIEW
    ════════════════════════════════════════════════════════ -->
    <?php else: ?>

    <!-- Stats -->
    <div class="grid grid-cols-2 gap-3 mb-5">
      <div class="card p-4 text-center">
        <div class="text-2xl font-black text-yellow-400"><?= number_format($totalWinners) ?></div>
        <div class="text-xs text-gray-500 mt-1">Total Winners</div>
      </div>
      <div class="card p-4 text-center">
        <div class="text-2xl font-black text-orange-400"><?= number_format($totalCompleted) ?></div>
        <div class="text-xs text-gray-500 mt-1">Draws Completed</div>
      </div>
    </div>

    <!-- Search -->
    <form method="GET" class="flex gap-3 mb-5">
      <input type="text" name="q" class="form-control flex-1" placeholder="Search by draw title, category or winner name…" value="<?= e($search) ?>">
      <button class="btn btn-primary px-6">Search</button>
      <?php if ($search): ?><a href="<?= APP_URL ?>/admin/winners.php" class="btn btn-secondary px-4">Clear</a><?php endif; ?>
    </form>

    <!-- Table -->
    <div class="card">
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Draw</th>
              <th>Winner</th>
              <th>Winning Code</th>
              <th>Matched</th>
              <th>Entries</th>
              <th>Finalized</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($draws as $d): ?>
            <tr>
              <td>
                <div class="font-semibold text-sm"><?= e($d['title']) ?></div>
                <?php if ($d['category']): ?>
                <span class="badge badge-info text-xs mt-0.5"><?= e($d['category']) ?></span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($d['winner_name']): ?>
                <div class="text-sm font-medium text-yellow-400"><?= e($d['winner_name']) ?></div>
                <div class="text-xs text-gray-500"><?= e(formatPhone($d['winner_phone'])) ?></div>
                <?php else: ?>
                <span class="text-gray-600 text-sm">—</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($d['winning_code']): ?>
                <code class="text-xs text-orange-400 font-mono"><?= e($d['winning_code']) ?></code>
                <?php else: ?><span class="text-gray-600">—</span><?php endif; ?>
              </td>
              <td>
                <?php if ($d['matched_digits'] !== null): ?>
                <span class="font-bold <?= $d['matched_digits']>=10?'text-green-400':($d['matched_digits']>=5?'text-yellow-400':'text-gray-400') ?>">
                  <?= $d['matched_digits'] ?>/15
                </span>
                <?php else: ?><span class="text-gray-600">—</span><?php endif; ?>
              </td>
              <td class="text-sm text-gray-400"><?= number_format($d['total_entries']) ?></td>
              <td class="text-xs text-gray-500">
                <?= $d['announced_at'] ? date('M j, Y', strtotime($d['announced_at'])) : '—' ?>
              </td>
              <td>
                <a href="?draw=<?= $d['id'] ?>" class="btn btn-secondary btn-sm text-xs">View →</a>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$draws): ?>
            <tr><td colspan="7" class="text-center text-gray-500 py-10">No completed draws yet</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      <?php if ($pages > 1): ?>
      <div class="flex items-center justify-between p-4 border-t border-white/5">
        <div class="text-sm text-gray-400">Page <?= $page ?>/<?= $pages ?> · <?= number_format($total) ?> draws</div>
        <div class="flex gap-2">
          <?php if ($page > 1): ?><a href="?page=<?= $page-1 ?>&q=<?= urlencode($search) ?>" class="btn btn-sm btn-secondary">← Prev</a><?php endif; ?>
          <?php if ($page < $pages): ?><a href="?page=<?= $page+1 ?>&q=<?= urlencode($search) ?>" class="btn btn-sm btn-secondary">Next →</a><?php endif; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>

    <?php endif; // end list vs single ?>

  </div>
</div>

<script src="<?= APP_URL ?>/assets/js/app.js"></script>
</body>
</html>

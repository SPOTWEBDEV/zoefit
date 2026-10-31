<?php
// user/draws.php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../lib/draw_trigger.php';   // drawHasDeadline()
$auth = requireUser(); $userId = $auth['id'];
$currentPage = 'draws'; $pageTitle = 'Draws & Rewards';
$db = getDB();

$filter  = $_GET['status'] ?? 'active';
$allowed = ['active','pending','completed','ended'];
if (!in_array($filter, $allowed)) $filter = 'active';

$stmt = $db->prepare(
    "SELECT d.*,
            (SELECT COUNT(*) FROM draw_entries WHERE draw_id=d.id AND user_id=?) AS my_entries,
            (SELECT COUNT(*) FROM draw_entries WHERE draw_id=d.id)               AS total_entries
     FROM draws d
     WHERE d.status=?"
);
$stmt->execute([$userId, $filter]);
$draws = $stmt->fetchAll();

// Sorting: draws with a real end date come first (soonest ending first);
// entry-triggered draws with no deadline follow (oldest start first).
// Ended / completed draws: most recently closed first.
usort($draws, function ($a, $b) use ($filter) {
    if (in_array($filter, ['ended','completed'])) {
        $ta = strtotime($a['ended_at'] ?? '') ?: (drawHasDeadline($a['end_date']) ? strtotime($a['end_date']) : strtotime($a['start_date']));
        $tb = strtotime($b['ended_at'] ?? '') ?: (drawHasDeadline($b['end_date']) ? strtotime($b['end_date']) : strtotime($b['start_date']));
        return $tb <=> $ta;
    }
    $da = drawHasDeadline($a['end_date']); $db_ = drawHasDeadline($b['end_date']);
    if ($da !== $db_) return $da ? -1 : 1;
    return $da ? strtotime($a['end_date']) <=> strtotime($b['end_date'])
               : strtotime($a['start_date']) <=> strtotime($b['start_date']);
});

$tabs = [
    'active'    => ['Live',      'bolt'],
    'pending'   => ['Upcoming',  'clock'],
    'completed' => ['Completed', 'check-circle'],
    'ended'     => ['Ended',     'stop'],
];
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="csrf-token" content="<?= generateCsrf() ?>">
  <title><?= e($pageTitle) ?> — <?= APP_NAME ?></title>
  <script src="<?= APP_URL ?>/assets/js/tailwind.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
</head>
<body class="bg-[#0a0f1a] text-white antialiased">
<?php include __DIR__ . '/../components/saas-ui.php'; ?>
<?php include __DIR__ . '/../components/user-sidebar.php'; ?>

<div class="main-content">
  <div class="topbar">
    <button onclick="toggleSidebar()" class="md:hidden text-gray-400 text-2xl mr-3" aria-label="Menu">☰</button>
    <div class="min-w-0">
      <h1 class="text-lg font-semibold leading-tight">Draws &amp; Rewards</h1>
      <p class="text-xs text-slate-400 hidden sm:block">Enter live draws and check your results</p>
    </div>
  </div>

  <div class="p-4 md:p-8 max-w-6xl mx-auto space-y-6">

    <!-- Tabs -->
    <nav class="s-tabs" aria-label="Draw status">
      <?php foreach ($tabs as $s => [$label, $icon]): ?>
      <a href="?status=<?= $s ?>" class="s-tab <?= $filter === $s ? 'on' : '' ?>"><?= ico($icon, 'w-4 h-4') ?> <?= $label ?></a>
      <?php endforeach; ?>
    </nav>

    <?php if ($draws): ?>
    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
      <?php foreach ($draws as $d):
        $isEntries   = ($d['draw_type'] ?? 'timed') === 'entries';
        $hasDeadline = drawHasDeadline($d['end_date']);          // entry draws without a deadline store a placeholder date: never show it
        $target      = (int)($d['target_entries'] ?? 0);
        $total       = (int)$d['total_entries'];
        $mine        = (int)$d['my_entries'];
        $pct         = ($isEntries && $target > 0) ? min(100, (int)floor($total / $target * 100)) : 0;
        $reached     = $isEntries && $target > 0 && $total >= $target;
        $left        = max(0, $target - $total);
        $closedAt    = !empty($d['ended_at']) ? strtotime($d['ended_at']) : ($hasDeadline ? strtotime($d['end_date']) : null);
        $canViewResult = ($d['status'] === 'completed' && $d['winner_user_id']) || $d['status'] === 'ended';
        $prize = trim((string)$d['prize_details']);
        if (mb_strlen($prize) > 50) $prize = mb_substr($prize, 0, 50) . '…';
      ?>
      <article class="s-card s-card-hover overflow-hidden flex flex-col">
        <!-- Banner -->
        <div class="h-36 flex items-center justify-center text-slate-600" style="background:linear-gradient(135deg,#1a2235,#0d1118)">
          <?php if ($d['banner_image'] && file_exists(UPLOAD_PATH.$d['banner_image'])): ?>
            <img src="<?= APP_URL ?>/uploads/<?= e($d['banner_image']) ?>" class="w-full h-full object-cover" alt="">
          <?php else: ?><?= ico($isEntries ? 'target' : 'trophy', 'w-10 h-10') ?><?php endif; ?>
        </div>

        <div class="p-4 md:p-5 flex flex-col flex-1">
          <!-- Pills -->
          <div class="flex gap-1.5 mb-3 flex-wrap">
            <?php if ($d['status'] === 'active'): ?><span class="s-pill s-pill-green dot">Live</span><?php endif; ?>
            <?php if ($isEntries): ?><span class="s-pill s-pill-purple"><?= ico('target', 'w-3 h-3') ?> Entry-triggered</span><?php endif; ?>
            <?php if ($mine > 0): ?><span class="s-pill s-pill-blue"><?= ico('check', 'w-3 h-3') ?> Entered (<?= $mine ?>)</span><?php endif; ?>
            <?php if ($d['category']): ?><span class="s-pill s-pill-gray"><?= e($d['category']) ?></span><?php endif; ?>
          </div>

          <h3 class="font-semibold text-[15px] leading-snug mb-1"><?= e($d['title']) ?></h3>
          <?php if ($prize !== ''): ?>
          <p class="text-sm text-orange-400 font-medium mb-2 flex items-start gap-1.5"><?= ico('trophy', 'w-4 h-4 mt-0.5 flex-shrink-0') ?><span><?= e($prize) ?></span></p>
          <?php endif; ?>

          <!-- Timing / progress -->
          <div class="my-3 space-y-3 flex-1">
            <?php if ($d['status'] === 'active'): ?>

              <?php if ($hasDeadline): ?>
              <!-- Only draws with a real deadline get a countdown -->
              <div class="flex gap-3 items-center" data-countdown="<?= e($d['end_date']) ?>"></div>
              <?php endif; ?>

              <?php if ($isEntries && $target > 0): ?>
              <div>
                <div class="flex items-center justify-between text-xs mb-1.5">
                  <span class="font-medium <?= $reached ? 'text-green-400' : 'text-slate-300' ?>"><?= number_format($total) ?> / <?= number_format($target) ?> entries</span>
                  <span class="<?= $reached ? 'text-green-400' : 'text-slate-500' ?>"><?= $reached ? 'Target reached' : $pct . '%' ?></span>
                </div>
                <div class="s-progress"><div class="<?= $reached ? 'done' : '' ?>" style="width:<?= $pct ?>%"></div></div>
                <?php if (!$reached): ?>
                <div class="text-[11.5px] text-slate-500 mt-1.5">Draw closes after <?= number_format($left) ?> more <?= $left === 1 ? 'entry' : 'entries' ?><?= $hasDeadline ? ', or at the deadline' : '' ?>.</div>
                <?php endif; ?>
              </div>
              <?php elseif (!$hasDeadline): ?>
              <div class="text-xs text-slate-500">No end date</div>
              <?php endif; ?>

            <?php elseif ($d['status'] === 'completed'): ?>
              <div class="s-meta-item text-sm text-slate-500"><?= ico('calendar', 'w-4 h-4') ?>
                <?= $closedAt ? 'Completed ' . date('M j, Y', $closedAt) : 'Completed' ?>
              </div>

            <?php elseif ($d['status'] === 'ended'): ?>
              <div class="s-meta-item text-sm text-slate-500"><?= ico('calendar', 'w-4 h-4') ?>
                <?= $closedAt ? 'Ended ' . date('M j, Y', $closedAt) : 'Ended' ?>
                <?= ($d['ended_reason'] ?? '') === 'target_reached' ? '· entry target reached' : '' ?>
              </div>

            <?php else: /* pending */ ?>
              <div class="s-meta-item text-sm text-slate-500"><?= ico('calendar', 'w-4 h-4') ?> Starts <?= date('M j, Y g:ia', strtotime($d['start_date'])) ?></div>
              <?php if ($isEntries && $target > 0): ?>
              <div class="text-xs text-slate-500">Closes automatically at <?= number_format($target) ?> entries<?= $hasDeadline ? ', or at ' . date('M j, Y g:ia', strtotime($d['end_date'])) : '' ?>.</div>
              <?php elseif ($hasDeadline): ?>
              <div class="text-xs text-slate-500">Ends <?= date('M j, Y g:ia', strtotime($d['end_date'])) ?></div>
              <?php endif; ?>
            <?php endif; ?>
          </div>

          <!-- Action -->
          <?php if ($d['status'] === 'active'): ?>
          <a href="<?= APP_URL ?>/user/enter-draw.php?id=<?= $d['id'] ?>" class="s-btn s-btn-primary w-full py-2.5">Enter draw →</a>
          <?php elseif ($canViewResult): ?>
          <a href="<?= APP_URL ?>/user/draw-detail.php?id=<?= $d['id'] ?>" class="s-btn s-btn-ghost w-full py-2.5">View results</a>
          <?php else: ?>
          <button class="s-btn s-btn-ghost w-full py-2.5" disabled>Not yet active</button>
          <?php endif; ?>
        </div>
      </article>
      <?php endforeach; ?>
    </div>

    <?php else: ?>
    <div class="s-card p-12 text-center">
      <div class="w-12 h-12 mx-auto mb-4 rounded-full bg-white/5 flex items-center justify-center text-slate-400"><?= ico('inbox', 'w-6 h-6') ?></div>
      <div class="font-medium mb-1">No <?= e($filter === 'active' ? 'live' : ($filter === 'pending' ? 'upcoming' : $filter)) ?> draws</div>
      <div class="text-sm text-slate-400">Check back soon.</div>
    </div>
    <?php endif; ?>
  </div>
</div>
<script>window.APP_URL = '<?= APP_URL ?>';</script>
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
</body></html>
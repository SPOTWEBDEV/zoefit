<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
$auth = requireAdmin(); $adminId = $auth['id'];
$db = getDB();

$page    = max(1,(int)($_GET['page']??1));
$perPage = 30;
$offset  = ($page-1)*$perPage;
$actor   = $_GET['actor'] ?? 'all';
$action  = trim($_GET['action'] ?? '');
$search  = trim($_GET['q'] ?? '');

$where = "1=1"; $params = [];
if ($actor !== 'all')  { $where .= " AND actor_type=?"; $params[] = $actor; }
if ($action)           { $where .= " AND action=?";      $params[] = $action; }
if ($search)           { $where .= " AND (description LIKE ? OR action LIKE ?)"; $s="%$search%"; $params=array_merge($params,[$s,$s]); }

$logs = $db->prepare("SELECT * FROM audit_logs WHERE $where ORDER BY created_at DESC LIMIT $perPage OFFSET $offset");
$logs->execute($params); $logs = $logs->fetchAll();

$cnt = $db->prepare("SELECT COUNT(*) FROM audit_logs WHERE $where");
$cnt->execute($params); $total = $cnt->fetchColumn();
$pages = ceil($total/$perPage);

// Get distinct actions for filter
$actions = $db->query("SELECT DISTINCT action FROM audit_logs ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);

$aPage = 'audit'; $pageTitle = 'Audit Logs';
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?= e($pageTitle) ?> — <?= APP_NAME ?> Admin</title>
  <script src="<?= APP_URL ?>/assets/js/tailwind.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
  <style>*{font-family:'Poppins',sans-serif!important}</style>
</head>
<body class="bg-[#0a0f1a] text-white">
<?php include __DIR__ . '/../components/admin-sidebar.php'; ?>
<div class="main-content">
  <div class="topbar">
    <button onclick="toggleSidebar()" class="md:hidden text-gray-400 text-2xl mr-3">☰</button>
    <h1 class="text-xl font-bold">Audit Logs</h1>
    <div class="text-sm text-gray-400"><?= number_format($total) ?> entries</div>
  </div>
  <div class="p-6">
    <!-- Filters -->
    <form method="GET" class="flex flex-wrap gap-3 mb-5">
      <input type="text" name="q" class="form-control flex-1 min-w-48" placeholder="Search action or description…" value="<?= e($search) ?>">
      <select name="actor" class="form-control w-40">
        <option value="all">All Actors</option>
        <?php foreach(['user','admin','vendor','super_admin','system'] as $a): ?>
        <option value="<?= $a ?>" <?= $actor===$a?'selected':'' ?>><?= ucfirst(str_replace('_',' ',$a)) ?></option>
        <?php endforeach; ?>
      </select>
      <select name="action" class="form-control w-48">
        <option value="">All Actions</option>
        <?php foreach($actions as $a): ?>
        <option value="<?= e($a) ?>" <?= $action===$a?'selected':'' ?>><?= e($a) ?></option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="btn btn-primary px-6">Filter</button>
      <a href="?" class="btn btn-secondary px-4">Clear</a>
    </form>

    <div class="card">
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>#</th>
              <th>Actor</th>
              <th>Action</th>
              <th>Description</th>
              <th>Entity</th>
              <th>IP Address</th>
              <th>Time</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($logs as $log): ?>
            <tr>
              <td class="text-xs text-gray-600"><?= $log['id'] ?></td>
              <td>
                <?php $actorColors = ['user'=>'badge-info','admin'=>'badge-warning','vendor'=>'badge-success','super_admin'=>'badge-danger','system'=>'badge-muted']; ?>
                <span class="badge <?= $actorColors[$log['actor_type']] ?? 'badge-muted' ?> capitalize">
                  <?= e(str_replace('_',' ',$log['actor_type'])) ?> #<?= $log['actor_id'] ?>
                </span>
              </td>
              <td><code class="text-orange-400 text-xs bg-orange-500/10 px-2 py-0.5 rounded"><?= e($log['action']) ?></code></td>
              <td class="text-xs text-gray-400 max-w-xs">
                <div class="truncate" title="<?= e($log['description']) ?>"><?= e($log['description'] ?: '—') ?></div>
              </td>
              <td class="text-xs text-gray-500">
                <?php if($log['entity_type']&&$log['entity_id']): ?>
                  <span class="badge badge-muted"><?= e($log['entity_type']) ?> #<?= $log['entity_id'] ?></span>
                <?php else: ?>—<?php endif; ?>
              </td>
              <td class="text-xs text-gray-500 font-mono"><?= e($log['ip_address'] ?: '—') ?></td>
              <td class="text-xs text-gray-500 whitespace-nowrap"><?= date('M j, Y', strtotime($log['created_at'])) ?><br><span class="text-gray-600"><?= date('g:i:s a', strtotime($log['created_at'])) ?></span></td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$logs): ?>
            <tr><td colspan="7" class="text-center text-gray-500 py-10">No logs found</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
      <!-- Pagination -->
      <?php if ($pages > 1): ?>
      <div class="flex items-center justify-between p-4 border-t border-white/5">
        <div class="text-sm text-gray-400">Page <?= $page ?> of <?= $pages ?> (<?= number_format($total) ?> total)</div>
        <div class="flex gap-2">
          <?php if($page>1): ?><a href="?page=<?=$page-1?>&actor=<?=urlencode($actor)?>&action=<?=urlencode($action)?>&q=<?=urlencode($search)?>" class="btn btn-sm btn-secondary">← Prev</a><?php endif; ?>
          <?php if($page<$pages): ?><a href="?page=<?=$page+1?>&actor=<?=urlencode($actor)?>&action=<?=urlencode($action)?>&q=<?=urlencode($search)?>" class="btn btn-sm btn-secondary">Next →</a><?php endif; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<script>
  window.APP_URL = '<?= APP_URL ?>';
</script>
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
</body></html>

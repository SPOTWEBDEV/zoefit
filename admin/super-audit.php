<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
$auth = requireSuperAdmin(); $saId = $auth['id'];
$db = getDB();

$page=max(1,(int)($_GET['page']??1)); $per=50; $offset=($page-1)*$per;
$actor=trim($_GET['actor']??'all');
$q=trim($_GET['q']??'');

$where="1=1"; $params=[];
if ($actor!=='all') { $where.=" AND actor_type=?"; $params[]=$actor; }
if ($q) { $where.=" AND (description LIKE ? OR action LIKE ? OR ip_address LIKE ?)"; $s="%$q%"; $params=array_merge($params,[$s,$s,$s]); }

$logs=$db->prepare("SELECT * FROM audit_logs WHERE $where ORDER BY created_at DESC LIMIT $per OFFSET $offset");
$logs->execute($params); $logs=$logs->fetchAll();
$cnt=$db->prepare("SELECT COUNT(*) FROM audit_logs WHERE $where");$cnt->execute($params);$total=$cnt->fetchColumn();
$pages=ceil($total/$per);
$saPage='audit';
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Audit Logs — <?= APP_NAME ?> Super Admin</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= APP_URL ?>/assets/css/app.css">
  <style>*{font-family:'Poppins',sans-serif!important}</style>
</head>
<body class="bg-[#060b12] text-white">
<?php include __DIR__ . '/../components/super-sidebar.php'; ?>
<div class="main-content">
  <div class="topbar" style="background:#0a0f1a">
    <button onclick="toggleSidebar()" class="md:hidden text-gray-400 text-2xl mr-3">☰</button>
    <h1 class="text-xl font-bold">Full Audit Logs</h1>
    <div class="text-sm text-gray-400"><?= number_format($total) ?> records</div>
  </div>
  <div class="p-6">
    <form method="GET" class="flex flex-wrap gap-3 mb-5">
      <input type="text" name="q" class="form-control flex-1 min-w-48" placeholder="Search…" value="<?= e($q) ?>">
      <select name="actor" class="form-control w-44">
        <option value="all">All Actors</option>
        <?php foreach(['user','admin','vendor','super_admin','system'] as $a): ?>
        <option value="<?= $a ?>" <?= $actor===$a?'selected':'' ?>><?= ucfirst(str_replace('_',' ',$a)) ?></option>
        <?php endforeach; ?>
      </select>
      <button class="btn btn-primary px-5">Filter</button>
      <a href="?" class="btn btn-secondary">Clear</a>
    </form>
    <div class="card">
      <div class="table-wrap">
        <table>
          <thead><tr><th>ID</th><th>Actor</th><th>Action</th><th>Description</th><th>IP</th><th>User Agent</th><th>Time</th></tr></thead>
          <tbody>
            <?php foreach($logs as $l): ?>
            <tr>
              <td class="text-xs text-gray-600"><?= $l['id'] ?></td>
              <td><?php $c=['user'=>'badge-info','admin'=>'badge-warning','vendor'=>'badge-success','super_admin'=>'badge-danger','system'=>'badge-muted']; ?><span class="badge <?= $c[$l['actor_type']]??'badge-muted' ?>"><?= e(str_replace('_',' ',$l['actor_type'])) ?> #<?= $l['actor_id'] ?></span></td>
              <td><code class="text-orange-400 text-xs bg-orange-500/8 px-2 py-0.5 rounded"><?= e($l['action']) ?></code></td>
              <td class="text-xs text-gray-400 max-w-xs"><div class="truncate" title="<?= e($l['description']) ?>"><?= e($l['description']?:'—') ?></div></td>
              <td class="text-xs font-mono text-gray-500"><?= e($l['ip_address']?:'—') ?></td>
              <td class="text-xs text-gray-600 max-w-32"><div class="truncate" title="<?= e($l['user_agent']??'') ?>"><?= e(substr($l['user_agent']??'—',0,30)) ?></div></td>
              <td class="text-xs text-gray-500 whitespace-nowrap"><?= date('M j, Y g:ia',strtotime($l['created_at'])) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php if($pages>1): ?>
      <div class="flex justify-between items-center p-4 border-t border-white/5">
        <div class="text-sm text-gray-400">Page <?= $page ?>/<?= $pages ?></div>
        <div class="flex gap-2">
          <?php if($page>1): ?><a href="?page=<?=$page-1?>&actor=<?=urlencode($actor)?>&q=<?=urlencode($q)?>" class="btn btn-sm btn-secondary">← Prev</a><?php endif; ?>
          <?php if($page<$pages): ?><a href="?page=<?=$page+1?>&actor=<?=urlencode($actor)?>&q=<?=urlencode($q)?>" class="btn btn-sm btn-secondary">Next →</a><?php endif; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>
<script src="<?= APP_URL ?>/assets/js/app.js"></script>
</body></html>

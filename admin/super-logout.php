<?php
require_once __DIR__ . '/../config/config.php';
startAppSession();
if (!empty($_SESSION['super_admin_id'])) {
  require_once __DIR__ . '/../config/database.php';
  auditLog('super_admin',$_SESSION['super_admin_id'],'logout','Super admin logged out');
}
session_destroy();
redirect(APP_URL.'/admin/super-login.php');

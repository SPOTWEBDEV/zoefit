<?php
require_once __DIR__ . '/../config/config.php';
startAppSession();
if (!empty($_SESSION['admin_id'])) {
  require_once __DIR__ . '/../config/database.php';
  auditLog('admin',$_SESSION['admin_id'],'logout','Admin logged out');
}
session_destroy();
redirect(APP_URL.'/admin/login.php');

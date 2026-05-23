<?php
require_once __DIR__ . '/../config/config.php';
startAppSession();
if (!empty($_SESSION['vendor_id'])) {
  require_once __DIR__ . '/../config/database.php';
  auditLog('vendor',$_SESSION['vendor_id'],'logout','Vendor logged out');
}
session_destroy();
redirect(APP_URL.'/vendor/login.php');

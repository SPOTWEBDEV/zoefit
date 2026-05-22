<?php
require_once __DIR__ . '/../config/config.php';
startAppSession();
if (!empty($_SESSION['user_id'])) {
    require_once __DIR__ . '/../config/database.php';
    auditLog('user', $_SESSION['user_id'], 'logout', 'User logged out');
}
session_destroy();
redirect(APP_URL . '/user/login.php');

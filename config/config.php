<?php
// config/config.php
define('APP_NAME', 'ZoeFeeds');
define('APP_URL', 'http://localhost/zoefeeds');
define('APP_VERSION', '1.0.0');

// Session config
define('SESSION_NAME', 'zf_session');
define('SESSION_LIFETIME', 7200); // 2 hours

// Security
define('CSRF_TOKEN_NAME', '_zf_csrf');

// Rate limiting
define('LOGIN_MAX_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_MINUTES', 15);

// Pagination
define('CODES_PER_PAGE', 20);
define('LOGS_PER_PAGE', 25);

// Code generation
define('CODE_LENGTH', 15);

// Upload paths
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('UPLOAD_URL', APP_URL . '/uploads/');

// Timezone
date_default_timezone_set('Africa/Lagos');

// Start session
function startAppSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path'     => '/',
            'secure'   => isset($_SERVER['HTTPS']),
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();
    }
}

// CSRF helpers
function generateCsrf(): string {
    startAppSession();
    if (empty($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

function verifyCsrf(string $token): bool {
    startAppSession();
    return isset($_SESSION[CSRF_TOKEN_NAME]) && hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

function csrfField(): string {
    return '<input type="hidden" name="' . CSRF_TOKEN_NAME . '" value="' . htmlspecialchars(generateCsrf()) . '">';
}

// XSS
function e(mixed $val): string {
    return htmlspecialchars((string)$val, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

// Phone normalization
function normalizePhone(string $phone): string {
    $phone = preg_replace('/\D/', '', $phone);
    if (str_starts_with($phone, '0')) {
        $phone = '234' . substr($phone, 1);
    } elseif (str_starts_with($phone, '234')) {
        // already good
    } elseif (strlen($phone) === 10) {
        $phone = '234' . $phone;
    }
    return $phone;
}

function formatPhone(string $phone): string {
    // Display as 0XXXXXXXXXX
    if (str_starts_with($phone, '234')) {
        return '0' . substr($phone, 3);
    }
    return $phone;
}

// Audit logger
function auditLog(string $actorType, int $actorId, string $action, string $description = '', string $entityType = '', int $entityId = 0): void {
    try {
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO audit_logs (actor_type, actor_id, action, description, entity_type, entity_id, ip_address, user_agent) VALUES (?,?,?,?,?,?,?,?)");
        $stmt->execute([
            $actorType, $actorId, $action, $description, $entityType, $entityId ?: null,
            $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? ''
        ]);
    } catch (Exception $e) {
        // Fail silently for audit logs
    }
}

// Notification helper
function createNotification(int $userId, string $title, string $message, string $type = 'info'): void {
    try {
        $db = getDB();
        $db->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?,?,?,?)")
           ->execute([$userId, $title, $message, $type]);
    } catch (Exception $e) {}
}

// JSON response
function jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// Auth guards
function requireUser(): array {
    startAppSession();
    if (empty($_SESSION['user_id'])) {
        if (isAjax()) jsonResponse(['error' => 'Unauthenticated'], 401);
        header('Location: ' . APP_URL . '/user/login.php');
        exit;
    }
    return ['id' => $_SESSION['user_id'], 'name' => $_SESSION['user_name'] ?? ''];
}

function requireAdmin(): array {
    startAppSession();
    if (empty($_SESSION['admin_id'])) {
        if (isAjax()) jsonResponse(['error' => 'Unauthenticated'], 401);
        header('Location: ' . APP_URL . '/admin/login.php');
        exit;
    }
    return ['id' => $_SESSION['admin_id'], 'name' => $_SESSION['admin_name'] ?? ''];
}

function requireVendor(): array {
    startAppSession();
    if (empty($_SESSION['vendor_id'])) {
        if (isAjax()) jsonResponse(['error' => 'Unauthenticated'], 401);
        header('Location: ' . APP_URL . '/vendor/login.php');
        exit;
    }
    return ['id' => $_SESSION['vendor_id'], 'name' => $_SESSION['vendor_name'] ?? ''];
}

function requireSuperAdmin(): array {
    startAppSession();
    if (empty($_SESSION['super_admin_id'])) {
        if (isAjax()) jsonResponse(['error' => 'Unauthenticated'], 401);
        header('Location: ' . APP_URL . '/admin/super-login.php');
        exit;
    }
    return ['id' => $_SESSION['super_admin_id'], 'name' => $_SESSION['super_admin_name'] ?? ''];
}

function isAjax(): bool {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

function isPost(): bool {
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

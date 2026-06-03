<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// database.php must be loaded first (it sets ZF_ENV)
require_once __DIR__ . '/database.php';

// ============================================================
// RELEASE VERSION FLAGS
// ============================================================
// V1 = true  → Vendor system is hidden from all UI (pages still
//              exist on disk, just not linked or accessible via nav).
//              Set to false when you are ready to launch vendor features.
define('ZF_V1_MODE',         true);   // true = vendor features hidden in UI
define('ZF_SHOW_VENDOR_NAV', false);  // false = hide vendor nav items & apply page
define('ZF_SHOW_TRANSFER',   false);  // false = hide transfer page from user nav
 

// ============================================================
// APP IDENTITY
// ============================================================
define('APP_NAME',    'ZoeFeeds');
define('APP_VERSION', '1.0.0');
define('APP_TAGLINE', 'Loyalty Reward & Raffle Platform');

// ============================================================
// APP URL — auto-resolved per environment
// The APP_URL is built from the actual request so it always
// matches the current protocol (http or https) and domain.
// You can also hard-code it per environment below.
// ============================================================

function zf_build_app_url(): string {
    // --- Override map: set a hard-coded URL per environment ---
    $overrides = [
        'local'      => 'http://localhost/zoofeeds',
        'staging'    => 'http://staging.zoefeeds.com',
        'production' => 'https://www.zoefeeds.com',
    ];

    if (isset($overrides[ZF_ENV])) {
        return rtrim($overrides[ZF_ENV], '/');
    }

    // --- Auto-detect from server variables ---
    $isHttps =
        (!empty($_SERVER['HTTPS'])                  && $_SERVER['HTTPS']                  !== 'off') ||
        (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') ||
        (!empty($_SERVER['HTTP_X_FORWARDED_SSL'])   && strtolower($_SERVER['HTTP_X_FORWARDED_SSL'])   === 'on')    ||
        (!empty($_SERVER['SERVER_PORT'])            && (int)$_SERVER['SERVER_PORT']                   === 443)     ||
        (!empty($_SERVER['REQUEST_SCHEME'])         && strtolower($_SERVER['REQUEST_SCHEME'])          === 'https');

    $scheme = $isHttps ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';

    // Determine the subfolder path (e.g. /zoefeeds on localhost, or / on live domain)
    // We walk up from the current script to find the project root.
    $scriptDir  = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    $rootMarker = '/config';   // This file lives in /config — strip it

    // Find the project root by removing known sub-paths
    $subPaths = ['/config', '/user', '/vendor', '/admin', '/ajax'];
    $basePath = $scriptDir;
    foreach ($subPaths as $sub) {
        $pos = strrpos($basePath, $sub);
        if ($pos !== false) {
            $basePath = substr($basePath, 0, $pos);
            break;
        }
    }
    $basePath = rtrim($basePath, '/');

    return $scheme . '://' . $host . $basePath;
}

define('APP_URL', zf_build_app_url());

// ============================================================
// TIMEZONE
// ============================================================
date_default_timezone_set('Africa/Lagos');

// ============================================================
// SESSION CONFIGURATION
// ============================================================
define('SESSION_NAME',     'zf_session');
define('SESSION_LIFETIME', 7200);   // 2 hours in seconds

// ============================================================
// SECURITY
// ============================================================
define('CSRF_TOKEN_NAME', '_zf_csrf');

// Login rate-limiting
define('LOGIN_MAX_ATTEMPTS',    5);
define('LOGIN_LOCKOUT_MINUTES', 15);

// Cookie security: enforce Secure flag only on HTTPS
define('COOKIE_SECURE', ZF_ENV === 'production' || ZF_ENV === 'staging');

// ============================================================
// PAGINATION
// ============================================================
define('CODES_PER_PAGE', 20);
define('LOGS_PER_PAGE',  25);

// ============================================================
// CODE GENERATION
// ============================================================
define('CODE_LENGTH', 15);

// ============================================================
// FILE UPLOADS
// ============================================================
define('UPLOAD_PATH', realpath(__DIR__ . '/../uploads') . DIRECTORY_SEPARATOR);
define('UPLOAD_URL',  APP_URL . '/uploads/');
define('UPLOAD_MAX_MB', 10);
define('UPLOAD_ALLOWED_TYPES', ['jpg','jpeg','png','gif','webp']);

// ============================================================
// SESSION BOOTSTRAP
// ============================================================
function startAppSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_name(SESSION_NAME);
        session_set_cookie_params([
            'lifetime' => SESSION_LIFETIME,
            'path'     => '/',
            'secure'   => COOKIE_SECURE,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();
    }
}

// ============================================================
// CSRF HELPERS
// ============================================================
function generateCsrf(): string {
    startAppSession();
    if (empty($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

function verifyCsrf(string $token): bool {
    startAppSession();
    return !empty($_SESSION[CSRF_TOKEN_NAME])
        && hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

function csrfField(): string {
    return '<input type="hidden" name="' . CSRF_TOKEN_NAME
         . '" value="' . htmlspecialchars(generateCsrf(), ENT_QUOTES, 'UTF-8') . '">';
}

// ============================================================
// OUTPUT ESCAPING
// ============================================================
function e(mixed $val): string {
    return htmlspecialchars((string)$val, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

// ============================================================
// PHONE NORMALIZATION
// Accepts: 080xxxxxxxx, 081xxxxxxxx, +234xxxxxxxxxx,
//          234xxxxxxxxxx, 70xxxxxxxx (10-digit)
// Stores as: 234XXXXXXXXXX
// ============================================================
function normalizePhone(string $phone): string {
    // Strip everything except digits
    $phone = preg_replace('/\D/', '', $phone);

    if (str_starts_with($phone, '234') && strlen($phone) === 13) {
        return $phone;                          // Already 234XXXXXXXXXX
    }
    if (str_starts_with($phone, '0') && strlen($phone) === 11) {
        return '234' . substr($phone, 1);       // 0XXXXXXXXXX → 234XXXXXXXXXX
    }
    if (!str_starts_with($phone, '0') && !str_starts_with($phone, '234') && strlen($phone) === 10) {
        return '234' . $phone;                  // 10-digit without leading 0
    }
    // Strip leading 234 then re-apply (handles edge cases like 2340xxx)
    if (str_starts_with($phone, '234')) {
        return $phone;
    }
    return $phone; // Return as-is; validation happens upstream
}

function formatPhone(string $phone): string {
    if (str_starts_with($phone, '234') && strlen($phone) === 13) {
        return '0' . substr($phone, 3);         // 234XXXXXXXXXX → 0XXXXXXXXXX
    }
    return $phone;
}

// ============================================================
// AUDIT LOGGER
// ============================================================
function auditLog(
    string $actorType,
    int    $actorId,
    string $action,
    string $description = '',
    string $entityType  = '',
    int    $entityId    = 0
): void {
    try {
        $db   = getDB();
        $ip   = $_SERVER['REMOTE_ADDR']     ?? '';
        $ua   = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $stmt = $db->prepare(
            "INSERT INTO audit_logs
             (actor_type, actor_id, action, description, entity_type, entity_id, ip_address, user_agent)
             VALUES (?,?,?,?,?,?,?,?)"
        );
        $stmt->execute([
            $actorType,
            $actorId,
            $action,
            $description,
            $entityType     ?: null,
            $entityId > 0   ? $entityId : null,
            $ip,
            $ua,
        ]);
    } catch (Exception $e) {
        // Fail silently — audit logs must never break the app
    }
}

// ============================================================
// NOTIFICATION HELPER
// ============================================================
function createNotification(
    int    $userId,
    string $title,
    string $message,
    string $type = 'info'
): void {
    try {
        $db = getDB();
        $db->prepare(
            "INSERT INTO notifications (user_id, title, message, type) VALUES (?,?,?,?)"
        )->execute([$userId, $title, $message, $type]);
    } catch (Exception $e) {
        // Fail silently
    }
}

// ============================================================
// JSON RESPONSE
// ============================================================
function jsonResponse(array $data, int $httpCode = 200): void {
    http_response_code($httpCode);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

// ============================================================
// REDIRECT
// ============================================================
function redirect(string $url): void {
    header('Location: ' . $url, true, 302);
    exit;
}

// ============================================================
// REQUEST HELPERS
// ============================================================
function isAjax(): bool {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
        && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

function isPost(): bool {
    return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST';
}

function isGet(): bool {
    return ($_SERVER['REQUEST_METHOD'] ?? '') === 'GET';
}

// ============================================================
// AUTH GUARDS
// ============================================================
function requireUser(): array {
    startAppSession();
    if (empty($_SESSION['user_id'])) {
        if (isAjax()) {
            jsonResponse(['error' => 'Session expired. Please log in.'], 401);
        }
        redirect(APP_URL . '/user/login.php');
    }
    return [
        'id'   => (int)$_SESSION['user_id'],
        'name' => $_SESSION['user_name'] ?? '',
    ];
}

function requireAdmin(): array {
    startAppSession();
    if (empty($_SESSION['admin_id'])) {
        if (isAjax()) {
            jsonResponse(['error' => 'Session expired. Please log in.'], 401);
        }
        redirect(APP_URL . '/admin/login.php');
    }
    return [
        'id'   => (int)$_SESSION['admin_id'],
        'name' => $_SESSION['admin_name'] ?? '',
    ];
}

function requireVendor(): array {
    startAppSession();
    if (empty($_SESSION['vendor_id'])) {
        if (isAjax()) {
            jsonResponse(['error' => 'Session expired. Please log in.'], 401);
        }
        redirect(APP_URL . '/vendor/login.php');
    }
    return [
        'id'   => (int)$_SESSION['vendor_id'],
        'name' => $_SESSION['vendor_name'] ?? '',
    ];
}

function requireSuperAdmin(): array {
    startAppSession();
    if (empty($_SESSION['super_admin_id'])) {
        if (isAjax()) {
            jsonResponse(['error' => 'Session expired. Please log in.'], 401);
        }
        redirect(APP_URL . '/admin/super-login.php');
    }
    return [
        'id'   => (int)$_SESSION['super_admin_id'],
        'name' => $_SESSION['super_admin_name'] ?? '',
    ];
}

// ============================================================
// DEV HELPER — visible only on local, silent on production
// Usage: zf_debug($variable);
// ============================================================
function zf_debug(mixed $data): void {
    if (ZF_ENV !== 'local') return;
    echo '<pre style="background:#1a1a2e;color:#00ff88;padding:16px;margin:8px;border-radius:8px;font-size:13px;overflow:auto;z-index:9999;position:relative">';
    echo htmlspecialchars(print_r($data, true), ENT_QUOTES, 'UTF-8');
    echo '</pre>';
}



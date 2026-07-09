<?php

define('ZF_FORCE_ENV', 'production');

function zf_detect_env(): string {
    if (ZF_FORCE_ENV !== '') {
        return ZF_FORCE_ENV;
    }

    // CLI context (cron jobs, etc.) has no $_SERVER HTTPS vars to inspect,
    // so it always fell through to 'local' before. Require an explicit
    // ZF_ENV env var for CLI runs instead of silently guessing wrong.
    if (php_sapi_name() === 'cli') {
        $cliEnv = $_ENV['ZF_ENV']; 
        if ($cliEnv !== false && in_array($cliEnv, ['local', 'staging', 'production'], true)) {
            return $cliEnv;
        }
        fwrite(STDERR, "ZF_ENV environment variable not set for CLI execution. "
            . "Set it in your crontab, e.g.: ZF_ENV=production php cron/finalize-expired-draws.php\n");
        exit(1);
    }

    $isHttps =
        (!empty($_SERVER['HTTPS'])              && $_SERVER['HTTPS']              !== 'off') ||
        (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') ||
        (!empty($_SERVER['HTTP_X_FORWARDED_SSL'])   && strtolower($_SERVER['HTTP_X_FORWARDED_SSL'])   === 'on')  ||
        (!empty($_SERVER['SERVER_PORT'])        && (int)$_SERVER['SERVER_PORT']   === 443)  ||
        (!empty($_SERVER['REQUEST_SCHEME'])     && strtolower($_SERVER['REQUEST_SCHEME'])    === 'https');

    return $isHttps ? 'production' : 'local';
}

$ZF_ENV = zf_detect_env();

// -----------------------------------------------------------
// DATABASE CREDENTIALS PER ENVIRONMENT
// -----------------------------------------------------------

$ZF_DB_CONFIGS = [

    // -------------------------------------------------------
    // LOCAL / DEVELOPMENT  (HTTP — e.g. localhost, XAMPP, Laragon)
    // -------------------------------------------------------
    'local' => [
        'host'    => 'localhost',
        'name'    => 'zoefeeds',           // Local DB name
        'user'    => 'root',               // Local DB username
        'pass'    => '',                   // Local DB password (blank for XAMPP/Laragon)
        'charset' => 'utf8mb4',
        'port'    => 3306,
    ],

    // -------------------------------------------------------
    // STAGING  (HTTP on a staging server — optional)
    // -------------------------------------------------------
    'staging' => [
        'host'    => 'localhost',
        'name'    => 'zoefeeds_db',   // Staging DB name
        'user'    => 'zoefeeds_db',       // Staging DB username
        'pass'    => 'zoefeeds_db', // Staging DB password
        'charset' => 'utf8mb4',
        'port'    => 3306,
    ],

    // -------------------------------------------------------
    // PRODUCTION / LIVE  (HTTPS — your live hosting server)
    // -------------------------------------------------------
    'production' => [
        'host'    => 'localhost',           // Usually 'localhost' on cPanel hosting
        'name'    => 'zoefeeds_db', // cPanel DB name  (e.g. username_dbname)
        'user'    => 'zoefeeds_db',   // cPanel DB username
        'pass'    => 'zoefeeds_db', // cPanel DB password
        'charset' => 'utf8mb4',
        'port'    => 3306,
    ],

];

// Select the correct config for detected environment
$ZF_DB = $ZF_DB_CONFIGS[$ZF_ENV] ?? $ZF_DB_CONFIGS['local'];

// Define constants for use throughout the app
define('DB_HOST',    $ZF_DB['host']);
define('DB_NAME',    $ZF_DB['name']);
define('DB_USER',    $ZF_DB['user']);
define('DB_PASS',    $ZF_DB['pass']);
define('DB_CHARSET', $ZF_DB['charset']);
define('DB_PORT',    $ZF_DB['port']);
define('ZF_ENV',     $ZF_ENV);

unset($ZF_DB, $ZF_DB_CONFIGS);  // Clean up sensitive data from memory

// -----------------------------------------------------------
// PDO CONNECTION  (singleton — one connection per request)
// -----------------------------------------------------------

function getDB(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_PERSISTENT         => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            http_response_code(500);
            // Only show details in local env; hide them in production
            if (ZF_ENV === 'local') {
                $detail = htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
                die(json_encode([
                    'error' => 'Database connection failed.',
                    'detail' => $detail,
                    'env'   => ZF_ENV,
                ]));
            } else {
                die(json_encode(['error' => 'Service temporarily unavailable. Please try again later.']));
            }
        }
    }

    return $pdo;
}

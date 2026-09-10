<?php

// 1. Autoload Composer dependencies & load .env safely
require_once __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

define('ZF_FORCE_ENV', $_ENV['APP_ENV'] ?? '');

function zf_detect_env(): string {
    if (ZF_FORCE_ENV !== '') {
        return ZF_FORCE_ENV;
    }

    if (php_sapi_name() === 'cli') {
        $cliEnv = $_ENV['ZF_ENV'] ?? $_ENV['APP_ENV'] ?? false; 
        if ($cliEnv !== false && in_array($cliEnv, ['local', 'staging', 'production'], true)) {
            return $cliEnv;
        }
        fwrite(STDERR, "ZF_ENV environment variable not set for CLI execution. "
            . "Set it in your crontab, e.g.: ZF_ENV=production php cron/finalize-expired-draws.php\n");
        exit(1);
    }

    $isHttps =
        (!empty($_SERVER['HTTPS'])                  && $_SERVER['HTTPS']              !== 'off') ||
        (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') ||
        (!empty($_SERVER['HTTP_X_FORWARDED_SSL'])   && strtolower($_SERVER['HTTP_X_FORWARDED_SSL'])   === 'on')  ||
        (!empty($_SERVER['SERVER_PORT'])            && (int)$_SERVER['SERVER_PORT']   === 443)  ||
        (!empty($_SERVER['REQUEST_SCHEME'])         && strtolower($_SERVER['REQUEST_SCHEME'])    === 'https');

    return $isHttps ? 'production' : 'local';
}

$ZF_ENV = zf_detect_env();

// -----------------------------------------------------------
// DEFINE CONSTANTS DIRECTLY FROM .ENV (WITH FALLBACKS)
// -----------------------------------------------------------

define('DB_HOST',    $_ENV['DB_HOST']    ?? '127.0.0.1');
define('DB_NAME',    $_ENV['DB_NAME']    ?? 'zoefeeds');
define('DB_USER',    $_ENV['DB_USER']    ?? 'root');
define('DB_PASS',    $_ENV['DB_PASS']    ?? '');
define('DB_CHARSET', $_ENV['DB_CHARSET'] ?? 'utf8mb4');
define('DB_PORT',    $_ENV['DB_PORT']    ?? 3306);


// -----------------------------------------------------------
// PDO CONNECTION (singleton — one connection per request)
// -----------------------------------------------------------

function getDB(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            DB_HOST, (int)DB_PORT, DB_NAME, DB_CHARSET
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
            if (ZF_ENV === 'local') {
                $detail = htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
                header('Content-Type: application/json; charset=utf-8');
                die(json_encode([
                    'error'  => 'Database connection failed.',
                    'detail' => $detail,
                    'env'    => ZF_ENV,
                ]));
            } else {
                header('Content-Type: application/json; charset=utf-8');
                die(json_encode(['error' => 'Service temporarily unavailable. Please try again later.']));
            }
        }
    }

    return $pdo;
}
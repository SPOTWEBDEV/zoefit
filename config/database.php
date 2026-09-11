<?php

// 1. Load Composer autoloader from the root directory (zoofeeds/vendor)
require_once dirname(__DIR__) . '/vendors/autoload.php';

// 2. Load .env safely from the root directory (zoofeeds/.env)
$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

// -----------------------------------------------------------
// DB credentials — read straight from $_SERVER / $_ENV
// -----------------------------------------------------------
define('DB_HOST',    $_SERVER['DB_HOST']    ?? $_ENV['DB_HOST']    ?? '127.0.0.1');
define('DB_PORT',    $_SERVER['DB_PORT']    ?? $_ENV['DB_PORT']    ?? 3306);
define('DB_NAME',    $_SERVER['DB_NAME']    ?? $_ENV['DB_NAME']    ?? 'zoefeeds');
define('DB_USER',    $_SERVER['DB_USER']    ?? $_ENV['DB_USER']    ?? 'root');
define('DB_PASS',    $_SERVER['DB_PASS']    ?? $_ENV['DB_PASS']    ?? '');
define('DB_CHARSET', $_SERVER['DB_CHARSET'] ?? $_ENV['DB_CHARSET'] ?? 'utf8mb4');

// -----------------------------------------------------------
// PDO CONNECTION (singleton — one connection per request)
// -----------------------------------------------------------
function getDB(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            DB_HOST, (int) DB_PORT, DB_NAME, DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log('[DB CONNECTION FAILED] ' . $e->getMessage());

            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');

            die(json_encode([
                'error'  => 'Database connection failed.',
                'detail' => $e->getMessage(),
            ]));
        }
    }

    return $pdo;
}
<?php
if (PHP_SAPI !== 'cli' && session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION)) {
    $_SESSION = [];
}

if (!defined('AUTH_COOKIE_NAME')) {
    define('AUTH_COOKIE_NAME', 'rentalsystem_auth');
}
if (!defined('AUTH_COOKIE_SECRET')) {
    define('AUTH_COOKIE_SECRET', 'xE4f8uPq2B7nK1wLzV9sY0mTtQ3rGaHi');
}
if (!defined('AUTH_COOKIE_DURATION')) {
    define('AUTH_COOKIE_DURATION', 60 * 60 * 24 * 30);
}
if (!defined('AUTH_COOKIE_SAMESITE')) {
    define('AUTH_COOKIE_SAMESITE', 'Lax');
}

$host = '127.0.0.1';
$db   = 'rentalsystem';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$serverDsn = "mysql:host=$host;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    try {
        $pdo = new PDO($dsn, $user, $pass, $options);
    } catch (PDOException $e) {
        if ((int) $e->getCode() !== 1049) {
            throw $e;
        }

        $serverPdo = new PDO($serverDsn, $user, $pass, $options);
        $serverPdo->exec("CREATE DATABASE IF NOT EXISTS `$db` CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci");
        $pdo = new PDO($dsn, $user, $pass, $options);
    }

    require_once __DIR__ . '/schema.php';
    ensureDatabaseSchema($pdo);
} catch (PDOException $e) {
    http_response_code(500);
    echo "Database connection failed: " . htmlspecialchars($e->getMessage());
    exit;
}

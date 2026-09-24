<?php

require_once __DIR__ . '/env.php';

date_default_timezone_set(env('APP_TIMEZONE', 'Asia/Manila'));

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

define('DB_HOST', env('DB_HOST', 'localhost'));
define('DB_NAME', env('DB_NAME', 'it_management_system'));
define('DB_USER', env('DB_USER', 'root'));
define('DB_PASS', env('DB_PASS', ''));
define('APP_KEY', env('APP_KEY', ''));
define('APP_URL', env('APP_URL', ''));
define('CRON_SECRET', env('CRON_SECRET', ''));
define('MAIL_FROM_ADDRESS', env('MAIL_FROM_ADDRESS', 'noreply@localhost'));
define('MAIL_FROM_NAME', env('MAIL_FROM_NAME', 'IT Management System'));

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("ERROR: Could not connect. " . $e->getMessage());
}

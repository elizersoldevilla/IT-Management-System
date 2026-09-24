<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

session_start();


if (isset($_SESSION['user_id'])) {
    log_action($pdo, $_SESSION['user_id'], 'LOGOUT', 'User logged out');
}


if (isset($_COOKIE['remember_me'])) {
    setcookie('remember_me', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}


$_SESSION = [];


if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
}


session_destroy();

header("Location: login.php");
exit;
?>

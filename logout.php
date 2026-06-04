<?php
session_set_cookie_params(['httponly' => true, 'samesite' => 'Strict']);
session_start();

require_once __DIR__ . '/config/db.php';
if (isset($_SESSION['user_id'])) {
    $pdo->prepare('INSERT INTO audit_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)')
        ->execute([$_SESSION['user_id'], 'LOGOUT', 'User logged out', $_SERVER['REMOTE_ADDR']]);
}

session_unset();
session_destroy();

setcookie(session_name(), '', [
    'expires' => time() - 3600,
    'path' => '/',
    'httponly' => true,
    'samesite' => 'Strict'
]);

header('Location: login.php');
exit;
?>
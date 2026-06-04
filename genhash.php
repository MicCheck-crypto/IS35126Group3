<?php
$password = 'Admin@1234';
$hash = password_hash($password, PASSWORD_BCRYPT);

require_once 'config/db.php';

$pdo->prepare('UPDATE users SET password = ? WHERE username = "admin"')->execute([$hash]);
$pdo->prepare('UPDATE users SET password = ? WHERE username = "manager1"')->execute([$hash]);

echo 'Password updated successfully!<br>';
echo 'Username: admin | Password: Admin@1234<br>';
echo 'Username: manager1 | Password: Admin@1234<br>';
echo '<br><a href="login.php">Go to Login</a>';
echo '<br><br><strong>DELETE THIS FILE AFTER USE!</strong>';
?>
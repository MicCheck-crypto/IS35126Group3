<?php
require_once __DIR__ . '/config/db.php';

$adminHash = password_hash('Admin@1234', PASSWORD_BCRYPT);
$managerHash = password_hash('Manager@1234', PASSWORD_BCRYPT);

$pdo->prepare('UPDATE users SET password = ? WHERE username = "admin"')->execute([$adminHash]);
$pdo->prepare('UPDATE users SET password = ? WHERE username = "manager1"')->execute([$managerHash]);

echo 'Done!<br>';
echo 'admin password: Admin@1234<br>';
echo 'manager1 password: Manager@1234<br>';
?>

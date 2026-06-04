<?php
require_once __DIR__ . '/config/db.php';
$hash = password_hash('Admin@1234', PASSWORD_BCRYPT);
$pdo->prepare('UPDATE users SET password = ? WHERE username = "admin"')->execute([$hash]);
$pdo->prepare('UPDATE users SET password = ? WHERE username = "manager1"')->execute([$hash]);
echo 'Done! Password set to Admin@1234';
echo '<br>Hash: ' . $hash;
?>

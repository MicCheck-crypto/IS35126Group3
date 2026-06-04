<?php
session_set_cookie_params(['httponly' => true, 'samesite' => 'Strict']);
session_start();

header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline'; style-src 'self' 'unsafe-inline'");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");

if (isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true) {
    header('Location: tenant/dashboard.php'); exit;
}

require_once __DIR__ . '/config/db.php';

$errors = [];
$success = '';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors[] = 'Invalid request. Please try again.';
    } else {
        $full_name = trim(strip_tags($_POST['full_name'] ?? ''));
        $username = trim(strip_tags($_POST['username'] ?? ''));
        $email = trim($_POST['email'] ?? '');
        $phone = trim(strip_tags($_POST['phone'] ?? ''));
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($full_name)) {
            $errors[] = 'Full name is required.';
        } elseif (strlen($full_name) < 2 || strlen($full_name) > 100) {
            $errors[] = 'Full name must be between 2 and 100 characters.';
        } elseif (!preg_match('/^[a-zA-Z\s]+$/', $full_name)) {
            $errors[] = 'Full name must contain letters and spaces only.';
        }

        if (empty($username)) {
            $errors[] = 'Username is required.';
        } elseif (strlen($username) < 3 || strlen($username) > 50) {
            $errors[] = 'Username must be between 3 and 50 characters.';
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            $errors[] = 'Username can only contain letters, numbers and underscores.';
        }

        if (empty($email)) {
            $errors[] = 'Email is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        }

        if (empty($phone)) {
            $errors[] = 'Phone number is required.';
        } elseif (!preg_match('/^[0-9+\s\-]{7,15}$/', $phone)) {
            $errors[] = 'Please enter a valid phone number.';
        }

        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        } elseif (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Password must contain at least one uppercase letter.';
        } elseif (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Password must contain at least one number.';
        }

        if ($password !== $confirm_password) {
            $errors[] = 'Passwords do not match.';
        }

        if (empty($errors)) {
            $stmt = $pdo->prepare('SELECT id FROM users WHERE username = ? OR email = ?');
            $stmt->execute([$username, $email]);
            if ($stmt->fetch()) {
                $errors[] = 'Username or email already exists. Please choose another.';
            } else {
                $hashed = password_hash($password, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare(
                    'INSERT INTO users (username, email, password, role, full_name, phone)
                     VALUES (?, ?, ?, "tenant", ?, ?)'
                );
                $stmt->execute([$username, $email, $hashed, $full_name, $phone]);
                $newId = $pdo->lastInsertId();
                $pdo->prepare('INSERT INTO audit_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)')
                    ->execute([$newId, 'REGISTER', 'New tenant registered', $_SERVER['REMOTE_ADDR']]);
                $success = 'Registration successful! You can now login.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1'>
    <title>Register — IS351 Property Management</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f0f4f8; display: flex;
            justify-content: center; align-items: center; min-height: 100vh; margin: 0; padding: 20px; }
        .card { background: #fff; border-radius: 10px; padding: 40px 36px;
            box-shadow: 0 4px 20px rgba(0,0,0,.12); width: 380px; }
        h2 { color: #1F4E79; margin-bottom: 4px; text-align: center; }
        .subtitle { text-align: center; color: #777; font-size: 13px; margin-bottom: 24px; }
        label { display: block; margin-bottom: 4px; font-size: 14px; color: #555; }
        input[type=text], input[type=email],
        input[type=password], input[type=tel] {
            width: 100%; padding: 10px; margin-bottom: 14px; border: 1px solid #ccc;
            border-radius: 6px; font-size: 15px; box-sizing: border-box; }
        input:focus { border-color: #2E75B6; outline: none; }
        button { width: 100%; padding: 11px; background: #2E75B6; color: #fff;
            border: none; border-radius: 6px; font-size: 16px; cursor: pointer; margin-top: 4px; }
        button:hover { background: #1F4E79; }
        .errors { background: #fdecea; color: #c0392b; padding: 12px;
            border-radius: 6px; margin-bottom: 16px; font-size: 14px; }
        .errors ul { padding-left: 18px; }
        .success { background: #e8f5e9; color: #2E7D32; padding: 12px;
            border-radius: 6px; margin-bottom: 16px; font-size: 14px; text-align: center; }
        .login-link { text-align: center; font-size: 13px; color: #777; margin-top: 16px; }
        .login-link a { color: #2E75B6; }
    </style>
</head>
<body>
    <div class='card'>
        <h2>🏠 Property Management</h2>
        <p class='subtitle'>Create your Tenant Account</p>

        <?php if (!empty($errors)): ?>
            <div class='errors'>
                <ul>
                    <?php foreach ($errors as $e): ?>
                        <li><?= htmlspecialchars($e) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class='success'>
                ✅ <?= htmlspecialchars($success) ?>
                <br><br>
                <a href='login.php'>Click here to Login</a>
            </div>
        <?php else: ?>

        <form method='POST'>
            <input type='hidden' name='csrf_token' value='<?= $_SESSION['csrf_token'] ?>'>

            <label>Full Name</label>
            <input type='text' name='full_name'
                value='<?= htmlspecialchars($_POST['full_name'] ?? '') ?>'
                placeholder='e.g. John Smith' required>

            <label>Username</label>
            <input type='text' name='username'
                value='<?= htmlspecialchars($_POST['username'] ?? '') ?>'
                placeholder='e.g. johnsmith123' required>

            <label>Email Address</label>
            <input type='email' name='email'
                value='<?= htmlspecialchars($_POST['email'] ?? '') ?>'
                placeholder='your@gmail.com' required>

            <label>Phone Number</label>
            <input type='tel' name='phone'
                value='<?= htmlspecialchars($_POST['phone'] ?? '') ?>'
                placeholder='e.g. +679 123 4567' required>

            <label>Password</label>
            <div style='position:relative'>
                <input type='password' name='password' id='password'
                    placeholder='Min 8 chars, 1 uppercase, 1 number'
                    style='width:100%;padding:10px 40px 10px 10px;margin-bottom:14px;
                    border:1px solid #ccc;border-radius:6px;font-size:15px;box-sizing:border-box;'
                    required>
                <span onclick="togglePassword('password')"
                    style='position:absolute;right:10px;top:10px;cursor:pointer;font-size:18px;'>👁️</span>
            </div>

            <label>Confirm Password</label>
            <div style='position:relative'>
                <input type='password' name='confirm_password' id='confirm_password'
                    placeholder='Repeat your password'
                    style='width:100%;padding:10px 40px 10px 10px;margin-bottom:14px;
                    border:1px solid #ccc;border-radius:6px;font-size:15px;box-sizing:border-box;'
                    required>
                <span onclick="togglePassword('confirm_password')"
                    style='position:absolute;right:10px;top:10px;cursor:pointer;font-size:18px;'>👁️</span>
            </div>

            <button type='submit'>Create Account</button>
        </form>

        <?php endif; ?>
        <div class='login-link'>
            Already have an account? <a href='login.php'>Login here</a>
        </div>
        
    </div>

    <script>
    function togglePassword(fieldId) {
        const field = document.getElementById(fieldId);
        if (field.type === 'password') {
            field.type = 'text';
        } else {
            field.type = 'password';
        }
    }
    </script>
</body>
</html>

<?php
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();

// Security headers (Week 8 Lab 3)
header("Content-Security-Policy: default-src 'self'; script-src 'self' https://www.google.com https://www.gstatic.com 'unsafe-inline'");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");

if (isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true) {
    if ($_SESSION['role'] === 'admin') header('Location: admin/dashboard.php');
    elseif ($_SESSION['role'] === 'property_manager') header('Location: manager/dashboard.php');
    else header('Location: tenant/dashboard.php');
    exit;
}

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/mail.php';

$error = '';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Invalid request. Please try again.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($username === '' || $password === '') {
            $error = 'Please enter both username and password.';
        } else {
            $stmt = $pdo->prepare('SELECT id, username, email, password, role, full_name FROM users WHERE username = ? AND is_active = 1');
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                $pdo->prepare('DELETE FROM otp_tokens WHERE user_id = ?')->execute([$user['id']]);

                $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                $otpHash = hash('sha256', $otp);
                date_default_timezone_set('Pacific/Fiji');
                $expiresAt = date('Y-m-d H:i:s', strtotime('+10 minutes'));

                $stmt = $pdo->prepare('INSERT INTO otp_tokens (user_id, otp_hash, expires_at) VALUES (?, ?, ?)');
                $stmt->execute([$user['id'], $otpHash, $expiresAt]);

                if (sendOtpEmail($user['email'], $user['full_name'], $otp)) {
                    $_SESSION['2fa_user_id'] = $user['id'];
                    $_SESSION['2fa_username'] = $user['username'];
                    $_SESSION['2fa_email'] = $user['email'];
                    $_SESSION['2fa_role'] = $user['role'];
                    $_SESSION['2fa_fullname'] = $user['full_name'];
                    header('Location: verify_otp.php');
                    exit;
                } else {
                    $error = 'Failed to send verification email. Please try again.';
                }
            } else {
                $error = 'Invalid username or password.';
                usleep(500000);
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
    <title>Login — IS351 Property Management</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f0f4f8; display: flex;
            justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
        .card { background: #fff; border-radius: 10px; padding: 40px 36px;
            box-shadow: 0 4px 20px rgba(0,0,0,.12); width: 340px; }
        h2 { color: #1F4E79; margin-bottom: 8px; text-align: center; }
        .subtitle { text-align: center; color: #777; font-size: 13px; margin-bottom: 24px; }
        label { display: block; margin-bottom: 4px; font-size: 14px; color: #555; }
        input[type=text], input[type=password] {
            width: 100%; padding: 10px; margin-bottom: 16px; border: 1px solid #ccc;
            border-radius: 6px; font-size: 15px; box-sizing: border-box; }
        button { width: 100%; padding: 11px; background: #2E75B6; color: #fff;
            border: none; border-radius: 6px; font-size: 16px; cursor: pointer; margin-bottom: 12px; }
        button:hover { background: #1F4E79; }
        .btn-google { width: 100%; padding: 10px; background: #fff; color: #444;
            border: 1px solid #ccc; border-radius: 6px; font-size: 15px; cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            gap: 10px; text-decoration: none; margin-bottom: 16px; box-sizing: border-box; }
        .btn-google:hover { background: #f5f5f5; }
        .divider { text-align: center; color: #aaa; font-size: 13px;
            margin-bottom: 12px; position: relative; }
        .divider::before, .divider::after { content: ''; position: absolute;
            top: 50%; width: 42%; height: 1px; background: #ddd; }
        .divider::before { left: 0; }
        .divider::after { right: 0; }
        .error { background: #fdecea; color: #c0392b; padding: 10px;
            border-radius: 6px; margin-bottom: 14px; font-size: 14px; }
        .hint { text-align: center; font-size: 12px; color: #999; margin-top: 8px; }
        .register-link { text-align: center; font-size: 13px; color: #777; margin-top: 16px; }
        .register-link a { color: #2E75B6; }
    </style>
</head>
<body>
    <div class='card'>
        <h2>🏠 Property Management</h2>
        <p class='subtitle'>IS351 Group 3 — Secure Login</p>

        <?php if ($error): ?>
            <div class='error'><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method='POST'>
            <input type='hidden' name='csrf_token' value='<?= $_SESSION['csrf_token'] ?>'>
            <label>Username</label>
            <input type='text' name='username' autocomplete='username' required>
            <label>Password</label>
            <input type='password' name='password' autocomplete='current-password' required>
            <button type='submit'>Login</button>
        </form>

        <div class='divider'>or</div>

        <a href='google_auth.php' class='btn-google'>
            <img src='https://www.google.com/favicon.ico' width='18'>
            Continue with Google
        </a>

        <p class='hint'>Step 1 of 2 — A verification code will be emailed to you</p>
        <div class='register-link'>
            Don't have an account? <a href='register.php'>Register as Tenant</a>
        </div>
    </div>
</body>
</html>

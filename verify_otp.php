<?php
session_set_cookie_params(['httponly' => true, 'samesite' => 'Strict']);
session_start();

header("Content-Security-Policy: default-src 'self'; script-src 'self' https://www.google.com https://www.gstatic.com 'unsafe-inline'");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");

if (!isset($_SESSION['2fa_user_id'])) {
    header('Location: login.php'); exit;
}

require_once __DIR__ . '/config/db.php';

define('MAX_OTP_ATTEMPTS', 5);

$error = '';
$userId = $_SESSION['2fa_user_id'];
$maskedEmail = preg_replace('/(?<=.).(?=.*@)/u', '*', $_SESSION['2fa_email']);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Invalid request. Please try again.';
    } else {
        $submitted = trim($_POST['otp'] ?? '');

        if (!preg_match('/^\d{6}$/', $submitted)) {
            $error = 'Please enter the 6-digit code.';
        } else {
            $stmt = $pdo->prepare(
                'SELECT id, otp_hash, attempts FROM otp_tokens
                WHERE user_id = ? AND expires_at > UTC_TIMESTAMP()
                ORDER BY created_at DESC LIMIT 1'
            );
            $stmt->execute([$userId]);
            $token = $stmt->fetch();

            if (!$token) {
                $error = 'Your code has expired. <a href="login.php">Request a new one</a>.';
            } elseif ($token['attempts'] >= MAX_OTP_ATTEMPTS) {
                $error = 'Too many failed attempts. <a href="login.php">Start over</a>.';
            } else {
                $submittedHash = hash('sha256', $submitted);

                if (hash_equals($token['otp_hash'], $submittedHash)) {
                    $pdo->prepare('DELETE FROM otp_tokens WHERE id = ?')->execute([$token['id']]);

                    session_regenerate_id(true);
                    $_SESSION['authenticated'] = true;
                    $_SESSION['user_id'] = $userId;
                    $_SESSION['username'] = $_SESSION['2fa_username'];
                    $_SESSION['role'] = $_SESSION['2fa_role'];
                    $_SESSION['full_name'] = $_SESSION['2fa_fullname'];
                    $_SESSION['last_active'] = time();

                    $pdo->prepare('INSERT INTO audit_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)')
                        ->execute([$userId, 'LOGIN', 'User logged in successfully', $_SERVER['REMOTE_ADDR']]);

                    unset($_SESSION['2fa_user_id'], $_SESSION['2fa_username'],
                          $_SESSION['2fa_email'], $_SESSION['2fa_role'],
                          $_SESSION['2fa_fullname']);

                    if ($_SESSION['role'] === 'admin') {
                        header('Location: admin/dashboard.php');
                    } elseif ($_SESSION['role'] === 'property_manager') {
                        header('Location: manager/dashboard.php');
                    } else {
                        header('Location: tenant/dashboard.php');
                    }
                    exit;
                } else {
                    $pdo->prepare('UPDATE otp_tokens SET attempts = attempts + 1 WHERE id = ?')
                        ->execute([$token['id']]);
                    $remaining = MAX_OTP_ATTEMPTS - ($token['attempts'] + 1);
                    $error = 'Incorrect code. ' . max(0, $remaining) . ' attempt(s) remaining.';
                }
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
    <title>Verify Code — IS351 Property Management</title>
    <style>
        body { font-family: Arial, sans-serif; background: #f0f4f8; display: flex;
            justify-content: center; align-items: center; min-height: 100vh; margin: 0; }
        .card { background: #fff; border-radius: 10px; padding: 40px 36px;
            box-shadow: 0 4px 20px rgba(0,0,0,.12); width: 340px; text-align: center; }
        h2 { color: #1F4E79; margin-bottom: 8px; }
        .sub { text-align: center; color: #555; font-size: 14px; margin-bottom: 24px; }
        input[type=text] { width: 100%; padding: 14px; font-size: 28px;
            letter-spacing: 12px; text-align: center; border: 2px solid #2E75B6;
            border-radius: 8px; box-sizing: border-box; margin-bottom: 16px; }
        button { width: 100%; padding: 11px; background: #2E75B6; color: #fff;
            border: none; border-radius: 6px; font-size: 16px; cursor: pointer; }
        button:hover { background: #1F4E79; }
        .error { background: #fdecea; color: #c0392b; padding: 10px;
            border-radius: 6px; margin-bottom: 14px; font-size: 14px; }
        .back { text-align: center; margin-top: 14px; font-size: 13px; }
        .back a { color: #2E75B6; }
    </style>
</head>
<body>
    <div class='card'>
        <h2>Check Your Email</h2>
        <p class='sub'>We sent a 6-digit code to<br>
        <strong><?= htmlspecialchars($maskedEmail) ?></strong></p>

        <?php if ($error): ?>
            <div class='error'><?= $error ?></div>
        <?php endif; ?>

        <form method='POST'>
            <input type='hidden' name='csrf_token' value='<?= $_SESSION['csrf_token'] ?>'>
            <input type='text' name='otp' maxlength='6' pattern='\d{6}'
                inputmode='numeric' autocomplete='one-time-code'
                placeholder='000000' required>
            <button type='submit'>Verify Code</button>
        </form>
        <p class='back'><a href='login.php'>← Back to login</a></p>
    </div>
</body>
</html>
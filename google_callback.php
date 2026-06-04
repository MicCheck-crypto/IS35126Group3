<?php
// FILE: google_callback.php
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/db.php';

// Auto detect redirect URI based on environment
$redirect_uri = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on'
    ? 'https://' . $_SERVER['HTTP_HOST'] . '/google_callback.php'
    : 'http://' . $_SERVER['HTTP_HOST'] . '/google_callback.php';

$client = new Google\Client();
$client->setClientId('332167711514-33fl96ti4tea9qc249kajf09j87jmld4.apps.googleusercontent.com');
$client->setClientSecret('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');
$client->setRedirectUri($redirect_uri);
$client->addScope('email');
$client->addScope('profile');

if (isset($_GET['error'])) {
    header('Location: login.php?error=google_cancelled');
    exit;
}

if (!isset($_GET['code'])) {
    header('Location: login.php');
    exit;
}

try {
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);

    if (isset($token['error'])) {
        error_log('Google token error: ' . $token['error']);
        header('Location: login.php?error=google_failed');
        exit;
    }

    $client->setAccessToken($token);

    $google_service = new Google\Service\Oauth2($client);
    $google_user = $google_service->userinfo->get();

    $google_id = $google_user->id;
    $google_email = $google_user->email;
    $google_name = $google_user->name;

    $stmt = $pdo->prepare('SELECT * FROM users WHERE google_id = ? OR email = ?');
    $stmt->execute([$google_id, $google_email]);
    $user = $stmt->fetch();

    if ($user) {
        if (!$user['google_id']) {
            $pdo->prepare('UPDATE users SET google_id = ? WHERE id = ?')
                ->execute([$google_id, $user['id']]);
        }

        session_regenerate_id(true);
        $_SESSION['authenticated'] = true;
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['last_active'] = time();

        $pdo->prepare('INSERT INTO audit_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)')
            ->execute([$user['id'], 'GOOGLE_LOGIN', 'User logged in via Google', $_SERVER['REMOTE_ADDR']]);

        if ($_SESSION['role'] === 'admin') {
            header('Location: admin/dashboard.php');
        } elseif ($_SESSION['role'] === 'property_manager') {
            header('Location: manager/dashboard.php');
        } else {
            header('Location: tenant/dashboard.php');
        }
        exit;

    } else {
        $username = strtolower(str_replace(' ', '', $google_name)) . rand(100, 999);
        $hashed = password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT);

        $stmt = $pdo->prepare(
            'INSERT INTO users (username, email, password, role, full_name, google_id)
             VALUES (?, ?, ?, "tenant", ?, ?)'
        );
        $stmt->execute([$username, $google_email, $hashed, $google_name, $google_id]);
        $newId = $pdo->lastInsertId();

        session_regenerate_id(true);
        $_SESSION['authenticated'] = true;
        $_SESSION['user_id'] = $newId;
        $_SESSION['username'] = $username;
        $_SESSION['role'] = 'tenant';
        $_SESSION['full_name'] = $google_name;
        $_SESSION['last_active'] = time();

        $pdo->prepare('INSERT INTO audit_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)')
            ->execute([$newId, 'GOOGLE_REGISTER', 'New tenant registered via Google', $_SERVER['REMOTE_ADDR']]);

        header('Location: tenant/dashboard.php');
        exit;
    }

} catch (Exception $e) {
    error_log('Google OAuth error: ' . $e->getMessage());
    header('Location: login.php?error=google_failed');
    exit;
}
?>

<?php
// FILE: google_auth.php
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();

require_once __DIR__ . '/vendor/autoload.php';

$client = new Google\Client();
$client->setClientId('332167711514-33fl96ti4tea9qc249kajf09j87jmld4.apps.googleusercontent.com');
$client->setClientSecret('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');
$client->setRedirectUri('http://localhost/project/google_callback.php');
$client->addScope('email');
$client->addScope('profile');
$client->setAccessType('online');

$auth_url = $client->createAuthUrl();
header('Location: ' . filter_var($auth_url, FILTER_SANITIZE_URL));
exit;
?>
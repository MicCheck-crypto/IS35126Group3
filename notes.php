<?php
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
?>
<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1'>
    <title>Technical Notes — IS351 Property Management</title>
    
</head>
<body>
    <div class='card'>
        <h1>📋 Technical Notes</h1>
        <p class='subtitle'>IS351 Group 3 — Online Property Management System</p>

        <hr>

        <h2>⚠️ Why CAPTCHA is Not Showing on Live Site</h2>
        <div class='note'>
            <strong>Reason:</strong> Google reCAPTCHA v2 requires external scripts from
            <code>google.com</code> and <code>gstatic.com</code>. Railway's free tier
            Content Security Policy environment restricts loading of certain external
            scripts, causing the CAPTCHA widget to be blocked.
        </div>
        <p>
            The CAPTCHA is fully implemented in the code using Google reCAPTCHA v2
            with server-side verification. It works correctly on the local development
            environment (localhost) as demonstrated in the submission screenshots.
        </p>
        <div class='success'>
            <strong>✅ CAPTCHA works on:</strong> localhost (http://localhost/project/login.php)
            <br>
            <strong>❌ CAPTCHA blocked on:</strong> Railway free tier due to CSP restrictions
        </div>

        <hr>

        <h2>⚠️ Why Email OTP (2FA) is Not Active on Live Site</h2>
        <div class='note'>
            <strong>Reason:</strong> Railway's free tier blocks outgoing SMTP connections
            on ports 587 and 465. This is a deliberate security restriction by Railway
            to prevent spam abuse on their platform. PHPMailer requires SMTP access
            to send emails via Gmail.
        </div>
        <p>
            The complete 2FA Email OTP system is implemented in the codebase including:
            random OTP generation using <code>random_int()</code>, SHA-256 hashing before
            database storage, 10-minute expiry, single-use deletion, and 5-attempt lockout.
            It works correctly on localhost as demonstrated in the submission screenshots.
        </p>
        <div class='success'>
            <strong>✅ Email OTP works on:</strong> localhost with Gmail SMTP + App Password
            <br>
            <strong>❌ Email OTP blocked on:</strong> Railway free tier — SMTP ports 587/465 blocked
        </div>

        <hr>

        <h2>✅ What Works on Live Site</h2>
        <div class='success'>
            <strong>All of the following work on the live deployment:</strong>
            <ul style='margin-top: 8px; padding-left: 20px;'>
                <li>Username and password login with bcrypt verification</li>
                <li>Google OAuth login (Continue with Google)</li>
                <li>Role-Based Access Control (Admin, Manager, Tenant)</li>
                <li>Tenant self-registration</li>
                <li>Maintenance request submission</li>
                <li>Property listings and management</li>
                <li>Audit logging</li>
                <li>Session management and timeout</li>
                <li>CSRF protection on all forms</li>
                <li>SQL Injection prevention</li>
                <li>XSS prevention</li>
                <li>HTTPS with SSL certificate</li>
                <li>Security headers</li>
            </ul>
        </div>

        <a href='login.php' class='back'>← Back to Login</a>
    </div>
</body>
</html>

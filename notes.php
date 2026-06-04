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
    <style>
        body { font-family: Arial, sans-serif; background: #f0f4f8;
            min-height: 100vh; margin: 0; padding: 20px; }
        .card { background: #fff; border-radius: 10px; padding: 40px;
            box-shadow: 0 4px 20px rgba(0,0,0,.12); max-width: 700px; margin: 40px auto; }
        h1 { color: #1F4E79; font-size: 24px; margin-bottom: 8px; }
        .subtitle { color: #777; font-size: 14px; margin-bottom: 30px; }
        h2 { color: #2E75B6; font-size: 18px; margin-bottom: 8px; margin-top: 24px; }
        p { color: #555; font-size: 14px; line-height: 1.6; margin-bottom: 12px; }
        .note { background: #fff3e0; border-left: 4px solid #FF9800;
            padding: 12px 16px; border-radius: 4px; margin-bottom: 16px; font-size: 14px; color: #555; }
        .success { background: #e8f5e9; border-left: 4px solid #4CAF50;
            padding: 12px 16px; border-radius: 4px; margin-bottom: 16px; font-size: 14px; color: #555; }
        .back { display: inline-block; margin-top: 24px; padding: 10px 20px;
            background: #2E75B6; color: #fff; border-radius: 6px; text-decoration: none; font-size: 14px; }
        .back:hover { background: #1F4E79; }
        hr { border: none; border-top: 1px solid #e0e0e0; margin: 24px 0; }
    </style>
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

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
        <h1>Below is the reasons</h1>
        <p class='subtitle'>IS351 Group 3 (Online Property Management System)</p>

        <hr>

        <h2>Why CAPTCHA is Not Showing on the Live Website</h2>

        <p>
            When we built this system, we added Google reCAPTCHA, the "I'm not a robot" 
            checkbox, to both the login and registration pages. The purpose of CAPTCHA is to 
            stop automated bots from trying to guess passwords or spam the registration form.
        </p>

        <p>
            On our local computer (localhost), the CAPTCHA works perfectly fine. 
            You can see it ticking and everything works as expected.
        </p>

        <div class='note'>
            <strong>So why doesn't it show on the live website?</strong><br><br>
            When we deployed our website to Railway (our free cloud hosting), we encounter with an error that says "failed to send varifying code". Then we asked AI 
            for help, and we discovered that Railway has very strict security settings that block certain external content 
            from loading. Google's reCAPTCHA needs to load scripts and frames from 
            Google's own servers (google.com and gstatic.com). Railway's environment 
            was blocking these external resources from loading, which is why we see 
            a blank space or blocked content where the CAPTCHA should be.<br><br>
            We tried several fixes including updating the Content Security Policy headers 
            to allow Google's scripts, but Railway's environment kept blocking them. according to AI and it says
            "This is a known limitation of Railway's free tier hosting". Thefore, in our understanding to 
            solve this we have to upgrade from free.
        </div>

        <div class='success'>
            <strong>The CAPTCHA works on Local Host ?</strong><br><br>
            Yes, the CAPTCAH  was working on our local host, we confirmed it works when we started testing locally.
        </div>

        <hr>

        <h2>Why the Email Verification Code (OTP) is Not Working on the Live Website</h2>

        <p>
            We know that to fully we shold bulid our system with a complete Two-Factor Authentication (2FA) system.
            That after entering your username and password, the system was supposed to send 
            a 6-digit one-time code to your email address. You would then need to enter 
            that code to complete the login. This adds an extra layer of security,
            even if someone steals your password, they still cannot log in without 
            access to your email.
        </p>

        <p>
            On our local computer, this works perfectly. When you log in on localhost, 
            you receive an email with the 6-digit code within seconds. 
            The screenshots in our submission show this working correctly.
        </p>

        <div class='note'>
            <strong>So why doesn't it work on the live website?</strong><br><br>
            Well, again we asked AI for helps, and it's mentioned that 
            "Railway's free hosting platform completely blocks all outgoing SMTP connections". 
            This means our server is literally not allowed to send any emails at all. 
            <br><br>
            We tried hint from AI like using Gmail SMTP on port 587, then port 465, but both were blocked, 
            which we didn't have available for this project.<br><br>
            We don't have any ideas why this is happened and how it will be solved.
            so we removed it.
        </div>


        <hr>

        <h2>Everything Else That Works on the Live Website</h2>

        <p>Despite the two limitations above, everything else in our system works 
        correctly on the live website:</p>

        <ul>
            <li><strong>Login with username and password</strong> — works with bcrypt password hashing</li>
            <li><strong>Login with Google account</strong> — Google OAuth works on the live site</li>
            <li><strong>Tenant registration</strong> — new tenants can register themselves</li>
            <li><strong>Admin dashboard</strong> — admin can see and manage all users, properties and requests</li>
            <li><strong>Manager dashboard</strong> — manager can update maintenance request statuses</li>
            <li><strong>Tenant dashboard</strong> — tenants can submit and view their maintenance requests</li>
            <li><strong>Role-Based Access Control</strong> — each role only sees what they are allowed to see</li>
            <li><strong>CSRF protection</strong> — all forms are protected against Cross-Site Request Forgery</li>
            <li><strong>SQL Injection prevention</strong> — all database queries use prepared statements</li>
            <li><strong>XSS prevention</strong> — all output is sanitized before being shown on screen</li>
            <li><strong>HTTPS with SSL certificate</strong> — all data is encrypted in transit</li>
            <li><strong>Session management</strong> — sessions expire after 30 minutes of inactivity</li>
            <li><strong>Audit logging</strong> — all important actions are recorded in the database</li>
            <li><strong>Security headers</strong> — X-Frame-Options, X-Content-Type-Options and more</li>
        </ul>

        <hr>

       

        <a href='login.php' class='back'>← Back to Login</a>
    </div>
</body>
</html>

<?php
// FILE: config/mail.php
// PHPMailer configured for Gmail SMTP (from Week 11 lab)

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/autoload.php';

define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'miikapalmer22@gmail.com'); 
define('SMTP_PASS', 'rbsd puru kjui jnaj'); // PASTE YOUR APP PASSWORD HERE
define('SMTP_FROM', 'miikapalmer22@gmail.com');
define('SMTP_FROM_NAME', 'IS351 Property Management');

function sendOtpEmail(string $toEmail, string $toName, string $otp): bool {
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USER;
        $mail->Password = SMTP_PASS;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;

        $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
        $mail->addAddress($toEmail, $toName);

        $mail->isHTML(true);
        $mail->Subject = 'Your Property Management Login Code';
        $mail->Body = '
        <div style="font-family:Arial,sans-serif;max-width:480px;margin:auto;
        border:1px solid #ddd;border-radius:8px;overflow:hidden">
            <div style="background:#2E7D32;padding:20px;text-align:center">
                <h2 style="color:#fff;margin:0">Property Management System</h2>
            </div>
            <div style="padding:30px">
                <p>Hello <strong>' . htmlspecialchars($toName) . '</strong>,</p>
                <p>Your one-time login code is:</p>
                <div style="font-size:36px;font-weight:bold;letter-spacing:8px;
                text-align:center;color:#1B5E20;padding:20px 0">' . $otp . '</div>
                <p>This code expires in <strong>10 minutes</strong>.
                Do not share it with anyone.</p>
                <p style="color:#999;font-size:12px">
                If you did not attempt to log in, ignore this email.</p>
            </div>
        </div>';
        $mail->AltBody = 'Your login code is: ' . $otp . ' (expires in 10 min)';
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log('PHPMailer error: ' . $mail->ErrorInfo);
        return false;
    }
}
?>
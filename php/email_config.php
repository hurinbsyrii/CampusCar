<?php
// mail_config.php - Konfigurasi Email CampusCar
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/Exception.php';
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php';
require_once __DIR__ . '/../vendor/phpmailer/phpmailer/src/SMTP.php';



function sendResetEmail($email, $fullName, $token) {
    $mail = new PHPMailer(true);
    
    try {
        // Gmail SMTP Configuration
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'campuscar.team@gmail.com'; // Email CampusCar
        $mail->Password = 'zftkbsjfcxwlyrdz'; // App Password (hapus spasi)
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        
        // Optional: Debug mode (comment jika sudah berjalan)
        // $mail->SMTPDebug = 2;
        // $mail->Debugoutput = function($str, $level) {
        //     file_put_contents('smtp_debug.log', "$level: $str\n", FILE_APPEND);
        // };
        
        // Sender
        $mail->setFrom('campuscar.team@gmail.com', 'CampusCar Team');
        // Recipient
        $mail->addAddress($email, $fullName);
        
        // Email content
        $mail->isHTML(true);
        $mail->Subject = 'Password Reset Request - CampusCar';
        
        // Create reset link
        $resetLink = "http://localhost:8080/DIPLOMAPROJECT/php/reset_password.php?token=" . $token;
        
        $mail->Body = "
            <!DOCTYPE html>
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background-color: #4CAF50; color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
                    .content { background-color: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
                    .button { background-color: #4CAF50; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block; }
                    .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; font-size: 12px; color: #666; }
                    .code { background-color: #f5f5f5; padding: 10px; border-radius: 5px; font-family: monospace; word-break: break-all; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>CampusCar</h1>
                        <p>Password Reset Request</p>
                    </div>
                    <div class='content'>
                        <h2>Hello " . htmlspecialchars($fullName) . "!</h2>
                        <p>You have requested to reset your password for your CampusCar account.</p>
                        
                        <p style='text-align: center; margin: 30px 0;'>
                            <a href='$resetLink' class='button'>
                                Reset My Password
                            </a>
                        </p>
                        
                        <p>If the button above doesn't work, copy and paste this link into your browser:</p>
                        <div class='code'>$resetLink</div>
                        
                        <p><strong>⚠️ Important:</strong> This password reset link will expire in <strong>1 hour</strong>.</p>
                        
                        <div class='footer'>
                            <p>If you didn't request this password reset, please ignore this email. Your account is secure.</p>
                            <p>This is an automated message from CampusCar System. Please do not reply to this email.</p>
                            <p>© " . date('Y') . " CampusCar. All rights reserved.</p>
                        </div>
                    </div>
                </div>
            </body>
            </html>
        ";
        
        $mail->AltBody = "Password Reset Request - CampusCar\n\n" .
                         "Hello $fullName,\n\n" .
                         "You requested to reset your password for CampusCar account.\n\n" .
                         "Reset your password by visiting this link:\n" .
                         "$resetLink\n\n" .
                         "This link will expire in 1 hour.\n\n" .
                         "If you didn't request this password reset, please ignore this email.\n\n" .
                         "Best regards,\n" .
                         "CampusCar Team";
        
        return $mail->send();
        
    } catch (Exception $e) {
        // Log error untuk debugging
        error_log("[" . date('Y-m-d H:i:s') . "] Mailer Error: " . $mail->ErrorInfo . "\n", 3, __DIR__ . '/email_errors.log');
        return false;
    }
}
?>
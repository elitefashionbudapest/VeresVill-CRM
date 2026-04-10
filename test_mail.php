<?php
/**
 * SMTP Email teszt — töröld ki ha kész!
 */
require_once __DIR__ . '/phpmailer/src/phpmailer.php';
require_once __DIR__ . '/phpmailer/src/smtp.php';
require_once __DIR__ . '/phpmailer/src/exception.php';
require_once __DIR__ . '/api/config/env.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: text/plain; charset=utf-8');

echo "=== SMTP Email teszt ===\n\n";
echo "SMTP Host: " . env('SMTP_HOST') . "\n";
echo "SMTP Port: " . env('SMTP_PORT') . "\n";
echo "SMTP User: " . env('SMTP_USER') . "\n";
echo "SMTP Pass: " . (env('SMTP_PASS') ? '***SET***' : 'EMPTY!') . "\n";
echo "From: " . env('FROM_EMAIL') . "\n\n";

try {
    $mail = new PHPMailer(true);
    $mail->CharSet = 'UTF-8';
    $mail->Encoding = 'base64';
    $mail->SMTPDebug = 2; // Részletes debug
    $mail->Debugoutput = function($str, $level) { echo "SMTP [{$level}]: {$str}\n"; };

    $mail->isSMTP();
    $mail->Host       = env('SMTP_HOST', 'mail.veresvill.hu');
    $mail->SMTPAuth   = true;
    $mail->Username   = env('SMTP_USER');
    $mail->Password   = env('SMTP_PASS');
    $mail->SMTPSecure = env('SMTP_SECURE', 'ssl');
    $mail->Port       = (int) env('SMTP_PORT', '465');
    $mail->Timeout    = 15;

    $mail->setFrom(env('FROM_EMAIL', 'ajanlatkeres@veresvill.hu'), 'VV CRM Teszt');
    $mail->addAddress('adam.nemeth@elitedivat.hu', 'Teszt');

    $mail->isHTML(true);
    $mail->Subject = 'VV CRM Teszt Email - ' . date('H:i:s');
    $mail->Body    = '<h2>Ez egy teszt email a VeresVill CRM-ből!</h2><p>Ha ezt látod, az SMTP működik.</p>';
    $mail->AltBody = 'Teszt email - SMTP működik.';

    $mail->send();
    echo "\n=== SIKER! Email elküldve! ===\n";
} catch (Exception $e) {
    echo "\n=== HIBA! ===\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "Mailer Error: " . $mail->ErrorInfo . "\n";
}

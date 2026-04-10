<?php
/**
 * Email teszt — töröld ki ha kész!
 */
require_once __DIR__ . '/phpmailer/src/phpmailer.php';
require_once __DIR__ . '/phpmailer/src/smtp.php';
require_once __DIR__ . '/phpmailer/src/exception.php';
require_once __DIR__ . '/api/config/env.php';

use PHPMailer\PHPMailer\PHPMailer;

header('Content-Type: text/plain; charset=utf-8');

echo "=== Email teszt (PHP mail) ===\n\n";

try {
    $mail = new PHPMailer(true);
    $mail->CharSet = 'UTF-8';
    $mail->isMail(); // PHP mail() — nincs SMTP
    $mail->setFrom(env('FROM_EMAIL', 'ajanlatkeres@veresvill.hu'), 'VV CRM Teszt');
    $mail->addAddress('adam.nemeth@elitedivat.hu', 'Teszt');
    $mail->isHTML(true);
    $mail->Subject = 'VV CRM Teszt - ' . date('H:i:s');
    $mail->Body = '<h2>Teszt email a VeresVill CRM-ből!</h2><p>Ha ezt látod, az email küldés működik.</p>';
    $mail->AltBody = 'Teszt email - működik.';
    $mail->send();
    echo "SIKER! Email elküldve!\n";
} catch (Exception $e) {
    echo "HIBA: " . $e->getMessage() . "\n";
}

<?php
/**
 * Email teszt végpont — böngészőből hívható
 * https://visualbyadam.hu/veresvill_crm/api/debug_mail.php?key=vv-deploy-2026&to=cimzett@example.com
 */

define('DEPLOY_KEY', 'vv-deploy-2026');
if (($_GET['key'] ?? '') !== DEPLOY_KEY) {
    http_response_code(403);
    die('Forbidden');
}

header('Content-Type: text/plain; charset=utf-8');

require_once __DIR__ . '/config/env.php';
require_once dirname(__DIR__) . '/phpmailer/src/phpmailer.php';
require_once dirname(__DIR__) . '/phpmailer/src/smtp.php';
require_once dirname(__DIR__) . '/phpmailer/src/exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$to = $_GET['to'] ?? env('ADMIN_EMAIL', 'veresvill.ads@gmail.com');

echo "=== Mail debug ===\n";
echo "MAIL_METHOD: " . env('MAIL_METHOD', '(unset)') . "\n";
echo "FROM_EMAIL:  " . env('FROM_EMAIL', '(unset)') . "\n";
echo "To:          {$to}\n\n";

$mail = new PHPMailer(true);
$mail->SMTPDebug  = 3;
$mail->Debugoutput = function($str, $level) { echo "[debug {$level}] {$str}\n"; };
$mail->CharSet = 'UTF-8';

try {
    $useSmtp = env('MAIL_METHOD', 'phpmail') === 'smtp';
    if ($useSmtp) {
        $mail->isSMTP();
        $mail->Host       = env('SMTP_HOST');
        $mail->SMTPAuth   = true;
        $mail->Username   = env('SMTP_USER');
        $mail->Password   = env('SMTP_PASS');
        $mail->SMTPSecure = env('SMTP_SECURE', 'ssl');
        $mail->Port       = (int) env('SMTP_PORT', '465');
    } else {
        $mail->isMail();
    }

    $fromEmail = env('FROM_EMAIL', 'ajanlatkeres@veresvill.hu');
    $mail->setFrom($fromEmail, env('FROM_NAME', 'Veresvill'));
    $mail->Sender = $fromEmail;
    $mail->addAddress($to);

    $mail->isHTML(true);
    $mail->Subject = 'VeresVill CRM mail teszt';
    $mail->Body    = '<p>Ez egy teszt email a debug végpontból.</p>';
    $mail->AltBody = 'Ez egy teszt email a debug vegpontbol.';

    $ok = $mail->send();
    echo "\n=== RESULT ===\n";
    echo $ok ? "SIKER\n" : "SIKERTELEN\n";
    echo "ErrorInfo: " . $mail->ErrorInfo . "\n";
} catch (Exception $e) {
    echo "\n=== EXCEPTION ===\n";
    echo $e->getMessage() . "\n";
    echo "ErrorInfo: " . $mail->ErrorInfo . "\n";
}

// PHP ini értékek
echo "\n=== PHP mail() config ===\n";
echo "sendmail_path: " . ini_get('sendmail_path') . "\n";
echo "SMTP (win):    " . ini_get('SMTP') . "\n";

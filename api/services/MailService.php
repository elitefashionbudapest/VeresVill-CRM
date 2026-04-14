<?php
/**
 * Email küldő szolgáltatás — PHPMailer wrapper
 */

require_once dirname(__DIR__, 2) . '/phpmailer/src/phpmailer.php';
require_once dirname(__DIR__, 2) . '/phpmailer/src/smtp.php';
require_once dirname(__DIR__, 2) . '/phpmailer/src/exception.php';
require_once __DIR__ . '/../config/env.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class MailService
{
    /**
     * Árajánlat email küldése a megrendelőnek
     *
     * @param array $order   Megrendelés adatai (customer_name, customer_email, address, property_type, size)
     * @param int   $amount  Bruttó összeg forintban
     * @param array $slots   Választható időpontok [{id, start_time, end_time}, ...]
     * @return bool
     */
    public static function sendQuoteEmail(array $order, int $amount, array $slots): bool
    {
        require_once __DIR__ . '/../templates/quote_email.php';

        $token = $order['quote_token'] ?? '';
        $htmlBody = getQuoteEmailHtml($order, $amount, $slots, $token);
        $textBody = getQuoteEmailText($order, $amount, $slots, $token);

        $firstName = explode(' ', $order['customer_name'] ?? '')[0];
        $subject = "Árajánlata elkészült! - Veresvill Villamos Felülvizsgálat";

        return self::sendRaw(
            $order['customer_email'],
            $order['customer_name'] ?? $firstName,
            $subject,
            $htmlBody,
            $textBody,
            env('ADMIN_EMAIL', 'veresvill.ads@gmail.com')
        );
    }

    /**
     * Admin értesítés: megrendelő elfogadta az árajánlatot
     *
     * @param array $order        Megrendelés adatai
     * @param array $selectedSlot Kiválasztott időpont {id, start_time, end_time}
     * @return bool
     */
    public static function sendQuoteAcceptedNotification(array $order, array $selectedSlot): bool
    {
        require_once __DIR__ . '/../templates/quote_accepted.php';

        $htmlBody = getQuoteAcceptedHtml($order, $selectedSlot);
        $textBody = getQuoteAcceptedText($order, $selectedSlot);

        $subject = "Ajanlat elfogadva - {$order['customer_name']} | {$order['address']}";

        return self::sendRaw(
            env('ADMIN_EMAIL', 'veresvill.ads@gmail.com'),
            env('ADMIN_NAME', 'Veresvill'),
            $subject,
            $htmlBody,
            $textBody
        );
    }

    /**
     * Általános email küldés SMTP-vel
     *
     * @param string      $to       Címzett email
     * @param string      $toName   Címzett neve
     * @param string      $subject  Tárgy
     * @param string      $htmlBody HTML tartalom
     * @param string      $textBody Szöveges tartalom
     * @param string|null $replyTo  Reply-To cím (opcionális)
     * @return bool
     */
    public static function sendRaw(
        string $to,
        string $toName,
        string $subject,
        string $htmlBody,
        string $textBody,
        ?string $replyTo = null
    ): bool {
        // Teszt módban: csak az admin felé menő emaileket blokkoljuk
        $adminEmail = env('ADMIN_EMAIL', 'veresvill.ads@gmail.com');
        if (env('APP_DEBUG') === 'true' && $to === $adminEmail) {
            error_log("MailService [TEST MODE - admin email kihagyva]: To={$to}, Subject={$subject}");
            return true;
        }

        // FROM a szerver domainjén (SPF/DKIM rendben — Gmail elfogadja).
        // A reply-to marad az .env-ben beállított ajanlatkeres@veresvill.hu.
        $fromEmail = 'noreply@visualbyadam.hu';
        $fromName  = env('FROM_NAME', 'Veresvill - Villamos Felülvizsgálat');
        $replyToDefault = env('FROM_EMAIL', 'ajanlatkeres@veresvill.hu');
        $useSmtp   = env('MAIL_METHOD', 'phpmail') === 'smtp';

        try {
            $mail = new PHPMailer(true);
            $mail->CharSet = 'UTF-8';
            $mail->Encoding = 'base64';

            if ($useSmtp) {
                // SMTP mód (éles szerveren)
                $mail->isSMTP();
                $mail->Host       = env('SMTP_HOST', 'mail.veresvill.hu');
                $mail->SMTPAuth   = true;
                $mail->Username   = env('SMTP_USER', 'ajanlatkeres@veresvill.hu');
                $mail->Password   = env('SMTP_PASS');
                $mail->SMTPSecure = env('SMTP_SECURE', 'ssl');
                $mail->Port       = (int) env('SMTP_PORT', '465');
            } else {
                // PHP mail() — nincs SMTP konfig, a szerver saját levelezőjét használja
                $mail->isMail();
            }

            $mail->setFrom($fromEmail, $fromName);
            // Envelope sender — phpmail() módban a -f paramétert állítja be,
            // ami nélkül a legtöbb shared host nem fogadja el a küldést
            $mail->Sender = $fromEmail;
            $mail->addAddress($to, $toName);

            // Ha a hívó adott reply-to-t, azt használjuk (pl. admin
            // értesítés esetén az ügyfél címe), egyébként a default.
            $mail->addReplyTo($replyTo ?? $replyToDefault, $fromName);

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $htmlBody;
            $mail->AltBody = $textBody;

            $ok = $mail->send();
            if (!$ok) {
                error_log('MailService send failed: ' . $mail->ErrorInfo);
            }
            return $ok;
        } catch (Exception $e) {
            error_log('MailService exception: ' . $e->getMessage() . ' | ErrorInfo: ' . ($mail->ErrorInfo ?? ''));
            return false;
        }
    }
}

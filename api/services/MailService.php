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
     * Megrendelői visszaigazolás az elfogadott időponttal.
     *
     * @param array $order        customer_name, customer_email, customer_address, quote_amount
     * @param array $selectedSlot slot_date, slot_start, slot_end
     */
    public static function sendCustomerConfirmation(array $order, array $selectedSlot): bool
    {
        require_once __DIR__ . '/../templates/quote_confirmed_customer.php';

        $gcalUrl = self::buildGoogleCalendarUrl($order, $selectedSlot);
        $htmlBody = getQuoteConfirmedCustomerHtml($order, $selectedSlot, $gcalUrl);
        $textBody = getQuoteConfirmedCustomerText($order, $selectedSlot, $gcalUrl);

        $subject = 'Időpont megerősítve — Veresvill Villamos Felülvizsgálat';

        return self::sendRaw(
            $order['customer_email'],
            $order['customer_name'] ?? '',
            $subject,
            $htmlBody,
            $textBody,
            env('ADMIN_EMAIL', 'veresvill.ads@gmail.com')
        );
    }

    /**
     * "Add to Google Calendar" link a megrendelonek.
     * Nem kell OAuth, csak egy pre-filled URL amit a user a sajat
     * Google fiokjaban nyit meg.
     */
    private static function buildGoogleCalendarUrl(array $order, array $selectedSlot): string
    {
        $tz = new DateTimeZone('Europe/Budapest');
        $start = new DateTime($selectedSlot['slot_date'] . ' ' . $selectedSlot['slot_start'], $tz);
        $end   = new DateTime($selectedSlot['slot_date'] . ' ' . $selectedSlot['slot_end'], $tz);
        $start->setTimezone(new DateTimeZone('UTC'));
        $end->setTimezone(new DateTimeZone('UTC'));

        $params = [
            'action'   => 'TEMPLATE',
            'text'     => 'Villamos biztonsági felülvizsgálat',
            'dates'    => $start->format('Ymd\THis\Z') . '/' . $end->format('Ymd\THis\Z'),
            'details'  => "Veresvill helyszíni mérés\nÖsszeg: " . number_format((int)($order['quote_amount'] ?? 0), 0, ',', '.') . ' Ft',
            'location' => $order['customer_address'] ?? '',
        ];
        return 'https://calendar.google.com/calendar/render?' . http_build_query($params);
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
     * Admin értesítés: megrendelő jelezte, hogy egyik kiajánlott időpont sem felel meg neki.
     */
    public static function sendSlotRejectionAdminNotification(array $order, string $customerNote = ''): bool
    {
        $name    = htmlspecialchars($order['customer_name'] ?? '');
        $phone   = htmlspecialchars($order['customer_phone'] ?? '');
        $email   = htmlspecialchars($order['customer_email'] ?? '');
        $address = htmlspecialchars($order['customer_address'] ?? '');
        $noteHtml = $customerNote !== '' ? '<p><strong>Megjegyzés:</strong> ' . nl2br(htmlspecialchars($customerNote)) . '</p>' : '';

        $adminUrl = rtrim(env('APP_URL', 'https://veresvill.hu'), '/') . '/admin/';

        $html = <<<HTML
<!DOCTYPE html>
<html lang="hu"><head><meta charset="UTF-8"></head>
<body style="font-family:Segoe UI,Arial,sans-serif;background:#f0f4f8;padding:30px;">
    <table width="600" cellpadding="0" cellspacing="0" align="center" style="background:#fff;border-radius:12px;box-shadow:0 4px 20px rgba(0,0,0,0.08);">
        <tr><td style="background:#FF9800;padding:25px 30px;border-radius:12px 12px 0 0;">
            <h2 style="color:#fff;margin:0;font-size:22px;">⚠️ Időpontok elutasítva</h2>
        </td></tr>
        <tr><td style="padding:25px 30px;color:#2c3e50;">
            <p>A megrendelő jelezte, hogy <strong>egyik kiajánlott időpont sem felel meg neki</strong>. Új időpontokat kell felajánlani.</p>
            <table cellpadding="8" cellspacing="0" style="width:100%;background:#f8fafb;border-radius:8px;margin:15px 0;">
                <tr><td style="color:#5a6c7d;width:100px;">Név:</td><td><strong>{$name}</strong></td></tr>
                <tr><td style="color:#5a6c7d;">Telefon:</td><td><a href="tel:{$phone}">{$phone}</a></td></tr>
                <tr><td style="color:#5a6c7d;">Email:</td><td><a href="mailto:{$email}">{$email}</a></td></tr>
                <tr><td style="color:#5a6c7d;">Cím:</td><td>{$address}</td></tr>
            </table>
            {$noteHtml}
            <p style="margin-top:20px;"><a href="{$adminUrl}" style="display:inline-block;background:#4A90E2;color:#fff;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:700;">Megnyitás az adminban</a></p>
        </td></tr>
    </table>
</body></html>
HTML;

        $text = "IDŐPONTOK ELUTASÍTVA\n\n"
              . "A megrendelő jelezte, hogy egyik kiajánlott időpont sem felel meg neki.\n\n"
              . "Név: {$order['customer_name']}\n"
              . "Telefon: {$order['customer_phone']}\n"
              . "Email: {$order['customer_email']}\n"
              . "Cím: {$order['customer_address']}\n"
              . ($customerNote !== '' ? "\nMegjegyzés: {$customerNote}\n" : '')
              . "\nKérjük, ajánljon fel új időpontokat az adminban.\n";

        $subject = "⚠️ Időpontok elutasítva - {$order['customer_name']}";

        return self::sendRaw(
            env('ADMIN_EMAIL', 'veresvill.ads@gmail.com'),
            env('ADMIN_NAME', 'Veresvill'),
            $subject,
            $html,
            $text
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
        $fromEmail = 'noreply@veresvill.hu';
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

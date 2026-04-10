<?php
/**
 * Arajanlat email sablon
 *
 * Azonos stilus a send_mail.php sablonjaival:
 * - Header: logo + kek gradient (#4A90E2 -> #357ABD)
 * - Font: Segoe UI
 * - Footer: sotet hatter (#2C3E50)
 */

/**
 * Arajanlat email HTML verzio
 *
 * @param array  $order  Megrendeles adatai
 * @param int    $amount Brutto osszeg forintban
 * @param array  $slots  Valaszthato idopontok [{id, start_time, end_time}, ...]
 * @param string $token  Arajanlat token
 * @return string HTML
 */
function getQuoteEmailHtml(array $order, int $amount, array $slots, string $token): string
{
    $firstName = explode(' ', $order['customer_name'] ?? '')[0];
    $address = htmlspecialchars($order['customer_address'] ?? $order['address'] ?? '');
    $propertyLabel = htmlspecialchars($order['property_type_label'] ?? $order['property_type'] ?? '');
    $size = htmlspecialchars($order['size'] ?? '');

    // Kedvezményes ár: a beírt összeg = -10%-os ár, eredeti = összeg / 0.9
    $originalAmount = (int) ceil($amount / 0.9 / 1000) * 1000;
    $formattedAmount = number_format($amount, 0, ',', '.');
    $formattedOriginal = number_format($originalAmount, 0, ',', '.');
    $savings = number_format($originalAmount - $amount, 0, ',', '.');

    $appUrl = rtrim(env('APP_URL', 'https://veresvill.hu'), '/');

    // Idopont gombok generalasa
    $slotButtons = '';
    foreach ($slots as $slot) {
        $startDt = new DateTime($slot['start_time']);
        $endDt = new DateTime($slot['end_time']);

        // Magyar napnev
        $dayNames = ['vasarnap', 'hetfo', 'kedd', 'szerda', 'csutortok', 'pentek', 'szombat'];
        $dayName = $dayNames[(int)$startDt->format('w')];

        $dateStr = $startDt->format('Y. F j.') . ' (' . $dayName . ')';
        // Honap neveket magyarra csereljuk
        $dateStr = str_replace(
            ['January', 'February', 'March', 'April', 'May', 'June',
             'July', 'August', 'September', 'October', 'November', 'December'],
            ['januar', 'februar', 'marcius', 'aprilis', 'majus', 'junius',
             'julius', 'augusztus', 'szeptember', 'oktober', 'november', 'december'],
            $dateStr
        );
        $timeStr = $startDt->format('H:i') . ' - ' . $endDt->format('H:i');

        $slotUrl = htmlspecialchars("{$appUrl}/public/quote.php?token={$token}&slot={$slot['id']}");

        $slotButtons .= <<<SLOT
            <tr>
                <td style="padding: 6px 0;">
                    <table width="100%" cellpadding="0" cellspacing="0">
                        <tr>
                            <td style="text-align: center;">
                                <a href="{$slotUrl}" style="display: inline-block; background: linear-gradient(135deg, #4A90E2 0%, #357ABD 100%); color: #FFFFFF; padding: 16px 40px; border-radius: 50px; text-decoration: none; font-weight: 700; font-size: 15px; min-width: 320px; text-align: center;">
                                    {$dateStr}<br>
                                    <span style="font-size: 18px; font-weight: 800;">{$timeStr}</span>
                                </a>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
SLOT;
    }

    return <<<HTML
<!DOCTYPE html>
<html lang="hu">
<head><meta charset="UTF-8"></head>
<body style="margin: 0; padding: 0; background-color: #F0F4F8; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background-color: #F0F4F8; padding: 30px 0;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background: #FFFFFF; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 20px rgba(0,0,0,0.08);">

    <!-- Header -->
    <tr>
        <td style="background: linear-gradient(135deg, #4A90E2 0%, #357ABD 100%); padding: 35px 40px; text-align: center;">
            <img src="https://veresvill.hu/veresvill_logo.webp" alt="VeresvVill" style="max-width: 200px; height: auto; margin-bottom: 10px;">
            <p style="color: rgba(255,255,255,0.9); margin: 8px 0 0; font-size: 14px;">Villamos Biztonsagi Felulvizsgalat</p>
        </td>
    </tr>

    <!-- Udvozles -->
    <tr>
        <td style="padding: 35px 40px 20px;">
            <h2 style="color: #2C3E50; font-size: 24px; margin: 0 0 15px;">Tisztelt {$firstName}!</h2>
            <p style="color: #5A6C7D; font-size: 16px; line-height: 1.7; margin: 0;">
                Koszonjuk megrendeleset! Az On arajanlata elkeszult.
            </p>
        </td>
    </tr>

    <!-- Ingatlan osszefoglalo -->
    <tr>
        <td style="padding: 0 40px 25px;">
            <h3 style="color: #4A90E2; font-size: 18px; margin: 0 0 15px; border-bottom: 3px solid #E8F4FD; padding-bottom: 10px;">Ingatlan adatai</h3>
            <table width="100%" cellpadding="0" cellspacing="0" style="border: 1px solid #E8F4FD; border-radius: 10px; overflow: hidden;">
                <tr style="background: #F8FAFB;">
                    <td style="padding: 12px 20px; font-weight: 600; color: #5A6C7D; border-bottom: 1px solid #E8F4FD; width: 160px;">Cim</td>
                    <td style="padding: 12px 20px; color: #2C3E50; border-bottom: 1px solid #E8F4FD;">{$address}</td>
                </tr>
                <tr>
                    <td style="padding: 12px 20px; font-weight: 600; color: #5A6C7D; border-bottom: 1px solid #E8F4FD;">Ingatlan tipus</td>
                    <td style="padding: 12px 20px; color: #2C3E50; border-bottom: 1px solid #E8F4FD;">{$propertyLabel}</td>
                </tr>
                <tr style="background: #F8FAFB;">
                    <td style="padding: 12px 20px; font-weight: 600; color: #5A6C7D;">Meret</td>
                    <td style="padding: 12px 20px; color: #2C3E50;">{$size} m2</td>
                </tr>
            </table>
        </td>
    </tr>

    <!-- Osszeg -->
    <tr>
        <td style="padding: 0 40px 25px;">
            <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td style="background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%); padding: 25px; border-radius: 12px; text-align: center;">
                        <p style="color: rgba(255,255,255,0.7); margin: 0 0 4px; font-size: 14px;"><span style="text-decoration: line-through;">{$formattedOriginal} Ft</span></p>
                        <p style="color: #FFFFFF; margin: 0; font-size: 36px; font-weight: 800;">{$formattedAmount} Ft</p>
                        <p style="color: #FFD54F; margin: 8px 0 0; font-size: 15px; font-weight: 700;">-10% kedvezmeny (megtakaritas: {$savings} Ft)</p>
                        <p style="color: rgba(255,255,255,0.7); margin: 6px 0 0; font-size: 12px;">Brutto ar, tartalmazza az AFA-t</p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>

    <!-- Idopont valasztas -->
    <tr>
        <td style="padding: 0 40px 30px;">
            <h3 style="color: #4A90E2; font-size: 18px; margin: 0 0 5px;">Valasszon idopontot:</h3>
            <p style="color: #5A6C7D; font-size: 14px; margin: 0 0 15px;">Kattintson a kivalasztott idopontra a megerositeshez.</p>
            <table width="100%" cellpadding="0" cellspacing="0">
                {$slotButtons}
            </table>
        </td>
    </tr>

    <!-- Megjegyzes -->
    <tr>
        <td style="padding: 0 40px 30px;">
            <table width="100%" cellpadding="0" cellspacing="0" style="background: #FFF9E6; border: 1px solid #FFE082; border-radius: 10px;">
                <tr>
                    <td style="padding: 15px 20px; color: #5A6C7D; font-size: 14px; line-height: 1.6;">
                        <strong style="color: #F57F17;">Az arajanlat 7 napig ervenyes.</strong><br>
                        Az idopont kivalasztasa utan kollegank felveszi Onnel a kapcsolatot a reszletek egyeztetesehez.
                    </td>
                </tr>
            </table>
        </td>
    </tr>

    <!-- Kapcsolat -->
    <tr>
        <td style="padding: 0 40px 30px; text-align: center;">
            <p style="color: #5A6C7D; font-size: 14px; margin: 0 0 10px;">Kerdese van? Irjon nekunk batran:</p>
            <a href="mailto:veresvill.ads@gmail.com" style="color: #4A90E2; font-weight: 600; text-decoration: none; font-size: 16px;">veresvill.ads@gmail.com</a>
        </td>
    </tr>

    <!-- Footer -->
    <tr>
        <td style="background: #2C3E50; padding: 25px 40px; text-align: center;">
            <img src="https://veresvill.hu/veresvill_logo.webp" alt="VeresvVill" style="max-width: 120px; height: auto; margin-bottom: 8px;">
            <p style="color: rgba(255,255,255,0.6); margin: 0; font-size: 12px;">Villamos Biztonsagi Felulvizsgalat - Budapest es Pest megye</p>
            <p style="color: rgba(255,255,255,0.4); margin: 10px 0 0; font-size: 11px;">Ez egy automatikus ertesites.</p>
        </td>
    </tr>

</table>
</td></tr>
</table>
</body>
</html>
HTML;
}

/**
 * Arajanlat email szoveges (plain text) verzio
 */
function getQuoteEmailText(array $order, int $amount, array $slots, string $token): string
{
    $firstName = explode(' ', $order['customer_name'] ?? '')[0];
    $address = $order['customer_address'] ?? $order['address'] ?? '';
    $propertyLabel = $order['property_type_label'] ?? $order['property_type'] ?? '';
    $size = $order['size'] ?? '';

    $originalAmount = (int) ceil($amount / 0.9 / 1000) * 1000;
    $formattedAmount = number_format($amount, 0, ',', '.');
    $formattedOriginal = number_format($originalAmount, 0, ',', '.');

    $appUrl = rtrim(env('APP_URL', 'https://veresvill.hu'), '/');

    $text = "Tisztelt {$firstName}!\n\n";
    $text .= "Koszonjuk megrendeleset! Az On arajanlata elkeszult.\n\n";
    $text .= "INGATLAN ADATAI:\n";
    $text .= "Cim: {$address}\n";
    $text .= "Tipus: {$propertyLabel}\n";
    $text .= "Meret: {$size} m2\n\n";
    $text .= "EREDETI AR: {$formattedOriginal} Ft\n";
    $text .= "KEDVEZMENYES AR: {$formattedAmount} Ft (-10%)\n";
    $text .= "(Brutto ar, tartalmazza az AFA-t)\n\n";
    $text .= "VALASSZON IDOPONTOT:\n";

    $dayNames = ['vasarnap', 'hetfo', 'kedd', 'szerda', 'csutortok', 'pentek', 'szombat'];
    $monthNames = [
        1 => 'januar', 2 => 'februar', 3 => 'marcius', 4 => 'aprilis',
        5 => 'majus', 6 => 'junius', 7 => 'julius', 8 => 'augusztus',
        9 => 'szeptember', 10 => 'oktober', 11 => 'november', 12 => 'december',
    ];

    foreach ($slots as $slot) {
        $startDt = new DateTime($slot['start_time']);
        $endDt = new DateTime($slot['end_time']);
        $dayName = $dayNames[(int)$startDt->format('w')];
        $monthName = $monthNames[(int)$startDt->format('n')];

        $dateStr = $startDt->format('Y') . '. ' . $monthName . ' ' . $startDt->format('j') . '. (' . $dayName . ')';
        $timeStr = $startDt->format('H:i') . ' - ' . $endDt->format('H:i');

        $slotUrl = "{$appUrl}/public/quote.php?token={$token}&slot={$slot['id']}";
        $text .= "\n  {$dateStr} {$timeStr}\n  Link: {$slotUrl}\n";
    }

    $text .= "\nAz arajanlat 7 napig ervenyes.\n\n";
    $text .= "Kerdese van? Irjon nekunk: veresvill.ads@gmail.com\n\n";
    $text .= "Udvozlettel,\nVeresvill csapata";

    return $text;
}

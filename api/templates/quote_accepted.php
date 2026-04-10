<?php
/**
 * Arajanlat elfogadva - admin ertesito email sablon
 */

/**
 * Admin ertesites HTML: megrendelo elfogadta az arajanlato
 *
 * @param array $order        Megrendeles adatai
 * @param array $selectedSlot Kivalasztott idopont {id, start_time, end_time}
 * @return string HTML
 */
function getQuoteAcceptedHtml(array $order, array $selectedSlot): string
{
    $customerName = htmlspecialchars($order['customer_name'] ?? '');
    $email = htmlspecialchars($order['customer_email'] ?? '');
    $phone = htmlspecialchars($order['customer_phone'] ?? '');
    $address = htmlspecialchars($order['address'] ?? '');
    $propertyLabel = htmlspecialchars($order['property_type_label'] ?? $order['property_type'] ?? '');
    $size = htmlspecialchars($order['size'] ?? '');

    $startDt = new DateTime($selectedSlot['start_time']);
    $endDt = new DateTime($selectedSlot['end_time']);

    $dayNames = ['vasarnap', 'hetfo', 'kedd', 'szerda', 'csutortok', 'pentek', 'szombat'];
    $dayName = $dayNames[(int)$startDt->format('w')];

    $dateStr = $startDt->format('Y. F j.') . ' (' . $dayName . ')';
    $dateStr = str_replace(
        ['January', 'February', 'March', 'April', 'May', 'June',
         'July', 'August', 'September', 'October', 'November', 'December'],
        ['januar', 'februar', 'marcius', 'aprilis', 'majus', 'junius',
         'julius', 'augusztus', 'szeptember', 'oktober', 'november', 'december'],
        $dateStr
    );
    $timeStr = $startDt->format('H:i') . ' - ' . $endDt->format('H:i');

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
        <td style="background: linear-gradient(135deg, #4A90E2 0%, #357ABD 100%); padding: 30px 40px; text-align: center;">
            <img src="https://veresvill.hu/veresvill_logo.webp" alt="VeresvVill" style="max-width: 200px; height: auto; margin-bottom: 10px;">
            <p style="color: rgba(255,255,255,0.9); margin: 8px 0 0; font-size: 14px;">Villamos Biztonsagi Felulvizsgalat</p>
        </td>
    </tr>

    <!-- Sikeres elfogadas banner -->
    <tr>
        <td style="background: #4CAF50; padding: 16px 40px; text-align: center;">
            <h2 style="color: #FFFFFF; margin: 0; font-size: 20px; font-weight: 700;">Arajanlat elfogadva!</h2>
        </td>
    </tr>

    <!-- Kivalasztott idopont -->
    <tr>
        <td style="padding: 25px 40px 15px;">
            <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td style="background: linear-gradient(135deg, #4A90E2 0%, #357ABD 100%); color: #FFFFFF; padding: 20px; border-radius: 12px; text-align: center;">
                        <p style="margin: 0 0 5px; font-size: 14px; color: rgba(255,255,255,0.9);">Kivalasztott idopont:</p>
                        <p style="margin: 0; font-size: 20px; font-weight: 800;">{$dateStr}</p>
                        <p style="margin: 5px 0 0; font-size: 22px; font-weight: 800;">{$timeStr}</p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>

    <!-- Megrendelo adatai -->
    <tr>
        <td style="padding: 15px 40px 30px;">
            <h3 style="color: #4A90E2; font-size: 18px; margin: 0 0 15px; border-bottom: 3px solid #E8F4FD; padding-bottom: 10px;">Megrendelo adatai</h3>
            <table width="100%" cellpadding="0" cellspacing="0" style="border: 1px solid #E8F4FD; border-radius: 10px; overflow: hidden;">
                <tr style="background: #F8FAFB;">
                    <td style="padding: 12px 20px; font-weight: 600; color: #5A6C7D; border-bottom: 1px solid #E8F4FD; width: 160px;">Nev</td>
                    <td style="padding: 12px 20px; color: #2C3E50; border-bottom: 1px solid #E8F4FD; font-weight: 700;">{$customerName}</td>
                </tr>
                <tr>
                    <td style="padding: 12px 20px; font-weight: 600; color: #5A6C7D; border-bottom: 1px solid #E8F4FD;">Email</td>
                    <td style="padding: 12px 20px; color: #2C3E50; border-bottom: 1px solid #E8F4FD;"><a href="mailto:{$email}" style="color: #4A90E2; text-decoration: none;">{$email}</a></td>
                </tr>
                <tr style="background: #F8FAFB;">
                    <td style="padding: 12px 20px; font-weight: 600; color: #5A6C7D; border-bottom: 1px solid #E8F4FD;">Telefon</td>
                    <td style="padding: 12px 20px; color: #2C3E50; border-bottom: 1px solid #E8F4FD;"><a href="tel:{$phone}" style="color: #4A90E2; text-decoration: none; font-weight: 700;">{$phone}</a></td>
                </tr>
                <tr>
                    <td style="padding: 12px 20px; font-weight: 600; color: #5A6C7D; border-bottom: 1px solid #E8F4FD;">Cim</td>
                    <td style="padding: 12px 20px; color: #2C3E50; border-bottom: 1px solid #E8F4FD;">{$address}</td>
                </tr>
                <tr style="background: #F8FAFB;">
                    <td style="padding: 12px 20px; font-weight: 600; color: #5A6C7D; border-bottom: 1px solid #E8F4FD;">Ingatlan</td>
                    <td style="padding: 12px 20px; color: #2C3E50; border-bottom: 1px solid #E8F4FD;">{$propertyLabel}</td>
                </tr>
                <tr>
                    <td style="padding: 12px 20px; font-weight: 600; color: #5A6C7D;">Meret</td>
                    <td style="padding: 12px 20px; color: #2C3E50;">{$size} m2</td>
                </tr>
            </table>
        </td>
    </tr>

    <!-- Gyors muvelet -->
    <tr>
        <td style="padding: 0 40px 30px;">
            <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td style="background: #E8F4FD; padding: 20px; border-radius: 10px; text-align: center;">
                        <p style="color: #2C3E50; margin: 0 0 10px; font-weight: 600;">Hivja vissza a megrendelot!</p>
                        <a href="tel:{$phone}" style="display: inline-block; background: #4A90E2; color: #FFFFFF; padding: 12px 30px; border-radius: 50px; text-decoration: none; font-weight: 700; font-size: 16px;">Hivjon: {$phone}</a>
                    </td>
                </tr>
            </table>
        </td>
    </tr>

    <!-- Footer -->
    <tr>
        <td style="background: #2C3E50; padding: 20px 40px; text-align: center;">
            <img src="https://veresvill.hu/veresvill_logo.webp" alt="VeresvVill" style="max-width: 120px; height: auto; margin-bottom: 8px;">
            <p style="color: rgba(255,255,255,0.7); margin: 0; font-size: 12px;">Villamos Biztonsagi Felulvizsgalat | Automatikus ertesites</p>
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
 * Admin ertesites szoveges (plain text) verzio
 */
function getQuoteAcceptedText(array $order, array $selectedSlot): string
{
    $customerName = $order['customer_name'] ?? '';
    $email = $order['customer_email'] ?? '';
    $phone = $order['customer_phone'] ?? '';
    $address = $order['address'] ?? '';

    $startDt = new DateTime($selectedSlot['start_time']);
    $endDt = new DateTime($selectedSlot['end_time']);

    $dayNames = ['vasarnap', 'hetfo', 'kedd', 'szerda', 'csutortok', 'pentek', 'szombat'];
    $monthNames = [
        1 => 'januar', 2 => 'februar', 3 => 'marcius', 4 => 'aprilis',
        5 => 'majus', 6 => 'junius', 7 => 'julius', 8 => 'augusztus',
        9 => 'szeptember', 10 => 'oktober', 11 => 'november', 12 => 'december',
    ];

    $dayName = $dayNames[(int)$startDt->format('w')];
    $monthName = $monthNames[(int)$startDt->format('n')];
    $dateStr = $startDt->format('Y') . '. ' . $monthName . ' ' . $startDt->format('j') . '. (' . $dayName . ')';
    $timeStr = $startDt->format('H:i') . ' - ' . $endDt->format('H:i');

    $text = "ARAJANLAT ELFOGADVA - VERESVILL\n";
    $text .= "================================\n\n";
    $text .= "Megrendelo: {$customerName}\n";
    $text .= "Email: {$email}\n";
    $text .= "Telefon: {$phone}\n";
    $text .= "Cim: {$address}\n\n";
    $text .= "KIVALASZTOTT IDOPONT:\n";
    $text .= "{$dateStr} {$timeStr}\n\n";
    $text .= "Hivja vissza a megrendelot: {$phone}\n";

    return $text;
}

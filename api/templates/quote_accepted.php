<?php
/**
 * Árajánlat elfogadva — admin értesítő email sablon
 */

/**
 * Árajánlat elfogadva HTML verzió
 */
function getQuoteAcceptedHtml(array $order, array $selectedSlot): string
{
    $customerName = htmlspecialchars($order['customer_name'] ?? '');
    $address = htmlspecialchars($order['customer_address'] ?? $order['address'] ?? '');
    $phone = htmlspecialchars($order['customer_phone'] ?? '');
    $propertyLabel = htmlspecialchars($order['property_type_label'] ?? $order['property_type'] ?? '');
    $size = htmlspecialchars($order['size'] ?? '');
    $amount = number_format((int)($order['quote_amount'] ?? 0), 0, ',', '.');

    $dayNames = ['vasárnap', 'hétfő', 'kedd', 'szerda', 'csütörtök', 'péntek', 'szombat'];
    $monthNames = [
        1 => 'január', 2 => 'február', 3 => 'március', 4 => 'április',
        5 => 'május', 6 => 'június', 7 => 'július', 8 => 'augusztus',
        9 => 'szeptember', 10 => 'október', 11 => 'november', 12 => 'december',
    ];

    $startDt = new DateTime($selectedSlot['start_time'] ?? $selectedSlot['slot_date'] . ' ' . $selectedSlot['slot_start']);
    $endDt = new DateTime($selectedSlot['end_time'] ?? $selectedSlot['slot_date'] . ' ' . $selectedSlot['slot_end']);
    $dayName = $dayNames[(int)$startDt->format('w')];
    $monthName = $monthNames[(int)$startDt->format('n')];
    $dateStr = $startDt->format('Y') . '. ' . $monthName . ' ' . $startDt->format('j') . '. (' . $dayName . ')';
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
            <div style="background: #FFFFFF; display: inline-block; padding: 8px 20px; border-radius: 10px; margin-bottom: 10px;">
                <img src="https://veresvill.hu/veresvill_logo.webp" alt="VeresVill" style="max-width: 180px; height: auto;">
            </div>
            <p style="color: rgba(255,255,255,0.9); margin: 8px 0 0; font-size: 14px;">Villamos Biztonsági Felülvizsgálat</p>
        </td>
    </tr>

    <!-- Elfogadva banner -->
    <tr>
        <td style="background: #4CAF50; padding: 20px 40px; text-align: center;">
            <h2 style="color: #FFFFFF; margin: 0; font-size: 22px; font-weight: 700;">✓ Árajánlat elfogadva!</h2>
        </td>
    </tr>

    <!-- Kiválasztott időpont -->
    <tr>
        <td style="padding: 30px 40px 20px;">
            <h3 style="color: #4A90E2; font-size: 18px; margin: 0 0 15px;">Kiválasztott időpont</h3>
            <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td style="background: #E8F5E9; padding: 20px; border-radius: 12px; text-align: center;">
                        <p style="color: #2C3E50; margin: 0; font-size: 18px; font-weight: 700;">{$dateStr}</p>
                        <p style="color: #4CAF50; margin: 8px 0 0; font-size: 24px; font-weight: 800;">{$timeStr}</p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>

    <!-- Megrendelő adatai -->
    <tr>
        <td style="padding: 0 40px 25px;">
            <h3 style="color: #4A90E2; font-size: 18px; margin: 0 0 15px; border-bottom: 3px solid #E8F4FD; padding-bottom: 10px;">Megrendelő adatai</h3>
            <table width="100%" cellpadding="0" cellspacing="0" style="border: 1px solid #E8F4FD; border-radius: 10px; overflow: hidden;">
                <tr style="background: #F8FAFB;">
                    <td style="padding: 12px 20px; font-weight: 600; color: #5A6C7D; border-bottom: 1px solid #E8F4FD; width: 140px;">Név</td>
                    <td style="padding: 12px 20px; color: #2C3E50; border-bottom: 1px solid #E8F4FD; font-weight: 700;">{$customerName}</td>
                </tr>
                <tr>
                    <td style="padding: 12px 20px; font-weight: 600; color: #5A6C7D; border-bottom: 1px solid #E8F4FD;">Telefon</td>
                    <td style="padding: 12px 20px; color: #2C3E50; border-bottom: 1px solid #E8F4FD;"><a href="tel:{$phone}" style="color: #4A90E2;">{$phone}</a></td>
                </tr>
                <tr style="background: #F8FAFB;">
                    <td style="padding: 12px 20px; font-weight: 600; color: #5A6C7D; border-bottom: 1px solid #E8F4FD;">Cím</td>
                    <td style="padding: 12px 20px; color: #2C3E50; border-bottom: 1px solid #E8F4FD;">{$address}</td>
                </tr>
                <tr>
                    <td style="padding: 12px 20px; font-weight: 600; color: #5A6C7D; border-bottom: 1px solid #E8F4FD;">Ingatlan</td>
                    <td style="padding: 12px 20px; color: #2C3E50; border-bottom: 1px solid #E8F4FD;">{$propertyLabel}, {$size} m²</td>
                </tr>
                <tr style="background: #F8FAFB;">
                    <td style="padding: 12px 20px; font-weight: 600; color: #5A6C7D;">Összeg</td>
                    <td style="padding: 12px 20px; color: #2C3E50; font-weight: 700; font-size: 16px;">{$amount} Ft</td>
                </tr>
            </table>
        </td>
    </tr>

    <!-- Hívja vissza -->
    <tr>
        <td style="padding: 0 40px 30px;">
            <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td style="background: #E8F4FD; padding: 20px; border-radius: 12px; text-align: center;">
                        <p style="color: #2C3E50; margin: 0 0 10px; font-weight: 600;">Hívja vissza a megrendelőt az időpont megerősítéséhez!</p>
                        <a href="tel:{$phone}" style="display: inline-block; background: #4A90E2; color: #FFFFFF; padding: 12px 30px; border-radius: 50px; text-decoration: none; font-weight: 700; font-size: 16px;">📞 Hívjon: {$phone}</a>
                    </td>
                </tr>
            </table>
        </td>
    </tr>

    <!-- Footer -->
    <tr>
        <td style="background: #2C3E50; padding: 20px 40px; text-align: center;">
            <div style="background: #FFFFFF; display: inline-block; padding: 6px 14px; border-radius: 8px; margin-bottom: 8px;">
                <img src="https://veresvill.hu/veresvill_logo.webp" alt="VeresVill" style="max-width: 100px; height: auto;">
            </div>
            <p style="color: rgba(255,255,255,0.6); margin: 0; font-size: 12px;">Villamos Biztonsági Felülvizsgálat - Budapest és Pest megye</p>
            <p style="color: rgba(255,255,255,0.4); margin: 10px 0 0; font-size: 11px;">Ez egy automatikus értesítés.</p>
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
 * Árajánlat elfogadva szöveges verzió
 */
function getQuoteAcceptedText(array $order, array $selectedSlot): string
{
    $customerName = $order['customer_name'] ?? '';
    $address = $order['customer_address'] ?? $order['address'] ?? '';
    $phone = $order['customer_phone'] ?? '';

    $dayNames = ['vasárnap', 'hétfő', 'kedd', 'szerda', 'csütörtök', 'péntek', 'szombat'];
    $monthNames = [
        1 => 'január', 2 => 'február', 3 => 'március', 4 => 'április',
        5 => 'május', 6 => 'június', 7 => 'július', 8 => 'augusztus',
        9 => 'szeptember', 10 => 'október', 11 => 'november', 12 => 'december',
    ];

    $startDt = new DateTime($selectedSlot['start_time'] ?? $selectedSlot['slot_date'] . ' ' . $selectedSlot['slot_start']);
    $endDt = new DateTime($selectedSlot['end_time'] ?? $selectedSlot['slot_date'] . ' ' . $selectedSlot['slot_end']);
    $dayName = $dayNames[(int)$startDt->format('w')];
    $monthName = $monthNames[(int)$startDt->format('n')];
    $dateStr = $startDt->format('Y') . '. ' . $monthName . ' ' . $startDt->format('j') . '. (' . $dayName . ')';
    $timeStr = $startDt->format('H:i') . ' - ' . $endDt->format('H:i');

    $text = "ÁRAJÁNLAT ELFOGADVA!\n";
    $text .= "====================\n\n";
    $text .= "Megrendelő: {$customerName}\n";
    $text .= "Telefon: {$phone}\n";
    $text .= "Cím: {$address}\n\n";
    $text .= "KIVÁLASZTOTT IDŐPONT:\n";
    $text .= "{$dateStr} {$timeStr}\n\n";
    $text .= "Hívja vissza a megrendelőt az időpont megerősítéséhez!\n";
    $text .= "Telefon: {$phone}";

    return $text;
}

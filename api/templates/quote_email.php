<?php
/**
 * Árajánlat email sablon
 */

/**
 * Árajánlat email HTML verzió
 */
function getQuoteEmailHtml(array $order, int $amount, array $slots, string $token): string
{
    $firstName = htmlspecialchars($order['customer_name'] ?? '');
    $address = htmlspecialchars($order['customer_address'] ?? $order['address'] ?? '');
    $propertyLabel = htmlspecialchars($order['property_type_label'] ?? $order['property_type'] ?? '');
    $size = htmlspecialchars($order['size'] ?? '');

    // Az admin a vegleges arat irja be; az "eredeti" = beirt * 1.1 (athuzva)
    $originalAmount = (int) round($amount * 1.1);
    $formattedAmount = number_format($amount, 0, ',', '.');
    $formattedOriginal = number_format($originalAmount, 0, ',', '.');
    $savings = number_format($originalAmount - $amount, 0, ',', '.');

    $energyCertAmount = (int) ($order['energy_certificate_amount'] ?? 0);
    $hasEnergyCert = $energyCertAmount > 0;
    $formattedEnergyCert = $hasEnergyCert ? number_format($energyCertAmount, 0, ',', '.') : '';
    $totalAmount = $hasEnergyCert ? number_format($amount + $energyCertAmount, 0, ',', '.') : '';

    $electricLabel = $hasEnergyCert
        ? '<p style="color: #5A6C7D; font-size: 14px; font-weight: 600; margin: 0 0 8px;">Villamos biztonsági felülvizsgálat:</p>'
        : '';

    $energyCertBlock = '';
    if ($hasEnergyCert) {
        $energyCertBlock = <<<ECERT
    <tr>
        <td style="padding: 0 40px 25px;">
            <p style="color: #5A6C7D; font-size: 14px; font-weight: 600; margin: 0 0 8px;">Energetikai tanúsítvány:</p>
            <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td style="background: linear-gradient(135deg, #FF9800 0%, #F57C00 100%); padding: 20px; border-radius: 12px; text-align: center;">
                        <p style="color: #FFFFFF; margin: 0; font-size: 30px; font-weight: 800;">{$formattedEnergyCert} Ft</p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
    <tr>
        <td style="padding: 0 40px 25px;">
            <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td style="background: #2C3E50; padding: 18px; border-radius: 12px; text-align: center;">
                        <p style="color: rgba(255,255,255,0.7); margin: 0 0 4px; font-size: 14px;">Összesen:</p>
                        <p style="color: #FFFFFF; margin: 0; font-size: 32px; font-weight: 800;">{$totalAmount} Ft</p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>
ECERT;
    }

    $appUrl = rtrim(env('APP_URL', 'https://veresvill.hu'), '/');

    $dayNames = ['vasárnap', 'hétfő', 'kedd', 'szerda', 'csütörtök', 'péntek', 'szombat'];
    $monthNames = [
        1 => 'január', 2 => 'február', 3 => 'március', 4 => 'április',
        5 => 'május', 6 => 'június', 7 => 'július', 8 => 'augusztus',
        9 => 'szeptember', 10 => 'október', 11 => 'november', 12 => 'december',
    ];

    // Időpont gombok generálása
    $slotButtons = '';
    foreach ($slots as $slot) {
        $startDt = new DateTime($slot['start_time']);
        $endDt = new DateTime($slot['end_time']);

        $dayName = $dayNames[(int)$startDt->format('w')];
        $monthName = $monthNames[(int)$startDt->format('n')];
        $dateStr = $startDt->format('Y') . '. ' . $monthName . ' ' . $startDt->format('j') . '. (' . $dayName . ')';
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
            <table cellpadding="0" cellspacing="0" border="0" align="center" style="margin:0 auto 10px;">
                <tr><td style="background:#FFFFFF;padding:10px 22px;border-radius:10px;">
                    <img src="https://veresvill.hu/veresvill_logo.webp" alt="VeresVill" width="180" style="display:block;max-width:180px;height:auto;">
                </td></tr>
            </table>
            <p style="color: rgba(255,255,255,0.9); margin: 8px 0 0; font-size: 14px;">Villamos Biztonsági Felülvizsgálat</p>
        </td>
    </tr>

    <!-- Üdvözlés -->
    <tr>
        <td style="padding: 35px 40px 20px;">
            <h2 style="color: #2C3E50; font-size: 24px; margin: 0 0 15px;">Tisztelt {$firstName}!</h2>
            <p style="color: #5A6C7D; font-size: 16px; line-height: 1.7; margin: 0;">
                Köszönjük megrendelését! Az Ön árajánlata elkészült.
            </p>
        </td>
    </tr>

    <!-- Ingatlan összefoglaló -->
    <tr>
        <td style="padding: 0 40px 25px;">
            <h3 style="color: #4A90E2; font-size: 18px; margin: 0 0 15px; border-bottom: 3px solid #E8F4FD; padding-bottom: 10px;">Ingatlan adatai</h3>
            <table width="100%" cellpadding="0" cellspacing="0" style="border: 1px solid #E8F4FD; border-radius: 10px; overflow: hidden;">
                <tr style="background: #F8FAFB;">
                    <td style="padding: 12px 20px; font-weight: 600; color: #5A6C7D; border-bottom: 1px solid #E8F4FD; width: 160px;">Cím</td>
                    <td style="padding: 12px 20px; color: #2C3E50; border-bottom: 1px solid #E8F4FD;">{$address}</td>
                </tr>
                <tr>
                    <td style="padding: 12px 20px; font-weight: 600; color: #5A6C7D; border-bottom: 1px solid #E8F4FD;">Ingatlan típus</td>
                    <td style="padding: 12px 20px; color: #2C3E50; border-bottom: 1px solid #E8F4FD;">{$propertyLabel}</td>
                </tr>
                <tr style="background: #F8FAFB;">
                    <td style="padding: 12px 20px; font-weight: 600; color: #5A6C7D;">Méret</td>
                    <td style="padding: 12px 20px; color: #2C3E50;">{$size} m²</td>
                </tr>
            </table>
        </td>
    </tr>

    <!-- Összeg: villamos felülvizsgálat -->
    <tr>
        <td style="padding: 0 40px 25px;">
            {$electricLabel}
            <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td style="background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%); padding: 25px; border-radius: 12px; text-align: center;">
                        <p style="color: rgba(255,255,255,0.7); margin: 0 0 4px; font-size: 14px;"><span style="text-decoration: line-through;">{$formattedOriginal} Ft</span></p>
                        <p style="color: #FFFFFF; margin: 0; font-size: 36px; font-weight: 800;">{$formattedAmount} Ft</p>
                        <p style="color: #FFD54F; margin: 8px 0 0; font-size: 15px; font-weight: 700;">-10% kedvezmény (megtakarítás: {$savings} Ft)</p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>

    {$energyCertBlock}

    <!-- Időpont választás -->
    <tr>
        <td style="padding: 0 40px 15px;">
            <h3 style="color: #4A90E2; font-size: 18px; margin: 0 0 5px;">Válasszon időpontot:</h3>
            <p style="color: #5A6C7D; font-size: 14px; margin: 0 0 15px;">Kattintson a kiválasztott időpontra a megerősítéshez.</p>
            <table width="100%" cellpadding="0" cellspacing="0">
                {$slotButtons}
            </table>
        </td>
    </tr>

    <!-- Egyik sem felel meg -->
    <tr>
        <td style="padding: 0 40px 30px; text-align: center;">
            <a href="{$appUrl}/public/quote.php?token={$token}&reject=1" style="display: inline-block; color: #E65100; padding: 10px 20px; border: 2px solid #FFB74D; border-radius: 50px; text-decoration: none; font-weight: 600; font-size: 14px; background: #FFF8E1;">
                Egyik időpont sem felel meg &rarr;
            </a>
            <p style="color: #9e9e9e; font-size: 12px; margin: 8px 0 0;">Jelezze felénk és új időpontokkal keressük.</p>
        </td>
    </tr>

    <!-- Megjegyzés -->
    <tr>
        <td style="padding: 0 40px 30px;">
            <table width="100%" cellpadding="0" cellspacing="0" style="background: #FFF9E6; border: 1px solid #FFE082; border-radius: 10px;">
                <tr>
                    <td style="padding: 15px 20px; color: #5A6C7D; font-size: 14px; line-height: 1.6;">
                        <strong style="color: #F57F17;">Az árajánlat 7 napig érvényes.</strong><br>
                        Az időpont kiválasztása után kollégánk felveszi Önnel a kapcsolatot a részletek egyeztetéséhez.
                    </td>
                </tr>
            </table>
        </td>
    </tr>

    <!-- Kapcsolat -->
    <tr>
        <td style="padding: 0 40px 30px; text-align: center;">
            <p style="color: #5A6C7D; font-size: 14px; margin: 0 0 10px;">Kérdése van? Írjon nekünk bátran:</p>
            <a href="mailto:veresvill.ads@gmail.com" style="color: #4A90E2; font-weight: 600; text-decoration: none; font-size: 16px;">veresvill.ads@gmail.com</a>
        </td>
    </tr>

    <!-- Footer -->
    <tr>
        <td style="background: #2C3E50; padding: 25px 40px; text-align: center;">
            <table cellpadding="0" cellspacing="0" border="0" align="center" style="margin:0 auto 8px;">
                <tr><td style="background:#FFFFFF;padding:8px 16px;border-radius:8px;">
                    <img src="https://veresvill.hu/veresvill_logo.webp" alt="VeresVill" width="100" style="display:block;max-width:100px;height:auto;">
                </td></tr>
            </table>
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
 * Árajánlat email szöveges verzió
 */
function getQuoteEmailText(array $order, int $amount, array $slots, string $token): string
{
    $firstName = $order['customer_name'] ?? '';
    $address = $order['customer_address'] ?? $order['address'] ?? '';
    $propertyLabel = $order['property_type_label'] ?? $order['property_type'] ?? '';
    $size = $order['size'] ?? '';

    $originalAmount = (int) ceil($amount / 0.9 / 1000) * 1000;
    $formattedAmount = number_format($amount, 0, ',', '.');
    $formattedOriginal = number_format($originalAmount, 0, ',', '.');

    $appUrl = rtrim(env('APP_URL', 'https://veresvill.hu'), '/');

    $dayNames = ['vasárnap', 'hétfő', 'kedd', 'szerda', 'csütörtök', 'péntek', 'szombat'];
    $monthNames = [
        1 => 'január', 2 => 'február', 3 => 'március', 4 => 'április',
        5 => 'május', 6 => 'június', 7 => 'július', 8 => 'augusztus',
        9 => 'szeptember', 10 => 'október', 11 => 'november', 12 => 'december',
    ];

    $text = "Tisztelt {$firstName}!\n\n";
    $text .= "Köszönjük megrendelését! Az Ön árajánlata elkészült.\n\n";
    $text .= "INGATLAN ADATAI:\n";
    $text .= "Cím: {$address}\n";
    $text .= "Típus: {$propertyLabel}\n";
    $text .= "Méret: {$size} m²\n\n";
    $energyCertAmount = (int) ($order['energy_certificate_amount'] ?? 0);

    $text .= "VILLAMOS FELÜLVIZSGÁLAT:\n";
    $text .= "EREDETI ÁR: {$formattedOriginal} Ft\n";
    $text .= "KEDVEZMÉNYES ÁR: {$formattedAmount} Ft (-10%)\n";
    if ($energyCertAmount > 0) {
        $text .= "\nENERGETIKAI TANÚSÍTVÁNY: " . number_format($energyCertAmount, 0, ',', '.') . " Ft\n";
        $text .= "\nÖSSZESEN: " . number_format($amount + $energyCertAmount, 0, ',', '.') . " Ft\n";
    }
    $text .= "\nVÁLASSZON IDŐPONTOT:\n";

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

    $text .= "\nHa egyik időpont sem felel meg, jelezze itt:\n";
    $text .= "{$appUrl}/public/quote.php?token={$token}&reject=1\n";
    $text .= "\nAz árajánlat 7 napig érvényes.\n\n";
    $text .= "Kérdése van? Írjon nekünk: veresvill.ads@gmail.com\n\n";
    $text .= "Üdvözlettel,\nVeresVill csapata";

    return $text;
}

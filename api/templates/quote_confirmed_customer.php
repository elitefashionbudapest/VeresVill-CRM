<?php
/**
 * Ügyfél visszaigazoló email — az elfogadott időponttal
 * A megrendelő kapja, miután kiválasztott egy slot-ot.
 */

function getQuoteConfirmedCustomerHtml(array $order, array $selectedSlot, string $gcalUrl): string
{
    $customerName = htmlspecialchars($order['customer_name'] ?? '');
    $firstName = htmlspecialchars(explode(' ', $order['customer_name'] ?? '')[0] ?? '');
    $address = htmlspecialchars($order['customer_address'] ?? '');
    $amount = number_format((int)($order['quote_amount'] ?? 0), 0, ',', '.');

    $dayNames = ['vasárnap', 'hétfő', 'kedd', 'szerda', 'csütörtök', 'péntek', 'szombat'];
    $monthNames = [
        1 => 'január', 2 => 'február', 3 => 'március', 4 => 'április',
        5 => 'május', 6 => 'június', 7 => 'július', 8 => 'augusztus',
        9 => 'szeptember', 10 => 'október', 11 => 'november', 12 => 'december',
    ];

    $startDt = new DateTime($selectedSlot['slot_date'] . ' ' . $selectedSlot['slot_start']);
    $endDt = new DateTime($selectedSlot['slot_date'] . ' ' . $selectedSlot['slot_end']);
    $dayName = $dayNames[(int)$startDt->format('w')];
    $monthName = $monthNames[(int)$startDt->format('n')];
    $dateStr = $startDt->format('Y') . '. ' . $monthName . ' ' . $startDt->format('j') . '. (' . $dayName . ')';
    $timeStr = $startDt->format('H:i') . ' - ' . $endDt->format('H:i');

    return <<<HTML
<!DOCTYPE html>
<html lang="hu">
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#F0F4F8;font-family:'Segoe UI',Tahoma,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#F0F4F8;padding:30px 0;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background:#FFFFFF;border-radius:16px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.08);">

    <!-- Fejléc -->
    <tr>
        <td style="background:linear-gradient(135deg,#4CAF50 0%,#45a049 100%);padding:36px 40px;text-align:center;">
            <h1 style="color:#FFFFFF;margin:0 0 6px;font-size:26px;">✓ Időpont megerősítve!</h1>
            <p style="color:rgba(255,255,255,.9);margin:0;font-size:15px;">Köszönjük, hogy minket választott</p>
        </td>
    </tr>

    <!-- Üdvözlés -->
    <tr>
        <td style="padding:32px 40px 10px;">
            <h2 style="color:#2C3E50;font-size:22px;margin:0 0 12px;">Kedves {$firstName}!</h2>
            <p style="color:#5A6C7D;font-size:16px;line-height:1.7;margin:0;">
                Megerősítettük az Ön által választott időpontot. Kollégánk a megadott napon és időpontban érkezik a helyszínre.
            </p>
        </td>
    </tr>

    <!-- Időpont kártya -->
    <tr>
        <td style="padding:20px 40px 10px;">
            <table width="100%" cellpadding="0" cellspacing="0" style="background:#E8F4FD;border:2px solid #4A90E2;border-radius:14px;">
                <tr>
                    <td style="padding:24px 28px;">
                        <p style="color:#4A90E2;margin:0 0 8px;font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:1px;">Megerősített időpont</p>
                        <p style="color:#2C3E50;margin:0 0 4px;font-size:22px;font-weight:700;">{$dateStr}</p>
                        <p style="color:#2C3E50;margin:0;font-size:18px;font-weight:600;">{$timeStr}</p>
                    </td>
                </tr>
                <tr>
                    <td style="padding:0 28px 24px;">
                        <a href="{$gcalUrl}" target="_blank" style="display:inline-block;background:#4A90E2;color:#FFFFFF;padding:12px 24px;border-radius:8px;text-decoration:none;font-weight:700;font-size:14px;">
                            📅 Hozzáadás Google Naptárhoz
                        </a>
                    </td>
                </tr>
            </table>
        </td>
    </tr>

    <!-- Megrendelés összesítő -->
    <tr>
        <td style="padding:20px 40px 10px;">
            <h3 style="color:#4A90E2;font-size:17px;margin:0 0 12px;border-bottom:2px solid #E8F4FD;padding-bottom:8px;">Megrendelés adatai</h3>
            <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #E8F4FD;border-radius:10px;overflow:hidden;">
                <tr style="background:#F8FAFB;">
                    <td style="padding:12px 18px;font-weight:600;color:#5A6C7D;width:140px;">Név</td>
                    <td style="padding:12px 18px;color:#2C3E50;">{$customerName}</td>
                </tr>
                <tr>
                    <td style="padding:12px 18px;font-weight:600;color:#5A6C7D;">Helyszín</td>
                    <td style="padding:12px 18px;color:#2C3E50;">{$address}</td>
                </tr>
                <tr style="background:#F8FAFB;">
                    <td style="padding:12px 18px;font-weight:600;color:#5A6C7D;">Összeg</td>
                    <td style="padding:12px 18px;color:#2C3E50;font-weight:700;">{$amount} Ft</td>
                </tr>
            </table>
        </td>
    </tr>

    <!-- Fontos tudnivalók -->
    <tr>
        <td style="padding:20px 40px 10px;">
            <table width="100%" cellpadding="0" cellspacing="0" style="background:#FFF8E1;border-radius:10px;">
                <tr>
                    <td style="padding:18px 22px;">
                        <p style="margin:0 0 8px;color:#E65100;font-weight:700;font-size:14px;">Mi lesz a helyszíni felmérés menete?</p>
                        <p style="margin:0;color:#5A6C7D;font-size:13px;line-height:1.6;">
                            Kollégánk a megbeszélt időpontban érkezik, a helyszíni mérés általában 30–45 percet vesz igénybe. A mérést követő munkanapon emailben megkapja a jegyzőkönyvet.
                        </p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>

    <!-- Kapcsolat -->
    <tr>
        <td style="padding:20px 40px 30px;text-align:center;">
            <p style="color:#5A6C7D;font-size:13px;margin:0 0 8px;">Kérdése van? Írjon nekünk:</p>
            <a href="mailto:veresvill.ads@gmail.com" style="color:#4A90E2;font-weight:600;text-decoration:none;">veresvill.ads@gmail.com</a>
        </td>
    </tr>

    <!-- Footer -->
    <tr>
        <td style="background:#2C3E50;padding:22px 40px;text-align:center;">
            <img src="https://veresvill.hu/veresvill_logo.webp" alt="Veresvill" style="max-width:110px;height:auto;margin-bottom:6px;">
            <p style="color:rgba(255,255,255,.6);margin:0;font-size:12px;">Villamos Biztonsági Felülvizsgálat</p>
        </td>
    </tr>

</table>
</td></tr>
</table>
</body>
</html>
HTML;
}

function getQuoteConfirmedCustomerText(array $order, array $selectedSlot, string $gcalUrl): string
{
    $firstName = explode(' ', $order['customer_name'] ?? '')[0] ?? '';
    $address = $order['customer_address'] ?? '';
    $amount = number_format((int)($order['quote_amount'] ?? 0), 0, ',', '.');

    $startDt = new DateTime($selectedSlot['slot_date'] . ' ' . $selectedSlot['slot_start']);
    $endDt = new DateTime($selectedSlot['slot_date'] . ' ' . $selectedSlot['slot_end']);
    $dateStr = $startDt->format('Y.m.d.');
    $timeStr = $startDt->format('H:i') . '-' . $endDt->format('H:i');

    $t = "Kedves {$firstName}!\n\n";
    $t .= "Megerositettuk az On altal valasztott idopontot.\n\n";
    $t .= "IDOPONT: {$dateStr} {$timeStr}\n";
    $t .= "HELYSZIN: {$address}\n";
    $t .= "OSSZEG: {$amount} Ft\n\n";
    $t .= "Hozzaadas Google Naptarhoz:\n{$gcalUrl}\n\n";
    $t .= "A helyszini meres 30-45 perc, a jegyzokonyvet a koveto munkanapon kuldjuk emailben.\n\n";
    $t .= "Udvozlettel,\nVeresvill csapata\nveresvill.ads@gmail.com";
    return $t;
}

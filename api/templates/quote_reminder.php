<?php
/**
 * Emlékeztető email sablon — 30 perccel az árajánlat után
 */

function getQuoteReminderHtml(array $order, array $slots, string $token): string
{
    $firstName  = htmlspecialchars($order['customer_name'] ?? '');
    $address    = htmlspecialchars($order['customer_address'] ?? '');
    $appUrl     = rtrim(env('APP_URL', 'https://veresvill.hu'), '/');
    $amount     = (int) ($order['quote_amount'] ?? 0);
    $formattedAmount = number_format($amount, 0, ',', '.');

    $dayNames   = ['vasárnap','hétfő','kedd','szerda','csütörtök','péntek','szombat'];
    $monthNames = [1=>'január',2=>'február',3=>'március',4=>'április',5=>'május',6=>'június',
                   7=>'július',8=>'augusztus',9=>'szeptember',10=>'október',11=>'november',12=>'december'];

    $slotButtons = '';
    foreach ($slots as $slot) {
        $startDt  = new DateTime($slot['start_time']);
        $endDt    = new DateTime($slot['end_time']);
        $dayName  = $dayNames[(int)$startDt->format('w')];
        $monthName = $monthNames[(int)$startDt->format('n')];
        $dateStr  = $startDt->format('Y') . '. ' . $monthName . ' ' . $startDt->format('j') . '. (' . $dayName . ')';
        $timeStr  = $startDt->format('H:i') . ' - ' . $endDt->format('H:i');
        $slotUrl  = htmlspecialchars("{$appUrl}/public/quote.php?token={$token}&slot={$slot['id']}");

        $slotButtons .= <<<SLOT
            <tr>
                <td style="padding: 6px 0;">
                    <table width="100%" cellpadding="0" cellspacing="0"><tr><td style="text-align:center;">
                        <a href="{$slotUrl}" style="display:inline-block;background:linear-gradient(135deg,#4A90E2 0%,#357ABD 100%);color:#FFFFFF;padding:16px 40px;border-radius:50px;text-decoration:none;font-weight:700;font-size:15px;min-width:320px;text-align:center;">
                            {$dateStr}<br><span style="font-size:18px;font-weight:800;">{$timeStr}</span>
                        </a>
                    </td></tr></table>
                </td>
            </tr>
SLOT;
    }

    return <<<HTML
<!DOCTYPE html>
<html lang="hu">
<head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#F0F4F8;font-family:'Segoe UI',Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#F0F4F8;padding:30px 0;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background:#FFFFFF;border-radius:16px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,0.08);">

    <!-- Header -->
    <tr>
        <td style="background:linear-gradient(135deg,#E53935 0%,#B71C1C 100%);padding:35px 40px;text-align:center;">
            <table cellpadding="0" cellspacing="0" border="0" align="center" style="margin:0 auto 10px;">
                <tr><td style="background:#FFFFFF;padding:10px 22px;border-radius:10px;">
                    <img src="https://veresvill.hu/veresvill_logo.webp" alt="VeresVill" width="180" style="display:block;max-width:180px;height:auto;">
                </td></tr>
            </table>
            <p style="color:rgba(255,255,255,0.9);margin:8px 0 0;font-size:14px;">Villamos Biztonsági Felülvizsgálat</p>
        </td>
    </tr>

    <!-- Üdvözlés -->
    <tr>
        <td style="padding:35px 40px 20px;">
            <h2 style="color:#2C3E50;font-size:24px;margin:0 0 15px;">Tisztelt {$firstName}!</h2>
            <p style="color:#5A6C7D;font-size:16px;line-height:1.7;margin:0;">
                Korábban elküldtük árajánlatát, azonban még nem érkezett visszajelzés.<br>
                Kérjük, <strong>foglaljon időpontot még ma</strong> — az árajánlat érvényes és az Ön neve alatt van tartva.
            </p>
        </td>
    </tr>

    <!-- Összeg emlékeztető -->
    <tr>
        <td style="padding:0 40px 25px;">
            <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td style="background:linear-gradient(135deg,#4CAF50 0%,#45a049 100%);padding:20px;border-radius:12px;text-align:center;">
                        <p style="color:rgba(255,255,255,0.8);margin:0 0 4px;font-size:14px;">Az Ön árajánlata:</p>
                        <p style="color:#FFFFFF;margin:0;font-size:34px;font-weight:800;">{$formattedAmount} Ft</p>
                        <p style="color:#FFD54F;margin:6px 0 0;font-size:14px;font-weight:700;">-10% kedvezménnyel</p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>

    <!-- Időpont gombok -->
    <tr>
        <td style="padding:0 40px 15px;">
            <h3 style="color:#4A90E2;font-size:18px;margin:0 0 5px;">Válasszon időpontot:</h3>
            <p style="color:#5A6C7D;font-size:14px;margin:0 0 15px;">Kattintson a kívánt időpontra a megerősítéshez.</p>
            <table width="100%" cellpadding="0" cellspacing="0">
                {$slotButtons}
            </table>
        </td>
    </tr>

    <!-- Egyik sem felel meg -->
    <tr>
        <td style="padding:0 40px 20px;text-align:center;">
            <a href="{$appUrl}/public/quote.php?token={$token}&reject=1"
               style="display:inline-block;color:#E65100;padding:10px 20px;border:2px solid #FFB74D;border-radius:50px;text-decoration:none;font-weight:600;font-size:14px;background:#FFF8E1;">
                Egyik időpont sem felel meg &rarr;
            </a>
        </td>
    </tr>

    <!-- Figyelmeztetés: napi törlés -->
    <tr>
        <td style="padding:0 40px 30px;">
            <table width="100%" cellpadding="0" cellspacing="0" style="background:#FFF3E0;border:2px solid #FF9800;border-radius:10px;">
                <tr>
                    <td style="padding:15px 20px;color:#5A6C7D;font-size:14px;line-height:1.6;">
                        <strong style="color:#E65100;">⚠️ Fontos tudnivaló:</strong><br>
                        A nem visszaigazolt megrendeléseket <strong>minden este töröljük</strong> rendszerünkből.
                        Ha ma nem erősíti meg az időpontot, megrendelése törlődik és újra kell igényelnie az árajánlatot.
                    </td>
                </tr>
            </table>
        </td>
    </tr>

    <!-- Kapcsolat -->
    <tr>
        <td style="padding:0 40px 30px;text-align:center;">
            <p style="color:#5A6C7D;font-size:14px;margin:0 0 10px;">Kérdése van? Írjon nekünk:</p>
            <a href="mailto:veresvill.ads@gmail.com" style="color:#4A90E2;font-weight:600;text-decoration:none;font-size:16px;">veresvill.ads@gmail.com</a>
        </td>
    </tr>

    <!-- Footer -->
    <tr>
        <td style="background:#2C3E50;padding:25px 40px;text-align:center;">
            <table cellpadding="0" cellspacing="0" border="0" align="center" style="margin:0 auto 8px;">
                <tr><td style="background:#FFFFFF;padding:8px 16px;border-radius:8px;">
                    <img src="https://veresvill.hu/veresvill_logo.webp" alt="VeresVill" width="100" style="display:block;max-width:100px;height:auto;">
                </td></tr>
            </table>
            <p style="color:rgba(255,255,255,0.6);margin:0;font-size:12px;">Villamos Biztonsági Felülvizsgálat — Budapest és Pest megye</p>
            <p style="color:rgba(255,255,255,0.4);margin:10px 0 0;font-size:11px;">Ez egy automatikus emlékeztető.</p>
        </td>
    </tr>

</table>
</td></tr>
</table>
</body>
</html>
HTML;
}

function getQuoteReminderText(array $order, array $slots, string $token): string
{
    $firstName = $order['customer_name'] ?? '';
    $appUrl    = rtrim(env('APP_URL', 'https://veresvill.hu'), '/');
    $amount    = (int) ($order['quote_amount'] ?? 0);

    $dayNames   = ['vasárnap','hétfő','kedd','szerda','csütörtök','péntek','szombat'];
    $monthNames = [1=>'január',2=>'február',3=>'március',4=>'április',5=>'május',6=>'június',
                   7=>'július',8=>'augusztus',9=>'szeptember',10=>'október',11=>'november',12=>'december'];

    $text  = "Tisztelt {$firstName}!\n\n";
    $text .= "Korábban elküldtük árajánlatát, azonban még nem érkezett visszajelzés.\n";
    $text .= "Kérjük, foglaljon időpontot még ma!\n\n";
    $text .= "Az Ön árajánlata: " . number_format($amount, 0, ',', '.') . " Ft (-10% kedvezménnyel)\n\n";
    $text .= "SZABAD IDŐPONTOK:\n";

    foreach ($slots as $slot) {
        $startDt   = new DateTime($slot['start_time']);
        $endDt     = new DateTime($slot['end_time']);
        $dayName   = $dayNames[(int)$startDt->format('w')];
        $monthName = $monthNames[(int)$startDt->format('n')];
        $dateStr   = $startDt->format('Y') . '. ' . $monthName . ' ' . $startDt->format('j') . '. (' . $dayName . ')';
        $timeStr   = $startDt->format('H:i') . ' - ' . $endDt->format('H:i');
        $slotUrl   = "{$appUrl}/public/quote.php?token={$token}&slot={$slot['id']}";
        $text .= "\n  {$dateStr} {$timeStr}\n  Link: {$slotUrl}\n";
    }

    $text .= "\nHa egyik időpont sem felel meg:\n{$appUrl}/public/quote.php?token={$token}&reject=1\n\n";
    $text .= "FONTOS: A nem visszaigazolt megrendeléseket minden este töröljük rendszerünkből.\n";
    $text .= "Ha ma nem erősíti meg az időpontot, megrendelése törlődik.\n\n";
    $text .= "Kérdése van? veresvill.ads@gmail.com\n\n";
    $text .= "Üdvözlettel,\nVeresVill csapata";

    return $text;
}

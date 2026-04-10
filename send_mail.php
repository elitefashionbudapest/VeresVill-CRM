<?php
/**
 * Veresvill - Kapcsolati Űrlap Email Küldő
 * PHPMailer SMTP-vel
 */

// CORS és response beállítások
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: ' . (defined('APP_ORIGIN') ? APP_ORIGIN : 'https://veresvill.hu'));
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Csak POST kérés engedélyezett.']);
    exit;
}

// PHPMailer betöltése
require_once __DIR__ . '/phpmailer/src/phpmailer.php';
require_once __DIR__ . '/phpmailer/src/smtp.php';
require_once __DIR__ . '/phpmailer/src/exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// ============================================
// SMTP BEÁLLÍTÁSOK - .env fájlból
// ============================================
require_once __DIR__ . '/api/config/env.php';

// CORS origin az .env-ből
$appUrl = env('APP_URL', 'https://veresvill.hu');
$parsedUrl = parse_url($appUrl);
define('APP_ORIGIN', ($parsedUrl['scheme'] ?? 'https') . '://' . ($parsedUrl['host'] ?? 'veresvill.hu'));

define('SMTP_HOST', env('SMTP_HOST', 'mail.veresvill.hu'));
define('SMTP_PORT', (int) env('SMTP_PORT', '465'));
define('SMTP_SECURE', env('SMTP_SECURE', 'ssl'));
define('SMTP_USER', env('SMTP_USER', 'ajanlatkeres@veresvill.hu'));
define('SMTP_PASS', env('SMTP_PASS'));
define('ADMIN_EMAIL', env('ADMIN_EMAIL', 'veresvill.ads@gmail.com'));
define('ADMIN_NAME', env('ADMIN_NAME', 'Veresvill'));
define('FROM_EMAIL', env('FROM_EMAIL', 'ajanlatkeres@veresvill.hu'));
define('FROM_NAME', env('FROM_NAME', 'Veresvill - Villamos Felülvizsgálat'));

// Google reCAPTCHA
define('RECAPTCHA_SECRET', env('RECAPTCHA_SECRET', '6LdAzG8sAAAAAIbEobU5Eg9BuUTv_H5dwT9h6mBp'));

// ============================================
// reCAPTCHA ellenőrzés
// ============================================
$recaptchaResponse = $_POST['g-recaptcha-response'] ?? '';
if (empty($recaptchaResponse)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Kérjük, igazolja hogy nem robot!']);
    exit;
}

$ch = curl_init('https://www.google.com/recaptcha/api/siteverify');
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => http_build_query([
        'secret'   => RECAPTCHA_SECRET,
        'response' => $recaptchaResponse,
    ]),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 10,
]);
$recaptchaVerify = curl_exec($ch);
$curlError = curl_error($ch);
curl_close($ch);

if ($recaptchaVerify === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'reCAPTCHA szerver nem elérhető. Kérjük, próbálja újra később.']);
    exit;
}

$recaptchaData = json_decode($recaptchaVerify, true);

if (empty($recaptchaData['success'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'reCAPTCHA ellenőrzés sikertelen. Kérjük, próbálja újra.']);
    exit;
}

// ============================================
// Űrlap adatok feldolgozása
// ============================================
$name         = trim($_POST['name'] ?? '');
$email        = trim($_POST['email'] ?? '');
$phone        = trim($_POST['phone'] ?? '');
$address      = trim($_POST['address'] ?? '');
$propertyType = trim($_POST['property-type'] ?? '');
$size         = trim($_POST['size'] ?? '');
$urgency      = trim($_POST['urgency'] ?? 'normal');
$message      = trim($_POST['message'] ?? '');

// Validáció
$errors = [];
if (empty($name))    $errors[] = 'Név megadása kötelező.';
if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Érvényes email cím megadása kötelező.';
if (empty($phone))   $errors[] = 'Telefonszám megadása kötelező.';
if (empty($address)) $errors[] = 'Cím megadása kötelező.';
if (empty($propertyType)) $errors[] = 'Ingatlan típus kiválasztása kötelező.';
if (empty($size))    $errors[] = 'Ingatlan méret megadása kötelező.';

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => implode(' ', $errors)]);
    exit;
}

// Ingatlan típus magyar megnevezés
$propertyTypes = [
    'tarsashazi-lakas' => 'Társasházi lakás (6+ lakásos)',
    'csaladi-haz'      => 'Családi ház',
    'ikerhaz'          => 'Ikerház',
    'sorhaz'           => 'Sorház',
    'kis-tarsashaz'    => 'Kis társasház (2-5 lakás)',
    'uzlet'            => 'Üzlet / Iroda',
    'egyeb'            => 'Egyéb',
];
$propertyLabel = $propertyTypes[$propertyType] ?? $propertyType;

// Sürgősség magyar megnevezés
$urgencyLabels = [
    'normal'   => 'Normál (24 órán belül)',
    'same-day' => 'Még ma (aznap)',
    'express'  => 'Sürgős (24 órán belül)',
];
$urgencyLabel = $urgencyLabels[$urgency] ?? $urgency;

$dateTime = date('Y.m.d. H:i');

// ============================================
// Email küldés az ADMINNAK
// ============================================
try {
    $adminMail = new PHPMailer(true);
    $adminMail->CharSet = 'UTF-8';
    $adminMail->Encoding = 'base64';

    // SMTP beállítások
    $adminMail->isSMTP();
    $adminMail->Host       = SMTP_HOST;
    $adminMail->SMTPAuth   = true;
    $adminMail->Username   = SMTP_USER;
    $adminMail->Password   = SMTP_PASS;
    $adminMail->SMTPSecure = SMTP_SECURE;
    $adminMail->Port       = SMTP_PORT;

    // Feladó / Címzett
    $adminMail->setFrom(FROM_EMAIL, FROM_NAME);
    $adminMail->addAddress(ADMIN_EMAIL, ADMIN_NAME);
    $adminMail->addBCC('adam@visualbyadam.hu', 'Adam');
    $adminMail->addReplyTo($email, $name);

    // Tartalom
    $adminMail->isHTML(true);
    $adminMail->Subject = "Új megrendelés - {$name} | {$propertyLabel} | {$urgencyLabel}";
    $adminMail->Body    = getAdminEmailHtml($name, $email, $phone, $address, $propertyLabel, $size, $urgencyLabel, $message, $dateTime);
    $adminMail->AltBody = getAdminEmailText($name, $email, $phone, $address, $propertyLabel, $size, $urgencyLabel, $message, $dateTime);

    $adminMail->send();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Hiba történt az email küldésekor. Kérjük, próbálja újra később.']);
    exit;
}

// ============================================
// CRM: Mentés adatbázisba (nem kritikus)
// ============================================
try {
    require_once __DIR__ . '/api/config/database.php';
    require_once __DIR__ . '/api/config/constants.php';
    $pdo = getDbConnection();

    // Megrendelés mentése
    $stmt = $pdo->prepare("
        INSERT INTO vv_orders (customer_name, customer_email, customer_phone, customer_address, property_type, property_type_label, size, urgency, urgency_label, message, status)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $name,
        $email,
        $phone,
        $address,
        $propertyType,
        $propertyLabel,
        (int) $size,
        $urgency,
        $urgencyLabel,
        $message ?: null,
        ORDER_STATUS_NEW,
    ]);
    $orderId = (int) $pdo->lastInsertId();

    // Státusz napló bejegyzés
    $stmt = $pdo->prepare("
        INSERT INTO vv_order_status_log (order_id, old_status, new_status, changed_by, note, created_at)
        VALUES (?, NULL, ?, NULL, 'Automatikus rögzítés webes űrlapról', NOW())
    ");
    $stmt->execute([$orderId, ORDER_STATUS_NEW]);

    // Push értesítés küldése adminoknak (nem kritikus)
    try {
        require_once __DIR__ . '/api/services/PushService.php';
        PushService::notifyAdmins(
            'Új megrendelés!',
            "{$name} - {$propertyLabel} ({$size} m²)",
            ['order_id' => $orderId, 'type' => 'new_order']
        );
    } catch (Exception $pushError) {
        error_log('VV CRM Push notification failed: ' . $pushError->getMessage());
    }
} catch (Exception $dbError) {
    error_log('VV CRM DB save failed: ' . $dbError->getMessage());
    // DB hiba nem kritikus — az email már elment
}

// ============================================
// Visszaigazoló email a MEGRENDELŐNEK
// ============================================
try {
    $customerMail = new PHPMailer(true);
    $customerMail->CharSet = 'UTF-8';
    $customerMail->Encoding = 'base64';

    $customerMail->isSMTP();
    $customerMail->Host       = SMTP_HOST;
    $customerMail->SMTPAuth   = true;
    $customerMail->Username   = SMTP_USER;
    $customerMail->Password   = SMTP_PASS;
    $customerMail->SMTPSecure = SMTP_SECURE;
    $customerMail->Port       = SMTP_PORT;

    $customerMail->setFrom(FROM_EMAIL, FROM_NAME);
    $customerMail->addAddress($email, $name);
    $customerMail->addReplyTo(ADMIN_EMAIL, ADMIN_NAME);

    $customerMail->isHTML(true);
    $customerMail->Subject = 'Megrendelését megkaptuk! - Veresvill Villamos Felülvizsgálat';
    $customerMail->Body    = getCustomerEmailHtml($name, $email, $phone, $address, $propertyLabel, $size, $urgencyLabel, $message, $dateTime);
    $customerMail->AltBody = getCustomerEmailText($name, $address, $urgencyLabel, $dateTime);

    $customerMail->send();
} catch (Exception $e) {
    // A megrendelő email hiba nem kritikus, az admin emailt már elküldtük
}

// Siker
echo json_encode(['success' => true, 'message' => 'Köszönjük megkeresését! Kollégánk 60 percen belül felveszi Önnel a kapcsolatot.']);
exit;

// ============================================
// EMAIL SABLONOK
// ============================================

/**
 * Admin email - HTML
 */
function getAdminEmailHtml($name, $email, $phone, $address, $propertyLabel, $size, $urgencyLabel, $message, $dateTime) {
    $urgencyColor = '#4CAF50';
    if (strpos($urgencyLabel, 'aznap') !== false) $urgencyColor = '#FF9800';
    if (strpos($urgencyLabel, 'Sürgős') !== false) $urgencyColor = '#FF6B6B';

    $messageRow = '';
    if (!empty($message)) {
        $messageHtml = nl2br(htmlspecialchars($message));
        $messageRow = "
        <tr>
            <td style=\"padding: 14px 20px; font-weight: 600; color: #5A6C7D; border-bottom: 1px solid #E8F4FD; width: 160px; vertical-align: top;\">Megjegyzés</td>
            <td style=\"padding: 14px 20px; color: #2C3E50; border-bottom: 1px solid #E8F4FD;\">{$messageHtml}</td>
        </tr>";
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
        <td style="background: linear-gradient(135deg, #4A90E2 0%, #357ABD 100%); padding: 30px 40px; text-align: center;">
            <img src="https://veresvill.hu/veresvill_logo.webp" alt="VeresvVill" style="max-width: 200px; height: auto; margin-bottom: 10px;">
            <p style="color: rgba(255,255,255,0.9); margin: 8px 0 0; font-size: 14px;">Villamos Biztonsági Felülvizsgálat</p>
        </td>
    </tr>

    <!-- Új megrendelés banner -->
    <tr>
        <td style="background: #FF6B6B; padding: 16px 40px; text-align: center;">
            <h2 style="color: #FFFFFF; margin: 0; font-size: 20px; font-weight: 700;">🔔 ÚJ MEGRENDELÉS ÉRKEZETT!</h2>
            <p style="color: rgba(255,255,255,0.9); margin: 6px 0 0; font-size: 13px;">{$dateTime}</p>
        </td>
    </tr>

    <!-- Sürgősség -->
    <tr>
        <td style="padding: 25px 40px 15px;">
            <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td style="background: {$urgencyColor}; color: #FFFFFF; padding: 12px 20px; border-radius: 10px; text-align: center; font-weight: 700; font-size: 16px;">
                        ⏰ Sürgősség: {$urgencyLabel}
                    </td>
                </tr>
            </table>
        </td>
    </tr>

    <!-- Megrendelő adatai -->
    <tr>
        <td style="padding: 10px 40px 30px;">
            <h3 style="color: #4A90E2; font-size: 18px; margin: 0 0 15px; border-bottom: 3px solid #E8F4FD; padding-bottom: 10px;">📋 Megrendelő adatai</h3>
            <table width="100%" cellpadding="0" cellspacing="0" style="border: 1px solid #E8F4FD; border-radius: 10px; overflow: hidden;">
                <tr style="background: #F8FAFB;">
                    <td style="padding: 14px 20px; font-weight: 600; color: #5A6C7D; border-bottom: 1px solid #E8F4FD; width: 160px;">Név</td>
                    <td style="padding: 14px 20px; color: #2C3E50; border-bottom: 1px solid #E8F4FD; font-weight: 700; font-size: 16px;">{$name}</td>
                </tr>
                <tr>
                    <td style="padding: 14px 20px; font-weight: 600; color: #5A6C7D; border-bottom: 1px solid #E8F4FD; width: 160px;">📧 Email</td>
                    <td style="padding: 14px 20px; color: #2C3E50; border-bottom: 1px solid #E8F4FD;"><a href="mailto:{$email}" style="color: #4A90E2; text-decoration: none;">{$email}</a></td>
                </tr>
                <tr style="background: #F8FAFB;">
                    <td style="padding: 14px 20px; font-weight: 600; color: #5A6C7D; border-bottom: 1px solid #E8F4FD; width: 160px;">📞 Telefon</td>
                    <td style="padding: 14px 20px; color: #2C3E50; border-bottom: 1px solid #E8F4FD; font-weight: 700; font-size: 16px;"><a href="tel:{$phone}" style="color: #4A90E2; text-decoration: none;">{$phone}</a></td>
                </tr>
                <tr>
                    <td style="padding: 14px 20px; font-weight: 600; color: #5A6C7D; border-bottom: 1px solid #E8F4FD; width: 160px;">📍 Cím</td>
                    <td style="padding: 14px 20px; color: #2C3E50; border-bottom: 1px solid #E8F4FD;">{$address}</td>
                </tr>
                <tr style="background: #F8FAFB;">
                    <td style="padding: 14px 20px; font-weight: 600; color: #5A6C7D; border-bottom: 1px solid #E8F4FD; width: 160px;">🏠 Ingatlan típus</td>
                    <td style="padding: 14px 20px; color: #2C3E50; border-bottom: 1px solid #E8F4FD;">{$propertyLabel}</td>
                </tr>
                <tr>
                    <td style="padding: 14px 20px; font-weight: 600; color: #5A6C7D; border-bottom: 1px solid #E8F4FD; width: 160px;">📐 Méret</td>
                    <td style="padding: 14px 20px; color: #2C3E50; border-bottom: 1px solid #E8F4FD;">{$size} m²</td>
                </tr>
                {$messageRow}
            </table>
        </td>
    </tr>

    <!-- Gyors műveletek -->
    <tr>
        <td style="padding: 0 40px 30px;">
            <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td style="background: #E8F4FD; padding: 20px; border-radius: 10px; text-align: center;">
                        <p style="color: #2C3E50; margin: 0 0 10px; font-weight: 600;">⚡ Visszahívási garancia: 60 percen belül!</p>
                        <a href="tel:{$phone}" style="display: inline-block; background: #4A90E2; color: #FFFFFF; padding: 12px 30px; border-radius: 50px; text-decoration: none; font-weight: 700; font-size: 16px;">📞 Visszahívás most: {$phone}</a>
                    </td>
                </tr>
            </table>
        </td>
    </tr>

    <!-- Footer -->
    <tr>
        <td style="background: #2C3E50; padding: 20px 40px; text-align: center;">
            <img src="https://veresvill.hu/veresvill_logo.webp" alt="VeresvVill" style="max-width: 120px; height: auto; margin-bottom: 8px;">
            <p style="color: rgba(255,255,255,0.7); margin: 0; font-size: 12px;">Villamos Biztonsági Felülvizsgálat | Ez egy automatikus értesítés</p>
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
 * Admin email - Szöveges változat
 */
function getAdminEmailText($name, $email, $phone, $address, $propertyLabel, $size, $urgencyLabel, $message, $dateTime) {
    $text = "ÚJ MEGRENDELÉS - VERESVILL\n";
    $text .= "========================\n\n";
    $text .= "Dátum: {$dateTime}\n";
    $text .= "Sürgősség: {$urgencyLabel}\n\n";
    $text .= "MEGRENDELŐ ADATAI:\n";
    $text .= "Név: {$name}\n";
    $text .= "Email: {$email}\n";
    $text .= "Telefon: {$phone}\n";
    $text .= "Cím: {$address}\n";
    $text .= "Ingatlan: {$propertyLabel}\n";
    $text .= "Méret: {$size} m²\n";
    if (!empty($message)) {
        $text .= "Megjegyzés: {$message}\n";
    }
    $text .= "\n60 PERCEN BELÜL HÍVJA VISSZA!";
    return $text;
}

/**
 * Megrendelő visszaigazoló email - HTML
 */
function getCustomerEmailHtml($name, $email, $phone, $address, $propertyLabel, $size, $urgencyLabel, $message, $dateTime) {
    $firstName = explode(' ', $name)[0];

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
            <p style="color: rgba(255,255,255,0.9); margin: 8px 0 0; font-size: 14px;">Villamos Biztonsági Felülvizsgálat</p>
        </td>
    </tr>

    <!-- Visszaigazolás -->
    <tr>
        <td style="padding: 35px 40px 20px;">
            <h2 style="color: #2C3E50; font-size: 24px; margin: 0 0 15px;">Kedves {$firstName}!</h2>
            <p style="color: #5A6C7D; font-size: 16px; line-height: 1.7; margin: 0;">
                Köszönjük megkeresését! Megrendelését sikeresen rögzítettük. Kollégánk <strong style="color: #FF6B6B;">60 percen belül</strong> felveszi Önnel a kapcsolatot a megadott telefonszámon.
            </p>
        </td>
    </tr>

    <!-- Garancia badge -->
    <tr>
        <td style="padding: 0 40px 25px;">
            <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td style="background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%); color: #FFFFFF; padding: 18px 25px; border-radius: 12px; text-align: center;">
                        <p style="margin: 0; font-size: 15px; font-weight: 700;">✓ Ha nem hívjuk vissza 60 percen belül: -3.000 Ft kedvezmény!</p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>

    <!-- Megrendelés összesítő -->
    <tr>
        <td style="padding: 0 40px 30px;">
            <h3 style="color: #4A90E2; font-size: 18px; margin: 0 0 15px; border-bottom: 3px solid #E8F4FD; padding-bottom: 10px;">📋 Megrendelésének összefoglalója</h3>
            <table width="100%" cellpadding="0" cellspacing="0" style="border: 1px solid #E8F4FD; border-radius: 10px; overflow: hidden;">
                <tr style="background: #F8FAFB;">
                    <td style="padding: 12px 20px; font-weight: 600; color: #5A6C7D; border-bottom: 1px solid #E8F4FD; width: 160px;">Név</td>
                    <td style="padding: 12px 20px; color: #2C3E50; border-bottom: 1px solid #E8F4FD;">{$name}</td>
                </tr>
                <tr>
                    <td style="padding: 12px 20px; font-weight: 600; color: #5A6C7D; border-bottom: 1px solid #E8F4FD;">Helyszín</td>
                    <td style="padding: 12px 20px; color: #2C3E50; border-bottom: 1px solid #E8F4FD;">{$address}</td>
                </tr>
                <tr style="background: #F8FAFB;">
                    <td style="padding: 12px 20px; font-weight: 600; color: #5A6C7D; border-bottom: 1px solid #E8F4FD;">Ingatlan típus</td>
                    <td style="padding: 12px 20px; color: #2C3E50; border-bottom: 1px solid #E8F4FD;">{$propertyLabel}</td>
                </tr>
                <tr>
                    <td style="padding: 12px 20px; font-weight: 600; color: #5A6C7D; border-bottom: 1px solid #E8F4FD;">Méret</td>
                    <td style="padding: 12px 20px; color: #2C3E50; border-bottom: 1px solid #E8F4FD;">{$size} m²</td>
                </tr>
                <tr style="background: #F8FAFB;">
                    <td style="padding: 12px 20px; font-weight: 600; color: #5A6C7D;">Igényelt határidő</td>
                    <td style="padding: 12px 20px; color: #2C3E50; font-weight: 700;">{$urgencyLabel}</td>
                </tr>
            </table>
        </td>
    </tr>

    <!-- Folyamat lépések -->
    <tr>
        <td style="padding: 0 40px 30px;">
            <h3 style="color: #4A90E2; font-size: 18px; margin: 0 0 20px;">🔄 Mi történik ezután?</h3>

            <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 12px;">
                <tr>
                    <td width="50" valign="top">
                        <div style="background: #4A90E2; color: #FFFFFF; width: 36px; height: 36px; border-radius: 50%; text-align: center; line-height: 36px; font-weight: 700; font-size: 16px;">1</div>
                    </td>
                    <td style="padding: 5px 0 15px;">
                        <p style="margin: 0; color: #2C3E50; font-weight: 600; font-size: 15px;">Visszahívjuk 60 percen belül</p>
                        <p style="margin: 4px 0 0; color: #5A6C7D; font-size: 13px;">Egyeztetjük az időpontot és a részleteket</p>
                    </td>
                </tr>
            </table>

            <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 12px;">
                <tr>
                    <td width="50" valign="top">
                        <div style="background: #FF9800; color: #FFFFFF; width: 36px; height: 36px; border-radius: 50%; text-align: center; line-height: 36px; font-weight: 700; font-size: 16px;">2</div>
                    </td>
                    <td style="padding: 5px 0 15px;">
                        <p style="margin: 0; color: #2C3E50; font-weight: 600; font-size: 15px;">Helyszíni mérés (30-45 perc)</p>
                        <p style="margin: 4px 0 0; color: #5A6C7D; font-size: 13px;">Szakembereink elvégzik a villamos biztonsági méréseket</p>
                    </td>
                </tr>
            </table>

            <table width="100%" cellpadding="0" cellspacing="0">
                <tr>
                    <td width="50" valign="top">
                        <div style="background: #4CAF50; color: #FFFFFF; width: 36px; height: 36px; border-radius: 50%; text-align: center; line-height: 36px; font-weight: 700; font-size: 16px;">3</div>
                    </td>
                    <td style="padding: 5px 0;">
                        <p style="margin: 0; color: #2C3E50; font-weight: 600; font-size: 15px;">Jegyzőkönyv kézbesítése</p>
                        <p style="margin: 4px 0 0; color: #5A6C7D; font-size: 13px;">A mérést követő munkanapon emailben megküldjük</p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>

    <!-- Garanciák -->
    <tr>
        <td style="padding: 0 40px 30px;">
            <table width="100%" cellpadding="0" cellspacing="0" style="background: #E8F4FD; border-radius: 12px; overflow: hidden;">
                <tr>
                    <td style="padding: 20px 25px;">
                        <h4 style="color: #4A90E2; margin: 0 0 12px; font-size: 16px;">🛡️ Az Ön garanciái</h4>
                        <table width="100%" cellpadding="0" cellspacing="0">
                            <tr>
                                <td style="padding: 6px 0; color: #2C3E50; font-size: 14px;">✅ <strong>Teljesítési garancia:</strong> A jegyzőkönyv a mérést követő munkanapon garantáltan kész!</td>
                            </tr>
                            <tr>
                                <td style="padding: 6px 0; color: #2C3E50; font-size: 14px;">✅ <strong>Pontossági garancia:</strong> 30 perc késés = -3.000 Ft</td>
                            </tr>
                            <tr>
                                <td style="padding: 6px 0; color: #2C3E50; font-size: 14px;">✅ <strong>Visszahívási garancia:</strong> 60 perc = -3.000 Ft</td>
                            </tr>
                        </table>
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
            <img src="https://veresvill.hu/veresvill_logo.webp" alt="VeresvVill" style="max-width: 120px; height: auto; margin-bottom: 8px;">
            <p style="color: rgba(255,255,255,0.6); margin: 0; font-size: 12px;">Villamos Biztonsági Felülvizsgálat - Budapest és Pest megye</p>
            <p style="color: rgba(255,255,255,0.4); margin: 10px 0 0; font-size: 11px;">Ez egy automatikus visszaigazolás. Kérjük, ne válaszoljon erre az emailre.</p>
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
 * Megrendelő email - Szöveges változat
 */
function getCustomerEmailText($name, $address, $urgencyLabel, $dateTime) {
    $firstName = explode(' ', $name)[0];
    $text = "Kedves {$firstName}!\n\n";
    $text .= "Köszönjük megkeresését! Megrendelését sikeresen rögzítettük.\n";
    $text .= "Kollégánk 60 percen belül felveszi Önnel a kapcsolatot.\n\n";
    $text .= "Ha nem hívjuk vissza 60 percen belül: -3.000 Ft kedvezmény!\n\n";
    $text .= "Helyszín: {$address}\n";
    $text .= "Igényelt határidő: {$urgencyLabel}\n";
    $text .= "Beérkezés: {$dateTime}\n\n";
    $text .= "Garanciáink:\n";
    $text .= "- A jegyzőkönyv a mérést követő munkanapon garantáltan kész!\n";
    $text .= "- 30 perc késés = -3.000 Ft kedvezmény\n";
    $text .= "- 60 perc visszahívás = -3.000 Ft kedvezmény\n\n";
    $text .= "Üdvözlettel,\nVeresvill csapata\nveresvill.ads@gmail.com";
    return $text;
}

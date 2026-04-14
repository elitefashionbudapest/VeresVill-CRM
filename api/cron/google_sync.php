<?php
/**
 * Google Calendar szinkron cron job
 * cPanel-ben: */5 * * * * php /home/user/public_html/veresvill_crm/api/cron/google_sync.php
 */

require_once dirname(__DIR__) . '/config/env.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/constants.php';
require_once dirname(__DIR__) . '/services/GoogleCalendarService.php';

date_default_timezone_set('Europe/Budapest');

$pdo = getDbConnection();

// Aktív Google kapcsolattal rendelkező felhasználók
$stmt = $pdo->query("SELECT user_id FROM vv_google_tokens WHERE sync_enabled = 1");
$users = $stmt->fetchAll();

foreach ($users as $row) {
    $userId = (int) $row['user_id'];

    // Google → CRM (read-only pull, push megszűnt)
    $pull = GoogleCalendarService::syncFromGoogle($userId);

    if (php_sapi_name() === 'cli') {
        echo "[User {$userId}] Pull: created={$pull['created']}, updated={$pull['updated']}, deleted={$pull['deleted']}\n";
    }
}

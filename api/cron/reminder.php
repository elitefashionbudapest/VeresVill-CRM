<?php
/**
 * 30 perces emlékeztető cron job
 * Minden 5 percben fut. Azoknak küld emlékeztetőt akiknek az árajánlat
 * legalább 30 perce ki lett küldve, de még nem fogadták el és nem ment emlékeztető.
 *
 * cPanel cron: *\/5 * * * * php /home/user/public_html/veresvill_crm/api/cron/reminder.php
 */

require_once dirname(__DIR__) . '/config/env.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/constants.php';
require_once dirname(__DIR__) . '/services/MailService.php';

date_default_timezone_set('Europe/Budapest');

$pdo = getDbConnection();

// Megrendelések ahol:
// - státusz = ajanlat_kuldve (árajánlat el lett küldve, de ügyfél még nem reagált)
// - quote_sent_at legalább 30 perce volt
// - reminder_sent_at NULL (emlékeztető még nem ment)
// - quote_token nem lejárt (van még érvényes link az ügyfélnek)
$stmt = $pdo->prepare("
    SELECT o.*
    FROM vv_orders o
    WHERE o.status = ?
      AND o.quote_sent_at <= NOW() - INTERVAL 30 MINUTE
      AND o.reminder_sent_at IS NULL
      AND o.quote_token IS NOT NULL
      AND (o.quote_token_expires IS NULL OR o.quote_token_expires > NOW())
");
$stmt->execute([ORDER_STATUS_QUOTE_SENT]);
$orders = $stmt->fetchAll();

if (empty($orders)) {
    if (php_sapi_name() === 'cli') {
        echo "Nincs küldendő emlékeztető.\n";
    }
    exit(0);
}

foreach ($orders as $order) {
    // Időpontok lekérése
    $slotsStmt = $pdo->prepare("
        SELECT id,
               CONCAT(slot_date, ' ', slot_start) AS start_time,
               CONCAT(slot_date, ' ', slot_end)   AS end_time
        FROM vv_time_slots
        WHERE order_id = ?
        ORDER BY slot_date ASC, slot_start ASC
    ");
    $slotsStmt->execute([$order['id']]);
    $slots = $slotsStmt->fetchAll();

    $sent = false;
    try {
        $sent = MailService::sendQuoteReminder($order, $slots);
    } catch (\Exception $e) {
        error_log("Reminder mail exception (order #{$order['id']}): " . $e->getMessage());
    }

    if ($sent) {
        $pdo->prepare("UPDATE vv_orders SET reminder_sent_at = NOW() WHERE id = ?")
            ->execute([$order['id']]);

        if (php_sapi_name() === 'cli') {
            echo "Emlékeztető elküldve: order #{$order['id']} — {$order['customer_name']} ({$order['customer_email']})\n";
        }
    } else {
        error_log("Reminder mail FAILED for order #{$order['id']}");
    }
}

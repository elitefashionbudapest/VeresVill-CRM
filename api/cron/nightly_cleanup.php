<?php
/**
 * Esti törlés cron job — minden este 22:00-kor fut.
 * Törli az összes 'uj' státuszú megrendelést ahol még nem ment árajánlat.
 * (Ezek olyan rendelések ahol az ügyfél soha nem kapott visszajelzést, pl. spam vagy napközbeni no-show.)
 *
 * cPanel cron: 0 22 * * * php /home/user/public_html/veresvill_crm/api/cron/nightly_cleanup.php
 */

require_once dirname(__DIR__) . '/config/env.php';
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/constants.php';

date_default_timezone_set('Europe/Budapest');

$pdo = getDbConnection();

// Törlendő megrendelések listája (naplózáshoz)
$stmt = $pdo->prepare("
    SELECT id, customer_name, customer_email, created_at
    FROM vv_orders
    WHERE status = ?
      AND quote_sent_at IS NULL
");
$stmt->execute([ORDER_STATUS_NEW]);
$toDelete = $stmt->fetchAll();

if (empty($toDelete)) {
    if (php_sapi_name() === 'cli') {
        echo "[" . date('Y-m-d H:i:s') . "] Nincs törlendő megrendelés.\n";
    }
    exit(0);
}

// Törlés (ON DELETE CASCADE gondoskodik a time_slots-ról is)
$deleteStmt = $pdo->prepare("
    DELETE FROM vv_orders
    WHERE status = ?
      AND quote_sent_at IS NULL
");
$deleteStmt->execute([ORDER_STATUS_NEW]);
$deleted = $deleteStmt->rowCount();

if (php_sapi_name() === 'cli') {
    echo "[" . date('Y-m-d H:i:s') . "] Törölt megrendelések: {$deleted}\n";
    foreach ($toDelete as $o) {
        echo "  - #{$o['id']} {$o['customer_name']} ({$o['customer_email']}) — létrehozva: {$o['created_at']}\n";
    }
}

error_log("[nightly_cleanup] Törölt megrendelések: {$deleted} db — " . date('Y-m-d H:i:s'));

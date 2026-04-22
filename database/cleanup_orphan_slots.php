<?php
/**
 * Egyszeri cleanup: törli az el nem fogadott időpontokat azoknál a megrendeléseknél,
 * ahol már van kiválasztott (is_selected = 1) időpont.
 *
 * Futtatás: php database/cleanup_orphan_slots.php
 * Előnézet (törlés nélkül): php database/cleanup_orphan_slots.php --dry-run
 */

require_once __DIR__ . '/../api/config/env.php';
require_once __DIR__ . '/../api/config/database.php';

$dryRun = in_array('--dry-run', $argv ?? [], true);

$pdo = getDbConnection();

// Megrendelések, ahol van elfogadott slot
$stmt = $pdo->query("
    SELECT DISTINCT order_id FROM vv_time_slots WHERE is_selected = 1
");
$orderIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

if (empty($orderIds)) {
    echo "Nincs olyan megrendelés ahol elfogadott időpont lenne.\n";
    exit(0);
}

// El nem fogadott slotok száma ezekben a megrendelésekben
$placeholders = implode(',', array_fill(0, count($orderIds), '?'));
$stmt = $pdo->prepare("
    SELECT ts.id, ts.order_id, ts.slot_date, ts.slot_start, ts.slot_end,
           o.customer_name
    FROM vv_time_slots ts
    JOIN vv_orders o ON o.id = ts.order_id
    WHERE ts.is_selected = 0 AND ts.order_id IN ($placeholders)
    ORDER BY ts.order_id, ts.slot_date
");
$stmt->execute($orderIds);
$toDelete = $stmt->fetchAll();

if (empty($toDelete)) {
    echo "Nincs törlendő felesleges időpont.\n";
    exit(0);
}

echo ($dryRun ? "[DRY RUN] " : "") . count($toDelete) . " felesleges időpont találva:\n\n";

foreach ($toDelete as $row) {
    echo "  Megrendelés #{$row['order_id']} ({$row['customer_name']}): "
       . "{$row['slot_date']} {$row['slot_start']}-{$row['slot_end']} (slot id: {$row['id']})\n";
}

if ($dryRun) {
    echo "\nDry run — semmi sem törlődött. Éles futtatáshoz hagyd el a --dry-run kapcsolót.\n";
    exit(0);
}

// Törlés
$stmt = $pdo->prepare("
    DELETE FROM vv_time_slots
    WHERE is_selected = 0 AND order_id IN ($placeholders)
");
$stmt->execute($orderIds);
$deleted = $stmt->rowCount();

echo "\n{$deleted} időpont törölve.\n";

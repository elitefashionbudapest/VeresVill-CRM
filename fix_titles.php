<?php
// Egyszer futtatandó fix: naptár események címeinek frissítése
// Töröld ki utána!
require_once __DIR__ . '/api/config/env.php';
require_once __DIR__ . '/api/config/database.php';
header('Content-Type: text/plain; charset=utf-8');

$pdo = getDbConnection();
$stmt = $pdo->query("
    SELECT ce.id, ce.title, o.customer_name, o.customer_address
    FROM vv_calendar_events ce
    JOIN vv_orders o ON ce.order_id = o.id
    WHERE ce.title LIKE 'Megrendelés%'
");
$rows = $stmt->fetchAll();

$update = $pdo->prepare("UPDATE vv_calendar_events SET title = ? WHERE id = ?");
foreach ($rows as $row) {
    $newTitle = $row['customer_name'] . ' - ' . $row['customer_address'];
    $update->execute([$newTitle, $row['id']]);
    echo "#{$row['id']}: \"{$row['title']}\" → \"{$newTitle}\"\n";
}
echo "\nKész! " . count($rows) . " frissítve.\n";
echo "TÖRÖLD KI EZT A FÁJLT!\n";

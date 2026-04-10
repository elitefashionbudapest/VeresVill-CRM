<?php
/**
 * OrderStatusLog model - vv_order_status_log tabla
 */

require_once __DIR__ . '/../config/database.php';

class OrderStatusLog
{
    /**
     * Uj statuszvaltozas bejegyzes.
     * @return int Az uj naplo bejegyzes ID-ja
     */
    public static function create(int $orderId, ?string $oldStatus, string $newStatus, ?int $changedBy, string $note = ''): int
    {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare(
            'INSERT INTO vv_order_status_log (order_id, old_status, new_status, changed_by, note)
             VALUES (:order_id, :old_status, :new_status, :changed_by, :note)'
        );
        $stmt->execute([
            ':order_id'   => $orderId,
            ':old_status' => $oldStatus,
            ':new_status' => $newStatus,
            ':changed_by' => $changedBy,
            ':note'       => $note,
        ]);
        return (int) $pdo->lastInsertId();
    }

    /**
     * Statusznaplo lekerese egy megrendeleshez, legujabb elol.
     * Tartalmazza a felhasznalo nevet is (ha van changed_by).
     */
    public static function findByOrderId(int $orderId): array
    {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare(
            'SELECT l.*, u.name AS changed_by_name
             FROM vv_order_status_log l
             LEFT JOIN vv_users u ON l.changed_by = u.id
             WHERE l.order_id = :order_id
             ORDER BY l.created_at DESC'
        );
        $stmt->execute([':order_id' => $orderId]);
        return $stmt->fetchAll();
    }
}

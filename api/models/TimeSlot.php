<?php
/**
 * TimeSlot model - vv_time_slots tabla
 */

require_once __DIR__ . '/../config/database.php';

class TimeSlot
{
    /**
     * Tobb idopont slot letrehozasa egy megrendeleshez.
     * @param array $slots [{worker_id, date, start, end}, ...]
     * @return array Az uj slot ID-k
     */
    public static function createForOrder(int $orderId, array $slots): array
    {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare(
            'INSERT INTO vv_time_slots (order_id, worker_id, slot_date, slot_start, slot_end)
             VALUES (:order_id, :worker_id, :slot_date, :slot_start, :slot_end)'
        );

        $ids = [];
        foreach ($slots as $slot) {
            $stmt->execute([
                ':order_id'   => $orderId,
                ':worker_id'  => (int) $slot['worker_id'],
                ':slot_date'  => $slot['date'],
                ':slot_start' => $slot['start'],
                ':slot_end'   => $slot['end'],
            ]);
            $ids[] = (int) $pdo->lastInsertId();
        }
        return $ids;
    }

    /**
     * Osszes slot lekerese egy megrendeleshez.
     */
    public static function findByOrderId(int $orderId): array
    {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare(
            'SELECT ts.*, u.name AS worker_name
             FROM vv_time_slots ts
             LEFT JOIN vv_users u ON ts.worker_id = u.id
             WHERE ts.order_id = :order_id
             ORDER BY ts.slot_date ASC, ts.slot_start ASC'
        );
        $stmt->execute([':order_id' => $orderId]);
        return $stmt->fetchAll();
    }

    /**
     * Slot keresese ID alapjan.
     */
    public static function findById(int $id): ?array
    {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('SELECT * FROM vv_time_slots WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Slot kivalasztottnak jelolese.
     */
    public static function markSelected(int $id): bool
    {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('UPDATE vv_time_slots SET is_selected = 1 WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Osszes slot torlese egy megrendeleshez.
     */
    public static function deleteByOrderId(int $orderId): void
    {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('DELETE FROM vv_time_slots WHERE order_id = :order_id');
        $stmt->execute([':order_id' => $orderId]);
    }
}

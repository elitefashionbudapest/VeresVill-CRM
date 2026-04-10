<?php
/**
 * Order model - vv_orders tabla
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';

class Order
{
    /**
     * Megrendeles keresese ID alapjan.
     */
    public static function findById(int $id): ?array
    {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare(
            'SELECT o.*, u.name AS assigned_to_name
             FROM vv_orders o
             LEFT JOIN vv_users u ON o.assigned_to = u.id
             WHERE o.id = :id'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Megrendelesek listazasa szurekkel es lapozassal.
     * @return array{items: array, total: int}
     */
    public static function list(array $filters, int $page, int $perPage): array
    {
        $pdo = getDbConnection();
        $where = [];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = 'o.status = :status';
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['assigned_to'])) {
            $where[] = 'o.assigned_to = :assigned_to';
            $params[':assigned_to'] = (int) $filters['assigned_to'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = 'o.created_at >= :date_from';
            $params[':date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = 'o.created_at <= :date_to';
            $params[':date_to'] = $filters['date_to'] . ' 23:59:59';
        }

        if (!empty($filters['search'])) {
            $where[] = '(o.customer_name LIKE :search
                      OR o.customer_email LIKE :search
                      OR o.customer_phone LIKE :search
                      OR o.customer_address LIKE :search)';
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        $whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

        // Total count
        $countSql = "SELECT COUNT(*) FROM vv_orders o {$whereClause}";
        $countStmt = $pdo->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        // Items
        $offset = ($page - 1) * $perPage;
        $sql = "SELECT o.*, u.name AS assigned_to_name
                FROM vv_orders o
                LEFT JOIN vv_users u ON o.assigned_to = u.id
                {$whereClause}
                ORDER BY o.created_at DESC
                LIMIT :limit OFFSET :offset";
        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'items' => $stmt->fetchAll(),
            'total' => $total,
        ];
    }

    /**
     * Uj megrendeles letrehozasa (contact form).
     * @return int Az uj megrendeles ID-ja
     */
    public static function create(array $data): int
    {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare(
            'INSERT INTO vv_orders
                (customer_name, customer_email, customer_phone, customer_address,
                 property_type, property_type_label, size, urgency, urgency_label, message)
             VALUES
                (:name, :email, :phone, :address,
                 :property_type, :property_type_label, :size, :urgency, :urgency_label, :message)'
        );
        $stmt->execute([
            ':name'                => $data['customer_name'],
            ':email'               => $data['customer_email'],
            ':phone'               => $data['customer_phone'],
            ':address'             => $data['customer_address'],
            ':property_type'       => $data['property_type'],
            ':property_type_label' => $data['property_type_label'] ?? (PROPERTY_TYPES[$data['property_type']] ?? $data['property_type']),
            ':size'                => (int) $data['size'],
            ':urgency'             => $data['urgency'] ?? 'normal',
            ':urgency_label'       => $data['urgency_label'] ?? (URGENCY_LABELS[$data['urgency'] ?? 'normal'] ?? 'Normal'),
            ':message'             => $data['message'] ?? null,
        ]);

        $orderId = (int) $pdo->lastInsertId();

        // Insert initial status log
        OrderStatusLog::create($orderId, null, ORDER_STATUS_NEW, null, 'Megrendeles letrehozva');

        return $orderId;
    }

    /**
     * Megrendeles adatainak frissitese.
     */
    public static function update(int $id, array $data): bool
    {
        $allowed = [
            'customer_name', 'customer_email', 'customer_phone', 'customer_address',
            'property_type', 'property_type_label', 'size', 'urgency', 'urgency_label',
            'message', 'status', 'assigned_to', 'quote_amount', 'admin_notes',
            'completed_at',
        ];
        $sets = [];
        $params = [':id' => $id];

        foreach ($allowed as $field) {
            if (array_key_exists($field, $data)) {
                $sets[] = "{$field} = :{$field}";
                $params[":{$field}"] = $data[$field];
            }
        }

        if (empty($sets)) {
            return false;
        }

        $pdo = getDbConnection();
        $sql = 'UPDATE vv_orders SET ' . implode(', ', $sets) . ' WHERE id = :id';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    /**
     * Statusz valtas + naplo bejegyzes.
     */
    public static function updateStatus(int $id, string $newStatus, ?int $changedBy, string $note = ''): bool
    {
        $pdo = getDbConnection();

        // Fetch current status
        $stmt = $pdo->prepare('SELECT status FROM vv_orders WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $current = $stmt->fetchColumn();

        if ($current === false) {
            return false;
        }

        $updateStmt = $pdo->prepare('UPDATE vv_orders SET status = :status WHERE id = :id');
        $updateStmt->execute([':status' => $newStatus, ':id' => $id]);

        // Log the status change
        OrderStatusLog::create($id, $current, $newStatus, $changedBy, $note);

        return true;
    }

    /**
     * Arajanlat token es osszeg beallitasa.
     */
    public static function setQuoteToken(int $id, string $token, int $amount): bool
    {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare(
            'UPDATE vv_orders
             SET quote_token = :token,
                 quote_amount = :amount,
                 quote_token_expires = DATE_ADD(NOW(), INTERVAL :days DAY),
                 quote_sent_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute([
            ':token'  => $token,
            ':amount' => $amount,
            ':days'   => QUOTE_TOKEN_EXPIRY_DAYS,
            ':id'     => $id,
        ]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Megrendeles keresese arajanlat token alapjan (ervenyes token).
     */
    public static function findByQuoteToken(string $token): ?array
    {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare(
            'SELECT * FROM vv_orders
             WHERE quote_token = :token
               AND quote_token_expires > NOW()'
        );
        $stmt->execute([':token' => $token]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Arajanlat elfogadasa: statusz + idopont + elfogadas datuma.
     */
    public static function acceptQuote(int $id, int $slotId): bool
    {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare(
            'UPDATE vv_orders
             SET status = :status,
                 quote_accepted_at = NOW(),
                 selected_slot_id = :slot_id
             WHERE id = :id'
        );
        $stmt->execute([
            ':status'  => ORDER_STATUS_TIME_SELECTED,
            ':slot_id' => $slotId,
            ':id'      => $id,
        ]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Statusz szerinti darabszamok (dashboard).
     */
    public static function countByStatus(): array
    {
        $pdo = getDbConnection();
        $stmt = $pdo->query('SELECT status, COUNT(*) AS count FROM vv_orders GROUP BY status');
        $rows = $stmt->fetchAll();

        // Initialize all statuses with 0
        $result = [];
        foreach (ORDER_STATUSES as $key => $label) {
            $result[$key] = 0;
        }
        foreach ($rows as $row) {
            $result[$row['status']] = (int) $row['count'];
        }
        return $result;
    }
}

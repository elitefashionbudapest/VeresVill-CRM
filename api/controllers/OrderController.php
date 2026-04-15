<?php
/**
 * OrderController - Megrendelések kezelése
 */
class OrderController {

    /**
     * GET orders
     * Megrendelések listázása (DataTables server-side kompatibilis)
     */
    public function index(): void {
        $user = Auth::user();
        $pdo  = getDbConnection();

        // Paraméterek
        $page    = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = min(100, max(1, (int) ($_GET['per_page'] ?? 25)));
        $offset  = ($page - 1) * $perPage;

        $status  = $_GET['status'] ?? '';
        $search  = $_GET['search'] ?? '';
        $dateFrom = $_GET['date_from'] ?? '';
        $dateTo   = $_GET['date_to'] ?? '';

        // DataTables kompatibilitás
        $draw   = (int) ($_GET['draw'] ?? 0);
        $dtStart  = isset($_GET['start']) ? (int) $_GET['start'] : null;
        $dtLength = isset($_GET['length']) ? (int) $_GET['length'] : null;

        if ($dtStart !== null) {
            $offset  = $dtStart;
            $perPage = $dtLength ?: 25;
            $page    = (int) floor($offset / $perPage) + 1;
        }

        // Alap query
        $where  = [];
        $params = [];

        // Worker csak a sajátjait látja
        if ($user['role'] === ROLE_WORKER) {
            $where[]  = 'o.assigned_to = ?';
            $params[] = $user['id'];
        }

        // Státusz szűrő
        if ($status !== '' && array_key_exists($status, ORDER_STATUSES)) {
            $where[]  = 'o.status = ?';
            $params[] = $status;
        }

        // Keresés
        if ($search !== '') {
            $where[]  = '(o.customer_name LIKE ? OR o.customer_email LIKE ? OR o.customer_phone LIKE ? OR o.customer_address LIKE ?)';
            $searchLike = '%' . $search . '%';
            $params[] = $searchLike;
            $params[] = $searchLike;
            $params[] = $searchLike;
            $params[] = $searchLike;
        }

        // Dátum szűrők
        if ($dateFrom !== '') {
            $where[]  = 'o.created_at >= ?';
            $params[] = $dateFrom . ' 00:00:00';
        }
        if ($dateTo !== '') {
            $where[]  = 'o.created_at <= ?';
            $params[] = $dateTo . ' 23:59:59';
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        // Count query
        $countSql = "SELECT COUNT(*) as total FROM vv_orders o {$whereClause}";
        $stmt     = $pdo->prepare($countSql);
        $stmt->execute($params);
        $total = (int) $stmt->fetch()['total'];

        // Data query
        $sql = "
            SELECT
                o.id,
                o.customer_name,
                o.customer_email,
                o.customer_phone,
                o.customer_address,
                o.property_type,
                o.size,
                o.urgency,
                o.status,
                o.assigned_to,
                o.quote_amount,
                o.created_at,
                u.name as assigned_to_name
            FROM vv_orders o
            LEFT JOIN vv_users u ON o.assigned_to = u.id
            {$whereClause}
            ORDER BY o.created_at DESC
            LIMIT ? OFFSET ?
        ";
        $dataParams   = array_merge($params, [$perPage, $offset]);
        $stmt         = $pdo->prepare($sql);
        $stmt->execute($dataParams);
        $orders = $stmt->fetchAll();

        // Label-ek hozzáadása
        foreach ($orders as &$order) {
            $order['property_type_label'] = PROPERTY_TYPES[$order['property_type']] ?? $order['property_type'];
            $order['urgency_label']       = URGENCY_LABELS[$order['urgency']] ?? $order['urgency'];
            $order['status_label']        = ORDER_STATUSES[$order['status']] ?? $order['status'];
        }
        unset($order);

        // DataTables formátum
        if ($draw > 0) {
            Response::json([
                'draw'            => $draw,
                'recordsTotal'    => $total,
                'recordsFiltered' => $total,
                'data'            => $orders,
            ]);
        }

        // Standard paginált válasz
        Response::paginated($orders, $total, $page, $perPage);
    }

    /**
     * GET orders/{id}
     * Egyedi megrendelés részletek
     */
    public function show(string $id): void {
        $user = Auth::user();
        $pdo  = getDbConnection();
        $id   = (int) $id;

        $sql = "
            SELECT
                o.*,
                u.name as assigned_to_name
            FROM vv_orders o
            LEFT JOIN vv_users u ON o.assigned_to = u.id
            WHERE o.id = ?
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        $order = $stmt->fetch();

        if (!$order) {
            Response::error('Megrendelés nem található.', 404);
        }

        // Worker csak a sajátját láthatja
        if ($user['role'] === ROLE_WORKER && (int) $order['assigned_to'] !== (int) $user['id']) {
            Response::error('Nincs jogosultsága megtekinteni ezt a megrendelést.', 403);
        }

        // Label-ek
        $order['property_type_label'] = PROPERTY_TYPES[$order['property_type']] ?? $order['property_type'];
        $order['urgency_label']       = URGENCY_LABELS[$order['urgency']] ?? $order['urgency'];
        $order['status_label']        = ORDER_STATUSES[$order['status']] ?? $order['status'];

        // Státusz napló
        $stmt = $pdo->prepare("
            SELECT sl.*, u.name as changed_by_name
            FROM vv_order_status_log sl
            LEFT JOIN vv_users u ON sl.changed_by = u.id
            WHERE sl.order_id = ?
            ORDER BY sl.created_at DESC
        ");
        $stmt->execute([$id]);
        $order['status_log'] = $stmt->fetchAll();

        // Időpontok (time slots)
        $stmt = $pdo->prepare("
            SELECT ts.*, u.name as worker_name
            FROM vv_time_slots ts
            LEFT JOIN vv_users u ON ts.worker_id = u.id
            WHERE ts.order_id = ?
            ORDER BY ts.slot_date ASC, ts.slot_start ASC
        ");
        $stmt->execute([$id]);
        $order['time_slots'] = $stmt->fetchAll();

        Response::success($order);
    }

    /**
     * PUT orders/{id}
     * Megrendelés módosítása (admin: assigned_to, admin_notes)
     */
    public function update(string $id, array $input = []): void {
        $pdo = getDbConnection();
        $id  = (int) $id;

        // Megrendelés létezés ellenőrzés
        $stmt = $pdo->prepare("SELECT id FROM vv_orders WHERE id = ?");
        $stmt->execute([$id]);
        if (!$stmt->fetch()) {
            Response::error('Megrendelés nem található.', 404);
        }

        $sets   = [];
        $params = [];

        if (array_key_exists('assigned_to', $input)) {
            $assignedTo = $input['assigned_to'] !== null ? (int) $input['assigned_to'] : null;

            // Ha van hozzárendelés, ellenőrizzük, hogy létezik-e a felhasználó
            if ($assignedTo !== null) {
                $stmt = $pdo->prepare("SELECT id FROM vv_users WHERE id = ? AND is_active = 1");
                $stmt->execute([$assignedTo]);
                if (!$stmt->fetch()) {
                    Response::error('A kiválasztott felhasználó nem található vagy inaktív.', 422);
                }
            }

            $sets[]   = 'assigned_to = ?';
            $params[] = $assignedTo;
        }

        if (array_key_exists('admin_notes', $input)) {
            $sets[]   = 'admin_notes = ?';
            $params[] = $input['admin_notes'];
        }

        if (empty($sets)) {
            Response::error('Nincs módosítandó adat.', 422);
        }

        $params[] = $id;
        $sql = "UPDATE vv_orders SET " . implode(', ', $sets) . ", updated_at = NOW() WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        // Frissített rendelés visszaadása
        $stmt = $pdo->prepare("SELECT * FROM vv_orders WHERE id = ?");
        $stmt->execute([$id]);
        $order = $stmt->fetch();

        Response::success($order, 'Megrendelés frissítve.');
    }

    /**
     * PUT orders/{id}/status
     * Státusz változtatás (átmenet validálással)
     */
    public function updateStatus(string $id, array $input = []): void {
        $pdo  = getDbConnection();
        $user = Auth::user();
        $id   = (int) $id;

        $v = new Validator($input);
        $v->required('status', 'Státusz');

        if ($v->fails()) {
            Response::error($v->firstError(), 422, $v->errors());
        }

        $newStatus = $v->get('status');

        // Megrendelés lekérése
        $stmt = $pdo->prepare("SELECT id, status FROM vv_orders WHERE id = ?");
        $stmt->execute([$id]);
        $order = $stmt->fetch();

        if (!$order) {
            Response::error('Megrendelés nem található.', 404);
        }

        $currentStatus = $order['status'];

        // Átmenet validálás
        $allowedTransitions = ORDER_STATUS_TRANSITIONS[$currentStatus] ?? [];
        if (!in_array($newStatus, $allowedTransitions, true)) {
            $currentLabel = ORDER_STATUSES[$currentStatus] ?? $currentStatus;
            $newLabel     = ORDER_STATUSES[$newStatus] ?? $newStatus;
            Response::error(
                "Nem lehet \"{$currentLabel}\" státuszból \"{$newLabel}\" státuszba lépni.",
                422
            );
        }

        // Státusz frissítés
        $stmt = $pdo->prepare("UPDATE vv_orders SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$newStatus, $id]);

        // Napló bejegyzés
        $note = $input['note'] ?? null;
        $stmt = $pdo->prepare("
            INSERT INTO vv_order_status_log (order_id, old_status, new_status, changed_by, note)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$id, $currentStatus, $newStatus, $user['id'], $note]);

        Response::success([
            'id'         => $id,
            'old_status' => $currentStatus,
            'new_status' => $newStatus,
            'status_label' => ORDER_STATUSES[$newStatus] ?? $newStatus,
        ], 'Státusz frissítve.');
    }

    /**
     * DELETE orders/{id}
     * Megrendelés törlése (admin)
     */
    public function destroy(string $id): void {
        $pdo = getDbConnection();
        $id  = (int) $id;

        // A Sheet frissiteshez kell a telefonszam meg torles elott
        $stmt = $pdo->prepare("SELECT customer_phone FROM vv_orders WHERE id = ?");
        $stmt->execute([$id]);
        $order = $stmt->fetch();
        if (!$order) {
            Response::error('Megrendelés nem található.', 404);
        }
        $phone = $order['customer_phone'] ?? '';

        // Kapcsolódó adatok törlése (CASCADE-dal is menne, de legyen explicit)
        $pdo->prepare("DELETE FROM vv_time_slots WHERE order_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM vv_calendar_events WHERE order_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM vv_order_status_log WHERE order_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM vv_orders WHERE id = ?")->execute([$id]);

        // Google Sheet: jelolje meg a sort torlesnek (telefonszam alapjan)
        if (!empty($phone)) {
            try {
                require_once __DIR__ . '/../services/GoogleCalendarService.php';
                GoogleCalendarService::markRowDeletedByPhone($phone);
            } catch (\Exception $e) {
                error_log('Sheet mark-deleted exception: ' . $e->getMessage());
            }
        }

        Response::success(null, 'Megrendelés törölve.');
    }
}

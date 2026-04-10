<?php
/**
 * CalendarController - Naptár események kezelése
 */
class CalendarController {

    /**
     * GET calendar/events
     * Események listázása (FullCalendar kompatibilis formátum)
     */
    public function index(): void {
        $user = Auth::user();
        $pdo  = getDbConnection();

        $start = $_GET['start'] ?? date('Y-m-01');
        $end   = $_GET['end'] ?? date('Y-m-t');

        $where  = ['ce.start_datetime < ? AND ce.end_datetime > ?'];
        $params = [$end . ' 23:59:59', $start . ' 00:00:00'];

        // Worker csak a sajátját látja
        if ($user['role'] === ROLE_WORKER) {
            $where[]  = 'ce.user_id = ?';
            $params[] = $user['id'];
        }

        // Szűrés felhasználóra (admin esetén)
        if ($user['role'] === ROLE_ADMIN && !empty($_GET['user_id'])) {
            $where[]  = 'ce.user_id = ?';
            $params[] = (int) $_GET['user_id'];
        }

        $whereClause = 'WHERE ' . implode(' AND ', $where);

        $stmt = $pdo->prepare("
            SELECT
                ce.id,
                ce.title,
                ce.start_datetime,
                ce.end_datetime,
                ce.event_type,
                ce.order_id,
                ce.user_id,
                u.name as user_name
            FROM vv_calendar_events ce
            LEFT JOIN vv_users u ON ce.user_id = u.id
            {$whereClause}
            ORDER BY ce.start_datetime ASC
        ");
        $stmt->execute($params);
        $events = $stmt->fetchAll();

        // FullCalendar formátum
        $calendarEvents = array_map(function ($event) {
            $colorMap = [
                EVENT_APPOINTMENT => '#2196F3',
                EVENT_BLOCK       => '#9E9E9E',
                EVENT_TRAVEL      => '#FF9800',
            ];

            return [
                'id'    => (int) $event['id'],
                'title' => $event['title'],
                'start' => $event['start_datetime'],
                'end'   => $event['end_datetime'],
                'color' => $colorMap[$event['event_type']] ?? '#2196F3',
                'extendedProps' => [
                    'order_id'   => $event['order_id'] ? (int) $event['order_id'] : null,
                    'event_type' => $event['event_type'],
                    'user_id'    => (int) $event['user_id'],
                    'user_name'  => $event['user_name'],
                ],
            ];
        }, $events);

        Response::success($calendarEvents);
    }

    /**
     * POST calendar/events
     * Új esemény létrehozása
     */
    public function store(array $input = []): void {
        $user = Auth::user();
        $pdo  = getDbConnection();

        $v = new Validator($input);
        $v->required('title', 'Cím')
          ->required('start_datetime', 'Kezdés')
          ->required('end_datetime', 'Befejezés')
          ->required('event_type', 'Esemény típus')
          ->inArray('event_type', [EVENT_APPOINTMENT, EVENT_BLOCK, EVENT_TRAVEL], 'Esemény típus');

        if ($v->fails()) {
            Response::error($v->firstError(), 422, $v->errors());
        }

        // Admin bárki nevében, worker csak a sajátját
        $targetUserId = $user['id'];
        if ($user['role'] === ROLE_ADMIN && !empty($input['user_id'])) {
            $targetUserId = (int) $input['user_id'];
        }

        $startDatetime = $v->get('start_datetime');
        $endDatetime   = $v->get('end_datetime');
        $eventType     = $v->get('event_type');

        // Időpont validálás
        $startTs = strtotime($startDatetime);
        $endTs   = strtotime($endDatetime);

        if (!$startTs || !$endTs) {
            Response::error('Érvénytelen dátum formátum.', 422);
        }

        if ($endTs <= $startTs) {
            Response::error('A befejezés időpontja a kezdés után kell legyen.', 422);
        }

        // Ütközés ellenőrzés
        if ($this->hasConflict($pdo, $targetUserId, $startDatetime, $endDatetime)) {
            Response::error('Időpont ütközés van egy másik eseménnyel.', 409);
        }

        $orderId = !empty($input['order_id']) ? (int) $input['order_id'] : null;

        $stmt = $pdo->prepare("
            INSERT INTO vv_calendar_events (user_id, order_id, title, start_datetime, end_datetime, event_type)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $targetUserId,
            $orderId,
            $v->get('title'),
            $startDatetime,
            $endDatetime,
            $eventType,
        ]);

        $eventId = (int) $pdo->lastInsertId();

        Response::success(['id' => $eventId], 'Esemény létrehozva.', 201);
    }

    /**
     * PUT calendar/events/{id}
     * Esemény módosítása
     */
    public function update(string $id, array $input = []): void {
        $user = Auth::user();
        $pdo  = getDbConnection();
        $id   = (int) $id;

        // Esemény lekérése
        $stmt = $pdo->prepare("SELECT * FROM vv_calendar_events WHERE id = ?");
        $stmt->execute([$id]);
        $event = $stmt->fetch();

        if (!$event) {
            Response::error('Esemény nem található.', 404);
        }

        // Worker csak a sajátját módosíthatja
        if ($user['role'] === ROLE_WORKER && (int) $event['user_id'] !== (int) $user['id']) {
            Response::error('Nincs jogosultsága módosítani ezt az eseményt.', 403);
        }

        $sets   = [];
        $params = [];

        if (!empty($input['title'])) {
            $sets[]   = 'title = ?';
            $params[] = trim($input['title']);
        }

        if (!empty($input['start_datetime'])) {
            $sets[]   = 'start_datetime = ?';
            $params[] = $input['start_datetime'];
        }

        if (!empty($input['end_datetime'])) {
            $sets[]   = 'end_datetime = ?';
            $params[] = $input['end_datetime'];
        }

        if (!empty($input['event_type'])) {
            $validTypes = [EVENT_APPOINTMENT, EVENT_BLOCK, EVENT_TRAVEL];
            if (!in_array($input['event_type'], $validTypes, true)) {
                Response::error('Érvénytelen esemény típus.', 422);
            }
            $sets[]   = 'event_type = ?';
            $params[] = $input['event_type'];
        }

        if (empty($sets)) {
            Response::error('Nincs módosítandó adat.', 422);
        }

        // Ütközés ellenőrzés, ha időpont változott
        $newStart = $input['start_datetime'] ?? $event['start_datetime'];
        $newEnd   = $input['end_datetime'] ?? $event['end_datetime'];

        if ($newStart !== $event['start_datetime'] || $newEnd !== $event['end_datetime']) {
            if ($this->hasConflict($pdo, $event['user_id'], $newStart, $newEnd, $id)) {
                Response::error('Időpont ütközés van egy másik eseménnyel.', 409);
            }
        }

        $params[] = $id;
        $sql  = "UPDATE vv_calendar_events SET " . implode(', ', $sets) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        Response::success(null, 'Esemény frissítve.');
    }

    /**
     * DELETE calendar/events/{id}
     * Esemény törlése
     */
    public function destroy(string $id): void {
        $user = Auth::user();
        $pdo  = getDbConnection();
        $id   = (int) $id;

        // Esemény lekérése
        $stmt = $pdo->prepare("SELECT * FROM vv_calendar_events WHERE id = ?");
        $stmt->execute([$id]);
        $event = $stmt->fetch();

        if (!$event) {
            Response::error('Esemény nem található.', 404);
        }

        // Worker csak a sajátját törölheti
        if ($user['role'] === ROLE_WORKER && (int) $event['user_id'] !== (int) $user['id']) {
            Response::error('Nincs jogosultsága törölni ezt az eseményt.', 403);
        }

        $stmt = $pdo->prepare("DELETE FROM vv_calendar_events WHERE id = ?");
        $stmt->execute([$id]);

        Response::success(null, 'Esemény törölve.');
    }

    /**
     * GET calendar/available-slots
     * Szabad időpontok keresése egy munkáshoz
     */
    public function availableSlots(): void {
        $pdo = getDbConnection();

        $workerId  = (int) ($_GET['worker_id'] ?? 0);
        $dateFrom  = $_GET['date_from'] ?? date('Y-m-d');
        $dateTo    = $_GET['date_to'] ?? date('Y-m-d', strtotime('+7 days'));
        $duration  = max(1, (int) ($_GET['duration'] ?? 1)); // órában

        if ($workerId <= 0) {
            Response::error('Munkás megadása kötelező.', 422);
        }

        // Munkás ellenőrzés
        $stmt = $pdo->prepare("SELECT id, name FROM vv_users WHERE id = ? AND is_active = 1");
        $stmt->execute([$workerId]);
        $worker = $stmt->fetch();

        if (!$worker) {
            Response::error('Munkás nem található.', 404);
        }

        // Meglévő események lekérése az adott időszakban
        $stmt = $pdo->prepare("
            SELECT start_datetime, end_datetime
            FROM vv_calendar_events
            WHERE user_id = ?
            AND DATE(start_datetime) >= ?
            AND DATE(end_datetime) <= ?
            ORDER BY start_datetime ASC
        ");
        $stmt->execute([$workerId, $dateFrom, $dateTo]);
        $busySlots = $stmt->fetchAll();

        // Szabad slot-ok keresése (8:00-17:00 munkaidő)
        $workStartHour = 8;
        $workEndHour   = 17;
        $availableSlots = [];

        $currentDate = new DateTime($dateFrom);
        $endDate     = new DateTime($dateTo);

        while ($currentDate <= $endDate) {
            $dateStr = $currentDate->format('Y-m-d');

            // Hétvége kihagyása
            $dayOfWeek = (int) $currentDate->format('N');
            if ($dayOfWeek >= 6) {
                $currentDate->modify('+1 day');
                continue;
            }

            // Óránkénti slot-ok generálása
            for ($hour = $workStartHour; $hour <= $workEndHour - $duration; $hour++) {
                $slotStart = sprintf('%s %02d:00:00', $dateStr, $hour);
                $slotEnd   = sprintf('%s %02d:00:00', $dateStr, $hour + $duration);

                $isBusy = false;
                foreach ($busySlots as $busy) {
                    // Ütközés: ha a slot kezdete a foglalt időszak előtt van ÉS a slot vége a foglalt kezdete után
                    if ($slotStart < $busy['end_datetime'] && $slotEnd > $busy['start_datetime']) {
                        $isBusy = true;
                        break;
                    }
                }

                if (!$isBusy) {
                    $availableSlots[] = [
                        'date'  => $dateStr,
                        'start' => sprintf('%02d:00', $hour),
                        'end'   => sprintf('%02d:00', $hour + $duration),
                    ];
                }
            }

            $currentDate->modify('+1 day');
        }

        Response::success([
            'worker'    => $worker,
            'date_from' => $dateFrom,
            'date_to'   => $dateTo,
            'duration'  => $duration,
            'slots'     => $availableSlots,
        ]);
    }

    /**
     * Ütközés ellenőrzés
     */
    private function hasConflict(PDO $pdo, int $userId, string $start, string $end, ?int $excludeId = null): bool {
        $sql = "
            SELECT COUNT(*) as cnt
            FROM vv_calendar_events
            WHERE user_id = ?
            AND start_datetime < ?
            AND end_datetime > ?
        ";
        $params = [$userId, $end, $start];

        if ($excludeId !== null) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetch()['cnt'] > 0;
    }
}

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

        $where  = ['ce.event_date >= ? AND ce.event_date <= ?'];
        $params = [$start, $end];

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
                ce.event_date,
                ce.start_time,
                ce.end_time,
                ce.event_type,
                ce.order_id,
                ce.notes,
                ce.user_id,
                u.name as user_name
            FROM vv_calendar_events ce
            LEFT JOIN vv_users u ON ce.user_id = u.id
            {$whereClause}
            ORDER BY ce.event_date ASC, ce.start_time ASC
        ");
        $stmt->execute($params);
        $events = $stmt->fetchAll();

        // FullCalendar formátum
        $calendarEvents = array_map(function ($event) {
            $colorMap = [
                EVENT_APPOINTMENT => '#28a745',
                EVENT_BLOCK       => '#6c757d',
                EVENT_TRAVEL      => '#17a2b8',
            ];

            return [
                'id'    => (int) $event['id'],
                'title' => $event['title'],
                'start' => $event['event_date'] . 'T' . $event['start_time'],
                'end'   => $event['event_date'] . 'T' . $event['end_time'],
                'color' => $colorMap[$event['event_type']] ?? '#007bff',
                'extendedProps' => [
                    'order_id'   => $event['order_id'] ? (int) $event['order_id'] : null,
                    'event_type' => $event['event_type'],
                    'notes'      => $event['notes'],
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
        $v->required('event_date', 'Dátum')
          ->required('start_time', 'Kezdés')
          ->required('end_time', 'Befejezés')
          ->required('event_type', 'Esemény típus')
          ->inArray('event_type', [EVENT_APPOINTMENT, EVENT_BLOCK, EVENT_TRAVEL], 'Esemény típus');

        if ($v->fails()) {
            Response::error($v->firstError(), 422, $v->errors());
        }

        $targetUserId = $user['id'];
        if ($user['role'] === ROLE_ADMIN && !empty($input['user_id'])) {
            $targetUserId = (int) $input['user_id'];
        }

        $eventDate = $v->get('event_date');
        $startTime = $v->get('start_time');
        $endTime   = $v->get('end_time');
        $title     = $v->get('title') ?: $v->get('event_type');

        if ($endTime <= $startTime) {
            Response::error('A befejezés időpontja a kezdés után kell legyen.', 422);
        }

        // Ütközés ellenőrzés
        if ($this->hasConflict($pdo, $targetUserId, $eventDate, $startTime, $endTime)) {
            Response::error('Időpont ütközés van egy másik eseménnyel.', 409);
        }

        $orderId = !empty($input['order_id']) ? (int) $input['order_id'] : null;

        $stmt = $pdo->prepare("
            INSERT INTO vv_calendar_events (user_id, order_id, title, event_date, start_time, end_time, event_type, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $targetUserId,
            $orderId,
            $title,
            $eventDate,
            $startTime,
            $endTime,
            $v->get('event_type'),
            $input['notes'] ?? null,
        ]);

        Response::success(['id' => (int) $pdo->lastInsertId()], 'Esemény létrehozva.', 201);
    }

    /**
     * PUT calendar/events/{id}
     * Esemény módosítása
     */
    public function update(string $id, array $input = []): void {
        $user = Auth::user();
        $pdo  = getDbConnection();
        $id   = (int) $id;

        $stmt = $pdo->prepare("SELECT * FROM vv_calendar_events WHERE id = ?");
        $stmt->execute([$id]);
        $event = $stmt->fetch();

        if (!$event) {
            Response::error('Esemény nem található.', 404);
        }

        if ($user['role'] === ROLE_WORKER && (int) $event['user_id'] !== (int) $user['id']) {
            Response::error('Nincs jogosultsága módosítani ezt az eseményt.', 403);
        }

        $sets   = [];
        $params = [];

        if (!empty($input['title'])) {
            $sets[]   = 'title = ?';
            $params[] = trim($input['title']);
        }
        if (!empty($input['event_date'])) {
            $sets[]   = 'event_date = ?';
            $params[] = $input['event_date'];
        }
        if (!empty($input['start_time'])) {
            $sets[]   = 'start_time = ?';
            $params[] = $input['start_time'];
        }
        if (!empty($input['end_time'])) {
            $sets[]   = 'end_time = ?';
            $params[] = $input['end_time'];
        }
        if (!empty($input['event_type'])) {
            $sets[]   = 'event_type = ?';
            $params[] = $input['event_type'];
        }
        if (array_key_exists('notes', $input)) {
            $sets[]   = 'notes = ?';
            $params[] = $input['notes'];
        }

        if (empty($sets)) {
            Response::error('Nincs módosítandó adat.', 422);
        }

        // Ütközés ellenőrzés ha időpont változott
        $newDate  = $input['event_date'] ?? $event['event_date'];
        $newStart = $input['start_time'] ?? $event['start_time'];
        $newEnd   = $input['end_time'] ?? $event['end_time'];

        if ($newDate !== $event['event_date'] || $newStart !== $event['start_time'] || $newEnd !== $event['end_time']) {
            if ($this->hasConflict($pdo, $event['user_id'], $newDate, $newStart, $newEnd, $id)) {
                Response::error('Időpont ütközés van egy másik eseménnyel.', 409);
            }
        }

        $params[] = $id;
        $stmt = $pdo->prepare("UPDATE vv_calendar_events SET " . implode(', ', $sets) . " WHERE id = ?");
        $stmt->execute($params);

        Response::success(null, 'Esemény frissítve.');
    }

    /**
     * DELETE calendar/events/{id}
     */
    public function destroy(string $id): void {
        $user = Auth::user();
        $pdo  = getDbConnection();
        $id   = (int) $id;

        $stmt = $pdo->prepare("SELECT * FROM vv_calendar_events WHERE id = ?");
        $stmt->execute([$id]);
        $event = $stmt->fetch();

        if (!$event) {
            Response::error('Esemény nem található.', 404);
        }

        if ($user['role'] === ROLE_WORKER && (int) $event['user_id'] !== (int) $user['id']) {
            Response::error('Nincs jogosultsága törölni ezt az eseményt.', 403);
        }

        $stmt = $pdo->prepare("DELETE FROM vv_calendar_events WHERE id = ?");
        $stmt->execute([$id]);

        Response::success(null, 'Esemény törölve.');
    }

    /**
     * GET calendar/available-slots
     */
    public function availableSlots(): void {
        $pdo = getDbConnection();

        $workerId = (int) ($_GET['worker_id'] ?? 0);
        $dateFrom = $_GET['date_from'] ?? date('Y-m-d');
        $dateTo   = $_GET['date_to'] ?? date('Y-m-d', strtotime('+7 days'));
        $duration = max(1, (int) ($_GET['duration'] ?? 1));

        if ($workerId <= 0) {
            Response::error('Munkás megadása kötelező.', 422);
        }

        $stmt = $pdo->prepare("SELECT id, name FROM vv_users WHERE id = ? AND is_active = 1");
        $stmt->execute([$workerId]);
        $worker = $stmt->fetch();

        if (!$worker) {
            Response::error('Munkás nem található.', 404);
        }

        // Foglalt időpontok
        $stmt = $pdo->prepare("
            SELECT event_date, start_time, end_time
            FROM vv_calendar_events
            WHERE user_id = ? AND event_date >= ? AND event_date <= ?
            ORDER BY event_date ASC, start_time ASC
        ");
        $stmt->execute([$workerId, $dateFrom, $dateTo]);
        $busySlots = $stmt->fetchAll();

        $workStartHour = 8;
        $workEndHour   = 17;
        $availableSlots = [];

        $currentDate = new DateTime($dateFrom);
        $endDate     = new DateTime($dateTo);

        while ($currentDate <= $endDate) {
            $dateStr   = $currentDate->format('Y-m-d');
            $dayOfWeek = (int) $currentDate->format('N');

            if ($dayOfWeek >= 6) {
                $currentDate->modify('+1 day');
                continue;
            }

            for ($hour = $workStartHour; $hour <= $workEndHour - $duration; $hour++) {
                $slotStart = sprintf('%02d:00:00', $hour);
                $slotEnd   = sprintf('%02d:00:00', $hour + $duration);

                $isBusy = false;
                foreach ($busySlots as $busy) {
                    if ($busy['event_date'] === $dateStr && $slotStart < $busy['end_time'] && $slotEnd > $busy['start_time']) {
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
            'slots'     => $availableSlots,
        ]);
    }

    /**
     * Ütközés ellenőrzés
     */
    private function hasConflict(PDO $pdo, int $userId, string $date, string $start, string $end, ?int $excludeId = null): bool {
        $sql = "
            SELECT COUNT(*) as cnt
            FROM vv_calendar_events
            WHERE user_id = ? AND event_date = ? AND start_time < ? AND end_time > ?
        ";
        $params = [$userId, $date, $end, $start];

        if ($excludeId !== null) {
            $sql .= " AND id != ?";
            $params[] = $excludeId;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetch()['cnt'] > 0;
    }
}

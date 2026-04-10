<?php
/**
 * CalendarEvent model - vv_calendar_events tabla
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';

class CalendarEvent
{
    // FullCalendar szinek
    private const COLORS = [
        EVENT_APPOINTMENT => '#28a745', // green
        EVENT_BLOCK       => '#6c757d', // gray
        EVENT_TRAVEL      => '#17a2b8', // teal
    ];
    private const COLOR_TIME_SELECTED = '#28a745'; // green - idopont kivalasztva
    private const COLOR_PENDING_SLOT  = '#ffc107'; // yellow - varakozas valaszra

    /**
     * Esemenyek lekerese datumtartomanyra, FullCalendar formatumban.
     */
    public static function findByDateRange(string $start, string $end, ?int $userId = null): array
    {
        $pdo = getDbConnection();

        $where = 'ce.event_date >= :start AND ce.event_date <= :end';
        $params = [':start' => $start, ':end' => $end];

        if ($userId !== null) {
            $where .= ' AND ce.user_id = :user_id';
            $params[':user_id'] = $userId;
        }

        $stmt = $pdo->prepare(
            "SELECT ce.*, u.name AS user_name, o.status AS order_status, o.customer_name
             FROM vv_calendar_events ce
             LEFT JOIN vv_users u ON ce.user_id = u.id
             LEFT JOIN vv_orders o ON ce.order_id = o.id
             WHERE {$where}
             ORDER BY ce.event_date ASC, ce.start_time ASC"
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        return array_map(function (array $row) {
            $color = self::COLORS[$row['event_type']] ?? '#28a745';

            // Order-linked events: override color based on order status
            if ($row['order_id'] && $row['order_status'] === ORDER_STATUS_TIME_SELECTED) {
                $color = self::COLOR_TIME_SELECTED;
            }

            return [
                'id'    => (int) $row['id'],
                'title' => $row['title'],
                'start' => $row['event_date'] . 'T' . $row['start_time'],
                'end'   => $row['event_date'] . 'T' . $row['end_time'],
                'color' => $color,
                'extendedProps' => [
                    'event_type'     => $row['event_type'],
                    'user_id'        => (int) $row['user_id'],
                    'user_name'      => $row['user_name'],
                    'order_id'       => $row['order_id'] ? (int) $row['order_id'] : null,
                    'customer_name'  => $row['customer_name'] ?? null,
                    'notes'          => $row['notes'],
                ],
            ];
        }, $rows);
    }

    /**
     * Uj naptar esemeny letrehozasa.
     * @return int Az uj esemeny ID-ja
     */
    public static function create(array $data): int
    {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare(
            'INSERT INTO vv_calendar_events
                (user_id, order_id, title, event_date, start_time, end_time, event_type, notes)
             VALUES
                (:user_id, :order_id, :title, :event_date, :start_time, :end_time, :event_type, :notes)'
        );
        $stmt->execute([
            ':user_id'    => (int) $data['user_id'],
            ':order_id'   => $data['order_id'] ?? null,
            ':title'      => $data['title'],
            ':event_date' => $data['event_date'],
            ':start_time' => $data['start_time'],
            ':end_time'   => $data['end_time'],
            ':event_type' => $data['event_type'] ?? EVENT_APPOINTMENT,
            ':notes'      => $data['notes'] ?? null,
        ]);
        return (int) $pdo->lastInsertId();
    }

    /**
     * Esemeny frissitese.
     */
    public static function update(int $id, array $data): bool
    {
        $allowed = ['title', 'event_date', 'start_time', 'end_time', 'event_type', 'notes', 'order_id', 'user_id'];
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
        $sql = 'UPDATE vv_calendar_events SET ' . implode(', ', $sets) . ' WHERE id = :id';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    /**
     * Esemeny torlese.
     */
    public static function delete(int $id): bool
    {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('DELETE FROM vv_calendar_events WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Esemeny keresese ID alapjan.
     */
    public static function findById(int $id): ?array
    {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('SELECT * FROM vv_calendar_events WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Utkozes ellenorzes: van-e mar esemeny az adott idopontban.
     */
    public static function hasConflict(int $userId, string $date, string $startTime, string $endTime, ?int $excludeId = null): bool
    {
        $pdo = getDbConnection();

        $excludeClause = '';
        $params = [
            ':user_id'    => $userId,
            ':date'       => $date,
            ':start_time' => $startTime,
            ':end_time'   => $endTime,
        ];

        if ($excludeId !== null) {
            $excludeClause = 'AND id != :exclude_id';
            $params[':exclude_id'] = $excludeId;
        }

        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM vv_calendar_events
             WHERE user_id = :user_id
               AND event_date = :date
               AND start_time < :end_time
               AND end_time > :start_time
               {$excludeClause}"
        );
        $stmt->execute($params);
        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Szabad idopontok keresese egy munkas szamara.
     * Visszaadja a szabad slotokat a megadott idotartammal.
     */
    public static function getAvailableSlots(int $workerId, string $dateFrom, string $dateTo, int $durationMinutes = 60): array
    {
        $pdo = getDbConnection();

        // Fetch working hours from settings
        $settingsStmt = $pdo->prepare("SELECT setting_value FROM vv_settings WHERE setting_key = 'working_hours'");
        $settingsStmt->execute();
        $workingHours = json_decode($settingsStmt->fetchColumn(), true);

        $dayMap = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];

        // Fetch existing events in range
        $eventsStmt = $pdo->prepare(
            'SELECT event_date, start_time, end_time
             FROM vv_calendar_events
             WHERE user_id = :worker_id
               AND event_date >= :date_from
               AND event_date <= :date_to
             ORDER BY event_date, start_time'
        );
        $eventsStmt->execute([
            ':worker_id'  => $workerId,
            ':date_from'  => $dateFrom,
            ':date_to'    => $dateTo,
        ]);
        $events = $eventsStmt->fetchAll();

        // Group events by date
        $eventsByDate = [];
        foreach ($events as $ev) {
            $eventsByDate[$ev['event_date']][] = $ev;
        }

        $availableSlots = [];
        $currentDate = new DateTime($dateFrom);
        $endDate = new DateTime($dateTo);

        while ($currentDate <= $endDate) {
            $dateStr = $currentDate->format('Y-m-d');
            $dayOfWeek = (int) $currentDate->format('N'); // 1=Mon, 7=Sun
            $dayKey = $dayMap[$dayOfWeek - 1];

            // Check if working day
            if (empty($workingHours[$dayKey])) {
                $currentDate->modify('+1 day');
                continue;
            }

            $dayStart = $workingHours[$dayKey]['start'];
            $dayEnd   = $workingHours[$dayKey]['end'];
            $dayEvents = $eventsByDate[$dateStr] ?? [];

            // Sort events by start time
            usort($dayEvents, function ($a, $b) {
                return strcmp($a['start_time'], $b['start_time']);
            });

            // Find gaps
            $cursor = $dayStart;
            foreach ($dayEvents as $ev) {
                $evStart = substr($ev['start_time'], 0, 5); // HH:MM
                $evEnd   = substr($ev['end_time'], 0, 5);

                // Gap between cursor and event start
                $gapMinutes = self::timeDiffMinutes($cursor, $evStart);
                if ($gapMinutes >= $durationMinutes) {
                    $slotStart = $cursor;
                    while (self::timeDiffMinutes($slotStart, $evStart) >= $durationMinutes) {
                        $slotEnd = self::addMinutes($slotStart, $durationMinutes);
                        if (strcmp($slotEnd, $evStart) <= 0) {
                            $availableSlots[] = [
                                'date'  => $dateStr,
                                'start' => $slotStart,
                                'end'   => $slotEnd,
                            ];
                        }
                        $slotStart = $slotEnd;
                    }
                }

                // Move cursor past event
                if (strcmp($evEnd, $cursor) > 0) {
                    $cursor = $evEnd;
                }
            }

            // Gap after last event until day end
            $gapMinutes = self::timeDiffMinutes($cursor, $dayEnd);
            if ($gapMinutes >= $durationMinutes) {
                $slotStart = $cursor;
                while (self::timeDiffMinutes($slotStart, $dayEnd) >= $durationMinutes) {
                    $slotEnd = self::addMinutes($slotStart, $durationMinutes);
                    if (strcmp($slotEnd, $dayEnd) <= 0) {
                        $availableSlots[] = [
                            'date'  => $dateStr,
                            'start' => $slotStart,
                            'end'   => $slotEnd,
                        ];
                    }
                    $slotStart = $slotEnd;
                }
            }

            $currentDate->modify('+1 day');
        }

        return $availableSlots;
    }

    /**
     * Ket HH:MM ido kozotti kulonbseg percben.
     */
    private static function timeDiffMinutes(string $from, string $to): int
    {
        $fromParts = explode(':', $from);
        $toParts   = explode(':', $to);
        $fromMin = (int) $fromParts[0] * 60 + (int) $fromParts[1];
        $toMin   = (int) $toParts[0] * 60 + (int) $toParts[1];
        return $toMin - $fromMin;
    }

    /**
     * Percek hozzaadasa HH:MM formatumhoz.
     */
    private static function addMinutes(string $time, int $minutes): string
    {
        $parts = explode(':', $time);
        $totalMin = (int) $parts[0] * 60 + (int) $parts[1] + $minutes;
        return sprintf('%02d:%02d', intdiv($totalMin, 60), $totalMin % 60);
    }
}

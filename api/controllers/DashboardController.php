<?php
/**
 * DashboardController - Irányítópult statisztikák
 */
class DashboardController {

    /**
     * GET dashboard/stats
     * Összesített statisztikák
     */
    public function stats(): void {
        $user = Auth::user();
        $pdo  = getDbConnection();

        // Státuszonkénti darabszámok
        $whereUser  = '';
        $userParams = [];

        if ($user['role'] === ROLE_WORKER) {
            $whereUser  = 'WHERE assigned_to = ?';
            $userParams = [$user['id']];
        }

        $stmt = $pdo->prepare("
            SELECT status, COUNT(*) as count
            FROM vv_orders
            {$whereUser}
            GROUP BY status
        ");
        $stmt->execute($userParams);
        $statusRows = $stmt->fetchAll();

        $statusCounts = [];
        $totalOrders  = 0;
        foreach ($statusRows as $row) {
            $statusCounts[$row['status']] = [
                'count' => (int) $row['count'],
                'label' => ORDER_STATUSES[$row['status']] ?? $row['status'],
            ];
            $totalOrders += (int) $row['count'];
        }

        // Ma esedékes találkozók
        $today = date('Y-m-d');

        $todaySql = "
            SELECT ce.id, ce.title, ce.start_datetime, ce.end_datetime, ce.order_id,
                   u.name as user_name
            FROM vv_calendar_events ce
            LEFT JOIN vv_users u ON ce.user_id = u.id
            WHERE DATE(ce.start_datetime) = ?
            AND ce.event_type = ?
        ";
        $todayParams = [$today, EVENT_APPOINTMENT];

        if ($user['role'] === ROLE_WORKER) {
            $todaySql     .= " AND ce.user_id = ?";
            $todayParams[] = $user['id'];
        }

        $todaySql .= " ORDER BY ce.start_datetime ASC";

        $stmt = $pdo->prepare($todaySql);
        $stmt->execute($todayParams);
        $todayAppointments = $stmt->fetchAll();

        // Havi bevétel (elfogadott, időpont kiválasztott, elvégzett)
        $monthStart = date('Y-m-01');
        $monthEnd   = date('Y-m-t');

        $revenueSql = "
            SELECT COALESCE(SUM(quote_amount), 0) as revenue
            FROM vv_orders
            WHERE status IN (?, ?, ?)
            AND updated_at >= ?
            AND updated_at <= ?
        ";
        $revenueParams = [
            ORDER_STATUS_ACCEPTED,
            ORDER_STATUS_TIME_SELECTED,
            ORDER_STATUS_DONE,
            $monthStart . ' 00:00:00',
            $monthEnd . ' 23:59:59',
        ];

        if ($user['role'] === ROLE_WORKER) {
            $revenueSql     .= " AND assigned_to = ?";
            $revenueParams[] = $user['id'];
        }

        $stmt = $pdo->prepare($revenueSql);
        $stmt->execute($revenueParams);
        $monthlyRevenue = (int) $stmt->fetch()['revenue'];

        // Új megrendelések ma
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count
            FROM vv_orders
            WHERE DATE(created_at) = ?
            " . ($user['role'] === ROLE_WORKER ? 'AND assigned_to = ?' : '') . "
        ");
        $newTodayParams = [$today];
        if ($user['role'] === ROLE_WORKER) {
            $newTodayParams[] = $user['id'];
        }
        $stmt->execute($newTodayParams);
        $newToday = (int) $stmt->fetch()['count'];

        Response::success([
            'total_orders'        => $totalOrders,
            'status_counts'       => $statusCounts,
            'today_appointments'  => $todayAppointments,
            'today_appointment_count' => count($todayAppointments),
            'monthly_revenue'     => $monthlyRevenue,
            'monthly_revenue_formatted' => number_format($monthlyRevenue, 0, ',', ' ') . ' Ft',
            'new_orders_today'    => $newToday,
        ]);
    }
}

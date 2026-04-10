<?php
/**
 * PushSubscription model - vv_push_subscriptions tabla
 */

require_once __DIR__ . '/../config/database.php';

class PushSubscription
{
    /**
     * Uj push feliratkozas letrehozasa.
     * @return int Az uj feliratkozas ID-ja
     */
    public static function subscribe(int $userId, array $data): int
    {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare(
            'INSERT INTO vv_push_subscriptions
                (user_id, platform, endpoint, p256dh, auth, device_token, user_agent)
             VALUES
                (:user_id, :platform, :endpoint, :p256dh, :auth, :device_token, :user_agent)'
        );
        $stmt->execute([
            ':user_id'      => $userId,
            ':platform'     => $data['platform'] ?? 'web',
            ':endpoint'     => $data['endpoint'] ?? null,
            ':p256dh'       => $data['p256dh'] ?? null,
            ':auth'         => $data['auth'] ?? null,
            ':device_token' => $data['device_token'] ?? null,
            ':user_agent'   => $data['user_agent'] ?? null,
        ]);
        return (int) $pdo->lastInsertId();
    }

    /**
     * Leiratkozas endpoint vagy device_token alapjan.
     */
    public static function unsubscribe(int $userId, ?string $endpoint = null, ?string $deviceToken = null): bool
    {
        $pdo = getDbConnection();

        if ($endpoint !== null) {
            $stmt = $pdo->prepare(
                'DELETE FROM vv_push_subscriptions WHERE user_id = :user_id AND endpoint = :endpoint'
            );
            $stmt->execute([':user_id' => $userId, ':endpoint' => $endpoint]);
        } elseif ($deviceToken !== null) {
            $stmt = $pdo->prepare(
                'DELETE FROM vv_push_subscriptions WHERE user_id = :user_id AND device_token = :device_token'
            );
            $stmt->execute([':user_id' => $userId, ':device_token' => $deviceToken]);
        } else {
            // Remove all subscriptions for user
            $stmt = $pdo->prepare('DELETE FROM vv_push_subscriptions WHERE user_id = :user_id');
            $stmt->execute([':user_id' => $userId]);
        }

        return $stmt->rowCount() > 0;
    }

    /**
     * Felhasznalo osszes feliratkozasa.
     */
    public static function findByUserId(int $userId): array
    {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('SELECT * FROM vv_push_subscriptions WHERE user_id = :user_id');
        $stmt->execute([':user_id' => $userId]);
        return $stmt->fetchAll();
    }

    /**
     * Osszes aktiv feliratkozas (broadcast notificationhoz).
     */
    public static function findAllActive(): array
    {
        $pdo = getDbConnection();
        $stmt = $pdo->query(
            'SELECT ps.*, u.name AS user_name, u.role
             FROM vv_push_subscriptions ps
             JOIN vv_users u ON ps.user_id = u.id
             WHERE u.is_active = 1'
        );
        return $stmt->fetchAll();
    }

    /**
     * Csak admin felhasznalok feliratkozasai.
     */
    public static function findAdminSubscriptions(): array
    {
        $pdo = getDbConnection();
        $stmt = $pdo->query(
            "SELECT ps.*, u.name AS user_name
             FROM vv_push_subscriptions ps
             JOIN vv_users u ON ps.user_id = u.id
             WHERE u.is_active = 1 AND u.role = 'admin'"
        );
        return $stmt->fetchAll();
    }
}

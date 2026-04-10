<?php
/**
 * Autentikációs Middleware
 */
class Auth {

    private static ?array $currentUser = null;

    /**
     * Bearer token ellenőrzés — hívd meg a védett endpointok előtt
     */
    public static function check(): array {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

        if (empty($header) || !str_starts_with($header, 'Bearer ')) {
            Response::error('Bejelentkezés szükséges.', 401);
        }

        $token = substr($header, 7);

        if (strlen($token) < 32) {
            Response::error('Érvénytelen token.', 401);
        }

        $pdo = getDbConnection();
        $stmt = $pdo->prepare("
            SELECT u.id, u.name, u.email, u.role, u.is_active, t.id as token_id
            FROM vv_auth_tokens t
            JOIN vv_users u ON t.user_id = u.id
            WHERE t.token = ? AND t.expires_at > NOW()
            LIMIT 1
        ");
        $stmt->execute([$token]);
        $user = $stmt->fetch();

        if (!$user) {
            Response::error('Token lejárt vagy érvénytelen.', 401);
        }

        if (!$user['is_active']) {
            Response::error('A fiók inaktív.', 403);
        }

        unset($user['token_id']);
        self::$currentUser = $user;

        return $user;
    }

    /**
     * Admin jogosultság ellenőrzés
     */
    public static function requireAdmin(): array {
        $user = self::check();
        if ($user['role'] !== ROLE_ADMIN) {
            Response::error('Admin jogosultság szükséges.', 403);
        }
        return $user;
    }

    /**
     * Aktuális bejelentkezett felhasználó
     */
    public static function user(): ?array {
        return self::$currentUser;
    }

    /**
     * Aktuális user ID
     */
    public static function userId(): ?int {
        return self::$currentUser ? (int) self::$currentUser['id'] : null;
    }
}

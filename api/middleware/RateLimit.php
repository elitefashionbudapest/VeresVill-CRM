<?php
/**
 * Rate Limiting Middleware (login brute-force védelem)
 */
class RateLimit {

    /**
     * Login kísérlet ellenőrzése
     */
    public static function checkLogin(): void {
        $ip = self::getIp();
        $pdo = getDbConnection();

        $stmt = $pdo->prepare("
            SELECT COUNT(*) as cnt
            FROM vv_login_attempts
            WHERE ip_address = ?
            AND attempted_at > DATE_SUB(NOW(), INTERVAL ? MINUTE)
        ");
        $stmt->execute([$ip, LOGIN_LOCKOUT_MINUTES]);
        $result = $stmt->fetch();

        if ($result['cnt'] >= LOGIN_MAX_ATTEMPTS) {
            Response::error(
                "Túl sok sikertelen bejelentkezés. Próbálja újra {$result['cnt']} perc múlva.",
                429
            );
        }
    }

    /**
     * Sikertelen login rögzítése
     */
    public static function recordFailedLogin(string $email = ''): void {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare(
            "INSERT INTO vv_login_attempts (ip_address, email) VALUES (?, ?)"
        );
        $stmt->execute([self::getIp(), $email]);
    }

    /**
     * Sikeres login után töröljük az IP-hez tartozó kísérleteket
     */
    public static function clearLoginAttempts(): void {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("DELETE FROM vv_login_attempts WHERE ip_address = ?");
        $stmt->execute([self::getIp()]);
    }

    /**
     * Kliens IP cím
     */
    private static function getIp(): string {
        return $_SERVER['HTTP_X_FORWARDED_FOR']
            ?? $_SERVER['HTTP_X_REAL_IP']
            ?? $_SERVER['REMOTE_ADDR']
            ?? '0.0.0.0';
    }
}

<?php
/**
 * AuthToken model - vv_auth_tokens tabla
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/constants.php';

class AuthToken
{
    /**
     * Uj token letrehozasa a felhasznalonak.
     * @return string A generalt token
     */
    public static function create(int $userId): string
    {
        $pdo = getDbConnection();
        $token = bin2hex(random_bytes(32)); // 64 karakter hex

        $stmt = $pdo->prepare(
            'INSERT INTO vv_auth_tokens (user_id, token, expires_at)
             VALUES (:user_id, :token, DATE_ADD(NOW(), INTERVAL :days DAY))'
        );
        $stmt->execute([
            ':user_id' => $userId,
            ':token'   => $token,
            ':days'    => AUTH_TOKEN_EXPIRY_DAYS,
        ]);

        return $token;
    }

    /**
     * Token keresese + felhasznalo adatai, ervenyes token eseten.
     */
    public static function findByToken(string $token): ?array
    {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare(
            'SELECT t.id AS token_id, t.user_id, t.expires_at, t.created_at AS token_created_at,
                    u.id, u.name, u.email, u.role, u.is_active
             FROM vv_auth_tokens t
             JOIN vv_users u ON t.user_id = u.id
             WHERE t.token = :token
               AND t.expires_at > NOW()
               AND u.is_active = 1'
        );
        $stmt->execute([':token' => $token]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Token torlese (kijelentkezes).
     */
    public static function delete(string $token): bool
    {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('DELETE FROM vv_auth_tokens WHERE token = :token');
        $stmt->execute([':token' => $token]);
        return $stmt->rowCount() > 0;
    }

    /**
     * Lejart tokenek torlese.
     * @return int Torolt tokenek szama
     */
    public static function deleteExpired(): int
    {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('DELETE FROM vv_auth_tokens WHERE expires_at <= NOW()');
        $stmt->execute();
        return $stmt->rowCount();
    }

    /**
     * Felhasznalo osszes tokenjenek torlese (pl. jelszo valtozas utan).
     */
    public static function deleteAllForUser(int $userId): void
    {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('DELETE FROM vv_auth_tokens WHERE user_id = :user_id');
        $stmt->execute([':user_id' => $userId]);
    }
}

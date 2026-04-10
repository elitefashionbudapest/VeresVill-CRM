<?php
/**
 * User model - vv_users tabla
 */

require_once __DIR__ . '/../config/database.php';

class User
{
    /**
     * Felhasznalo keresese ID alapjan.
     */
    public static function findById(int $id): ?array
    {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('SELECT * FROM vv_users WHERE id = :id');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Felhasznalo keresese email alapjan.
     */
    public static function findByEmail(string $email): ?array
    {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('SELECT * FROM vv_users WHERE email = :email');
        $stmt->execute([':email' => $email]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Osszes aktiv felhasznalo lekerese.
     */
    public static function all(): array
    {
        $pdo = getDbConnection();
        $stmt = $pdo->query('SELECT id, name, email, role, is_active, last_login, created_at FROM vv_users WHERE is_active = 1 ORDER BY name ASC');
        return $stmt->fetchAll();
    }

    /**
     * Uj felhasznalo letrehozasa.
     * @return int Az uj felhasznalo ID-ja
     */
    public static function create(string $name, string $email, string $password, string $role): int
    {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare(
            'INSERT INTO vv_users (name, email, password, role) VALUES (:name, :email, :password, :role)'
        );
        $stmt->execute([
            ':name'     => $name,
            ':email'    => $email,
            ':password' => password_hash($password, PASSWORD_BCRYPT),
            ':role'     => $role,
        ]);
        return (int) $pdo->lastInsertId();
    }

    /**
     * Felhasznalo adatainak frissitese.
     * Engedelyezett mezok: name, email, role, is_active
     */
    public static function update(int $id, array $data): bool
    {
        $allowed = ['name', 'email', 'role', 'is_active'];
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
        $sql = 'UPDATE vv_users SET ' . implode(', ', $sets) . ' WHERE id = :id';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount() > 0;
    }

    /**
     * Utolso bejelentkezes idopontjanak frissitese.
     */
    public static function updateLastLogin(int $id): void
    {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare('UPDATE vv_users SET last_login = NOW() WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }
}

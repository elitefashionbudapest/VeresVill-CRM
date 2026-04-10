<?php
/**
 * UserController - Felhasználók kezelése (admin only)
 */
class UserController {

    /**
     * GET users
     * Felhasználók listázása
     */
    public function index(): void {
        $pdo = getDbConnection();

        $stmt = $pdo->prepare("
            SELECT id, name, email, role, is_active, created_at
            FROM vv_users
            ORDER BY name ASC
        ");
        $stmt->execute();
        $users = $stmt->fetchAll();

        Response::success($users);
    }

    /**
     * POST users
     * Új felhasználó létrehozása
     */
    public function store(array $input = []): void {
        $pdo = getDbConnection();

        $v = new Validator($input);
        $v->required('name', 'Név')
          ->required('email', 'E-mail cím')
          ->email('email', 'E-mail cím')
          ->required('password', 'Jelszó')
          ->minLength('password', 8, 'Jelszó')
          ->required('role', 'Szerepkör')
          ->inArray('role', [ROLE_ADMIN, ROLE_WORKER], 'Szerepkör');

        if ($v->fails()) {
            Response::error($v->firstError(), 422, $v->errors());
        }

        // Email egyediség ellenőrzés
        $stmt = $pdo->prepare("SELECT id FROM vv_users WHERE email = ?");
        $stmt->execute([$v->get('email')]);
        if ($stmt->fetch()) {
            Response::error('Ez az e-mail cím már regisztrálva van.', 422);
        }

        $hashedPassword = password_hash($v->get('password'), PASSWORD_BCRYPT);

        $stmt = $pdo->prepare("
            INSERT INTO vv_users (name, email, password, role, is_active)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $v->get('name'),
            $v->get('email'),
            $hashedPassword,
            $v->get('role'),
            (int) ($input['is_active'] ?? 1),
        ]);

        $userId = (int) $pdo->lastInsertId();

        Response::success([
            'id'    => $userId,
            'name'  => $v->get('name'),
            'email' => $v->get('email'),
            'role'  => $v->get('role'),
        ], 'Felhasználó létrehozva.', 201);
    }

    /**
     * PUT users/{id}
     * Felhasználó módosítása
     */
    public function update(string $id, array $input = []): void {
        $pdo = getDbConnection();
        $id  = (int) $id;

        // Felhasználó létezés ellenőrzés
        $stmt = $pdo->prepare("SELECT id, email FROM vv_users WHERE id = ?");
        $stmt->execute([$id]);
        $existingUser = $stmt->fetch();

        if (!$existingUser) {
            Response::error('Felhasználó nem található.', 404);
        }

        $sets   = [];
        $params = [];

        if (!empty($input['name'])) {
            $sets[]   = 'name = ?';
            $params[] = trim($input['name']);
        }

        if (!empty($input['email'])) {
            $email = trim($input['email']);
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                Response::error('Érvénytelen e-mail cím formátum.', 422);
            }
            // Email egyediség (kivéve saját)
            $stmt = $pdo->prepare("SELECT id FROM vv_users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $id]);
            if ($stmt->fetch()) {
                Response::error('Ez az e-mail cím már használatban van.', 422);
            }
            $sets[]   = 'email = ?';
            $params[] = $email;
        }

        if (!empty($input['role'])) {
            $validRoles = [ROLE_ADMIN, ROLE_WORKER];
            if (!in_array($input['role'], $validRoles, true)) {
                Response::error('Érvénytelen szerepkör.', 422);
            }
            $sets[]   = 'role = ?';
            $params[] = $input['role'];
        }

        if (array_key_exists('is_active', $input)) {
            $sets[]   = 'is_active = ?';
            $params[] = (int) $input['is_active'];
        }

        if (!empty($input['password'])) {
            if (mb_strlen($input['password']) < 8) {
                Response::error('A jelszó legalább 8 karakter kell legyen.', 422);
            }
            $sets[]   = 'password = ?';
            $params[] = password_hash($input['password'], PASSWORD_BCRYPT);
        }

        if (empty($sets)) {
            Response::error('Nincs módosítandó adat.', 422);
        }

        $params[] = $id;
        $sql  = "UPDATE vv_users SET " . implode(', ', $sets) . " WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        // Frissített felhasználó visszaadása
        $stmt = $pdo->prepare("SELECT id, name, email, role, is_active, created_at FROM vv_users WHERE id = ?");
        $stmt->execute([$id]);
        $user = $stmt->fetch();

        Response::success($user, 'Felhasználó frissítve.');
    }
}

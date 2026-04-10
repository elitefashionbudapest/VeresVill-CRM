<?php
/**
 * AuthController - Bejelentkezés, kijelentkezés, profil
 */
class AuthController {

    /**
     * POST auth/login
     * Bejelentkezés email + jelszó alapján
     */
    public function login(array $input = []): void {
        // Rate limit ellenőrzés
        RateLimit::checkLogin();

        $v = new Validator($input);
        $v->required('email', 'E-mail cím')
          ->email('email', 'E-mail cím')
          ->required('password', 'Jelszó');

        if ($v->fails()) {
            Response::error($v->firstError(), 422, $v->errors());
        }

        $email    = $v->get('email');
        $password = $v->get('password');

        $pdo  = getDbConnection();
        $stmt = $pdo->prepare("
            SELECT id, name, email, password, role, is_active
            FROM vv_users
            WHERE email = ?
            LIMIT 1
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if (!$user || !password_verify($password, $user['password'])) {
            RateLimit::recordFailedLogin($email);
            Response::error('Hibás e-mail cím vagy jelszó.', 401);
        }

        if (!$user['is_active']) {
            Response::error('A fiók inaktív. Forduljon az adminisztrátorhoz.', 403);
        }

        // Sikeres login - rate limit törlés
        RateLimit::clearLoginAttempts();

        // Token generálás
        $token     = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', strtotime('+' . AUTH_TOKEN_EXPIRY_DAYS . ' days'));

        $stmt = $pdo->prepare("
            INSERT INTO vv_auth_tokens (user_id, token, expires_at)
            VALUES (?, ?, ?)
        ");
        $stmt->execute([$user['id'], $token, $expiresAt]);

        // Jelszó ne kerüljön a válaszba
        unset($user['password']);

        Response::success([
            'token' => $token,
            'user'  => $user,
        ], 'Sikeres bejelentkezés.');
    }

    /**
     * POST auth/logout
     * Kijelentkezés - token törlése
     */
    public function logout(array $input = []): void {
        $header = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        $token  = substr($header, 7); // "Bearer " eltávolítása

        $pdo  = getDbConnection();
        $stmt = $pdo->prepare("DELETE FROM vv_auth_tokens WHERE token = ?");
        $stmt->execute([$token]);

        Response::success(null, 'Sikeres kijelentkezés.');
    }

    /**
     * GET auth/me
     * Bejelentkezett felhasználó adatai
     */
    public function me(): void {
        $user = Auth::user();
        Response::success($user);
    }
}

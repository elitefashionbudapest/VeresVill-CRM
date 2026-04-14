<?php
/**
 * GoogleCalendarController — OAuth2 callback + szinkron API
 */

require_once __DIR__ . '/../services/GoogleCalendarService.php';

class GoogleCalendarController {

    /**
     * GET google/auth
     * OAuth2 bejelentkezési URL generálása
     */
    public function auth(): void {
        $user = Auth::user();
        $url = GoogleCalendarService::getAuthUrl($user['id']);
        Response::success(['url' => $url]);
    }

    /**
     * GET google/callback
     * OAuth2 callback — Google visszairányít ide a kóddal
     */
    public function callback(): void {
        $code = $_GET['code'] ?? '';
        $userId = (int) ($_GET['state'] ?? 0);
        $error = $_GET['error'] ?? '';

        if ($error) {
            $this->renderCallbackPage('Hiba', 'A Google Naptár hozzáférés meg lett tagadva.', false);
            return;
        }

        if (!$code || !$userId) {
            $this->renderCallbackPage('Hiba', 'Érvénytelen kérés.', false);
            return;
        }

        // Token beváltás
        $tokenData = GoogleCalendarService::exchangeCode($code);
        if (!$tokenData) {
            $this->renderCallbackPage('Hiba', 'Nem sikerült csatlakozni a Google Naptárhoz.', false);
            return;
        }

        // Token mentése
        GoogleCalendarService::saveToken($userId, $tokenData);

        // Első szinkron — Google események behúzása CRM-be (push megszűnt)
        $pullResult = GoogleCalendarService::syncFromGoogle($userId);

        $importedCount = $pullResult['created'] ?? 0;
        $this->renderCallbackPage(
            'Sikeres csatlakozás!',
            "Google Naptár csatlakoztatva. {$importedCount} esemény importálva.",
            true
        );
    }

    /**
     * GET google/status
     * Google Naptár kapcsolat állapota
     */
    public function status(): void {
        $user = Auth::user();
        try {
            $status = GoogleCalendarService::isConnected($user['id']);
        } catch (\Exception $e) {
            // Tábla nem létezik még — nincs Google kapcsolat
            $status = ['connected' => false, 'sync_enabled' => false, 'last_sync' => null, 'calendar_id' => 'primary'];
        }
        Response::success($status);
    }

    /**
     * POST google/disconnect
     * Google kapcsolat bontása
     */
    public function disconnect(): void {
        $user = Auth::user();
        GoogleCalendarService::disconnect($user['id']);
        Response::success(null, 'Google Naptár lecsatlakoztatva.');
    }

    /**
     * POST google/sync
     * Kézi szinkronizálás indítása
     */
    public function sync(): void {
        $user = Auth::user();

        // Kézi szinkron → teljes újraszinkron, nem csak a változások
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("UPDATE vv_google_tokens SET sync_token = NULL WHERE user_id = ?");
        $stmt->execute([$user['id']]);

        // Google → CRM (read-only pull, push megszűnt)
        $pullResult = GoogleCalendarService::syncFromGoogle($user['id']);

        if (isset($pullResult['error'])) {
            Response::error($pullResult['error'], 500);
        }

        Response::success([
            'pulled'  => $pullResult,
        ], 'Szinkronizálás kész.');
    }

    /**
     * POST google/calendar-id
     * Google naptár ID beállítása
     */
    public function setCalendarId(): void {
        $user = Auth::user();
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $calendarId = trim($input['calendar_id'] ?? 'primary');

        if (empty($calendarId)) {
            Response::error('Naptár ID megadása kötelező.', 422);
        }

        $pdo = getDbConnection();
        $stmt = $pdo->prepare("UPDATE vv_google_tokens SET calendar_id = ? WHERE user_id = ?");
        $stmt->execute([$calendarId, $user['id']]);

        Response::success(null, 'Naptár ID beállítva.');
    }

    /**
     * GET google/calendars
     * Elérhető Google naptárak listázása
     */
    public function listCalendars(): void {
        $user = Auth::user();
        $accessToken = GoogleCalendarService::getValidToken($user['id']);

        if (!$accessToken) {
            Response::error('Nincs Google kapcsolat.', 401);
        }

        $ch = curl_init('https://www.googleapis.com/calendar/v3/users/me/calendarList');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $accessToken],
            CURLOPT_TIMEOUT        => 10,
        ]);
        $response = json_decode(curl_exec($ch), true);
        curl_close($ch);

        $calendars = [];
        foreach ($response['items'] ?? [] as $cal) {
            $calendars[] = [
                'id'      => $cal['id'],
                'summary' => $cal['summary'],
                'primary' => $cal['primary'] ?? false,
            ];
        }

        Response::success($calendars);
    }

    /**
     * Callback oldal renderelése (böngészőben jelenik meg)
     */
    private function renderCallbackPage(string $title, string $message, bool $success): void {
        $icon = $success ? '&#10003;' : '&#10007;';
        $color = $success ? '#10B981' : '#EF4444';
        $bgColor = $success ? '#D1FAE5' : '#FEE2E2';
        $basePath = rtrim(env('APP_BASE_PATH', ''), '/');
        $adminUrl = $basePath . '/admin/index.php#settings';

        header('Content-Type: text/html; charset=utf-8');
        echo <<<HTML
<!DOCTYPE html>
<html lang="hu">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{$title} - VeresVill CRM</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #F1F5F9; display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 20px; }
        .card { background: #fff; border-radius: 16px; padding: 40px; max-width: 440px; width: 100%; text-align: center; box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
        .icon { width: 64px; height: 64px; border-radius: 50%; background: {$bgColor}; color: {$color}; display: flex; align-items: center; justify-content: center; font-size: 28px; margin: 0 auto 20px; }
        h1 { font-size: 22px; color: #1E293B; margin-bottom: 10px; }
        p { color: #64748B; font-size: 14px; line-height: 1.6; margin-bottom: 24px; }
        .btn { display: inline-block; background: #3B82F6; color: #fff; padding: 12px 28px; border-radius: 10px; text-decoration: none; font-weight: 600; font-size: 14px; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">{$icon}</div>
        <h1>{$title}</h1>
        <p>{$message}</p>
        <a href="{$adminUrl}" class="btn">Vissza az admin panelhez</a>
    </div>
    <script>setTimeout(() => window.close(), 5000);</script>
</body>
</html>
HTML;
        exit;
    }
}

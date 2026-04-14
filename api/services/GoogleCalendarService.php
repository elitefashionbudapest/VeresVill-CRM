<?php
/**
 * Google Calendar Service — OAuth2 + kétirányú szinkron
 * Natív PHP implementáció, nem kell Google SDK
 */

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/database.php';

class GoogleCalendarService {

    private const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';
    private const CALENDAR_API = 'https://www.googleapis.com/calendar/v3';
    private const SHEETS_API = 'https://sheets.googleapis.com/v4';
    private const SCOPES = 'https://www.googleapis.com/auth/calendar https://www.googleapis.com/auth/spreadsheets';

    // ============================================
    // OAuth2 Flow
    // ============================================

    /**
     * OAuth2 bejelentkezési URL generálása
     */
    public static function getAuthUrl(int $userId): string {
        $params = [
            'client_id'     => env('GOOGLE_CLIENT_ID'),
            'redirect_uri'  => self::getRedirectUri(),
            'response_type' => 'code',
            'scope'         => self::SCOPES,
            'access_type'   => 'offline',
            'prompt'        => 'consent',
            'state'         => $userId,
        ];
        return self::AUTH_URL . '?' . http_build_query($params);
    }

    /**
     * Redirect URI
     */
    public static function getRedirectUri(): string {
        return rtrim(env('APP_URL'), '/') . '/api/google/callback';
    }

    /**
     * Authorization code beváltása tokenekre
     */
    public static function exchangeCode(string $code): ?array {
        $response = self::httpPost(self::TOKEN_URL, [
            'code'          => $code,
            'client_id'     => env('GOOGLE_CLIENT_ID'),
            'client_secret' => env('GOOGLE_CLIENT_SECRET'),
            'redirect_uri'  => self::getRedirectUri(),
            'grant_type'    => 'authorization_code',
        ]);

        if (!$response || isset($response['error'])) {
            error_log('Google token exchange error: ' . json_encode($response));
            return null;
        }

        return $response;
    }

    /**
     * Token frissítés refresh_token-nel
     */
    public static function refreshToken(string $refreshToken): ?array {
        $response = self::httpPost(self::TOKEN_URL, [
            'refresh_token' => $refreshToken,
            'client_id'     => env('GOOGLE_CLIENT_ID'),
            'client_secret' => env('GOOGLE_CLIENT_SECRET'),
            'grant_type'    => 'refresh_token',
        ]);

        if (!$response || isset($response['error'])) {
            error_log('Google token refresh error: ' . json_encode($response));
            return null;
        }

        return $response;
    }

    /**
     * Érvényes access token lekérése (automatikus frissítéssel)
     */
    public static function getValidToken(int $userId): ?string {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("SELECT * FROM vv_google_tokens WHERE user_id = ? AND sync_enabled = 1");
        $stmt->execute([$userId]);
        $token = $stmt->fetch();

        if (!$token) return null;

        // Ha még érvényes (5 perc ráhagyással)
        if (strtotime($token['token_expires_at']) > time() + 300) {
            return $token['access_token'];
        }

        // Frissítés
        $newToken = self::refreshToken($token['refresh_token']);
        if (!$newToken) return null;

        $expiresAt = date('Y-m-d H:i:s', time() + ($newToken['expires_in'] ?? 3600));
        $stmt = $pdo->prepare("UPDATE vv_google_tokens SET access_token = ?, token_expires_at = ?, updated_at = NOW() WHERE user_id = ?");
        $stmt->execute([$newToken['access_token'], $expiresAt, $userId]);

        return $newToken['access_token'];
    }

    /**
     * Token mentése az OAuth callback után
     */
    public static function saveToken(int $userId, array $tokenData): void {
        $pdo = getDbConnection();
        $expiresAt = date('Y-m-d H:i:s', time() + ($tokenData['expires_in'] ?? 3600));

        $stmt = $pdo->prepare("SELECT id FROM vv_google_tokens WHERE user_id = ?");
        $stmt->execute([$userId]);

        if ($stmt->fetch()) {
            $stmt = $pdo->prepare("
                UPDATE vv_google_tokens
                SET access_token = ?, refresh_token = COALESCE(?, refresh_token), token_expires_at = ?, sync_enabled = 1, updated_at = NOW()
                WHERE user_id = ?
            ");
            $stmt->execute([$tokenData['access_token'], $tokenData['refresh_token'] ?? null, $expiresAt, $userId]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO vv_google_tokens (user_id, access_token, refresh_token, token_expires_at)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$userId, $tokenData['access_token'], $tokenData['refresh_token'] ?? '', $expiresAt]);
        }
    }

    /**
     * Google kapcsolat bontása
     */
    public static function disconnect(int $userId): void {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("DELETE FROM vv_google_tokens WHERE user_id = ?");
        $stmt->execute([$userId]);
    }

    /**
     * Van-e Google kapcsolat
     */
    public static function isConnected(int $userId): array {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("SELECT sync_enabled, last_sync_at, calendar_id FROM vv_google_tokens WHERE user_id = ?");
        $stmt->execute([$userId]);
        $token = $stmt->fetch();

        return [
            'connected'    => (bool) $token,
            'sync_enabled' => $token ? (bool) $token['sync_enabled'] : false,
            'last_sync'    => $token['last_sync_at'] ?? null,
            'calendar_id'  => $token['calendar_id'] ?? 'primary',
        ];
    }

    // ============================================
    // Calendar API — CRM → Google
    // ============================================

    /**
     * Esemény létrehozása Google Naptárban
     */
    public static function createEvent(int $userId, array $calendarEvent): ?string {
        $accessToken = self::getValidToken($userId);
        if (!$accessToken) return null;

        $pdo = getDbConnection();
        $stmt = $pdo->prepare("SELECT calendar_id FROM vv_google_tokens WHERE user_id = ?");
        $stmt->execute([$userId]);
        $calendarId = $stmt->fetch()['calendar_id'] ?? 'primary';

        $googleEvent = self::formatToGoogle($calendarEvent);

        $url = self::CALENDAR_API . '/calendars/' . urlencode($calendarId) . '/events';
        $response = self::httpRequest('POST', $url, $accessToken, $googleEvent);

        if ($response && isset($response['id'])) {
            // Google event ID mentése
            $stmt = $pdo->prepare("UPDATE vv_calendar_events SET google_event_id = ?, google_synced_at = NOW() WHERE id = ?");
            $stmt->execute([$response['id'], $calendarEvent['id']]);
            return $response['id'];
        }

        error_log('Google create event error: ' . json_encode($response));
        return null;
    }

    /**
     * Esemény frissítése Google Naptárban
     */
    public static function updateEvent(int $userId, array $calendarEvent): bool {
        if (empty($calendarEvent['google_event_id'])) return false;

        $accessToken = self::getValidToken($userId);
        if (!$accessToken) return false;

        $pdo = getDbConnection();
        $stmt = $pdo->prepare("SELECT calendar_id FROM vv_google_tokens WHERE user_id = ?");
        $stmt->execute([$userId]);
        $calendarId = $stmt->fetch()['calendar_id'] ?? 'primary';

        $googleEvent = self::formatToGoogle($calendarEvent);

        $url = self::CALENDAR_API . '/calendars/' . urlencode($calendarId) . '/events/' . urlencode($calendarEvent['google_event_id']);
        $response = self::httpRequest('PUT', $url, $accessToken, $googleEvent);

        if ($response && isset($response['id'])) {
            $stmt = $pdo->prepare("UPDATE vv_calendar_events SET google_synced_at = NOW() WHERE id = ?");
            $stmt->execute([$calendarEvent['id']]);
            return true;
        }

        return false;
    }

    /**
     * Esemény törlése Google Naptárból
     */
    public static function deleteEvent(int $userId, string $googleEventId): bool {
        $accessToken = self::getValidToken($userId);
        if (!$accessToken) return false;

        $pdo = getDbConnection();
        $stmt = $pdo->prepare("SELECT calendar_id FROM vv_google_tokens WHERE user_id = ?");
        $stmt->execute([$userId]);
        $calendarId = $stmt->fetch()['calendar_id'] ?? 'primary';

        $url = self::CALENDAR_API . '/calendars/' . urlencode($calendarId) . '/events/' . urlencode($googleEventId);
        self::httpRequest('DELETE', $url, $accessToken);

        return true;
    }

    // ============================================
    // Calendar API — Google → CRM
    // ============================================

    /**
     * Google naptár események szinkronizálása a CRM-be
     */
    public static function syncFromGoogle(int $userId): array {
        $accessToken = self::getValidToken($userId);
        if (!$accessToken) return ['error' => 'Nincs érvényes Google token.'];

        $pdo = getDbConnection();
        $stmt = $pdo->prepare("SELECT calendar_id, sync_token FROM vv_google_tokens WHERE user_id = ?");
        $stmt->execute([$userId]);
        $tokenRow = $stmt->fetch();
        $calendarId = $tokenRow['calendar_id'] ?? 'primary';
        $syncToken = $tokenRow['sync_token'];

        $url = self::CALENDAR_API . '/calendars/' . urlencode($calendarId) . '/events';
        $params = ['maxResults' => 100, 'singleEvents' => 'true'];

        if ($syncToken) {
            // Csak a változásokat kérjük
            $params['syncToken'] = $syncToken;
        } else {
            // Első szinkron — utolsó 30 nap + következő 90 nap
            $params['timeMin'] = date('c', strtotime('-30 days'));
            $params['timeMax'] = date('c', strtotime('+90 days'));
        }

        $response = self::httpRequest('GET', $url . '?' . http_build_query($params), $accessToken);

        if (!$response || isset($response['error'])) {
            // Ha a syncToken érvénytelen, teljes újraszinkron
            if (isset($response['error']['code']) && $response['error']['code'] == 410) {
                $stmt = $pdo->prepare("UPDATE vv_google_tokens SET sync_token = NULL WHERE user_id = ?");
                $stmt->execute([$userId]);
                return self::syncFromGoogle($userId);
            }
            return ['error' => 'Google API hiba: ' . json_encode($response['error'] ?? $response)];
        }

        $created = 0;
        $updated = 0;
        $deleted = 0;

        foreach ($response['items'] ?? [] as $gEvent) {
            // Törölt események
            if (($gEvent['status'] ?? '') === 'cancelled') {
                $stmt = $pdo->prepare("DELETE FROM vv_calendar_events WHERE google_event_id = ? AND user_id = ?");
                $stmt->execute([$gEvent['id'], $userId]);
                if ($stmt->rowCount() > 0) $deleted++;
                continue;
            }

            // Kihagyjuk a teljes napos eseményeket
            if (!isset($gEvent['start']['dateTime'])) continue;

            // Létezik-e már a CRM-ben?
            $stmt = $pdo->prepare("SELECT id FROM vv_calendar_events WHERE google_event_id = ? AND user_id = ?");
            $stmt->execute([$gEvent['id'], $userId]);
            $existing = $stmt->fetch();

            $startDt = new DateTime($gEvent['start']['dateTime']);
            $endDt = new DateTime($gEvent['end']['dateTime']);
            $eventDate = $startDt->format('Y-m-d');
            $startTime = $startDt->format('H:i:s');
            $endTime = $endDt->format('H:i:s');
            $title = $gEvent['summary'] ?? 'Google esemény';

            if ($existing) {
                // Frissítés
                $stmt = $pdo->prepare("
                    UPDATE vv_calendar_events
                    SET title = ?, event_date = ?, start_time = ?, end_time = ?, google_synced_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$title, $eventDate, $startTime, $endTime, $existing['id']]);
                $updated++;
            } else {
                // Csak ha nincs hozzákapcsolt megrendelés (azok a CRM-ből jönnek)
                $stmt = $pdo->prepare("
                    INSERT INTO vv_calendar_events (user_id, title, event_date, start_time, end_time, event_type, google_event_id, google_synced_at)
                    VALUES (?, ?, ?, ?, ?, 'block', ?, NOW())
                ");
                $stmt->execute([$userId, $title, $eventDate, $startTime, $endTime, $gEvent['id']]);
                $created++;
            }
        }

        // Sync token mentése a következő szinkronhoz
        if (isset($response['nextSyncToken'])) {
            $stmt = $pdo->prepare("UPDATE vv_google_tokens SET sync_token = ?, last_sync_at = NOW() WHERE user_id = ?");
            $stmt->execute([$response['nextSyncToken'], $userId]);
        }

        return ['created' => $created, 'updated' => $updated, 'deleted' => $deleted];
    }

    /**
     * Teljes szinkron: CRM → Google (meglévő események feltöltése)
     */
    public static function pushAllToGoogle(int $userId): array {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("
            SELECT * FROM vv_calendar_events
            WHERE user_id = ? AND google_event_id IS NULL AND event_date >= CURDATE()
        ");
        $stmt->execute([$userId]);
        $events = $stmt->fetchAll();

        $synced = 0;
        foreach ($events as $event) {
            $googleId = self::createEvent($userId, $event);
            if ($googleId) $synced++;
        }

        return ['synced' => $synced, 'total' => count($events)];
    }

    // ============================================
    // Google Sheets — elfogadott időpont export
    // ============================================

    /**
     * Elfogadott időpont sor hozzáfűzése a Google Sheet-hez.
     *
     * Oszlopok: Forrás | Állapot | Ki megy? | Cím | Név | Telefon | Ár | Határidő | Időpont? | Dátum | Idő
     */
    public static function appendAcceptedSlotRow(array $order, array $slot): bool {
        $sheetId = env('GOOGLE_SHEET_ID');
        $sheetTab = env('GOOGLE_SHEET_TAB', 'Sheet1');
        if (!$sheetId) {
            error_log('Sheets append skipped: GOOGLE_SHEET_ID nincs beallitva');
            return false;
        }

        // Tokent tetszőleges csatlakoztatott admin user-hez találhatunk
        $pdo = getDbConnection();
        $stmt = $pdo->query("SELECT user_id FROM vv_google_tokens WHERE sync_enabled = 1 ORDER BY id ASC LIMIT 1");
        $row = $stmt->fetch();
        if (!$row) {
            error_log('Sheets append skipped: nincs csatlakoztatott Google fiok');
            return false;
        }
        $userId = (int) $row['user_id'];

        $accessToken = self::getValidToken($userId);
        if (!$accessToken) {
            error_log('Sheets append skipped: nincs ervenyes access token');
            return false;
        }

        // Sor összeállítása
        $datumHu = date('Y.m.d.', strtotime($slot['slot_date']));
        $ido     = substr($slot['slot_start'], 0, 5);
        $nevEmail = trim(($order['customer_name'] ?? '') . ' ' . ($order['customer_email'] ?? ''));

        $values = [[
            'veresvill',                        // A  Forrás
            '',                                 // B  Állapot (üres)
            'Szebasztián',                      // C  Ki megy?
            'TESZT ' . ($order['customer_address'] ?? ''), // D  Cím
            $nevEmail,                          // E  Név
            $order['customer_phone'] ?? '',     // F  Telefon
            (int) ($order['quote_amount'] ?? 0),// G  Ár
            '',                                 // H  Határidő
            'Nem mozgatható',                   // I  Időpont?
            $datumHu,                           // J  Dátum
            $ido,                               // K  Idő
            '',                                 // L  Felmérés?
            '',                                 // M  Fizetett?
            '',                                 // N  Kiküldve?
            '',                                 // O  Megjegyzés
            '',                                 // P  Elszámolás?
            '',                                 // Q  Elszámolandó
            '',                                 // R  Hibakereső
            '',                                 // S
            '',                                 // T
            '',                                 // U
            'TRUE',                             // V  Változás?
        ]];

        $range = $sheetTab . '!A:V';
        $url = self::SHEETS_API . '/spreadsheets/' . urlencode($sheetId) . '/values/' . rawurlencode($range)
             . ':append?valueInputOption=USER_ENTERED&insertDataOption=INSERT_ROWS';

        $response = self::httpRequest('POST', $url, $accessToken, ['values' => $values]);

        if ($response && isset($response['updates'])) {
            return true;
        }

        error_log('Sheets append error: ' . json_encode($response));
        return false;
    }

    // ============================================
    // HELPERS
    // ============================================

    /**
     * CRM esemény → Google Calendar formátum
     */
    private static function formatToGoogle(array $event): array {
        $date = $event['event_date'];
        $start = $event['start_time'];
        $end = $event['end_time'];

        return [
            'summary'     => $event['title'],
            'description' => $event['notes'] ?? '',
            'start' => [
                'dateTime' => $date . 'T' . $start,
                'timeZone' => 'Europe/Budapest',
            ],
            'end' => [
                'dateTime' => $date . 'T' . $end,
                'timeZone' => 'Europe/Budapest',
            ],
        ];
    }

    /**
     * HTTP POST (form-encoded)
     */
    private static function httpPost(string $url, array $data): ?array {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($data),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        return $response ? json_decode($response, true) : null;
    }

    /**
     * HTTP kérés Google API-hoz (JSON)
     */
    private static function httpRequest(string $method, string $url, string $accessToken, ?array $body = null): ?array {
        $ch = curl_init($url);
        $headers = [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json',
        ];

        $opts = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => $headers,
        ];

        switch ($method) {
            case 'POST':
                $opts[CURLOPT_POST] = true;
                $opts[CURLOPT_POSTFIELDS] = json_encode($body);
                break;
            case 'PUT':
                $opts[CURLOPT_CUSTOMREQUEST] = 'PUT';
                $opts[CURLOPT_POSTFIELDS] = json_encode($body);
                break;
            case 'DELETE':
                $opts[CURLOPT_CUSTOMREQUEST] = 'DELETE';
                break;
        }

        curl_setopt_array($ch, $opts);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 204) return ['success' => true]; // DELETE returns no content
        return $response ? json_decode($response, true) : null;
    }
}

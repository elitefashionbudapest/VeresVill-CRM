<?php
/**
 * NotificationController - Push értesítés feliratkozások kezelése
 */
class NotificationController {

    /**
     * GET push/vapid-key
     * Visszaadja a VAPID public key-t a kliensnek push subscribe-hoz
     */
    public function vapidKey(): void {
        $key = env('VAPID_PUBLIC_KEY', '');
        Response::success(['public_key' => $key]);
    }

    /**
     * POST push/test
     * Teszt push értesítés küldése a jelenlegi usernek
     */
    public function testPush(): void {
        $user = Auth::user();
        require_once __DIR__ . '/../services/PushService.php';
        PushService::sendToUser(
            (int) $user['id'],
            'Teszt értesítés',
            'Ha ezt látod, a push működik.'
        );
        Response::success(null, 'Teszt értesítés elküldve.');
    }

    /**
     * POST push/subscribe
     * Push értesítés feliratkozás mentése
     */
    public function subscribe(array $input = []): void {
        $user = Auth::user();
        $pdo  = getDbConnection();

        $v = new Validator($input);
        $v->required('platform', 'Platform')
          ->inArray('platform', [PUSH_PLATFORM_WEB, PUSH_PLATFORM_IOS], 'Platform');

        if ($v->fails()) {
            Response::error($v->firstError(), 422, $v->errors());
        }

        $platform = $v->get('platform');

        if ($platform === PUSH_PLATFORM_WEB) {
            // Web push subscription (PushSubscription JSON)
            if (empty($input['subscription'])) {
                Response::error('Push subscription adatok megadása kötelező.', 422);
            }
            $subscriptionData = is_string($input['subscription'])
                ? $input['subscription']
                : json_encode($input['subscription'], JSON_UNESCAPED_UNICODE);
        } elseif ($platform === PUSH_PLATFORM_IOS) {
            // iOS device token
            if (empty($input['device_token'])) {
                Response::error('Device token megadása kötelező.', 422);
            }
            $subscriptionData = $input['device_token'];
        } else {
            $subscriptionData = '';
        }

        // Meglévő feliratkozás frissítése vagy új létrehozása
        $stmt = $pdo->prepare("
            SELECT id FROM vv_push_subscriptions
            WHERE user_id = ? AND platform = ?
            LIMIT 1
        ");
        $stmt->execute([$user['id'], $platform]);
        $existing = $stmt->fetch();

        if ($existing) {
            $stmt = $pdo->prepare("
                UPDATE vv_push_subscriptions
                SET subscription_data = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$subscriptionData, $existing['id']]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO vv_push_subscriptions (user_id, platform, subscription_data)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$user['id'], $platform, $subscriptionData]);
        }

        Response::success(null, 'Push értesítés feliratkozás mentve.');
    }

    /**
     * DELETE push/subscribe
     * Push értesítés leiratkozás
     */
    public function unsubscribe(array $input = []): void {
        $user = Auth::user();
        $pdo  = getDbConnection();

        $platform = $input['platform'] ?? '';

        $sql    = "DELETE FROM vv_push_subscriptions WHERE user_id = ?";
        $params = [$user['id']];

        if ($platform !== '' && in_array($platform, [PUSH_PLATFORM_WEB, PUSH_PLATFORM_IOS], true)) {
            $sql     .= " AND platform = ?";
            $params[] = $platform;
        }

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        Response::success(null, 'Push értesítés leiratkozás sikeres.');
    }
}

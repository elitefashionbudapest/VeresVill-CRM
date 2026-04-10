<?php
/**
 * Push notification szolgáltatás
 *
 * Web Push (VAPID) implementáció natív PHP openssl-lel.
 * MEGJEGYZES: A VAPID/Web Push kriptográfia komplex. Ha a natív openssl
 * nem működik megfelelően a shared hosting-on, a web-push-php könyvtár
 * (minishlink/web-push) egy robosztusabb alternatíva.
 */

require_once __DIR__ . '/../config/env.php';
require_once __DIR__ . '/../config/database.php';

class PushService
{
    /**
     * Push értesítés küldése minden admin felhasználónak
     */
    public static function notifyAdmins(string $title, string $body, ?array $data = null): void
    {
        $vapidPublic = env('VAPID_PUBLIC_KEY');
        $vapidPrivate = env('VAPID_PRIVATE_KEY');

        if (empty($vapidPublic) || empty($vapidPrivate)) {
            error_log('PushService: Push not configured - VAPID keys are empty');
            return;
        }

        try {
            $pdo = getDbConnection();

            // Minden admin felhasználó push subscription-jeit lekérjük
            $stmt = $pdo->query("
                SELECT ps.endpoint, ps.p256dh_key, ps.auth_key
                FROM vv_push_subscriptions ps
                JOIN vv_users u ON u.id = ps.user_id
                WHERE u.role = 'admin'
                  AND ps.is_active = 1
            ");

            $payload = json_encode([
                'title' => $title,
                'body'  => $body,
                'data'  => $data,
            ], JSON_UNESCAPED_UNICODE);

            while ($sub = $stmt->fetch()) {
                try {
                    self::sendWebPush([
                        'endpoint' => $sub['endpoint'],
                        'p256dh'   => $sub['p256dh_key'],
                        'auth'     => $sub['auth_key'],
                    ], $payload);
                } catch (\Exception $e) {
                    error_log('PushService: Failed to send to endpoint: ' . $e->getMessage());
                }
            }
        } catch (\Exception $e) {
            error_log('PushService notifyAdmins error: ' . $e->getMessage());
            // Nem dobunk kivételt — push nem kritikus
        }
    }

    /**
     * Push értesítés küldése adott felhasználónak
     */
    public static function sendToUser(int $userId, string $title, string $body): void
    {
        $vapidPublic = env('VAPID_PUBLIC_KEY');
        $vapidPrivate = env('VAPID_PRIVATE_KEY');

        if (empty($vapidPublic) || empty($vapidPrivate)) {
            error_log('PushService: Push not configured - VAPID keys are empty');
            return;
        }

        try {
            $pdo = getDbConnection();

            $stmt = $pdo->prepare("
                SELECT endpoint, p256dh_key, auth_key
                FROM vv_push_subscriptions
                WHERE user_id = ? AND is_active = 1
            ");
            $stmt->execute([$userId]);

            $payload = json_encode([
                'title' => $title,
                'body'  => $body,
            ], JSON_UNESCAPED_UNICODE);

            while ($sub = $stmt->fetch()) {
                try {
                    self::sendWebPush([
                        'endpoint' => $sub['endpoint'],
                        'p256dh'   => $sub['p256dh_key'],
                        'auth'     => $sub['auth_key'],
                    ], $payload);
                } catch (\Exception $e) {
                    error_log('PushService: Failed to send to user ' . $userId . ': ' . $e->getMessage());
                }
            }
        } catch (\Exception $e) {
            error_log('PushService sendToUser error: ' . $e->getMessage());
        }
    }

    /**
     * Egyetlen Web Push értesítés küldése VAPID-dal
     *
     * MEGJEGYZES: Ez egy egyszerűsített implementáció. A teljes Web Push
     * titkosítás (RFC 8291 - aes128gcm) összetett. Ha ez nem működik
     * megbízhatóan, használja a minishlink/web-push könyvtárat.
     *
     * @param array  $subscription ['endpoint' => ..., 'p256dh' => ..., 'auth' => ...]
     * @param string $payload      JSON payload
     * @return bool
     */
    public static function sendWebPush(array $subscription, string $payload): bool
    {
        $vapidPublic  = env('VAPID_PUBLIC_KEY');
        $vapidPrivate = env('VAPID_PRIVATE_KEY');
        $vapidSubject = env('VAPID_SUBJECT', 'mailto:veresvill.ads@gmail.com');

        if (empty($vapidPublic) || empty($vapidPrivate)) {
            error_log('PushService: VAPID keys not configured');
            return false;
        }

        $endpoint = $subscription['endpoint'];

        // --- VAPID JWT létrehozása ---
        $parsedUrl = parse_url($endpoint);
        $audience = $parsedUrl['scheme'] . '://' . $parsedUrl['host'];

        $header = self::base64UrlEncode(json_encode([
            'typ' => 'JWT',
            'alg' => 'ES256',
        ]));

        $now = time();
        $claims = self::base64UrlEncode(json_encode([
            'aud' => $audience,
            'exp' => $now + 43200, // 12 óra
            'sub' => $vapidSubject,
        ]));

        $signingInput = $header . '.' . $claims;

        // ECDSA P-256 aláírás
        $privateKeyPem = self::vapidKeyToPem($vapidPrivate);
        if ($privateKeyPem === false) {
            error_log('PushService: Failed to convert VAPID private key to PEM');
            return false;
        }

        $privateKey = openssl_pkey_get_private($privateKeyPem);
        if ($privateKey === false) {
            error_log('PushService: Failed to load VAPID private key: ' . openssl_error_string());
            return false;
        }

        $signature = '';
        $signResult = openssl_sign($signingInput, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        if (!$signResult) {
            error_log('PushService: openssl_sign failed: ' . openssl_error_string());
            return false;
        }

        // Az openssl_sign DER formátumban ad vissza, JWT-hez R||S kell (64 byte)
        $jwtSignature = self::derToJose($signature);
        $jwt = $signingInput . '.' . self::base64UrlEncode($jwtSignature);

        // --- Payload titkosítás (aes128gcm, RFC 8291) ---
        $encrypted = self::encryptPayload(
            $payload,
            $subscription['p256dh'],
            $subscription['auth']
        );

        if ($encrypted === false) {
            error_log('PushService: Payload encryption failed');
            return false;
        }

        // --- HTTP kérés küldése ---
        $headers = [
            'Authorization: vapid t=' . $jwt . ', k=' . $vapidPublic,
            'Content-Type: application/octet-stream',
            'Content-Encoding: aes128gcm',
            'Content-Length: ' . strlen($encrypted),
            'TTL: 86400',
            'Urgency: high',
        ];

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $encrypted,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 15,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            error_log('PushService curl error: ' . $curlError);
            return false;
        }

        // 201 = Created (sikeres push), 410 = Gone (subscription expired)
        if ($httpCode === 410 || $httpCode === 404) {
            // Subscription lejárt — inaktiváljuk
            try {
                $pdo = getDbConnection();
                $stmt = $pdo->prepare("UPDATE vv_push_subscriptions SET is_active = 0 WHERE endpoint = ?");
                $stmt->execute([$endpoint]);
            } catch (\Exception $e) {
                error_log('PushService: Failed to deactivate expired subscription: ' . $e->getMessage());
            }
            return false;
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            error_log("PushService: Push endpoint returned HTTP {$httpCode}: {$response}");
            return false;
        }

        return true;
    }

    // ========================================
    // Segédfüggvények
    // ========================================

    /**
     * VAPID base64url privát kulcs konvertálása PEM formátumra
     * A VAPID privát kulcs 32 byte-os nyers EC private key (d paraméter)
     */
    private static function vapidKeyToPem(string $base64UrlKey): string|false
    {
        $rawKey = self::base64UrlDecode($base64UrlKey);
        if (strlen($rawKey) !== 32) {
            error_log('PushService: VAPID private key must be 32 bytes, got ' . strlen($rawKey));
            return false;
        }

        // EC P-256 privát kulcs DER struktúra összeállítása
        // SEQUENCE {
        //   INTEGER 1 (version)
        //   OCTET STRING (private key, 32 bytes)
        //   [0] OID prime256v1
        // }
        $der = "\x30\x41"
            . "\x02\x01\x01"                     // version = 1
            . "\x04\x20" . $rawKey                // private key (32 bytes)
            . "\xa0\x0a"                           // [0] context tag
            . "\x06\x08"                           // OID
            . "\x2a\x86\x48\xce\x3d\x03\x01\x07"; // prime256v1

        $pem = "-----BEGIN EC PRIVATE KEY-----\n"
            . chunk_split(base64_encode($der), 64, "\n")
            . "-----END EC PRIVATE KEY-----\n";

        return $pem;
    }

    /**
     * Payload titkosítás aes128gcm (RFC 8291)
     *
     * MEGJEGYZES: Ez az implementáció a Web Push titkosítás egyszerűsített
     * változata. Éles környezetben a minishlink/web-push könyvtár ajánlott.
     */
    private static function encryptPayload(string $payload, string $userPublicKey, string $userAuth): string|false
    {
        try {
            $userPublicKeyRaw = self::base64UrlDecode($userPublicKey);
            $userAuthRaw = self::base64UrlDecode($userAuth);

            // Szerver oldali ECDH kulcspár generálása
            $serverKey = openssl_pkey_new([
                'curve_name'       => 'prime256v1',
                'private_key_type' => OPENSSL_KEYTYPE_EC,
            ]);

            if ($serverKey === false) {
                error_log('PushService: Failed to generate server EC key: ' . openssl_error_string());
                return false;
            }

            $serverKeyDetails = openssl_pkey_get_details($serverKey);
            $serverPublicKeyRaw = "\x04"
                . str_pad($serverKeyDetails['ec']['x'], 32, "\x00", STR_PAD_LEFT)
                . str_pad($serverKeyDetails['ec']['y'], 32, "\x00", STR_PAD_LEFT);

            // ECDH shared secret kiszámítása
            // Az openssl_pkey_derive-hoz szükség van a user public key-re PEM formátumban
            $userPubPem = self::rawPublicKeyToPem($userPublicKeyRaw);
            if ($userPubPem === false) {
                return false;
            }

            $userPubKey = openssl_pkey_get_public($userPubPem);
            if ($userPubKey === false) {
                error_log('PushService: Failed to load user public key: ' . openssl_error_string());
                return false;
            }

            $sharedSecret = openssl_pkey_derive($serverKey, $userPubKey, 32);
            if ($sharedSecret === false) {
                // openssl_pkey_derive nem mindig elérhető
                error_log('PushService: openssl_pkey_derive failed - may need web-push-php library');
                return false;
            }

            // HKDF-alapú kulcs deriválás (RFC 8291)
            // IKM = ECDH shared secret
            // auth_info = "WebPush: info\x00" || client_public || server_public
            $authInfo = "WebPush: info\x00" . $userPublicKeyRaw . $serverPublicKeyRaw;

            // PRK = HKDF-Extract(auth_secret, shared_secret)
            $prk = hash_hmac('sha256', $sharedSecret, $userAuthRaw, true);

            // IKM for final HKDF
            $ikm = self::hkdfExpand($prk, $authInfo, 32);

            // Salt (random 16 byte)
            $salt = random_bytes(16);

            // Content encryption key and nonce derivation
            $prkFinal = hash_hmac('sha256', $ikm, $salt, true);
            $cek = self::hkdfExpand($prkFinal, "Content-Encoding: aes128gcm\x00\x01", 16);
            $nonce = self::hkdfExpand($prkFinal, "Content-Encoding: nonce\x00\x01", 12);

            // Padding hozzáadása a payload-hoz (RFC 8291: delimiter + padding)
            $paddedPayload = $payload . "\x02"; // record delimiter

            // AES-128-GCM titkosítás
            $tag = '';
            $encrypted = openssl_encrypt(
                $paddedPayload,
                'aes-128-gcm',
                $cek,
                OPENSSL_RAW_DATA,
                $nonce,
                $tag,
                '',
                16
            );

            if ($encrypted === false) {
                error_log('PushService: AES-128-GCM encryption failed: ' . openssl_error_string());
                return false;
            }

            // aes128gcm header: salt(16) || rs(4) || idlen(1) || keyid(65) || ciphertext || tag
            $rs = pack('N', 4096); // record size
            $idLen = chr(65);      // server public key length

            return $salt . $rs . $idLen . $serverPublicKeyRaw . $encrypted . $tag;

        } catch (\Exception $e) {
            error_log('PushService encryptPayload error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Nyers EC public key (65 byte, uncompressed) konvertálása PEM formátumra
     */
    private static function rawPublicKeyToPem(string $rawKey): string|false
    {
        if (strlen($rawKey) !== 65 || $rawKey[0] !== "\x04") {
            error_log('PushService: Invalid uncompressed EC public key');
            return false;
        }

        // SubjectPublicKeyInfo DER wrapper for EC P-256
        $der = "\x30\x59"                                      // SEQUENCE (89 bytes)
            . "\x30\x13"                                        // SEQUENCE (19 bytes) - AlgorithmIdentifier
            . "\x06\x07\x2a\x86\x48\xce\x3d\x02\x01"          // OID ecPublicKey
            . "\x06\x08\x2a\x86\x48\xce\x3d\x03\x01\x07"      // OID prime256v1
            . "\x03\x42\x00"                                    // BIT STRING (66 bytes, 0 unused bits)
            . $rawKey;

        $pem = "-----BEGIN PUBLIC KEY-----\n"
            . chunk_split(base64_encode($der), 64, "\n")
            . "-----END PUBLIC KEY-----\n";

        return $pem;
    }

    /**
     * HKDF-Expand (RFC 5869)
     */
    private static function hkdfExpand(string $prk, string $info, int $length): string
    {
        $t = '';
        $output = '';
        $counter = 1;

        while (strlen($output) < $length) {
            $t = hash_hmac('sha256', $t . $info . chr($counter), $prk, true);
            $output .= $t;
            $counter++;
        }

        return substr($output, 0, $length);
    }

    /**
     * DER formátumú ECDSA aláírás konvertálása JOSE formátumra (R||S, 64 byte)
     */
    private static function derToJose(string $der): string
    {
        // DER: 0x30 [len] 0x02 [r_len] [r] 0x02 [s_len] [s]
        $offset = 2; // Skip SEQUENCE tag and length

        // R érték
        $rLen = ord($der[$offset + 1]);
        $r = substr($der, $offset + 2, $rLen);
        $offset += 2 + $rLen;

        // S érték
        $sLen = ord($der[$offset + 1]);
        $s = substr($der, $offset + 2, $sLen);

        // Vezető nullák eltávolítása, 32 byte-ra pad-elés
        $r = ltrim($r, "\x00");
        $s = ltrim($s, "\x00");

        return str_pad($r, 32, "\x00", STR_PAD_LEFT)
            . str_pad($s, 32, "\x00", STR_PAD_LEFT);
    }

    /**
     * Base64url kódolás
     */
    private static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * Base64url dekódolás
     */
    private static function base64UrlDecode(string $data): string
    {
        $padded = str_pad($data, strlen($data) + (4 - strlen($data) % 4) % 4, '=');
        return base64_decode(strtr($padded, '-_', '+/'));
    }
}

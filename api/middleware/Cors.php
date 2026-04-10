<?php
/**
 * CORS Middleware
 */
class Cors {

    public static function handle(): void {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        $appUrl = env('APP_URL', 'https://veresvill.hu');

        // Megengedett origin-ek
        $allowed = [
            $appUrl,
            rtrim($appUrl, '/'),
        ];

        // Fejlesztési módban localhost is megengedett
        if (env('APP_DEBUG') === 'true') {
            $allowed[] = 'http://localhost';
            $allowed[] = 'http://localhost:3000';
            $allowed[] = 'http://127.0.0.1';
        }

        if (in_array($origin, $allowed, true)) {
            header("Access-Control-Allow-Origin: {$origin}");
        }

        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        header('Access-Control-Max-Age: 86400');

        // Preflight
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(204);
            exit;
        }
    }
}

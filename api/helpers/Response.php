<?php
/**
 * JSON response helper
 */
class Response {

    public static function json($data, int $status = 200): void {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function success($data = null, string $message = 'OK', int $status = 200): void {
        $response = ['success' => true, 'message' => $message];
        if ($data !== null) {
            $response['data'] = $data;
        }
        self::json($response, $status);
    }

    public static function error(string $message, int $status = 400, $errors = null): void {
        $response = ['success' => false, 'message' => $message];
        if ($errors !== null) {
            $response['errors'] = $errors;
        }
        self::json($response, $status);
    }

    public static function paginated(array $items, int $total, int $page, int $perPage): void {
        self::json([
            'success' => true,
            'data'    => $items,
            'meta'    => [
                'total'        => $total,
                'page'         => $page,
                'per_page'     => $perPage,
                'last_page'    => (int) ceil($total / $perPage),
            ],
        ]);
    }
}

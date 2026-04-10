<?php
/**
 * VeresVill CRM - REST API Router / Front Controller
 */

require_once __DIR__ . '/bootstrap.php';

// CORS kezelés
Cors::handle();

// Request adatok
$method = $_SERVER['REQUEST_METHOD'];
$uri    = $_SERVER['REQUEST_URI'] ?? '/';

// Query string eltávolítása
$uri = parse_url($uri, PHP_URL_PATH);

// /api/ prefix eltávolítása
$uri = preg_replace('#^/api/#', '', ltrim($uri, '/'));
$uri = rtrim($uri, '/');

// JSON input POST/PUT kérésekhez
$input = [];
if (in_array($method, ['POST', 'PUT', 'DELETE'])) {
    $rawInput = file_get_contents('php://input');
    if ($rawInput) {
        $input = json_decode($rawInput, true) ?? [];
    }
}

// --- Útvonalak definíciója ---
// Formátum: [HTTP_METHOD, regex, controller, method, auth_level]
// auth_level: 'none', 'auth', 'admin'
$routes = [
    // Auth
    ['POST',   'auth/login',                    'AuthController',         'login',           'none'],
    ['POST',   'auth/logout',                   'AuthController',         'logout',          'auth'],
    ['GET',    'auth/me',                        'AuthController',         'me',              'auth'],

    // Orders
    ['GET',    'orders',                         'OrderController',        'index',           'auth'],
    ['GET',    'orders/(\d+)',                   'OrderController',        'show',            'auth'],
    ['PUT',    'orders/(\d+)',                   'OrderController',        'update',          'admin'],
    ['PUT',    'orders/(\d+)/status',            'OrderController',        'updateStatus',    'admin'],

    // Quotes
    ['POST',   'orders/(\d+)/quote',            'QuoteController',        'sendQuote',       'admin'],
    ['GET',    'quote/view/([a-f0-9]+)',         'QuoteController',        'viewQuote',       'none'],
    ['POST',   'quote/accept/([a-f0-9]+)',       'QuoteController',        'acceptQuote',     'none'],

    // Calendar
    ['GET',    'calendar/available-slots',       'CalendarController',     'availableSlots',  'auth'],
    ['GET',    'calendar/events',                'CalendarController',     'index',           'auth'],
    ['POST',   'calendar/events',                'CalendarController',     'store',           'auth'],
    ['PUT',    'calendar/events/(\d+)',          'CalendarController',     'update',          'auth'],
    ['DELETE', 'calendar/events/(\d+)',          'CalendarController',     'destroy',         'auth'],

    // Users
    ['GET',    'users',                          'UserController',         'index',           'admin'],
    ['POST',   'users',                          'UserController',         'store',           'admin'],
    ['PUT',    'users/(\d+)',                    'UserController',         'update',          'admin'],

    // Push notifications
    ['POST',   'push/subscribe',                'NotificationController', 'subscribe',       'auth'],
    ['DELETE', 'push/subscribe',                'NotificationController', 'unsubscribe',     'auth'],

    // Dashboard
    ['GET',    'dashboard/stats',               'DashboardController',    'stats',           'auth'],
];

// --- Route matching ---
$matched = false;

foreach ($routes as $route) {
    [$routeMethod, $pattern, $controllerName, $action, $authLevel] = $route;

    if ($method !== $routeMethod) {
        continue;
    }

    $regex = '#^' . $pattern . '$#';

    if (preg_match($regex, $uri, $matches)) {
        $matched = true;

        // Auth kezelés
        switch ($authLevel) {
            case 'admin':
                Auth::requireAdmin();
                break;
            case 'auth':
                Auth::check();
                break;
            // 'none' - nincs auth szükséges
        }

        // Controller példányosítás
        $controller = new $controllerName();

        // Paraméterek kinyerése (capture groups)
        $params = array_slice($matches, 1);

        // Controller metódus hívás paraméterekkel + input
        $controller->$action(...[...$params, $input]);

        exit;
    }
}

// Ha nem találtunk route-ot
if (!$matched) {
    Response::error('Az endpoint nem található.', 404);
}

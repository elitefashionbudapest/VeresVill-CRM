<?php
/**
 * API Bootstrap - Autoload és inicializáció
 */

// Hibakezelés
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Időzóna
date_default_timezone_set('Europe/Budapest');

// Config betöltés
require_once __DIR__ . '/config/env.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/constants.php';

// Helpers
require_once __DIR__ . '/helpers/Response.php';
require_once __DIR__ . '/helpers/Validator.php';

// Middleware
require_once __DIR__ . '/middleware/Cors.php';
require_once __DIR__ . '/middleware/Auth.php';
require_once __DIR__ . '/middleware/RateLimit.php';

// Egyszerű autoloader a controllers, models, services mappákhoz
spl_autoload_register(function (string $class) {
    $dirs = [
        __DIR__ . '/controllers/',
        __DIR__ . '/models/',
        __DIR__ . '/services/',
    ];
    foreach ($dirs as $dir) {
        $file = $dir . $class . '.php';
        if (file_exists($file)) {
            require_once $file;
            return;
        }
    }
});

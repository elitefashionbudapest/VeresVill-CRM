<?php
/**
 * Adatbázis kapcsolat (PDO singleton)
 */

require_once __DIR__ . '/env.php';

function getDbConnection(): PDO {
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $host = env('DB_HOST', 'localhost');
    $name = env('DB_NAME', 'veresvill_crm');
    $user = env('DB_USER');
    $pass = env('DB_PASS');
    $charset = env('DB_CHARSET', 'utf8mb4');

    $dsn = "mysql:host={$host};dbname={$name};charset={$charset}";

    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    return $pdo;
}

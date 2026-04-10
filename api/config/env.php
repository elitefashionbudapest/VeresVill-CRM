<?php
/**
 * .env fájl betöltése
 * Egyszerű parser — nem kell Composer/vlucas/phpdotenv
 */

function loadEnv(string $path): array {
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    if (!file_exists($path)) {
        throw new RuntimeException(".env fájl nem található: {$path}");
    }

    $env = [];
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        $line = trim($line);
        // Kommentek és üres sorok kihagyása
        if ($line === '' || $line[0] === '#') {
            continue;
        }

        $eqPos = strpos($line, '=');
        if ($eqPos === false) {
            continue;
        }

        $key = trim(substr($line, 0, $eqPos));
        $value = trim(substr($line, $eqPos + 1));

        // Idézőjelek eltávolítása
        if (strlen($value) >= 2) {
            $first = $value[0];
            $last = $value[strlen($value) - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, -1);
            }
        }

        $env[$key] = $value;
    }

    $cache = $env;
    return $env;
}

/**
 * Egyetlen env érték lekérése
 */
function env(string $key, string $default = ''): string {
    static $env = null;
    if ($env === null) {
        $env = loadEnv(dirname(__DIR__, 2) . '/.env');
    }
    return $env[$key] ?? $default;
}

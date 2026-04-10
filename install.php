<?php
/**
 * VeresVill CRM - Telepítő / Diagnosztikai szkript
 * Feltöltés után futtasd: https://visualbyadam.hu/veresvill_crm/install.php
 * FONTOS: Töröld ki amikor minden működik!
 */

echo "<html><head><meta charset='UTF-8'><title>VV CRM Install</title>";
echo "<style>body{font-family:monospace;max-width:800px;margin:40px auto;padding:20px;background:#1a1a2e;color:#e0e0e0;}";
echo ".ok{color:#4CAF50;} .err{color:#FF6B6B;} .warn{color:#FF9800;} h1{color:#4A90E2;} hr{border-color:#333;}</style></head><body>";
echo "<h1>VeresVill CRM - Telepítés ellenőrzés</h1><hr>";

$errors = 0;
$basePath = __DIR__;

// 1. PHP verzió
$phpVer = phpversion();
$phpOk = version_compare($phpVer, '8.0', '>=');
echo "<p>" . ($phpOk ? "<span class='ok'>✓</span>" : "<span class='err'>✗</span>") . " PHP verzió: {$phpVer}" . ($phpOk ? "" : " (min. 8.0 szükséges!)") . "</p>";
if (!$phpOk) $errors++;

// 2. Szükséges PHP extensionök
$exts = ['pdo', 'pdo_mysql', 'openssl', 'curl', 'mbstring', 'json'];
foreach ($exts as $ext) {
    $loaded = extension_loaded($ext);
    echo "<p>" . ($loaded ? "<span class='ok'>✓</span>" : "<span class='err'>✗</span>") . " PHP extension: {$ext}</p>";
    if (!$loaded) $errors++;
}

// 3. .env fájl
echo "<hr><h2>Fájlok és jogok</h2>";
$envExists = file_exists($basePath . '/.env');
echo "<p>" . ($envExists ? "<span class='ok'>✓</span>" : "<span class='err'>✗</span>") . " .env fájl" . ($envExists ? "" : " — HIÁNYZIK! Töltsd fel a .env fájlt!") . "</p>";
if (!$envExists) $errors++;

// 4. Fontos fájlok és mappák ellenőrzése
$checkPaths = [
    'api/index.php'            => 'API router',
    'api/.htaccess'            => 'API rewrite szabályok',
    'api/bootstrap.php'        => 'API bootstrap',
    'api/config/env.php'       => 'Env config',
    'api/config/database.php'  => 'DB config',
    'api/controllers/AuthController.php' => 'Auth controller',
    'admin/index.php'          => 'Admin panel',
    'admin/login.php'          => 'Login oldal',
    'public/quote.php'         => 'Ügyfél árajánlat oldal',
    'send_mail.php'            => 'Email küldő',
    '.htaccess'                => 'Root htaccess',
];

foreach ($checkPaths as $path => $label) {
    $exists = file_exists($basePath . '/' . $path);
    echo "<p>" . ($exists ? "<span class='ok'>✓</span>" : "<span class='err'>✗</span>") . " {$label} ({$path})</p>";
    if (!$exists) $errors++;
}

// 5. Mappa jogok
echo "<hr><h2>Mappa jogosultságok</h2>";
$dirs = ['api', 'api/config', 'api/controllers', 'api/models', 'api/services', 'api/middleware', 'api/helpers', 'api/templates', 'admin', 'admin/pages', 'admin/assets', 'public', 'database', 'phpmailer/src'];

foreach ($dirs as $dir) {
    $fullPath = $basePath . '/' . $dir;
    if (!is_dir($fullPath)) {
        echo "<p><span class='err'>✗</span> {$dir}/ — MAPPA NEM LÉTEZIK</p>";
        $errors++;
        continue;
    }
    $perms = substr(sprintf('%o', fileperms($fullPath)), -4);
    $readable = is_readable($fullPath);
    $executable = is_executable($fullPath);
    $ok = $readable && $executable;
    echo "<p>" . ($ok ? "<span class='ok'>✓</span>" : "<span class='err'>✗</span>") . " {$dir}/ — jogok: {$perms}" . (!$ok ? " — <strong>JAVÍTÁS SZÜKSÉGES!</strong>" : "") . "</p>";
    if (!$ok) $errors++;
}

// 6. .htaccess mod_rewrite teszt
echo "<hr><h2>Apache modulok</h2>";
if (function_exists('apache_get_modules')) {
    $modules = apache_get_modules();
    $rewrite = in_array('mod_rewrite', $modules);
    echo "<p>" . ($rewrite ? "<span class='ok'>✓</span>" : "<span class='err'>✗</span>") . " mod_rewrite" . ($rewrite ? "" : " — SZÜKSÉGES az API működéséhez!") . "</p>";
    if (!$rewrite) $errors++;
} else {
    echo "<p><span class='warn'>?</span> mod_rewrite — nem ellenőrizhető (CGI/FPM mód), valószínűleg OK cPanel-en</p>";
}

// 7. Adatbázis kapcsolat
echo "<hr><h2>Adatbázis</h2>";
if ($envExists) {
    require_once $basePath . '/api/config/env.php';
    require_once $basePath . '/api/config/database.php';

    try {
        $pdo = getDbConnection();
        echo "<p><span class='ok'>✓</span> Adatbázis kapcsolat OK (" . env('DB_NAME') . ")</p>";

        // Táblák ellenőrzése
        $tables = ['vv_users', 'vv_orders', 'vv_time_slots', 'vv_calendar_events', 'vv_order_status_log', 'vv_push_subscriptions', 'vv_auth_tokens', 'vv_login_attempts', 'vv_settings'];
        $stmt = $pdo->query("SHOW TABLES");
        $existingTables = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $allTablesExist = true;
        foreach ($tables as $table) {
            $exists = in_array($table, $existingTables);
            echo "<p>" . ($exists ? "<span class='ok'>✓</span>" : "<span class='err'>✗</span>") . " Tábla: {$table}</p>";
            if (!$exists) { $errors++; $allTablesExist = false; }
        }

        if (!$allTablesExist) {
            echo "<p><span class='warn'>!</span> Futtasd a <strong>database/schema.sql</strong> fájlt phpMyAdmin-ban!</p>";
        }

        // Admin user
        if (in_array('vv_users', $existingTables)) {
            $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM vv_users WHERE role = 'admin'");
            $adminCount = $stmt->fetch()['cnt'];
            if ($adminCount > 0) {
                echo "<p><span class='ok'>✓</span> Admin felhasználó létezik ({$adminCount} db)</p>";
            } else {
                echo "<p><span class='err'>✗</span> Nincs admin felhasználó! Futtasd: <strong>php database/seed.php</strong></p>";
                $errors++;
            }
        }

    } catch (PDOException $e) {
        echo "<p><span class='err'>✗</span> Adatbázis hiba: " . htmlspecialchars($e->getMessage()) . "</p>";
        $errors++;
    }
} else {
    echo "<p><span class='err'>✗</span> .env hiányzik, DB teszt kihagyva</p>";
}

// 8. Jogok javítása gomb
echo "<hr><h2>Automatikus javítás</h2>";
if (isset($_GET['fix'])) {
    echo "<p>Jogok beállítása...</p>";
    $fixDirs = array_merge($dirs, ['']);
    foreach ($fixDirs as $dir) {
        $fullPath = $basePath . ($dir ? '/' . $dir : '');
        if (is_dir($fullPath)) {
            chmod($fullPath, 0755);
            echo "<p><span class='ok'>✓</span> chmod 755: {$dir}/</p>";
        }
    }
    // PHP fájlok 644
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($basePath));
    $count = 0;
    foreach ($iterator as $file) {
        if ($file->isFile() && in_array($file->getExtension(), ['php', 'html', 'css', 'js', 'sql', 'htaccess'])) {
            chmod($file->getPathname(), 0644);
            $count++;
        }
    }
    echo "<p><span class='ok'>✓</span> chmod 644: {$count} fájl</p>";
    echo "<p><span class='ok'>✓</span> .env védelem: chmod 600</p>";
    if (file_exists($basePath . '/.env')) {
        chmod($basePath . '/.env', 0600);
    }
    echo "<p><strong>Kész! <a href='install.php' style='color:#4A90E2'>Futtasd újra az ellenőrzést</a></strong></p>";
} else {
    echo "<p><a href='install.php?fix=1' style='background:#4A90E2;color:#fff;padding:10px 25px;border-radius:8px;text-decoration:none;font-weight:bold;'>Jogok automatikus javítása (chmod 755/644)</a></p>";
}

// 9. Összegzés
echo "<hr>";
if ($errors === 0) {
    echo "<h2 style='color:#4CAF50'>Minden rendben! A rendszer üzemkész.</h2>";
    echo "<p>Admin bejelentkezés: <a href='admin/login.php' style='color:#4A90E2;font-weight:bold'>admin/login.php</a></p>";
    echo "<p style='color:#FF6B6B'><strong>FONTOS: Töröld ki ezt az install.php fájlt!</strong></p>";
} else {
    echo "<h2 style='color:#FF6B6B'>{$errors} hiba található. Javítsd ki és futtasd újra!</h2>";
}

echo "</body></html>";

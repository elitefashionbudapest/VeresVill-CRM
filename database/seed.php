<?php
/**
 * Admin felhasználó létrehozása
 * Futtatás: php database/seed.php
 */

require_once __DIR__ . '/../api/config/database.php';

echo "VeresVill CRM - Admin felhasználó létrehozása\n";
echo "=============================================\n\n";

// Admin adatok - MÓDOSÍTSD FELTÖLTÉS ELŐTT!
$adminName  = 'Admin';
$adminEmail = 'admin@veresvill.hu';
$adminPass  = 'VeresvillAdmin2026!'; // Változtasd meg!

$hashedPassword = password_hash($adminPass, PASSWORD_BCRYPT);

try {
    $pdo = getDbConnection();

    // Ellenőrizzük, létezik-e már
    $check = $pdo->prepare("SELECT id FROM vv_users WHERE email = ?");
    $check->execute([$adminEmail]);

    if ($check->fetch()) {
        echo "Admin felhasználó már létezik ({$adminEmail})\n";
    } else {
        $stmt = $pdo->prepare(
            "INSERT INTO vv_users (name, email, password, role, is_active) VALUES (?, ?, ?, 'admin', 1)"
        );
        $stmt->execute([$adminName, $adminEmail, $hashedPassword]);
        echo "Admin felhasználó létrehozva!\n";
        echo "  Email: {$adminEmail}\n";
        echo "  Jelszó: {$adminPass}\n";
        echo "\n  FONTOS: Változtasd meg a jelszót bejelentkezés után!\n";
    }
} catch (PDOException $e) {
    echo "HIBA: " . $e->getMessage() . "\n";
    exit(1);
}

<?php
/**
 * VeresVill CRM - Auto Deploy
 * Git pull a szerverről — böngészőből vagy webhookkal hívható.
 *
 * Használat: https://visualbyadam.hu/veresvill_crm/deploy.php?key=TITKOSKULCS
 * FONTOS: Változtasd meg a DEPLOY_KEY értékét!
 */

// Titkos kulcs — csak ezzel lehet triggerelni
define('DEPLOY_KEY', 'vv-deploy-2026');

// Ellenőrzés
if (($_GET['key'] ?? '') !== DEPLOY_KEY) {
    http_response_code(403);
    die('Forbidden');
}

header('Content-Type: text/plain; charset=utf-8');
echo "=== VeresVill CRM Deploy ===\n\n";

$repoDir = __DIR__;

// Ha még nincs git repo, klónozzuk
if (!is_dir($repoDir . '/.git')) {
    echo "Git repo nem található, klónozás...\n";
    $output = shell_exec("cd " . escapeshellarg($repoDir) . " && git init && git remote add origin https://github.com/elitefashionbudapest/VeresVill-CRM.git && git fetch origin && git checkout -f origin/master -B master 2>&1");
    echo $output . "\n";
} else {
    echo "Git pull...\n";
    $output = shell_exec("cd " . escapeshellarg($repoDir) . " && git fetch origin && git reset --hard origin/master 2>&1");
    echo $output . "\n";
}

echo "\n--- Fájl jogok beállítása ---\n";
shell_exec("cd " . escapeshellarg($repoDir) . " && find . -type d -exec chmod 755 {} \\; 2>&1");
shell_exec("cd " . escapeshellarg($repoDir) . " && find . -type f -exec chmod 644 {} \\; 2>&1");
if (file_exists($repoDir . '/.env')) {
    chmod($repoDir . '/.env', 0600);
}
echo "Jogok OK\n";

echo "\n=== Deploy kész! ===\n";
echo "Idő: " . date('Y-m-d H:i:s') . "\n";

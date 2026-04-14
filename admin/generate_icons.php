<?php
/**
 * VV CRM - PWA ikon generátor
 * Egyszer futtatandó: https://veresvill.hu/admin/generate_icons.php?key=vv-icons-2026
 * A kigenerált PNG-k a favico/ mappába kerülnek.
 * Futtatás után toröld ki!
 */

if (($_GET['key'] ?? '') !== 'vv-icons-2026') {
    http_response_code(403);
    die('Forbidden');
}

if (!extension_loaded('gd')) {
    die('GD extension szukseges.');
}

header('Content-Type: text/plain; charset=utf-8');

$outDir = dirname(__DIR__) . '/favico';
if (!is_dir($outDir)) mkdir($outDir, 0755, true);

$sizes = [
    'android-chrome-192x192.png' => 192,
    'android-chrome-512x512.png' => 512,
    'apple-touch-icon.png'       => 180,
];

function drawVV(int $size): GdImage {
    $img = imagecreatetruecolor($size, $size);
    imagesavealpha($img, true);

    // Gradient háttér (kék -> sötétkék, diagonal)
    $top    = imagecolorallocate($img, 0x4A, 0x90, 0xE2); // #4A90E2
    $bottom = imagecolorallocate($img, 0x1E, 0x3A, 0x8A); // #1E3A8A

    for ($y = 0; $y < $size; $y++) {
        $ratio = $y / $size;
        $r = (int) (0x4A + ($ratio * (0x1E - 0x4A)));
        $g = (int) (0x90 + ($ratio * (0x3A - 0x90)));
        $b = (int) (0xE2 + ($ratio * (0x8A - 0xE2)));
        $c = imagecolorallocate($img, $r, $g, $b);
        imageline($img, 0, $y, $size, $y, $c);
    }

    // Lekerekített sarkok (maskoláshoz fekete alapot kapja a főképernyő)
    // iOS maga maskolja a touch-icon-t, úgyhogy nincs szükség rá
    // Android manifest "maskable" — 10% safe zone van

    // "VV" monogram
    $white = imagecolorallocate($img, 255, 255, 255);
    $shadowCol = imagecolorallocatealpha($img, 0, 0, 0, 90);

    // Betu magassaga kb. 55% az ikonnak
    // imagettftext-hez font kell, a GD beépített font kicsi — nagy méretnek manuálisan skálázzuk
    // Egyszerűbb: két vastag "V" alakzat rajzolás lefillelt háromszögekkel

    $midY = (int) ($size * 0.28);
    $baseY = (int) ($size * 0.78);
    $stroke = max(1, (int) ($size * 0.10));
    $letterH = $baseY - $midY;
    $letterW = (int) ($letterH * 0.82);
    $gap = (int) ($size * 0.04);

    $totalW = $letterW * 2 + $gap;
    $startX = (int) (($size - $totalW) / 2);

    drawV($img, $startX, $midY, $letterW, $letterH, $stroke, $white);
    drawV($img, $startX + $letterW + $gap, $midY, $letterW, $letterH, $stroke, $white);

    return $img;
}

/**
 * Vastag "V" betu rajzolasa polygonokkal.
 * Ket ferde vastag sav lefele, csucsuk alul talalkozik.
 */
function drawV(GdImage $img, int $x, int $y, int $w, int $h, int $stroke, int $color): void {
    $cx = $x + (int) ($w / 2); // csucs x
    $cy = $y + $h;              // csucs y

    // Bal szar: felso-bal sarok -> csucs
    $leftPoly = [
        $x,                  $y,
        $x + $stroke,        $y,
        $cx,                 $cy,
        $cx - (int)($stroke * 0.6), $cy,
    ];
    imagefilledpolygon($img, $leftPoly, $color);

    // Jobb szar: felso-jobb sarok -> csucs
    $rightPoly = [
        $x + $w - $stroke,   $y,
        $x + $w,             $y,
        $cx + (int)($stroke * 0.6), $cy,
        $cx,                 $cy,
    ];
    imagefilledpolygon($img, $rightPoly, $color);
}

echo "=== VV CRM ikon generator ===\n\n";

foreach ($sizes as $filename => $size) {
    $img = drawVV($size);
    $path = $outDir . '/' . $filename;
    imagepng($img, $path);
    imagedestroy($img);
    echo "OK: {$filename} ({$size}x{$size})\n";
}

echo "\nKesz! A favico/ mappaban lecserelve a fenti 3 fajl.\n";
echo "Torold ki ezt a scriptet hasznalat utan!\n";

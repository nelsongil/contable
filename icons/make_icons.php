<?php
/**
 * Generador de iconos PNG para la PWA.
 * Accede a esta URL UNA SOLA VEZ tras colocar tu logo en assets/logo.png
 * o deja que use el icono SVG por defecto.
 *
 * Uso: http://localhost/icons/make_icons.php
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/auth.php';

if (!extension_loaded('gd')) {
    die('Se requiere la extensión GD de PHP. Actívala en php.ini.');
}

$sizes  = [192, 512];
$errors = [];
$done   = [];

// Fuente: si hay logo PNG en assets, usarlo; si no, crear icono programático
$logoSrc = __DIR__ . '/../assets/logo.png';
$hasLogo = file_exists($logoSrc);

foreach ($sizes as $size) {
    $dest = __DIR__ . "/icon-{$size}.png";
    $canvas = imagecreatetruecolor($size, $size);
    imagesavealpha($canvas, true);

    if ($hasLogo) {
        // Redimensionar el logo existente
        $src = imagecreatefrompng($logoSrc) ?: @imagecreatefromjpeg($logoSrc);
        if (!$src) { $errors[] = "No se pudo leer assets/logo.png"; imagedestroy($canvas); continue; }
        $sw = imagesx($src); $sh = imagesy($src);
        $bg = imagecolorallocate($canvas, 67, 56, 202); // #4338CA
        imagefill($canvas, 0, 0, $bg);
        // Escalar con margen del 10%
        $margin  = (int)($size * 0.10);
        $inner   = $size - $margin * 2;
        $ratio   = min($inner / $sw, $inner / $sh);
        $dw      = (int)($sw * $ratio);
        $dh      = (int)($sh * $ratio);
        $dx      = (int)(($size - $dw) / 2);
        $dy      = (int)(($size - $dh) / 2);
        imagecopyresampled($canvas, $src, $dx, $dy, 0, 0, $dw, $dh, $sw, $sh);
        imagedestroy($src);
    } else {
        // Icono programático: fondo indigo + "€" centrado
        $bg   = imagecolorallocate($canvas, 67,  56, 202); // indigo
        $gold = imagecolorallocate($canvas, 245, 158,  11); // gold
        $white= imagecolorallocate($canvas, 255, 255, 255);
        imagefill($canvas, 0, 0, $bg);
        $font = 5; // fuente built-in GD
        $charW = imagefontwidth($font);
        $charH = imagefontheight($font);
        $text  = 'EUR';
        $textW = strlen($text) * $charW;
        $tx    = (int)(($size - $textW) / 2);
        $ty    = (int)(($size - $charH) / 2);
        imagestring($canvas, $font, $tx, $ty, $text, $gold);
    }

    if (imagepng($canvas, $dest)) {
        $done[] = "icon-{$size}.png creado correctamente.";
    } else {
        $errors[] = "No se pudo escribir icon-{$size}.png (permisos?).";
    }
    imagedestroy($canvas);
}
?>
<!DOCTYPE html>
<html lang="es"><head><meta charset="utf-8"><title>PWA Icons</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head><body class="p-4">
<h4>Generador de iconos PWA</h4>
<?php foreach ($done   as $m): ?><div class="alert alert-success py-2"><?= htmlspecialchars($m) ?></div><?php endforeach; ?>
<?php foreach ($errors as $e): ?><div class="alert alert-danger  py-2"><?= htmlspecialchars($e) ?></div><?php endforeach; ?>
<?php if ($done): ?>
<p class="text-muted mt-3">Iconos generados en <code>/icons/</code>. Puedes eliminar este archivo.</p>
<div class="d-flex gap-3 mt-2">
  <?php foreach ($sizes as $s): ?>
  <div class="text-center"><img src="icon-<?= $s ?>.png" style="width:<?= min($s,128) ?>px;border:1px solid #ccc;border-radius:8px"><br><small>icon-<?= $s ?>.png</small></div>
  <?php endforeach; ?>
</div>
<?php endif; ?>
</body></html>

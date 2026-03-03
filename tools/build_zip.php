<?php
/**
 * Script para generar el paquete ZIP de distribución del proyecto.
 * Excluye archivos locales, temporales y sensibles.
 */

// Intentar obtener la versión desde install.php
$installContent = file_get_contents(__DIR__ . '/../install.php');
$version = '1.0';
if (preg_match("/define\('APP_VERSION',\s*'([^']+)'\)/", $installContent, $m)) {
    $version = $m[1];
}

$zipName = "contable_v{$version}.zip";
$sourceDir = realpath(__DIR__ . '/../');
$zipPath = $sourceDir . '/' . $zipName;

if (file_exists($zipPath)) {
    unlink($zipPath);
}

$zip = new ZipArchive();
if ($zip->open($zipPath, ZipArchive::CREATE) !== TRUE) {
    die("No se pudo crear el archivo ZIP: $zipPath\n");
}

echo "Generando $zipName...\n";

$it = new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS);
$files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::LEAVES_ONLY);

$excludedPatterns = [
    '/config\/database\.php$/',
    '/config\/\.installed$/',
    '/\.git/',
    '/\.antigravity/',
    '/tools\//',
    '/\.zip$/',
    '/error_log$/',
    '/\.gitignore$/',
    '/\.DS_Store$/',
];

$count = 0;
foreach ($files as $name => $file) {
    if (!$file->isDir()) {
        $filePath = $file->getRealPath();
        $relativePath = substr($filePath, strlen($sourceDir) + 1);
        
        // Normalizar separadores para Windows/Unix
        $normPath = str_replace('\\', '/', $relativePath);
        
        $exclude = false;
        foreach ($excludedPatterns as $pattern) {
            if (preg_match($pattern, $normPath)) {
                $exclude = true;
                break;
            }
        }
        
        if (!$exclude) {
            $zip->addFile($filePath, $relativePath);
            $count++;
        }
    }
}

$zip->close();

echo "¡Hecho! Se han incluido $count archivos.\n";
echo "Archivo generado en: $zipPath\n";

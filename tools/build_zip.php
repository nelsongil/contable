<?php
/**
 * Script para generar el paquete ZIP de distribución del proyecto.
 * Excluye archivos locales, temporales y sensibles.
 */

// Leer versión desde el archivo VERSION (fuente única de verdad)
$versionFile = __DIR__ . '/../VERSION';
$version = file_exists($versionFile) ? trim(file_get_contents($versionFile)) : '1.5.1';

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
    // Credenciales y estado de instalación
    '/config\/database\.php$/',
    '/config\/\.installed$/',
    // Control de versiones y herramientas de desarrollo
    '/\.git\//',
    '/\.claude\//',          // Configuración local Claude Code (skills, settings)
    '/tools\//',
    // Documentación interna (no para usuarios finales)
    '/^[^\/]+\.md$/',        // .md en raíz: CLAUDE.md, AGENTS.md, CONVENTIONS.md, etc.
    // Archivos generados por el instalador (se crean durante la instalación)
    '/\.htaccess$/',
    // Carpetas del servidor del desarrollador
    '/\.well-known\//',
    '/backups\//',
    // Temporales y sistema
    '/\.zip$/',
    '/error_log$/',
    '/\.gitignore$/',
    '/\.DS_Store$/',
    '/Thumbs\.db$/',
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

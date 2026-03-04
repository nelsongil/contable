<?php
/**
 * Proceso de actualización por pasos vía AJAX
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/functions.php';

// Seguridad: Solo admin logado
if (empty($_SESSION['usuario_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'Sesión expirada o no autorizada.']);
    exit;
}

$step = get('step');
$updateData = $_SESSION['update_available'] ?? null;

// En el paso finalize el objeto $_SESSION puede haber sido alterado, pero necesitamos los datos
if (!$updateData && !in_array($step, ['finalize'])) {
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'No hay información de actualización disponible.']);
    exit;
}

$tmpDir = __DIR__ . '/../tmp/update';
$backupDir = __DIR__ . '/../backups';

if (!is_dir($tmpDir)) @mkdir($tmpDir, 0755, true);
if (!is_dir($backupDir)) @mkdir($backupDir, 0755, true);

header('Content-Type: application/json');

switch ($step) {
    case 'backup':
        try {
            $filename = 'backup_pre_update_' . date('Ymd_His') . '.sql';
            $path = $backupDir . '/' . $filename;

            $sql = generateSQLDump();
            if (file_put_contents($path, $sql) === false) {
                throw new Exception("No se pudo escribir el backup en el servidor.");
            }

            echo json_encode(['ok' => true, 'file' => $filename]);
        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'error' => 'Fallo en backup: ' . $e->getMessage()]);
        }
        break;

    case 'download':
        try {
            $url = $updateData['url'];
            $zipFile = $tmpDir . '/update.zip';

            $ch = curl_init($url);
            $fp = fopen($zipFile, 'wb');
            curl_setopt($ch, CURLOPT_FILE, $fp);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Libro-Contable-Updater/' . (defined('APP_VERSION') ? APP_VERSION : '1.0'));
            curl_setopt($ch, CURLOPT_TIMEOUT, 90);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            $res      = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr  = curl_error($ch);
            curl_close($ch);
            fclose($fp);

            if ($res === false || $curlErr) {
                throw new Exception("Error de red al descargar el paquete: $curlErr");
            }
            if ($httpCode >= 400) {
                throw new Exception("GitHub devolvió HTTP $httpCode al descargar el paquete.");
            }
            if (!file_exists($zipFile) || filesize($zipFile) < 1000) {
                throw new Exception("El archivo descargado no es válido o está incompleto.");
            }

            // Verificar cabecera PK (ZIP)
            $f = fopen($zipFile, 'rb');
            $header = fread($f, 2);
            fclose($f);
            if ($header !== 'PK') {
                throw new Exception("El archivo descargado no es un ZIP válido (cabecera incorrecta).");
            }

            echo json_encode(['ok' => true]);
        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'error' => 'Fallo en descarga: ' . $e->getMessage()]);
        }
        break;

    case 'prepare':
        try {
            $zipFile = $tmpDir . '/update.zip';
            $extractPath = $tmpDir . '/extracted';
            
            if (!file_exists($zipFile)) throw new Exception("Archivo ZIP no encontrado.");

            if (is_dir($extractPath)) rrmdir_recursive($extractPath);
            @mkdir($extractPath, 0755, true);

            $zip = new ZipArchive;
            $res = $zip->open($zipFile);
            if ($res === TRUE) {
                $zip->extractTo($extractPath);
                $zip->close();
                echo json_encode(['ok' => true]);
            } else {
                throw new Exception("No se pudo abrir el archivo ZIP (Error code: $res).");
            }
        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'error' => 'Fallo en preparación: ' . $e->getMessage()]);
        }
        break;

    case 'install':
        try {
            $extractPath = $tmpDir . '/extracted';
            $items = scandir($extractPath);
            $subFolder = '';
            foreach ($items as $item) {
                if ($item !== '.' && $item !== '..' && is_dir($extractPath . '/' . $item)) {
                    $subFolder = $item;
                    break;
                }
            }
            
            $source = $extractPath . ($subFolder ? '/' . $subFolder : '');
            $target = __DIR__ . '/..';

            if (!is_dir($source)) throw new Exception("Carpeta de origen no encontrada en el paquete.");

            // Exclusiones para no sobreescribir datos de usuario
            $exclude = [
                'config/database.php',
                'config/.installed',
                '.htaccess',
                'backups',
                'tmp',
                'assets/logo.png',
                '.git',
                '.github',
                'SECURITY.md',
                'CONVENTIONS.md'
            ];
            
            rcopy_recursive($source, $target, $exclude);

            // Migraciones SQL
            $migrationsDir = $source . '/config/migrations';
            if (is_dir($migrationsDir)) {
                $files = scandir($migrationsDir);
                sort($files);
                $db = getDB();
                foreach ($files as $f) {
                    if (pathinfo($f, PATHINFO_EXTENSION) === 'sql') {
                        $sql = file_get_contents($migrationsDir . '/' . $f);
                        if (!empty(trim($sql))) $db->exec($sql);
                    }
                }
            }

            echo json_encode(['ok' => true]);
        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'error' => 'Fallo en instalación: ' . $e->getMessage()]);
        }
        break;

    case 'finalize':
        try {
            if (!$updateData) {
                $v = defined('APP_VERSION') ? 'v'.APP_VERSION : 'v1.x';
                echo json_encode(['ok' => true, 'version' => $v]);
                exit;
            }
            
            $newVerTag = $updateData['version']; // ej: v1.3
            $newVerNum = ltrim($newVerTag, 'v');

            // Actualizar APP_VERSION en config/database.php
            $dbConfig = __DIR__ . '/../config/database.php';
            if (file_exists($dbConfig)) {
                $content = file_get_contents($dbConfig);
                $content = preg_replace("/define\('APP_VERSION',\s*'[^']+'\)/", "define('APP_VERSION', '$newVerNum')", $content);
                file_put_contents($dbConfig, $content);
            }

            // Limpiar temporales
            rrmdir_recursive($tmpDir);
            
            // Log final
            $logMsg = "[" . date('Y-m-d H:i:s') . "] Actualizado a $newVerTag\n";
            @file_put_contents($backupDir . '/update_log.txt', $logMsg, FILE_APPEND);

            unset($_SESSION['update_available']);
            echo json_encode(['ok' => true, 'version' => $newVerTag]);
        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'error' => 'Error finalizando: ' . $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(['ok' => false, 'error' => 'Paso no reconocido.']);
}

// ── Helpers ──────────────────────────────────────────────────────────────────

function rcopy_recursive($src, $dst, $exclude = [], $rel = '') {
    if (!is_dir($dst)) @mkdir($dst, 0755, true);
    $dir = opendir($src);
    while (false !== ($file = readdir($dir))) {
        if ($file === '.' || $file === '..') continue;
        
        $currentRel = $rel ? "$rel/$file" : $file;
        
        $skip = false;
        foreach ($exclude as $p) {
            if ($currentRel === $p || (is_dir($src.'/'.$file) && strpos($currentRel, $p.'/') === 0)) {
                $skip = true; break;
            }
        }
        if ($skip) continue;

        if (is_dir($src . '/' . $file)) {
            rcopy_recursive($src . '/' . $file, $dst . '/' . $file, $exclude, $currentRel);
        } else {
            @copy($src . '/' . $file, $dst . '/' . $file);
        }
    }
    closedir($dir);
}

function rrmdir_recursive($dir) {
    if (is_dir($dir)) {
        $objects = scandir($dir);
        foreach ($objects as $obj) {
            if ($obj != "." && $obj != "..") {
                if (is_dir($dir . "/" . $obj)) rrmdir_recursive($dir . "/" . $obj);
                else unlink($dir . "/" . $obj);
            }
        }
        rmdir($dir);
    }
}

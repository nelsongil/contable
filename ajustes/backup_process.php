<?php
/**
 * Lógica de Backup (Exportar/Importar/Eliminar/Auto)
 * Libro Contable v1.2
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/functions.php';

// Seguridad: Solo admin logado
if (empty($_SESSION['usuario_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'Sesión no autorizada.']);
    exit;
}

$action = get('action');
$backupDir = __DIR__ . '/../backups';
if (!is_dir($backupDir)) @mkdir($backupDir, 0755, true);

header('Content-Type: application/json');

switch ($action) {
    case 'export':
        try {
            $filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
            $path = $backupDir . '/' . $filename;
            
            $sql = generateSQLDump();
            if (file_put_contents($path, $sql) === false) {
                throw new Exception("No se pudo escribir el archivo en el servidor.");
            }

            echo json_encode([
                'ok' => true, 
                'filename' => $filename,
                'size' => formatBytes(filesize($path)),
                'date' => date('d/m/Y H:i')
            ]);
        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        break;

    case 'list':
        $files = glob($backupDir . '/*.sql');
        usort($files, fn($a, $b) => filemtime($b) - filemtime($a)); // Más nuevos primero
        
        $list = [];
        foreach (array_slice($files, 0, 10) as $f) {
            $list[] = [
                'name' => basename($f),
                'size' => formatBytes(filesize($f)),
                'date' => date('d/m/Y H:i', filemtime($f)),
                'time' => filemtime($f)
            ];
        }
        echo json_encode(['ok' => true, 'backups' => $list]);
        break;

    case 'delete':
        $file = get('file');
        $path = realpath($backupDir . '/' . $file);
        
        // Validar que el archivo esté en la carpeta permitida
        if ($path && str_starts_with($path, realpath($backupDir)) && str_ends_with($path, '.sql')) {
            unlink($path);
            echo json_encode(['ok' => true]);
        } else {
            echo json_encode(['ok' => false, 'error' => 'Archivo no válido.']);
        }
        break;

    case 'restore':
        $file = get('file');
        $confirm = post('confirm');
        $path = realpath($backupDir . '/' . $file);

        if ($confirm !== 'CONFIRMAR') {
            echo json_encode(['ok' => false, 'error' => 'Debes escribir CONFIRMAR correctamente.']);
            exit;
        }

        if ($path && str_starts_with($path, realpath($backupDir)) && str_ends_with($path, '.sql')) {
            try {
                $sql = file_get_contents($path);
                $db = getDB();
                
                $db->beginTransaction();
                $db->exec("SET FOREIGN_KEY_CHECKS=0;");
                
                // Dividir el SQL por punto y coma (rudimentario pero efectivo para backups sencillos)
                // Usamos un regex para no dividir dentro de strings. Mejor: ejecutar el bloque completo si PDO lo permite.
                $db->exec($sql);
                
                $db->exec("SET FOREIGN_KEY_CHECKS=1;");
                $db->commit();
                
                // Limpiar sesiones tras restaurar ya que los datos de usuario pueden haber cambiado
                session_destroy();
                echo json_encode(['ok' => true]);
            } catch (Exception $e) {
                if ($db->inTransaction()) $db->rollBack();
                echo json_encode(['ok' => false, 'error' => 'Fallo al restaurar: ' . $e->getMessage()]);
            }
        } else {
            echo json_encode(['ok' => false, 'error' => 'Archivo de backup no encontrado.']);
        }
        break;

    case 'auto_check':
        // Check silencioso para el Weekly Backup
        $auto = getConfig('backup_auto', '0');
        if ($auto !== '1') {
            echo json_encode(['ok' => true, 'msg' => 'Auto-backup desactivado.']);
            exit;
        }

        $last = (int)getConfig('ultimo_backup_auto', 0);
        $week = 7 * 24 * 3600;

        if (time() - $last > $week) {
            try {
                $filename = 'backup_auto_' . date('Y-m-d_H-i-s') . '.sql';
                $path = $backupDir . '/' . $filename;
                $sql = generateSQLDump();
                file_put_contents($path, $sql);
                
                setConfig('ultimo_backup_auto', time());
                
                // Rotación: Máximo 4 automáticos
                $autoFiles = glob($backupDir . '/backup_auto_*.sql');
                usort($autoFiles, fn($a, $b) => filemtime($a) - filemtime($b)); // Más viejos primero
                while (count($autoFiles) > 4) {
                    $old = array_shift($autoFiles);
                    unlink($old);
                }
                
                echo json_encode(['ok' => true, 'msg' => 'Auto-backup realizado.']);
            } catch (Exception $e) {
                echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
            }
        } else {
            echo json_encode(['ok' => true, 'msg' => 'Aún no toca backup auto.']);
        }
        break;

    case 'set_config':
        $key = get('key');
        $val = get('val');
        if (in_array($key, ['backup_auto'])) {
            setConfig($key, $val);
            echo json_encode(['ok' => true]);
        } else {
            echo json_encode(['ok' => false, 'error' => 'Clave no permitida.']);
        }
        break;

    default:
        echo json_encode(['ok' => false, 'error' => 'Acción no reconocida.']);
}

/**
 * Genera el volcado SQL completo usando PDO
 */
function generateSQLDump() {
    $db = getDB();
    $tables = [];
    $res = $db->query("SHOW TABLES");
    while ($r = $res->fetch(PDO::FETCH_NUM)) {
        if ($r[0] !== 'sessions') $tables[] = $r[0];
    }

    $out = "-- Backup Libro Contable — v" . APP_VERSION . " — " . date('Y-m-d H:i:s') . "\n";
    $out .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

    foreach ($tables as $t) {
        $create = $db->query("SHOW CREATE TABLE `$t`")->fetch(PDO::FETCH_NUM);
        $out .= "DROP TABLE IF EXISTS `$t`;\n" . $create[1] . ";\n\n";
        
        $rows = $db->query("SELECT * FROM `$t`")->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
            $cols = implode("`, `", array_keys($row));
            $vals = array_map(fn($v) => $v === null ? 'NULL' : $db->quote($v), array_values($row));
            $out .= "INSERT INTO `$t` (`$cols`) VALUES (" . implode(", ", $vals) . ");\n";
        }
        $out .= "\n";
    }
    $out .= "SET FOREIGN_KEY_CHECKS=1;\n";
    return $out;
}

function formatBytes($bytes, $precision = 2) {
    if ($bytes <= 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= pow(1024, $pow);
    return round($bytes, $precision) . ' ' . $units[$pow];
}

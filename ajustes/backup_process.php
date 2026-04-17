<?php
/**
 * Lógica de Backup — Libro Contable
 * Acciones: export | list | download | delete | restore | auto_check | set_config
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/functions.php';

// Seguridad: solo usuario autenticado
if (empty($_SESSION['usuario_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'Sesión no autorizada.']);
    exit;
}

$action    = get('action');
$backupDir = __DIR__ . '/../backups';
if (!is_dir($backupDir)) @mkdir($backupDir, 0755, true);

switch ($action) {

    // ── Generar backup ZIP ────────────────────────────────────────────────────
    case 'export':
        header('Content-Type: application/json');
        try {
            $filename = 'backup_' . date('Y-m-d_H-i-s') . '.zip';
            $path     = $backupDir . '/' . $filename;

            generateBackupZip($path);

            // Rotar manuales (incluye .zip y .sql legacy)
            $maxManuales  = max(1, (int)getConfig('backup_max_manuales', 10));
            $todosBackups = array_merge(
                glob($backupDir . '/backup_*.zip') ?: [],
                glob($backupDir . '/backup_*.sql') ?: []
            );
            $manuales = array_values(array_filter(
                $todosBackups,
                fn($f) => !str_contains(basename($f), '_auto_')
            ));
            usort($manuales, fn($a, $b) => filemtime($a) - filemtime($b));
            while (count($manuales) > $maxManuales) {
                @unlink(array_shift($manuales));
            }

            echo json_encode([
                'ok'       => true,
                'filename' => $filename,
                'size'     => formatBackupBytes(filesize($path)),
                'date'     => date('d/m/Y H:i'),
            ]);
        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        break;

    // ── Listar backups disponibles (.zip + .sql legacy) ───────────────────────
    case 'list':
        header('Content-Type: application/json');
        $files = array_merge(
            glob($backupDir . '/*.zip') ?: [],
            glob($backupDir . '/*.sql') ?: []
        );
        usort($files, fn($a, $b) => filemtime($b) - filemtime($a));

        $list = [];
        foreach (array_slice($files, 0, 20) as $f) {
            $list[] = [
                'name' => basename($f),
                'size' => formatBackupBytes(filesize($f)),
                'date' => date('d/m/Y H:i', filemtime($f)),
                'time' => filemtime($f),
                'type' => str_ends_with($f, '.zip') ? 'zip' : 'sql',
            ];
        }
        echo json_encode(['ok' => true, 'backups' => $list]);
        break;

    // ── Descargar backup ──────────────────────────────────────────────────────
    // Nunca expone la ruta física — sirve el archivo vía PHP con headers seguros
    case 'download':
        $file = basename(get('file'));   // basename() previene path traversal
        $path = realpath($backupDir . '/' . $file);

        if (!$path
            || !str_starts_with($path, realpath($backupDir))
            || (!str_ends_with($path, '.zip') && !str_ends_with($path, '.sql'))
        ) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => 'Archivo no válido.']);
            exit;
        }

        $mime = str_ends_with($path, '.zip') ? 'application/zip' : 'application/octet-stream';
        header('Content-Type: ' . $mime);
        header('Content-Disposition: attachment; filename="' . $file . '"');
        header('Content-Length: ' . filesize($path));
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');
        readfile($path);
        exit;

    // ── Eliminar backup ───────────────────────────────────────────────────────
    case 'delete':
        header('Content-Type: application/json');
        $file = basename(get('file'));
        $path = realpath($backupDir . '/' . $file);

        if ($path
            && str_starts_with($path, realpath($backupDir))
            && (str_ends_with($path, '.zip') || str_ends_with($path, '.sql'))
        ) {
            unlink($path);
            echo json_encode(['ok' => true]);
        } else {
            echo json_encode(['ok' => false, 'error' => 'Archivo no válido.']);
        }
        break;

    // ── Restaurar backup ──────────────────────────────────────────────────────
    // Operación destructiva: requiere CSRF + confirmación explícita
    case 'restore':
        header('Content-Type: application/json');
        csrfVerify(true);   // falla con JSON si el token no es válido

        $file    = basename(get('file'));
        $confirm = post('confirm');
        $path    = realpath($backupDir . '/' . $file);

        if ($confirm !== 'CONFIRMAR') {
            echo json_encode(['ok' => false, 'error' => 'Debes escribir CONFIRMAR correctamente.']);
            exit;
        }

        if (!$path
            || !str_starts_with($path, realpath($backupDir))
            || (!str_ends_with($path, '.zip') && !str_ends_with($path, '.sql'))
        ) {
            echo json_encode(['ok' => false, 'error' => 'Archivo de backup no encontrado.']);
            exit;
        }

        try {
            $db = getDB();
            // SET FOREIGN_KEY_CHECKS fuera de transacción — los DDL causan commit
            // implícito en MariaDB/MySQL, por lo que la transacción no aporta aquí
            $db->exec("SET FOREIGN_KEY_CHECKS=0;");

            if (str_ends_with($path, '.zip')) {
                // ── Formato ZIP (nuevo) ───────────────────────────────────
                $zip = new ZipArchive();
                if ($zip->open($path) !== true) {
                    throw new Exception('No se pudo abrir el archivo ZIP.');
                }

                $sqlContent = $zip->getFromName('database.sql');
                if ($sqlContent === false) {
                    $zip->close();
                    throw new Exception('El archivo ZIP no contiene database.sql.');
                }

                $logoContent = $zip->getFromName('assets/logo.png');
                $zip->close();

                $db->exec($sqlContent);

                if ($logoContent !== false) {
                    $logoDir = __DIR__ . '/../assets/';
                    if (!is_dir($logoDir)) @mkdir($logoDir, 0755, true);
                    file_put_contents($logoDir . 'logo.png', $logoContent);
                }
            } else {
                // ── Formato SQL legacy ────────────────────────────────────
                $db->exec(file_get_contents($path));
            }

            $db->exec("SET FOREIGN_KEY_CHECKS=1;");
            session_destroy();
            echo json_encode(['ok' => true]);
        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'error' => 'Fallo al restaurar: ' . $e->getMessage()]);
        }
        break;

    // ── Check auto-backup semanal ─────────────────────────────────────────────
    case 'auto_check':
        header('Content-Type: application/json');
        if (!(bool)getConfig('backup_auto', false)) {
            echo json_encode(['ok' => true, 'msg' => 'Auto-backup desactivado.']);
            exit;
        }

        $last = (int)getConfig('ultimo_backup_auto', 0);
        if (time() - $last > 7 * 24 * 3600) {
            try {
                $filename = 'backup_auto_' . date('Y-m-d_H-i-s') . '.zip';
                $path     = $backupDir . '/' . $filename;
                generateBackupZip($path);
                setConfig('ultimo_backup_auto', time());

                // Rotar auto-backups
                $maxAuto   = max(1, (int)getConfig('backup_max_auto', 4));
                $autoFiles = array_merge(
                    glob($backupDir . '/backup_auto_*.zip') ?: [],
                    glob($backupDir . '/backup_auto_*.sql') ?: []
                );
                usort($autoFiles, fn($a, $b) => filemtime($a) - filemtime($b));
                while (count($autoFiles) > $maxAuto) @unlink(array_shift($autoFiles));

                echo json_encode(['ok' => true, 'msg' => 'Auto-backup realizado.']);
            } catch (Exception $e) {
                echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
            }
        } else {
            echo json_encode(['ok' => true, 'msg' => 'Aún no toca backup auto.']);
        }
        break;

    // ── Guardar configuración ─────────────────────────────────────────────────
    case 'set_config':
        header('Content-Type: application/json');
        $key = get('key');
        $val = get('val');
        if (in_array($key, ['backup_auto', 'backup_max_manuales', 'backup_max_auto'])) {
            setConfig($key, $val);
            echo json_encode(['ok' => true]);
        } else {
            echo json_encode(['ok' => false, 'error' => 'Clave no permitida.']);
        }
        break;

    default:
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'Acción no reconocida.']);
}

// ── Helper local de formato de tamaño ─────────────────────────────────────────
function formatBackupBytes(int $bytes, int $precision = 2): string {
    if ($bytes <= 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB'];
    $pow   = min((int)floor(log($bytes, 1024)), count($units) - 1);
    return round($bytes / pow(1024, $pow), $precision) . ' ' . $units[$pow];
}

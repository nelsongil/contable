<?php
/**
 * Proceso de actualización por pasos vía AJAX
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/functions.php';

// Seguridad: Solo admin logado
require_once __DIR__ . '/../includes/auth.php';
if (!isAdmin()) {
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'No autorizado.']);
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

            // Detectar estructura del ZIP:
            // - GitHub zipball → carpeta raíz única (nelsongil-contable-{sha}/)
            // - ZIP personalizado → archivos directamente en la raíz (index.php visible)
            if (file_exists($extractPath . '/index.php')) {
                $source    = $extractPath;
                $subFolder = '';
            } else {
                $subFolder = '';
                foreach (array_diff(scandir($extractPath), ['.', '..']) as $item) {
                    if (is_dir($extractPath . '/' . $item)) {
                        $subFolder = $item;
                        break;
                    }
                }
                $source = $extractPath . ($subFolder ? '/' . $subFolder : '');
            }

            $target = __DIR__ . '/..';

            if (!is_dir($source) || !file_exists($source . '/index.php')) {
                throw new Exception("Carpeta de origen no encontrada en el paquete (buscado: '$source').");
            }

            // Exclusiones para no sobreescribir datos de usuario ni archivos del servidor
            $exclude = [
                'config/database.php',
                'config/.installed',
                '.htaccess',
                'backups',
                'tmp',
                'assets/logo.png',
                '.git',
                '.github',
                '.well-known',   // archivos de validación SSL del hosting (Let's Encrypt)
                'SECURITY.md',
                'CONVENTIONS.md'
            ];

            $filesCopied = 0;
            $filesFailed = [];
            rcopy_recursive($source, $target, $exclude, '', $filesCopied, $filesFailed);

            if ($filesFailed) {
                error_log('[update] Fallos al copiar archivos: ' . implode(', ', array_slice($filesFailed, 0, 20)));
            }

            // ── Migraciones SQL con control de ejecución única ────────────────
            // Cada migración se registra en migration_log al ejecutarse.
            // Las ya aplicadas con éxito se saltan en actualizaciones posteriores.
            // Un error en una migración no impide las siguientes.
            $migrationsDir    = $source . '/config/migrations';
            $migrationErrors  = [];
            $migrationsApplied = [];
            $migrationsSkipped = [];

            if (is_dir($migrationsDir)) {
                $db         = getDB();
                $newVerNum  = $updateData ? ltrim($updateData['version'], 'v') : null;

                // Garantizar que migration_log existe antes de cualquier consulta
                $db->exec("CREATE TABLE IF NOT EXISTS migration_log (
                    id            INT AUTO_INCREMENT PRIMARY KEY,
                    archivo       VARCHAR(200) NOT NULL,
                    version       VARCHAR(20)  NULL,
                    ejecutada_en  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
                    estado        ENUM('ok','error') NOT NULL DEFAULT 'ok',
                    error_detalle TEXT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

                // Conjunto de migraciones ya aplicadas con éxito (O(1) lookup)
                $applied = array_flip(
                    $db->query("SELECT archivo FROM migration_log WHERE estado='ok'")
                       ->fetchAll(PDO::FETCH_COLUMN, 0)
                );

                $files = array_values(array_filter(
                    scandir($migrationsDir),
                    fn($f) => pathinfo($f, PATHINFO_EXTENSION) === 'sql'
                ));
                sort($files); // orden cronológico por prefijo de fecha

                foreach ($files as $f) {
                    if (isset($applied[$f])) {
                        $migrationsSkipped[] = $f;
                        continue;
                    }

                    $sql = file_get_contents($migrationsDir . '/' . $f);
                    if (empty(trim($sql))) {
                        $migrationsSkipped[] = $f;
                        continue;
                    }

                    try {
                        $db->exec($sql);
                        $db->prepare(
                            "INSERT INTO migration_log (archivo, version, estado)
                             VALUES (?, ?, 'ok')"
                        )->execute([$f, $newVerNum]);
                        $migrationsApplied[] = $f;
                    } catch (PDOException $me) {
                        $err = $me->getMessage();
                        $migrationErrors[] = $f . ': ' . $err;
                        $db->prepare(
                            "INSERT INTO migration_log (archivo, version, estado, error_detalle)
                             VALUES (?, ?, 'error', ?)"
                        )->execute([$f, $newVerNum, $err]);
                    }
                }
            }

            if ($migrationErrors) {
                error_log('[update] Errores en migraciones: ' . implode(' | ', $migrationErrors));
            }

            // ── Post-migración PHP: completar schema de usuarios ──────────────
            // Ejecutar SIEMPRE (idempotente): asigna email al admin existente,
            // añade UNIQUE key y elimina columnas obsoletas (username, password).
            $postMigLog = [];
            try {
                $colCheck = $db->query("SHOW COLUMNS FROM usuarios LIKE 'email'")->fetch();
                if ($colCheck) {
                    // Asignar email desde constante EMPRESA_EMAIL si la fila no tiene email
                    if (defined('EMPRESA_EMAIL') && EMPRESA_EMAIL) {
                        $stEmail = $db->prepare(
                            "UPDATE usuarios SET email = ?
                             WHERE (email IS NULL OR email = '') AND rol = 'admin'
                             LIMIT 1"
                        );
                        $stEmail->execute([EMPRESA_EMAIL]);
                        if ($stEmail->rowCount() > 0) $postMigLog[] = 'email admin asignado';
                    }
                    // Añadir índice UNIQUE en email (idempotente)
                    try {
                        $db->exec("ALTER TABLE usuarios ADD UNIQUE KEY uk_usuarios_email (email)");
                        $postMigLog[] = 'UNIQUE key email creada';
                    } catch (PDOException) { /* ya existe */ }
                    // Eliminar columnas obsoletas
                    $dropped = [];
                    foreach (['username', 'password'] as $col) {
                        try {
                            $db->exec("ALTER TABLE usuarios DROP COLUMN IF EXISTS `{$col}`");
                            $dropped[] = $col;
                        } catch (PDOException) {}
                    }
                    if ($dropped) $postMigLog[] = 'columnas eliminadas: ' . implode(', ', $dropped);
                }
            } catch (PDOException $pe) {
                $postMigLog[] = 'error post-mig: ' . $pe->getMessage();
                error_log('[update] Post-migración usuarios: ' . $pe->getMessage());
            }

            echo json_encode([
                'ok'         => true,
                'files'      => [
                    'copied'     => $filesCopied,
                    'failed'     => count($filesFailed),
                    'failed_list'=> array_slice($filesFailed, 0, 10), // primeros 10 para diagnóstico
                    'subfolder'  => $subFolder ?: '(raíz)',
                ],
                'migrations' => [
                    'applied' => $migrationsApplied,
                    'skipped' => $migrationsSkipped,
                    'errors'  => $migrationErrors,
                ],
            ]);
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
            
            $newVerTag = $updateData['version']; // ej: v1.5.1
            $newVerNum = ltrim($newVerTag, 'v');

            // Fuente única de verdad: escribir /VERSION
            file_put_contents(__DIR__ . '/../VERSION', $newVerNum);

            // Compatibilidad retroactiva: actualizar define en config/database.php si tiene APP_VERSION hardcodeada
            $dbConfig = __DIR__ . '/../config/database.php';
            if (file_exists($dbConfig)) {
                $content = file_get_contents($dbConfig);
                $updated = preg_replace("/define\('APP_VERSION',\s*'[^']+'\)/", "define('APP_VERSION', '$newVerNum')", $content);
                if ($updated !== $content) {
                    file_put_contents($dbConfig, $updated);
                }
            }

            // Limpiar temporales
            rrmdir_recursive($tmpDir);

            // Invalidar OPcache para que PHP ejecute los archivos recién copiados
            $opcacheReset = false;
            if (function_exists('opcache_reset')) {
                $opcacheReset = opcache_reset();
            }

            // Log final
            $logMsg = "[" . date('Y-m-d H:i:s') . "] Actualizado a $newVerTag"
                    . ($opcacheReset ? " (OPcache invalidado)" : "") . "\n";
            @file_put_contents($backupDir . '/update_log.txt', $logMsg, FILE_APPEND);

            unset($_SESSION['update_available']);
            echo json_encode(['ok' => true, 'version' => $newVerTag, 'opcache_reset' => $opcacheReset]);
        } catch (Exception $e) {
            echo json_encode(['ok' => false, 'error' => 'Error finalizando: ' . $e->getMessage()]);
        }
        break;

    default:
        echo json_encode(['ok' => false, 'error' => 'Paso no reconocido.']);
}

// ── Helpers ──────────────────────────────────────────────────────────────────

function rcopy_recursive($src, $dst, $exclude = [], $rel = '', &$copied = 0, &$failed = []) {
    if (!is_dir($dst)) mkdir($dst, 0755, true);
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
            rcopy_recursive($src . '/' . $file, $dst . '/' . $file, $exclude, $currentRel, $copied, $failed);
        } else {
            if (copy($src . '/' . $file, $dst . '/' . $file)) {
                $copied++;
            } else {
                $failed[] = $currentRel;
            }
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

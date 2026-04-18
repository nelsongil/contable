<?php
require_once __DIR__ . '/../config/database.php';

// ─── Configuración ──────────────────────────────────────────
function getConfig(string $key, $default = null) {
    static $cache = [];
    if (isset($cache[$key])) return $cache[$key];

    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT valor FROM configuracion WHERE clave = ?");
        $stmt->execute([$key]);
        $row = $stmt->fetch();
        
        $value = $row ? $row['valor'] : $default;
        
        // Conversión manual de tipos básicos
        if ($value === 'true' || $value === '1' || $value === true)  $value = true;
        elseif ($value === 'false' || $value === '0' || $value === false) $value = false;
        
        $cache[$key] = $value;
        return $value;
    } catch (Exception $e) {
        return $default;
    }
}

function setConfig(string $key, $value): bool {
    if (is_bool($value)) $value = $value ? 'true' : 'false';
    try {
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO configuracion (clave, valor) VALUES (?, ?) 
                              ON DUPLICATE KEY UPDATE valor = VALUES(valor)");
        return $stmt->execute([$key, $value]);
    } catch (Exception $e) {
        return false;
    }
}

function getThemeCSS(): string {
    $colors = [
        '--verde'    => getConfig('theme_color_primary', '#1A2E2A'),
        '--verde-m'  => getConfig('theme_color_medium',  '#2D5245'),
        '--verde-a'  => getConfig('theme_color_accent',  '#3E7B64'),
        '--gold'     => getConfig('theme_color_gold',    '#C9A84C'),
        '--bg'       => getConfig('theme_color_bg',      '#F4F7F5'),
    ];
    
    $css = "\n<style>\n:root {\n";
    foreach ($colors as $var => $val) {
        $css .= "  $var: $val !important;\n";
    }
    $css .= "}\n</style>\n";
    return $css;
}

// ─── Seguridad básica ────────────────────────────────────────
function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}
function post(string $k, $default = ''): string {
    return isset($_POST[$k]) ? trim($_POST[$k]) : $default;
}
function get(string $k, $default = ''): string {
    return isset($_GET[$k]) ? trim($_GET[$k]) : $default;
}

// ─── Formato moneda ──────────────────────────────────────────
function money(float $v): string {
    return number_format($v, 2, ',', '.') . ' €';
}
function moneyInput(float $v): string {
    return number_format($v, 2, '.', '');
}

// ─── Trimestre de una fecha ──────────────────────────────────
function trimestre(string $fecha): int {
    $m = (int)date('n', strtotime($fecha));
    return (int)ceil($m / 3);
}

// ─── Siguiente número de factura ─────────────────────────────
// Usa LAST_INSERT_ID(expr) para un incremento atómico sin race condition.
// Compatible con instalaciones existentes: inicializa numeracion.ultimo
// a partir de factura_proximo (configuracion) en el primer uso del año.
function siguienteNumeroFactura(): string {
    $pref    = getConfig('factura_prefijo', 'F');
    $usaAnio = getConfig('factura_usa_anio', true);
    $digitos = (int)getConfig('factura_digitos', 5);
    $anio    = (int)date('Y');
    $db      = getDB();

    // Primer uso del año: inicializar desde factura_proximo si existe
    $proximo = (int)getConfig('factura_proximo', 1);
    $inicio  = max(0, $proximo - 1);
    $db->prepare("INSERT IGNORE INTO numeracion (anio, ultimo) VALUES (?, ?)")
       ->execute([$anio, $inicio]);

    // Incremento atómico — LAST_INSERT_ID(expr) es scoped a la conexión
    $db->prepare("UPDATE numeracion SET ultimo = LAST_INSERT_ID(ultimo + 1) WHERE anio = ?")
       ->execute([$anio]);
    $ultimo = (int)$db->query("SELECT LAST_INSERT_ID()")->fetchColumn();

    $anioStr   = $usaAnio ? (string)$anio : '';
    $numeroStr = str_pad($ultimo, $digitos, '0', STR_PAD_LEFT);
    return $pref . $anioStr . $numeroStr;
}

// ─── CSRF ────────────────────────────────────────────────────
function csrfToken(): string {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrfField(): string {
    return '<input type="hidden" name="csrf_token" value="' . csrfToken() . '">';
}

// $ajaxMode=true: devuelve JSON de error en lugar de redirigir (para endpoints AJAX)
function csrfVerify(bool $ajaxMode = false): void {
    $provided = $_POST['csrf_token'] ?? '';
    if (hash_equals(csrfToken(), $provided)) return;

    if ($ajaxMode) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'Sesión caducada. Recarga la página.']);
        exit;
    }
    flash('Petición inválida. Vuelve a intentarlo.', 'error');
    $back = $_SERVER['HTTP_REFERER'] ?? '/';
    header('Location: ' . $back);
    exit;
}

// ─── Audit trail ─────────────────────────────────────────────
// Registra operaciones financieras. Nunca lanza excepción — el audit
// no debe interrumpir la operación principal.
function auditLog(
    string  $tabla,
    ?int    $registroId,
    string  $accion,
    ?array  $antes    = null,
    ?array  $despues  = null
): void {
    try {
        $db = getDB();
        $db->prepare(
            "INSERT INTO auditoria
             (tabla, registro_id, accion, datos_antes, datos_despues, usuario, usuario_id, ip)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        )->execute([
            $tabla,
            $registroId,
            $accion,
            $antes   !== null ? json_encode($antes,   JSON_UNESCAPED_UNICODE) : null,
            $despues !== null ? json_encode($despues, JSON_UNESCAPED_UNICODE) : null,
            $_SESSION['usuario_nombre'] ?? 'sistema',
            $_SESSION['usuario_id']     ?? null,
            $_SERVER['REMOTE_ADDR']     ?? null,
        ]);
    } catch (Exception) {
        // Silencioso: el audit nunca debe romper la operación contable
    }
}

// ─── CRUD helpers ────────────────────────────────────────────
function getClientes(bool $soloActivos = true): array {
    $db  = getDB();
    $sql = "SELECT * FROM clientes" . ($soloActivos ? " WHERE activo=1" : "") . " ORDER BY nombre";
    return $db->query($sql)->fetchAll();
}
function getCliente(int $id): array|false {
    $st = getDB()->prepare("SELECT * FROM clientes WHERE id=?");
    $st->execute([$id]);
    return $st->fetch();
}
function getProveedores(bool $soloActivos = true): array {
    $db  = getDB();
    $sql = "SELECT * FROM proveedores" . ($soloActivos ? " WHERE activo=1" : "") . " ORDER BY nombre";
    return $db->query($sql)->fetchAll();
}
function getProveedor(int $id): array|false {
    $st = getDB()->prepare("SELECT * FROM proveedores WHERE id=?");
    $st->execute([$id]);
    return $st->fetch();
}

// ─── Facturas ────────────────────────────────────────────────
function getFacturasEmitidas(int $anio = 0, int $trim = 0): array {
    $db   = getDB();
    $where = ["1=1"];
    $params = [];
    if ($anio) { $where[] = "YEAR(fecha)=?"; $params[] = $anio; }
    if ($trim) { $where[] = "trimestre=?";   $params[] = $trim; }
    $sql = "SELECT fe.*, c.nombre AS cliente_nombre_actual
            FROM facturas_emitidas fe
            LEFT JOIN clientes c ON c.id = fe.cliente_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY fecha DESC, numero DESC";
    $st = $db->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}
function getFacturaEmitida(int $id): array|false {
    $st = getDB()->prepare(
        "SELECT fe.*, c.nombre AS cliente_nombre_actual, c.nif AS cliente_nif_actual
         FROM facturas_emitidas fe
         LEFT JOIN clientes c ON c.id = fe.cliente_id
         WHERE fe.id=?"
    );
    $st->execute([$id]);
    return $st->fetch();
}
function getLineasFactura(int $facturaId): array {
    $st = getDB()->prepare("SELECT * FROM facturas_emitidas_lineas WHERE factura_id=? ORDER BY orden");
    $st->execute([$facturaId]);
    return $st->fetchAll();
}
function getFacturasRecibidas(int $anio = 0, int $trim = 0, string $categoria = ''): array {
    $db     = getDB();
    $where  = ["1=1"];
    $params = [];
    if ($anio)      { $where[] = "YEAR(fr.fecha)=?";  $params[] = $anio; }
    if ($trim)      { $where[] = "fr.trimestre=?";    $params[] = $trim; }
    if ($categoria) { $where[] = "fr.categoria=?";    $params[] = $categoria; }
    $sql = "SELECT fr.*, p.nombre AS proveedor_nombre_actual
            FROM facturas_recibidas fr
            LEFT JOIN proveedores p ON p.id = fr.proveedor_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY fr.fecha DESC";
    $st = $db->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

// ─── Resumen fiscal ──────────────────────────────────────────
function resumenTrimestral(int $anio, int $trim): array {
    $db = getDB();

    // ── Ventas: totales + desglose por tipo IVA ──────────────────────────────
    $ve = $db->prepare(
        "SELECT
            COALESCE(SUM(base_imponible),0)                                              base,
            COALESCE(SUM(cuota_iva),0)                                                   iva,
            COALESCE(SUM(cuota_irpf),0)                                                  irpf,
            COALESCE(SUM(CASE WHEN porcentaje_iva=21 THEN base_imponible ELSE 0 END),0)  base_21,
            COALESCE(SUM(CASE WHEN porcentaje_iva=21 THEN cuota_iva      ELSE 0 END),0)  iva_21,
            COALESCE(SUM(CASE WHEN porcentaje_iva=10 THEN base_imponible ELSE 0 END),0)  base_10,
            COALESCE(SUM(CASE WHEN porcentaje_iva=10 THEN cuota_iva      ELSE 0 END),0)  iva_10,
            COALESCE(SUM(CASE WHEN porcentaje_iva=4  THEN base_imponible ELSE 0 END),0)  base_4,
            COALESCE(SUM(CASE WHEN porcentaje_iva=4  THEN cuota_iva      ELSE 0 END),0)  iva_4
         FROM facturas_emitidas
         WHERE YEAR(fecha)=? AND trimestre=? AND estado!='cancelada'"
    );
    $ve->execute([$anio, $trim]);
    $ventas = $ve->fetch();

    // ── Compras: totales bruto/deducible + desglose por tipo IVA ─────────────
    // IVA deducible efectivo = cuota_iva * (pct_iva_deducible / 100)
    $co = $db->prepare(
        "SELECT
            COALESCE(SUM(base_imponible),0)                                                           base,
            COALESCE(SUM(cuota_iva),0)                                                                iva_bruto,
            COALESCE(SUM(cuota_iva * pct_iva_deducible / 100),0)                                      iva_deducible,
            COALESCE(SUM(CASE WHEN porcentaje_iva=21 THEN base_imponible                ELSE 0 END),0) base_21,
            COALESCE(SUM(CASE WHEN porcentaje_iva=21 THEN cuota_iva*pct_iva_deducible/100 ELSE 0 END),0) iva_21,
            COALESCE(SUM(CASE WHEN porcentaje_iva=10 THEN base_imponible                ELSE 0 END),0) base_10,
            COALESCE(SUM(CASE WHEN porcentaje_iva=10 THEN cuota_iva*pct_iva_deducible/100 ELSE 0 END),0) iva_10,
            COALESCE(SUM(CASE WHEN porcentaje_iva=4  THEN base_imponible                ELSE 0 END),0) base_4,
            COALESCE(SUM(CASE WHEN porcentaje_iva=4  THEN cuota_iva*pct_iva_deducible/100 ELSE 0 END),0) iva_4
         FROM facturas_recibidas
         WHERE YEAR(fecha)=? AND trimestre=?"
    );
    $co->execute([$anio, $trim]);
    $compras = $co->fetch();

    // Costes de personal — solo si el módulo está activo y las tablas existen
    $mesInicio = ($trim - 1) * 3 + 1;
    $mesFin    = $mesInicio + 2;
    $sueldos = $ss_empresa = $cuota_autonomo = 0.0;

    if (getConfig('modulo_empleados', false)) {
        try {
            $em = $db->prepare(
                "SELECT COALESCE(SUM(salario_pagado),0) sueldos,
                        COALESCE(SUM(ss_empresa),0)     ss_empresa
                 FROM retenciones_empleados WHERE anio=? AND mes BETWEEN ? AND ?"
            );
            $em->execute([$anio, $mesInicio, $mesFin]);
            $personal   = $em->fetch();
            $sueldos    = (float)$personal['sueldos'];
            $ss_empresa = (float)$personal['ss_empresa'];
        } catch (PDOException) { /* tabla aún no migrada */ }

        try {
            $au = $db->prepare(
                "SELECT COALESCE(SUM(importe),0) cuota
                 FROM cuotas_autonomo WHERE anio=? AND mes BETWEEN ? AND ?"
            );
            $au->execute([$anio, $mesInicio, $mesFin]);
            $cuota_autonomo = (float)$au->fetchColumn();
        } catch (PDOException) { /* tabla aún no migrada */ }
    }

    $total_gastos  = (float)$compras['base'] + $sueldos + $ss_empresa + $cuota_autonomo;
    $iva_deducible = (float)$compras['iva_deducible'];
    $iva_resultado = (float)$ventas['iva'] - $iva_deducible;

    return [
        // ── Existentes (compatibilidad total con código consumidor) ──
        'ventas_base'    => (float)$ventas['base'],
        'ventas_iva'     => (float)$ventas['iva'],
        'ventas_irpf'    => (float)$ventas['irpf'],
        'compras_base'   => (float)$compras['base'],
        'compras_iva'    => $iva_deducible,          // CORREGIDO: ahora es el deducible efectivo
        'sueldos'        => $sueldos,
        'ss_empresa'     => $ss_empresa,
        'cuota_autonomo' => $cuota_autonomo,
        'total_gastos'   => $total_gastos,
        'iva_resultado'  => $iva_resultado,          // CORREGIDO: usa deducible efectivo
        'rendimiento'    => (float)$ventas['base'] - $total_gastos,

        // ── Nuevos: IVA bruto (informativo) ──
        'compras_iva_bruto' => (float)$compras['iva_bruto'],

        // ── Nuevos: ventas desglosadas por tipo (→ Modelo 303 casillas 01-09) ──
        'ventas_base_21' => (float)$ventas['base_21'],
        'ventas_iva_21'  => (float)$ventas['iva_21'],
        'ventas_base_10' => (float)$ventas['base_10'],
        'ventas_iva_10'  => (float)$ventas['iva_10'],
        'ventas_base_4'  => (float)$ventas['base_4'],
        'ventas_iva_4'   => (float)$ventas['iva_4'],

        // ── Nuevos: compras deducibles desglosadas por tipo (→ Modelo 303 casilla 29) ──
        'compras_base_21' => (float)$compras['base_21'],
        'compras_iva_21'  => (float)$compras['iva_21'],
        'compras_base_10' => (float)$compras['base_10'],
        'compras_iva_10'  => (float)$compras['iva_10'],
        'compras_base_4'  => (float)$compras['base_4'],
        'compras_iva_4'   => (float)$compras['iva_4'],
    ];
}

// ─── Flash messages ──────────────────────────────────────────
function flash(string $msg, string $type = 'success'): void {
    $_SESSION['flash'] = ['msg' => $msg, 'type' => $type];
}
function showFlash(): string {
    if (!isset($_SESSION['flash'])) return '';
    $f   = $_SESSION['flash'];
    unset($_SESSION['flash']);
    $cls = $f['type'] === 'success' ? 'alert-success' : 'alert-danger';
    return '<div class="alert ' . $cls . ' alert-dismissible fade show" role="alert">'
         . e($f['msg'])
         . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
}

// ─── Redirect ────────────────────────────────────────────────
function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

// ─── Backup completo (ZIP: database.sql + assets/logo.png) ──────────────
function generateBackupZip(string $targetPath): void {
    if (!class_exists('ZipArchive')) {
        throw new Exception('La extensión ZipArchive no está disponible en este servidor.');
    }

    $zip = new ZipArchive();
    if ($zip->open($targetPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new Exception('No se pudo crear el archivo ZIP en ' . basename($targetPath) . '.');
    }

    // Volcado completo de BD
    $zip->addFromString('database.sql', generateSQLDump());

    // Logo de empresa (archivo user-specific, no está en el repositorio)
    $logo = __DIR__ . '/../assets/logo.png';
    if (file_exists($logo)) {
        $zip->addFile($logo, 'assets/logo.png');
    }

    if ($zip->close() === false) {
        throw new Exception('Error al finalizar el archivo ZIP.');
    }
}

// ─── Volcado SQL (Backup) ─────────────────────────────────────
function generateSQLDump(): string {
    $db = getDB();
    $tables = [];
    $res = $db->query("SHOW TABLES");
    while ($r = $res->fetch(PDO::FETCH_NUM)) {
        if ($r[0] !== 'sessions') $tables[] = $r[0];
    }

    $out  = "-- Backup Libro Contable — v" . (defined('APP_VERSION') ? APP_VERSION : '?') . " — " . date('Y-m-d H:i:s') . "\n";
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

    // Datos de empresa desde constantes PHP (INSERT IGNORE: solo si no existen ya en configuracion).
    // Garantiza que el backup siempre contiene los datos de empresa aunque el usuario
    // no los haya guardado nunca desde ajustes/empresa.php.
    $empresaConst = [
        'empresa_nombre'   => defined('EMPRESA_NOMBRE')   ? EMPRESA_NOMBRE   : '',
        'empresa_sociedad' => defined('EMPRESA_SOCIEDAD') ? EMPRESA_SOCIEDAD : '',
        'empresa_cif'      => defined('EMPRESA_CIF')      ? EMPRESA_CIF      : '',
        'empresa_dir1'     => defined('EMPRESA_DIR1')     ? EMPRESA_DIR1     : '',
        'empresa_dir2'     => defined('EMPRESA_DIR2')     ? EMPRESA_DIR2     : '',
        'empresa_tel'      => defined('EMPRESA_TEL')      ? EMPRESA_TEL      : '',
        'empresa_email'    => defined('EMPRESA_EMAIL')    ? EMPRESA_EMAIL    : '',
        'empresa_web'      => defined('EMPRESA_WEB')      ? EMPRESA_WEB      : '',
        'empresa_banco'    => defined('EMPRESA_BANCO')    ? EMPRESA_BANCO    : '',
        'empresa_iban'     => defined('EMPRESA_IBAN')     ? EMPRESA_IBAN     : '',
        'empresa_iva_def'  => defined('EMPRESA_IVA')      ? EMPRESA_IVA      : '',
        'empresa_irpf_def' => defined('EMPRESA_IRPF')     ? EMPRESA_IRPF     : '',
    ];
    $out .= "-- Datos de empresa (fallback desde config/database.php)\n";
    foreach ($empresaConst as $clave => $valor) {
        if ($valor !== '') {
            $out .= "INSERT IGNORE INTO `configuracion` (`clave`, `valor`) VALUES ("
                  . $db->quote($clave) . ", " . $db->quote((string)$valor) . ");\n";
        }
    }
    $out .= "\n";

    $out .= "SET FOREIGN_KEY_CHECKS=1;\n";
    return $out;
}

// ─── Categorías de gasto ─────────────────────────────────────
function getCategoriasGasto(bool $soloActivas = true): array {
    $db  = getDB();
    $sql = "SELECT id, nombre, pct_iva_deducible, pct_irpf_deducible, codigo_aeat, activa
            FROM categorias_gasto"
         . ($soloActivas ? " WHERE activa = 1" : "")
         . " ORDER BY nombre";
    return $db->query($sql)->fetchAll();
}

function getCategoriaGasto(int $id): array|false {
    $st = getDB()->prepare(
        "SELECT id, nombre, pct_iva_deducible, pct_irpf_deducible, codigo_aeat, activa
         FROM categorias_gasto WHERE id = ?"
    );
    $st->execute([$id]);
    return $st->fetch();
}

// ─── Ayuda contextual ────────────────────────────────────────
/**
 * Devuelve un icono de interrogación con tooltip Bootstrap 5.
 * Los tooltips se inicializan globalmente en footer.php.
 */
function helpTip(string $tip, string $placement = 'top'): string {
    return ' <i class="bi bi-info-circle-fill help-tip"'
         . ' data-bs-toggle="tooltip"'
         . ' data-bs-placement="' . $placement . '"'
         . ' data-bs-title="' . htmlspecialchars($tip, ENT_QUOTES, 'UTF-8') . '"></i>';
}

// ─── Empleados ────────────────────────────────────────────────
function getEmpleados(bool $soloActivos = true): array {
    $db  = getDB();
    $sql = "SELECT id, nombre, nif, puesto, salario_mensual, porcentaje_irpf,
                   porcentaje_ss_empresa, porcentaje_ss_empleado, fecha_alta, activo
            FROM empleados" . ($soloActivos ? " WHERE activo=1" : "") . " ORDER BY nombre";
    return $db->query($sql)->fetchAll();
}
function getEmpleado(int $id): array|false {
    $st = getDB()->prepare(
        "SELECT id, nombre, nif, puesto, salario_mensual, porcentaje_irpf,
                porcentaje_ss_empresa, porcentaje_ss_empleado, fecha_alta, activo
         FROM empleados WHERE id=?"
    );
    $st->execute([$id]);
    return $st->fetch();
}
function resumenModelo111(int $anio, int $trim): array {
    $mesInicio = ($trim - 1) * 3 + 1;
    $mesFin    = $mesInicio + 2;
    $st = getDB()->prepare(
        "SELECT COUNT(DISTINCT empleado_id) AS perceptores,
                COALESCE(SUM(salario_pagado), 0) AS base,
                COALESCE(SUM(retencion_irpf), 0) AS retenciones
         FROM retenciones_empleados
         WHERE anio=? AND mes BETWEEN ? AND ?"
    );
    $st->execute([$anio, $mesInicio, $mesFin]);
    return $st->fetch() ?: ['perceptores' => 0, 'base' => 0.0, 'retenciones' => 0.0];
}

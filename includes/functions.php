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
function siguienteNumeroFactura(): string {
    $pref    = getConfig('factura_prefijo', 'F');
    $usaAnio = getConfig('factura_usa_anio', true);
    $digitos = (int)getConfig('factura_digitos', 5);
    $proximo = (int)getConfig('factura_proximo', 1);

    $anio = $usaAnio ? date('Y') : '';
    $numeroStr = str_pad($proximo, $digitos, '0', STR_PAD_LEFT);
    
    $final = $pref . $anio . $numeroStr;

    // Incrementar el próximo número para la siguiente vez
    setConfig('factura_proximo', $proximo + 1);

    return $final;
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
function getFacturasRecibidas(int $anio = 0, int $trim = 0): array {
    $db   = getDB();
    $where = ["1=1"];
    $params = [];
    if ($anio) { $where[] = "YEAR(fecha)=?"; $params[] = $anio; }
    if ($trim) { $where[] = "trimestre=?";   $params[] = $trim; }
    $sql = "SELECT fr.*, p.nombre AS proveedor_nombre_actual
            FROM facturas_recibidas fr
            LEFT JOIN proveedores p ON p.id = fr.proveedor_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY fecha DESC";
    $st = $db->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

// ─── Resumen fiscal ──────────────────────────────────────────
function resumenTrimestral(int $anio, int $trim): array {
    $db = getDB();

    $ve = $db->prepare("SELECT COALESCE(SUM(base_imponible),0) base, COALESCE(SUM(cuota_iva),0) iva, COALESCE(SUM(cuota_irpf),0) irpf
                        FROM facturas_emitidas WHERE YEAR(fecha)=? AND trimestre=? AND estado!='cancelada'");
    $ve->execute([$anio, $trim]);
    $ventas = $ve->fetch();

    $co = $db->prepare("SELECT COALESCE(SUM(base_imponible),0) base, COALESCE(SUM(cuota_iva),0) iva
                        FROM facturas_recibidas WHERE YEAR(fecha)=? AND trimestre=?");
    $co->execute([$anio, $trim]);
    $compras = $co->fetch();

    $iva_resultado = $ventas['iva'] - $compras['iva'];

    return [
        'ventas_base'  => (float)$ventas['base'],
        'ventas_iva'   => (float)$ventas['iva'],
        'ventas_irpf'  => (float)$ventas['irpf'],
        'compras_base' => (float)$compras['base'],
        'compras_iva'  => (float)$compras['iva'],
        'iva_resultado'=> $iva_resultado,
        'rendimiento'  => (float)$ventas['base'] - (float)$compras['base'],
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

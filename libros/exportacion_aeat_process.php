<?php
/**
 * Generador de Libros Registro AEAT — CSV según Orden HAC/773/2019
 * Parámetros GET: tipo (expedidas|recibidas), anio, trimestre (0=todos)
 *
 * Decisiones de diseño documentadas:
 *  - tipo_factura = F1 hardcodeado (deuda técnica: actualizar cuando se implementen rectificativas)
 *  - concepto_ingreso = I01 hardcodeado (válido para autónomos en estimación directa IRPF)
 *  - Nº recepción = id de facturas_recibidas (correlativo único por tabla)
 *  - Facturas sin categoria_gasto_id exportan con código G16 (Otros gastos deducibles)
 */
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/functions.php';

if (empty($_SESSION['usuario_id'])) {
    http_response_code(403);
    exit('Acceso no autorizado.');
}

$tipo      = get('tipo');
$anio      = (int)get('anio', date('Y'));
$trimestre = (int)get('trimestre', 0);

if (!in_array($tipo, ['expedidas', 'recibidas'])) {
    http_response_code(400);
    exit('Parámetro tipo no válido.');
}
if ($anio < 2000 || $anio > 2099) {
    http_response_code(400);
    exit('Año fuera de rango.');
}

$periodo  = $trimestre ? "T{$trimestre}" : 'anual';
$filename = "libro_registro_{$tipo}_aeat_{$anio}_{$periodo}.csv";

header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');

// BOM UTF-8 — necesario para que Excel abra el archivo en UTF-8 sin recodificar
echo "\xEF\xBB\xBF";

$out = fopen('php://output', 'w');

// ── Helpers de formato ────────────────────────────────────────────────────────

function csvDec($val): string
{
    return str_replace('.', ',', number_format((float)$val, 2, '.', ''));
}

function csvFecha(string $fecha): string
{
    return date('d/m/Y', strtotime($fecha));
}

/**
 * Infiere el tipo de NIF según su estructura:
 *  J = persona jurídica española (empieza por letra A-H, J-N, P-W)
 *  F = persona física española o NIE (empieza por dígito, X, Y o Z)
 *  E = identificación extranjera (resto)
 */
function inferNifTipo(string $nif): string
{
    $nif = strtoupper(trim($nif));
    if ($nif === '') return 'E';
    $c = $nif[0];
    if (ctype_alpha($c) && !in_array($c, ['X', 'Y', 'Z'])) return 'J';
    if (ctype_digit($c) || in_array($c, ['X', 'Y', 'Z']))  return 'F';
    return 'E';
}

/** Extrae el prefijo alfabético del número de factura (ej: "F" de "F20260001") */
function csvSerie(string $numero): string
{
    preg_match('/^([A-Za-z]+)/', $numero, $m);
    return $m[1] ?? '';
}

/** Extrae la parte numérica del número de factura (ej: "20260001" de "F20260001") */
function csvNumero(string $numero): string
{
    return preg_replace('/^[A-Za-z]+/', '', $numero);
}

// ── Construcción de condición WHERE ──────────────────────────────────────────

$db = getDB();

if ($tipo === 'expedidas') {

    fputcsv($out, [
        'Ejercicio',
        'Período',
        'Tipo de factura',
        'Concepto de ingreso',
        'Fecha expedición',
        'Serie',
        'Número',
        'NIF destinatario - Tipo',
        'NIF destinatario - Identificación',
        'Nombre destinatario',
        'Total factura',
        'Base imponible',
        'Tipo IVA',
        'Cuota IVA repercutida',
        'Tipo retención IRPF',
        'Importe retención IRPF',
    ], ';');

    $where  = "WHERE fe.estado NOT IN ('borrador', 'cancelada') AND YEAR(fe.fecha) = ?";
    $params = [$anio];
    if ($trimestre) {
        $where  .= " AND fe.trimestre = ?";
        $params[] = $trimestre;
    }

    $stmt = $db->prepare(
        "SELECT fe.fecha, fe.trimestre, fe.numero,
                fe.cliente_nif, fe.cliente_nombre,
                fe.total, fe.base_imponible, fe.porcentaje_iva, fe.cuota_iva,
                fe.porcentaje_irpf, fe.cuota_irpf
         FROM facturas_emitidas fe
         $where
         ORDER BY fe.fecha, fe.numero"
    );
    $stmt->execute($params);

    while ($r = $stmt->fetch()) {
        fputcsv($out, [
            date('Y', strtotime($r['fecha'])),
            $r['trimestre'] . 'T',
            'F1',
            'I01',
            csvFecha($r['fecha']),
            csvSerie($r['numero']),
            csvNumero($r['numero']),
            inferNifTipo($r['cliente_nif'] ?? ''),
            $r['cliente_nif'] ?? '',
            $r['cliente_nombre'] ?? '',
            csvDec($r['total']),
            csvDec($r['base_imponible']),
            csvDec($r['porcentaje_iva']),
            csvDec($r['cuota_iva']),
            (float)$r['porcentaje_irpf'] > 0 ? csvDec($r['porcentaje_irpf']) : '',
            (float)$r['porcentaje_irpf'] > 0 ? csvDec($r['cuota_irpf'])      : '',
        ], ';');
    }

} else {

    fputcsv($out, [
        'Ejercicio',
        'Período',
        'Tipo de factura',
        'Concepto de gasto',
        'Nº recepción',
        'Fecha expedición',
        'NIF expedidor',
        'Nombre expedidor',
        'Base imponible',
        'Tipo IVA',
        'Cuota IVA soportado',
        'Cuota IVA deducible',
        'Gasto deducible IRPF',
        'Tipo retención IRPF',
        'Importe retención IRPF',
    ], ';');

    $where  = "WHERE YEAR(fr.fecha) = ?";
    $params = [$anio];
    if ($trimestre) {
        $where  .= " AND fr.trimestre = ?";
        $params[] = $trimestre;
    }

    $stmt = $db->prepare(
        "SELECT fr.id, fr.fecha, fr.trimestre,
                fr.proveedor_nif, fr.proveedor_nombre,
                fr.base_imponible, fr.porcentaje_iva, fr.cuota_iva,
                fr.pct_iva_deducible, fr.pct_irpf_deducible,
                fr.porcentaje_irpf, fr.cuota_irpf,
                COALESCE(cg.codigo_aeat, 'G16') AS concepto_gasto
         FROM facturas_recibidas fr
         LEFT JOIN categorias_gasto cg ON fr.categoria_gasto_id = cg.id
         $where
         ORDER BY fr.fecha, fr.id"
    );
    $stmt->execute($params);

    while ($r = $stmt->fetch()) {
        $iva_ded   = round((float)$r['cuota_iva']      * (float)$r['pct_iva_deducible']  / 100, 2);
        $gasto_ded = round((float)$r['base_imponible'] * (float)$r['pct_irpf_deducible'] / 100, 2);

        fputcsv($out, [
            date('Y', strtotime($r['fecha'])),
            $r['trimestre'] . 'T',
            'F1',
            $r['concepto_gasto'],
            $r['id'],
            csvFecha($r['fecha']),
            $r['proveedor_nif']    ?? '',
            $r['proveedor_nombre'] ?? '',
            csvDec($r['base_imponible']),
            csvDec($r['porcentaje_iva']),
            csvDec($r['cuota_iva']),
            csvDec($iva_ded),
            csvDec($gasto_ded),
            (float)$r['porcentaje_irpf'] > 0 ? csvDec($r['porcentaje_irpf']) : '',
            (float)$r['porcentaje_irpf'] > 0 ? csvDec($r['cuota_irpf'])      : '',
        ], ';');
    }
}

fclose($out);
exit;

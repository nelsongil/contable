<?php
/**
 * importar_pdf.php — Extracción local de facturas en PDF
 * Usa smalot/pdfparser (solo PDFs con texto seleccionable).
 * PDFs escaneados/imagen requieren OCR externo — se notifica al usuario.
 */

if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../vendor/pdfparser/autoload.php';

header('Content-Type: application/json; charset=utf-8');

if (!isset($_FILES['pdf']) || $_FILES['pdf']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['error' => 'No se ha recibido el archivo PDF correctamente.']);
    exit;
}

$pdfFile = $_FILES['pdf']['tmp_name'];

try {
    $parser = new \Smalot\PdfParser\Parser();
    $pdf    = $parser->parseFile($pdfFile);
    $text   = $pdf->getText();
} catch (Exception $e) {
    echo json_encode(['error' => 'Error al procesar el PDF: ' . $e->getMessage()]);
    exit;
}

if (!$text || strlen(trim($text)) < 20) {
    echo json_encode([
        'error' => 'Este PDF no contiene texto seleccionable (parece una imagen escaneada). ' .
                   'Para este tipo de documentos se necesita OCR, que no está disponible en este servidor. ' .
                   'Introduce los datos manualmente o usa un PDF con texto.'
    ]);
    exit;
}

// Normalizar
$lines    = array_values(array_filter(array_map('trim', preg_split('/[\r\n]+/', $text)), fn($l) => $l !== ''));
$fullText = implode("\n", $lines);

$data = [
    'proveedor_nombre'    => '',
    'proveedor_nif'       => '',
    'proveedor_direccion' => '',
    'factura_numero'      => '',
    'factura_fecha'       => '',
    'base_imponible'      => 0.0,
    'porcentaje_iva'      => 21,
    'cuota_iva'           => 0.0,
    'total'               => 0.0,
    'descripcion'         => '',
    'lineas'              => [],
    'confianza'           => [
        'nif'    => 'baja',
        'numero' => 'baja',
        'base'   => 'baja',
        'total'  => 'baja',
        'fecha'  => 'baja',
    ],
];

// ── 1. NIF / CIF ─────────────────────────────────────────────────────────────
// Formatos: B12345678, 12345678Z, X1234567Z, ESB12345678
if (preg_match('/\b(?:ES)?([A-Z]\d{7}[A-Z0-9]|\d{8}[A-Z]|[XYZ]\d{7}[A-Z])\b/', $fullText, $m)) {
    $data['proveedor_nif'] = strtoupper($m[1]);
    $data['confianza']['nif'] = 'alta';
}

// ── 2. Número de factura ─────────────────────────────────────────────────────
if (preg_match('/(?:factura[^\w]*n[uú]?m(?:ero)?|n[uú]?m(?:ero)?[^\w]*(?:de\s+)?factura|fra\.?|invoice\s*(?:no|#|number)?)[^\w]*([A-Z0-9][A-Z0-9\/\-_]{2,19})/i', $fullText, $m)) {
    $data['factura_numero'] = trim($m[1]);
    $data['confianza']['numero'] = 'media';
} elseif (preg_match('/\b((?:F|FAC|INV|FV|FR|A|B)\d{4,})\b/', $fullText, $m)) {
    $data['factura_numero'] = $m[1];
    $data['confianza']['numero'] = 'baja';
}

// ── 3. Fecha ─────────────────────────────────────────────────────────────────
// Priorizar fechas cercanas a palabras clave
$fechaPatrones = [
    '/(?:fecha(?:\s+de\s+(?:emisi[oó]n|factura)?)?|date)[^\d]*(\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4})/i',
    '/\b(\d{1,2}[\/\-\.]\d{1,2}[\/\-\.](?:20)\d{2})\b/',
    '/\b(\d{4}[\/\-\.]\d{2}[\/\-\.]\d{2})\b/',  // ISO
];
foreach ($fechaPatrones as $pat) {
    if (preg_match($pat, $fullText, $m)) {
        $raw = $m[1];
        // Detectar formato ISO vs día/mes/año
        if (preg_match('/^\d{4}/', $raw)) {
            $data['factura_fecha'] = $raw; // ya es YYYY-...
        } else {
            $sep   = preg_match('/\//', $raw) ? '/' : (preg_match('/-/', $raw) ? '-' : '.');
            $parts = explode($sep, $raw);
            if (count($parts) === 3) {
                $d = str_pad($parts[0], 2, '0', STR_PAD_LEFT);
                $mo = str_pad($parts[1], 2, '0', STR_PAD_LEFT);
                $y  = strlen($parts[2]) === 2 ? '20'.$parts[2] : $parts[2];
                $data['factura_fecha'] = "$y-$mo-$d";
            }
        }
        $data['confianza']['fecha'] = 'media';
        break;
    }
}
if (!$data['factura_fecha']) $data['factura_fecha'] = date('Y-m-d');

// ── 4. Base imponible ─────────────────────────────────────────────────────────
if (preg_match('/(?:base\s*imponible|subtotal\s*(?:neto)?|base\s*(?:neta)?)[^\d]*(\d{1,3}(?:[.,]\d{3})*[.,]\d{2})/i', $fullText, $m)) {
    $data['base_imponible'] = parseDecimal($m[1]);
    $data['confianza']['base'] = 'alta';
}

// ── 5. Total factura ─────────────────────────────────────────────────────────
if (preg_match('/(?:total\s*(?:factura|a\s*pagar|importe)?|importe\s*total)[^\d]*(\d{1,3}(?:[.,]\d{3})*[.,]\d{2})/i', $fullText, $m)) {
    $data['total'] = parseDecimal($m[1]);
    $data['confianza']['total'] = 'alta';
}

// ── 6. IVA ───────────────────────────────────────────────────────────────────
if (preg_match('/(\d{1,2})\s*%\s*(?:de\s*)?(?:iva|igic|impuesto)/i', $fullText, $m)) {
    $data['porcentaje_iva'] = (int)$m[1];
} elseif (preg_match('/(?:iva|igic)[^\d]*(\d{1,2})\s*%/i', $fullText, $m)) {
    $data['porcentaje_iva'] = (int)$m[1];
}
if (preg_match('/(?:cuota\s*(?:de\s*)?iva|iva\s*(?:repercutido)?)[^\d]*(\d{1,3}(?:[.,]\d{3})*[.,]\d{2})/i', $fullText, $m)) {
    $data['cuota_iva'] = parseDecimal($m[1]);
} elseif ($data['base_imponible'] > 0) {
    $data['cuota_iva'] = round($data['base_imponible'] * $data['porcentaje_iva'] / 100, 2);
}

// Si no hay base pero sí total y cuota_iva, inferir base
if ($data['base_imponible'] == 0 && $data['total'] > 0 && $data['cuota_iva'] > 0) {
    $data['base_imponible'] = round($data['total'] - $data['cuota_iva'], 2);
    $data['confianza']['base'] = 'media';
}
// Si no hay total, calcularlo
if ($data['total'] == 0 && $data['base_imponible'] > 0) {
    $data['total'] = round($data['base_imponible'] + $data['cuota_iva'], 2);
}

// ── 7. Dirección del proveedor ────────────────────────────────────────────────
$dirPatrones = [
    '/\b((?:Calle|C\/|Cl\.?|Avda?\.?|Avenida|Plaza|Paseo|Pol(?:\.|ígono)?|Camino|Carretera|Rambla|Vía)[^\n,]{5,60})/i',
    '/\b(\d{5})\s+([A-Za-záéíóúÁÉÍÓÚñÑ][A-Za-záéíóúÁÉÍÓÚñÑ\s]{3,30})/', // CP + ciudad
];
foreach ($dirPatrones as $pat) {
    if (preg_match($pat, $fullText, $m)) {
        $data['proveedor_direccion'] = trim($m[0]);
        break;
    }
}

// ── 8. Descripción / Concepto ─────────────────────────────────────────────────
if (preg_match('/(?:concepto|descripci[oó]n|objeto|detalle|servicios?\s*prestados?)[^\S\n]*[:\-]\s*([^\n]{5,100})/i', $fullText, $m)) {
    $data['descripcion'] = trim($m[1]);
}

// ── 9. Nombre del proveedor ───────────────────────────────────────────────────
$nifLineIdx = -1;
if ($data['proveedor_nif']) {
    foreach ($lines as $idx => $line) {
        if (stripos($line, $data['proveedor_nif']) !== false) { $nifLineIdx = $idx; break; }
    }
}
if ($nifLineIdx !== -1) {
    // Buscar nombre en las 3 líneas anteriores al NIF
    for ($i = max(0, $nifLineIdx - 3); $i < $nifLineIdx; $i++) {
        $line = $lines[$i];
        if (strlen($line) >= 3
            && !preg_match('/\d{2}[\/\-\.]/', $line)   // no fecha
            && !preg_match('/^\d+[.,]?\d*$/', $line)    // no solo número
            && !preg_match('/\b(calle|c\/|avda|cp:|tel|fax|www|@)/i', $line)) {
            $data['proveedor_nombre'] = $line;
        }
    }
}
// Fallback: primeras líneas no numéricas sin aspecto de dirección
if (!$data['proveedor_nombre']) {
    foreach ($lines as $line) {
        if (strlen($line) < 4) continue;
        if (preg_match('/^\d/', $line)) continue;
        if (preg_match('/\d{2}[\/\-\.]/', $line)) continue;
        if (preg_match('/\b(calle|c\/|avda|plaza|www\.|@|tel[éf]|fax|nif|cif)/i', $line)) continue;
        $data['proveedor_nombre'] = $line;
        break;
    }
}

// ── 10. Líneas de detalle ─────────────────────────────────────────────────────
// Intentar detectar filas: cantidad + descripción + importe
$lineas = [];
// Patrón: número al inicio, texto, importe al final
if (preg_match_all(
    '/^(\d+(?:[.,]\d+)?)\s{1,4}(.{5,60}?)\s{1,6}(\d{1,3}(?:[.,]\d{3})*[.,]\d{2})\s*$/m',
    $fullText, $matches, PREG_SET_ORDER
)) {
    foreach ($matches as $m) {
        $imp = parseDecimal($m[3]);
        // Filtrar: importe menor que el total y mayor que 0
        if ($imp > 0 && ($data['total'] == 0 || $imp <= $data['total'] * 1.05)) {
            $lineas[] = [
                'cantidad'    => (float)str_replace(',', '.', $m[1]),
                'descripcion' => trim($m[2]),
                'importe'     => $imp,
            ];
        }
    }
}
// Si no hay líneas con patrón estricto, intentar patrón más flexible
if (!$lineas) {
    if (preg_match_all(
        '/^(.{5,60}?)\s{2,}(\d{1,3}(?:[.,]\d{3})*[.,]\d{2})\s*$/m',
        $fullText, $matches, PREG_SET_ORDER
    )) {
        foreach ($matches as $m) {
            $imp = parseDecimal($m[2]);
            $desc = trim($m[1]);
            if ($imp > 0 && $imp < ($data['total'] ?: 99999) * 1.05
                && !preg_match('/^(?:base|iva|total|cuota|subtotal)/i', $desc)) {
                $lineas[] = ['cantidad' => 1, 'descripcion' => $desc, 'importe' => $imp];
            }
        }
    }
}
$data['lineas'] = array_slice($lineas, 0, 20); // máx 20 líneas

// ── Buscar proveedor en BD ────────────────────────────────────────────────────
$db = getDB();
$proveedorId = null;
if ($data['proveedor_nif']) {
    $st = $db->prepare("SELECT id, nombre FROM proveedores WHERE nif = ? AND activo = 1 LIMIT 1");
    $st->execute([$data['proveedor_nif']]);
    $prov = $st->fetch();
    if ($prov) { $proveedorId = $prov['id']; $data['proveedor_nombre'] = $prov['nombre']; }
}
if (!$proveedorId && $data['proveedor_nombre']) {
    $st = $db->prepare("SELECT id, nombre, nif FROM proveedores WHERE nombre LIKE ? AND activo = 1 LIMIT 1");
    $st->execute(['%' . $data['proveedor_nombre'] . '%']);
    $prov = $st->fetch();
    if ($prov) {
        $proveedorId = $prov['id'];
        $data['proveedor_nombre'] = $prov['nombre'];
        if (!$data['proveedor_nif']) $data['proveedor_nif'] = $prov['nif'];
    }
}
$data['proveedor_id'] = $proveedorId;

// Si las líneas tienen descripción pero no hay descripción general, usar la primera línea
if (!$data['descripcion'] && !empty($data['lineas'])) {
    $descs = array_column($data['lineas'], 'descripcion');
    $data['descripcion'] = implode('; ', array_slice($descs, 0, 3));
    if (strlen($data['descripcion']) > 100) $data['descripcion'] = substr($data['descripcion'], 0, 97) . '…';
}

echo json_encode($data, JSON_UNESCAPED_UNICODE);

// ── Helper ────────────────────────────────────────────────────────────────────
function parseDecimal(string $s): float {
    // Soporta: 1.234,56 | 1,234.56 | 1234,56 | 1234.56
    $s = trim($s);
    if (preg_match('/^(\d{1,3}(?:\.\d{3})+),(\d{2})$/', $s)) {
        return (float)str_replace(['.', ','], ['', '.'], $s);
    }
    if (preg_match('/^(\d{1,3}(?:,\d{3})+)\.(\d{2})$/', $s)) {
        return (float)str_replace(',', '', $s);
    }
    return (float)str_replace(',', '.', $s);
}

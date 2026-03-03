<?php
/**
 * importar_pdf.php — Nuevo endpoint para procesar facturas PDF localmente
 * Reemplaza la dependencia de APIs externas usando smalot/pdfparser
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

// Verificar que se haya subido un archivo
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

if (!$text || strlen(trim($text)) < 10) {
    echo json_encode(['error' => 'No se pudo extraer texto del PDF.']);
    exit;
}

// Normalizar texto
$lines = array_values(array_filter(
    array_map('trim', preg_split('/[\r\n]+/', $text)),
    fn($l) => $l !== ''
));
$fullText = implode("\n", $lines);

// --- PATRONES REGEX SOLICITADOS ---

$data = [
    'proveedor_nombre' => '',
    'proveedor_nif'    => '',
    'factura_numero'   => '',
    'factura_fecha'    => '',
    'base_imponible'   => 0.0,
    'porcentaje_iva'   => 21,
    'cuota_iva'        => 0.0,
    'total'            => 0.0,
    'descripcion'      => '',
    'confianza'        => [
        'nif'    => 'baja',
        'numero' => 'baja',
        'base'   => 'baja',
        'total'  => 'baja',
        'fecha'  => 'baja'
    ],
    'texto_completo'   => mb_substr($fullText, 0, 500) . (strlen($fullText) > 500 ? '...' : '')
];

// 1. NIF/CIF
if (preg_match('/\b([A-Z]\d{7}[A-Z0-9]|\d{8}[A-Z])\b/', $fullText, $m)) {
    $data['proveedor_nif'] = strtoupper($m[1]);
    $data['confianza']['nif'] = 'alta';
}

// 2. Número de factura
if (preg_match('/(?:factura|fra|invoice|nº|núm|número)[^\w]*([A-Z0-9\/\-]{3,20})/i', $fullText, $m)) {
    $data['factura_numero'] = trim($m[1]);
    $data['confianza']['numero'] = 'media';
}

// 3. Fecha
if (preg_match('/\b(\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4})\b/', $fullText, $m)) {
    $fechaRaw = $m[1];
    // Intentar normalizar a YYYY-MM-DD
    $sep = strpos($fechaRaw, '/') !== false ? '/' : (strpos($fechaRaw, '-') !== false ? '-' : '.');
    $parts = explode($sep, $fechaRaw);
    if (count($parts) === 3) {
        $day = str_pad($parts[0], 2, '0', STR_PAD_LEFT);
        $month = str_pad($parts[1], 2, '0', STR_PAD_LEFT);
        $year = $parts[2];
        if (strlen($year) === 2) $year = '20' . $year;
        $data['factura_fecha'] = "$year-$month-$day";
        $data['confianza']['fecha'] = 'media';
    } else {
        $data['factura_fecha'] = date('Y-m-d');
    }
} else {
    $data['factura_fecha'] = date('Y-m-d');
}

// 4. Base imponible
if (preg_match('/(?:base\s*imponible|subtotal|base)[^\d]*(\d+[.,]\d{2})/i', $fullText, $m)) {
    $data['base_imponible'] = (float)str_replace(',', '.', $m[1]);
    $data['confianza']['base'] = 'alta';
}

// 5. Total factura
if (preg_match('/(?:total\s*factura|total\s*a\s*pagar|importe\s*total|total)[^\d]*(\d+[.,]\d{2})/i', $fullText, $m)) {
    $data['total'] = (float)str_replace(',', '.', $m[1]);
    $data['confianza']['total'] = 'alta';
}

// 6. IVA (procentaje y cuota)
if (preg_match('/(\d{1,2})\s*%\s*(?:iva|impuesto)/i', $fullText, $m)) {
    $data['porcentaje_iva'] = (int)$m[1];
} elseif (preg_match('/(?:iva|impuesto)[^\d]*(\d{1,2})\s*%/i', $fullText, $m)) {
    $data['porcentaje_iva'] = (int)$m[1];
}

if (preg_match('/(?:cuota\s*iva|iva)[^\d]*(\d+[.,]\d{2})/i', $fullText, $m)) {
    $data['cuota_iva'] = (float)str_replace(',', '.', $m[1]);
} else {
    // Si no se detecta la cuota pero tenemos base y %, calcularla
    if ($data['base_imponible'] > 0) {
        $data['cuota_iva'] = round($data['base_imponible'] * $data['porcentaje_iva'] / 100, 2);
    }
}

// 7. Nombre del proveedor
$nifLineIdx = -1;
if ($data['proveedor_nif']) {
    foreach ($lines as $idx => $line) {
        if (stripos($line, $data['proveedor_nif']) !== false) {
            $nifLineIdx = $idx;
            break;
        }
    }
}

// Intentar buscar cerca del NIF (2 líneas antes o después)
if ($nifLineIdx !== -1) {
    for ($i = max(0, $nifLineIdx - 2); $i <= min(count($lines) - 1, $nifLineIdx + 2); $i++) {
        if ($i === $nifLineIdx) continue;
        $line = $lines[$i];
        if (strlen($line) > 4 && !preg_match('/\d{2}[\/\-\.]/', $line) && !preg_match('/^\d+$/', $line)) {
            $data['proveedor_nombre'] = $line;
            break;
        }
    }
}

// Fallback: Buscar las primeras 3 líneas no vacías si no se detectó arriba
if (!$data['proveedor_nombre']) {
    $count = 0;
    foreach ($lines as $line) {
        if ($count >= 3) break;
        if (preg_match('/\d{2}[\/\-\.]/', $line)) continue; 
        if (preg_match('/^\d+$/', $line)) continue; 
        if (preg_match('/(Calle|C\/|Avda|Plaza|Paseo|Pol\.)/i', $line)) continue;
        
        if (strlen($line) > 3) {
            $data['proveedor_nombre'] = $line;
            break;
        }
        $count++;
    }
}

// --- BUSCAR PROVEEDOR EN BD ---
$db = getDB();
$proveedorId = null;
if ($data['proveedor_nif']) {
    $st = $db->prepare("SELECT id, nombre FROM proveedores WHERE nif = ? AND activo = 1 LIMIT 1");
    $st->execute([$data['proveedor_nif']]);
    $prov = $st->fetch();
    if ($prov) {
        $proveedorId = $prov['id'];
        $data['proveedor_nombre'] = $prov['nombre'];
        $data['confianza']['nif'] = 'alta';
    }
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

echo json_encode($data, JSON_UNESCAPED_UNICODE);

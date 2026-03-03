<?php
/**
 * parse_pdf.php — Endpoint AJAX para parsear texto extraído de un PDF de factura
 * Recibe: POST JSON { "text": "...texto extraído por PDF.js..." }
 * Devuelve: JSON con los datos de la factura detectados
 *
 * Formato típico de bloque de proveedor en facturas españolas:
 *   NOMBRE EMPRESA S.L.
 *   C/ Dirección, 7, Ciudad
 *   28400 Ciudad
 *   CIF: B12345678
 */

if (session_status() === PHP_SESSION_NONE) session_start();

if (empty($_SESSION['usuario_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'No autorizado']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json; charset=utf-8');

$body = file_get_contents('php://input');
if (strlen($body) > 600000) {
    echo json_encode(['error' => 'El texto extraído es demasiado grande.']);
    exit;
}

$data = json_decode($body, true);
$text = trim($data['text'] ?? '');

if (!$text) {
    echo json_encode(['error' => 'No se pudo extraer texto del PDF. Es posible que sea un PDF escaneado (imagen).']);
    exit;
}

// ─── Normalizar líneas ────────────────────────────────────────────────────────
$lines = array_values(array_filter(
    array_map('trim', preg_split('/[\r\n]+/', $text)),
    fn($l) => $l !== ''
));
$fullText = implode("\n", $lines);

// ─── Extraer NIF/CIF ─────────────────────────────────────────────────────────
function extractNif(string $text): string {
    // CIF empresas: letra + 7 dígitos + control
    if (preg_match('/\b([ABCDEFGHJKLMNPQRSUVW]\d{7}[0-9A-J])\b/i', $text, $m)) return strtoupper($m[1]);
    // NIE
    if (preg_match('/\b([XYZ]\d{7}[A-Z])\b/i', $text, $m)) return strtoupper($m[1]);
    // NIF
    if (preg_match('/\b(\d{8}[A-Z])\b/i', $text, $m)) return strtoupper($m[1]);
    return '';
}

// ─── Extraer nombre y dirección del proveedor ─────────────────────────────────
// Las facturas españolas siguen este patrón en el bloque del proveedor:
//   NOMBRE EMPRESA S.L.           ← nombre (puede ser 1-2 líneas)
//   C/ Dirección nº, Municipio    ← dirección
//   28400 Ciudad                  ← CP + ciudad
//   NIF: B12345678                ← NIF
// El NIF puede estar antes o después del bloque de dirección.
function extractBloqueFiscal(array $lines, string $nif): array {
    $result = ['nombre' => '', 'direccion' => '', 'cp' => '', 'ciudad' => '', 'provincia' => ''];
    if (!$nif) return $result;

    // Encontrar la línea que contiene el NIF
    $nifIdx = -1;
    foreach ($lines as $i => $line) {
        if (stripos($line, $nif) !== false) { $nifIdx = $i; break; }
    }
    if ($nifIdx === -1) return $result;

    // ── Recoger líneas alrededor del NIF ────────────────────────────────────
    // En facturas, el bloque empresa tiene entre 2 y 6 líneas antes del NIF
    // o inmediatamente después del NIF.
    // Primero intentamos: bloque ANTES del NIF (lo más común)
    $bloque = [];
    for ($j = max(0, $nifIdx - 8); $j < $nifIdx; $j++) {
        $bloque[] = $lines[$j];
    }

    // Si el bloque es muy corto (<2 líneas), también miramos DESPUÉS del NIF
    if (count($bloque) < 2) {
        for ($j = $nifIdx + 1; $j <= min(count($lines) - 1, $nifIdx + 6); $j++) {
            $bloque[] = $lines[$j];
        }
    }

    // ── Clasificar cada línea del bloque ────────────────────────────────────
    $nombre     = '';
    $direccion  = '';
    $cp         = '';
    $ciudad     = '';
    $provincia  = '';

    foreach ($bloque as $line) {
        $l = trim($line);
        if (!$l) continue;

        // ¿CP español? (5 dígitos al principio o al final)
        if (preg_match('/\b(\d{5})\b[.\s,]*(.+)?/', $l, $m)) {
            $cp = $m[1];
            // Intentar extraer ciudad y provincia del resto
            $resto = trim(preg_replace('/\d{5}\.?\s*/', '', $l));
            // "Ciudad.Provincia" o "Ciudad, Provincia" o solo "Ciudad"
            $partes = preg_split('/[.,\/]/', $resto, 2);
            $ciudad    = trim($partes[0] ?? '');
            $provincia = trim($partes[1] ?? '');
            continue;
        }

        // ¿Dirección? (empieza con C/, Av, Calle, Pza, Pol., etc.)
        if (preg_match('/^(C\/|Av(da)?\.?|Calle|Plaza|Pza\.?|Pol\.|Crta\.?|Carretera|Paseo|Ronda|Camino|Urb\.?)/i', $l)) {
            $direccion = $l;
            continue;
        }

        // ¿Teléfono / Email / Web? → ignorar
        if (preg_match('/^(Tel[eé]?f?\.?|Tlf|Phone|www\.|http|@)/i', $l)) continue;
        if (preg_match('/\b\d{9}\b/', $l) && strlen($l) < 20) continue; // solo número de tel

        // ¿Parece nombre de empresa? (contiene S.L., S.A., SLU, SL, SA, etc. o es solo letras)
        if (!$nombre) {
            $nombre = $l;
        }
    }

    // Si la dirección incluye ciudad al final ("C/ Calle 7, Ciudad"), separar
    if ($direccion && !$ciudad) {
        if (preg_match('/^(.+),\s*([A-ZÁÉÍÓÚ][a-záéíóú\s]+)$/', $direccion, $m)) {
            $direccion = trim($m[1]);
            $ciudad = trim($m[2]);
        }
    }

    $result['nombre']    = $nombre;
    $result['direccion'] = $direccion;
    $result['cp']        = $cp;
    $result['ciudad']    = trim($ciudad);
    $result['provincia'] = trim($provincia);
    return $result;
}

// ─── Extraer número de factura ───────────────────────────────────────────────
function extractNumero(string $text): string {
    $patterns = [
        '/(?:Factura|Fra\.?|Invoice|N[uú]mero\s+de\s+factura|Nº\.?\s*Factura)[:\s#nNº°]*([A-Z0-9][A-Z0-9\-\/\.]{2,19})/iu',
        '/(?:Nº|N°|Num\.?)[:\s]*([A-Z0-9][A-Z0-9\-\/\.]{2,19})/iu',
        '/\b(FAC|FRA|INV|F)[-\/]?\d{4}[-\/]\d{2,6}\b/i',
    ];
    foreach ($patterns as $p) {
        if (preg_match($p, $text, $m)) return trim($m[1] ?? $m[0]);
    }
    return '';
}

// ─── Extraer fecha ───────────────────────────────────────────────────────────
function extractFecha(string $text): string {
    $months = ['ene'=>1,'feb'=>2,'mar'=>3,'abr'=>4,'may'=>5,'jun'=>6,
               'jul'=>7,'ago'=>8,'sep'=>9,'oct'=>10,'nov'=>11,'dic'=>12,
               'jan'=>1,'apr'=>4,'aug'=>8,'dec'=>12];
    if (preg_match('/\b(\d{1,2})[\/\-\.](\d{1,2})[\/\-\.](\d{4})\b/', $text, $m)) {
        if (intval($m[2]) <= 12 && intval($m[1]) <= 31)
            return sprintf('%04d-%02d-%02d', $m[3], $m[2], $m[1]);
    }
    if (preg_match('/\b(\d{4})[\/\-\.](\d{2})[\/\-\.](\d{2})\b/', $text, $m))
        return "$m[1]-$m[2]-$m[3]";
    if (preg_match('/\b(\d{1,2})\s+(?:de\s+)?([a-záéíóú]+)\.?\s+(?:de\s+)?(\d{4})\b/iu', $text, $m)) {
        $mes = strtolower(substr($m[2], 0, 3));
        if (isset($months[$mes]))
            return sprintf('%04d-%02d-%02d', $m[3], $months[$mes], $m[1]);
    }
    return date('Y-m-d');
}

// ─── Parsear importe en formato español/internacional ────────────────────────
function parseAmount(string $s): float {
    $s = trim($s);
    if (preg_match('/^\d{1,3}(\.\d{3})+(,\d{1,2})?$/', $s)) {
        $s = str_replace(['.', ','], ['', '.'], $s);
    } elseif (strpos($s, ',') !== false && strpos($s, '.') === false) {
        $s = str_replace(',', '.', $s);
    } elseif (strpos($s, ',') !== false && strpos($s, '.') !== false) {
        if (strrpos($s, ',') > strrpos($s, '.')) {
            $s = str_replace(['.', ','], ['', '.'], $s);
        } else {
            $s = str_replace(',', '', $s);
        }
    }
    return round((float)$s, 2);
}

// ─── Extraer importes ────────────────────────────────────────────────────────
function extractImportes(string $text): array {
    $base = 0.0; $cuota = 0.0; $total = 0.0; $pct = 21;
    if (preg_match('/(?:IVA|I\.V\.A\.)\s*\(?\s*(\d{1,2})\s*%/i', $text, $m)) $pct = intval($m[1]);
    $ap = '([\d]{1,3}(?:[.,]\d{3})*(?:[.,]\d{1,2})?)';
    if (preg_match('/(?:Base\s+imponible|Base)[:\s]+' . $ap . '/iu', $text, $m)) $base = parseAmount($m[1]);
    if (preg_match('/(?:Cuota\s+IVA|IVA\s+\d{1,2}%|IVA)[:\s]+' . $ap . '/iu', $text, $m)) $cuota = parseAmount($m[1]);
    if (preg_match('/(?:Total\s+factura|Total\s+a\s+pagar|TOTAL|Importe\s+Total)[:\s]+' . $ap . '/iu', $text, $m)) $total = parseAmount($m[1]);
    if ($base > 0 && $total > 0 && $cuota == 0) $cuota = round($total - $base, 2);
    if ($base > 0 && $cuota > 0 && $total == 0) $total = round($base + $cuota, 2);
    if ($base == 0 && $total > 0) { $base = round($total / (1 + $pct / 100), 2); $cuota = round($total - $base, 2); }
    if ($base > 0 && $cuota > 0) { $c = round($cuota / $base * 100); if (in_array($c, [4, 10, 21])) $pct = $c; }
    return ['base' => $base, 'cuota_iva' => $cuota, 'total' => $total, 'pct_iva' => $pct];
}

// ─── Extraer descripción ─────────────────────────────────────────────────────
function extractDescripcion(string $text): string {
    foreach (explode("\n", $text) as $i => $line) {
        $lower = strtolower($line);
        foreach (['concepto', 'descripci', 'detalle', 'servicio'] as $kw) {
            if (strpos($lower, $kw) !== false) {
                $desc = trim(preg_replace('/^[^:]+:\s*/i', '', $line));
                return strlen($desc) > 5 ? $desc : '';
            }
        }
    }
    return '';
}

// ══════════════════════════════════════════════════════════════════════════════
// PARSEAR
// ══════════════════════════════════════════════════════════════════════════════
$nif      = extractNif($fullText);
$bloque   = extractBloqueFiscal($lines, $nif);
$numero   = extractNumero($fullText);
$fecha    = extractFecha($fullText);
$importes = extractImportes($fullText);
$desc     = extractDescripcion($fullText);

$nombre    = $bloque['nombre'];
$direccion = $bloque['direccion'];
$cp        = $bloque['cp'];
$ciudad    = $bloque['ciudad'];
$provincia = $bloque['provincia'];

// ─── Buscar proveedor en BD por NIF ─────────────────────────────────────────
$proveedorId     = null;
$proveedorNombre = $nombre;

if ($nif) {
    $st = getDB()->prepare("SELECT id, nombre FROM proveedores WHERE nif = ? AND activo = 1 LIMIT 1");
    $st->execute([$nif]);
    $prov = $st->fetch();
    if ($prov) { $proveedorId = $prov['id']; $proveedorNombre = $prov['nombre']; }
} elseif ($nombre) {
    $st = getDB()->prepare("SELECT id, nombre FROM proveedores WHERE nombre LIKE ? AND activo = 1 LIMIT 1");
    $st->execute(['%' . $nombre . '%']);
    $prov = $st->fetch();
    if ($prov) { $proveedorId = $prov['id']; $proveedorNombre = $prov['nombre']; }
}

// ─── Respuesta ───────────────────────────────────────────────────────────────
echo json_encode([
    'ok'               => true,
    'nif'              => $nif,
    'nombre'           => $nombre,
    'direccion'        => $direccion,
    'cp'               => $cp,
    'ciudad'           => $ciudad,
    'provincia'        => $provincia,
    'numero'           => $numero,
    'fecha'            => $fecha,
    'base'             => $importes['base'],
    'pct_iva'          => $importes['pct_iva'],
    'cuota_iva'        => $importes['cuota_iva'],
    'total'            => $importes['total'],
    'descripcion'      => $desc,
    'proveedor_id'     => $proveedorId,
    'proveedor_nombre' => $proveedorNombre,
    'proveedor_nuevo'  => $proveedorId === null,
], JSON_UNESCAPED_UNICODE);

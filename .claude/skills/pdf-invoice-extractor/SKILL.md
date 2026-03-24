# Skill: pdf-invoice-extractor

## Activación
Activar cuando trabajes con `compras/importar_pdf.php`, `compras/nueva.php` (sección drag & drop), o cualquier tarea relacionada con extracción de datos de facturas PDF de proveedores.

---

## Arquitectura del extractor

### Librería usada: `smalot/pdfparser`
- Ubicación: `vendor/pdfparser/`
- Autoload: `vendor/pdfparser/autoload.php`
- Clase principal: `\Smalot\PdfParser\Parser`
- Repositorio original: github.com/smalot/pdfparser
- **Versión vendored** (no gestionada por Composer) — cualquier actualización debe hacerse manualmente sustituyendo los archivos en `vendor/pdfparser/`.

### Flujo completo de importación

```
Usuario arrastra PDF en compras/nueva.php
    ↓
JavaScript (PDF.js) — extrae texto del PDF en el navegador
    ↓  [POST multipart con el archivo]
compras/importar_pdf.php — endpoint AJAX PHP
    ↓
smalot/pdfparser → $pdf->getText() → texto plano
    ↓
Regex sobre texto → array $data con campos de la factura
    ↓
Búsqueda de proveedor en BD por NIF, luego por nombre LIKE
    ↓
JSON response → JavaScript auto-rellena el formulario de compra
```

### Uso de la librería

```php
require_once __DIR__ . '/../vendor/pdfparser/autoload.php';

$parser = new \Smalot\PdfParser\Parser();
$pdf    = $parser->parseFile($pdfFile);   // recibe ruta del tmp file
$text   = $pdf->getText();                // string con todo el texto extraído
```

**Importante:** `parseFile()` recibe la ruta temporal de `$_FILES['pdf']['tmp_name']`. No la URL ni el nombre original.

---

## Regex implementadas en `compras/importar_pdf.php`

| Campo | Regex | Confianza |
|-------|-------|-----------|
| NIF/CIF | `/\b([A-Z]\d{7}[A-Z0-9]\|\d{8}[A-Z])\b/` | alta si hay match |
| Número factura | `/(?:factura\|fra\|invoice\|nº\|núm\|número)[^\w]*([A-Z0-9\/\-]{3,20})/i` | media |
| Fecha | `/\b(\d{1,2}[\/\-\.]\d{1,2}[\/\-\.]\d{2,4})\b/` | media |
| Base imponible | `/(?:base\s*imponible\|subtotal\|base)[^\d]*(\d+[.,]\d{2})/i` | alta si hay match |
| Total | `/(?:total\s*factura\|total\s*a\s*pagar\|importe\s*total\|total)[^\d]*(\d+[.,]\d{2})/i` | alta si hay match |
| % IVA | `/(\d{1,2})\s*%\s*(?:iva\|impuesto)/i` | — |
| Cuota IVA | `/(?:cuota\s*iva\|iva)[^\d]*(\d+[.,]\d{2})/i` | — |

### Normalización de fecha
El extractor convierte `dd/mm/yyyy`, `dd-mm-yy`, `dd.mm.yyyy` a formato MySQL `YYYY-MM-DD`. Si no detecta fecha, usa `date('Y-m-d')` como fallback.

### Detección del nombre de proveedor
Estrategia en dos pasos:
1. Busca el NIF en las líneas del texto → toma las 2 líneas adyacentes que no sean fechas ni números puros
2. Fallback: primera línea de las 3 iniciales que no sea dirección (Calle, C/, Avda…)

---

## Respuesta JSON del endpoint

```json
{
  "proveedor_nombre": "EMPRESA S.L.",
  "proveedor_nif": "B12345678",
  "proveedor_id": 42,
  "factura_numero": "F2026-001",
  "factura_fecha": "2026-03-01",
  "base_imponible": 100.00,
  "porcentaje_iva": 21,
  "cuota_iva": 21.00,
  "total": 121.00,
  "descripcion": "",
  "confianza": {
    "nif": "alta",
    "numero": "media",
    "base": "alta",
    "total": "alta",
    "fecha": "media"
  },
  "texto_completo": "Primeros 500 chars del PDF..."
}
```

`proveedor_id` es `null` si el proveedor no existe en BD. En ese caso el frontend muestra un enlace para crearlo desde `proveedores/nuevo.php?nombre=&nif=` (pre-relleno por URL).

---

## Seguridad del endpoint

```php
// Validaciones obligatorias (ya implementadas):
if (empty($_SESSION['usuario_id'])) → 401
if ($_FILES['pdf']['error'] !== UPLOAD_ERR_OK) → error JSON
// NO se guarda el archivo — solo se lee del tmp_name
// NO se usan datos del nombre original del archivo
```

---

## Gotchas — Errores reales y limitaciones conocidas

### 1. PDFs escaneados (imágenes) no extraen texto
**Problema:** `smalot/pdfparser` extrae texto de PDFs con texto embebido. Si el PDF es un escáner (imagen), `$pdf->getText()` devuelve string vacío o con menos de 10 caracteres.
**Comportamiento actual:** El endpoint devuelve `{'error': 'No se pudo extraer texto del PDF.'}`.
**Solución futura si se necesita:** OCR con Tesseract (no implementado).

### 2. Importes con formato europeo vs americano
**Problema:** Facturas españolas usan `1.234,56` pero el regex captura `\d+[.,]\d{2}`, que puede coger el separador de miles.
**Actual:** Se hace `str_replace(',', '.', $m[1])` — funciona solo si el número no tiene miles separados con punto.
**Si falla:** El campo quedará a 0.0 y el usuario lo corrige manualmente.

### 3. NIF en el campo incorrecto (NIF del cliente en lugar del proveedor)
**Problema:** Si la factura del proveedor incluye también el NIF del receptor (el autónomo), el primer NIF que capture el regex puede ser el propio, no el del proveedor.
**Mitigación actual:** Búsqueda por NIF en tabla `proveedores` — si no coincide, el `proveedor_id` queda null.

### 4. Encoding del texto extraído
**Problema:** Algunos PDFs tienen texto en encodings distintos (ISOLatin, WinAnsi). `smalot/pdfparser` tiene clases de encoding en `vendor/pdfparser/src/Smalot/PdfParser/Encoding/` que lo gestionan, pero no siempre funciona perfectamente con fuentes embebidas.
**Síntoma:** Texto con caracteres extraños o ilegibles tras `getText()`.

### 5. `vendor/pdfparser` no tiene Composer
**Problema:** No existe `composer.json` ni `vendor/autoload.php` gestionado. El autoload es custom (`vendor/pdfparser/autoload.php`).
**Consecuencia:** Para actualizar la librería hay que sustituir manualmente los archivos. No ejecutar `composer update`.

### 6. `$_FILES` no disponible sin enctype correcto
**Problema:** El formulario de upload debe tener `enctype="multipart/form-data"`. Sin él, `$_FILES` está vacío.
**En `compras/nueva.php`:** El panel de importación usa `FormData` vía JavaScript con `fetch()`, que sí envía multipart automáticamente.

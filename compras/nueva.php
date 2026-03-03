<?php
$pageTitle = 'Nueva factura recibida';
require_once __DIR__ . '/../includes/header.php';

$id         = (int)get('id');
$isEdit     = (bool)$id;
$proveedores = getProveedores();

$fr = null;
if ($isEdit) {
    $st = getDB()->prepare("SELECT * FROM facturas_recibidas WHERE id=?");
    $st->execute([$id]); $fr = $st->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $provId     = (int)post('proveedor_id');
    $proveedor  = $provId ? getProveedor($provId) : null;
    $fecha      = post('fecha') ?: date('Y-m-d');
    $numero     = post('numero');
    $base       = (float)str_replace(',', '.', post('base_imponible'));
    $pct_iva    = (float)post('pct_iva', 21);
    $cuota_iva  = round($base * $pct_iva / 100, 2);
    $total      = $base + $cuota_iva;
    $descripcion= post('descripcion');
    $notas      = post('notas');
    $trim       = trimestre($fecha);

    if (!$numero) { $error = 'El número de factura es obligatorio.'; }
    elseif (!$base) { $error = 'La base imponible no puede ser cero.'; }
    else {
        $db = getDB();
        $data = [$fecha, $provId ?: null,
                 $proveedor['nombre'] ?? post('proveedor_nombre'),
                 $proveedor['nif'] ?? '',
                 $base, $pct_iva, $cuota_iva, $total,
                 $descripcion, $notas, $trim, $numero];
        if ($isEdit) {
            $db->prepare("UPDATE facturas_recibidas SET fecha=?,proveedor_id=?,proveedor_nombre=?,proveedor_nif=?,
                          base_imponible=?,porcentaje_iva=?,cuota_iva=?,total=?,descripcion=?,notas=?,trimestre=?,numero=?
                          WHERE id=?")->execute([...$data, $id]);
            flash('Factura actualizada.');
        } else {
            $db->prepare("INSERT INTO facturas_recibidas
                          (fecha,proveedor_id,proveedor_nombre,proveedor_nif,base_imponible,porcentaje_iva,cuota_iva,total,descripcion,notas,trimestre,numero)
                          VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")->execute($data);
            flash('Factura de compra registrada.');
        }
        redirect('/compras/');
    }
}
?>

<div class="topbar">
  <h1><i class="bi bi-bag me-2"></i><?= $isEdit ? 'Editar factura recibida' : 'Nueva factura recibida' ?></h1>
  <a href="/compras/" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Volver</a>
</div>

<?php if (!empty($error)): ?>
<div class="alert alert-danger"><?= e($error) ?></div>
<?php endif; ?>

<?php if (!$isEdit): ?>
<!-- ═══ PANEL IMPORTAR PDF ═══ -->
<div class="card mb-3" id="pdfImportCard" style="max-width:700px">
  <div class="card-header d-flex justify-content-between align-items-center" style="cursor:pointer" onclick="togglePdfPanel()">
    <span><i class="bi bi-file-earmark-pdf me-2"></i>Importar desde PDF <small class="text-warning ms-2" style="font-size:.72rem;font-weight:400">NUEVO</small></span>
    <i class="bi bi-chevron-down" id="pdfChevron"></i>
  </div>
  <div id="pdfPanel">
    <div class="card-body">
      <p class="text-muted mb-3" style="font-size:.85rem">
        Sube la factura en PDF y los campos se rellenarán automáticamente. El archivo se procesa en tu navegador y <strong>nunca se sube al servidor</strong>.
      </p>

      <!-- Zona drag & drop -->
      <div id="pdfDropzone"
           onclick="document.getElementById('pdfFileInput').click()"
           ondragover="event.preventDefault();this.classList.add('drag-over')"
           ondragleave="this.classList.remove('drag-over')"
           ondrop="handleDrop(event)"
           style="border:2px dashed var(--verde-a);border-radius:12px;padding:2.5rem 1rem;text-align:center;cursor:pointer;transition:all .2s;background:#f8fbf9">
        <i class="bi bi-cloud-upload" style="font-size:2.5rem;color:var(--verde-a)"></i>
        <div style="margin-top:.75rem;color:#374151;font-size:.9rem">Arrastra la factura PDF aquí o <strong>haz clic para seleccionar</strong></div>
        <div style="font-size:.75rem;color:#9ca3af;margin-top:.35rem">Solo archivos PDF · máx. 20 MB</div>
        <input type="file" id="pdfFileInput" accept=".pdf,application/pdf" style="display:none">
      </div>

      <!-- Estado del procesamiento -->
      <div id="pdfStatus" class="mt-3" style="display:none">
        <div class="d-flex align-items-center gap-2 mb-2">
          <div class="spinner-border spinner-border-sm text-success" id="pdfSpinner"></div>
          <span id="pdfStatusText" style="font-size:.87rem">Procesando PDF…</span>
        </div>
        <div class="progress" style="height:5px">
          <div class="progress-bar bg-success" id="pdfProgress" style="width:0%;transition:width .3s"></div>
        </div>
      </div>

      <!-- Resultado -->
      <div id="pdfResult" class="mt-3" style="display:none"></div>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- ═══ FORMULARIO ═══ -->
<div class="card" style="max-width:700px">
  <div class="card-body p-4">
    <form method="post">
      <div class="row g-3">
        <div class="col-md-8">
          <label class="form-label">Proveedor</label>
          <select name="proveedor_id" id="selectProveedor" class="form-select">
            <option value="">— Selecciona o escribe —</option>
            <?php foreach ($proveedores as $p): ?>
            <option value="<?= $p['id'] ?>" <?= ($fr['proveedor_id'] ?? 0) == $p['id'] ? 'selected' : '' ?>>
              <?= e($p['nombre']) ?> <?= $p['nif'] ? '— ' . e($p['nif']) : '' ?>
            </option>
            <?php endforeach; ?>
          </select>
          <div class="form-text">¿Nuevo proveedor? <a href="/proveedores/nuevo.php" target="_blank">Créalo aquí</a></div>
        </div>
        <div class="col-md-4">
          <label class="form-label">Nº Factura proveedor *</label>
          <input type="text" name="numero" id="inputNumero" class="form-control" required value="<?= e($fr['numero'] ?? '') ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Fecha *</label>
          <input type="date" name="fecha" id="inputFecha" class="form-control" required value="<?= e($fr['fecha'] ?? date('Y-m-d')) ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Base imponible *</label>
          <div class="input-group">
            <input type="number" name="base_imponible" class="form-control" step="0.01" min="0" required
                   id="baseInput" value="<?= moneyInput($fr['base_imponible'] ?? 0) ?>" oninput="recalcCompra()">
            <span class="input-group-text">€</span>
          </div>
        </div>
        <div class="col-md-4">
          <label class="form-label">IVA %</label>
          <select name="pct_iva" class="form-select" id="pctIvaCompra" onchange="recalcCompra()">
            <?php foreach ([21, 10, 4, 0] as $t): ?>
            <option value="<?= $t ?>" <?= ($fr['porcentaje_iva'] ?? 21) == $t ? 'selected' : '' ?>><?= $t ?>%</option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label">Cuota IVA</label>
          <div class="input-group">
            <input type="text" class="form-control" id="cuotaIvaDisplay" readonly>
            <span class="input-group-text">€</span>
          </div>
        </div>
        <div class="col-md-4">
          <label class="form-label">Total factura</label>
          <div class="input-group">
            <input type="text" class="form-control fw-bold" id="totalDisplay" readonly>
            <span class="input-group-text">€</span>
          </div>
        </div>
        <div class="col-12">
          <label class="form-label">Descripción / Concepto</label>
          <input type="text" name="descripcion" id="inputDescripcion" class="form-control" value="<?= e($fr['descripcion'] ?? '') ?>" placeholder="Ej: Material eléctrico, servicios de hosting, etc.">
        </div>
        <div class="col-12">
          <label class="form-label">Notas internas</label>
          <textarea name="notas" class="form-control" rows="2"><?= e($fr['notas'] ?? '') ?></textarea>
        </div>
        <div class="col-12 pt-2">
          <button type="submit" class="btn btn-gold px-4">
            <i class="bi bi-check-lg me-1"></i><?= $isEdit ? 'Guardar cambios' : 'Registrar factura' ?>
          </button>
        </div>
      </div>
    </form>
  </div>
</div>

<style>
#pdfDropzone.drag-over { background: #e8f4f0 !important; border-color: var(--verde) !important; }
#pdfImportCard .card-header { background: var(--verde-m); }
</style>

<script type="module">
// ── PDF.js ──────────────────────────────────────────────────────────────────
import * as pdfjsLib from 'https://cdn.jsdelivr.net/npm/pdfjs-dist@4.2.67/build/pdf.min.mjs';
pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdn.jsdelivr.net/npm/pdfjs-dist@4.2.67/build/pdf.worker.min.mjs';

// ── TomSelect ───────────────────────────────────────────────────────────────
// (se inicializa en el script clásico de abajo para evitar conflicto de módulos)

// ── Extrae todo el texto del PDF ────────────────────────────────────────────
async function extractPdfText(file) {
    const arrayBuffer = await file.arrayBuffer();
    const pdf = await pdfjsLib.getDocument({ data: arrayBuffer }).promise;
    let text = '';
    for (let i = 1; i <= pdf.numPages; i++) {
        const page = await pdf.getPage(i);
        const content = await page.getTextContent();
        text += content.items.map(s => s.str).join(' ') + '\n';
    }
    return text.trim();
}

// ── Procesa el PDF y rellena el formulario ──────────────────────────────────
async function processPdf(file) {
    if (!file || file.type !== 'application/pdf') {
        showResult('error', 'El archivo debe ser un PDF.');
        return;
    }
    if (file.size > 20 * 1024 * 1024) {
        showResult('error', 'El archivo es demasiado grande (máx. 20 MB).');
        return;
    }

    setStatus(true, 'Extrayendo texto del PDF…', 30);

    let text;
    try {
        text = await extractPdfText(file);
    } catch (err) {
        showResult('error', 'No se pudo leer el PDF: ' + err.message);
        setStatus(false);
        return;
    }

    if (!text || text.length < 20) {
        showResult('error', 'No se pudo extraer texto. Es posible que el PDF sea una imagen escaneada.');
        setStatus(false);
        return;
    }

    setStatus(true, 'Analizando la factura…', 70);

    let data;
    try {
        const resp = await fetch('parse_pdf.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ text })
        });
        data = await resp.json();
    } catch (err) {
        showResult('error', 'Error de red al analizar la factura.');
        setStatus(false);
        return;
    }

    setStatus(true, '¡Listo!', 100);
    setTimeout(() => setStatus(false), 600);

    if (data.error) {
        showResult('error', data.error);
        return;
    }

    // ── Rellenar formulario ───────────────────────────────────────────────
    if (data.numero) document.getElementById('inputNumero').value = data.numero;
    if (data.fecha)  document.getElementById('inputFecha').value  = data.fecha;
    if (data.base)   { document.getElementById('baseInput').value = data.base.toFixed(2); }
    if (data.descripcion) document.getElementById('inputDescripcion').value = data.descripcion;

    // IVA %
    const sel = document.getElementById('pctIvaCompra');
    [21, 10, 4, 0].forEach(v => { if (v === data.pct_iva) sel.value = v; });

    // Proveedor
    if (data.proveedor_id) {
        // Seleccionar en TomSelect
        window._tomSelectProveedor?.setValue(String(data.proveedor_id));
    }

    recalcCompra();

    // ── Mostrar resumen ───────────────────────────────────────────────────
    const nuevoBadge = data.proveedor_nuevo
        ? `<div class="alert alert-warning mt-2 mb-0 py-2" style="font-size:.82rem">
             <i class="bi bi-person-plus me-1"></i>
             Proveedor <strong>${escHtml(data.nombre || data.nif || 'desconocido')}</strong>
             no encontrado en la BD.
             <a href="/proveedores/nuevo.php?nombre=${encodeURIComponent(data.nombre)}&nif=${encodeURIComponent(data.nif)}&direccion=${encodeURIComponent(data.direccion||'')}&cp=${encodeURIComponent(data.cp||'')}&ciudad=${encodeURIComponent(data.ciudad||'')}&provincia=${encodeURIComponent(data.provincia||'')}"
                target="_blank" class="btn btn-sm btn-warning ms-2 py-0">
               <i class="bi bi-plus-lg"></i> Crear proveedor
             </a>
             <div class="form-text mt-1">Crea el proveedor y <a href="" onclick="location.reload();return false">recarga esta página</a> para vincularlo.</div>
           </div>`
        : `<div class="text-success" style="font-size:.82rem"><i class="bi bi-check-circle me-1"></i>Proveedor identificado: <strong>${escHtml(data.proveedor_nombre)}</strong></div>`;

    showResult('success',
        `<div class="d-flex flex-wrap gap-3 mb-2" style="font-size:.83rem">
           <span><i class="bi bi-hash me-1 text-muted"></i><strong>${escHtml(data.numero || '—')}</strong></span>
           <span><i class="bi bi-calendar3 me-1 text-muted"></i>${escHtml(data.fecha || '—')}</span>
           <span><i class="bi bi-cash me-1 text-muted"></i>Base: <strong>${fmt(data.base)}</strong></span>
           <span>IVA ${data.pct_iva}%: ${fmt(data.cuota_iva)}</span>
           <span class="fw-bold">Total: ${fmt(data.total)}</span>
         </div>` + nuevoBadge
    );
}

function escHtml(s) {
    return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function fmt(v) {
    return (v||0).toLocaleString('es-ES',{minimumFractionDigits:2,maximumFractionDigits:2}) + ' €';
}
function setStatus(show, msg = '', pct = 0) {
    const el = document.getElementById('pdfStatus');
    el.style.display = show ? '' : 'none';
    if (show) {
        document.getElementById('pdfStatusText').textContent = msg;
        document.getElementById('pdfProgress').style.width = pct + '%';
        document.getElementById('pdfSpinner').style.display = pct >= 100 ? 'none' : '';
    }
}
function showResult(type, html) {
    const el = document.getElementById('pdfResult');
    el.style.display = '';
    el.innerHTML = type === 'error'
        ? `<div class="alert alert-danger py-2 mb-0" style="font-size:.85rem"><i class="bi bi-exclamation-triangle me-1"></i>${html}</div>`
        : `<div class="alert alert-success py-2 mb-0" style="font-size:.85rem"><i class="bi bi-magic me-1"></i><strong>Datos detectados:</strong><br>${html}</div>`;
}

// ── Event listeners ─────────────────────────────────────────────────────────
document.getElementById('pdfFileInput')?.addEventListener('change', e => {
    if (e.target.files[0]) processPdf(e.target.files[0]);
});
window.handleDrop = function(e) {
    e.preventDefault();
    document.getElementById('pdfDropzone').classList.remove('drag-over');
    const file = e.dataTransfer.files[0];
    if (file) processPdf(file);
};
</script>

<script>
// Script clásico (no módulo) para TomSelect y recalc
window._tomSelectProveedor = new TomSelect('#selectProveedor', { create: false, sortField: 'text' });

function fmt(v) { return v.toLocaleString('es-ES', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
function recalcCompra() {
    const base  = parseFloat(document.getElementById('baseInput').value)    || 0;
    const pct   = parseFloat(document.getElementById('pctIvaCompra').value) || 0;
    const cuota = Math.round(base * pct / 100 * 100) / 100;
    document.getElementById('cuotaIvaDisplay').value = fmt(cuota);
    document.getElementById('totalDisplay').value    = fmt(base + cuota);
}
function togglePdfPanel() {
    const panel   = document.getElementById('pdfPanel');
    const chevron = document.getElementById('pdfChevron');
    const hidden  = panel.style.display === 'none';
    panel.style.display  = hidden ? '' : 'none';
    chevron.className = hidden ? 'bi bi-chevron-up' : 'bi bi-chevron-down';
}
recalcCompra();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>


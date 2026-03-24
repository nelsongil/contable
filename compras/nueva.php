<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$id         = (int)get('id');
$isEdit     = (bool)$id;
$proveedores = getProveedores();

$fr = null;
if ($isEdit) {
    $st = getDB()->prepare("SELECT * FROM facturas_recibidas WHERE id=?");
    $st->execute([$id]); $fr = $st->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post('action');
    
    if ($action === 'import_pdf_save') {
        // Lógica especial para importación AJAX en 2 pasos
        $provId    = (int)post('proveedor_id');
        $crearProv = post('crear_prov') === 'true';
        $db = getDB();

        if (!$provId && $crearProv) {
            // Crear nuevo proveedor
            $nombre = post('prov_nombre');
            $nif    = post('prov_nif');
            $dir    = post('prov_dir');
            $st = $db->prepare("INSERT INTO proveedores (nombre, nif, direccion, activo) VALUES (?, ?, ?, 1)");
            $st->execute([$nombre, $nif, $dir]);
            $provId = $db->lastInsertId();
        }

        $fecha      = post('fecha') ?: date('Y-m-d');
        $numero     = post('numero');
        $base       = (float)post('base_imponible');
        $pct_iva    = (float)post('pct_iva', 21);
        $cuota_iva  = round($base * $pct_iva / 100, 2);
        $total      = $base + $cuota_iva;
        $descripcion= post('descripcion');
        $trim       = trimestre($fecha);

        // Obtener datos finales del proveedor (por si se acaba de crear o ya existía)
        $st = $db->prepare("SELECT nombre, nif FROM proveedores WHERE id = ?");
        $st->execute([$provId]);
        $p = $st->fetch();

        $data = [$fecha, $provId ?: null, $p['nombre'] ?? 'Desconocido', $p['nif'] ?? '',
                 $base, $pct_iva, $cuota_iva, $total, $descripcion, '', $trim, $numero];
        
        $db->prepare("INSERT INTO facturas_recibidas
                      (fecha,proveedor_id,proveedor_nombre,proveedor_nif,base_imponible,porcentaje_iva,cuota_iva,total,descripcion,notas,trimestre,numero)
                      VALUES (?,?,?,?,?,?,?,?,?,?,?,?)")->execute($data);
        
        flash('Factura importada y guardada correctamente.');
        echo json_encode(['ok' => true]);
        exit;
    }

    // Lógica normal de formulario
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

$pageTitle = 'Nueva factura recibida';
require_once __DIR__ . '/../includes/header.php';
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
        Sube la factura en PDF y los campos se rellenarán automáticamente gracias a la extracción local en el servidor.
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
    <form method="post" id="formCompra">
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
          <div class="form-floating">
            <input type="text" name="numero" id="inputNumero" class="form-control" placeholder="Nº Factura" required value="<?= e($fr['numero'] ?? '') ?>">
            <label for="inputNumero">Nº Factura proveedor *</label>
          </div>
        </div>
        <div class="col-md-4">
          <div class="form-floating">
            <input type="date" name="fecha" id="inputFecha" class="form-control" placeholder="Fecha" required value="<?= e($fr['fecha'] ?? date('Y-m-d')) ?>">
            <label for="inputFecha">Fecha *</label>
          </div>
        </div>
        <div class="col-md-4">
          <div class="form-floating">
            <input type="number" name="base_imponible" class="form-control" step="0.01" min="0" required
                   id="baseInput" value="<?= moneyInput($fr['base_imponible'] ?? 0) ?>" oninput="recalcCompra()" placeholder="Base">
            <label for="baseInput">Base imponible *</label>
          </div>
        </div>
        <div class="col-md-4">
          <label class="form-label">IVA %</label>
          <select name="pct_iva" class="form-select" id="pctIvaCompra" onchange="recalcCompra()">
            <?php 
              $defIva = (int)getConfig('empresa_iva_def', 21);
              foreach ([21, 10, 4, 0] as $t): 
            ?>
            <option value="<?= $t ?>" <?= ($fr['porcentaje_iva'] ?? $defIva) == $t ? 'selected' : '' ?>><?= $t ?>%</option>
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
          <div class="form-floating">
            <input type="text" name="descripcion" id="inputDescripcion" class="form-control" value="<?= e($fr['descripcion'] ?? '') ?>" placeholder="Concepto">
            <label for="inputDescripcion">Descripción / Concepto</label>
          </div>
        </div>
        <div class="col-12">
          <label class="form-label">Notas internas</label>
          <textarea name="notas" class="form-control" rows="2"><?= e($fr['notas'] ?? '') ?></textarea>
        </div>
        <div class="col-12 pt-2">
          <button type="submit" class="btn btn-gold px-4" id="btnSubmit">
            <span class="spinner-border spinner-border-sm d-none me-1" id="submitSpinner"></span>
            <i class="bi bi-check-lg me-1" id="submitIcon"></i>
            <span id="submitText"><?= $isEdit ? 'Guardar cambios' : 'Registrar factura' ?></span>
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
// ── PDF.js — DESACTIVADO (ahora se procesa en servidor) ───────────
/*
import * as pdfjsLib from 'https://cdn.jsdelivr.net/npm/pdfjs-dist@4.2.67/build/pdf.min.mjs';
pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdn.jsdelivr.net/npm/pdfjs-dist@4.2.67/build/pdf.worker.min.mjs';

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
*/

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

    setStatus(true, 'Subiendo y analizando factura en el servidor…', 50);

    let data;
    try {
        const formData = new FormData();
        formData.append('pdf', file);

        const resp = await fetch('importar_pdf.php', {
            method: 'POST',
            body: formData
        });
        data = await resp.json();
    } catch (err) {
        showResult('error', 'Error al procesar la factura en el servidor.');
        setStatus(false);
        return;
    }

    setStatus(true, '¡Listo!', 100);
    setTimeout(() => setStatus(false), 600);

    if (data.error) {
        showResult('error', data.error);
        return;
    }

    // ─── Renderizar Formulario de Confirmación 2-pasos ────────────────────
    
    // Función para obtener clase CSS de confianza
    const getConfClass = (level) => {
        if (level === 'alta') return 'border-success bg-success-subtle';
        if (level === 'media') return 'border-warning bg-warning-subtle';
        return 'border-danger bg-danger-subtle';
    };

    // Checkbox para nuevo proveedor si no hay match
    let providerInfo = '';
    if (data.proveedor_id) {
        providerInfo = `<div class="alert alert-success py-2 mb-2" style="font-size:.85rem">
            <i class="bi bi-check-circle-fill me-1"></i> Proveedor encontrado: <strong>${escHtml(data.proveedor_nombre)}</strong>
            <input type="hidden" id="import_prov_id" value="${data.proveedor_id}">
        </div>`;
    } else {
        providerInfo = `<div class="alert alert-warning py-2 mb-2" style="font-size:.85rem">
            <i class="bi bi-exclamation-triangle-fill me-1"></i> Proveedor no encontrado.
            <div class="form-check mt-1">
                <input class="form-check-input" type="checkbox" id="import_crear_prov" checked>
                <label class="form-check-label" for="import_crear_prov" style="font-size:.75rem">
                    ¿Crear nuevo proveedor con estos datos?
                </label>
            </div>
            <div class="mt-2 p-2 bg-white rounded border ${getConfClass(data.confianza.nif)}" style="font-size:.78rem;color:#4b5563">
                <strong>Nombre:</strong> <span id="import_prov_nombre_display">${escHtml(data.proveedor_nombre)}</span><br>
                <strong>NIF:</strong> <span id="import_prov_nif_display">${escHtml(data.proveedor_nif)}</span>
            </div>
        </div>`;
    }

    const html = `
        <div class="verification-step bg-white p-3 border rounded shadow-sm">
            <h6 class="mb-3" style="font-size:.9rem;color:var(--verde-a)"><i class="bi bi-1-circle me-1"></i> Paso 1: Revisar y confirmar datos</h6>
            
            ${providerInfo}

            <div class="row g-2 mt-2">
                <div class="col-md-6 text-start">
                    <label class="form-label mb-0" style="font-size:.75rem">Nº Factura</label>
                    <input type="text" id="import_numero" class="form-control form-control-sm ${getConfClass(data.confianza.numero)}" value="${escHtml(data.factura_numero)}">
                </div>
                <div class="col-md-6 text-start">
                    <label class="form-label mb-0" style="font-size:.75rem">Fecha</label>
                    <input type="date" id="import_fecha" class="form-control form-control-sm ${getConfClass(data.confianza.fecha)}" value="${data.factura_fecha}">
                </div>
                <div class="col-md-5 text-start">
                    <label class="form-label mb-0" style="font-size:.75rem">Base Imponible (€)</label>
                    <input type="number" step="0.01" id="import_base" class="form-control form-control-sm ${getConfClass(data.confianza.base)}" value="${data.base_imponible.toFixed(2)}" oninput="recalcImportModal()">
                </div>
                <div class="col-md-3 text-start">
                    <label class="form-label mb-0" style="font-size:.75rem">IVA %</label>
                    <select id="import_iva" class="form-select form-select-sm" onchange="recalcImportModal()">
                        <option value="21" ${data.porcentaje_iva == 21 ? 'selected' : ''}>21%</option>
                        <option value="10" ${data.porcentaje_iva == 10 ? 'selected' : ''}>10%</option>
                        <option value="4" ${data.porcentaje_iva == 4 ? 'selected' : ''}>4%</option>
                        <option value="0" ${data.porcentaje_iva == 0 ? 'selected' : ''}>0%</option>
                    </select>
                </div>
                <div class="col-md-4 text-start">
                    <label class="form-label mb-0" style="font-size:.75rem">Total (€)</label>
                    <input type="text" id="import_total_display" class="form-control form-control-sm fw-bold ${getConfClass(data.confianza.total)}" readonly value="${fmt(data.total)}">
                </div>
                <div class="col-12 text-start">
                    <label class="form-label mb-0" style="font-size:.75rem">Concepto / Descripción</label>
                    <input type="text" id="import_desc" class="form-control form-control-sm" value="${escHtml(data.descripcion)}" maxlength="100">
                </div>
            </div>

            <div class="mt-2 text-muted" style="font-size:.7rem; font-style: italic;">
                * Colores indican nivel de confianza en la detección automática.
            </div>

            <div class="mt-4 text-end border-top pt-3">
                <button type="button" class="btn btn-sm btn-outline-secondary me-2" onclick="document.getElementById('pdfResult').style.display='none'">Cancelar</button>
                <button type="button" class="btn btn-sm btn-success px-4" id="btnConfirmImport">
                    <i class="bi bi-check-lg me-1"></i> Confirmar y guardar
                </button>
            </div>
        </div>
    `;

    showResult('info', html);

    // Función interna para recalcular en el modal
    window.recalcImportModal = () => {
        const base = parseFloat(document.getElementById('import_base').value) || 0;
        const pct  = parseFloat(document.getElementById('import_iva').value)  || 0;
        const total = base * (1 + pct/100);
        document.getElementById('import_total_display').value = total.toLocaleString('es-ES', {minimumFractionDigits:2, maximumFractionDigits:2}) + ' €';
    };

    // Evento para el botón de confirmar final
    document.getElementById('btnConfirmImport').onclick = async () => {
        const btn = document.getElementById('btnConfirmImport');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Guardando…';

        const finalData = {
            proveedor_id:   document.getElementById('import_prov_id')?.value || null,
            crear_prov:     document.getElementById('import_crear_prov')?.checked || false,
            prov_nombre:    data.proveedor_nombre,
            prov_nif:       data.proveedor_nif,
            prov_dir:       data.proveedor_direccion,
            numero:         document.getElementById('import_numero').value,
            fecha:          document.getElementById('import_fecha').value,
            base_imponible: document.getElementById('import_base').value,
            pct_iva:        document.getElementById('import_iva').value,
            descripcion:    document.getElementById('import_desc').value,
            action:         'import_pdf_save'
        };

        try {
            const resp = await fetch('nueva.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams(finalData)
            });
            
            const result = await resp.json();
            if (result.ok) {
                window.location.href = '/compras/';
            } else {
                showResult('error', result.error || 'Error al guardar la factura. Revisa los datos.');
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-check-lg me-1"></i> Confirmar y guardar';
            }
        } catch (err) {
            showResult('error', 'Error de conexión al guardar.');
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-check-lg me-1"></i> Confirmar y guardar';
        }
    };
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
        : `<div class="p-1 mb-0">${html}</div>`;
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
document.addEventListener('DOMContentLoaded', function() {
    // Script clásico (no módulo) para TomSelect y recalc
    if (typeof TomSelect !== 'undefined') {
        window._tomSelectProveedor = new TomSelect('#selectProveedor', { create: false, sortField: 'text' });
    }

    window.fmt = function(v) { return v.toLocaleString('es-ES', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
    window.recalcCompra = function() {
        const base  = parseFloat(document.getElementById('baseInput').value)    || 0;
        const pct   = parseFloat(document.getElementById('pctIvaCompra').value) || 0;
        const cuota = Math.round(base * pct / 100 * 100) / 100;
        document.getElementById('cuotaIvaDisplay').value = window.fmt(cuota);
        document.getElementById('totalDisplay').value    = window.fmt(base + cuota);
    }
    window.togglePdfPanel = function() {
        const panel   = document.getElementById('pdfPanel');
        const chevron = document.getElementById('pdfChevron');
        const hidden  = panel.style.display === 'none';
        panel.style.display  = hidden ? '' : 'none';
        chevron.className = hidden ? 'bi bi-chevron-up' : 'bi bi-chevron-down';
    }
    window.recalcCompra();

    // ── Spinner en submit ────────────────────────────────
    document.getElementById('formCompra').addEventListener('submit', function() {
        const btn = document.getElementById('btnSubmit');
        const spinner = document.getElementById('submitSpinner');
        const icon = document.getElementById('submitIcon');
        const text = document.getElementById('submitText');
        
        btn.disabled = true;
        spinner.classList.remove('d-none');
        icon.classList.add('d-none');
        text.textContent = 'Guardando...';
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>


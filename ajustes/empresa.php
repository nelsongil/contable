<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// ── Procesar formulario ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Datos de empresa
    setConfig('empresa_nombre',   post('empresa_nombre'));
    setConfig('empresa_sociedad', post('empresa_sociedad'));
    setConfig('empresa_cif',      post('empresa_cif'));
    setConfig('empresa_dir1',     post('empresa_dir1'));
    setConfig('empresa_dir2',     post('empresa_dir2'));
    setConfig('empresa_tel',      post('empresa_tel'));
    setConfig('empresa_email',    post('empresa_email'));
    setConfig('empresa_web',      post('empresa_web'));
    setConfig('empresa_banco',    post('empresa_banco'));
    setConfig('empresa_iban',     post('empresa_iban'));
    setConfig('empresa_iva_def',  post('empresa_iva_def'));
    setConfig('empresa_irpf_def', post('empresa_irpf_def'));

    // Numeración
    setConfig('factura_prefijo',  post('factura_prefijo'));
    setConfig('factura_usa_anio', post('factura_usa_anio'));
    setConfig('factura_digitos',  post('factura_digitos'));
    setConfig('factura_proximo',  post('factura_proximo'));

    flash('Configuración de empresa guardada correctamente.');
    redirect('/ajustes/empresa.php');
}

$pageTitle = 'Configuración de Empresa';
require_once __DIR__ . '/../includes/header.php';

// Cargar valores actuales
$pref   = getConfig('factura_prefijo', 'F');
$usaAnio= getConfig('factura_usa_anio', true);
$dig    = (int)getConfig('factura_digitos', 5);
$prox   = (int)getConfig('factura_proximo', 1);
?>

<div class="topbar">
    <h1><i class="bi bi-building me-2"></i>Datos de Empresa y Numeración</h1>
    <div class="d-flex gap-2">
        <div class="dropdown">
            <button class="btn btn-outline-secondary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
                <i class="bi bi-gear me-1"></i> Herramientas
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow">
                <li><a class="dropdown-item" href="config_process.php?action=export&download=1"><i class="bi bi-download me-2"></i>Exportar Configuración</a></li>
                <li><a class="dropdown-item" href="#" onclick="document.getElementById('importFile').click()"><i class="bi bi-upload me-2"></i>Importar Configuración</a></li>
            </ul>
        </div>
        <button type="submit" form="formEmpresa" class="btn btn-gold btn-sm px-4">
            <i class="bi bi-save me-1"></i> Guardar cambios
        </button>
    </div>
</div>

<input type="file" id="importFile" class="d-none" accept=".json" onchange="handleImport(this)">

<form id="formEmpresa" method="POST">
    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card h-100">
                <div class="card-header">Información de la Empresa</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-12">
                            <label class="form-label">Nombre Comercial / Sociedad</label>
                            <input type="text" name="empresa_sociedad" class="form-control" value="<?= e(getConfig('empresa_sociedad', EMPRESA_SOCIEDAD)) ?>">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">Nombre del Titular</label>
                            <input type="text" name="empresa_nombre" class="form-control" value="<?= e(getConfig('empresa_nombre', EMPRESA_NOMBRE)) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">CIF / NIF</label>
                            <input type="text" name="empresa_cif" class="form-control" value="<?= e(getConfig('empresa_cif', EMPRESA_CIF)) ?>">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Dirección (Línea 1)</label>
                            <input type="text" name="empresa_dir1" class="form-control" value="<?= e(getConfig('empresa_dir1', EMPRESA_DIR1)) ?>">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Dirección (Línea 2 - Ciudad, CP, Prov)</label>
                            <input type="text" name="empresa_dir2" class="form-control" value="<?= e(getConfig('empresa_dir2', EMPRESA_DIR2)) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Teléfono</label>
                            <input type="text" name="empresa_tel" class="form-control" value="<?= e(getConfig('empresa_tel', EMPRESA_TEL)) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email</label>
                            <input type="email" name="empresa_email" class="form-control" value="<?= e(getConfig('empresa_email', EMPRESA_EMAIL)) ?>">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Sitio Web</label>
                            <input type="text" name="empresa_web" class="form-control" value="<?= e(getConfig('empresa_web', EMPRESA_WEB)) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Entidad Bancaria</label>
                            <input type="text" name="empresa_banco" class="form-control" value="<?= e(getConfig('empresa_banco', EMPRESA_BANCO)) ?>">
                        </div>
                        <div class="col-md-8">
                            <label class="form-label">IBAN</label>
                            <input type="text" name="empresa_iban" class="form-control" value="<?= e(getConfig('empresa_iban', EMPRESA_IBAN)) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">IVA por defecto</label>
                            <select name="empresa_iva_def" class="form-select">
                                <?php foreach([21, 10, 4, 0] as $v): ?>
                                <option value="<?= $v ?>" <?= (int)getConfig('empresa_iva_def', 21) === $v ? 'selected' : '' ?>><?= $v ?>%</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">IRPF por defecto</label>
                            <select name="empresa_irpf_def" class="form-select">
                                <?php foreach([0, 7, 15, 19] as $v): ?>
                                <option value="<?= $v ?>" <?= (int)getConfig('empresa_irpf_def', 15) === $v ? 'selected' : '' ?>><?= $v ?>%</option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card h-100">
                <div class="card-header">Configuración de Numeración</div>
                <div class="card-body">
                    <div class="mb-4 p-3 bg-light border rounded text-center">
                        <div class="text-muted small mb-1">Vista previa del formato</div>
                        <div class="h3 fw-bold mb-0" id="previewNumero" style="color: var(--verde-a)">F202600001</div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Prefijo</label>
                            <input type="text" name="factura_prefijo" id="prefInput" class="form-control" value="<?= e($pref) ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Incluir Año</label>
                            <select name="factura_usa_anio" id="anioInput" class="form-select">
                                <option value="true" <?= $usaAnio ? 'selected' : '' ?>>Sí (F2026...)</option>
                                <option value="false" <?= !$usaAnio ? 'selected' : '' ?>>No (F00001...)</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Próximo Número</label>
                            <input type="number" name="factura_proximo" id="proxInput" class="form-control" value="<?= $prox ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Nº de Dígitos</label>
                            <select name="factura_digitos" id="digInput" class="form-select">
                                <option value="4" <?= $dig === 4 ? 'selected' : '' ?>>4 dígitos</option>
                                <option value="5" <?= $dig === 5 ? 'selected' : '' ?>>5 dígitos</option>
                            </select>
                        </div>
                    </div>

                    <div class="mt-4 alert alert-info py-2" style="font-size: .8rem">
                        <i class="bi bi-info-circle me-1"></i> El próximo número se incrementará automáticamente al crear cada factura. Protégelo si ya tienes facturas emitidas.
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
<!-- Modal Importación -->
<div class="modal fade" id="modalImport" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Importar Configuración</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="alert alert-warning small">
            <i class="bi bi-exclamation-triangle me-1"></i> Selecciona qué secciones deseas sobreescribir con los datos del archivo.
        </div>
        <div id="importContent">
            <!-- Checkboxes dinámicos -->
        </div>
        <input type="hidden" id="importDataJson">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-gold" onclick="applyImport()">Aplicar selección</button>
      </div>
    </div>
  </div>
</div>

<script>
async function handleImport(input) {
    if (!input.files || !input.files[0]) return;
    
    const formData = new FormData();
    formData.append('config_file', input.files[0]);

    try {
        const res = await fetch('config_process.php?action=import_preview', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (!data.ok) throw new Error(data.error);

        const content = document.getElementById('importContent');
        content.innerHTML = '';
        
        const sections = {
            empresa: 'Datos de la Empresa',
            numeracion: 'Configuración de Numeración',
            plantilla: 'Diseño de Plantilla',
            tema: 'Tema de Interfaz'
        };

        for (const [key, label] of Object.entries(sections)) {
            if (data.data[key]) {
                const div = document.createElement('div');
                div.className = 'form-check mb-2';
                div.innerHTML = `
                    <input class="form-check-input" type="checkbox" name="importSections" value="${key}" id="chk_${key}" checked>
                    <label class="form-check-label" for="chk_${key}">${label}</label>
                `;
                content.appendChild(div);
            }
        }
        
        document.getElementById('importDataJson').value = JSON.stringify(data.data);
        new bootstrap.Modal(document.getElementById('modalImport')).show();
        
    } catch (err) {
        alert('Error al leer el archivo: ' + err.message);
    } finally {
        input.value = '';
    }
}

async function applyImport() {
    const json = document.getElementById('importDataJson').value;
    const checks = document.querySelectorAll('input[name="importSections"]:checked');
    const sections = Array.from(checks).map(c => c.value);

    if (sections.length === 0) {
        alert('Selecciona al menos una sección para importar.');
        return;
    }

    try {
        const formData = new FormData();
        formData.append('json', json);
        sections.forEach(s => formData.append('sections[]', s));

        const res = await fetch('config_process.php?action=import_apply', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data.ok) {
            alert('Configuración importada con éxito. La página se recargará.');
            location.reload();
        } else throw new Error(data.error);
    } catch (err) {
        alert('Error al importar: ' + err.message);
    }
}

function updatePreview() {
    const pref = document.getElementById('prefInput').value;
    const anio = document.getElementById('anioInput').value === 'true' ? '<?= date('Y') ?>' : '';
    const prox = document.getElementById('proxInput').value;
    const dig  = parseInt(document.getElementById('digInput').value);
    
    let numStr = prox.toString().padStart(dig, '0');
    document.getElementById('previewNumero').innerText = pref + anio + numStr;
}
document.querySelectorAll('#formEmpresa input, #formEmpresa select').forEach(el => {
    el.addEventListener('input', updatePreview);
});
updatePreview();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

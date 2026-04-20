<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

// ── Procesar formulario ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Logo upload
    if (!empty($_FILES['logo']['tmp_name'])) {
        $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png'])) {
            move_uploaded_file($_FILES['logo']['tmp_name'], __DIR__ . '/../assets/logo.png');
            setConfig('invoice_logo', '/assets/logo.png');
        }
    }

    // Colores y textos
    setConfig('invoice_color_primary', post('invoice_color_primary'));
    setConfig('invoice_color_accent',  post('invoice_color_accent'));
    setConfig('invoice_color_text',    post('invoice_color_text'));
    setConfig('invoice_font',          post('invoice_font'));
    setConfig('invoice_footer',        post('invoice_footer'));
    setConfig('invoice_conditions',    post('invoice_conditions'));
    setConfig('invoice_legal',         str_replace(["\r\n", "\r", "\n"], ' ', post('invoice_legal')));

    flash('Personalización de plantilla guardada.');
    redirect('/ajustes/plantilla.php');
}

$pageTitle = 'Personalizar Plantilla';
require_once __DIR__ . '/../includes/header.php';

$fonts = [
    'Inter' => 'Inter, sans-serif',
    'Roboto' => 'Roboto, sans-serif',
    'Open Sans' => '"Open Sans", sans-serif',
    'Lato' => 'Lato, sans-serif',
    'Montserrat' => 'Montserrat, sans-serif'
];
?>

<div class="topbar">
    <h1><i class="bi bi-palette me-2"></i>Personalización de Plantilla</h1>
    <div class="d-flex gap-2">
        <a href="/facturas/ver.php?id=1&pdf=1&preview=1" target="_blank" class="btn btn-outline-primary btn-sm">
            <i class="bi bi-eye me-1"></i> Ver Previsualización
        </a>
        <button type="submit" form="formPlantilla" class="btn btn-gold btn-sm px-4">
            <i class="bi bi-save me-1"></i> Guardar configuración
        </button>
    </div>
</div>

<form id="formPlantilla" method="POST" enctype="multipart/form-data">
    <div class="row g-4">
        <!-- Izquierda: Configuración -->
        <div class="col-lg-6">
            <div class="card mb-4">
                <div class="card-header">Diseño y Logo</div>
                <div class="card-body">
                    <div class="mb-4">
                        <label class="form-label d-block">Logo de la empresa</label>
                        <div class="d-flex align-items-center gap-3">
                            <div class="border rounded p-2 bg-light" style="width: 100px; height: 60px; display: flex; align-items: center; justify-content: center; overflow: hidden">
                                <img id="logoPreview" src="<?= e(getConfig('invoice_logo', '/assets/logo.png')) ?>?t=<?= time() ?>" alt="Logo" style="max-width: 100%; max-height: 100%">
                            </div>
                            <input type="file" name="logo" class="form-control form-control-sm" accept="image/*" onchange="previewImg(this)">
                        </div>
                        <div class="form-text">JPG o PNG, máx 2MB.</div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Color Primario</label>
                            <input type="color" name="invoice_color_primary" class="form-control form-control-color w-100" value="<?= e(getConfig('invoice_color_primary', '#1A2E2A')) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Color Acento</label>
                            <input type="color" name="invoice_color_accent" class="form-control form-control-color w-100" value="<?= e(getConfig('invoice_color_accent', '#C9A84C')) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Color Texto</label>
                            <input type="color" name="invoice_color_text" class="form-control form-control-color w-100" value="<?= e(getConfig('invoice_color_text', '#1a1a1a')) ?>">
                        </div>
                        <div class="col-md-12">
                            <label class="form-label">Tipografía</label>
                            <select name="invoice_font" class="form-select">
                                <?php foreach($fonts as $name => $val): ?>
                                <option value="<?= $val ?>" <?= getConfig('invoice_font') === $val ? 'selected' : '' ?>><?= $name ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">Textos Dinámicos</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Pie de factura (Agradecimiento)</label>
                        <input type="text" name="invoice_footer" class="form-control" placeholder="Gracias por su confianza" value="<?= e(getConfig('invoice_footer', 'Gracias por su confianza')) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Condiciones de pago</label>
                        <textarea name="invoice_conditions" class="form-control" rows="2"><?= e(getConfig('invoice_conditions', 'Transferencia bancaria a 30 días.')) ?></textarea>
                    </div>
                    <div class="mb-0">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="form-label mb-0">Nota Legal / LOPD</label>
                            <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none" onclick="generateLOPD()">
                                <i class="bi bi-magic me-1"></i> Generar LOPD automáticamente
                            </button>
                        </div>
                        <textarea name="invoice_legal" id="invoice_legal" class="form-control" rows="4"><?= e(getConfig('invoice_legal', 'En cumplimiento de la LOPD...')) ?></textarea>
                        <div id="lopdWarning" class="alert alert-warning small mt-2 d-none">
                            <i class="bi bi-exclamation-triangle me-1"></i> Faltan datos de empresa (CIF o Email) para generar el texto completo. <a href="empresa.php" class="alert-link">Corregir ahora</a>.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Derecha: Guía visual -->
        <div class="col-lg-6">
            <div class="alert alert-info border-0 shadow-sm">
                <h5 class="alert-heading h6 fw-bold"><i class="bi bi-lightbulb me-1"></i> Consejos de diseño</h5>
                <ul class="mb-0 small ps-3">
                    <li>Usa un logo con fondo transparente (PNG) para mejor integración.</li>
                    <li>El color primario se usa en la cabecera y bordes de tablas.</li>
                    <li>El color acento resalta los números de factura y totales.</li>
                    <li>Asegúrate de que el color de texto sea legible sobre fondo blanco.</li>
                </ul>
            </div>
            
            <div class="p-4 border rounded bg-white shadow-sm mt-4 text-center">
                <p class="text-muted small">Los cambios guardados se aplicarán automáticamente a todas las facturas emitidas, incluyendo la versión PDF y la visualización online.</p>
                <i class="bi bi-file-earmark-pdf fs-1 text-danger opacity-50"></i>
            </div>
        </div>
    </div>
</form>

<script>
function previewImg(input) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('logoPreview').src = e.target.result;
        }
        reader.readAsDataURL(input.files[0]);
    }
}

function generateLOPD() {
    const nombre = "<?= e(getConfig('empresa_sociedad', EMPRESA_SOCIEDAD)) ?>";
    const cif    = "<?= e(getConfig('empresa_cif',    EMPRESA_CIF)) ?>";
    const dir    = "<?= e(getConfig('empresa_dir1',  EMPRESA_DIR1)) ?> " + "<?= e(getConfig('empresa_dir2', EMPRESA_DIR2)) ?>";
    const email  = "<?= e(getConfig('empresa_email',  EMPRESA_EMAIL)) ?>";

    if (!cif || !email) {
        document.getElementById('lopdWarning').classList.remove('d-none');
        return;
    }
    document.getElementById('lopdWarning').classList.add('d-none');

    const texto = `De conformidad con lo establecido en el Reglamento (UE) 2016/679 del Parlamento Europeo (RGPD) y la Ley Orgánica 3/2018 de Protección de Datos Personales (LOPDGDD), le informamos que los datos personales recogidos en este documento serán tratados por ${nombre} con CIF ${cif}, con domicilio en ${dir}, con la finalidad de gestionar la relación comercial y emitir la presente factura, así como para cumplir las obligaciones legales y contables derivadas de la misma. Los datos no serán cedidos a terceros salvo obligación legal expresa. Puede ejercer sus derechos de acceso, rectificación, supresión, oposición, limitación del tratamiento y portabilidad dirigiéndose a ${email}. Tiene derecho a presentar una reclamación ante la Agencia Española de Protección de Datos (www.aepd.es).`;

    document.getElementById('invoice_legal').value = texto;
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$temas = [
    'verde' => [
        'name' => 'Verde Esmeralda',
        'colors' => ['#1A2E2A', '#2D5245', '#3E7B64', '#C9A84C', '#F4F7F5']
    ],
    'azul' => [
        'name' => 'Azul Corporativo',
        'colors' => ['#1A2347', '#2D3F7A', '#3E5FC2', '#C9A84C', '#F4F7F5']
    ],
    'gris' => [
        'name' => 'Gris Antracita',
        'colors' => ['#1A1A2E', '#2D2D44', '#4A4A6A', '#C9A84C', '#F4F7F5']
    ],
    'burdeos' => [
        'name' => 'Burdeos',
        'colors' => ['#2E1A1A', '#522D2D', '#7B3E3E', '#C9A84C', '#F4F7F5']
    ],
    'negro' => [
        'name' => 'Negro Elegante',
        'colors' => ['#111111', '#222222', '#444444', '#C9A84C', '#F4F7F5']
    ],
];

// ── Procesar formulario ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (post('preset')) {
        $colors = $temas[post('preset')]['colors'];
        setConfig('theme_color_primary', $colors[0]);
        setConfig('theme_color_medium',  $colors[1]);
        setConfig('theme_color_accent',  $colors[2]);
        setConfig('theme_color_gold',    $colors[3]);
        setConfig('theme_color_bg',      $colors[4]);
    } else {
        setConfig('theme_color_primary', post('theme_color_primary'));
        setConfig('theme_color_medium',  post('theme_color_medium'));
        setConfig('theme_color_accent',  post('theme_color_accent'));
        setConfig('theme_color_gold',    post('theme_color_gold'));
        setConfig('theme_color_bg',      post('theme_color_bg'));

        // Fondo del logotipo en sidebar
        $logoBgVal = post('logo_bg_custom') && post('logo_bg') === 'custom'
            ? post('logo_bg_custom')
            : (post('logo_bg') ?: 'transparent');
        setConfig('logo_background_color', $logoBgVal);
    }

    flash('Tema de interfaz actualizado correctamente.');
    redirect('/ajustes/tema.php');
}

$pageTitle = 'Tema de Interfaz';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="topbar">
    <h1><i class="bi bi-brush me-2"></i>Personalización del Tema</h1>
    <button type="submit" form="formTema" class="btn btn-gold btn-sm px-4">
        <i class="bi bi-save me-1"></i> Guardar tema
    </button>
</div>

<div class="row g-4">
    <div class="col-lg-8">
        <div class="card mb-4">
            <div class="card-header">Temas Predefinidos</div>
            <div class="card-body">
                <div class="row g-3">
                    <?php foreach($temas as $id => $tema): ?>
                    <div class="col-md-4">
                        <form method="POST" class="h-100">
                            <input type="hidden" name="preset" value="<?= $id ?>">
                            <button type="submit" class="btn btn-outline-secondary w-100 h-100 p-3 text-start d-flex flex-column gap-2 overflow-hidden">
                                <span class="fw-bold small"><?= $tema['name'] ?></span>
                                <div class="d-flex gap-1">
                                    <?php foreach($tema['colors'] as $c): ?>
                                    <div style="width:16px; height:16px; background:<?= $c ?>; border-radius:3px"></div>
                                    <?php endforeach; ?>
                                </div>
                            </button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header">Personalización Manual</div>
            <div class="card-body">
                <form id="formTema" method="POST">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Color Principal</label>
                            <input type="color" name="theme_color_primary" class="form-control form-control-color w-100" value="<?= e(getConfig('theme_color_primary', '#1A2E2A')) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Color Medio (Hovers)</label>
                            <input type="color" name="theme_color_medium" class="form-control form-control-color w-100" value="<?= e(getConfig('theme_color_medium', '#2D5245')) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Color Acento</label>
                            <input type="color" name="theme_color_accent" class="form-control form-control-color w-100" value="<?= e(getConfig('theme_color_accent', '#3E7B64')) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Color Oro (Destacados)</label>
                            <input type="color" name="theme_color_gold" class="form-control form-control-color w-100" value="<?= e(getConfig('theme_color_gold', '#C9A84C')) ?>">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Color de Fondo</label>
                            <input type="color" name="theme_color_bg" class="form-control form-control-color w-100" value="<?= e(getConfig('theme_color_bg', '#F4F7F5')) ?>">
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <?php
        $currentLogoBg  = getConfig('logo_background_color', 'transparent');
        $logoBgPreset   = in_array($currentLogoBg, ['transparent', '#ffffff', '#000000'])
                          ? $currentLogoBg : 'custom';
        $logoBgCustom   = ($logoBgPreset === 'custom') ? $currentLogoBg : '#ffffff';
        $sbLogo         = getConfig('invoice_logo', '');
        ?>
        <div class="card">
            <div class="card-header">Fondo del logotipo en sidebar</div>
            <div class="card-body">
                <form method="POST">
                    <p class="small text-muted mb-3">
                        Controla el fondo del área del logo en la barra lateral.
                        Solo tiene efecto si has subido un logo en <a href="/ajustes/plantilla.php">Plantilla factura</a>.
                    </p>
                    <div class="row g-3 align-items-center">
                        <div class="col-md-6">
                            <div class="d-flex flex-column gap-2">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="logo_bg" id="logoBgTransparent"
                                           value="transparent" <?= $logoBgPreset === 'transparent' ? 'checked' : '' ?>
                                           onchange="updateLogoBgPreview()">
                                    <label class="form-check-label" for="logoBgTransparent">Transparente</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="logo_bg" id="logoBgWhite"
                                           value="#ffffff" <?= $logoBgPreset === '#ffffff' ? 'checked' : '' ?>
                                           onchange="updateLogoBgPreview()">
                                    <label class="form-check-label" for="logoBgWhite">Blanco</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="logo_bg" id="logoBgBlack"
                                           value="#000000" <?= $logoBgPreset === '#000000' ? 'checked' : '' ?>
                                           onchange="updateLogoBgPreview()">
                                    <label class="form-check-label" for="logoBgBlack">Negro</label>
                                </div>
                                <div class="form-check d-flex align-items-center gap-2">
                                    <input class="form-check-input" type="radio" name="logo_bg" id="logoBgCustom"
                                           value="custom" <?= $logoBgPreset === 'custom' ? 'checked' : '' ?>
                                           onchange="updateLogoBgPreview()">
                                    <label class="form-check-label" for="logoBgCustom">Personalizado</label>
                                    <input type="color" id="logoBgCustomPicker" name="logo_bg_custom"
                                           value="<?= e($logoBgCustom) ?>"
                                           class="form-control form-control-color"
                                           style="width:40px; height:30px; padding:2px;"
                                           oninput="document.getElementById('logoBgCustom').checked=true; updateLogoBgPreview()">
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6 text-center">
                            <p class="small text-muted mb-2">Vista previa</p>
                            <div id="logoBgPreview"
                                 style="background:<?= e($currentLogoBg) ?>; padding:1rem; border-radius:8px; border:1px dashed var(--border); min-height:80px; display:flex; align-items:center; justify-content:center;">
                                <?php if ($sbLogo): ?>
                                <img src="<?= e($sbLogo) ?>" alt="Logo"
                                     style="max-height:48px; max-width:160px; object-fit:contain;">
                                <?php else: ?>
                                <span class="text-muted small">Sin logo subido</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3">
                        <button type="submit" class="btn btn-gold btn-sm px-4">
                            <i class="bi bi-save me-1"></i> Guardar fondo
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">Vista Previa Sidebar</div>
            <div class="card-body p-0 overflow-hidden">
                <!-- Miniatura de Sidebar para preview -->
                <div id="sidebarPreview" style="background:#1A2E2A; padding:1.5rem 1rem; color:white; min-height:300px">
                    <div style="border-bottom:2px solid #C9A84C; padding-bottom:1rem; margin-bottom:1rem">
                        <div style="color:#C9A84C; font-weight:700; font-size:.8rem">MI EMPRESA S.L.</div>
                        <div style="font-size:.7rem; opacity:.7">Nelson Gil</div>
                    </div>
                    <div style="font-size:.6rem; opacity:.5; text-transform:uppercase; margin-bottom:.5rem">Facturación</div>
                    <div id="navLinkPreview" style="background:#2D5245; border-left:3px solid #C9A84C; padding:.5rem; font-size:.8rem; margin-bottom:.2rem">
                        Facturas emitidas
                    </div>
                    <div style="padding:.5rem; font-size:.8rem; opacity:.7">Nueva factura</div>
                </div>
            </div>
            <div class="card-footer bg-light"><small class="text-muted">La previsualización se actualiza al cambiar los colores.</small></div>
        </div>
    </div>
</div>

<script>
function refreshPreview() {
    const primary = document.querySelector('[name="theme_color_primary"]').value;
    const medium  = document.querySelector('[name="theme_color_medium"]').value;
    const gold    = document.querySelector('[name="theme_color_gold"]').value;

    document.getElementById('sidebarPreview').style.background = primary;
    document.getElementById('sidebarPreview').firstElementChild.style.borderColor = gold;
    document.getElementById('sidebarPreview').firstElementChild.firstElementChild.style.color = gold;

    const activeLink = document.getElementById('navLinkPreview');
    activeLink.style.background = medium;
    activeLink.style.borderLeftColor = gold;
}
document.querySelectorAll('[name^="theme_color"]').forEach(el => {
    el.addEventListener('input', refreshPreview);
});
refreshPreview();

function updateLogoBgPreview() {
    const selected = document.querySelector('input[name="logo_bg"]:checked');
    if (!selected) return;
    const val = selected.value === 'custom'
        ? document.getElementById('logoBgCustomPicker').value
        : selected.value;
    document.getElementById('logoBgPreview').style.background = val;
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

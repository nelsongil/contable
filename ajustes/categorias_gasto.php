<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$db = getDB();

// ── Acciones POST ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();
    $accion = post('accion');

    if ($accion === 'guardar') {
        $id         = (int)post('id');
        $nombre     = trim(post('nombre'));
        $pct_iva    = min(100, max(0, (float)str_replace(',', '.', post('pct_iva_deducible',  '100'))));
        $pct_irpf   = min(100, max(0, (float)str_replace(',', '.', post('pct_irpf_deducible', '100'))));
        $codigo_raw = strtoupper(trim(post('codigo_aeat', '')));
        // Validar código AEAT: G seguido de 2 dígitos (G01-G39), o vacío
        $codigo_aeat = preg_match('/^G\d{2}$/', $codigo_raw) ? $codigo_raw : null;

        if (!$nombre) {
            flash('El nombre de la categoría es obligatorio.', 'error');
            redirect('/ajustes/categorias_gasto.php' . ($id ? "?editar=$id" : ''));
        }

        if ($id) {
            $db->prepare(
                "UPDATE categorias_gasto
                 SET nombre=?, pct_iva_deducible=?, pct_irpf_deducible=?, codigo_aeat=?
                 WHERE id=?"
            )->execute([$nombre, $pct_iva, $pct_irpf, $codigo_aeat, $id]);
            flash('Categoría actualizada.');
        } else {
            try {
                $db->prepare(
                    "INSERT INTO categorias_gasto (nombre, pct_iva_deducible, pct_irpf_deducible, codigo_aeat)
                     VALUES (?, ?, ?, ?)"
                )->execute([$nombre, $pct_iva, $pct_irpf, $codigo_aeat]);
                flash('Categoría creada correctamente.');
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) {
                    flash('Ya existe una categoría con ese nombre.', 'error');
                } else {
                    flash('Error al guardar la categoría.', 'error');
                }
                redirect('/ajustes/categorias_gasto.php');
            }
        }
        redirect('/ajustes/categorias_gasto.php');
    }

    if ($accion === 'toggle') {
        $id = (int)post('id');
        $db->prepare("UPDATE categorias_gasto SET activa = 1 - activa WHERE id = ?")
           ->execute([$id]);
        redirect('/ajustes/categorias_gasto.php');
    }

    if ($accion === 'eliminar') {
        $id = (int)post('id');
        // Solo eliminar si no tiene facturas asociadas
        $uso = $db->prepare(
            "SELECT COUNT(*) FROM facturas_recibidas WHERE categoria_gasto_id = ?"
        );
        $uso->execute([$id]);
        if ((int)$uso->fetchColumn() > 0) {
            flash('No se puede eliminar: hay facturas asociadas a esta categoría. Desactívala en su lugar.', 'error');
        } else {
            $db->prepare("DELETE FROM categorias_gasto WHERE id = ?")->execute([$id]);
            flash('Categoría eliminada.');
        }
        redirect('/ajustes/categorias_gasto.php');
    }
}

// ── Cargar datos ──────────────────────────────────────────────────────────────
$categorias = getCategoriasGasto(false); // todas, incluidas inactivas

$editId  = (int)get('editar');
$editCat = $editId ? getCategoriaGasto($editId) : null;

$pageTitle = 'Categorías de gasto';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="topbar">
  <h1><i class="bi bi-tags me-2"></i>Categorías de gasto</h1>
  <a href="/ajustes/empresa.php" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i>Volver a ajustes
  </a>
</div>

<p class="text-muted mb-4" style="max-width:700px;font-size:.87rem">
  Define las categorías de gasto y sus porcentajes de deducibilidad por defecto.
  Al registrar una factura recibida, seleccionar la categoría autocompletará los porcentajes,
  que podrás ajustar manualmente para ese gasto concreto.
</p>

<div class="row g-4" style="max-width:900px">

  <!-- ═══ TABLA DE CATEGORÍAS ═══ -->
  <div class="col-12">
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-list-ul me-2"></i>Categorías configuradas</span>
        <a href="#formNueva" class="btn btn-gold btn-sm px-3">
          <i class="bi bi-plus-lg me-1"></i>Nueva categoría
        </a>
      </div>
      <div class="table-responsive">
        <table class="table table-hover mb-0" style="font-size:.87rem">
          <thead>
            <tr>
              <th>Nombre</th>
              <th class="text-center" style="width:95px">Cód. AEAT</th>
              <th class="text-center" style="width:130px">% IVA deducible</th>
              <th class="text-center" style="width:130px">% IRPF deducible</th>
              <th class="text-center" style="width:80px">Estado</th>
              <th style="width:110px"></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($categorias as $cat): ?>
            <tr class="<?= $cat['activa'] ? '' : 'text-muted' ?>">
              <td class="align-middle fw-semibold">
                <?= e($cat['nombre']) ?>
                <?php if (!$cat['activa']): ?>
                <span class="badge bg-secondary ms-1" style="font-size:.65rem">inactiva</span>
                <?php endif; ?>
              </td>
              <td class="text-center align-middle">
                <?php if (!empty($cat['codigo_aeat'])): ?>
                <code style="font-size:.78rem;background:var(--surface-2);padding:2px 6px;border-radius:4px"><?= e($cat['codigo_aeat']) ?></code>
                <?php else: ?>
                <span class="text-muted" style="font-size:.75rem">—</span>
                <?php endif; ?>
              </td>
              <td class="text-center align-middle">
                <?php
                $pctIva = (float)$cat['pct_iva_deducible'];
                $cls = $pctIva < 100 ? 'text-warning fw-bold' : 'text-success';
                ?>
                <span class="<?= $cls ?>"><?= number_format($pctIva, 0) ?>%</span>
              </td>
              <td class="text-center align-middle">
                <?php
                $pctIrpf = (float)$cat['pct_irpf_deducible'];
                $cls = $pctIrpf < 100 ? 'text-warning fw-bold' : 'text-success';
                ?>
                <span class="<?= $cls ?>"><?= number_format($pctIrpf, 0) ?>%</span>
              </td>
              <td class="text-center align-middle">
                <form method="post" class="d-inline">
                  <?= csrfField() ?>
                  <input type="hidden" name="accion" value="toggle">
                  <input type="hidden" name="id" value="<?= $cat['id'] ?>">
                  <button type="submit" class="btn btn-sm <?= $cat['activa'] ? 'btn-outline-success' : 'btn-outline-secondary' ?>"
                          style="font-size:.7rem;padding:2px 8px"
                          title="<?= $cat['activa'] ? 'Desactivar' : 'Activar' ?>">
                    <?= $cat['activa'] ? 'Activa' : 'Inactiva' ?>
                  </button>
                </form>
              </td>
              <td class="align-middle text-end">
                <a href="?editar=<?= $cat['id'] ?>" class="btn btn-sm btn-outline-secondary me-1"
                   title="Editar"><i class="bi bi-pencil"></i></a>
                <form method="post" class="d-inline"
                      data-confirm="¿Eliminar la categoría «<?= e($cat['nombre']) ?>»? Si tiene facturas asociadas no se podrá borrar.">
                  <?= csrfField() ?>
                  <input type="hidden" name="accion" value="eliminar">
                  <input type="hidden" name="id" value="<?= $cat['id'] ?>">
                  <button type="submit" class="btn btn-sm btn-outline-danger" title="Eliminar">
                    <i class="bi bi-trash"></i>
                  </button>
                </form>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$categorias): ?>
            <tr>
              <td colspan="5" class="text-center text-muted py-4">
                No hay categorías. Añade la primera usando el formulario de abajo.
              </td>
            </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- ═══ FORMULARIO AÑADIR / EDITAR ═══ -->
  <div class="col-12" id="formNueva">
    <div class="card" style="max-width:620px">
      <div class="card-header">
        <i class="bi bi-<?= $editCat ? 'pencil' : 'plus-lg' ?> me-2"></i>
        <?= $editCat ? 'Editar categoría' : 'Nueva categoría' ?>
      </div>
      <div class="card-body p-4">
        <form method="post">
          <?= csrfField() ?>
          <input type="hidden" name="accion" value="guardar">
          <input type="hidden" name="id" value="<?= $editCat['id'] ?? 0 ?>">
          <div class="row g-3">

            <div class="col-md-9">
              <label class="form-label">Nombre de la categoría *</label>
              <input type="text" name="nombre" class="form-control" required
                     maxlength="100" placeholder="Ej: Vehículo — uso mixto (50%)"
                     value="<?= e($editCat['nombre'] ?? '') ?>">
            </div>

            <div class="col-md-3">
              <label class="form-label">
                Código AEAT
                <span class="text-muted" style="font-size:.73rem">(G01–G39)</span><?= helpTip('Código G del Libro Registro de facturas recibidas según Orden HAC/773/2019. Necesario si exportas los libros para Hacienda desde LIBROS → Exportación AEAT.') ?>
              </label>
              <input type="text" name="codigo_aeat" class="form-control text-uppercase"
                     maxlength="3" placeholder="G16"
                     pattern="[Gg]\d{2}"
                     title="Formato: G seguido de 2 dígitos (ej: G16)"
                     value="<?= e($editCat['codigo_aeat'] ?? '') ?>">
              <div class="form-text">
                Usado en la <a href="/libros/exportacion_aeat.php">exportación AEAT</a>.
              </div>
            </div>

            <div class="col-md-6">
              <label class="form-label">
                % IVA deducible
                <span class="text-muted" style="font-size:.75rem">(0–100)</span><?= helpTip('Parte del IVA soportado que puedes deducirte. Ejemplo: un vehículo de uso mixto puede deducirse el 50% del IVA según criterio de Hacienda. 100% = deducción completa.') ?>
              </label>
              <div class="input-group">
                <input type="number" name="pct_iva_deducible" class="form-control"
                       step="0.01" min="0" max="100" required
                       value="<?= number_format((float)($editCat['pct_iva_deducible'] ?? 100), 2, '.', '') ?>">
                <span class="input-group-text">%</span>
              </div>
              <div class="form-text">100% = deducción completa del IVA soportado</div>
            </div>

            <div class="col-md-6">
              <label class="form-label">
                % IRPF deducible
                <span class="text-muted" style="font-size:.75rem">(0–100)</span><?= helpTip('Porcentaje del gasto que se incluye como deducción en el Modelo 130. Un gasto no relacionado con la actividad = 0%. Un gasto íntegramente profesional = 100%.') ?>
              </label>
              <div class="input-group">
                <input type="number" name="pct_irpf_deducible" class="form-control"
                       step="0.01" min="0" max="100" required
                       value="<?= number_format((float)($editCat['pct_irpf_deducible'] ?? 100), 2, '.', '') ?>">
                <span class="input-group-text">%</span>
              </div>
              <div class="form-text">100% = gasto íntegramente deducible en IRPF</div>
            </div>

            <div class="col-12 pt-1 d-flex gap-2">
              <button type="submit" class="btn btn-gold px-4">
                <i class="bi bi-check-lg me-1"></i>
                <?= $editCat ? 'Guardar cambios' : 'Añadir categoría' ?>
              </button>
              <?php if ($editCat): ?>
              <a href="/ajustes/categorias_gasto.php" class="btn btn-outline-secondary">
                Cancelar
              </a>
              <?php endif; ?>
            </div>

          </div>
        </form>
      </div>
    </div>
  </div>

</div>

<?php if ($editCat): ?>
<script>
// Scroll al formulario si estamos editando
document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('formNueva')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
});
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

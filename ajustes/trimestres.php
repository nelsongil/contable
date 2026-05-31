<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Seguridad: Solo admin
if (!isAdmin()) {
    flash('No tienes permiso para gestionar trimestres.', 'error');
    redirect('/ajustes/');
}

$anio = (int)get('anio', date('Y'));
$mensaje = null;
$error = null;

// ── Procesar acciones ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify();
    $action = post('action');
    $trim   = (int)post('trimestre');
    $notas  = post('notas');

    if (!in_array($trim, [1, 2, 3, 4])) {
        $error = 'Trimestre inválido.';
    } elseif ($action === 'cerrar') {
        if (cerrarTrimestre($anio, $trim, $notas)) {
            $mensaje = "Trimestre $trim/$anio cerrado correctamente.";
        } else {
            $error = "Error al cerrar el trimestre $trim/$anio.";
        }
    }
}

// ── Obtener listados ───────────────────────────────────────
$todosTrimestres = listarTrimestresFiscales();
$porAnio = [];
foreach ($todosTrimestres as $t) {
    $porAnio[$t['anio']][] = $t;
}

// Años disponibles
$anhos = array_unique(array_column($todosTrimestres, 'anio'));
rsort($anhos);
if (empty($anhos)) $anhos = [date('Y')];

$pageTitle = 'Gestión de Trimestres Fiscales';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="topbar">
  <h1><i class="bi bi-calendar-check me-2"></i>Gestión de Trimestres Fiscales</h1>
  <a href="/ajustes/" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i>Volver
  </a>
</div>

<?= showFlash() ?>

<?php if ($mensaje): ?>
<div class="alert alert-success alert-dismissible fade show">
  <i class="bi bi-check-circle me-2"></i><?= e($mensaje) ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<?php if ($error): ?>
<div class="alert alert-danger alert-dismissible fade show">
  <i class="bi bi-exclamation-triangle me-2"></i><?= e($error) ?>
  <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<!-- Selector de año -->
<div class="card mb-4" style="max-width: 500px">
  <div class="card-body">
    <form method="get" class="d-flex gap-2 align-items-center">
      <label class="form-label mb-0">Año:</label>
      <select name="anio" class="form-select" style="width: 150px" onchange="this.form.submit()">
        <?php foreach ($anhos as $a): ?>
        <option value="<?= $a ?>" <?= $anio == $a ? 'selected' : '' ?>><?= $a ?></option>
        <?php endforeach; ?>
      </select>
    </form>
  </div>
</div>

<!-- Explicación -->
<div class="alert alert-info mb-4">
  <i class="bi bi-info-circle me-2"></i>
  <strong>¿Cómo funciona?</strong>
  <ul class="mb-0 mt-2">
    <li><strong>Trimestre abierto:</strong> Se pueden añadir, editar y mover facturas.</li>
    <li><strong>Trimestre cerrado:</strong> No se pueden modificar facturas (ya presentado a la AEAT).</li>
    <li class="text-danger"><strong>Importante:</strong> El cierre de un trimestre es definitivo desde esta interfaz. Solo podría revertirse manualmente desde la base de datos.</li>
  </ul>
</div>

<!-- Trimestres del año seleccionado -->
<div class="row g-4">
  <?php
  $trimestresAnio = $porAnio[$anio] ?? [];
  // Asegurar que existen los 4 trimestres
  for ($t = 1; $t <= 4; $t++) {
      $existe = false;
      foreach ($trimestresAnio as $tr) {
          if ($tr['trimestre'] == $t) { $existe = true; break; }
      }
      if (!$existe) {
          $trimestresAnio[] = [
              'anio' => $anio,
              'trimestre' => $t,
              'estado' => 'abierto',
              'fecha_cierre' => null,
              'usuario_cierre' => null,
              'notas_cierre' => null,
          ];
      }
  }
  // Ordenar
  usort($trimestresAnio, fn($a, $b) => $b['trimestre'] - $a['trimestre']);

  $nombres = ['1' => 'Ene-Mar', '2' => 'Abr-Jun', '3' => 'Jul-Sep', '4' => 'Oct-Dic'];
  foreach ($trimestresAnio as $t):
    $trim = $t['trimestre'];
    $cerrado = $t['estado'] === 'cerrado';
    $fechaCierre = $t['fecha_cierre'] ? date('d/m/Y', strtotime($t['fecha_cierre'])) : null;
  ?>
  <div class="col-md-6 col-lg-3">
    <div class="card h-100 <?= $cerrado ? 'border-secondary' : 'border-success' ?>">
      <div class="card-header d-flex justify-content-between align-items-center"
           style="background: <?= $cerrado ? 'var(--verde)' : '#198754' ?>; color: white">
        <span>
          <i class="bi bi-calendar3 me-1"></i>
          T<?= $trim ?> <?= $nombres[$trim] ?>
        </span>
        <span class="badge" style="background: <?= $cerrado ? '#6c757d' : '#28a745' ?>">
          <?= ucfirst($t['estado']) ?>
        </span>
      </div>
      <div class="card-body">
        <?php if ($cerrado): ?>
          <p class="text-muted mb-3" style="font-size:.85rem">
            <i class="bi bi-lock me-1"></i>
            Cerrado el <?= $fechaCierre ?>
          </p>
          <?php if ($t['notas_cierre']): ?>
          <div class="alert alert-secondary py-2 mb-0" style="font-size:.78rem">
            <strong>Notas:</strong> <?= e($t['notas_cierre']) ?>
          </div>
          <?php endif; ?>
        <?php else: ?>
          <p class="text-success mb-3" style="font-size:.85rem">
            <i class="bi bi-unlock me-1"></i>
            Abierto para edición
          </p>

          <form method="post" class="mt-auto">
            <?= csrfField() ?>
            <input type="hidden" name="trimestre" value="<?= $trim ?>">
            <input type="hidden" name="action" value="cerrar">

            <div class="mb-2">
              <label class="form-label" style="font-size:.75rem">Confirmación</label>
              <input type="text" name="notas" class="form-control form-control-sm"
                     placeholder="Ej: Presentado Q<?= $trim ?> <?= $anio ?>" required>
            </div>
            <button type="submit" class="btn btn-sm btn-outline-danger w-100"
                    onclick="return confirm('⚠️ ¿Seguro que quieres CERRAR este trimestre?\\n\\nUna vez cerrado NO podrás añadir, editar ni mover facturas de este periodo.')">
              <i class="bi bi-lock me-1"></i>Cerrar trimestre
            </button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<!-- Auditoría -->
<?php
try {
    $db = getDB();
    $auditoria = $db->prepare(
        "SELECT a.*, u.nombre as usuario_nombre
         FROM auditoria_trimestres a
         LEFT JOIN usuarios u ON u.id = a.usuario_id
         WHERE a.anio = ?
         ORDER BY a.fecha DESC
         LIMIT 20"
    );
    $auditoria->execute([$anio]);
    $auditoriaRows = $auditoria->fetchAll();
} catch (Exception) {
    $auditoriaRows = [];
}
?>

<?php if ($auditoriaRows): ?>
<div class="card mt-5">
  <div class="card-header">
    <i class="bi bi-clock-history me-2"></i>Historial de cambios (<?= $anio ?>)
  </div>
  <div class="card-body p-0">
    <table class="table table-striped mb-0" style="font-size:.85rem">
      <thead>
        <tr>
          <th>Trimestre</th>
          <th>Acción</th>
          <th>Usuario</th>
          <th>Fecha</th>
          <th>Notas</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($auditoriaRows as $a): ?>
        <tr>
          <td><strong>T<?= $a['trimestre'] ?>/<?= $a['anio'] ?></strong></td>
          <td>
            <span class="badge" style="background: <?= $a['accion'] === 'cerrar' ? '#dc3545' : '#28a745' ?>">
              <?= ucfirst($a['accion']) ?>
            </span>
          </td>
          <td><?= e($a['usuario_nombre'] ?? 'Sistema') ?></td>
          <td><?= date('d/m/Y H:i', strtotime($a['fecha'])) ?></td>
          <td><?= e($a['notas'] ?? '—') ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?= themeCSS() ?>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>

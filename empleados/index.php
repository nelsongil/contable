<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

if (!getConfig('modulo_empleados', false)) redirect('/');

if (get('delete') && is_numeric(get('delete'))) {
    getDB()->prepare("UPDATE empleados SET activo=0 WHERE id=?")->execute([(int)get('delete')]);
    flash('Empleado desactivado.');
    redirect('/empleados/');
}

$todos = isset($_GET['todos']);
$empleados = getEmpleados(!$todos);

$pageTitle = 'Empleados';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="topbar fade-in-up">
  <h1><i class="bi bi-person-badge me-2"></i>Empleados</h1>
  <div class="d-flex gap-2 flex-wrap align-items-center">
    <div class="position-relative">
      <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-2 text-muted" style="font-size: 0.8rem;"></i>
      <input type="text" id="empSearch" class="form-control form-control-sm ps-4" placeholder="Buscar..." style="width: 180px;">
    </div>
    <a href="?todos=1" class="btn btn-sm btn-outline-secondary <?= $todos ? 'active' : '' ?>">Ver inactivos</a>
    <a href="nuevo.php" class="btn btn-gold btn-sm"><i class="bi bi-plus-lg me-1"></i>Nuevo empleado</a>
  </div>
</div>

<div class="card">
  <div class="card-header"><i class="bi bi-list me-2"></i><?= count($empleados) ?> empleados</div>
  <div class="card-body p-0">
    <table class="table table-hover mb-0" id="empTable">
      <thead>
        <tr>
          <th class="sortable">Nombre</th>
          <th class="sortable">NIF/DNI</th>
          <th class="sortable">Puesto</th>
          <th class="sortable text-end">Salario bruto/mes</th>
          <th class="sortable text-end">% IRPF</th>
          <th class="sortable">Alta</th>
          <th style="width:130px"></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($empleados as $e): ?>
        <tr class="<?= !$e['activo'] ? 'text-muted' : '' ?>">
          <td class="fw-semibold"><?= e($e['nombre']) ?></td>
          <td><?= e($e['nif']) ?></td>
          <td><?= e($e['puesto']) ?></td>
          <td class="text-end"><?= money((float)$e['salario_mensual']) ?></td>
          <td class="text-end"><?= number_format((float)$e['porcentaje_irpf'], 2) ?> %</td>
          <td><?= $e['fecha_alta'] ? date('d/m/Y', strtotime($e['fecha_alta'])) : '—' ?></td>
          <td>
            <div class="actions">
              <a href="nuevo.php?id=<?= $e['id'] ?>" class="btn btn-sm btn-outline-primary me-1"><i class="bi bi-pencil"></i></a>
              <?php if ($e['activo']): ?>
              <a href="?delete=<?= $e['id'] ?>" class="btn btn-sm btn-outline-danger"
                 data-confirm="¿Desactivar a <?= e($e['nombre']) ?>?"><i class="bi bi-archive"></i></a>
              <?php endif; ?>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$empleados): ?>
        <tr><td colspan="7" class="text-center text-muted py-4">Sin empleados. <a href="nuevo.php">Añade el primero</a>.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    filterTable('empSearch', 'empTable');
    makeSortable(document.getElementById('empTable'));
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

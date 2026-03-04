<?php
$pageTitle = 'Clientes';
require_once __DIR__ . '/../includes/header.php';

// Borrar
if (get('delete') && is_numeric(get('delete'))) {
    getDB()->prepare("UPDATE clientes SET activo=0 WHERE id=?")->execute([(int)get('delete')]);
    flash('Cliente eliminado correctamente.');
    redirect('/clientes/');
}

$todos = isset($_GET['todos']);
$clientes = getClientes(!$todos);
?>

<div class="topbar fade-in-up">
  <h1><i class="bi bi-people me-2"></i>Clientes</h1>
  <div class="d-flex gap-2 flex-wrap align-items-center">
    <div class="position-relative">
        <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-2 text-muted" style="font-size: 0.8rem;"></i>
        <input type="text" id="clienteSearch" class="form-control form-control-sm ps-4" placeholder="Buscar cliente..." style="width: 180px;">
    </div>
    <a href="?todos=1" class="btn btn-sm btn-outline-secondary <?= $todos ? 'active' : '' ?>">Ver inactivos</a>
    <a href="nuevo.php" class="btn btn-gold btn-sm"><i class="bi bi-plus-lg me-1"></i>Nuevo cliente</a>
  </div>
</div>

<div class="card">
  <div class="card-header"><i class="bi bi-list me-2"></i><?= count($clientes) ?> clientes</div>
  <div class="card-body p-0">
    <table class="table table-hover mb-0" id="clientesTable">
      <thead>
        <tr>
          <th class="sortable">Nombre</th>
          <th class="sortable">NIF/DNI</th>
          <th class="sortable">Ciudad</th>
          <th class="sortable">Teléfono</th>
          <th class="sortable">Email</th>
          <th style="width:120px"></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($clientes as $c): ?>
        <tr class="<?= !$c['activo'] ? 'text-muted' : '' ?>">
          <td class="fw-semibold"><?= e($c['nombre']) ?></td>
          <td><?= e($c['nif']) ?></td>
          <td><?= e($c['ciudad']) ?></td>
          <td><?= e($c['telefono']) ?></td>
          <td><?= e($c['email']) ?></td>
          <td>
            <div class="actions">
                <a href="editar.php?id=<?= $c['id'] ?>" class="btn btn-sm btn-outline-primary me-1"><i class="bi bi-pencil"></i></a>
                <a href="?delete=<?= $c['id'] ?>" class="btn btn-sm btn-outline-danger"
                   data-confirm="¿Desactivar este cliente?"><i class="bi bi-archive"></i></a>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$clientes): ?>
        <tr><td colspan="6" class="text-center text-muted py-4">Sin clientes. <a href="nuevo.php">Añade el primero</a></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    filterTable('clienteSearch', 'clientesTable');
    makeSortable(document.getElementById('clientesTable'));
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

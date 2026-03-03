<?php
$id = (int)($_GET['id'] ?? 0);
$pageTitle = $id ? 'Editar cliente' : 'Nuevo cliente';
require_once __DIR__ . '/../includes/header.php';
$c  = $id ? getCliente($id) : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        post('nombre'), post('nif'), post('direccion'), post('ciudad'),
        post('cp'), post('provincia'), post('telefono'), post('email'), post('notas')
    ];
    if (!$data[0]) { $error = 'El nombre es obligatorio.'; }
    else {
        $db = getDB();
        if ($id) {
            $db->prepare("UPDATE clientes SET nombre=?,nif=?,direccion=?,ciudad=?,cp=?,provincia=?,telefono=?,email=?,notas=? WHERE id=?")
               ->execute([...$data, $id]);
            flash('Cliente actualizado.');
        } else {
            $db->prepare("INSERT INTO clientes (nombre,nif,direccion,ciudad,cp,provincia,telefono,email,notas) VALUES (?,?,?,?,?,?,?,?,?)")
               ->execute($data);
            flash('Cliente creado correctamente.');
        }
        redirect('/clientes/');
    }
}
?>

<div class="topbar">
  <h1><i class="bi bi-person-plus me-2"></i><?= $pageTitle ?></h1>
  <a href="/clientes/" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Volver</a>
</div>

<?php if (!empty($error)): ?>
<div class="alert alert-danger"><?= e($error) ?></div>
<?php endif; ?>

<div class="card" style="max-width:700px">
  <div class="card-body p-4">
    <form method="post">
      <div class="row g-3">
        <div class="col-md-8">
          <label class="form-label">Nombre / Razón social *</label>
          <input type="text" name="nombre" class="form-control" required value="<?= e($c['nombre'] ?? '') ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">NIF / CIF / DNI</label>
          <input type="text" name="nif" class="form-control" value="<?= e($c['nif'] ?? '') ?>">
        </div>
        <div class="col-12">
          <label class="form-label">Dirección</label>
          <input type="text" name="direccion" class="form-control" value="<?= e($c['direccion'] ?? '') ?>">
        </div>
        <div class="col-md-5">
          <label class="form-label">Ciudad</label>
          <input type="text" name="ciudad" class="form-control" value="<?= e($c['ciudad'] ?? '') ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">C.P.</label>
          <input type="text" name="cp" class="form-control" value="<?= e($c['cp'] ?? '') ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Provincia</label>
          <input type="text" name="provincia" class="form-control" value="<?= e($c['provincia'] ?? '') ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">Teléfono</label>
          <input type="text" name="telefono" class="form-control" value="<?= e($c['telefono'] ?? '') ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">Email</label>
          <input type="email" name="email" class="form-control" value="<?= e($c['email'] ?? '') ?>">
        </div>
        <div class="col-12">
          <label class="form-label">Notas internas</label>
          <textarea name="notas" class="form-control" rows="2"><?= e($c['notas'] ?? '') ?></textarea>
        </div>
        <div class="col-12 pt-2">
          <button type="submit" class="btn btn-gold px-4">
            <i class="bi bi-check-lg me-1"></i><?= $id ? 'Guardar cambios' : 'Crear cliente' ?>
          </button>
        </div>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

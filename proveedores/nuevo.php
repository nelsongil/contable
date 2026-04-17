<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$id        = (int)($_GET['id'] ?? 0);
$inline    = isset($_GET['inline']);

// Pre-rellenar campos desde parámetros URL (ej: llegando desde importación PDF)
$prefill = [
    'nombre'    => get('nombre'),
    'nif'       => get('nif'),
    'direccion' => get('direccion'),
    'cp'        => get('cp'),
    'ciudad'    => get('ciudad'),
    'provincia' => get('provincia'),
];
$hasPrefill = array_filter($prefill) !== [];

$c = $id ? getProveedor($id) : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrfVerify($inline);
    $data = [
        post('nombre'), post('nif'), post('direccion'), post('ciudad'),
        post('cp'), post('provincia'), post('telefono'), post('email'), post('notas')
    ];
    if (!$data[0]) {
        if ($inline) { header('Content-Type: application/json'); echo json_encode(['ok' => false, 'error' => 'El nombre es obligatorio.']); exit; }
        $error = 'El nombre es obligatorio.';
    } else {
        $db = getDB();
        if ($id) {
            $db->prepare("UPDATE proveedores SET nombre=?,nif=?,direccion=?,ciudad=?,cp=?,provincia=?,telefono=?,email=?,notas=? WHERE id=?")
               ->execute([...$data, $id]);
            flash('Proveedor actualizado.');
        } else {
            $db->prepare("INSERT INTO proveedores (nombre,nif,direccion,ciudad,cp,provincia,telefono,email,notas) VALUES (?,?,?,?,?,?,?,?,?)")
               ->execute($data);
            if ($inline) {
                header('Content-Type: application/json');
                echo json_encode(['ok' => true, 'id' => (int)$db->lastInsertId(),
                    'nombre' => $data[0], 'nif' => $data[1],
                    'direccion' => $data[2], 'cp' => $data[4], 'ciudad' => $data[3]]);
                exit;
            }
            flash('Proveedor creado correctamente.');
        }
        redirect('/proveedores/');
    }
}

$pageTitle = $id ? 'Editar proveedor' : 'Nuevo proveedor';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="topbar">
  <h1><i class="bi bi-truck me-2"></i><?= $pageTitle ?></h1>
  <a href="/proveedores/" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Volver</a>
</div>

<?php if ($hasPrefill && !$id): ?>
<div class="alert alert-info py-2 mb-3" style="max-width:700px;font-size:.85rem">
  <i class="bi bi-magic me-1"></i>Datos pre-rellenados desde la importación del PDF. Revisa y completa antes de guardar.
</div>
<?php endif; ?>

<?php if (!empty($error)): ?>
<div class="alert alert-danger"><?= e($error) ?></div>
<?php endif; ?>

<div class="card" style="max-width:700px">
  <div class="card-body p-4">
    <form method="post">
      <?= csrfField() ?>
      <div class="row g-3">
        <div class="col-md-8">
          <label class="form-label">Nombre / Razón social *</label>
          <input type="text" name="nombre" class="form-control" required
                 value="<?= e($c['nombre'] ?? $prefill['nombre']) ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">NIF / CIF / DNI</label>
          <input type="text" name="nif" class="form-control"
                 value="<?= e($c['nif'] ?? $prefill['nif']) ?>">
        </div>
        <div class="col-12">
          <label class="form-label">Dirección</label>
          <input type="text" name="direccion" class="form-control"
                 value="<?= e($c['direccion'] ?? $prefill['direccion']) ?>">
        </div>
        <div class="col-md-5">
          <label class="form-label">Ciudad</label>
          <input type="text" name="ciudad" class="form-control"
                 value="<?= e($c['ciudad'] ?? $prefill['ciudad']) ?>">
        </div>
        <div class="col-md-3">
          <label class="form-label">C.P.</label>
          <input type="text" name="cp" class="form-control"
                 value="<?= e($c['cp'] ?? $prefill['cp']) ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Provincia</label>
          <input type="text" name="provincia" class="form-control"
                 value="<?= e($c['provincia'] ?? $prefill['provincia']) ?>">
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
            <i class="bi bi-check-lg me-1"></i><?= $id ? 'Guardar cambios' : 'Crear proveedor' ?>
          </button>
        </div>
      </div>
    </form>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

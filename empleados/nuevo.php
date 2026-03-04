<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

if (!getConfig('modulo_empleados', false)) redirect('/');

$id = (int)($_GET['id'] ?? 0);
$e  = $id ? getEmpleado($id) : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre   = post('nombre');
    $nif      = post('nif');
    $puesto   = post('puesto');
    $salario  = (float)str_replace(',', '.', post('salario_mensual', '0'));
    $irpf     = (float)str_replace(',', '.', post('porcentaje_irpf', '0'));
    $alta     = post('fecha_alta') ?: null;

    if (!$nombre) {
        $error = 'El nombre es obligatorio.';
    } else {
        $db = getDB();
        if ($id) {
            $db->prepare("UPDATE empleados SET nombre=?, nif=?, puesto=?, salario_mensual=?, porcentaje_irpf=?, fecha_alta=? WHERE id=?")
               ->execute([$nombre, $nif, $puesto, $salario, $irpf, $alta, $id]);
            flash('Empleado actualizado.');
        } else {
            $db->prepare("INSERT INTO empleados (nombre, nif, puesto, salario_mensual, porcentaje_irpf, fecha_alta) VALUES (?,?,?,?,?,?)")
               ->execute([$nombre, $nif, $puesto, $salario, $irpf, $alta]);
            flash('Empleado creado correctamente.');
        }
        redirect('/empleados/');
    }
}

$pageTitle = $id ? 'Editar empleado' : 'Nuevo empleado';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="topbar">
  <h1><i class="bi bi-person-plus me-2"></i><?= $pageTitle ?></h1>
  <a href="/empleados/" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Volver</a>
</div>

<?php if (!empty($error)): ?>
<div class="alert alert-danger"><?= e($error) ?></div>
<?php endif; ?>

<div class="card" style="max-width:680px">
  <div class="card-body p-4">
    <form method="post">
      <div class="row g-3">
        <div class="col-md-8">
          <label class="form-label">Nombre completo *</label>
          <input type="text" name="nombre" class="form-control" required value="<?= e($e['nombre'] ?? '') ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">NIF / DNI</label>
          <input type="text" name="nif" class="form-control" value="<?= e($e['nif'] ?? '') ?>">
        </div>
        <div class="col-md-8">
          <label class="form-label">Puesto / Categoría</label>
          <input type="text" name="puesto" class="form-control" value="<?= e($e['puesto'] ?? '') ?>">
        </div>
        <div class="col-md-4">
          <label class="form-label">Fecha de alta</label>
          <input type="date" name="fecha_alta" class="form-control" value="<?= e($e['fecha_alta'] ?? '') ?>">
        </div>
        <div class="col-md-6">
          <label class="form-label">Salario bruto mensual (€)</label>
          <input type="number" name="salario_mensual" class="form-control" step="0.01" min="0"
                 value="<?= moneyInput((float)($e['salario_mensual'] ?? 0)) ?>" id="salarioInput">
          <div class="form-text">Se usa como valor predeterminado al registrar retenciones.</div>
        </div>
        <div class="col-md-6">
          <label class="form-label">% IRPF pactado</label>
          <div class="input-group">
            <input type="number" name="porcentaje_irpf" class="form-control" step="0.01" min="0" max="45"
                   value="<?= number_format((float)($e['porcentaje_irpf'] ?? 0), 2, '.', '') ?>" id="irpfInput">
            <span class="input-group-text">%</span>
          </div>
          <div class="form-text">Retención aproximada: <strong id="retencionPreview">—</strong></div>
        </div>
        <div class="col-12 pt-2">
          <button type="submit" class="btn btn-gold px-4">
            <i class="bi bi-check-lg me-1"></i><?= $id ? 'Guardar cambios' : 'Crear empleado' ?>
          </button>
        </div>
      </div>
    </form>
  </div>
</div>

<script>
function updatePreview() {
    const sal  = parseFloat(document.getElementById('salarioInput').value) || 0;
    const irpf = parseFloat(document.getElementById('irpfInput').value) || 0;
    const ret  = (sal * irpf / 100).toFixed(2).replace('.', ',');
    document.getElementById('retencionPreview').textContent = ret + ' €/mes';
}
document.getElementById('salarioInput').addEventListener('input', updatePreview);
document.getElementById('irpfInput').addEventListener('input', updatePreview);
updatePreview();
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

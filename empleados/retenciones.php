<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

if (!getConfig('modulo_empleados', false)) redirect('/');

$anio = (int)get('anio', date('Y'));
$mes  = (int)get('mes', (int)date('n'));
if ($mes < 1 || $mes > 12) $mes = 1;

$nombresMes = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio',
               'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db      = getDB();
    $salarios = $_POST['salario']  ?? [];
    $irpfs   = $_POST['irpf']     ?? [];

    foreach ($salarios as $empId => $salario) {
        $empId   = (int)$empId;
        $salario = (float)str_replace(',', '.', $salario);
        $irpf    = (float)str_replace(',', '.', $irpfs[$empId] ?? '0');

        if ($empId <= 0) continue;

        $db->prepare(
            "INSERT INTO retenciones_empleados (empleado_id, anio, mes, salario_pagado, retencion_irpf)
             VALUES (?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE salario_pagado = VALUES(salario_pagado), retencion_irpf = VALUES(retencion_irpf)"
        )->execute([$empId, $anio, $mes, $salario, $irpf]);
    }

    flash('Retenciones de ' . $nombresMes[$mes] . ' ' . $anio . ' guardadas.');
    redirect("/empleados/retenciones.php?anio={$anio}&mes={$mes}");
}

// Cargar empleados activos
$empleados = getEmpleados(true);

// Cargar retenciones ya guardadas para este mes
$stRet = getDB()->prepare(
    "SELECT empleado_id, salario_pagado, retencion_irpf FROM retenciones_empleados WHERE anio=? AND mes=?"
);
$stRet->execute([$anio, $mes]);
$guardadas = [];
foreach ($stRet->fetchAll() as $r) {
    $guardadas[$r['empleado_id']] = $r;
}

$pageTitle = 'Retenciones mensuales';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="topbar">
  <h1><i class="bi bi-calendar3 me-2"></i>Retenciones — <?= $nombresMes[$mes] ?> <?= $anio ?></h1>
  <div class="d-flex gap-2 align-items-center">
    <select class="form-select form-select-sm" style="width:80px" onchange="updateUrl();" id="selAnio">
      <?php foreach ([date('Y'), date('Y')-1, date('Y')-2] as $y): ?>
      <option value="<?= $y ?>" <?= $y == $anio ? 'selected' : '' ?>><?= $y ?></option>
      <?php endforeach; ?>
    </select>
    <select class="form-select form-select-sm" style="width:130px" onchange="updateUrl();" id="selMes">
      <?php for ($m = 1; $m <= 12; $m++): ?>
      <option value="<?= $m ?>" <?= $m == $mes ? 'selected' : '' ?>><?= $nombresMes[$m] ?></option>
      <?php endfor; ?>
    </select>
  </div>
</div>

<?php if (!$empleados): ?>
<div class="alert alert-info">
  <i class="bi bi-info-circle me-2"></i>No hay empleados activos. <a href="nuevo.php">Añade el primero</a>.
</div>
<?php else: ?>
<form method="post" action="?anio=<?= $anio ?>&mes=<?= $mes ?>">
  <div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span><i class="bi bi-table me-2"></i>Nóminas de <?= $nombresMes[$mes] ?> <?= $anio ?></span>
      <button type="submit" class="btn btn-gold btn-sm px-4">
        <i class="bi bi-save me-1"></i>Guardar
      </button>
    </div>
    <div class="card-body p-0">
      <table class="table table-hover mb-0">
        <thead>
          <tr>
            <th>Empleado</th>
            <th>NIF</th>
            <th class="text-end" style="width:180px">Salario bruto (€)</th>
            <th class="text-end" style="width:100px">% IRPF</th>
            <th class="text-end" style="width:180px">IRPF retenido (€)</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($empleados as $e):
            $salDef = isset($guardadas[$e['id']]) ? $guardadas[$e['id']]['salario_pagado'] : $e['salario_mensual'];
            $retDef = isset($guardadas[$e['id']]) ? $guardadas[$e['id']]['retencion_irpf'] : round($e['salario_mensual'] * $e['porcentaje_irpf'] / 100, 2);
          ?>
          <tr>
            <td class="fw-semibold"><?= e($e['nombre']) ?></td>
            <td class="text-muted small"><?= e($e['nif']) ?></td>
            <td>
              <input type="number" name="salario[<?= $e['id'] ?>]" step="0.01" min="0"
                     class="form-control form-control-sm text-end sal-input"
                     data-irpf="<?= (float)$e['porcentaje_irpf'] ?>"
                     data-empid="<?= $e['id'] ?>"
                     value="<?= moneyInput((float)$salDef) ?>">
            </td>
            <td class="text-end text-muted small"><?= number_format((float)$e['porcentaje_irpf'], 2) ?> %</td>
            <td>
              <input type="number" name="irpf[<?= $e['id'] ?>]" step="0.01" min="0"
                     class="form-control form-control-sm text-end irpf-input"
                     id="irpf_<?= $e['id'] ?>"
                     value="<?= moneyInput((float)$retDef) ?>">
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr class="fw-bold" style="background:#f0f7f4">
            <td colspan="2">TOTAL</td>
            <td class="text-end" id="totSalario">—</td>
            <td></td>
            <td class="text-end" id="totIrpf">—</td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>
</form>

<script>
function updateUrl() {
    const anio = document.getElementById('selAnio').value;
    const mes  = document.getElementById('selMes').value;
    location.href = '?anio=' + anio + '&mes=' + mes;
}

function recalcRow(salInput) {
    const empId = salInput.dataset.empid;
    const pct   = parseFloat(salInput.dataset.irpf) || 0;
    const sal   = parseFloat(salInput.value) || 0;
    const irpfInput = document.getElementById('irpf_' + empId);
    if (irpfInput) irpfInput.value = (sal * pct / 100).toFixed(2);
    updateTotals();
}

function updateTotals() {
    let totSal = 0, totIrpf = 0;
    document.querySelectorAll('.sal-input').forEach(i => totSal += parseFloat(i.value) || 0);
    document.querySelectorAll('.irpf-input').forEach(i => totIrpf += parseFloat(i.value) || 0);
    document.getElementById('totSalario').textContent = totSal.toFixed(2).replace('.', ',') + ' €';
    document.getElementById('totIrpf').textContent    = totIrpf.toFixed(2).replace('.', ',') + ' €';
}

document.querySelectorAll('.sal-input').forEach(el => {
    el.addEventListener('input', () => recalcRow(el));
});
document.addEventListener('DOMContentLoaded', updateTotals);
</script>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

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

// ── Guardar nóminas ───────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('accion') === 'nominas') {
    csrfVerify();
    $db      = getDB();
    $salarios = $_POST['salario']      ?? [];
    $irpfs    = $_POST['irpf']         ?? [];
    $ssEmps   = $_POST['ss_empleado']  ?? [];
    $ssEmps2  = $_POST['ss_empresa']   ?? [];

    $db->beginTransaction();
    try {
        foreach ($salarios as $empId => $salario) {
            $empId      = (int)$empId;
            $salario    = (float)str_replace(',', '.', $salario);
            $irpf       = (float)str_replace(',', '.', $irpfs[$empId]   ?? '0');
            $ss_emp     = (float)str_replace(',', '.', $ssEmps[$empId]  ?? '0');
            $ss_emp2    = (float)str_replace(',', '.', $ssEmps2[$empId] ?? '0');

            if ($empId <= 0) continue;

            $db->prepare(
                "INSERT INTO retenciones_empleados (empleado_id, anio, mes, salario_pagado, retencion_irpf, ss_empleado, ss_empresa)
                 VALUES (?, ?, ?, ?, ?, ?, ?)
                 ON DUPLICATE KEY UPDATE
                   salario_pagado = VALUES(salario_pagado),
                   retencion_irpf = VALUES(retencion_irpf),
                   ss_empleado    = VALUES(ss_empleado),
                   ss_empresa     = VALUES(ss_empresa)"
            )->execute([$empId, $anio, $mes, $salario, $irpf, $ss_emp, $ss_emp2]);
        }
        $db->commit();
    } catch (PDOException $e) {
        if ($db->inTransaction()) $db->rollBack();
        flash('Error al guardar las nóminas. Inténtalo de nuevo.', 'error');
        redirect("/empleados/retenciones.php?anio={$anio}&mes={$mes}");
    }

    flash('Nóminas de ' . $nombresMes[$mes] . ' ' . $anio . ' guardadas.');
    redirect("/empleados/retenciones.php?anio={$anio}&mes={$mes}");
}

// ── Guardar cuota autónomo ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && post('accion') === 'autonomo') {
    csrfVerify();
    $importe = (float)str_replace(',', '.', post('cuota_autonomo', '0'));
    getDB()->prepare(
        "INSERT INTO cuotas_autonomo (anio, mes, importe)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE importe = VALUES(importe)"
    )->execute([$anio, $mes, $importe]);

    flash('Cuota autónomo de ' . $nombresMes[$mes] . ' ' . $anio . ' guardada.');
    redirect("/empleados/retenciones.php?anio={$anio}&mes={$mes}");
}

// ── Cargar datos ──────────────────────────────────────────────────────────────
$empleados = getEmpleados(true);

$stRet = getDB()->prepare(
    "SELECT empleado_id, salario_pagado, retencion_irpf, ss_empleado, ss_empresa
     FROM retenciones_empleados WHERE anio=? AND mes=?"
);
$stRet->execute([$anio, $mes]);
$guardadas = [];
foreach ($stRet->fetchAll() as $r) {
    $guardadas[$r['empleado_id']] = $r;
}

$stAuto = getDB()->prepare("SELECT importe FROM cuotas_autonomo WHERE anio=? AND mes=?");
$stAuto->execute([$anio, $mes]);
$cuotaAuto = (float)($stAuto->fetchColumn() ?: 0);

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

<!-- ═══ NÓMINAS ═══ -->
<form method="post" action="?anio=<?= $anio ?>&mes=<?= $mes ?>">
  <input type="hidden" name="accion" value="nominas">
  <?= csrfField() ?>
  <div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
      <span><i class="bi bi-table me-2"></i>Nóminas de <?= $nombresMes[$mes] ?> <?= $anio ?></span>
      <button type="submit" class="btn btn-gold btn-sm px-4">
        <i class="bi bi-save me-1"></i>Guardar nóminas
      </button>
    </div>
    <div class="card-body p-0">
      <div class="table-responsive">
      <table class="table table-hover mb-0" style="font-size:.87rem">
        <thead>
          <tr>
            <th>Empleado</th>
            <th class="text-end" style="width:140px">S. Bruto (€)</th>
            <th class="text-end" style="width:130px">SS Empresa (€)</th>
            <th class="text-end" style="width:130px">SS Empleado (€)</th>
            <th class="text-end" style="width:130px">IRPF (€)</th>
            <th class="text-end" style="width:130px">S. Neto (€)</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($empleados as $e):
            $g = $guardadas[$e['id']] ?? null;
            $salDef    = $g ? $g['salario_pagado'] : $e['salario_mensual'];
            $irpfDef   = $g ? $g['retencion_irpf'] : round($e['salario_mensual'] * $e['porcentaje_irpf'] / 100, 2);
            $ssEmpDef  = $g ? $g['ss_empleado']    : round($e['salario_mensual'] * $e['porcentaje_ss_empleado'] / 100, 2);
            $ssEmp2Def = $g ? $g['ss_empresa']     : round($e['salario_mensual'] * $e['porcentaje_ss_empresa']  / 100, 2);
          ?>
          <tr>
            <td class="fw-semibold align-middle"><?= e($e['nombre']) ?><br><span class="text-muted fw-normal" style="font-size:.75rem"><?= e($e['nif']) ?></span></td>
            <td>
              <input type="number" name="salario[<?= $e['id'] ?>]" step="0.01" min="0"
                     class="form-control form-control-sm text-end sal-input"
                     data-pct-ss-empresa="<?= (float)$e['porcentaje_ss_empresa'] ?>"
                     data-pct-ss-empleado="<?= (float)$e['porcentaje_ss_empleado'] ?>"
                     data-pct-irpf="<?= (float)$e['porcentaje_irpf'] ?>"
                     data-empid="<?= $e['id'] ?>"
                     value="<?= moneyInput((float)$salDef) ?>">
            </td>
            <td>
              <input type="number" name="ss_empresa[<?= $e['id'] ?>]" step="0.01" min="0"
                     class="form-control form-control-sm text-end ss-empresa-input"
                     id="ss_empresa_<?= $e['id'] ?>"
                     value="<?= moneyInput((float)$ssEmp2Def) ?>">
            </td>
            <td>
              <input type="number" name="ss_empleado[<?= $e['id'] ?>]" step="0.01" min="0"
                     class="form-control form-control-sm text-end ss-empleado-input"
                     id="ss_empleado_<?= $e['id'] ?>"
                     value="<?= moneyInput((float)$ssEmpDef) ?>">
            </td>
            <td>
              <input type="number" name="irpf[<?= $e['id'] ?>]" step="0.01" min="0"
                     class="form-control form-control-sm text-end irpf-input"
                     id="irpf_<?= $e['id'] ?>"
                     value="<?= moneyInput((float)$irpfDef) ?>">
            </td>
            <td class="align-middle text-end fw-semibold" id="neto_<?= $e['id'] ?>">—</td>
          </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr class="fw-bold" style="background:#f0f7f4">
            <td>TOTAL</td>
            <td class="text-end" id="totSalario">—</td>
            <td class="text-end" id="totSsEmpresa">—</td>
            <td class="text-end" id="totSsEmpleado">—</td>
            <td class="text-end" id="totIrpf">—</td>
            <td class="text-end" id="totNeto">—</td>
          </tr>
          <tr style="background:#e8f3ef;font-size:.8rem">
            <td class="text-muted">Coste total empresa</td>
            <td colspan="5" class="text-end fw-bold" id="totCosteEmpresa">—</td>
          </tr>
        </tfoot>
      </table>
      </div>
    </div>
  </div>
</form>

<?php endif; ?>

<!-- ═══ CUOTA AUTÓNOMO ═══ -->
<div class="card" style="max-width:420px">
  <div class="card-header"><i class="bi bi-person-badge me-2"></i>Cuota autónomo — <?= $nombresMes[$mes] ?> <?= $anio ?></div>
  <div class="card-body">
    <form method="post" action="?anio=<?= $anio ?>&mes=<?= $mes ?>">
      <input type="hidden" name="accion" value="autonomo">
      <?= csrfField() ?>
      <p class="text-muted mb-3" style="font-size:.85rem">Importe mensual de la Seguridad Social del autónomo (cuota propia).</p>
      <div class="input-group">
        <input type="number" name="cuota_autonomo" class="form-control" step="0.01" min="0"
               value="<?= moneyInput($cuotaAuto) ?>" placeholder="0.00">
        <span class="input-group-text">€</span>
        <button type="submit" class="btn btn-gold px-4"><i class="bi bi-save me-1"></i>Guardar</button>
      </div>
    </form>
  </div>
</div>

<script>
function updateUrl() {
    const anio = document.getElementById('selAnio').value;
    const mes  = document.getElementById('selMes').value;
    location.href = '?anio=' + anio + '&mes=' + mes;
}

function fmt(v) { return v.toFixed(2).replace('.', ',') + ' €'; }

function recalcRow(salInput) {
    const empId      = salInput.dataset.empid;
    const sal        = parseFloat(salInput.value) || 0;
    const pctIrpf    = parseFloat(salInput.dataset.pctIrpf)       || 0;
    const pctEmp     = parseFloat(salInput.dataset.pctSsEmpresa)   || 0;
    const pctEmpd    = parseFloat(salInput.dataset.pctSsEmpleado)  || 0;

    const irpfEl    = document.getElementById('irpf_'        + empId);
    const ssEmpEl   = document.getElementById('ss_empresa_'  + empId);
    const ssEmpdEl  = document.getElementById('ss_empleado_' + empId);

    if (irpfEl)   irpfEl.value   = (sal * pctIrpf  / 100).toFixed(2);
    if (ssEmpEl)  ssEmpEl.value  = (sal * pctEmp   / 100).toFixed(2);
    if (ssEmpdEl) ssEmpdEl.value = (sal * pctEmpd  / 100).toFixed(2);

    updateTotals();
}

function updateTotals() {
    let totSal = 0, totSsEmp = 0, totSsEmpd = 0, totIrpf = 0;

    document.querySelectorAll('.sal-input').forEach(i => {
        const empId = i.dataset.empid;
        const sal   = parseFloat(i.value) || 0;
        const ssE   = parseFloat(document.getElementById('ss_empresa_'  + empId)?.value) || 0;
        const ssEd  = parseFloat(document.getElementById('ss_empleado_' + empId)?.value) || 0;
        const irpf  = parseFloat(document.getElementById('irpf_'        + empId)?.value) || 0;
        const neto  = sal - ssEd - irpf;

        totSal   += sal;
        totSsEmp += ssE;
        totSsEmpd += ssEd;
        totIrpf  += irpf;

        const netoEl = document.getElementById('neto_' + empId);
        if (netoEl) netoEl.textContent = fmt(neto);
    });

    const totNeto = totSal - totSsEmpd - totIrpf;
    const totCoste = totSal + totSsEmp;

    document.getElementById('totSalario').textContent    = fmt(totSal);
    document.getElementById('totSsEmpresa').textContent  = fmt(totSsEmp);
    document.getElementById('totSsEmpleado').textContent = fmt(totSsEmpd);
    document.getElementById('totIrpf').textContent       = fmt(totIrpf);
    document.getElementById('totNeto').textContent       = fmt(totNeto);
    document.getElementById('totCosteEmpresa').textContent = fmt(totCoste);
}

document.querySelectorAll('.sal-input').forEach(el => {
    el.addEventListener('input', () => recalcRow(el));
});
document.querySelectorAll('.ss-empresa-input, .ss-empleado-input, .irpf-input').forEach(el => {
    el.addEventListener('input', updateTotals);
});
document.addEventListener('DOMContentLoaded', updateTotals);
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

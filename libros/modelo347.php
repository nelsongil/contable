<?php
$pageTitle = 'Modelo 347';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/fiscal_info.php';

define('UMBRAL_347', 3005.06);

$anio = (int)get('anio', date('Y'));
$db   = getDB();

// ── Clientes con > 3.005,06 € facturados (facturas emitidas, total con IVA) ──
$stClientes = $db->prepare(
    "SELECT fe.cliente_nif AS nif,
            fe.cliente_nombre AS nombre,
            COALESCE(SUM(fe.total), 0) AS total_anual,
            COALESCE(SUM(CASE WHEN fe.trimestre=1 THEN fe.total ELSE 0 END), 0) AS t1,
            COALESCE(SUM(CASE WHEN fe.trimestre=2 THEN fe.total ELSE 0 END), 0) AS t2,
            COALESCE(SUM(CASE WHEN fe.trimestre=3 THEN fe.total ELSE 0 END), 0) AS t3,
            COALESCE(SUM(CASE WHEN fe.trimestre=4 THEN fe.total ELSE 0 END), 0) AS t4
     FROM facturas_emitidas fe
     WHERE YEAR(fe.fecha) = ? AND fe.estado != 'cancelada'
     GROUP BY fe.cliente_nif, fe.cliente_nombre
     HAVING total_anual >= ?
     ORDER BY total_anual DESC"
);
$stClientes->execute([$anio, UMBRAL_347]);
$clientes347 = $stClientes->fetchAll();

// ── Proveedores con > 3.005,06 € recibidos (facturas recibidas, total con IVA) ──
$stProveedores = $db->prepare(
    "SELECT p.nif,
            p.nombre,
            COALESCE(SUM(fr.total), 0) AS total_anual,
            COALESCE(SUM(CASE WHEN fr.trimestre=1 THEN fr.total ELSE 0 END), 0) AS t1,
            COALESCE(SUM(CASE WHEN fr.trimestre=2 THEN fr.total ELSE 0 END), 0) AS t2,
            COALESCE(SUM(CASE WHEN fr.trimestre=3 THEN fr.total ELSE 0 END), 0) AS t3,
            COALESCE(SUM(CASE WHEN fr.trimestre=4 THEN fr.total ELSE 0 END), 0) AS t4
     FROM facturas_recibidas fr
     LEFT JOIN proveedores p ON p.id = fr.proveedor_id
     WHERE YEAR(fr.fecha) = ?
     GROUP BY fr.proveedor_id, p.nombre, p.nif
     HAVING total_anual >= ?
     ORDER BY total_anual DESC"
);
$stProveedores->execute([$anio, UMBRAL_347]);
$proveedores347 = $stProveedores->fetchAll();
?>

<div class="topbar">
  <h1><i class="bi bi-people me-2"></i>Modelo 347 — Operaciones con terceros <?= $anio ?></h1>
  <div class="d-flex gap-2 align-items-center">
    <select class="form-select form-select-sm" style="width:90px" onchange="location.href='?anio='+this.value">
      <?php foreach ([date('Y'), date('Y')-1, date('Y')-2] as $y): ?>
      <option value="<?= $y ?>" <?= $y == $anio ? 'selected' : '' ?>><?= $y ?></option>
      <?php endforeach; ?>
    </select>
  </div>
</div>

<?= fiscalInfoBox([
    'title' => '¿Qué es el Modelo 347?',
    'items' => [
        ['label' => '¿Qué es?',   'text' => 'Declaración anual de operaciones con terceros. Debes declarar a cada cliente o proveedor con quien hayas facturado más de 3.005,06 € en el año (IVA incluido).'],
        ['label' => 'Plazo',      'text' => 'Todo el mes de febrero del año siguiente al que se declara (es anual, no trimestral).'],
        ['label' => 'Para qué',   'text' => 'Hacienda cruza tu declaración con la del otro tercero para detectar discrepancias. Si tus datos no coinciden con los de tu cliente o proveedor, puede haber requerimientos.'],
        ['label' => 'Ejemplo',    'text' => 'Si facturaste 12.000 € a un cliente, lo incluyes en tu 347. Ese cliente debería incluirte a ti en el suyo. Ambas cifras deben coincidir.'],
    ]
]) ?>

<!-- Resumen KPI -->
<div class="row g-3 mb-4">
  <div class="col-md-3">
    <div class="kpi">
      <div class="label">Clientes a declarar</div>
      <div class="value"><?= count($clientes347) ?></div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="kpi">
      <div class="label">Total facturado (declarable)</div>
      <div class="value"><?= money(array_sum(array_column($clientes347, 'total_anual'))) ?></div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="kpi">
      <div class="label">Proveedores a declarar</div>
      <div class="value"><?= count($proveedores347) ?></div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="kpi">
      <div class="label">Total recibido (declarable)</div>
      <div class="value"><?= money(array_sum(array_column($proveedores347, 'total_anual'))) ?></div>
    </div>
  </div>
</div>

<!-- Clientes -->
<div class="card mb-4">
  <div class="card-header">
    <i class="bi bi-receipt me-2"></i>Clientes — Ventas (clave A) &nbsp;
    <span class="badge bg-light text-dark fw-normal"><?= count($clientes347) ?> declarados</span>
  </div>
  <div class="card-body p-0">
    <table class="table table-hover mb-0" id="tablaClientes">
      <thead>
        <tr>
          <th class="sortable">NIF</th>
          <th class="sortable">Nombre</th>
          <th class="text-end sortable">T1</th>
          <th class="text-end sortable">T2</th>
          <th class="text-end sortable">T3</th>
          <th class="text-end sortable">T4</th>
          <th class="text-end sortable fw-bold">Total anual</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($clientes347 as $c): ?>
        <tr>
          <td class="text-muted small"><?= e($c['nif']) ?: '—' ?></td>
          <td class="fw-semibold"><?= e($c['nombre']) ?></td>
          <td class="text-end"><?= (float)$c['t1'] ? money((float)$c['t1']) : '<span class="text-muted">—</span>' ?></td>
          <td class="text-end"><?= (float)$c['t2'] ? money((float)$c['t2']) : '<span class="text-muted">—</span>' ?></td>
          <td class="text-end"><?= (float)$c['t3'] ? money((float)$c['t3']) : '<span class="text-muted">—</span>' ?></td>
          <td class="text-end"><?= (float)$c['t4'] ? money((float)$c['t4']) : '<span class="text-muted">—</span>' ?></td>
          <td class="text-end fw-bold"><?= money((float)$c['total_anual']) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$clientes347): ?>
        <tr><td colspan="7" class="text-center text-muted py-4">
          Ningún cliente supera <?= money(UMBRAL_347) ?> en <?= $anio ?>
        </td></tr>
        <?php endif; ?>
      </tbody>
      <?php if ($clientes347): ?>
      <tfoot>
        <tr class="fw-bold" style="background:#f0f7f4">
          <td colspan="6" class="text-end">TOTAL</td>
          <td class="text-end"><?= money(array_sum(array_column($clientes347, 'total_anual'))) ?></td>
        </tr>
      </tfoot>
      <?php endif; ?>
    </table>
  </div>
</div>

<!-- Proveedores -->
<div class="card mb-4">
  <div class="card-header">
    <i class="bi bi-bag me-2"></i>Proveedores — Compras (clave B) &nbsp;
    <span class="badge bg-light text-dark fw-normal"><?= count($proveedores347) ?> declarados</span>
  </div>
  <div class="card-body p-0">
    <table class="table table-hover mb-0" id="tablaProveedores">
      <thead>
        <tr>
          <th class="sortable">NIF</th>
          <th class="sortable">Nombre</th>
          <th class="text-end sortable">T1</th>
          <th class="text-end sortable">T2</th>
          <th class="text-end sortable">T3</th>
          <th class="text-end sortable">T4</th>
          <th class="text-end sortable fw-bold">Total anual</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($proveedores347 as $p): ?>
        <tr>
          <td class="text-muted small"><?= e($p['nif']) ?: '—' ?></td>
          <td class="fw-semibold"><?= e($p['nombre']) ?></td>
          <td class="text-end"><?= (float)$p['t1'] ? money((float)$p['t1']) : '<span class="text-muted">—</span>' ?></td>
          <td class="text-end"><?= (float)$p['t2'] ? money((float)$p['t2']) : '<span class="text-muted">—</span>' ?></td>
          <td class="text-end"><?= (float)$p['t3'] ? money((float)$p['t3']) : '<span class="text-muted">—</span>' ?></td>
          <td class="text-end"><?= (float)$p['t4'] ? money((float)$p['t4']) : '<span class="text-muted">—</span>' ?></td>
          <td class="text-end fw-bold"><?= money((float)$p['total_anual']) ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$proveedores347): ?>
        <tr><td colspan="7" class="text-center text-muted py-4">
          Ningún proveedor supera <?= money(UMBRAL_347) ?> en <?= $anio ?>
        </td></tr>
        <?php endif; ?>
      </tbody>
      <?php if ($proveedores347): ?>
      <tfoot>
        <tr class="fw-bold" style="background:#f0f7f4">
          <td colspan="6" class="text-end">TOTAL</td>
          <td class="text-end"><?= money(array_sum(array_column($proveedores347, 'total_anual'))) ?></td>
        </tr>
      </tfoot>
      <?php endif; ?>
    </table>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    makeSortable(document.getElementById('tablaClientes'));
    makeSortable(document.getElementById('tablaProveedores'));
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

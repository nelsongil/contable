<?php
$pageTitle = 'Modelo 115 — Retenciones arrendamientos';
require_once __DIR__ . '/../includes/header.php';

$anio = (int)get('anio', date('Y'));
$db   = getDB();

// Datos por trimestre: arrendamientos con retención IRPF
$datos = [];
for ($t = 1; $t <= 4; $t++) {
    $st = $db->prepare(
        "SELECT COALESCE(SUM(base_imponible), 0) AS base,
                COALESCE(SUM(cuota_irpf), 0)     AS retencion,
                COUNT(*)                          AS num_facturas
         FROM facturas_recibidas
         WHERE YEAR(fecha) = ? AND trimestre = ? AND categoria = 'arrendamiento' AND cuota_irpf > 0"
    );
    $st->execute([$anio, $t]);
    $datos[$t] = $st->fetch();
}

$totBase   = array_sum(array_column($datos, 'base'));
$totRet    = array_sum(array_column($datos, 'retencion'));
$totFact   = array_sum(array_column($datos, 'num_facturas'));

// Trimestre activo (el trimestre actual del año seleccionado)
$trimActual = (int)ceil(date('n') / 3);
?>

<div class="topbar">
  <h1><i class="bi bi-file-earmark-text me-2"></i>Modelo 115 — Retenciones arrendamientos <?= $anio ?></h1>
  <div class="d-flex gap-2 align-items-center">
    <select class="form-select form-select-sm" style="width:90px" onchange="location.href='?anio='+this.value">
      <?php foreach ([date('Y'), date('Y')-1, date('Y')-2] as $y): ?>
      <option value="<?= $y ?>" <?= $y == $anio ? 'selected' : '' ?>><?= $y ?></option>
      <?php endforeach; ?>
    </select>
  </div>
</div>

<div class="alert alert-info py-2 mb-4" style="font-size:.85rem; max-width:900px">
  <i class="bi bi-info-circle me-2"></i>
  El Modelo 115 recoge las <strong>retenciones sobre arrendamientos de inmuebles</strong> (alquiler de local u oficina).
  Se presenta trimestralmente. Registra las facturas de alquiler en Compras con categoría <strong>Arrendamiento</strong>.
</div>

<!-- KPIs anuales -->
<div class="row g-3 mb-4">
  <div class="col-sm-4">
    <div class="kpi">
      <div class="label">Facturas arrendamiento</div>
      <div class="value"><?= $totFact ?></div>
    </div>
  </div>
  <div class="col-sm-4">
    <div class="kpi">
      <div class="label">Base total arrendada</div>
      <div class="value"><?= money($totBase) ?></div>
    </div>
  </div>
  <div class="col-sm-4">
    <div class="kpi kpi-red">
      <div class="label">Total retenciones a ingresar</div>
      <div class="value"><?= money($totRet) ?></div>
    </div>
  </div>
</div>

<!-- Tabla por trimestres -->
<div class="card mb-4">
  <div class="card-header"><i class="bi bi-table me-2"></i>Desglose trimestral</div>
  <div class="card-body p-0">
    <table class="table mb-0">
      <thead>
        <tr>
          <th>Trimestre</th>
          <th>Período</th>
          <th class="text-end">Nº facturas</th>
          <th class="text-end">Base arrendamientos</th>
          <th class="text-end">Retención IRPF</th>
          <th class="text-end fw-bold">A ingresar</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $periodos = [1=>'Ene–Mar',2=>'Abr–Jun',3=>'Jul–Sep',4=>'Oct–Dic'];
        for ($t = 1; $t <= 4; $t++):
            $d = $datos[$t];
        ?>
        <tr>
          <td><span class="badge-trim">T<?= $t ?></span></td>
          <td class="text-muted"><?= $periodos[$t] ?></td>
          <td class="text-end"><?= $d['num_facturas'] ?></td>
          <td class="text-end"><?= money($d['base']) ?></td>
          <td class="text-end"><?= money($d['retencion']) ?></td>
          <td class="text-end fw-bold <?= $d['retencion'] > 0 ? 'text-danger' : 'text-muted' ?>">
            <?= money($d['retencion']) ?>
            <?php if ($d['retencion'] > 0): ?>
            <small class="d-block fw-normal">↑ A ingresar</small>
            <?php endif; ?>
          </td>
        </tr>
        <?php endfor; ?>
      </tbody>
      <tfoot>
        <tr class="fw-bold" style="background:var(--surface-2)">
          <td colspan="2">ANUAL</td>
          <td class="text-end"><?= $totFact ?></td>
          <td class="text-end"><?= money($totBase) ?></td>
          <td class="text-end"><?= money($totRet) ?></td>
          <td class="text-end text-danger"><?= money($totRet) ?></td>
        </tr>
      </tfoot>
    </table>
  </div>
</div>

<!-- Detalle facturas del trimestre actual -->
<?php
$stDet = $db->prepare(
    "SELECT fr.numero, fr.fecha, fr.proveedor_nombre, fr.base_imponible,
            fr.porcentaje_irpf, fr.cuota_irpf, fr.descripcion
     FROM facturas_recibidas fr
     WHERE YEAR(fr.fecha) = ? AND categoria = 'arrendamiento' AND cuota_irpf > 0
     ORDER BY fr.fecha DESC"
);
$stDet->execute([$anio]);
$facturas = $stDet->fetchAll();
if ($facturas):
?>
<div class="card">
  <div class="card-header"><i class="bi bi-list me-2"></i>Facturas de arrendamiento <?= $anio ?></div>
  <div class="card-body p-0">
    <table class="table table-hover mb-0">
      <thead>
        <tr>
          <th>Nº Factura</th>
          <th>Fecha</th>
          <th>T</th>
          <th>Arrendador</th>
          <th>Descripción</th>
          <th class="text-end">Base</th>
          <th class="text-end">Ret. %</th>
          <th class="text-end">Ret. IRPF</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($facturas as $f): ?>
        <tr>
          <td class="fw-semibold"><?= e($f['numero']) ?></td>
          <td><?= date('d/m/Y', strtotime($f['fecha'])) ?></td>
          <td><span class="badge-trim">T<?= trimestre($f['fecha']) ?></span></td>
          <td><?= e($f['proveedor_nombre']) ?></td>
          <td class="text-muted small"><?= e($f['descripcion']) ?></td>
          <td class="text-end"><?= money($f['base_imponible']) ?></td>
          <td class="text-end"><?= $f['porcentaje_irpf'] ?>%</td>
          <td class="text-end fw-semibold text-danger"><?= money($f['cuota_irpf']) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php if (!$totFact): ?>
<div class="card">
  <div class="card-body text-center text-muted py-5">
    <i class="bi bi-building" style="font-size:2rem;opacity:.3"></i>
    <p class="mt-3 mb-1">No hay facturas de arrendamiento registradas en <?= $anio ?>.</p>
    <p style="font-size:.85rem">Al registrar compras de tipo <strong>Arrendamiento</strong> con retención IRPF, aparecerán aquí.</p>
    <a href="/compras/nueva.php" class="btn btn-outline-primary btn-sm mt-2">Registrar factura de alquiler</a>
  </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

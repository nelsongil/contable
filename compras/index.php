<?php
$pageTitle = 'Facturas recibidas';
require_once __DIR__ . '/../includes/header.php';

$anio  = (int)get('anio', date('Y'));
$trim  = (int)get('trim', 0);
$facturas = getFacturasRecibidas($anio, $trim);

$totBase = $totIva = $totTotal = 0;
foreach ($facturas as $f) { $totBase += $f['base_imponible']; $totIva += $f['cuota_iva']; $totTotal += $f['total']; }
?>

<div class="topbar">
  <h1><i class="bi bi-bag me-2"></i>Facturas recibidas</h1>
  <div class="d-flex gap-2">
    <select class="form-select form-select-sm" style="width:90px" onchange="location.href='?anio='+this.value+'&trim=<?= $trim ?>'">
      <?php foreach ([date('Y'), date('Y')-1] as $y): ?>
      <option value="<?= $y ?>" <?= $y==$anio?'selected':'' ?>><?= $y ?></option>
      <?php endforeach; ?>
    </select>
    <select class="form-select form-select-sm" style="width:90px" onchange="location.href='?anio=<?= $anio ?>&trim='+this.value">
      <option value="0" <?= !$trim?'selected':'' ?>>Todos</option>
      <?php for ($t=1;$t<=4;$t++): ?>
      <option value="<?= $t ?>" <?= $trim==$t?'selected':'' ?>>T<?= $t ?></option>
      <?php endfor; ?>
    </select>
    <a href="nueva.php" class="btn btn-gold btn-sm"><i class="bi bi-plus-lg me-1"></i>Registrar compra</a>
  </div>
</div>

<div class="row g-2 mb-3">
  <div class="col-sm-4"><div class="kpi py-2"><div class="label">Base imponible</div><div class="value" style="font-size:1.2rem"><?= money($totBase) ?></div></div></div>
  <div class="col-sm-4"><div class="kpi py-2"><div class="label">IVA soportado</div><div class="value" style="font-size:1.2rem"><?= money($totIva) ?></div></div></div>
  <div class="col-sm-4"><div class="kpi py-2"><div class="label">Total pagado</div><div class="value" style="font-size:1.2rem"><?= money($totTotal) ?></div></div></div>
</div>

<div class="card">
  <div class="card-header"><i class="bi bi-list me-2"></i><?= count($facturas) ?> facturas recibidas</div>
  <div class="card-body p-0">
    <table class="table table-hover mb-0">
      <thead>
        <tr><th>Nº Factura</th><th>Fecha</th><th>T</th><th>Proveedor</th><th>Concepto</th>
            <th class="text-end">Base</th><th class="text-end">IVA</th><th class="text-end">Total</th><th style="width:80px"></th></tr>
      </thead>
      <tbody>
        <?php foreach ($facturas as $f): ?>
        <tr>
          <td class="fw-semibold"><?= e($f['numero']) ?></td>
          <td><?= date('d/m/Y', strtotime($f['fecha'])) ?></td>
          <td><span class="badge-trim">T<?= $f['trimestre'] ?></span></td>
          <td class="text-truncate" style="max-width:140px"><?= e($f['proveedor_nombre']) ?></td>
          <td class="text-truncate text-muted small" style="max-width:160px"><?= e($f['descripcion']) ?></td>
          <td class="text-end"><?= money($f['base_imponible']) ?></td>
          <td class="text-end"><?= money($f['cuota_iva']) ?></td>
          <td class="text-end fw-semibold"><?= money($f['total']) ?></td>
          <td><a href="nueva.php?id=<?= $f['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a></td>
        </tr>
        <?php endforeach; ?>
        <?php if (!$facturas): ?>
        <tr><td colspan="9" class="text-center text-muted py-4">Sin facturas. <a href="nueva.php">Registra la primera</a></td></tr>
        <?php endif; ?>
      </tbody>
      <?php if ($facturas): ?>
      <tfoot>
        <tr class="fw-bold" style="background:#f0f7f4">
          <td colspan="5">TOTAL</td>
          <td class="text-end"><?= money($totBase) ?></td>
          <td class="text-end"><?= money($totIva) ?></td>
          <td class="text-end"><?= money($totTotal) ?></td>
          <td></td>
        </tr>
      </tfoot>
      <?php endif; ?>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

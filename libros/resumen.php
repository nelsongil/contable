<?php
$pageTitle = 'Resumen fiscal';
require_once __DIR__ . '/../includes/header.php';

$anio = (int)get('anio', date('Y'));
$trims = [];
$totV = $totC = $totIva = $totRend = $totIrpf = 0;

for ($t = 1; $t <= 4; $t++) {
    $r = resumenTrimestral($anio, $t);
    $r['irpf_a_pagar'] = max(0, $r['ventas_base'] * 0.20 - $r['ventas_irpf']);
    $trims[$t] = $r;
    $totV    += $r['ventas_base'];
    $totC    += $r['compras_base'];
    $totIva  += $r['iva_resultado'];
    $totRend += $r['rendimiento'];
    $totIrpf += $r['irpf_a_pagar'];
}
?>

<div class="topbar">
  <h1><i class="bi bi-bar-chart me-2"></i>Resumen fiscal <?= $anio ?></h1>
  <div class="d-flex gap-2">
    <select class="form-select form-select-sm" style="width:90px" onchange="location.href='?anio='+this.value">
      <?php foreach ([date('Y'), date('Y')-1, date('Y')-2] as $y): ?>
      <option value="<?= $y ?>" <?= $y==$anio?'selected':'' ?>><?= $y ?></option>
      <?php endforeach; ?>
    </select>
    <a href="exportar.php?anio=<?= $anio ?>" class="btn btn-sm btn-outline-success"><i class="bi bi-file-earmark-excel me-1"></i>Exportar Excel</a>
  </div>
</div>

<!-- IVA Trimestral -->
<div class="card mb-3">
  <div class="card-header"><i class="bi bi-receipt me-2"></i>IVA — Modelo 303</div>
  <div class="card-body p-0">
    <table class="table mb-0">
      <thead><tr><th>Trimestre</th><th class="text-end">Base ventas</th><th class="text-end">IVA repercutido</th><th class="text-end">Base compras</th><th class="text-end">IVA soportado</th><th class="text-end fw-bold">Resultado IVA</th></tr></thead>
      <tbody>
        <?php for ($t=1;$t<=4;$t++): $r=$trims[$t]; ?>
        <tr>
          <td><span class="badge-trim">T<?= $t ?></span></td>
          <td class="text-end"><?= money($r['ventas_base']) ?></td>
          <td class="text-end"><?= money($r['ventas_iva']) ?></td>
          <td class="text-end"><?= money($r['compras_base']) ?></td>
          <td class="text-end"><?= money($r['compras_iva']) ?></td>
          <td class="text-end fw-bold <?= $r['iva_resultado'] > 0 ? 'text-danger' : 'text-success' ?>">
            <?= money($r['iva_resultado']) ?>
            <small class="d-block fw-normal"><?= $r['iva_resultado'] > 0 ? '↑ A ingresar' : '↓ A compensar' ?></small>
          </td>
        </tr>
        <?php endfor; ?>
      </tbody>
      <tfoot>
        <tr class="fw-bold" style="background:#f0f7f4">
          <td>ANUAL</td>
          <td class="text-end"><?= money($totV) ?></td>
          <td class="text-end"><?= money(array_sum(array_column($trims,'ventas_iva'))) ?></td>
          <td class="text-end"><?= money($totC) ?></td>
          <td class="text-end"><?= money(array_sum(array_column($trims,'compras_iva'))) ?></td>
          <td class="text-end <?= $totIva > 0 ? 'text-danger' : 'text-success' ?>"><?= money($totIva) ?></td>
        </tr>
      </tfoot>
    </table>
  </div>
</div>

<!-- IRPF Trimestral -->
<div class="card">
  <div class="card-header"><i class="bi bi-cash me-2"></i>IRPF — Modelo 130</div>
  <div class="card-body p-0">
    <table class="table mb-0">
      <thead><tr><th>Trimestre</th><th class="text-end">Ingresos (base)</th><th class="text-end">Gastos (base)</th><th class="text-end">Rendimiento</th><th class="text-end">Retenciones</th><th class="text-end fw-bold">A ingresar (130)</th></tr></thead>
      <tbody>
        <?php for ($t=1;$t<=4;$t++): $r=$trims[$t]; ?>
        <tr>
          <td><span class="badge-trim">T<?= $t ?></span></td>
          <td class="text-end"><?= money($r['ventas_base']) ?></td>
          <td class="text-end"><?= money($r['compras_base']) ?></td>
          <td class="text-end <?= $r['rendimiento'] < 0 ? 'text-danger' : '' ?>"><?= money($r['rendimiento']) ?></td>
          <td class="text-end"><?= money($r['ventas_irpf']) ?></td>
          <td class="text-end fw-bold <?= $r['irpf_a_pagar'] > 0 ? 'text-danger' : 'text-muted' ?>"><?= money($r['irpf_a_pagar']) ?></td>
        </tr>
        <?php endfor; ?>
      </tbody>
      <tfoot>
        <tr class="fw-bold" style="background:#f0f7f4">
          <td>ANUAL</td>
          <td class="text-end"><?= money($totV) ?></td>
          <td class="text-end"><?= money($totC) ?></td>
          <td class="text-end"><?= money($totRend) ?></td>
          <td class="text-end"><?= money(array_sum(array_column($trims,'ventas_irpf'))) ?></td>
          <td class="text-end text-danger"><?= money($totIrpf) ?></td>
        </tr>
      </tfoot>
    </table>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

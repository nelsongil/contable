<?php
$pageTitle = 'Resumen fiscal';
require_once __DIR__ . '/../includes/header.php';

$anio = (int)get('anio', date('Y'));
$db   = getDB();

// ── Guardar compensaciones editables ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    setConfig("comp_irpf_{$anio}", (float)str_replace(',', '.', post('comp_irpf')));
    setConfig("comp_iva_{$anio}",  (float)str_replace(',', '.', post('comp_iva')));
    flash('Compensaciones actualizadas.');
    redirect("/libros/resumen.php?anio={$anio}");
}

$compIrpf = (float)getConfig("comp_irpf_{$anio}", 0); // base pendiente de compensar de años anteriores
$compIva  = (float)getConfig("comp_iva_{$anio}",  0); // IVA a compensar de ejercicio anterior

// ── Datos por trimestre ───────────────────────────────────────────────────────
$trims = [];
for ($t = 1; $t <= 4; $t++) {
    $trims[$t] = resumenTrimestral($anio, $t);
}

// ── Acumulados para IRPF (Modelo 130) ────────────────────────────────────────
$ingAcum = $gasAcum = $retAcum = $pagAcum = 0;
$irpfTrim = [];  // a ingresar cada trimestre
for ($t = 1; $t <= 4; $t++) {
    $ingAcum += $trims[$t]['ventas_base'];
    $gasAcum += $trims[$t]['compras_base'];
    $retAcum += $trims[$t]['ventas_irpf'];
    $baseAcum    = max(0, $ingAcum - $gasAcum - $compIrpf);
    $cuotaBruta  = $baseAcum * 0.20;
    $aIngresar   = max(0, $cuotaBruta - $retAcum - $pagAcum);
    $irpfTrim[$t] = [
        'ing_acum'   => $ingAcum,
        'gas_acum'   => $gasAcum,
        'base_acum'  => $ingAcum - $gasAcum,
        'cuota_acum' => $cuotaBruta,
        'ret_acum'   => $retAcum,
        'pago_prev'  => $pagAcum,
        'a_ingresar' => $aIngresar,
    ];
    $pagAcum += $aIngresar;
}

// ── IVA con compensación de trimestre anterior (dentro del año) ───────────────
$compAnterior = $compIva; // puede venir del año anterior
$ivaTrim = [];
for ($t = 1; $t <= 4; $t++) {
    $generado  = $trims[$t]['ventas_iva'];
    $deducible = $trims[$t]['compras_iva'];
    $resultado = $generado - $deducible - $compAnterior;
    $ivaTrim[$t] = [
        'generado'    => $generado,
        'deducible'   => $deducible,
        'comp_ant'    => $compAnterior,
        'resultado'   => $resultado,
    ];
    // Si el resultado es negativo, se compensa en el siguiente trimestre
    $compAnterior = $resultado < 0 ? abs($resultado) : 0;
}

// ── Totales anuales ───────────────────────────────────────────────────────────
$totIng    = $irpfTrim[4]['ing_acum'];
$totGas    = $irpfTrim[4]['gas_acum'];
$totBase   = $totIng - $totGas;
$totIrpf   = $pagAcum;
$totRend   = $totBase - $totIrpf;
$totIvaGen = array_sum(array_column($ivaTrim, 'generado'));
$totIvaDed = array_sum(array_column($ivaTrim, 'deducible'));
$totIvaRes = array_sum(array_column($ivaTrim, 'resultado'));
?>

<div class="topbar">
  <h1><i class="bi bi-bar-chart me-2"></i>Resumen fiscal <?= $anio ?></h1>
  <div class="d-flex gap-2 align-items-center flex-wrap">
    <select class="form-select form-select-sm" style="width:90px" onchange="location.href='?anio='+this.value">
      <?php foreach ([date('Y'), date('Y')-1, date('Y')-2] as $y): ?>
      <option value="<?= $y ?>" <?= $y==$anio?'selected':'' ?>><?= $y ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#modalComp">
      <i class="bi bi-pencil me-1"></i>Compensaciones
    </button>
    <a href="exportar.php?anio=<?= $anio ?>" class="btn btn-sm btn-outline-success">
      <i class="bi bi-file-earmark-excel me-1"></i>Exportar CSV
    </a>
  </div>
</div>

<style>
.resumen-table { font-size: .87rem; }
.resumen-table th { background: var(--verde); color: #fff; text-align: right; padding: .6rem 1rem; font-weight: 600; font-size: .75rem; text-transform: uppercase; letter-spacing: .05em; border: none; }
.resumen-table th:first-child { text-align: left; }
.resumen-table td { padding: .55rem 1rem; vertical-align: middle; border-color: var(--border); color: var(--text); }
.resumen-table td:not(:first-child) { text-align: right; }
.resumen-table .fila-total { background: var(--surface-2); font-weight: 700; border-top: 2px solid var(--verde-m); }
.resumen-table .fila-section { background: var(--verde-m); color: #fff; font-weight: 700; font-size: .78rem; text-transform: uppercase; letter-spacing: .06em; }
.resumen-table .fila-section td { color: #fff !important; border: none; }
.num-pos { color: var(--clr-success); }
.num-neg { color: var(--clr-danger); }
.num-neu { color: var(--text-3); }
</style>

<!-- ═══ TABLA RESUMEN GENERAL ═══ -->
<div class="card mb-4">
  <div class="card-header text-center fw-bold" style="font-size:1rem;letter-spacing:.04em">RESUMEN GENERAL <?= $anio ?></div>
  <div class="card-body p-0">
    <table class="table resumen-table mb-0">
      <thead>
        <tr>
          <th style="width:220px">CONCEPTO</th>
          <th>1er trimestre</th>
          <th>2º trimestre</th>
          <th>3er trimestre</th>
          <th>4º trimestre</th>
          <th>ANUAL</th>
        </tr>
      </thead>
      <tbody>

        <!-- ── IRPF ── -->
        <tr class="fila-section"><td colspan="6">IRPF — Modelo 130</td></tr>

        <!-- Ingresos (del trimestre, no acumulado) -->
        <tr>
          <td>INGRESOS</td>
          <?php for ($t=1;$t<=4;$t++): ?>
          <td><?= money($trims[$t]['ventas_base']) ?></td>
          <?php endfor; ?>
          <td class="fw-semibold"><?= money($totIng) ?></td>
        </tr>

        <!-- Gastos -->
        <tr>
          <td>GASTOS</td>
          <?php for ($t=1;$t<=4;$t++): ?>
          <td><?= money($trims[$t]['compras_base']) ?></td>
          <?php endfor; ?>
          <td class="fw-semibold"><?= money($totGas) ?></td>
        </tr>

        <!-- Base compensación anual acumulada -->
        <tr class="fw-semibold">
          <td title="Rendimiento neto acumulado desde enero hasta el final de cada trimestre">BASE COMPENS. ANUAL</td>
          <?php for ($t=1;$t<=4;$t++): $b=$irpfTrim[$t]['base_acum']; ?>
          <td class="<?= $b < 0 ? 'num-neg' : '' ?>"><?= money($b) ?></td>
          <?php endfor; ?>
          <td class="<?= $totBase < 0 ? 'num-neg' : 'fw-bold' ?>"><?= money($totBase) ?></td>
        </tr>

        <!-- IRPF (20% acumulado, a ingresar cada trim.) -->
        <tr>
          <td title="20% sobre base acumulada − retenciones − trimestres anteriores">IRPF (20%)</td>
          <?php for ($t=1;$t<=4;$t++): $ai=$irpfTrim[$t]['a_ingresar']; ?>
          <td class="<?= $ai > 0 ? 'num-neg' : 'num-neu' ?>"><?= money($ai) ?></td>
          <?php endfor; ?>
          <td class="num-neg fw-bold"><?= money($totIrpf) ?></td>
        </tr>

        <!-- Rendimiento final (base trim − irpf trim) -->
        <tr class="fila-total">
          <td>RENDIMIENTO FINAL</td>
          <?php for ($t=1;$t<=4;$t++):
            $rend = $trims[$t]['ventas_base'] - $trims[$t]['compras_base'] - $irpfTrim[$t]['a_ingresar'];
          ?>
          <td class="<?= $rend < 0 ? 'num-neg' : ($rend > 0 ? 'num-pos' : 'num-neu') ?>"><?= money($rend) ?></td>
          <?php endfor; ?>
          <td class="<?= $totRend < 0 ? 'num-neg' : 'num-pos' ?>"><?= money($totRend) ?></td>
        </tr>

        <!-- ── IVA ── -->
        <tr class="fila-section"><td colspan="6">IVA — Modelo 303</td></tr>

        <tr>
          <td>IVA VENTAS, GENERADO</td>
          <?php for ($t=1;$t<=4;$t++): ?>
          <td><?= money($ivaTrim[$t]['generado']) ?></td>
          <?php endfor; ?>
          <td class="fw-semibold"><?= money($totIvaGen) ?></td>
        </tr>

        <tr>
          <td>IVA COMPRAS, DEDUCIB.</td>
          <?php for ($t=1;$t<=4;$t++): ?>
          <td class="num-pos"><?= money($ivaTrim[$t]['deducible']) ?></td>
          <?php endfor; ?>
          <td class="num-pos fw-semibold"><?= money($totIvaDed) ?></td>
        </tr>

        <tr>
          <td title="Negativo del trimestre anterior que se lleva como compensación">COMP. TRIM. ANTERIOR</td>
          <?php for ($t=1;$t<=4;$t++): $ca=$ivaTrim[$t]['comp_ant']; ?>
          <td class="<?= $ca > 0 ? 'num-pos' : 'num-neu' ?>"><?= $ca > 0 ? money($ca) : '—' ?></td>
          <?php endfor; ?>
          <td class="num-neu">—</td>
        </tr>

        <tr class="fila-total">
          <td>RESULTANTE</td>
          <?php for ($t=1;$t<=4;$t++): $r=$ivaTrim[$t]['resultado']; ?>
          <td class="<?= $r > 0 ? 'num-neg' : ($r < 0 ? 'num-pos' : 'num-neu') ?>">
            <?= money($r) ?>
            <?php if ($r > 0): ?><small class="d-block fw-normal" style="font-size:.72rem">↑ A ingresar</small>
            <?php elseif ($r < 0): ?><small class="d-block fw-normal" style="font-size:.72rem">↓ A compensar</small><?php endif; ?>
          </td>
          <?php endfor; ?>
          <td class="<?= $totIvaRes > 0 ? 'num-neg' : ($totIvaRes < 0 ? 'num-pos' : '') ?> fw-bold"><?= money($totIvaRes) ?></td>
        </tr>

      </tbody>
    </table>
  </div>
</div>

<!-- ═══ ACCESOS RÁPIDOS MODELOS ═══ -->
<div class="row g-3 mb-2">
  <div class="col-sm-4">
    <a href="/libros/modelo130.php?anio=<?= $anio ?>" class="card text-decoration-none" style="transition:box-shadow .2s">
      <div class="card-body d-flex align-items-center gap-3 py-3">
        <i class="bi bi-file-earmark-text" style="font-size:1.6rem;color:var(--verde-a)"></i>
        <div>
          <div class="fw-semibold">Modelo 130</div>
          <div class="text-muted" style="font-size:.78rem">Pago fraccionado IRPF</div>
        </div>
      </div>
    </a>
  </div>
  <div class="col-sm-4">
    <a href="/libros/modelo115.php?anio=<?= $anio ?>" class="card text-decoration-none">
      <div class="card-body d-flex align-items-center gap-3 py-3">
        <i class="bi bi-building" style="font-size:1.6rem;color:var(--verde-a)"></i>
        <div>
          <div class="fw-semibold">Modelo 115</div>
          <div class="text-muted" style="font-size:.78rem">Retenciones arrendamientos</div>
        </div>
      </div>
    </a>
  </div>
  <div class="col-sm-4">
    <a href="/libros/modelo347.php?anio=<?= $anio ?>" class="card text-decoration-none">
      <div class="card-body d-flex align-items-center gap-3 py-3">
        <i class="bi bi-people" style="font-size:1.6rem;color:var(--verde-a)"></i>
        <div>
          <div class="fw-semibold">Modelo 347</div>
          <div class="text-muted" style="font-size:.78rem">Operaciones con terceros</div>
        </div>
      </div>
    </a>
  </div>
</div>

<!-- Modal compensaciones -->
<div class="modal fade" id="modalComp" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="post">
        <div class="modal-header">
          <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>Compensaciones <?= $anio ?></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <p class="text-muted" style="font-size:.87rem">Importes pendientes de compensar procedentes de ejercicios anteriores.</p>
          <div class="mb-3">
            <label class="form-label">Base IRPF a compensar (rendimiento negativo año anterior)</label>
            <div class="input-group">
              <input type="number" step="0.01" name="comp_irpf" class="form-control" value="<?= number_format($compIrpf,2,'.','') ?>" min="0">
              <span class="input-group-text">€</span>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">IVA a compensar del ejercicio anterior</label>
            <div class="input-group">
              <input type="number" step="0.01" name="comp_iva" class="form-control" value="<?= number_format($compIva,2,'.','') ?>" min="0">
              <span class="input-group-text">€</span>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary">Guardar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

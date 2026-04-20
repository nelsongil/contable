<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
$pageTitle = 'Resumen fiscal';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/fiscal_info.php';

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

// ── Acumulados para IRPF (Modelo 130) — base = ingresos − TODOS los gastos ────
$ingAcum = $gasAcum = $retAcum = $pagAcum = 0;
$irpfTrim = [];
for ($t = 1; $t <= 4; $t++) {
    $ingAcum += $trims[$t]['ventas_base'];
    $gasAcum += $trims[$t]['total_gastos'];   // facturas + sueldos + SS + autónomo
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
$compAnterior = $compIva;
$ivaTrim = [];
for ($t = 1; $t <= 4; $t++) {
    $generado  = $trims[$t]['ventas_iva'];
    $deducible = $trims[$t]['compras_iva'];       // ya es deducible efectivo
    $resultado = $generado - $deducible - $compAnterior;
    $ivaTrim[$t] = [
        'generado'       => $generado,
        'deducible'      => $deducible,
        'iva_bruto'      => $trims[$t]['compras_iva_bruto'],
        'comp_ant'       => $compAnterior,
        'resultado'      => $resultado,
        // desglose ventas por tipo
        'v_iva_21'       => $trims[$t]['ventas_iva_21'],
        'v_iva_10'       => $trims[$t]['ventas_iva_10'],
        'v_iva_4'        => $trims[$t]['ventas_iva_4'],
        // desglose compras deducibles por tipo
        'c_iva_21'       => $trims[$t]['compras_iva_21'],
        'c_iva_10'       => $trims[$t]['compras_iva_10'],
        'c_iva_4'        => $trims[$t]['compras_iva_4'],
    ];
    $compAnterior = $resultado < 0 ? abs($resultado) : 0;
}

// ── Qué tipos IVA tienen actividad en el año (para sub-filas condicionales) ──
$tiposVentas  = [];
$tiposCompras = [];
foreach ([21, 10, 4] as $tipo) {
    $keyV = "ventas_iva_{$tipo}";
    $keyC = "compras_iva_{$tipo}";
    foreach ($trims as $td) {
        if ($td[$keyV] > 0) { $tiposVentas[$tipo]  = true; }
        if ($td[$keyC] > 0) { $tiposCompras[$tipo] = true; }
    }
}
$hayBrutoDistinto = false; // hay facturas con pct_iva_deducible < 100 en el año
for ($t = 1; $t <= 4; $t++) {
    if ($ivaTrim[$t]['iva_bruto'] > $ivaTrim[$t]['deducible']) {
        $hayBrutoDistinto = true;
        break;
    }
}

// ── Totales anuales ───────────────────────────────────────────────────────────
$totIng         = $irpfTrim[4]['ing_acum'];
$totGas         = $irpfTrim[4]['gas_acum'];   // total_gastos acumulado
$totCompras     = array_sum(array_column($trims, 'compras_base'));
$totSueldos     = array_sum(array_column($trims, 'sueldos'));
$totSsEmpresa   = array_sum(array_column($trims, 'ss_empresa'));
$totAutonomo    = array_sum(array_column($trims, 'cuota_autonomo'));
$totBase        = $totIng - $totGas;
$totIrpf        = $pagAcum;
$totRend        = $totBase - $totIrpf;
$totIvaGen      = array_sum(array_column($ivaTrim, 'generado'));
$totIvaDed      = array_sum(array_column($ivaTrim, 'deducible'));
$totIvaRes      = array_sum(array_column($ivaTrim, 'resultado'));
$totIvaBruto    = array_sum(array_column($ivaTrim, 'iva_bruto'));
$totIvaV21      = array_sum(array_column($ivaTrim, 'v_iva_21'));
$totIvaV10      = array_sum(array_column($ivaTrim, 'v_iva_10'));
$totIvaV4       = array_sum(array_column($ivaTrim, 'v_iva_4'));
$totIvaC21      = array_sum(array_column($ivaTrim, 'c_iva_21'));
$totIvaC10      = array_sum(array_column($ivaTrim, 'c_iva_10'));
$totIvaC4       = array_sum(array_column($ivaTrim, 'c_iva_4'));
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
    <a href="/libros/liquidacion_trimestral.php?anio=<?= $anio ?>&trim=<?= (int)ceil(date('n')/3) ?>" class="btn btn-sm btn-outline-primary">
      <i class="bi bi-file-earmark-check me-1"></i>Liquidación completa
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
.kpi-periodo { border-left: 4px solid var(--verde-m); }
.kpi-periodo .kpi-label { font-size: .72rem; text-transform: uppercase; letter-spacing: .07em; color: var(--text-3); font-weight: 600; }
.kpi-periodo .kpi-valor { font-size: 1.55rem; font-weight: 700; line-height: 1.1; }
</style>

<?php $resultado_periodo = $totIng - $totGas; ?>
<?= fiscalInfoBox([
    'title' => 'Cómo interpretar este resumen',
    'items' => [
        ['label' => 'IRPF — Modelo 130',  'text' => 'Se calcula acumulando ingresos y gastos desde enero. El 20% sobre ese acumulado, menos retenciones soportadas y pagos de trimestres anteriores, es lo que ingresas cada trimestre.'],
        ['label' => 'IVA — Modelo 303',   'text' => 'IVA cobrado en ventas menos IVA deducible en compras. Si el resultado es positivo lo ingresas; si es negativo se compensa en el trimestre siguiente.'],
        ['label' => 'Rendimiento final',  'text' => 'Lo que te "queda" después de restar todos los gastos y el IRPF pagado. No incluye la cuota de autónomo ni otros gastos personales.'],
    ]
]) ?>

<!-- ═══ KPIs DEL PERIODO ═══ -->
<div class="row g-3 mb-4">
  <div class="col-sm-4">
    <div class="card kpi-periodo h-100">
      <div class="card-body py-3 px-4">
        <div class="kpi-label">Ingresos <?= $anio ?></div>
        <div class="kpi-valor"><?= money($totIng) ?></div>
      </div>
    </div>
  </div>
  <div class="col-sm-4">
    <div class="card kpi-periodo h-100">
      <div class="card-body py-3 px-4">
        <div class="kpi-label">Gastos <?= $anio ?></div>
        <div class="kpi-valor text-danger"><?= money($totGas) ?></div>
      </div>
    </div>
  </div>
  <div class="col-sm-4">
    <div class="card kpi-periodo h-100" style="border-left-color:<?= $resultado_periodo >= 0 ? 'var(--clr-success)' : 'var(--clr-danger)' ?>">
      <div class="card-body py-3 px-4">
        <div class="kpi-label">Resultado del periodo</div>
        <div class="kpi-valor <?= $resultado_periodo >= 0 ? 'text-success' : 'text-danger' ?>"><?= money($resultado_periodo) ?></div>
      </div>
    </div>
  </div>
</div>

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

        <!-- Gastos: facturas recibidas -->
        <tr>
          <td class="ps-3 text-muted" style="font-size:.83rem">· Facturas de gastos</td>
          <?php for ($t=1;$t<=4;$t++): ?>
          <td class="text-muted" style="font-size:.83rem"><?= money($trims[$t]['compras_base']) ?></td>
          <?php endfor; ?>
          <td class="text-muted" style="font-size:.83rem"><?= money($totCompras) ?></td>
        </tr>

        <!-- Sueldos brutos -->
        <tr>
          <td class="ps-3 text-muted" style="font-size:.83rem">· Sueldos brutos</td>
          <?php for ($t=1;$t<=4;$t++): ?>
          <td class="text-muted" style="font-size:.83rem"><?= money($trims[$t]['sueldos']) ?></td>
          <?php endfor; ?>
          <td class="text-muted" style="font-size:.83rem"><?= money($totSueldos) ?></td>
        </tr>

        <!-- SS empresa -->
        <tr>
          <td class="ps-3 text-muted" style="font-size:.83rem">· SS a cargo empresa</td>
          <?php for ($t=1;$t<=4;$t++): ?>
          <td class="text-muted" style="font-size:.83rem"><?= money($trims[$t]['ss_empresa']) ?></td>
          <?php endfor; ?>
          <td class="text-muted" style="font-size:.83rem"><?= money($totSsEmpresa) ?></td>
        </tr>

        <!-- Cuota autónomo -->
        <tr>
          <td class="ps-3 text-muted" style="font-size:.83rem">· Cuota autónomo</td>
          <?php for ($t=1;$t<=4;$t++): ?>
          <td class="text-muted" style="font-size:.83rem"><?= money($trims[$t]['cuota_autonomo']) ?></td>
          <?php endfor; ?>
          <td class="text-muted" style="font-size:.83rem"><?= money($totAutonomo) ?></td>
        </tr>

        <!-- Total gastos -->
        <tr>
          <td class="fw-semibold">GASTOS TOTALES</td>
          <?php for ($t=1;$t<=4;$t++): ?>
          <td><?= money($trims[$t]['total_gastos']) ?></td>
          <?php endfor; ?>
          <td class="fw-semibold"><?= money($totGas) ?></td>
        </tr>

        <!-- Base compensación anual acumulada -->
        <tr class="fw-semibold">
          <td>BASE COMPENS. ANUAL<?= helpTip('Rendimiento neto acumulado desde enero: ingresos acum. menos gastos acum. Es la base sobre la que se aplica el 20% del IRPF.') ?></td>
          <?php for ($t=1;$t<=4;$t++): $b=$irpfTrim[$t]['base_acum']; ?>
          <td class="<?= $b < 0 ? 'num-neg' : '' ?>"><?= money($b) ?></td>
          <?php endfor; ?>
          <td class="<?= $totBase < 0 ? 'num-neg' : 'fw-bold' ?>"><?= money($totBase) ?></td>
        </tr>

        <!-- IRPF acumulado (20% sobre base acumulada neta) -->
        <tr>
          <td>IRPF ACUMULADO (20%)<?= helpTip('20% sobre el rendimiento neto acumulado desde enero (ingresos acum. − gastos acum.). Es la cuota bruta antes de restar retenciones.') ?></td>
          <?php for ($t=1;$t<=4;$t++): $ca=$irpfTrim[$t]['cuota_acum']; ?>
          <td class="<?= $ca > 0 ? 'num-neg' : 'num-neu' ?>"><?= money($ca) ?></td>
          <?php endfor; ?>
          <td class="num-neg fw-semibold"><?= money($irpfTrim[4]['cuota_acum']) ?></td>
        </tr>

        <!-- IRPF (20% acumulado, a ingresar cada trim.) -->
        <tr>
          <td>IRPF A INGRESAR (trim.)<?= helpTip('Lo que pagas en Hacienda cada trimestre: IRPF acumulado menos retenciones ya soportadas en tus facturas y pagos de trimestres anteriores.') ?></td>
          <?php for ($t=1;$t<=4;$t++): $ai=$irpfTrim[$t]['a_ingresar']; ?>
          <td class="<?= $ai > 0 ? 'num-neg' : 'num-neu' ?>"><?= money($ai) ?></td>
          <?php endfor; ?>
          <td class="num-neg fw-bold"><?= money($totIrpf) ?></td>
        </tr>

        <!-- Rendimiento final (ingresos − todos los gastos − irpf trim) -->
        <tr class="fila-total">
          <td>RENDIMIENTO FINAL</td>
          <?php for ($t=1;$t<=4;$t++):
            $rend = $trims[$t]['ventas_base'] - $trims[$t]['total_gastos'] - $irpfTrim[$t]['a_ingresar'];
          ?>
          <td class="<?= $rend < 0 ? 'num-neg' : ($rend > 0 ? 'num-pos' : 'num-neu') ?>"><?= money($rend) ?></td>
          <?php endfor; ?>
          <td class="<?= $totRend < 0 ? 'num-neg' : 'num-pos' ?>"><?= money($totRend) ?></td>
        </tr>

        <!-- ── IVA ── -->
        <tr class="fila-section"><td colspan="6">IVA — Modelo 303</td></tr>

        <?php foreach ([21, 10, 4] as $tipo): if (!isset($tiposVentas[$tipo])): continue; endif; ?>
        <tr>
          <td class="ps-3 text-muted" style="font-size:.83rem">· IVA ventas al <?= $tipo ?>%</td>
          <?php for ($t=1;$t<=4;$t++): $v=$ivaTrim[$t]["v_iva_{$tipo}"]; ?>
          <td class="text-muted" style="font-size:.83rem"><?= $v > 0 ? money($v) : '—' ?></td>
          <?php endfor; ?>
          <td class="text-muted fw-semibold" style="font-size:.83rem">
            <?= ($tot = ${'totIvaV'.$tipo}) > 0 ? money($tot) : '—' ?>
          </td>
        </tr>
        <?php endforeach; ?>

        <tr>
          <td>IVA VENTAS TOTAL</td>
          <?php for ($t=1;$t<=4;$t++): ?>
          <td><?= money($ivaTrim[$t]['generado']) ?></td>
          <?php endfor; ?>
          <td class="fw-semibold"><?= money($totIvaGen) ?></td>
        </tr>

        <?php if ($hayBrutoDistinto): ?>
        <tr>
          <td class="ps-3 text-muted" title="IVA soportado antes de aplicar los porcentajes de deducibilidad" style="font-size:.83rem">
            · IVA soportado bruto
          </td>
          <?php for ($t=1;$t<=4;$t++): ?>
          <td class="text-muted" style="font-size:.83rem"><?= money($ivaTrim[$t]['iva_bruto']) ?></td>
          <?php endfor; ?>
          <td class="text-muted fw-semibold" style="font-size:.83rem"><?= money($totIvaBruto) ?></td>
        </tr>
        <?php endif; ?>

        <?php foreach ([21, 10, 4] as $tipo): if (!isset($tiposCompras[$tipo])): continue; endif; ?>
        <tr>
          <td class="ps-3 text-muted" style="font-size:.83rem">· IVA compras deducible <?= $tipo ?>%</td>
          <?php for ($t=1;$t<=4;$t++): $v=$ivaTrim[$t]["c_iva_{$tipo}"]; ?>
          <td class="text-muted num-pos" style="font-size:.83rem"><?= $v > 0 ? money($v) : '—' ?></td>
          <?php endfor; ?>
          <td class="text-muted fw-semibold num-pos" style="font-size:.83rem">
            <?= ($tot = ${'totIvaC'.$tipo}) > 0 ? money($tot) : '—' ?>
          </td>
        </tr>
        <?php endforeach; ?>

        <tr>
          <td>IVA SOPORTADO DEDUCIBLE<?= helpTip('IVA pagado en compras, aplicando los porcentajes de deducibilidad de cada categoría. Un coche de uso mixto, por ejemplo, solo deduce el 50%.') ?></td>
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
          <td>RESULTANTE<?= helpTip('IVA a ingresar si es positivo, o a compensar en el trimestre siguiente si es negativo. Corresponde a la casilla 52 del Modelo 303.') ?></td>
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
  <div class="col-6 col-md-3">
    <a href="/libros/modelo303.php?anio=<?= $anio ?>" class="card text-decoration-none">
      <div class="card-body d-flex align-items-center gap-3 py-3">
        <i class="bi bi-file-earmark-text" style="font-size:1.6rem;color:var(--verde-a)"></i>
        <div>
          <div class="fw-semibold">Modelo 303</div>
          <div class="text-muted" style="font-size:.78rem">Autoliquidación IVA</div>
        </div>
      </div>
    </a>
  </div>
  <div class="col-6 col-md-3">
    <a href="/libros/modelo130.php?anio=<?= $anio ?>" class="card text-decoration-none">
      <div class="card-body d-flex align-items-center gap-3 py-3">
        <i class="bi bi-file-earmark-text" style="font-size:1.6rem;color:var(--verde-a)"></i>
        <div>
          <div class="fw-semibold">Modelo 130</div>
          <div class="text-muted" style="font-size:.78rem">Pago fraccionado IRPF</div>
        </div>
      </div>
    </a>
  </div>
  <div class="col-6 col-md-3">
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
  <div class="col-6 col-md-3">
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

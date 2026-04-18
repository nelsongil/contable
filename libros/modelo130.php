<?php
$pageTitle = 'Modelo 130 — Pago fraccionado IRPF';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/fiscal_info.php';

$anio = (int)get('anio', date('Y'));
$db   = getDB();

/*
 * Modelo 130 — Cálculo acumulativo anual (art. 110 RIRPF)
 * ─────────────────────────────────────────────────────────
 * Cada trimestre se declara sobre el ACUMULADO desde enero:
 *   Cuota = max(0, 20% × (ingresos_acum − gastos_acum) − retenciones_acum − pagado_trims_anteriores)
 *
 * Si la cuota resulta negativa o 0 → no se ingresa nada ese trimestre.
 */

$trims       = [];
$ingAcum     = 0;
$gasAcum     = 0;
$retAcum     = 0;
$pagadoAcum  = 0; // lo ingresado en trimestres anteriores del mismo año

for ($t = 1; $t <= 4; $t++) {
    // Ingresos (base facturas emitidas, excl. canceladas)
    $stV = $db->prepare(
        "SELECT COALESCE(SUM(base_imponible), 0) ing, COALESCE(SUM(cuota_irpf), 0) ret
         FROM facturas_emitidas
         WHERE YEAR(fecha) = ? AND trimestre = ? AND estado != 'cancelada'"
    );
    $stV->execute([$anio, $t]);
    $v = $stV->fetch();

    // Gastos (base facturas recibidas)
    $stG = $db->prepare(
        "SELECT COALESCE(SUM(base_imponible), 0) gas
         FROM facturas_recibidas
         WHERE YEAR(fecha) = ? AND trimestre = ?"
    );
    $stG->execute([$anio, $t]);
    $g = $stG->fetch();

    $ingAcum += (float)$v['ing'];
    $gasAcum += (float)$g['gas'];
    $retAcum += (float)$v['ret'];

    $baseAcum  = $ingAcum - $gasAcum;
    $cuotaBruta = max(0.0, $baseAcum * 0.20);
    $aIngresar  = max(0.0, $cuotaBruta - $retAcum - $pagadoAcum);

    $trims[$t] = [
        'ing_trim'    => (float)$v['ing'],
        'gas_trim'    => (float)$g['gas'],
        'ret_trim'    => (float)$v['ret'],
        'ing_acum'    => $ingAcum,
        'gas_acum'    => $gasAcum,
        'ret_acum'    => $retAcum,
        'base_acum'   => $baseAcum,
        'cuota_bruta' => $cuotaBruta,
        'pagado_prev' => $pagadoAcum,
        'a_ingresar'  => $aIngresar,
    ];

    $pagadoAcum += $aIngresar;
}

$totalIngresado = $pagadoAcum;
?>

<div class="topbar">
  <h1><i class="bi bi-file-earmark-text me-2"></i>Modelo 130 — Pago fraccionado IRPF <?= $anio ?></h1>
  <div class="d-flex gap-2 align-items-center">
    <select class="form-select form-select-sm" style="width:90px" onchange="location.href='?anio='+this.value">
      <?php foreach ([date('Y'), date('Y')-1, date('Y')-2] as $y): ?>
      <option value="<?= $y ?>" <?= $y == $anio ? 'selected' : '' ?>><?= $y ?></option>
      <?php endforeach; ?>
    </select>
  </div>
</div>

<?= fiscalInfoBox([
    'title' => '¿Qué es el Modelo 130?',
    'items' => [
        ['label' => '¿Qué es?',   'text' => 'El pago fraccionado trimestral del IRPF para autónomos en estimación directa. Se presenta 4 veces al año.'],
        ['label' => 'Cálculo',    'text' => '20% × (ingresos acumulados desde enero − gastos acumulados) − retenciones ya soportadas en facturas − lo pagado en trimestres anteriores del mismo año.'],
        ['label' => 'Plazo',      'text' => 'Del 1 al 20 del mes siguiente al trimestre: abril (T1), julio (T2), octubre (T3), enero del año siguiente (T4).'],
        ['label' => 'Ejemplo',    'text' => 'En T2 acumulas 20.000 € de ingresos y 8.000 € de gastos. Base = 12.000 €. Cuota bruta = 2.400 €. Si te retuvieron 600 € y pagaste 900 € en T1, ingresas 900 € más.'],
    ]
]) ?>

<!-- KPIs -->
<div class="row g-3 mb-4">
  <div class="col-sm-3">
    <div class="kpi">
      <div class="label">Ingresos <?= $anio ?></div>
      <div class="value"><?= money($trims[4]['ing_acum']) ?></div>
    </div>
  </div>
  <div class="col-sm-3">
    <div class="kpi">
      <div class="label">Gastos <?= $anio ?></div>
      <div class="value"><?= money($trims[4]['gas_acum']) ?></div>
    </div>
  </div>
  <div class="col-sm-3">
    <div class="kpi">
      <div class="label">Rendimiento neto</div>
      <div class="value <?= $trims[4]['base_acum'] < 0 ? 'text-danger' : '' ?>"><?= money($trims[4]['base_acum']) ?></div>
    </div>
  </div>
  <div class="col-sm-3">
    <div class="kpi kpi-red">
      <div class="label">Total ingresado <?= $anio ?></div>
      <div class="value"><?= money($totalIngresado) ?></div>
    </div>
  </div>
</div>

<!-- Tabla trimestral -->
<div class="card mb-4">
  <div class="card-header"><i class="bi bi-table me-2"></i>Cálculo trimestral acumulado</div>
  <div class="card-body p-0">
    <table class="table mb-0" style="font-size:.86rem">
      <thead>
        <tr>
          <th>Concepto</th>
          <th class="text-end">T1</th>
          <th class="text-end">T2</th>
          <th class="text-end">T3</th>
          <th class="text-end">T4</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td class="text-muted">Ingresos del trimestre</td>
          <?php for ($t=1;$t<=4;$t++): ?>
          <td class="text-end"><?= money($trims[$t]['ing_trim']) ?></td>
          <?php endfor; ?>
        </tr>
        <tr>
          <td class="text-muted">Gastos del trimestre</td>
          <?php for ($t=1;$t<=4;$t++): ?>
          <td class="text-end"><?= money($trims[$t]['gas_trim']) ?></td>
          <?php endfor; ?>
        </tr>
        <tr class="fw-semibold" style="border-top: 2px solid var(--border)">
          <td>Ingresos acumulados (ene–trimestre)</td>
          <?php for ($t=1;$t<=4;$t++): ?>
          <td class="text-end"><?= money($trims[$t]['ing_acum']) ?></td>
          <?php endfor; ?>
        </tr>
        <tr class="fw-semibold">
          <td>Gastos acumulados (ene–trimestre)</td>
          <?php for ($t=1;$t<=4;$t++): ?>
          <td class="text-end"><?= money($trims[$t]['gas_acum']) ?></td>
          <?php endfor; ?>
        </tr>
        <tr class="fw-semibold">
          <td>Rendimiento neto acumulado</td>
          <?php for ($t=1;$t<=4;$t++): ?>
          <td class="text-end <?= $trims[$t]['base_acum'] < 0 ? 'text-danger' : '' ?>"><?= money($trims[$t]['base_acum']) ?></td>
          <?php endfor; ?>
        </tr>
        <tr>
          <td class="text-muted">20% sobre rendimiento acumulado</td>
          <?php for ($t=1;$t<=4;$t++): ?>
          <td class="text-end"><?= money($trims[$t]['cuota_bruta']) ?></td>
          <?php endfor; ?>
        </tr>
        <tr>
          <td class="text-muted">− Retenciones soportadas acumuladas</td>
          <?php for ($t=1;$t<=4;$t++): ?>
          <td class="text-end text-success"><?= money($trims[$t]['ret_acum']) ?></td>
          <?php endfor; ?>
        </tr>
        <tr>
          <td class="text-muted">− Pagos trims. anteriores</td>
          <?php for ($t=1;$t<=4;$t++): ?>
          <td class="text-end text-success"><?= $trims[$t]['pagado_prev'] > 0 ? money($trims[$t]['pagado_prev']) : '—' ?></td>
          <?php endfor; ?>
        </tr>
        <tr class="fw-bold" style="border-top: 2px solid var(--border); background: var(--surface-2)">
          <td>A ingresar cada trimestre</td>
          <?php for ($t=1;$t<=4;$t++): ?>
          <td class="text-end <?= $trims[$t]['a_ingresar'] > 0 ? 'text-danger' : 'text-muted' ?>">
            <?= $trims[$t]['a_ingresar'] > 0 ? money($trims[$t]['a_ingresar']) : '0,00 €' ?>
          </td>
          <?php endfor; ?>
        </tr>
      </tbody>
    </table>
  </div>
</div>

<!-- Resumen de plazos -->
<div class="card">
  <div class="card-header"><i class="bi bi-calendar-check me-2"></i>Presentación <?= $anio ?></div>
  <div class="card-body">
    <div class="row g-3">
      <?php
      $plazos = [
        1 => ['plazo' => '1–20 abril',   'periodo' => 'Ene–Mar'],
        2 => ['plazo' => '1–20 julio',   'periodo' => 'Abr–Jun'],
        3 => ['plazo' => '1–20 octubre', 'periodo' => 'Jul–Sep'],
        4 => ['plazo' => '1–20 enero ' . ($anio+1), 'periodo' => 'Oct–Dic'],
      ];
      for ($t=1;$t<=4;$t++): $p=$plazos[$t]; $ai=$trims[$t]['a_ingresar'];
      ?>
      <div class="col-md-3">
        <div class="kpi py-2 <?= $ai > 0 ? 'kpi-red' : '' ?>">
          <div class="label">T<?= $t ?> · <?= $p['periodo'] ?></div>
          <div class="value" style="font-size:1.2rem"><?= $ai > 0 ? money($ai) : '0,00 €' ?></div>
          <div class="sub"><?= $p['plazo'] ?></div>
        </div>
      </div>
      <?php endfor; ?>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

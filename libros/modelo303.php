<?php
session_start();
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$anio = (int)get('anio', date('Y'));
$trim = (int)get('trim', (int)ceil(date('n') / 3));
if ($trim < 1) $trim = 1;
if ($trim > 4) $trim = 4;

// ── POST: guardar campos editables ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post('action');

    if ($action === 'save_fields') {
        setConfig("iva_bieninv30_{$anio}_{$trim}", (float)str_replace(',', '.', post('casilla30')));
        setConfig("iva_bieninv31_{$anio}_{$trim}", (float)str_replace(',', '.', post('casilla31')));
        setConfig("iva_comp48_{$anio}_{$trim}",    (float)str_replace(',', '.', post('casilla48')));
        flash('Datos del T' . $trim . ' guardados.');
        redirect("/libros/modelo303.php?anio={$anio}&trim={$trim}");
    }

    if ($action === 'save_comp_next') {
        $saldo = round(abs((float)str_replace(',', '.', post('saldo'))), 2);
        if ($trim < 4) {
            $nextTrim = $trim + 1;
            $nextAnio = $anio;
        } else {
            $nextTrim = 1;
            $nextAnio = $anio + 1;
        }
        setConfig("iva_comp48_{$nextAnio}_{$nextTrim}", $saldo);
        flash("Compensación de " . number_format($saldo, 2, ',', '.') . " € guardada para T{$nextTrim} {$nextAnio}.");
        redirect("/libros/modelo303.php?anio={$anio}&trim={$trim}");
    }
}

// ── Datos del trimestre ────────────────────────────────────────────────────────
$d = resumenTrimestral($anio, $trim);

// Bloque A — IVA Devengado
$c01 = $d['ventas_base_21'];  $c03 = $d['ventas_iva_21'];
$c04 = $d['ventas_base_10'];  $c06 = $d['ventas_iva_10'];
$c07 = $d['ventas_base_4'];   $c09 = $d['ventas_iva_4'];
$c27 = $d['ventas_iva'];      // suma total devengado

// Bloque B — IVA Deducible
$c28 = $d['compras_base'];    // base corrientes (informativo)
$c29 = $d['compras_iva'];     // cuota corrientes deducible (automático)
$c30 = (float)getConfig("iva_bieninv30_{$anio}_{$trim}", 0);  // base bienes inversión (manual)
$c31 = (float)getConfig("iva_bieninv31_{$anio}_{$trim}", 0);  // cuota bienes inversión (manual)
$c44 = $c29 + $c31;

// Bloque C — Resultado
$c45 = round($c27 - $c44, 2);
$c46 = 100;                    // % tributación Estado (siempre 100% en Península/Baleares)
$c47 = round($c45 * $c46 / 100, 2);
$c48 = (float)getConfig("iva_comp48_{$anio}_{$trim}", 0);
$c49 = round($c47 - $c48, 2);

// ── CSV export ────────────────────────────────────────────────────────────────
if (isset($_GET['csv'])) {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="modelo303_T' . $trim . '_' . $anio . '.csv"');
    echo "\xEF\xBB\xBF"; // BOM UTF-8

    $rows = [
        ['01', 'Base imponible — tipo 21%',                          $c01],
        ['03', 'Cuota IVA — tipo 21%',                               $c03],
        ['04', 'Base imponible — tipo 10%',                          $c04],
        ['06', 'Cuota IVA — tipo 10%',                               $c06],
        ['07', 'Base imponible — tipo 4%',                           $c07],
        ['09', 'Cuota IVA — tipo 4%',                                $c09],
        ['27', 'Total IVA devengado',                                $c27],
        ['28', 'Base — cuotas deducibles operaciones corrientes',     $c28],
        ['29', 'Cuota — cuotas deducibles operaciones corrientes',    $c29],
        ['30', 'Base — bienes de inversión',                         $c30],
        ['31', 'Cuota — bienes de inversión',                        $c31],
        ['44', 'Total IVA deducible',                                $c44],
        ['45', 'Diferencia (casilla 27 - casilla 44)',               $c45],
        ['46', '% Tributación Administración del Estado',            $c46],
        ['47', 'Cuota resultante tributación al Estado',             $c47],
        ['48', 'Cuotas a compensar de períodos anteriores',          $c48],
        ['49', 'Resultado — A ingresar / A compensar / A devolver',  $c49],
    ];

    echo "Casilla;Descripción;Importe\r\n";
    foreach ($rows as [$num, $desc, $val]) {
        $fmt = $num === '46'
            ? number_format($val, 0, ',', '.') . '%'
            : number_format($val, 2, ',', '.');
        echo "{$num};{$desc};{$fmt}\r\n";
    }
    exit;
}

// ── Plazos de presentación ────────────────────────────────────────────────────
$plazos = [
    1 => ['plazo' => '20 de abril de ' . $anio,       'periodo' => 'Enero–Marzo'],
    2 => ['plazo' => '20 de julio de ' . $anio,        'periodo' => 'Abril–Junio'],
    3 => ['plazo' => '20 de octubre de ' . $anio,      'periodo' => 'Julio–Septiembre'],
    4 => ['plazo' => '30 de enero de ' . ($anio + 1),  'periodo' => 'Octubre–Diciembre'],
];

$pageTitle = "Modelo 303 — T{$trim} {$anio}";
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/fiscal_info.php';
?>

<div class="topbar">
  <h1><i class="bi bi-file-earmark-text me-2"></i>Modelo 303 — IVA T<?= $trim ?> <?= $anio ?></h1>
  <div class="d-flex gap-2 align-items-center flex-wrap">
    <select class="form-select form-select-sm" style="width:90px"
            onchange="location.href='?anio='+this.value+'&trim=<?= $trim ?>'">
      <?php foreach ([date('Y'), date('Y')-1, date('Y')-2] as $y): ?>
      <option value="<?= $y ?>" <?= $y == $anio ? 'selected' : '' ?>><?= $y ?></option>
      <?php endforeach; ?>
    </select>
    <div class="btn-group btn-group-sm" role="group">
      <?php foreach ([1, 2, 3, 4] as $t): ?>
      <a href="?anio=<?= $anio ?>&trim=<?= $t ?>"
         class="btn <?= $t == $trim ? 'btn-primary' : 'btn-outline-secondary' ?>">T<?= $t ?></a>
      <?php endforeach; ?>
    </div>
    <a href="?anio=<?= $anio ?>&trim=<?= $trim ?>&csv=1" class="btn btn-sm btn-outline-success">
      <i class="bi bi-file-earmark-excel me-1"></i>Exportar casillas CSV
    </a>
  </div>
</div>

<?= fiscalInfoBox([
    'title' => '¿Qué es el Modelo 303?',
    'items' => [
        ['label' => '¿Qué es?',   'text' => 'La autoliquidación trimestral del IVA. Declara el IVA cobrado en tus ventas (devengado) menos el IVA pagado en tus compras (deducible). Si el resultado es positivo lo ingresas a Hacienda; si es negativo se compensa en el trimestre siguiente o, en T4, puedes pedir devolución.'],
        ['label' => 'Plazo',      'text' => 'T1: hasta el 20 de abril. T2: hasta el 20 de julio. T3: hasta el 20 de octubre. T4: hasta el 30 de enero del año siguiente (10 días más que los otros trimestres).'],
        ['label' => 'Cálculo',    'text' => 'IVA devengado (cas. 27) − IVA deducible (cas. 44) = Diferencia (cas. 45). Para autónomos peninsulares cas. 46 = 100%, por lo que cas. 47 = cas. 45. Restando compensaciones anteriores (cas. 48) obtienes el resultado final (cas. 49).'],
        ['label' => 'Ejemplo',    'text' => 'Vendes 10.000 € + 21% IVA = 2.100 € devengados. Tienes facturas de gastos con 800 € de IVA deducible. Resultado: 2.100 − 800 = 1.300 € a ingresar (casilla 49).'],
    ]
]) ?>

<!-- KPIs -->
<div class="row g-3 mb-4">
  <div class="col-6 col-xl-3">
    <div class="kpi">
      <div class="label">IVA devengado <?= helpTip('Suma de todo el IVA repercutido en tus facturas de venta este trimestre. Casilla 27 del Modelo 303.') ?></div>
      <div class="value"><?= money($c27) ?></div>
      <div class="sub">Casilla 27</div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="kpi">
      <div class="label">IVA deducible <?= helpTip('IVA pagado en compras y gastos que puedes restar. Casilla 44 = casillas 29 (corrientes) + 31 (bienes de inversión).') ?></div>
      <div class="value" id="kpi_c44"><?= money($c44) ?></div>
      <div class="sub">Casilla 44</div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="kpi">
      <div class="label">Diferencia <?= helpTip('Casilla 27 menos casilla 44. Para autónomos peninsulares (cas. 46 = 100%) coincide con la casilla 47.') ?></div>
      <div class="value <?= $c45 < 0 ? 'text-success' : '' ?>" id="kpi_c45"><?= money($c45) ?></div>
      <div class="sub">Casilla 45</div>
    </div>
  </div>
  <div class="col-6 col-xl-3">
    <div class="kpi <?= $c49 > 0 ? 'kpi-red' : '' ?>">
      <div class="label">Resultado <?= helpTip('Positivo = a ingresar a Hacienda. Negativo en T1-T3 = a compensar el próximo trimestre. Negativo en T4 = a devolver o compensar T1 del año siguiente.') ?></div>
      <div class="value <?= $c49 > 0 ? 'text-danger' : ($c49 < 0 ? 'text-success' : '') ?>"
           id="kpi_c49"><?= money($c49) ?></div>
      <div class="sub">Casilla 49</div>
    </div>
  </div>
</div>

<form method="post">
<input type="hidden" name="action" value="save_fields">

<!-- Bloque A — IVA Devengado -->
<div class="card mb-3">
  <div class="card-header fw-semibold">
    <i class="bi bi-arrow-up-circle me-2" style="color:var(--clr-danger)"></i>Bloque A — IVA Devengado
  </div>
  <div class="card-body p-0">
    <table class="table mb-0" style="font-size:.87rem">
      <thead class="table-light">
        <tr>
          <th>Tipo</th>
          <th class="text-end" style="color:var(--text-3);font-weight:400">Cas.</th>
          <th class="text-end">Base imponible</th>
          <th class="text-end" style="color:var(--text-3);font-weight:400">Cas.</th>
          <th class="text-end">Cuota IVA</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($c01 > 0 || $c03 > 0): ?>
        <tr>
          <td>21%</td>
          <td class="text-end text-muted small">01</td>
          <td class="text-end"><?= money($c01) ?></td>
          <td class="text-end text-muted small">03</td>
          <td class="text-end fw-semibold"><?= money($c03) ?></td>
        </tr>
        <?php endif; ?>
        <?php if ($c04 > 0 || $c06 > 0): ?>
        <tr>
          <td>10%</td>
          <td class="text-end text-muted small">04</td>
          <td class="text-end"><?= money($c04) ?></td>
          <td class="text-end text-muted small">06</td>
          <td class="text-end fw-semibold"><?= money($c06) ?></td>
        </tr>
        <?php endif; ?>
        <?php if ($c07 > 0 || $c09 > 0): ?>
        <tr>
          <td>4%</td>
          <td class="text-end text-muted small">07</td>
          <td class="text-end"><?= money($c07) ?></td>
          <td class="text-end text-muted small">09</td>
          <td class="text-end fw-semibold"><?= money($c09) ?></td>
        </tr>
        <?php endif; ?>
        <?php if (!$c03 && !$c06 && !$c09): ?>
        <tr>
          <td colspan="5" class="text-center text-muted py-3">
            <i class="bi bi-inbox me-1"></i>Sin ventas registradas en este trimestre
          </td>
        </tr>
        <?php endif; ?>
      </tbody>
      <tfoot>
        <tr class="fw-bold" style="background:var(--surface-2)">
          <td colspan="4" class="text-end">27 — Total IVA devengado</td>
          <td class="text-end" style="color:var(--clr-danger)"><?= money($c27) ?></td>
        </tr>
      </tfoot>
    </table>
  </div>
</div>

<!-- Bloque B — IVA Deducible -->
<div class="card mb-3">
  <div class="card-header fw-semibold">
    <i class="bi bi-arrow-down-circle me-2 text-success"></i>Bloque B — IVA Deducible
  </div>
  <div class="card-body p-0">
    <table class="table mb-0 align-middle" style="font-size:.87rem">
      <thead class="table-light">
        <tr>
          <th>Concepto</th>
          <th class="text-end" style="color:var(--text-3);font-weight:400">Cas.</th>
          <th class="text-end">Base</th>
          <th class="text-end" style="color:var(--text-3);font-weight:400">Cas.</th>
          <th class="text-end">Cuota deducible</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td>
            Operaciones corrientes
            <?= helpTip('IVA deducible en compras y gastos del trimestre, aplicando los porcentajes de deducibilidad por categoría. Puede ser inferior al IVA bruto si hay gastos con deducción parcial (p.ej. vehículo de uso mixto al 50%).') ?>
          </td>
          <td class="text-end text-muted small">28</td>
          <td class="text-end"><?= money($c28) ?></td>
          <td class="text-end text-muted small">29</td>
          <td class="text-end fw-semibold text-success"><?= money($c29) ?></td>
        </tr>
        <tr>
          <td>
            Bienes de inversión
            <?= helpTip('Activos con valor unitario superior a 3.005,06 € (vehículos, maquinaria, equipos informáticos…). Si no tienes, deja 0. El IVA de bienes de inversión se regulariza durante 5 años (10 en inmuebles).') ?>
            <div class="text-muted small">Introduce manualmente si aplica</div>
          </td>
          <td class="text-end text-muted small">30</td>
          <td class="text-end">
            <input type="number" name="casilla30" step="0.01" min="0"
                   value="<?= number_format($c30, 2, '.', '') ?>"
                   class="form-control form-control-sm text-end ms-auto" style="width:120px"
                   id="inp_c30">
          </td>
          <td class="text-end text-muted small">31</td>
          <td class="text-end">
            <input type="number" name="casilla31" step="0.01" min="0"
                   value="<?= number_format($c31, 2, '.', '') ?>"
                   class="form-control form-control-sm text-end ms-auto" style="width:120px"
                   id="inp_c31" oninput="recalcular()">
          </td>
        </tr>
      </tbody>
      <tfoot>
        <tr class="fw-bold" style="background:var(--surface-2)">
          <td colspan="4" class="text-end">
            44 — Total IVA deducible
            <?= helpTip('Suma de casillas 29 (operaciones corrientes) y 31 (bienes de inversión).') ?>
          </td>
          <td class="text-end text-success" id="td_c44"><?= money($c44) ?></td>
        </tr>
      </tfoot>
    </table>
  </div>
</div>

<!-- Bloque C — Resultado -->
<div class="card mb-3">
  <div class="card-header fw-semibold">
    <i class="bi bi-calculator me-2"></i>Bloque C — Resultado
  </div>
  <div class="card-body p-0">
    <table class="table mb-0 align-middle" style="font-size:.87rem">
      <tbody>
        <tr>
          <td style="width:60%">45 — Diferencia (casilla 27 − casilla 44)</td>
          <td class="text-end fw-semibold" id="td_c45"><?= money($c45) ?></td>
        </tr>
        <tr>
          <td>
            46 — % Tributación Administración del Estado
            <?= helpTip('Para autónomos en Península y Baleares siempre es el 100%. Solo difiere en contribuyentes bajo las Haciendas Forales del País Vasco y Navarra.') ?>
          </td>
          <td class="text-end text-muted">100%</td>
        </tr>
        <tr>
          <td>47 — Cuota resultante (casilla 45 × 100%)</td>
          <td class="text-end fw-semibold" id="td_c47"><?= money($c47) ?></td>
        </tr>
        <tr>
          <td>
            48 — Cuotas a compensar de períodos anteriores
            <?= helpTip('Saldo negativo acumulado de trimestres anteriores. Se genera cuando el IVA deducible supera al devengado. Usa el botón "Guardar compensación" del trimestre anterior para rellenarlo automáticamente.') ?>
          </td>
          <td class="text-end">
            <input type="number" name="casilla48" step="0.01" min="0"
                   value="<?= number_format($c48, 2, '.', '') ?>"
                   class="form-control form-control-sm text-end ms-auto" style="width:120px"
                   id="inp_c48" oninput="recalcular()">
          </td>
        </tr>
        <tr style="background:var(--surface-2)">
          <td class="fw-bold" style="font-size:.95rem">
            49 — RESULTADO
            <?= helpTip('Positivo = A INGRESAR. Negativo en T1-T3 = A COMPENSAR en el siguiente trimestre (casilla 48). Negativo en T4 = puedes solicitar devolución a Hacienda o arrastrarlo a T1 del año siguiente.') ?>
          </td>
          <td class="text-end fw-bold" id="td_c49"
              style="font-size:1.15rem;color:<?= $c49 > 0 ? 'var(--clr-danger)' : ($c49 < 0 ? 'var(--verde-a)' : 'var(--text-3)') ?>">
            <?= money($c49) ?>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</div>

<div class="d-flex justify-content-end mb-4">
  <button type="submit" class="btn btn-primary">
    <i class="bi bi-floppy me-1"></i>Guardar bienes de inversión y compensación
  </button>
</div>
</form>

<!-- ── Acción según resultado ────────────────────────────────────────────────── -->
<?php
$abs49    = abs($c49);
$nextTrim = $trim < 4 ? $trim + 1 : 1;
$nextAnio = $trim < 4 ? $anio : $anio + 1;
?>
<?php if ($c49 > 0): ?>
<div class="alert alert-danger d-flex align-items-start gap-3 mb-4">
  <i class="bi bi-arrow-up-circle-fill" style="font-size:1.4rem;flex-shrink:0;margin-top:.1rem"></i>
  <div>
    <div class="fw-bold">A INGRESAR — <?= money($c49) ?></div>
    <div>Presenta el Modelo 303 e ingresa este importe antes del <strong><?= $plazos[$trim]['plazo'] ?></strong> en la Sede Electrónica de la AEAT.</div>
  </div>
</div>

<?php elseif ($c49 < 0 && $trim < 4): ?>
<div class="alert alert-success d-flex align-items-start gap-3 mb-4">
  <i class="bi bi-arrow-right-circle-fill" style="font-size:1.4rem;flex-shrink:0;margin-top:.1rem"></i>
  <div>
    <div class="fw-bold">A COMPENSAR — <?= money($abs49) ?></div>
    <div class="mb-2">Este saldo se arrastra a T<?= $nextTrim ?> <?= $nextAnio ?> (casilla 48). Guárdalo ahora para que aparezca pre-rellenado cuando abras T<?= $nextTrim ?>.</div>
    <form method="post" class="d-inline">
      <input type="hidden" name="action" value="save_comp_next">
      <input type="hidden" name="saldo" value="<?= number_format($abs49, 2, '.', '') ?>">
      <button type="submit" class="btn btn-sm btn-success">
        <i class="bi bi-floppy me-1"></i>Guardar compensación para T<?= $nextTrim ?> <?= $nextAnio ?>
      </button>
    </form>
  </div>
</div>

<?php elseif ($c49 < 0 && $trim === 4): ?>
<div class="alert alert-info d-flex align-items-start gap-3 mb-4">
  <i class="bi bi-info-circle-fill" style="font-size:1.4rem;flex-shrink:0;margin-top:.1rem"></i>
  <div>
    <div class="fw-bold">A DEVOLVER — puedes solicitar la devolución a Hacienda o compensarlo en el primer trimestre del año siguiente</div>
    <p class="mb-2 mt-1">Saldo negativo: <strong><?= money($abs49) ?></strong></p>
    <ul class="mb-2" style="font-size:.9rem">
      <li><strong>Solicitar devolución:</strong> márcalo en la casilla correspondiente al presentar el Modelo 303 T4 en la Sede Electrónica. Hacienda ingresa el importe en tu cuenta.</li>
      <li><strong>Compensar en T1 <?= $anio + 1 ?>:</strong> no solicitas devolución y el saldo se aplica en la casilla 48 del primer trimestre del año siguiente.</li>
    </ul>
    <form method="post" class="d-inline">
      <input type="hidden" name="action" value="save_comp_next">
      <input type="hidden" name="saldo" value="<?= number_format($abs49, 2, '.', '') ?>">
      <button type="submit" class="btn btn-sm btn-outline-info">
        <i class="bi bi-floppy me-1"></i>Compensar en T1 <?= $anio + 1 ?> (guardar saldo)
      </button>
    </form>
  </div>
</div>

<?php else: ?>
<div class="alert alert-secondary d-flex align-items-center gap-3 mb-4">
  <i class="bi bi-dash-circle" style="font-size:1.2rem;flex-shrink:0"></i>
  <div><span class="fw-bold">RESULTADO CERO</span> — No hay IVA a ingresar ni saldo a compensar.</div>
</div>
<?php endif; ?>

<!-- Plazo de presentación -->
<div class="card mb-4">
  <div class="card-header"><i class="bi bi-calendar-check me-2"></i>Plazo de presentación — T<?= $trim ?> <?= $anio ?></div>
  <div class="card-body">
    <div class="row g-3 align-items-center">
      <div class="col-sm-5">
        <div class="kpi py-2 <?= $c49 > 0 ? 'kpi-red' : '' ?>">
          <div class="label">T<?= $trim ?> · <?= $plazos[$trim]['periodo'] ?></div>
          <div class="value" style="font-size:1.15rem"><?= money(max(0, $c49)) ?></div>
          <div class="sub">Hasta el <?= $plazos[$trim]['plazo'] ?></div>
        </div>
      </div>
      <div class="col-sm-7 text-muted" style="font-size:.84rem">
        <p class="mb-1"><i class="bi bi-globe me-1"></i>Presentación en la Sede Electrónica:</p>
        <p class="mb-2"><code>sede.agenciatributaria.gob.es → IVA → Modelo 303</code></p>
        <?php if ($trim === 4): ?>
        <p class="mb-0"><i class="bi bi-info-circle me-1"></i>El T4 tiene plazo hasta el 30 de enero (10 días más). Si el resultado es negativo y solicitas devolución, el plazo también es el 30 de enero.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<script>
const C27 = <?= json_encode($c27) ?>;
const C29 = <?= json_encode($c29) ?>;

function fmt(n) {
    return n.toLocaleString('es-ES', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' \u20ac';
}

function setColor(el, val) {
    if (val > 0)      el.style.color = 'var(--clr-danger)';
    else if (val < 0) el.style.color = 'var(--verde-a)';
    else              el.style.color = 'var(--text-3)';
}

function recalcular() {
    const c31 = parseFloat(document.getElementById('inp_c31').value) || 0;
    const c48 = parseFloat(document.getElementById('inp_c48').value) || 0;

    const c44 = C29 + c31;
    const c45 = C27 - c44;
    const c47 = c45; // cas. 46 = 100%
    const c49 = c47 - c48;

    document.getElementById('td_c44').textContent  = fmt(c44);
    document.getElementById('kpi_c44').textContent = fmt(c44);
    document.getElementById('td_c45').textContent  = fmt(c45);
    document.getElementById('kpi_c45').textContent = fmt(c45);
    document.getElementById('td_c47').textContent  = fmt(c47);
    document.getElementById('td_c49').textContent  = fmt(c49);
    document.getElementById('kpi_c49').textContent = fmt(c49);

    setColor(document.getElementById('td_c49'),  c49);
    setColor(document.getElementById('kpi_c49'), c49);
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

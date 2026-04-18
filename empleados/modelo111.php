<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

if (!getConfig('modulo_empleados', false)) redirect('/');

$anio = (int)get('anio', date('Y'));

$trims = [];
$totPerceptores = $totBase = $totRetenciones = 0;

for ($t = 1; $t <= 4; $t++) {
    $r = resumenModelo111($anio, $t);
    $trims[$t] = $r;
    $totPerceptores = max($totPerceptores, (int)$r['perceptores']); // máximo de perceptores del año
    $totBase        += (float)$r['base'];
    $totRetenciones += (float)$r['retenciones'];
}

$pageTitle = 'Modelo 111';
require_once __DIR__ . '/../includes/header.php';
require_once __DIR__ . '/../includes/fiscal_info.php';
?>

<div class="topbar">
  <h1><i class="bi bi-file-earmark-text me-2"></i>Modelo 111 — Retenciones IRPF empleados <?= $anio ?></h1>
  <div class="d-flex gap-2 align-items-center">
    <select class="form-select form-select-sm" style="width:90px" onchange="location.href='?anio='+this.value">
      <?php foreach ([date('Y'), date('Y')-1, date('Y')-2] as $y): ?>
      <option value="<?= $y ?>" <?= $y == $anio ? 'selected' : '' ?>><?= $y ?></option>
      <?php endforeach; ?>
    </select>
    <a href="retenciones.php?anio=<?= $anio ?>" class="btn btn-sm btn-outline-primary">
      <i class="bi bi-pencil-square me-1"></i>Registrar retenciones
    </a>
  </div>
</div>

<?= fiscalInfoBox([
    'title' => '¿Qué es el Modelo 111?',
    'items' => [
        ['label' => '¿Qué es?',   'text' => 'Declaración trimestral de las retenciones del IRPF practicadas sobre los sueldos de tus empleados. Lo presentas tú como empleador.'],
        ['label' => 'Plazo',      'text' => 'Del 1 al 20 del mes siguiente al trimestre: abril (T1), julio (T2), octubre (T3), enero del año siguiente (T4).'],
        ['label' => 'Contenido',  'text' => 'Número de perceptores (empleados), total de salarios brutos pagados en el trimestre, y total de retenciones de IRPF practicadas.'],
        ['label' => 'Ejemplo',    'text' => 'Un empleado cobra 7.500 € brutos en el trimestre con retención del 15%: retienes 1.125 €. Le pagas 6.375 € e ingresas 1.125 € a Hacienda con este modelo.'],
    ]
]) ?>

<!-- Resumen KPI -->
<div class="row g-3 mb-4">
  <div class="col-md-4">
    <div class="kpi">
      <div class="label">Perceptores (máximo del año)</div>
      <div class="value"><?= $totPerceptores ?></div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="kpi">
      <div class="label">Base total de retenciones <?= $anio ?></div>
      <div class="value"><?= money($totBase) ?></div>
    </div>
  </div>
  <div class="col-md-4">
    <div class="kpi kpi-gold">
      <div class="label">IRPF retenido total <?= $anio ?></div>
      <div class="value"><?= money($totRetenciones) ?></div>
    </div>
  </div>
</div>

<!-- Tabla trimestral -->
<div class="card mb-4">
  <div class="card-header"><i class="bi bi-table me-2"></i>Desglose trimestral — Modelo 111</div>
  <div class="card-body p-0">
    <table class="table mb-0">
      <thead>
        <tr>
          <th>Trimestre</th>
          <th>Meses</th>
          <th class="text-end">Perceptores</th>
          <th class="text-end">Base retenciones</th>
          <th class="text-end fw-bold">IRPF a ingresar</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $rangoMeses = [1=>'Ene–Mar', 2=>'Abr–Jun', 3=>'Jul–Sep', 4=>'Oct–Dic'];
        for ($t = 1; $t <= 4; $t++):
            $r = $trims[$t];
            $sinDatos = (float)$r['base'] == 0 && (int)$r['perceptores'] == 0;
        ?>
        <tr class="<?= $sinDatos ? 'text-muted' : '' ?>">
          <td><span class="badge-trim">T<?= $t ?></span></td>
          <td><?= $rangoMeses[$t] ?></td>
          <td class="text-end"><?= (int)$r['perceptores'] ?></td>
          <td class="text-end"><?= money((float)$r['base']) ?></td>
          <td class="text-end fw-bold <?= (float)$r['retenciones'] > 0 ? 'text-danger' : 'text-muted' ?>">
            <?= money((float)$r['retenciones']) ?>
            <?php if (!$sinDatos): ?>
            <small class="d-block fw-normal" style="font-size:.75rem">↑ A ingresar a Hacienda</small>
            <?php endif; ?>
          </td>
        </tr>
        <?php endfor; ?>
      </tbody>
      <tfoot>
        <tr class="fw-bold" style="background:#f0f7f4">
          <td colspan="2">TOTAL ANUAL</td>
          <td class="text-end">—</td>
          <td class="text-end"><?= money($totBase) ?></td>
          <td class="text-end text-danger"><?= money($totRetenciones) ?></td>
        </tr>
      </tfoot>
    </table>
  </div>
</div>

<!-- Instrucciones -->
<div class="alert alert-info py-2" style="font-size:.85rem; max-width:700px">
  <i class="bi bi-info-circle me-2"></i>
  <strong>Cómo usar estos datos:</strong> En cada declaración trimestral del Modelo 111,
  introduce la cifra de <em>perceptores</em>, la <em>base de retenciones</em> y el <em>importe a ingresar</em>
  correspondientes al trimestre. El ingreso se realiza en los 20 primeros días del mes siguiente al trimestre
  (abril, julio, octubre y enero).
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

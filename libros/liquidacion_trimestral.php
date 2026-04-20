<?php
session_start();
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$anio = (int)get('anio', date('Y'));
$trim = (int)get('trim', (int)ceil(date('n') / 3));
if ($trim < 1) $trim = 1;
if ($trim > 4) $trim = 4;

$db = getDB();

// ── Plazos de presentación ────────────────────────────────────────────────────
$plazos = [
    1 => ['plazo' => '20 de abril de ' . $anio,       'periodo' => 'Enero–Marzo'],
    2 => ['plazo' => '20 de julio de ' . $anio,        'periodo' => 'Abril–Junio'],
    3 => ['plazo' => '20 de octubre de ' . $anio,      'periodo' => 'Julio–Septiembre'],
    4 => ['plazo' => '30 de enero de ' . ($anio + 1),  'periodo' => 'Octubre–Diciembre'],
];
$periodo = $plazos[$trim]['periodo'];
$plazo   = $plazos[$trim]['plazo'];

// ── Modelo 303 ────────────────────────────────────────────────────────────────
$d        = resumenTrimestral($anio, $trim);
$m303_c27 = $d['ventas_iva'];
$m303_c29 = $d['compras_iva'];
$m303_c31 = (float)getConfig("iva_bieninv31_{$anio}_{$trim}", 0);
$m303_c44 = $m303_c29 + $m303_c31;
$m303_c45 = round($m303_c27 - $m303_c44, 2);
$m303_c48 = (float)getConfig("iva_comp48_{$anio}_{$trim}", 0);
$m303_c49 = round($m303_c45 - $m303_c48, 2);
$m303_ingresar = max(0.0, $m303_c49); // negativo = compensa, no suma al total

// ── Modelo 130 ────────────────────────────────────────────────────────────────
// Cálculo acumulativo desde T1 hasta el trimestre seleccionado (art. 110 RIRPF)
$ingAcum = $gasAcum = $retAcum = $pagAcum = 0;
$m130    = [];

for ($t = 1; $t <= $trim; $t++) {
    $stV = $db->prepare(
        "SELECT COALESCE(SUM(base_imponible),0) ing, COALESCE(SUM(cuota_irpf),0) ret
         FROM facturas_emitidas
         WHERE YEAR(fecha)=? AND trimestre=? AND estado!='cancelada'"
    );
    $stV->execute([$anio, $t]);
    $v = $stV->fetch();

    $stG = $db->prepare(
        "SELECT COALESCE(SUM(base_imponible),0) gas
         FROM facturas_recibidas
         WHERE YEAR(fecha)=? AND trimestre=?"
    );
    $stG->execute([$anio, $t]);
    $g = $stG->fetch();

    $ingAcum += (float)$v['ing'];
    $gasAcum += (float)$g['gas'];
    $retAcum += (float)$v['ret'];

    $baseAcum   = $ingAcum - $gasAcum;
    $cuotaBruta = max(0.0, $baseAcum * 0.20);
    $aIngresar  = max(0.0, $cuotaBruta - $retAcum - $pagAcum);

    $m130[$t] = [
        'ing_acum'    => $ingAcum,
        'gas_acum'    => $gasAcum,
        'base_acum'   => $baseAcum,
        'cuota_bruta' => $cuotaBruta,
        'ret_acum'    => $retAcum,
        'pagado_prev' => $pagAcum,
        'a_ingresar'  => $aIngresar,
    ];
    $pagAcum += $aIngresar;
}

$m130_trim     = $m130[$trim];
$m130_ingresar = $m130_trim['a_ingresar'];

// ── Modelo 111 (solo si módulo activo) ───────────────────────────────────────
$modulo_empleados = (bool)getConfig('modulo_empleados', false);
$m111_ingresar    = 0.0;
$m111             = null;

if ($modulo_empleados) {
    try {
        $m111 = resumenModelo111($anio, $trim);
        $m111_ingresar = (float)$m111['retenciones'];
    } catch (Exception) {
        $m111 = null;
    }
}

// ── Total consolidado ─────────────────────────────────────────────────────────
$total_hacienda = $m303_ingresar + $m130_ingresar + $m111_ingresar;

$pageTitle = "Liquidación T{$trim} {$anio}";
require_once __DIR__ . '/../includes/header.php';
?>

<style>
/* ── Controles de pantalla ── */
.liq-controls { display: flex; gap: .5rem; align-items: center; flex-wrap: wrap; }

/* ── Documento ── */
.liq-doc {
    background: #fff;
    color: #1a1a1a;
    max-width: 760px;
    margin: 0 auto 2rem;
    padding: 28px 32px;
    border: 1px solid var(--border);
    border-radius: 8px;
    font-size: 9.5pt;
    font-family: 'Inter', 'Segoe UI', sans-serif;
    line-height: 1.45;
}
.liq-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding-bottom: 14px;
    margin-bottom: 16px;
    border-bottom: 3px solid #1a3a2a;
}
.liq-empresa-nombre { font-size: 13pt; font-weight: 700; color: #1a3a2a; margin-bottom: 3px; }
.liq-empresa-sub    { font-size: 8.5pt; color: #555; }
.liq-titulo         { text-align: right; }
.liq-titulo-main    { font-size: 15pt; font-weight: 700; color: #1a3a2a; line-height: 1.1; }
.liq-titulo-sub     { font-size: 8.5pt; color: #555; margin-top: 3px; }

.liq-bloque { margin-bottom: 14px; }
.liq-bloque-title {
    background: #1a3a2a;
    color: #fff;
    font-weight: 600;
    font-size: 8pt;
    text-transform: uppercase;
    letter-spacing: .07em;
    padding: 5px 10px;
    margin-bottom: 0;
    border-radius: 4px 4px 0 0;
}
.liq-table {
    width: 100%;
    border-collapse: collapse;
    border: 1px solid #d0d0d0;
    border-top: none;
    border-radius: 0 0 4px 4px;
    overflow: hidden;
}
.liq-table td {
    padding: 5px 10px;
    border-bottom: 1px solid #ebebeb;
    font-size: 9pt;
}
.liq-table tr:last-child td { border-bottom: none; }
.liq-table td:last-child { text-align: right; font-variant-numeric: tabular-nums; }
.liq-table .liq-sep td { border-top: 1px solid #bbb; background: #f7f7f7; }
.liq-table .liq-result td {
    background: #f0f7f4;
    font-weight: 700;
    font-size: 9.5pt;
    border-top: 2px solid #1a3a2a;
}
.liq-table .liq-result td:last-child { color: #b91c1c; }
.liq-table .liq-result.compensar td:last-child { color: #059669; }
.liq-plazo {
    font-size: 7.5pt;
    color: #777;
    text-align: right;
    padding: 2px 2px 8px;
}

.liq-total-box {
    border: 2.5px solid #1a3a2a;
    border-radius: 4px;
    margin-bottom: 14px;
    overflow: hidden;
}
.liq-total-header {
    background: #1a3a2a;
    color: #fff;
    font-weight: 700;
    font-size: 10pt;
    text-transform: uppercase;
    letter-spacing: .06em;
    padding: 7px 12px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}
.liq-total-header span:last-child { font-size: 13pt; }
.liq-total-detalle {
    padding: 5px 12px 7px;
    font-size: 8pt;
    color: #555;
    background: #f0f7f4;
    display: flex;
    gap: 18px;
}

.liq-firma {
    display: flex;
    justify-content: space-between;
    gap: 16px;
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px solid #d0d0d0;
}
.liq-firma-campo {
    flex: 1;
    border-bottom: 1px solid #999;
    padding-bottom: 3px;
    font-size: 8.5pt;
    color: #444;
    min-height: 28px;
}
.liq-firma-label { font-size: 7.5pt; color: #888; margin-top: 3px; }

.liq-footer {
    margin-top: 12px;
    padding-top: 8px;
    border-top: 1px solid #e0e0e0;
    font-size: 7.5pt;
    color: #888;
    text-align: center;
    line-height: 1.5;
}

/* ── Print ── */
@media print {
    aside.sidebar,
    #sidebarToggle,
    #sidebarBackdrop,
    .topbar,
    .liq-controls,
    .no-print { display: none !important; }

    main.main { margin: 0 !important; padding: 0 !important; }

    .liq-doc {
        max-width: 100%;
        margin: 0;
        padding: 0;
        border: none;
        border-radius: 0;
        box-shadow: none;
    }

    @page { size: A4 portrait; margin: 12mm 15mm; }
}
</style>

<!-- Topbar -->
<div class="topbar no-print">
  <h1><i class="bi bi-file-earmark-check me-2"></i>Liquidación trimestral T<?= $trim ?> <?= $anio ?></h1>
  <div class="liq-controls">
    <select class="form-select form-select-sm" style="width:90px"
            onchange="location.href='?anio='+this.value+'&trim=<?= $trim ?>'">
      <?php foreach ([date('Y'), date('Y')-1, date('Y')-2] as $y): ?>
      <option value="<?= $y ?>" <?= $y == $anio ? 'selected' : '' ?>><?= $y ?></option>
      <?php endforeach; ?>
    </select>
    <div class="btn-group btn-group-sm">
      <?php foreach ([1,2,3,4] as $t): ?>
      <a href="?anio=<?= $anio ?>&trim=<?= $t ?>"
         class="btn <?= $t == $trim ? 'btn-primary' : 'btn-outline-secondary' ?>">T<?= $t ?></a>
      <?php endforeach; ?>
    </div>
    <button onclick="window.print()" class="btn btn-sm btn-success">
      <i class="bi bi-printer me-1"></i>Imprimir / Descargar PDF
    </button>
  </div>
</div>

<!-- Documento -->
<div class="liq-doc">

  <!-- Cabecera empresa + periodo -->
  <div class="liq-header">
    <div>
      <div class="liq-empresa-nombre"><?= e(defined('EMPRESA_NOMBRE') ? EMPRESA_NOMBRE : getConfig('empresa_nombre', '—')) ?></div>
      <div class="liq-empresa-sub">
        CIF: <?= e(defined('EMPRESA_CIF') ? EMPRESA_CIF : '—') ?><br>
        <?php if (defined('EMPRESA_DIR1') && EMPRESA_DIR1): ?>
        <?= e(EMPRESA_DIR1) ?><?= (defined('EMPRESA_DIR2') && EMPRESA_DIR2) ? ', ' . e(EMPRESA_DIR2) : '' ?>
        <?php endif; ?>
      </div>
    </div>
    <div class="liq-titulo">
      <div class="liq-titulo-main">Liquidación fiscal</div>
      <div class="liq-titulo-sub">
        T<?= $trim ?> <?= $anio ?> · <?= $periodo ?><br>
        Generado el <?= date('d/m/Y') ?>
      </div>
    </div>
  </div>

  <!-- ── MODELO 303 ── -->
  <div class="liq-bloque">
    <div class="liq-bloque-title">Modelo 303 — IVA trimestral</div>
    <table class="liq-table">
      <tr>
        <td>IVA devengado <span style="color:#999;font-size:8pt">(cas. 27)</span></td>
        <td><?= money($m303_c27) ?></td>
      </tr>
      <tr>
        <td>IVA deducible <span style="color:#999;font-size:8pt">(cas. 44)</span></td>
        <td>− <?= money($m303_c44) ?></td>
      </tr>
      <?php if ($m303_c48 > 0): ?>
      <tr>
        <td>Compensación anterior <span style="color:#999;font-size:8pt">(cas. 48)</span></td>
        <td>− <?= money($m303_c48) ?></td>
      </tr>
      <?php endif; ?>
      <tr class="liq-result <?= $m303_c49 <= 0 ? 'compensar' : '' ?>">
        <td>RESULTADO <span style="font-weight:400;font-size:8pt">(cas. 49)</span></td>
        <td>
          <?php if ($m303_c49 > 0): ?>
            A INGRESAR: <?= money($m303_c49) ?>
          <?php elseif ($m303_c49 < 0): ?>
            A COMPENSAR: <?= money(abs($m303_c49)) ?>
          <?php else: ?>
            Sin cuota (0,00 €)
          <?php endif; ?>
        </td>
      </tr>
    </table>
    <div class="liq-plazo">Plazo: hasta el <?= $plazo ?></div>
  </div>

  <!-- ── MODELO 130 ── -->
  <div class="liq-bloque">
    <div class="liq-bloque-title">Modelo 130 — IRPF pago fraccionado</div>
    <table class="liq-table">
      <tr>
        <td>Rendimiento neto acumulado (ene–<?= ['', 'mar', 'jun', 'sep', 'dic'][$trim] ?> <?= $anio ?>)</td>
        <td><?= money($m130_trim['base_acum']) ?></td>
      </tr>
      <tr>
        <td>Cuota bruta (20%)</td>
        <td><?= money($m130_trim['cuota_bruta']) ?></td>
      </tr>
      <tr>
        <td>− Retenciones soportadas acumuladas</td>
        <td>− <?= money($m130_trim['ret_acum']) ?></td>
      </tr>
      <?php if ($m130_trim['pagado_prev'] > 0): ?>
      <tr>
        <td>− Pagado en trimestres anteriores</td>
        <td>− <?= money($m130_trim['pagado_prev']) ?></td>
      </tr>
      <?php endif; ?>
      <tr class="liq-result <?= $m130_ingresar == 0 ? 'compensar' : '' ?>">
        <td>A INGRESAR este trimestre</td>
        <td><?= money($m130_ingresar) ?></td>
      </tr>
    </table>
    <div class="liq-plazo">Plazo: hasta el <?= $plazo ?></div>
  </div>

  <!-- ── MODELO 111 (solo si módulo activo y hay datos) ── -->
  <?php if ($modulo_empleados && $m111 !== null): ?>
  <div class="liq-bloque">
    <div class="liq-bloque-title">Modelo 111 — Retenciones empleados</div>
    <table class="liq-table">
      <tr>
        <td>Perceptores</td>
        <td><?= (int)$m111['perceptores'] ?></td>
      </tr>
      <tr>
        <td>Base de retenciones</td>
        <td><?= money((float)$m111['base']) ?></td>
      </tr>
      <tr class="liq-result <?= $m111_ingresar == 0 ? 'compensar' : '' ?>">
        <td>RETENCIONES A INGRESAR</td>
        <td><?= money($m111_ingresar) ?></td>
      </tr>
    </table>
    <div class="liq-plazo">Plazo: hasta el <?= $plazo ?></div>
  </div>
  <?php endif; ?>

  <!-- ── TOTAL CONSOLIDADO ── -->
  <div class="liq-total-box">
    <div class="liq-total-header">
      <span>Total a ingresar a Hacienda</span>
      <span><?= money($total_hacienda) ?></span>
    </div>
    <div class="liq-total-detalle">
      <span>303: <?= money($m303_ingresar) ?></span>
      <span>130: <?= money($m130_ingresar) ?></span>
      <?php if ($modulo_empleados && $m111 !== null): ?>
      <span>111: <?= money($m111_ingresar) ?></span>
      <?php endif; ?>
      <?php if ($m303_c49 < 0): ?>
      <span style="color:#059669">303 con saldo a compensar de <?= money(abs($m303_c49)) ?> — contribuye 0 al total</span>
      <?php endif; ?>
    </div>
  </div>

  <!-- ── LÍNEA DE FIRMA ── -->
  <div class="liq-firma">
    <div style="flex:2">
      <div class="liq-firma-campo"></div>
      <div class="liq-firma-label">Revisado por asesor</div>
    </div>
    <div style="flex:1">
      <div class="liq-firma-campo"></div>
      <div class="liq-firma-label">Fecha</div>
    </div>
    <div style="flex:1.5">
      <div class="liq-firma-campo"></div>
      <div class="liq-firma-label">Firma</div>
    </div>
  </div>

  <!-- ── PIE ── -->
  <div class="liq-footer">
    Generado por Libro Contable v<?= defined('APP_VERSION') ? APP_VERSION : '' ?> · Solo para control contable.<br>
    Este documento no sustituye a la declaración oficial presentada en la Sede Electrónica de la AEAT.
  </div>

</div><!-- /liq-doc -->

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

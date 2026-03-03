<?php
$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/header.php';

$anio = (int)date('Y');
$trimActual = trimestre(date('Y-m-d'));

// KPIs anuales
$db = getDB();
$ve = $db->prepare("SELECT COALESCE(SUM(base_imponible),0) base, COALESCE(SUM(cuota_iva),0) iva, COUNT(*) cnt
                    FROM facturas_emitidas WHERE YEAR(fecha)=? AND estado!='cancelada'");
$ve->execute([$anio]); $ventas = $ve->fetch();

$co = $db->prepare("SELECT COALESCE(SUM(base_imponible),0) base, COALESCE(SUM(cuota_iva),0) iva, COUNT(*) cnt
                    FROM facturas_recibidas WHERE YEAR(fecha)=?");
$co->execute([$anio]); $compras = $co->fetch();

$ivaAnual    = $ventas['iva'] - $compras['iva'];
$rendimiento = $ventas['base'] - $compras['base'];
$irpfEstimado = max(0, $rendimiento * EMPRESA_IRPF);

// Últimas facturas
$ultimas = $db->prepare("SELECT fe.numero, fe.fecha, fe.total, fe.estado, fe.cliente_nombre
                         FROM facturas_emitidas fe WHERE YEAR(fe.fecha)=? ORDER BY fe.fecha DESC, fe.id DESC LIMIT 6");
$ultimas->execute([$anio]); $ultimas = $ultimas->fetchAll();

// Resumen trimestral
$trims = [];
for ($t = 1; $t <= 4; $t++) $trims[$t] = resumenTrimestral($anio, $t);
?>

<div class="topbar">
  <h1><i class="bi bi-speedometer2 me-2"></i>Dashboard <?= $anio ?></h1>
  <div class="d-flex gap-2">
    <span class="badge bg-secondary">T<?= $trimActual ?> activo</span>
    <a href="facturas/nueva.php" class="btn btn-gold btn-sm"><i class="bi bi-plus-lg me-1"></i>Nueva factura</a>
  </div>
</div>

<!-- KPIs -->
<div class="row g-3 mb-4">
  <div class="col-sm-6 col-xl-3">
    <div class="kpi">
      <div class="label">Ingresos <?= $anio ?></div>
      <div class="value"><?= money($ventas['base']) ?></div>
      <div class="sub"><?= $ventas['cnt'] ?> facturas emitidas</div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="kpi">
      <div class="label">Gastos <?= $anio ?></div>
      <div class="value"><?= money($compras['base']) ?></div>
      <div class="sub"><?= $compras['cnt'] ?> facturas recibidas</div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3 <?= $ivaAnual >= 0 ? '' : 'kpi-gold' ?>">
    <div class="kpi">
      <div class="label">IVA resultante acumulado</div>
      <div class="value"><?= money($ivaAnual) ?></div>
      <div class="sub"><?= $ivaAnual >= 0 ? 'A ingresar' : 'A compensar' ?></div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3">
    <div class="kpi">
      <div class="label">Rendimiento neto</div>
      <div class="value <?= $rendimiento < 0 ? 'text-danger' : '' ?>"><?= money($rendimiento) ?></div>
      <div class="sub">IRPF estimado: <?= money($irpfEstimado) ?></div>
    </div>
  </div>
</div>

<div class="row g-3">
  <!-- Resumen trimestral -->
  <div class="col-lg-7">
    <div class="card">
      <div class="card-header"><i class="bi bi-table me-2"></i>Resumen trimestral <?= $anio ?></div>
      <div class="card-body p-0">
        <table class="table table-hover mb-0">
          <thead>
            <tr>
              <th>Trimestre</th>
              <th class="text-end">Ingresos</th>
              <th class="text-end">Gastos</th>
              <th class="text-end">IVA neto</th>
              <th class="text-end">Rdto.</th>
            </tr>
          </thead>
          <tbody>
            <?php for ($t = 1; $t <= 4; $t++): $r = $trims[$t]; ?>
            <tr class="<?= $t === $trimActual ? 'table-warning fw-semibold' : '' ?>">
              <td><span class="badge-trim">T<?= $t ?></span></td>
              <td class="text-end"><?= money($r['ventas_base']) ?></td>
              <td class="text-end"><?= money($r['compras_base']) ?></td>
              <td class="text-end <?= $r['iva_resultado'] < 0 ? 'text-success' : 'text-danger' ?>">
                <?= money($r['iva_resultado']) ?>
              </td>
              <td class="text-end fw-semibold"><?= money($r['rendimiento']) ?></td>
            </tr>
            <?php endfor; ?>
          </tbody>
          <tfoot>
            <tr class="fw-bold" style="background:#f0f7f4">
              <td>ANUAL</td>
              <td class="text-end"><?= money($ventas['base']) ?></td>
              <td class="text-end"><?= money($compras['base']) ?></td>
              <td class="text-end <?= $ivaAnual < 0 ? 'text-success' : 'text-danger' ?>"><?= money($ivaAnual) ?></td>
              <td class="text-end"><?= money($rendimiento) ?></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  </div>

  <!-- Últimas facturas -->
  <div class="col-lg-5">
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-receipt me-2"></i>Últimas facturas</span>
        <a href="facturas/" class="btn btn-sm btn-outline-light">Ver todas</a>
      </div>
      <div class="card-body p-0">
        <table class="table table-hover mb-0">
          <thead><tr><th>Nº</th><th>Cliente</th><th class="text-end">Total</th><th>Estado</th></tr></thead>
          <tbody>
            <?php foreach ($ultimas as $f): ?>
            <tr>
              <td><a href="facturas/ver.php?id=<?= $f['id'] ?? '' ?>" class="text-decoration-none fw-semibold"><?= e($f['numero']) ?></a></td>
              <td class="text-truncate" style="max-width:120px"><?= e($f['cliente_nombre']) ?></td>
              <td class="text-end"><?= money($f['total']) ?></td>
              <td>
                <?php
                  $badges = ['borrador'=>'secondary','emitida'=>'primary','pagada'=>'success','cancelada'=>'danger'];
                  $b = $badges[$f['estado']] ?? 'secondary';
                ?>
                <span class="badge bg-<?= $b ?>"><?= e($f['estado']) ?></span>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if (!$ultimas): ?>
            <tr><td colspan="4" class="text-center text-muted py-3">Sin facturas aún</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

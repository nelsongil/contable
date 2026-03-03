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

// ─── Datos para el Gráfico Mensual ───
$mensualVentas = array_fill(1, 12, 0);
$mensualCompras = array_fill(1, 12, 0);

$mv = $db->prepare("SELECT MONTH(fecha) m, SUM(base_imponible) base FROM facturas_emitidas WHERE YEAR(fecha)=? AND estado!='cancelada' GROUP BY MONTH(fecha)");
$mv->execute([$anio]);
while($r = $mv->fetch()) $mensualVentas[(int)$r['m']] = (float)$r['base'];

$mc = $db->prepare("SELECT MONTH(fecha) m, SUM(base_imponible) base FROM facturas_recibidas WHERE YEAR(fecha)=? GROUP BY MONTH(fecha)");
$mc->execute([$anio]);
while($r = $mc->fetch()) $mensualCompras[(int)$r['m']] = (float)$r['base'];

// KPIs anuales (reutilizando lógica existente pero con mejores nombres)
$ivaAnual = $ventas['iva'] - $compras['iva'];
$rendimiento = $ventas['base'] - $compras['base'];
$irpfPct = (float)getConfig('empresa_irpf', 15) / 100;
$irpfEstimado = max(0, $rendimiento * $irpfPct);

$trims = [];
for ($t = 1; $t <= 4; $t++) $trims[$t] = resumenTrimestral($anio, $t);
?>

<div class="topbar fade-in-up">
  <div>
    <h1 class="d-flex align-items-center"><i class="bi bi-speedometer2 me-2"></i>Dashboard <?= $anio ?></h1>
    <p class="text-muted mb-0" style="font-size: 0.8rem;">Bienvenido de nuevo, <?= e($_SESSION['usuario_nombre'] ?? 'Usuario') ?></p>
  </div>
  <div class="d-flex gap-2">
    <div class="dropdown">
        <button class="btn btn-gold btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
            <i class="bi bi-plus-lg me-1"></i>Nuevo ingreso/gasto
        </button>
        <ul class="dropdown-menu dropdown-menu-end shadow">
            <li><a class="dropdown-item" href="facturas/nueva.php"><i class="bi bi-receipt me-2"></i>Nueva Factura Emitida</a></li>
            <li><a class="dropdown-item" href="compras/nueva.php"><i class="bi bi-bag me-2"></i>Nueva Factura Recibida</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="clientes/nuevo.php"><i class="bi bi-person-plus me-2"></i>Nuevo Cliente</a></li>
            <li><a class="dropdown-item" href="proveedores/nuevo.php"><i class="bi bi-truck me-2"></i>Nuevo Proveedor</a></li>
        </ul>
    </div>
  </div>
</div>

<!-- Quick Actions -->
<div class="row g-3 mb-4 fade-in-up delay-1">
    <div class="col-6 col-md-3">
        <a href="facturas/nueva.php" class="card text-decoration-none h-100 text-center p-3 border-0 shadow-sm" style="transition: transform 0.2s;">
            <div class="mb-2"><i class="bi bi-file-earmark-plus text-primary fs-2"></i></div>
            <div class="fw-bold text-dark small">Nueva Factura</div>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="compras/nueva.php" class="card text-decoration-none h-100 text-center p-3 border-0 shadow-sm" style="transition: transform 0.2s;">
            <div class="mb-2"><i class="bi bi-cart-plus text-success fs-2"></i></div>
            <div class="fw-bold text-dark small">Subir Compra</div>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="libros/resumen.php" class="card text-decoration-none h-100 text-center p-3 border-0 shadow-sm" style="transition: transform 0.2s;">
            <div class="mb-2"><i class="bi bi-file-earmark-bar-graph text-warning fs-2"></i></div>
            <div class="fw-bold text-dark small">Ver Impuestos</div>
        </a>
    </div>
    <div class="col-6 col-md-3">
        <a href="libros/" class="card text-decoration-none h-100 text-center p-3 border-0 shadow-sm" style="transition: transform 0.2s;">
            <div class="mb-2"><i class="bi bi-journal-check text-info fs-2"></i></div>
            <div class="fw-bold text-dark small">Libros Contables</div>
        </a>
    </div>
</div>

<!-- KPIs -->
<div class="row g-3 mb-4">
  <div class="col-sm-6 col-xl-3 fade-in-up delay-1">
    <div class="kpi h-100 border-start border-primary border-4">
      <div class="label">Ingresos <?= $anio ?></div>
      <div class="value"><?= money($ventas['base']) ?></div>
      <div class="sub"><?= $ventas['cnt'] ?> facturas emitidas</div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3 fade-in-up delay-2">
    <div class="kpi h-100 border-start border-success border-4">
      <div class="label">Gastos <?= $anio ?></div>
      <div class="value"><?= money($compras['base']) ?></div>
      <div class="sub"><?= $compras['cnt'] ?> facturas recibidas</div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3 fade-in-up delay-3">
    <div class="kpi h-100 border-start border-<?= $ivaAnual >= 0 ? 'warning' : 'info' ?> border-4">
      <div class="label">IVA acumulado</div>
      <div class="value"><?= money($ivaAnual) ?></div>
      <div class="sub"><?= $ivaAnual >= 0 ? '💰 A ingresar' : '📉 A compensar' ?></div>
    </div>
  </div>
  <div class="col-sm-6 col-xl-3 fade-in-up delay-4">
    <div class="kpi h-100 border-start border-gold border-4">
      <div class="label">Rendimiento neto</div>
      <div class="value <?= $rendimiento < 0 ? 'text-danger' : '' ?>"><?= money($rendimiento) ?></div>
      <div class="sub">IRPF estimado: <?= money($irpfEstimado) ?></div>
    </div>
  </div>
</div>

<div class="row g-3 mb-4">
    <!-- Gráfico Mensual -->
    <div class="col-lg-12">
        <div class="card fade-in-up delay-2 shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><i class="bi bi-graph-up-arrow me-2"></i>Evolución mensual <?= $anio ?></span>
                <span class="badge bg-light text-dark">Ventas vs Compras</span>
            </div>
            <div class="card-body">
                <canvas id="chartMensual" style="max-height: 250px;"></canvas>
            </div>
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

<script>
document.addEventListener('DOMContentLoaded', () => {
    const ctx = document.getElementById('chartMensual').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'],
            datasets: [
                {
                    label: 'Ventas (Base)',
                    data: <?= json_encode(array_values($mensualVentas)) ?>,
                    backgroundColor: '#3E7B64',
                    borderRadius: 5,
                },
                {
                    label: 'Compras (Base)',
                    data: <?= json_encode(array_values($mensualCompras)) ?>,
                    backgroundColor: '#C9A84C',
                    borderRadius: 5,
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: { beginAtZero: true, grid: { display: false } },
                x: { grid: { display: false } }
            },
            plugins: {
                legend: { position: 'bottom' }
            }
        }
    });
});
</script>

<!-- Trigger Auto-Backup (Silent) -->
<script>
fetch('ajustes/backup_process.php?action=auto_check');
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

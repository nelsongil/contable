<?php
$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/header.php';

$anio      = (int)date('Y');
$mesActual = (int)date('n');
$trimActual = trimestre(date('Y-m-d'));

$db = getDB();

// KPIs anuales
$ve = $db->prepare("SELECT COALESCE(SUM(base_imponible),0) base, COALESCE(SUM(cuota_iva),0) iva, COUNT(*) cnt
                    FROM facturas_emitidas WHERE YEAR(fecha)=? AND estado!='cancelada'");
$ve->execute([$anio]); $ventas = $ve->fetch();

$co = $db->prepare("SELECT COALESCE(SUM(base_imponible),0) base, COALESCE(SUM(cuota_iva),0) iva, COUNT(*) cnt
                    FROM facturas_recibidas WHERE YEAR(fecha)=?");
$co->execute([$anio]); $compras = $co->fetch();

// Pendiente de cobro
$pc = $db->prepare("SELECT COUNT(*) cnt, COALESCE(SUM(total),0) total
                    FROM facturas_emitidas WHERE estado='emitida'");
$pc->execute(); $pendiente = $pc->fetch();

// Datos para el gráfico mensual
$mensualVentas  = array_fill(1, 12, 0);
$mensualCompras = array_fill(1, 12, 0);
$mv = $db->prepare("SELECT MONTH(fecha) m, SUM(base_imponible) base FROM facturas_emitidas WHERE YEAR(fecha)=? AND estado!='cancelada' GROUP BY MONTH(fecha)");
$mv->execute([$anio]);
while ($r = $mv->fetch()) $mensualVentas[(int)$r['m']] = (float)$r['base'];
$mc = $db->prepare("SELECT MONTH(fecha) m, SUM(base_imponible) base FROM facturas_recibidas WHERE YEAR(fecha)=? GROUP BY MONTH(fecha)");
$mc->execute([$anio]);
while ($r = $mc->fetch()) $mensualCompras[(int)$r['m']] = (float)$r['base'];

// Derivados
$ivaAnual      = $ventas['iva'] - $compras['iva'];
$rendimiento   = $ventas['base'] - $compras['base'];
$irpfPct       = (float)getConfig('empresa_irpf', 15) / 100;
$irpfEstimado  = max(0, $rendimiento * $irpfPct);
$margenPct     = $ventas['base'] > 0 ? ($rendimiento / $ventas['base'] * 100) : 0;

// Resumen trimestral
$trims = [];
for ($t = 1; $t <= 4; $t++) $trims[$t] = resumenTrimestral($anio, $t);

// Últimas 5 facturas
$ul = $db->query("SELECT id, numero, cliente_nombre, total, estado, fecha
                  FROM facturas_emitidas ORDER BY fecha DESC, id DESC LIMIT 5");
$ultimas = $ul->fetchAll();
?>
<style>
/* ── KPI cards ── */
.kpi-card {
  background: #fff; border-radius: 14px;
  padding: 1.25rem 1.4rem;
  box-shadow: 0 1px 6px rgba(0,0,0,.07);
  display: flex; align-items: center; gap: 1rem;
  transition: box-shadow .2s, transform .15s;
  height: 100%;
}
.kpi-card:hover { box-shadow: 0 4px 18px rgba(0,0,0,.1); transform: translateY(-2px); }
.kpi-ic {
  width: 50px; height: 50px; border-radius: 12px; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center; font-size: 1.3rem;
}
.kpi-ic.g  { background: #dcfce7; color: #16a34a; }
.kpi-ic.r  { background: #fee2e2; color: #dc2626; }
.kpi-ic.b  { background: #dbeafe; color: #2563eb; }
.kpi-ic.or { background: #ffedd5; color: #ea580c; }
.kpi-ic.au { background: #fef3c7; color: #b45309; }
.kpi-lbl { font-size: .7rem; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: .07em; margin-bottom: .1rem; }
.kpi-val { font-size: 1.45rem; font-weight: 800; color: #111827; line-height: 1.15; }
.kpi-val.neg { color: #dc2626; }
.kpi-sub { font-size: .73rem; color: #9ca3af; margin-top: .2rem; }
.kpi-sub.ok  { color: #16a34a; }
.kpi-sub.bad { color: #dc2626; }
.kpi-sub.warn{ color: #d97706; }

/* ── Gráfico ── */
.chart-card { background: #fff; border-radius: 14px; box-shadow: 0 1px 6px rgba(0,0,0,.07); overflow: hidden; }
.chart-header {
  padding: .85rem 1.4rem; border-bottom: 1px solid #f0f0f0;
  display: flex; align-items: center; justify-content: space-between;
  font-weight: 600; font-size: .88rem; color: var(--verde);
}
.chart-header .legend { display: flex; gap: 1.2rem; }
.legend-dot { display: flex; align-items: center; gap: .35rem; font-size: .75rem; color: #6b7280; font-weight: 500; }
.legend-dot::before { content:''; width: 10px; height: 10px; border-radius: 3px; display: inline-block; }
.legend-dot.v::before { background: var(--verde-a); }
.legend-dot.c::before { background: var(--gold); }
.chart-body { padding: 1.2rem 1.4rem 1rem; }

/* ── Tabla trimestral ── */
.trim-table th { background: var(--verde) !important; color: #fff !important; font-size: .72rem !important; }
.trim-table tfoot td { background: #f0f7f4 !important; font-weight: 700; border-top: 2px solid #d1e7dd !important; }
.trim-active { background: #fffbeb !important; }
.trim-active td:first-child { border-left: 3px solid var(--gold) !important; }
.trim-badge {
  display: inline-flex; align-items: center; justify-content: center;
  width: 28px; height: 28px; border-radius: 8px;
  background: var(--verde-a); color: #fff; font-size: .72rem; font-weight: 700;
}
.trim-badge.active { background: var(--gold); color: var(--verde); }
.iva-ok  { color: #dc2626; font-weight: 600; }
.iva-neg { color: #16a34a; font-weight: 600; }

/* ── Últimas facturas ── */
.fact-row { cursor: pointer; transition: background .15s; }
.fact-row:hover { background: #f8fffe !important; }
.fact-num { font-weight: 700; color: var(--verde-a); font-size: .82rem; }
.fact-cli { font-size: .82rem; color: #374151; }
.fact-total { font-weight: 700; font-size: .85rem; }
.empty-state { padding: 2.5rem 1rem; text-align: center; color: #9ca3af; }
.empty-state i { font-size: 2rem; display: block; margin-bottom: .5rem; opacity: .4; }

/* ── Margen ring ── */
.margen-ring { text-align: center; padding: .5rem 0; }
.margen-ring .pct { font-size: 1.6rem; font-weight: 800; }
.margen-ring .lbl { font-size: .7rem; color: #9ca3af; text-transform: uppercase; letter-spacing: .05em; }
.progress-thin { height: 6px; border-radius: 3px; overflow: hidden; background: #e5e7eb; }
.progress-thin .bar { height: 100%; border-radius: 3px; background: var(--verde-a); transition: width .8s ease; }

/* ── Section header ── */
.section-card { background: #fff; border-radius: 14px; box-shadow: 0 1px 6px rgba(0,0,0,.07); overflow: hidden; }
.section-card .s-header {
  padding: .8rem 1.25rem; border-bottom: 1px solid #f0f0f0;
  display: flex; align-items: center; justify-content: space-between;
  font-weight: 600; font-size: .88rem; color: var(--verde);
}
</style>

<!-- Topbar -->
<div class="topbar fade-in-up">
  <div>
    <h1 class="d-flex align-items-center gap-2">
      <i class="bi bi-speedometer2"></i>Dashboard
      <span style="font-size:.85rem;font-weight:500;color:#6b7280;"><?= $anio ?></span>
    </h1>
    <p class="text-muted mb-0" style="font-size:.78rem;">
      Bienvenido, <strong><?= e($_SESSION['usuario_nombre'] ?? 'Usuario') ?></strong>
      &nbsp;·&nbsp; T<?= $trimActual ?> en curso &nbsp;·&nbsp;
      <?= date('d M Y') ?>
    </p>
  </div>
  <div class="d-flex gap-2">
    <div class="dropdown">
      <button class="btn btn-gold btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown">
        <i class="bi bi-plus-lg me-1"></i>Nuevo
      </button>
      <ul class="dropdown-menu dropdown-menu-end shadow-sm">
        <li><a class="dropdown-item" href="facturas/nueva.php"><i class="bi bi-receipt me-2 text-primary"></i>Factura emitida</a></li>
        <li><a class="dropdown-item" href="compras/nueva.php"><i class="bi bi-bag me-2 text-success"></i>Factura recibida</a></li>
        <li><hr class="dropdown-divider"></li>
        <li><a class="dropdown-item" href="clientes/nuevo.php"><i class="bi bi-person-plus me-2 text-info"></i>Cliente</a></li>
        <li><a class="dropdown-item" href="proveedores/nuevo.php"><i class="bi bi-truck me-2 text-warning"></i>Proveedor</a></li>
      </ul>
    </div>
  </div>
</div>

<!-- KPIs -->
<div class="row g-3 mb-4">
  <div class="col-6 col-xl-3 fade-in-up delay-1">
    <div class="kpi-card">
      <div class="kpi-ic g"><i class="bi bi-graph-up-arrow"></i></div>
      <div>
        <div class="kpi-lbl">Ingresos <?= $anio ?></div>
        <div class="kpi-val"><?= money($ventas['base']) ?></div>
        <div class="kpi-sub"><?= $ventas['cnt'] ?> facturas emitidas</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-xl-3 fade-in-up delay-2">
    <div class="kpi-card">
      <div class="kpi-ic r"><i class="bi bi-graph-down-arrow"></i></div>
      <div>
        <div class="kpi-lbl">Gastos <?= $anio ?></div>
        <div class="kpi-val"><?= money($compras['base']) ?></div>
        <div class="kpi-sub"><?= $compras['cnt'] ?> facturas recibidas</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-xl-3 fade-in-up delay-3">
    <div class="kpi-card">
      <div class="kpi-ic <?= $ivaAnual >= 0 ? 'or' : 'b' ?>"><i class="bi bi-bank"></i></div>
      <div>
        <div class="kpi-lbl">IVA neto acumulado</div>
        <div class="kpi-val <?= $ivaAnual < 0 ? '' : '' ?>"><?= money($ivaAnual) ?></div>
        <div class="kpi-sub <?= $ivaAnual >= 0 ? 'bad' : 'ok' ?>">
          <?= $ivaAnual >= 0 ? '<i class="bi bi-arrow-up-circle-fill me-1"></i>A ingresar a Hacienda' : '<i class="bi bi-arrow-down-circle-fill me-1"></i>A compensar' ?>
        </div>
      </div>
    </div>
  </div>
  <div class="col-6 col-xl-3 fade-in-up delay-4">
    <div class="kpi-card">
      <div class="kpi-ic au"><i class="bi bi-wallet2"></i></div>
      <div>
        <div class="kpi-lbl">Rendimiento neto</div>
        <div class="kpi-val <?= $rendimiento < 0 ? 'neg' : '' ?>"><?= money($rendimiento) ?></div>
        <div class="kpi-sub <?= $rendimiento < 0 ? 'bad' : 'warn' ?>">
          IRPF est. <?= money($irpfEstimado) ?>
          <?php if ($ventas['base'] > 0): ?>
          &nbsp;·&nbsp; margen <?= number_format($margenPct, 1) ?>%
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<?php if ($pendiente['cnt'] > 0): ?>
<!-- Alerta pendiente de cobro -->
<div class="alert d-flex align-items-center gap-3 fade-in-up mb-4 border-0 rounded-3"
     style="background:#fffbeb;border-left:4px solid #f59e0b !important;padding:.85rem 1.25rem;">
  <i class="bi bi-clock-history text-warning fs-5"></i>
  <div class="flex-grow-1" style="font-size:.85rem;">
    <strong><?= $pendiente['cnt'] ?> factura<?= $pendiente['cnt'] > 1 ? 's' : '' ?> pendiente<?= $pendiente['cnt'] > 1 ? 's' : '' ?> de cobro</strong>
    &nbsp;— Total: <strong><?= money($pendiente['total']) ?></strong>
  </div>
  <a href="facturas/?estado=emitida" class="btn btn-sm btn-warning text-dark fw-semibold" style="font-size:.78rem;">Ver facturas</a>
</div>
<?php endif; ?>

<!-- Gráfico mensual -->
<div class="chart-card fade-in-up delay-2 mb-4">
  <div class="chart-header">
    <span><i class="bi bi-bar-chart-line me-2"></i>Evolución mensual <?= $anio ?></span>
    <div class="legend">
      <span class="legend-dot v">Ingresos</span>
      <span class="legend-dot c">Gastos</span>
    </div>
  </div>
  <div class="chart-body">
    <canvas id="chartMensual" style="height:280px;max-height:280px;"></canvas>
  </div>
</div>

<!-- Trimestral + Últimas -->
<div class="row g-3">
  <div class="col-lg-7 fade-in-up delay-2">
    <div class="section-card h-100">
      <div class="s-header">
        <span><i class="bi bi-calendar3-range me-2"></i>Resumen trimestral <?= $anio ?></span>
        <a href="libros/resumen.php" class="btn btn-sm btn-outline-secondary" style="font-size:.75rem;">Ver detalle</a>
      </div>
      <div style="overflow-x:auto;">
        <table class="table table-hover trim-table mb-0">
          <thead>
            <tr>
              <th style="width:60px">Trim.</th>
              <th class="text-end">Ingresos</th>
              <th class="text-end">Gastos</th>
              <th class="text-end">IVA neto</th>
              <th class="text-end">Rendimiento</th>
            </tr>
          </thead>
          <tbody>
            <?php for ($t = 1; $t <= 4; $t++): $r = $trims[$t]; $esCurrent = ($t === $trimActual); ?>
            <tr class="<?= $esCurrent ? 'trim-active' : '' ?>">
              <td>
                <span class="trim-badge <?= $esCurrent ? 'active' : '' ?>">T<?= $t ?></span>
              </td>
              <td class="text-end"><?= money($r['ventas_base']) ?></td>
              <td class="text-end"><?= money($r['compras_base']) ?></td>
              <td class="text-end <?= $r['iva_resultado'] < 0 ? 'iva-neg' : 'iva-ok' ?>">
                <?= $r['iva_resultado'] < 0 ? '<i class="bi bi-arrow-down-short"></i>' : '<i class="bi bi-arrow-up-short"></i>' ?>
                <?= money(abs($r['iva_resultado'])) ?>
              </td>
              <td class="text-end fw-semibold <?= $r['rendimiento'] < 0 ? 'text-danger' : '' ?>">
                <?= money($r['rendimiento']) ?>
              </td>
            </tr>
            <?php endfor; ?>
          </tbody>
          <tfoot>
            <tr>
              <td><span class="trim-badge" style="background:#374151;width:auto;padding:0 .6rem;border-radius:6px;">Año</span></td>
              <td class="text-end"><?= money($ventas['base']) ?></td>
              <td class="text-end"><?= money($compras['base']) ?></td>
              <td class="text-end <?= $ivaAnual < 0 ? 'iva-neg' : 'iva-ok' ?>"><?= money($ivaAnual) ?></td>
              <td class="text-end <?= $rendimiento < 0 ? 'text-danger' : '' ?>"><?= money($rendimiento) ?></td>
            </tr>
          </tfoot>
        </table>
      </div>
    </div>
  </div>

  <div class="col-lg-5 fade-in-up delay-3">
    <div class="section-card h-100">
      <div class="s-header">
        <span><i class="bi bi-receipt me-2"></i>Últimas facturas</span>
        <a href="facturas/" class="btn btn-sm btn-outline-secondary" style="font-size:.75rem;">Ver todas</a>
      </div>
      <?php if ($ultimas): ?>
      <table class="table mb-0" style="font-size:.83rem;">
        <tbody>
          <?php foreach ($ultimas as $f):
            $badges = ['borrador'=>['secondary','Borrador'],'emitida'=>['primary','Emitida'],'pagada'=>['success','Pagada'],'cancelada'=>['danger','Cancelada']];
            [$bc, $bl] = $badges[$f['estado']] ?? ['secondary', $f['estado']];
          ?>
          <tr class="fact-row" onclick="location.href='facturas/ver.php?id=<?= $f['id'] ?>'">
            <td style="width:90px">
              <div class="fact-num"><?= e($f['numero']) ?></div>
              <div style="font-size:.7rem;color:#9ca3af;"><?= date('d/m/Y', strtotime($f['fecha'])) ?></div>
            </td>
            <td>
              <div class="fact-cli text-truncate" style="max-width:130px;"><?= e($f['cliente_nombre'] ?: '—') ?></div>
              <span class="badge bg-<?= $bc ?>" style="font-size:.65rem;"><?= $bl ?></span>
            </td>
            <td class="text-end fact-total" style="white-space:nowrap;"><?= money($f['total']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php else: ?>
      <div class="empty-state">
        <i class="bi bi-receipt"></i>
        Sin facturas emitidas aún.<br>
        <a href="facturas/nueva.php" class="btn btn-sm btn-primary mt-3">Crear primera factura</a>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const labels = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
    const ventas  = <?= json_encode(array_values($mensualVentas)) ?>;
    const compras = <?= json_encode(array_values($mensualCompras)) ?>;
    const mesIdx  = <?= $mesActual - 1 ?>;

    // Resaltar mes actual con opacidad diferente
    const colV = ventas.map((_,i) => i === mesIdx ? '#2D5245' : '#3E7B64');
    const colC = compras.map((_,i) => i === mesIdx ? '#b8923e' : '#C9A84C');

    new Chart(document.getElementById('chartMensual').getContext('2d'), {
        type: 'bar',
        data: {
            labels,
            datasets: [
                { label: 'Ingresos', data: ventas,  backgroundColor: colV, borderRadius: 6, borderSkipped: false },
                { label: 'Gastos',   data: compras, backgroundColor: colC, borderRadius: 6, borderSkipped: false }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: { color: '#f3f4f6', drawBorder: false },
                    ticks: {
                        font: { size: 11 }, color: '#9ca3af',
                        callback: v => v >= 1000 ? (v/1000).toFixed(0)+'k €' : v+' €'
                    }
                },
                x: {
                    grid: { display: false },
                    ticks: { font: { size: 11 }, color: '#6b7280' }
                }
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#1A2E2A',
                    padding: 12,
                    cornerRadius: 8,
                    callbacks: {
                        label: ctx => ' ' + ctx.dataset.label + ': ' +
                            new Intl.NumberFormat('es-ES', {style:'currency', currency:'EUR'}).format(ctx.parsed.y)
                    }
                }
            }
        }
    });
});
</script>

<script>fetch('ajustes/backup_process.php?action=auto_check');</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

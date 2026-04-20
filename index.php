<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
requireAdmin();
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
  background: var(--surface); border-radius: 16px;
  padding: 1.25rem 1.4rem 1.15rem;
  border: 1px solid var(--border);
  display: flex; align-items: center; gap: 1rem;
  transition: box-shadow .25s ease, transform .2s ease;
  height: 100%; position: relative; overflow: hidden;
  box-shadow: 0 1px 3px rgba(0,0,0,.04), 0 4px 16px rgba(0,0,0,.05);
}
.kpi-card::before {
  content: ''; position: absolute;
  top: 0; left: 0; right: 0; height: 3px;
  background: var(--kpi-accent, var(--verde-a));
  border-radius: 16px 16px 0 0;
}
.kpi-card:hover {
  box-shadow: 0 4px 8px rgba(0,0,0,.05), 0 16px 40px rgba(0,0,0,.1);
  transform: translateY(-3px);
}
.kpi-ic {
  width: 50px; height: 50px; border-radius: 14px; flex-shrink: 0;
  display: flex; align-items: center; justify-content: center; font-size: 1.3rem;
  box-shadow: 0 2px 8px rgba(0,0,0,.08);
}
.kpi-ic.g  { background: linear-gradient(135deg,rgba(5,150,105,.15),rgba(5,150,105,.08)); color: #059669; }
.kpi-ic.r  { background: linear-gradient(135deg,rgba(220,38,38,.15),rgba(220,38,38,.08)); color: #dc2626; }
.kpi-ic.b  { background: linear-gradient(135deg,rgba(37,99,235,.15),rgba(37,99,235,.08));  color: #2563eb; }
.kpi-ic.or { background: linear-gradient(135deg,rgba(234,88,12,.15),rgba(234,88,12,.08));  color: #ea580c; }
.kpi-ic.au { background: linear-gradient(135deg,rgba(180,83,9,.15),rgba(180,83,9,.08));   color: #b45309; }
.kpi-lbl { font-size: .68rem; font-weight: 700; color: var(--text-3); text-transform: uppercase; letter-spacing: .08em; margin-bottom: .15rem; }
.kpi-val { font-size: 1.48rem; font-weight: 800; color: var(--text); line-height: 1.15; letter-spacing: -.02em; }
.kpi-val.neg { color: var(--clr-danger); }
.kpi-sub { font-size: .72rem; color: var(--text-3); margin-top: .25rem; display: flex; align-items: center; gap: .25rem; }
.kpi-sub.ok  { color: var(--clr-success); }
.kpi-sub.bad { color: var(--clr-danger); }
.kpi-sub.warn{ color: var(--clr-warning); }

/* ── Gráfico ── */
.chart-card {
  background: var(--surface); border-radius: 16px;
  border: 1px solid var(--border); overflow: hidden;
  box-shadow: 0 1px 3px rgba(0,0,0,.04), 0 4px 16px rgba(0,0,0,.05);
}
.chart-header {
  padding: .9rem 1.4rem; border-bottom: 1px solid var(--border);
  display: flex; align-items: center; justify-content: space-between;
  font-weight: 600; font-size: .88rem; color: var(--text);
}
.chart-header .legend { display: flex; gap: 1.2rem; }
.legend-dot { display: flex; align-items: center; gap: .35rem; font-size: .75rem; color: var(--text-3); font-weight: 500; }
.legend-dot::before { content:''; width: 10px; height: 10px; border-radius: 3px; display: inline-block; }
.legend-dot.v::before { background: var(--verde-a); }
.legend-dot.c::before { background: var(--gold); }
.chart-body { padding: 1.2rem 1.4rem 1.1rem; }

/* ── Tabla trimestral ── */
.trim-table tfoot td { background: var(--surface-2) !important; font-weight: 700; border-top: 2px solid var(--border) !important; color: var(--text) !important; }
.trim-active { background: rgba(245,158,11,.05) !important; }
.trim-active td:first-child { border-left: 3px solid var(--gold) !important; }
.trim-badge {
  display: inline-flex; align-items: center; justify-content: center;
  width: 30px; height: 30px; border-radius: 9px;
  background: var(--verde-a); color: #fff; font-size: .71rem; font-weight: 700;
  box-shadow: 0 2px 6px rgba(99,102,241,.3);
}
.trim-badge.active { background: var(--gold); color: var(--verde); box-shadow: 0 2px 6px rgba(245,158,11,.35); }
.iva-ok  { color: var(--clr-danger);  font-weight: 600; }
.iva-neg { color: var(--clr-success); font-weight: 600; }

/* ── Últimas facturas ── */
.fact-row { transition: background .2s ease; }
.fact-row:hover { background: rgba(99,102,241,.04) !important; }
.fact-num { font-weight: 700; color: var(--verde-a); font-size: .82rem; }
.fact-cli { font-size: .82rem; color: var(--text-2); }
.fact-total { font-weight: 700; font-size: .85rem; color: var(--text); }
.empty-state { padding: 3rem 1rem; text-align: center; color: var(--text-3); }
.empty-state i { font-size: 2.5rem; display: block; margin-bottom: .75rem; opacity: .3; }

/* ── Sección cards ── */
.section-card {
  background: var(--surface); border-radius: 16px;
  border: 1px solid var(--border); overflow: hidden;
  box-shadow: 0 1px 3px rgba(0,0,0,.04), 0 4px 16px rgba(0,0,0,.05);
}
.section-card .s-header {
  padding: .85rem 1.25rem; border-bottom: 1px solid var(--border);
  display: flex; align-items: center; justify-content: space-between;
  font-weight: 600; font-size: .88rem; color: var(--text);
}

/* ── Barra margen ── */
.margin-bar {
  height: 4px; border-radius: 4px;
  background: var(--border); overflow: hidden; margin-top: .35rem;
}
.margin-bar-fill {
  height: 100%; border-radius: 4px;
  background: linear-gradient(90deg, var(--verde-a), var(--gold));
  transition: width .6s ease;
}
</style>

<!-- Topbar -->
<div class="topbar fade-in-up">
  <div>
    <h1 class="d-flex align-items-center gap-2 mb-0">
      <i class="bi bi-speedometer2" style="color:var(--verde-a)"></i>Dashboard
      <span class="badge rounded-pill" style="background:rgba(99,102,241,.12);color:var(--verde-a);font-size:.75rem;font-weight:600;padding:.25em .65em;"><?= $anio ?></span>
    </h1>
    <p class="mb-0 mt-1" style="font-size:.78rem;color:var(--text-3);">
      Bienvenido, <strong style="color:var(--text-2)"><?= e($_SESSION['usuario_nombre'] ?? 'Usuario') ?></strong>
      &nbsp;<span style="opacity:.4">·</span>&nbsp; T<?= $trimActual ?> en curso
      &nbsp;<span style="opacity:.4">·</span>&nbsp; <?= date('d M Y') ?>
    </p>
  </div>
  <div class="d-flex gap-2">
    <div class="dropdown">
      <button class="btn btn-gold btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="bi bi-plus-lg me-1"></i>Nuevo
      </button>
      <ul class="dropdown-menu dropdown-menu-end">
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
    <div class="kpi-card" style="--kpi-accent:#059669">
      <div class="kpi-ic g"><i class="bi bi-graph-up-arrow"></i></div>
      <div class="flex-grow-1 min-w-0">
        <div class="kpi-lbl">Ingresos <?= $anio ?><?= helpTip('Base imponible total de facturas emitidas este año (sin IVA). No incluye las canceladas.') ?></div>
        <div class="kpi-val"><?= money($ventas['base']) ?></div>
        <div class="kpi-sub ok"><i class="bi bi-receipt"></i><?= $ventas['cnt'] ?> facturas emitidas</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-xl-3 fade-in-up delay-2">
    <div class="kpi-card" style="--kpi-accent:#dc2626">
      <div class="kpi-ic r"><i class="bi bi-graph-down-arrow"></i></div>
      <div class="flex-grow-1 min-w-0">
        <div class="kpi-lbl">Gastos <?= $anio ?><?= helpTip('Base imponible total de facturas recibidas (compras y gastos) registradas este año.') ?></div>
        <div class="kpi-val"><?= money($compras['base']) ?></div>
        <div class="kpi-sub bad"><i class="bi bi-bag"></i><?= $compras['cnt'] ?> facturas recibidas</div>
      </div>
    </div>
  </div>
  <div class="col-6 col-xl-3 fade-in-up delay-3">
    <?php $ivaAccent = $ivaAnual >= 0 ? '#ea580c' : '#2563eb'; ?>
    <div class="kpi-card" style="--kpi-accent:<?= $ivaAccent ?>">
      <div class="kpi-ic <?= $ivaAnual >= 0 ? 'or' : 'b' ?>"><i class="bi bi-bank"></i></div>
      <div class="flex-grow-1 min-w-0">
        <div class="kpi-lbl">IVA neto acumulado<?= helpTip('IVA cobrado en ventas menos IVA deducible en compras. Positivo = debes ingresar a Hacienda; Negativo = saldo a compensar.') ?></div>
        <div class="kpi-val"><?= money($ivaAnual) ?></div>
        <div class="kpi-sub <?= $ivaAnual >= 0 ? 'bad' : 'ok' ?>">
          <i class="bi bi-<?= $ivaAnual >= 0 ? 'arrow-up-circle' : 'arrow-down-circle' ?>"></i>
          <?= $ivaAnual >= 0 ? 'A ingresar a Hacienda' : 'A compensar' ?>
        </div>
      </div>
    </div>
  </div>
  <div class="col-6 col-xl-3 fade-in-up delay-4">
    <div class="kpi-card" style="--kpi-accent:<?= $rendimiento >= 0 ? '#b45309' : '#dc2626' ?>">
      <div class="kpi-ic au"><i class="bi bi-wallet2"></i></div>
      <div class="flex-grow-1 min-w-0">
        <div class="kpi-lbl">Rendimiento neto<?= helpTip('Ingresos menos gastos. Es la base sobre la que se calcula el IRPF del Modelo 130 (estimación directa simplificada).') ?></div>
        <div class="kpi-val <?= $rendimiento < 0 ? 'neg' : '' ?>"><?= money($rendimiento) ?></div>
        <div class="kpi-sub warn"><i class="bi bi-percent"></i>IRPF est. <?= money($irpfEstimado) ?></div>
        <?php if ($ventas['base'] > 0): ?>
        <div class="margin-bar" title="Margen: <?= number_format($margenPct,1) ?>%">
          <div class="margin-bar-fill" style="width:<?= min(100,max(0,$margenPct)) ?>%"></div>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php if ($pendiente['cnt'] > 0): ?>
<!-- Alerta pendiente de cobro -->
<div class="d-flex align-items-center gap-3 fade-in-up mb-4 rounded-3"
     style="background:rgba(245,158,11,.08);border:1px solid rgba(245,158,11,.25);padding:.88rem 1.25rem;box-shadow:0 2px 8px rgba(245,158,11,.12);">
  <div style="width:36px;height:36px;border-radius:10px;background:rgba(245,158,11,.18);display:flex;align-items:center;justify-content:center;flex-shrink:0;">
    <i class="bi bi-clock-history" style="color:var(--gold);font-size:1rem;"></i>
  </div>
  <div class="flex-grow-1" style="font-size:.85rem;color:var(--text);">
    <strong><?= $pendiente['cnt'] ?> factura<?= $pendiente['cnt'] > 1 ? 's' : '' ?> pendiente<?= $pendiente['cnt'] > 1 ? 's' : '' ?> de cobro</strong>
    <span style="color:var(--text-3);margin:0 .35rem;">·</span>
    Total: <strong style="color:var(--gold)"><?= money($pendiente['total']) ?></strong>
  </div>
  <a href="facturas/" class="btn btn-sm btn-gold" style="font-size:.78rem;white-space:nowrap;">Ver facturas</a>
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
              <td><span class="trim-badge" style="background:var(--text-2);width:auto;padding:0 .6rem;border-radius:6px;">Año</span></td>
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
            <td style="width:95px">
              <div class="fact-num"><?= e($f['numero']) ?></div>
              <div style="font-size:.69rem;color:var(--text-3);"><?= date('d/m/Y', strtotime($f['fecha'])) ?></div>
            </td>
            <td>
              <div class="fact-cli text-truncate" style="max-width:140px;font-weight:500;"><?= e($f['cliente_nombre'] ?: '—') ?></div>
              <span class="badge bg-<?= $bc ?>" style="font-size:.62rem;margin-top:.15rem;"><?= $bl ?></span>
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
(function() {
  const labels  = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
  const ventas  = <?= json_encode(array_values($mensualVentas)) ?>;
  const compras = <?= json_encode(array_values($mensualCompras)) ?>;
  const mesIdx  = <?= $mesActual - 1 ?>;

  function getCSSVar(v) {
    return getComputedStyle(document.documentElement).getPropertyValue(v).trim();
  }

  function buildChart() {
    const isDark  = document.documentElement.getAttribute('data-bs-theme') === 'dark';
    const clrV    = getCSSVar('--verde-a');
    const clrVdk  = getCSSVar('--verde-m');
    const clrG    = getCSSVar('--gold');
    const clrGdk  = '#c77f00';
    const gridClr = isDark ? 'rgba(255,255,255,.06)' : 'rgba(0,0,0,.05)';
    const tickClr = getCSSVar('--text-3');
    const ttBg    = isDark ? '#1e293b' : getCSSVar('--verde');
    const ttBorder= isDark ? getCSSVar('--border') : 'transparent';

    const colV = ventas.map((_,i)  => i === mesIdx ? clrVdk : clrV);
    const colC = compras.map((_,i) => i === mesIdx ? clrGdk : clrG);

    const ctx = document.getElementById('chartMensual').getContext('2d');
    if (window._dashChart) window._dashChart.destroy();

    window._dashChart = new Chart(ctx, {
      type: 'bar',
      data: {
        labels,
        datasets: [
          { label: 'Ingresos', data: ventas,  backgroundColor: colV, borderRadius: 6, borderSkipped: false },
          { label: 'Gastos',   data: compras, backgroundColor: colC, borderRadius: 6, borderSkipped: false }
        ]
      },
      options: {
        responsive: true, maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        scales: {
          y: {
            beginAtZero: true,
            grid: { color: gridClr, drawBorder: false },
            ticks: {
              font: { size: 11 }, color: tickClr,
              callback: v => v >= 1000 ? (v/1000).toFixed(1)+'k €' : v+' €'
            }
          },
          x: {
            grid: { display: false },
            ticks: { font: { size: 11 }, color: tickClr }
          }
        },
        plugins: {
          legend: { display: false },
          tooltip: {
            backgroundColor: ttBg, borderColor: ttBorder, borderWidth: 1,
            padding: 12, cornerRadius: 8,
            titleColor: '#fff', bodyColor: 'rgba(255,255,255,.85)',
            callbacks: {
              label: ctx => ' ' + ctx.dataset.label + ': ' +
                new Intl.NumberFormat('es-ES', {style:'currency', currency:'EUR'}).format(ctx.parsed.y)
            }
          }
        }
      }
    });
  }

  document.addEventListener('DOMContentLoaded', buildChart);

  // Reconstruir chart al cambiar el tema
  const observer = new MutationObserver(function(mutations) {
    mutations.forEach(m => { if (m.attributeName === 'data-bs-theme') buildChart(); });
  });
  observer.observe(document.documentElement, { attributes: true });
})();
</script>

<script>fetch('ajustes/backup_process.php?action=auto_check');</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

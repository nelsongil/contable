<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
$pageTitle = 'Libro de ventas';
require_once __DIR__ . '/../includes/header.php';

$anio  = (int)get('anio', date('Y'));
$trim  = (int)get('trim', 0);
$facturas = getFacturasEmitidas($anio, $trim);
?>

<div class="topbar">
  <h1><i class="bi bi-journal-text me-2"></i>Libro de ventas <?= $anio ?></h1>
  <div class="d-flex gap-2">
    <select class="form-select form-select-sm" style="width:90px" onchange="location.href='?anio='+this.value+'&trim=<?= $trim ?>'">
      <?php foreach ([date('Y'),date('Y')-1,date('Y')-2] as $y): ?>
      <option value="<?= $y ?>" <?= $y==$anio?'selected':'' ?>><?= $y ?></option>
      <?php endforeach; ?>
    </select>
    <select class="form-select form-select-sm" style="width:90px" onchange="location.href='?anio=<?= $anio ?>&trim='+this.value">
      <option value="0" <?= !$trim?'selected':'' ?>>Todos</option>
      <?php for ($t=1;$t<=4;$t++): ?>
      <option value="<?= $t ?>" <?= $trim==$t?'selected':'' ?>>T<?= $t ?></option>
      <?php endfor; ?>
    </select>
  </div>
</div>

<?php
$trimActual = 0;
$subtotBase = $subtotIva = $subtotTotal = 0;
$totBase = $totIva = $totTotal = 0;

foreach ($facturas as $f):
    if ($f['trimestre'] !== $trimActual):
        if ($trimActual > 0):
?>
<tr class="fw-semibold" style="background:#e8f4f0">
  <td colspan="5" class="text-end">Subtotal T<?= $trimActual ?></td>
  <td class="text-end"><?= money($subtotBase) ?></td>
  <td class="text-end"><?= money($subtotIva) ?></td>
  <td class="text-end"><?= money($subtotTotal) ?></td>
  <td></td>
</tr>
<?php
        endif;
        $trimActual = $f['trimestre'];
        $subtotBase = $subtotIva = $subtotTotal = 0;
?>
</tbody></table></div>
<div class="card mb-3">
  <div class="card-header"><span class="badge-trim me-2">T<?= $trimActual ?></span>Trimestre <?= $trimActual ?></div>
  <div class="card-body p-0">
    <table class="table table-hover mb-0">
      <thead><tr><th>Nº Factura</th><th>Fecha</th><th>Cliente</th><th>NIF</th><th>IVA %</th>
                 <th class="text-end">Base</th><th class="text-end">IVA</th><th class="text-end">Total</th><th>Estado</th></tr></thead>
      <tbody>
<?php
    endif;
    if ($f['estado'] !== 'cancelada') {
        $subtotBase += $f['base_imponible']; $subtotIva += $f['cuota_iva']; $subtotTotal += $f['total'];
        $totBase += $f['base_imponible']; $totIva += $f['cuota_iva']; $totTotal += $f['total'];
    }
    $bs=['borrador'=>'secondary','emitida'=>'primary','pagada'=>'success','cancelada'=>'danger'];
?>
<tr class="<?= $f['estado']==='cancelada' ? 'text-muted text-decoration-line-through' : '' ?>">
  <td><a href="/facturas/ver.php?id=<?= $f['id'] ?>" class="fw-semibold text-decoration-none"><?= e($f['numero']) ?></a></td>
  <td><?= date('d/m/Y', strtotime($f['fecha'])) ?></td>
  <td><?= e($f['cliente_nombre']) ?></td>
  <td><?= e($f['cliente_nif']) ?></td>
  <td class="text-center"><?= $f['porcentaje_iva'] ?>%</td>
  <td class="text-end"><?= money($f['base_imponible']) ?></td>
  <td class="text-end"><?= money($f['cuota_iva']) ?></td>
  <td class="text-end fw-semibold"><?= money($f['total']) ?></td>
  <td><span class="badge bg-<?= $bs[$f['estado']] ?>"><?= e($f['estado']) ?></span></td>
</tr>
<?php endforeach; ?>
<?php if ($trimActual > 0): ?>
<tr class="fw-semibold" style="background:#e8f4f0">
  <td colspan="5" class="text-end">Subtotal T<?= $trimActual ?></td>
  <td class="text-end"><?= money($subtotBase) ?></td>
  <td class="text-end"><?= money($subtotIva) ?></td>
  <td class="text-end"><?= money($subtotTotal) ?></td>
  <td></td>
</tr>
</tbody></table></div></div>
<?php endif; ?>

<?php if ($totTotal > 0): ?>
<div class="card">
  <div class="card-body">
    <div class="row justify-content-end"><div class="col-md-4">
      <table class="table table-sm text-end mb-0 fw-bold">
        <tr><td>Base imponible total</td><td><?= money($totBase) ?></td></tr>
        <tr><td>IVA repercutido total</td><td><?= money($totIva) ?></td></tr>
        <tr class="fs-5"><td>TOTAL ANUAL</td><td><?= money($totTotal) ?></td></tr>
      </table>
    </div></div>
  </div>
</div>
<?php endif; ?>

<?php if (!$facturas): ?>
<div class="text-center text-muted py-5">Sin facturas para este periodo.</div>
<?php endif; ?>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

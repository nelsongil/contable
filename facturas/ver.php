<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/functions.php';

$id      = (int)get('id');
$factura = $id ? getFacturaEmitida($id) : null;
if (!$factura) { header('Location: /facturas/'); exit; }

$lineas  = getLineasFactura($id);
$pdf     = get('pdf') === '1';

// ── Marcar como pagada ────────────────────────────────
if (get('pagada') === '1') {
    getDB()->prepare("UPDATE facturas_emitidas SET estado='pagada' WHERE id=?")->execute([$id]);
    flash('Factura marcada como pagada.');
    redirect('/facturas/ver.php?id=' . $id);
}

if ($pdf) {
    // Modo impresión / PDF → sin sidebar
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Factura <?= e($factura['numero']) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: 'Inter', sans-serif; font-size: 10pt; color: #1a1a1a; background: #fff; }
@page { margin: 15mm 18mm; size: A4; }
@media print { .no-print { display: none !important; } }

.invoice { max-width: 780px; margin: 0 auto; padding: 20px; }

/* Header */
.inv-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 30px; border-bottom: 3px solid #1A2E2A; padding-bottom: 20px; }
.inv-logo-area .company { font-size: 22pt; font-weight: 700; color: #1A2E2A; }
.inv-logo-area .subtitle { color: #3E7B64; font-size: 9pt; }
.inv-logo-area .details  { font-size: 8pt; color: #555; margin-top: 6px; line-height: 1.6; }
.inv-title-area { text-align: right; }
.inv-title-area .factura-label { font-size: 26pt; font-weight: 700; color: #C9A84C; letter-spacing: -1px; }
.inv-title-area .numero { font-size: 13pt; font-weight: 600; color: #1A2E2A; }
.inv-title-area .fecha  { font-size: 9pt; color: #666; }

/* Parties */
.parties { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 28px; }
.party { background: #f8fbf9; border-left: 4px solid #1A2E2A; padding: 14px 16px; border-radius: 0 8px 8px 0; }
.party.cliente { border-left-color: #C9A84C; }
.party-label { font-size: 7.5pt; text-transform: uppercase; letter-spacing: .08em; color: #888; margin-bottom: 5px; }
.party-name  { font-size: 12pt; font-weight: 700; color: #1A2E2A; }
.party-detail { font-size: 8.5pt; color: #555; margin-top: 2px; line-height: 1.5; }

/* Lines table */
.lines-table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
.lines-table thead tr { background: #1A2E2A; color: #fff; }
.lines-table thead th { padding: 8px 10px; font-size: 8pt; font-weight: 600; text-transform: uppercase; }
.lines-table tbody tr:nth-child(even) { background: #f3f8f5; }
.lines-table tbody td { padding: 7px 10px; font-size: 9pt; border-bottom: 1px solid #e5ede9; }
.lines-table tfoot td { padding: 6px 10px; font-size: 9pt; border-top: 2px solid #1A2E2A; }

/* Totals */
.totals-wrap { display: flex; justify-content: flex-end; margin-bottom: 28px; }
.totals { width: 280px; }
.totals-row { display: flex; justify-content: space-between; padding: 5px 0; font-size: 9.5pt; border-bottom: 1px solid #e5ede9; }
.totals-row.grand { font-size: 13pt; font-weight: 700; color: #1A2E2A; border-top: 2px solid #1A2E2A; border-bottom: none; padding-top: 8px; }
.totals-row.irpf  { color: #c0392b; }

/* Payment / Notes */
.bottom-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
.bottom-box { background: #f8fbf9; border-radius: 8px; padding: 14px 16px; }
.bottom-box h4 { font-size: 8.5pt; text-transform: uppercase; letter-spacing: .07em; color: #888; margin-bottom: 8px; }
.bottom-box p  { font-size: 9pt; line-height: 1.7; color: #444; }

/* Print button */
.no-print { position: fixed; top: 20px; right: 20px; display: flex; gap: 8px; }
.btn-print { background: #1A2E2A; color: #fff; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-family: Inter; font-size: 13px; font-weight: 600; }
.btn-print:hover { background: #2D5245; }
.btn-back  { background: #fff; color: #1A2E2A; border: 2px solid #1A2E2A; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-family: Inter; font-size: 13px; font-weight: 600; text-decoration: none; }
</style>
</head>
<body>

<div class="no-print">
  <a href="ver.php?id=<?= $id ?>" class="btn-back">← Volver</a>
  <button onclick="window.print()" class="btn-print">🖨 Imprimir / Guardar PDF</button>
</div>

<div class="invoice">
  <!-- Cabecera -->
  <div class="inv-header">
    <div class="inv-logo-area">
      <div class="company"><?= EMPRESA_SOCIEDAD ?></div>
      <div class="subtitle"><?= EMPRESA_NOMBRE ?></div>
      <div class="details">
        <?= EMPRESA_DIR1 ?><br>
        <?= EMPRESA_DIR2 ?><br>
        CIF: <?= EMPRESA_CIF ?> · <?= EMPRESA_TEL ?><br>
        <?= EMPRESA_EMAIL ?> · <?= EMPRESA_WEB ?>
      </div>
    </div>
    <div class="inv-title-area">
      <div class="factura-label">FACTURA</div>
      <div class="numero"><?= e($factura['numero']) ?></div>
      <div class="fecha">Fecha: <?= date('d/m/Y', strtotime($factura['fecha'])) ?></div>
      <?php if ($factura['fecha_vencimiento']): ?>
      <div class="fecha">Vencimiento: <?= date('d/m/Y', strtotime($factura['fecha_vencimiento'])) ?></div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Partes -->
  <div class="parties">
    <div class="party">
      <div class="party-label">Emisor</div>
      <div class="party-name"><?= EMPRESA_NOMBRE ?></div>
      <div class="party-detail"><?= EMPRESA_DIR1 ?><br><?= EMPRESA_DIR2 ?><br>CIF: <?= EMPRESA_CIF ?></div>
    </div>
    <div class="party cliente">
      <div class="party-label">Cliente</div>
      <div class="party-name"><?= e($factura['cliente_nombre']) ?></div>
      <?php if ($factura['cliente_nif']): ?>
      <div class="party-detail">NIF/CIF: <?= e($factura['cliente_nif']) ?></div>
      <?php endif; ?>
      <?php
        $cli = $factura['cliente_id'] ? getCliente($factura['cliente_id']) : null;
        if ($cli && $cli['direccion']):
      ?>
      <div class="party-detail"><?= e($cli['direccion']) ?><br><?= e($cli['cp'] . ' ' . $cli['ciudad']) ?></div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Líneas -->
  <table class="lines-table">
    <thead><tr><th>Cant.</th><th>Descripción</th><th style="text-align:right">Precio unit.</th><th style="text-align:right">Total</th></tr></thead>
    <tbody>
      <?php foreach ($lineas as $l): ?>
      <tr>
        <td><?= number_format($l['cantidad'], $l['cantidad'] == floor($l['cantidad']) ? 0 : 2, ',', '.') ?></td>
        <td><?= e($l['descripcion']) ?></td>
        <td style="text-align:right"><?= money($l['precio']) ?></td>
        <td style="text-align:right"><?= money($l['total']) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <!-- Totales -->
  <div class="totals-wrap">
    <div class="totals">
      <div class="totals-row"><span>Base imponible</span><span><?= money($factura['base_imponible']) ?></span></div>
      <div class="totals-row"><span>IVA (<?= $factura['porcentaje_iva'] ?>%)</span><span><?= money($factura['cuota_iva']) ?></span></div>
      <?php if ($factura['porcentaje_irpf'] > 0): ?>
      <div class="totals-row irpf"><span>Ret. IRPF (<?= $factura['porcentaje_irpf'] ?>%)</span><span>-<?= money($factura['cuota_irpf']) ?></span></div>
      <?php endif; ?>
      <div class="totals-row grand"><span>TOTAL</span><span><?= money($factura['total']) ?></span></div>
      <?php if ($factura['porcentaje_irpf'] > 0): ?>
      <div class="totals-row" style="font-size:8.5pt;color:#888"><span>Líquido a cobrar</span><span><?= money($factura['liquido']) ?></span></div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Pago y notas -->
  <div class="bottom-grid">
    <div class="bottom-box">
      <h4>Datos de pago</h4>
      <p>
        Beneficiario: <?= EMPRESA_NOMBRE ?><br>
        Banco: <?= EMPRESA_BANCO ?><br>
        IBAN: <?= EMPRESA_IBAN ?><br>
        Referencia: <?= e($factura['numero']) ?>
      </p>
    </div>
    <div class="bottom-box">
      <h4>Información adicional</h4>
      <p>
        <?= EMPRESA_NOMBRE ?><br>
        <?= EMPRESA_TEL ?><br>
        CIF: <?= EMPRESA_CIF ?><br>
        <?= EMPRESA_WEB ?>
        <?php if ($factura['notas']): ?><br><br><?= nl2br(e($factura['notas'])) ?><?php endif; ?>
      </p>
    </div>
  </div>
</div>
</body></html>
<?php
    exit;
}

// ── Vista normal ──────────────────────────────────────
$pageTitle = 'Factura ' . $factura['numero'];
require_once __DIR__ . '/../includes/header.php';
?>

<div class="topbar">
  <h1><i class="bi bi-receipt me-2"></i><?= e($factura['numero']) ?> — <?= e($factura['cliente_nombre']) ?></h1>
  <div class="d-flex gap-2 flex-wrap">
    <?php if ($factura['estado'] !== 'pagada'): ?>
    <a href="?id=<?= $id ?>&pagada=1" class="btn btn-sm btn-success" onclick="return confirm('¿Marcar como pagada?')">
      <i class="bi bi-check-circle me-1"></i>Marcar pagada
    </a>
    <?php endif; ?>
    <a href="nueva.php?id=<?= $id ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil me-1"></i>Editar</a>
    <a href="?id=<?= $id ?>&pdf=1" target="_blank" class="btn btn-gold btn-sm"><i class="bi bi-printer me-1"></i>PDF / Imprimir</a>
    <a href="/facturas/" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Volver</a>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-8">
    <div class="card">
      <div class="card-header"><i class="bi bi-list-ul me-2"></i>Líneas de factura</div>
      <div class="card-body p-0">
        <table class="table mb-0">
          <thead><tr><th>Cant.</th><th>Descripción</th><th class="text-end">Precio</th><th class="text-end">Total</th></tr></thead>
          <tbody>
            <?php foreach ($lineas as $l): ?>
            <tr>
              <td><?= e($l['cantidad']) ?></td>
              <td><?= e($l['descripcion']) ?></td>
              <td class="text-end"><?= money($l['precio']) ?></td>
              <td class="text-end fw-semibold"><?= money($l['total']) ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <div class="card-footer bg-white">
        <div class="row justify-content-end">
          <div class="col-md-4">
            <table class="table table-sm text-end mb-0">
              <tr><td class="text-muted">Base imponible</td><td class="fw-semibold"><?= money($factura['base_imponible']) ?></td></tr>
              <tr><td class="text-muted">IVA (<?= $factura['porcentaje_iva'] ?>%)</td><td><?= money($factura['cuota_iva']) ?></td></tr>
              <?php if ($factura['porcentaje_irpf'] > 0): ?>
              <tr class="text-danger"><td>Ret. IRPF (<?= $factura['porcentaje_irpf'] ?>%)</td><td>-<?= money($factura['cuota_irpf']) ?></td></tr>
              <?php endif; ?>
              <tr class="fw-bold fs-5"><td>TOTAL</td><td><?= money($factura['total']) ?></td></tr>
              <?php if ($factura['porcentaje_irpf'] > 0): ?>
              <tr><td class="text-muted small">Líquido a cobrar</td><td class="small"><?= money($factura['liquido']) ?></td></tr>
              <?php endif; ?>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
  <div class="col-lg-4">
    <div class="card">
      <div class="card-header"><i class="bi bi-info-circle me-2"></i>Detalles</div>
      <div class="card-body">
        <dl class="row mb-0" style="font-size:.87rem">
          <dt class="col-5 text-muted">Número</dt>      <dd class="col-7 fw-semibold"><?= e($factura['numero']) ?></dd>
          <dt class="col-5 text-muted">Fecha</dt>       <dd class="col-7"><?= date('d/m/Y', strtotime($factura['fecha'])) ?></dd>
          <?php if ($factura['fecha_vencimiento']): ?>
          <dt class="col-5 text-muted">Vencimiento</dt> <dd class="col-7"><?= date('d/m/Y', strtotime($factura['fecha_vencimiento'])) ?></dd>
          <?php endif; ?>
          <dt class="col-5 text-muted">Trimestre</dt>   <dd class="col-7"><span class="badge-trim">T<?= $factura['trimestre'] ?></span></dd>
          <dt class="col-5 text-muted">Estado</dt>      <dd class="col-7">
            <?php $bs=['borrador'=>'secondary','emitida'=>'primary','pagada'=>'success','cancelada'=>'danger']; ?>
            <span class="badge bg-<?= $bs[$factura['estado']] ?>"><?= e($factura['estado']) ?></span>
          </dd>
          <dt class="col-5 text-muted">Cliente</dt>     <dd class="col-7"><?= e($factura['cliente_nombre']) ?></dd>
          <?php if ($factura['cliente_nif']): ?>
          <dt class="col-5 text-muted">NIF/CIF</dt>     <dd class="col-7"><?= e($factura['cliente_nif']) ?></dd>
          <?php endif; ?>
          <?php if ($factura['notas']): ?>
          <dt class="col-5 text-muted">Notas</dt>       <dd class="col-7"><?= nl2br(e($factura['notas'])) ?></dd>
          <?php endif; ?>
        </dl>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

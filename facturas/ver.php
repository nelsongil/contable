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
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($factura['numero']) ?> - <?= e($factura['cliente_nombre']) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&family=Roboto:wght@300;400;700&family=Open+Sans:wght@300;400;600;700&display=swap" rel="stylesheet">
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body { 
    font-family: <?= getConfig('invoice_font', "'Inter', sans-serif") ?>; 
    font-size: 10pt; 
    color: <?= getConfig('invoice_color_text', '#1a1a1a') ?>; 
    background: #fff; 
}
@page { margin: 0; size: A4; }
@media print {
  .no-print { display: none !important; }
  body { padding: 15mm 18mm; }
  .invoice { margin: 0 auto; }
}

.invoice { max-width: 780px; margin: 60px auto; padding: 20px; position: relative; }

/* Header */
.inv-header { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 25px; padding-bottom: 20px; border-bottom: 3px solid <?= getConfig('invoice_color_accent', '#C9A84C') ?>; }
.inv-logo-area .logo-img { max-height: 95px; max-width: 250px; display: block; }
.inv-title-area { text-align: right; }
.inv-title-area .factura-label { font-size: 28pt; font-weight: 700; color: <?= getConfig('invoice_color_primary', '#1A2E2A') ?>; line-height: 1; margin-bottom: 5px; }
.inv-title-area .numero { font-size: 14pt; font-weight: 600; color: #444; }
.inv-title-area .fecha  { font-size: 9.5pt; color: #666; margin-top: 4px; }

/* Section 2: Client */
.client-block { margin-bottom: 40px; padding: 10px 0; }
.client-label { font-size: 8pt; font-weight: 700; color: #999; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 8px; }
.client-name  { font-size: 16pt; font-weight: 700; color: <?= getConfig('invoice_color_primary', '#1A2E2A') ?>; margin-bottom: 4px; }
.client-details { font-size: 10pt; color: #444; line-height: 1.5; }

/* Lines table */
.lines-table { width: 100%; border-collapse: collapse; margin-bottom: 25px; }
.lines-table thead tr { background: <?= getConfig('invoice_color_primary', '#1A2E2A') ?>; color: #fff; }
.lines-table thead th { padding: 10px; font-size: 8.5pt; font-weight: 600; text-transform: uppercase; text-align: left; }
.lines-table tbody tr:nth-child(even) { background: #f9f9f9; }
.lines-table tbody td { padding: 12px 10px; font-size: 10pt; border-bottom: 1px solid #eee; }
.lines-table .text-right { text-align: right; }

/* Totals */
.totals-wrap { display: flex; justify-content: flex-end; margin-bottom: 50px; }
.totals { width: 300px; }
.totals-row { display: flex; justify-content: space-between; padding: 6px 0; font-size: 10pt; border-bottom: 1px solid #efefef; }
.totals-row.grand { font-size: 14pt; font-weight: 700; color: <?= getConfig('invoice_color_primary', '#1A2E2A') ?>; border-top: 2px solid <?= getConfig('invoice_color_primary', '#1A2E2A') ?>; border-bottom: none; padding-top: 10px; margin-top: 5px; }
.totals-row.irpf { color: #d32f2f; }

/* Footer table */
.footer-table { width: 100%; border-collapse: collapse; margin-top: 40px; border-top: 2px solid #eee; padding-top: 0; }
.footer-table td { width: 50%; vertical-align: top; padding-top: 24px; }
.footer-table td:last-child { text-align: right; }
.footer-table h4 { font-size: 8.5pt; text-transform: uppercase; letter-spacing: 1px; color: #999; margin-bottom: 12px; }
.footer-table p { font-size: 9pt; line-height: 1.6; color: #444; }


/* Invoice page footer */
.invoice-footer {
  margin-top: 28px; padding-top: 12px;
  border-top: 1px solid #e8e8e8;
  display: flex; align-items: center; justify-content: center; gap: 32px;
  font-size: 8pt; color: #888;
}
.invoice-footer a { color: #888; text-decoration: none; }
.invoice-footer .sep { color: #ddd; }

/* Utility */
.mb-2 { margin-bottom: 0.5rem; }
.mt-2 { margin-top: 0.5rem; }

/* Print-only Volver */
.no-print { position: fixed; top: 20px; right: 20px; display: flex; gap: 8px; z-index: 1000; }

/* ── Responsive para previsualización en móvil ── */
@media (max-width: 600px) {
  .invoice { margin: 10px; padding: 10px; }
  .inv-header { flex-direction: column; align-items: flex-start; gap: 1rem; }
  .inv-title-area { text-align: left; }
  .footer-table td { display: block; width: 100%; text-align: left !important; }
  .totals-wrap { justify-content: stretch; }
  .totals { width: 100%; }
  .no-print { position: relative; top: auto; right: auto; padding: 10px; flex-wrap: wrap; gap: 6px; }
  .lines-table { font-size: 9pt; }
}
.btn-print { background: #1A2E2A; color: #fff; border: none; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-family: Inter; font-size: 13px; font-weight: 600; }
.btn-back  { background: #fff; color: #1A2E2A; border: 2px solid #1A2E2A; padding: 10px 20px; border-radius: 8px; cursor: pointer; font-family: Inter; font-size: 13px; font-weight: 600; text-decoration: none; }
</style>
</head>
<body>

<div class="no-print">
  <a href="ver.php?id=<?= $id ?>" class="btn-back">← Volver</a>
  <button onclick="window.print()" class="btn-print">🖨 Imprimir / Guardar PDF</button>
</div>

<div class="invoice">
  <!-- SECCIÓN 1: CABECERA -->
  <div class="inv-header">
    <div class="inv-logo-area" style="display:flex;align-items:center;gap:16px;">
      <?php if ($logo = getConfig('invoice_logo', '/assets/logo.png')): ?>
      <img src="<?= $logo ?>?t=<?= time() ?>" class="logo-img" alt="Logo">
      <?php endif; ?>
      <div>
        <div style="font-size:13pt;font-weight:700;color:<?= getConfig('invoice_color_primary', '#1A2E2A') ?>;line-height:1.2;"><?= e(getConfig('empresa_sociedad', defined('EMPRESA_SOCIEDAD') ? EMPRESA_SOCIEDAD : '')) ?></div>
        <div style="font-size:8.5pt;color:#666;margin-top:3px;"><?= e(getConfig('empresa_nombre', defined('EMPRESA_NOMBRE') ? EMPRESA_NOMBRE : '')) ?></div>
        <div style="font-size:8pt;color:#888;">CIF: <?= e(getConfig('empresa_cif', defined('EMPRESA_CIF') ? EMPRESA_CIF : '')) ?></div>
      </div>
    </div>
    <div class="inv-title-area">
      <div class="factura-label">FACTURA</div>
      <div class="numero">Nº <?= e($factura['numero']) ?></div>
      <div class="fecha">Fecha: <?= date('d/m/Y', strtotime($factura['fecha'])) ?></div>
      <?php if ($factura['fecha_vencimiento']): ?>
      <div class="fecha">Vencimiento: <?= date('d/m/Y', strtotime($factura['fecha_vencimiento'])) ?></div>
      <?php endif; ?>
    </div>
  </div>

  <!-- SECCIÓN 2: DATOS CLIENTE -->
  <?php
    $cli = $factura['cliente_id'] ? getCliente($factura['cliente_id']) : null;
    $clienteNombre = $factura['cliente_nombre'] ?: ($cli ? $cli['nombre'] : '');
    $clienteNif    = $factura['cliente_nif']    ?: ($cli ? $cli['nif']    : '');
  ?>
  <div class="client-block">
    <div class="client-label">Facturado a:</div>
    <div class="client-name"><?= e($clienteNombre) ?></div>
    <div class="client-details">
      <?php if ($clienteNif): ?>NIF/CIF: <?= e($clienteNif) ?><br><?php endif; ?>
      <?php if ($cli && $cli['direccion']): ?>
        <?= e($cli['direccion']) ?><br>
        <?= e(trim($cli['cp'] . ' ' . $cli['ciudad'])) ?>
      <?php endif; ?>
    </div>
  </div>

  <!-- SECCIÓN 3: TABLA DE LÍNEAS -->
  <table class="lines-table">
    <thead>
        <tr>
            <th style="width: 80px">Cant.</th>
            <th>Descripción</th>
            <th class="text-right" style="width: 120px">Precio unit.</th>
            <th class="text-right" style="width: 120px">Total</th>
        </tr>
    </thead>
    <tbody>
      <?php foreach ($lineas as $l): ?>
      <tr>
        <td><?= number_format($l['cantidad'], $l['cantidad'] == floor($l['cantidad']) ? 0 : 2, ',', '.') ?></td>
        <td><?= e($l['descripcion']) ?></td>
        <td class="text-right"><?= money($l['precio']) ?></td>
        <td class="text-right"><?= money($l['total']) ?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <!-- SECCIÓN 4: TOTALES -->
  <div class="totals-wrap">
    <div class="totals">
      <div class="totals-row"><span>Base imponible</span><span><?= money($factura['base_imponible']) ?></span></div>
      <div class="totals-row"><span>IVA (<?= $factura['porcentaje_iva'] ?>%)</span><span><?= money($factura['cuota_iva']) ?></span></div>
      <?php if ($factura['porcentaje_irpf'] > 0): ?>
      <div class="totals-row irpf"><span>Retención IRPF (<?= $factura['porcentaje_irpf'] ?>%)</span><span>-<?= money($factura['cuota_irpf']) ?></span></div>
      <?php endif; ?>
      <div class="totals-row grand"><span>TOTAL</span><span><?= money($factura['total']) ?></span></div>
      <?php if ($factura['porcentaje_irpf'] > 0): ?>
      <div class="totals-row" style="font-size:9pt;color:#888;border-top:1px dashed #eee;margin-top:5px;padding-top:8px">
        <span>Líquido a percibir</span><span><?= money($factura['liquido']) ?></span>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- SECCIÓN 5: PIE (2 COLUMNAS con tabla) -->
  <table class="footer-table">
    <tr>
      <td>
        <h4>Datos de pago</h4>
        <p>
          <strong>Beneficiario:</strong> <?= e(getConfig('empresa_nombre', EMPRESA_NOMBRE)) ?><br>
          <strong>Banco:</strong> <?= e(getConfig('empresa_banco', EMPRESA_BANCO)) ?><br>
          <strong>IBAN:</strong> <?= e(getConfig('empresa_iban', EMPRESA_IBAN)) ?><br>
          <strong>Referencia:</strong> Factura <?= e($factura['numero']) ?>
        </p>
      </td>
      <td>
        <h4>Datos del emisor</h4>
        <p style="font-size:8.5pt">
          <strong><?= e(getConfig('empresa_sociedad', defined('EMPRESA_SOCIEDAD') ? EMPRESA_SOCIEDAD : '')) ?></strong><br>
          <?= e(getConfig('empresa_nombre', defined('EMPRESA_NOMBRE') ? EMPRESA_NOMBRE : '')) ?><br>
          CIF: <?= e(getConfig('empresa_cif', defined('EMPRESA_CIF') ? EMPRESA_CIF : '')) ?><br>
          <?= e(getConfig('empresa_dir1', defined('EMPRESA_DIR1') ? EMPRESA_DIR1 : '')) ?><br>
          <?= e(trim(getConfig('empresa_dir2', defined('EMPRESA_DIR2') ? EMPRESA_DIR2 : ''))) ?>
        </p>
      </td>
    </tr>
  </table>

  <!-- PIE DE PÁGINA: email y web -->
  <?php
    $emailEmisor = getConfig('empresa_email', defined('EMPRESA_EMAIL') ? EMPRESA_EMAIL : '');
    $webEmisor   = getConfig('empresa_web',  defined('EMPRESA_WEB')   ? EMPRESA_WEB   : '');
  ?>
  <?php if ($emailEmisor || $webEmisor): ?>
  <div class="invoice-footer">
    <?php if ($emailEmisor): ?>
      <span><i>✉</i> <?= e($emailEmisor) ?></span>
    <?php endif; ?>
    <?php if ($emailEmisor && $webEmisor): ?><span class="sep">|</span><?php endif; ?>
    <?php if ($webEmisor): ?>
      <span>🌐 <?= e($webEmisor) ?></span>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <!-- SECCIÓN 6: NOTA LEGAL LOPD — tabla 1 celda, sin nl2br para que fluya al ancho completo -->
  <?php if ($legal = getConfig('invoice_legal', '')): ?>
  <table style="width:100%;border-collapse:collapse;margin-top:20px;"><tr><td style="border-top:1px solid #eee;padding-top:12px;font-size:7.5pt;color:#999;line-height:1.5;text-align:justify;"><?= e(str_replace(["\r\n", "\r", "\n"], ' ', $legal)) ?></td></tr></table>
  <?php endif; ?>
</div><!-- /invoice -->
</body>
</html>
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
    <a href="?id=<?= $id ?>&pagada=1" class="btn btn-sm btn-success" data-confirm="¿Marcar esta factura como pagada?">
      <i class="bi bi-check-circle me-1"></i>Marcar pagada
    </a>
    <?php endif; ?>
    <a href="nueva.php?id=<?= $id ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil me-1"></i>Editar</a>
    
    <!-- Botón PDF que abre el modal -->
    <button type="button" class="btn btn-gold btn-sm" data-bs-toggle="modal" data-bs-target="#pdfModal">
      <i class="bi bi-printer me-1"></i>Ver / Imprimir PDF
    </button>

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
      <div class="card-footer">
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

<!-- ═══ MODAL PDF ═══ -->
<div class="modal fade" id="pdfModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-centered modal-fullscreen-md-down" style="height: 90vh;">
    <div class="modal-content h-100">
      <div class="modal-header bg-verde text-white py-2">
        <h5 class="modal-title" style="font-size: 1.1rem"><i class="bi bi-file-earmark-pdf me-2"></i>Vista previa de Factura</h5>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-0 bg-secondary bg-opacity-10 overflow-hidden d-flex flex-column">
        <div class="p-2 border-bottom bg-white d-flex justify-content-between align-items-center no-print">
            <span class="small text-muted">Factura: <strong><?= e($factura['numero']) ?></strong></span>
            <button onclick="document.getElementById('pdfFrame').contentWindow.print()" class="btn btn-sm btn-gold px-3 fw-bold">
                <i class="bi bi-printer me-1"></i> Imprimir / Guardar
            </button>
        </div>
        <iframe id="pdfFrame" src="ver.php?id=<?= $id ?>&pdf=1" class="flex-grow-1 border-0" style="width:100%"></iframe>
      </div>
      <div class="modal-footer py-1">
        <button type="button" class="btn btn-sm btn-outline-secondary px-3" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<style>
.modal-xl { max-width: 900px; }
#pdfFrame { height: 100%; min-height: 500px; }
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

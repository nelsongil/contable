<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/functions.php';

$db   = getDB();
$anio = (int)get('anio', date('Y'));
$trim = (int)get('trim', 0);

// Validar parámetros
if ($anio < 2000 || $anio > 2099) $anio = (int)date('Y');
if (!in_array($trim, [0, 1, 2, 3, 4])) $trim = 0;

// Años disponibles (desde el primer registro)
$anios = $db->query(
    "SELECT DISTINCT YEAR(fecha) anio FROM facturas_emitidas
     UNION
     SELECT DISTINCT YEAR(fecha) FROM facturas_recibidas
     ORDER BY anio DESC"
)->fetchAll(PDO::FETCH_COLUMN);
if (!$anios) $anios = [(int)date('Y')];

// Contar facturas para el período seleccionado (informativo)
$whereE  = "WHERE fe.estado NOT IN ('borrador','cancelada') AND YEAR(fe.fecha) = ?";
$whereR  = "WHERE YEAR(fr.fecha) = ?";
$paramsE = [$anio];
$paramsR = [$anio];
if ($trim) {
    $whereE  .= " AND fe.trimestre = ?"; $paramsE[] = $trim;
    $whereR  .= " AND fr.trimestre = ?"; $paramsR[] = $trim;
}
$stmtE  = $db->prepare("SELECT COUNT(*) FROM facturas_emitidas fe $whereE");
$stmtE->execute($paramsE);
$cntExp = (int)$stmtE->fetchColumn();

$stmtR   = $db->prepare("SELECT COUNT(*) FROM facturas_recibidas fr $whereR");
$stmtR->execute($paramsR);
$cntRec  = (int)$stmtR->fetchColumn();

$periodoLabel = $trim ? "T{$trim} {$anio}" : "Año {$anio} completo";

$pageTitle = 'Exportación AEAT';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="topbar">
  <h1><i class="bi bi-download me-2"></i>Exportación AEAT — Libros Registro</h1>
  <div class="d-flex gap-2 align-items-center flex-wrap">
    <form method="get" class="d-flex gap-2 align-items-center">
      <select name="anio" class="form-select form-select-sm" style="width:90px" onchange="this.form.submit()">
        <?php foreach ($anios as $a): ?>
        <option value="<?= $a ?>" <?= $a === $anio ? 'selected' : '' ?>><?= $a ?></option>
        <?php endforeach; ?>
      </select>
      <select name="trim" class="form-select form-select-sm" style="width:130px" onchange="this.form.submit()">
        <option value="0" <?= $trim === 0 ? 'selected' : '' ?>>Todos los trim.</option>
        <option value="1" <?= $trim === 1 ? 'selected' : '' ?>>1er trimestre</option>
        <option value="2" <?= $trim === 2 ? 'selected' : '' ?>>2º trimestre</option>
        <option value="3" <?= $trim === 3 ? 'selected' : '' ?>>3er trimestre</option>
        <option value="4" <?= $trim === 4 ? 'selected' : '' ?>>4º trimestre</option>
      </select>
    </form>
    <span class="badge rounded-pill" style="background:var(--verde-m);font-size:.75rem;padding:.4em .85em;">
      <?= e($periodoLabel) ?>
    </span>
  </div>
</div>

<div class="row g-4" style="max-width:960px">

  <!-- ═══ TARJETAS DE DESCARGA ═══ -->
  <div class="col-md-6">
    <div class="card h-100">
      <div class="card-header">
        <i class="bi bi-file-earmark-arrow-up me-2"></i>Libro de Facturas Expedidas
      </div>
      <div class="card-body p-4 d-flex flex-column">
        <p class="small text-muted mb-3">
          Registro de ventas emitidas. Excluye borradores y facturas canceladas.
        </p>
        <div class="d-flex align-items-center gap-3 mb-4">
          <span class="fs-3 fw-bold" style="color:var(--verde-a)"><?= $cntExp ?></span>
          <span class="small text-muted">facturas para<br><strong><?= e($periodoLabel) ?></strong></span>
        </div>
        <a href="exportacion_aeat_process.php?tipo=expedidas&anio=<?= $anio ?>&trimestre=<?= $trim ?>"
           class="btn btn-gold mt-auto <?= $cntExp === 0 ? 'disabled' : '' ?>"
           <?= $cntExp === 0 ? 'aria-disabled="true"' : '' ?>>
          <i class="bi bi-download me-2"></i>Descargar CSV — Expedidas
        </a>
        <?php if ($cntExp === 0): ?>
        <p class="small text-muted mt-2 mb-0 text-center">No hay facturas en este período.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-md-6">
    <div class="card h-100">
      <div class="card-header">
        <i class="bi bi-file-earmark-arrow-down me-2"></i>Libro de Facturas Recibidas
      </div>
      <div class="card-body p-4 d-flex flex-column">
        <p class="small text-muted mb-3">
          Registro de compras y gastos. Incluye cuota IVA deducible y gasto deducible en IRPF.
        </p>
        <div class="d-flex align-items-center gap-3 mb-4">
          <span class="fs-3 fw-bold" style="color:var(--verde-a)"><?= $cntRec ?></span>
          <span class="small text-muted">facturas para<br><strong><?= e($periodoLabel) ?></strong></span>
        </div>
        <a href="exportacion_aeat_process.php?tipo=recibidas&anio=<?= $anio ?>&trimestre=<?= $trim ?>"
           class="btn btn-gold mt-auto <?= $cntRec === 0 ? 'disabled' : '' ?>"
           <?= $cntRec === 0 ? 'aria-disabled="true"' : '' ?>>
          <i class="bi bi-download me-2"></i>Descargar CSV — Recibidas
        </a>
        <?php if ($cntRec === 0): ?>
        <p class="small text-muted mt-2 mb-0 text-center">No hay facturas en este período.</p>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- ═══ PANEL DE INFORMACIÓN ═══ -->
  <div class="col-12">
    <div class="card">
      <div class="card-header"><i class="bi bi-info-circle me-2"></i>Formato y limitaciones conocidas</div>
      <div class="card-body p-4">
        <div class="row g-4">
          <div class="col-md-6">
            <h6 class="fw-bold mb-3" style="font-size:.82rem;text-transform:uppercase;letter-spacing:.05em;color:var(--text-2)">
              Especificaciones del archivo
            </h6>
            <ul class="list-unstyled small mb-0" style="line-height:2">
              <li><i class="bi bi-check2 text-success me-2"></i>Formato CSV · separador <code>;</code></li>
              <li><i class="bi bi-check2 text-success me-2"></i>Codificación UTF-8 con BOM (compatible Excel)</li>
              <li><i class="bi bi-check2 text-success me-2"></i>Fechas en formato <code>DD/MM/AAAA</code></li>
              <li><i class="bi bi-check2 text-success me-2"></i>Decimales con coma <code>1.234,56</code></li>
              <li><i class="bi bi-check2 text-success me-2"></i>Orden cronológico por fecha y número</li>
            </ul>
          </div>
          <div class="col-md-6">
            <h6 class="fw-bold mb-3" style="font-size:.82rem;text-transform:uppercase;letter-spacing:.05em;color:var(--text-2)">
              Deuda técnica documentada
            </h6>
            <ul class="list-unstyled small mb-0" style="line-height:2">
              <li><i class="bi bi-exclamation-triangle text-warning me-2"></i>
                <strong>Tipo de factura</strong> = <code>F1</code> en todos los registros.
                Las facturas rectificativas aún no están implementadas.
              </li>
              <li><i class="bi bi-exclamation-triangle text-warning me-2"></i>
                <strong>Concepto de ingreso</strong> = <code>I01</code> fijo.
                Válido para autónomos en estimación directa (IRPF).
              </li>
              <li><i class="bi bi-info-circle text-muted me-2"></i>
                Facturas recibidas sin categoría AEAT exportan con código <code>G16</code>.
                Configura los códigos en <a href="/ajustes/categorias_gasto.php">Categorías de gasto</a>.
              </li>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </div>

</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

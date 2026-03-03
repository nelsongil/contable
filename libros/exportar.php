<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/functions.php';

$anio = (int)get('anio', date('Y'));

// ── Copia de seguridad SQL ──────────────────────────────────
if (get('backup') === '1') {
    try {
        $db = getDB();
        $tables = [];
        $res = $db->query("SHOW TABLES");
        while ($r = $res->fetch(PDO::FETCH_NUM)) $tables[] = $r[0];

        $out = "-- Backup Libro Contable — " . date('Y-m-d H:i:s') . "\n";
        $out .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

        foreach ($tables as $t) {
            $create = $db->query("SHOW CREATE TABLE `$t`")->fetch(PDO::FETCH_NUM);
            $out .= "DROP TABLE IF EXISTS `$t`;\n" . $create[1] . ";\n\n";
            
            $rows = $db->query("SELECT * FROM `$t`")->fetchAll(PDO::FETCH_ASSOC);
            foreach ($rows as $row) {
                $cols = implode("`, `", array_keys($row));
                $vals = array_map(fn($v) => $v === null ? 'NULL' : $db->quote($v), array_values($row));
                $out .= "INSERT INTO `$t` (`$cols`) VALUES (" . implode(", ", $vals) . ");\n";
            }
            $out .= "\n";
        }
        $out .= "SET FOREIGN_KEY_CHECKS=1;\n";

        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="backup_contable_' . date('Ymd_His') . '.sql"');
        echo $out;
        exit;
    } catch (Exception $e) {
        die("Error al generar backup: " . $e->getMessage());
    }
}

// Si se pide descarga
if (get('download') === '1') {
    // Necesita PhpSpreadsheet - si no está instalado, genera CSV
    $ventas  = getFacturasEmitidas($anio);
    $compras = getFacturasRecibidas($anio);

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="LibroContable' . $anio . '.csv"');
    echo "\xEF\xBB\xBF"; // BOM UTF-8

    echo "LIBRO DE VENTAS $anio\r\n";
    echo "Nº Factura,Fecha,Trimestre,Cliente,NIF,Base Imponible,IVA %,Cuota IVA,Retención IRPF,Total,Líquido,Estado\r\n";
    foreach ($ventas as $f) {
        echo implode(',', [
            $f['numero'],
            date('d/m/Y', strtotime($f['fecha'])),
            'T'.$f['trimestre'],
            '"'.str_replace('"','""',$f['cliente_nombre']).'"',
            $f['cliente_nif'],
            number_format($f['base_imponible'],2,'.',','),
            $f['porcentaje_iva'].'%',
            number_format($f['cuota_iva'],2,'.',','),
            number_format($f['cuota_irpf'],2,'.',','),
            number_format($f['total'],2,'.',','),
            number_format($f['liquido'],2,'.',','),
            $f['estado'],
        ]) . "\r\n";
    }

    echo "\r\n";
    echo "LIBRO DE COMPRAS $anio\r\n";
    echo "Nº Factura,Fecha,Trimestre,Proveedor,NIF,Base Imponible,IVA %,Cuota IVA,Total,Descripción\r\n";
    foreach ($compras as $f) {
        echo implode(',', [
            '"'.str_replace('"','""',$f['numero']).'"',
            date('d/m/Y', strtotime($f['fecha'])),
            'T'.$f['trimestre'],
            '"'.str_replace('"','""',$f['proveedor_nombre']).'"',
            $f['proveedor_nif'],
            number_format($f['base_imponible'],2,'.',','),
            $f['porcentaje_iva'].'%',
            number_format($f['cuota_iva'],2,'.',','),
            number_format($f['total'],2,'.',','),
            '"'.str_replace('"','""',$f['descripcion']).'"',
        ]) . "\r\n";
    }
    exit;
}

// Página normal
$pageTitle = 'Exportar datos';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="topbar">
  <h1><i class="bi bi-file-earmark-excel me-2"></i>Exportar datos</h1>
</div>

<div class="row g-3" style="max-width:700px">
  <div class="col-12">
    <div class="card">
      <div class="card-header"><i class="bi bi-download me-2"></i>Exportar libro contable</div>
      <div class="card-body">
        <p class="text-muted mb-3">Descarga un CSV con todos los datos del año seleccionado, listo para importar en Excel.</p>
        <div class="d-flex gap-3 align-items-end">
          <div>
            <label class="form-label">Año</label>
            <select class="form-select" id="selAnio">
              <?php foreach ([date('Y'), date('Y')-1, date('Y')-2] as $y): ?>
              <option value="<?= $y ?>" <?= $y==$anio?'selected':'' ?>><?= $y ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <button class="btn btn-gold" onclick="location.href='exportar.php?download=1&anio='+document.getElementById('selAnio').value">
            <i class="bi bi-file-earmark-arrow-down me-2"></i>Descargar CSV
          </button>
        </div>
        <div class="alert alert-success mt-3 mb-0" style="font-size:.85rem">
          <i class="bi bi-info-circle me-1"></i>
          El archivo CSV se puede abrir directamente en Excel. Para importarlo en el Libro Contable Excel que ya tienes,
          abre el CSV, copia las filas de datos y pégalas en las hojas VENTAS / COMPRAS.
        </div>
      </div>
    </div>
  </div>

  <div class="col-12">
    <div class="card">
      <div class="card-header"><i class="bi bi-printer me-2"></i>Imprimir libros</div>
      <div class="card-body">
        <p class="text-muted mb-3">Versión imprimible del libro de ventas y compras por trimestre.</p>
        <div class="d-flex gap-2 flex-wrap">
          <a href="/libros/" class="btn btn-outline-primary"><i class="bi bi-journal-text me-1"></i>Libro de ventas</a>
          <a href="/compras/" class="btn btn-outline-primary"><i class="bi bi-journal me-1"></i>Libro de compras</a>
          <a href="/libros/resumen.php" class="btn btn-outline-secondary"><i class="bi bi-bar-chart me-1"></i>Resumen fiscal</a>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

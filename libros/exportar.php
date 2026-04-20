<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/functions.php';

$anio        = (int)get('anio', 0);
$trim        = (int)get('trim', 0);
$fecha_desde = get('fecha_desde');
$fecha_hasta = get('fecha_hasta');
$tipo        = get('tipo', 'ambas'); // emitidas | recibidas | ambas

// ── Copia de seguridad SQL ──────────────────────────────────────────────────
if (get('backup') === '1') {
    try {
        $sql = generateSQLDump();
        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="backup_contable_' . date('Ymd_His') . '.sql"');
        echo $sql;
        exit;
    } catch (Exception $e) {
        die("Error al generar backup: " . $e->getMessage());
    }
}

// ── Descarga CSV ─────────────────────────────────────────────────────────────
if (get('download') === '1') {
    $db = getDB();

    // Construir condiciones según el período (params independientes por tabla)
    $whereEmit  = ["fe.estado != 'cancelada'"];
    $whereRecib = ["1=1"];
    $paramsE    = [];
    $paramsR    = [];

    if ($anio && !$fecha_desde) {
        $whereEmit[]  = "YEAR(fe.fecha) = ?";  $paramsE[] = $anio;
        $whereRecib[] = "YEAR(fr.fecha) = ?";  $paramsR[] = $anio;
    }
    if ($trim) {
        $whereEmit[]  = "fe.trimestre = ?";    $paramsE[] = $trim;
        $whereRecib[] = "fr.trimestre = ?";    $paramsR[] = $trim;
    }
    if ($fecha_desde) {
        $whereEmit[]  = "fe.fecha >= ?";       $paramsE[] = $fecha_desde;
        $whereRecib[] = "fr.fecha >= ?";       $paramsR[] = $fecha_desde;
    }
    if ($fecha_hasta) {
        $whereEmit[]  = "fe.fecha <= ?";       $paramsE[] = $fecha_hasta;
        $whereRecib[] = "fr.fecha <= ?";       $paramsR[] = $fecha_hasta;
    }

    // Nombre del archivo
    if ($fecha_desde && $fecha_hasta) {
        $sufijo = date('d-m-Y', strtotime($fecha_desde)) . '_' . date('d-m-Y', strtotime($fecha_hasta));
    } elseif ($trim && $anio) {
        $sufijo = "T{$trim}_{$anio}";
    } elseif ($anio) {
        $sufijo = $anio;
    } else {
        $sufijo = 'todo';
    }
    $nombre = "facturas_{$tipo}_{$sufijo}.csv";

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $nombre . '"');
    echo "\xEF\xBB\xBF"; // BOM UTF-8

    // ── Facturas emitidas ──
    if ($tipo === 'emitidas' || $tipo === 'ambas') {
        $stE = $db->prepare(
            "SELECT fe.numero, fe.fecha, fe.trimestre,
                    fe.cliente_nombre, fe.cliente_nif,
                    fe.base_imponible, fe.porcentaje_iva, fe.cuota_iva,
                    fe.cuota_irpf, fe.total, fe.liquido, fe.estado
             FROM facturas_emitidas fe
             WHERE " . implode(' AND ', $whereEmit) . "
             ORDER BY fe.fecha, fe.numero"
        );
        $stE->execute($paramsE);
        $ventas = $stE->fetchAll();

        echo "FACTURAS EMITIDAS\r\n";
        echo "Nº Factura,Fecha,Trimestre,Cliente,NIF,Base Imponible,IVA %,Cuota IVA,Retención IRPF,Total,Líquido,Estado\r\n";
        foreach ($ventas as $f) {
            echo implode(',', [
                '"' . str_replace('"', '""', $f['numero']) . '"',
                date('d/m/Y', strtotime($f['fecha'])),
                'T' . $f['trimestre'],
                '"' . str_replace('"', '""', $f['cliente_nombre']) . '"',
                $f['cliente_nif'],
                number_format($f['base_imponible'], 2, '.', ''),
                $f['porcentaje_iva'] . '%',
                number_format($f['cuota_iva'], 2, '.', ''),
                number_format($f['cuota_irpf'], 2, '.', ''),
                number_format($f['total'], 2, '.', ''),
                number_format($f['liquido'], 2, '.', ''),
                $f['estado'],
            ]) . "\r\n";
        }
        echo "\r\n";
    }

    // ── Facturas recibidas ──
    if ($tipo === 'recibidas' || $tipo === 'ambas') {
        $stR = $db->prepare(
            "SELECT fr.numero, fr.fecha, fr.trimestre,
                    fr.proveedor_nombre, fr.proveedor_nif,
                    fr.categoria, fr.base_imponible,
                    fr.porcentaje_iva, fr.cuota_iva,
                    fr.porcentaje_irpf, fr.cuota_irpf,
                    fr.total, fr.descripcion
             FROM facturas_recibidas fr
             WHERE " . implode(' AND ', $whereRecib) . "
             ORDER BY fr.fecha, fr.numero"
        );
        $stR->execute($paramsR);
        $compras = $stR->fetchAll();

        echo "FACTURAS RECIBIDAS\r\n";
        echo "Nº Factura,Fecha,Trimestre,Proveedor,NIF,Categoría,Base Imponible,IVA %,Cuota IVA,Ret. IRPF %,Cuota IRPF,Total,Descripción\r\n";
        foreach ($compras as $f) {
            echo implode(',', [
                '"' . str_replace('"', '""', $f['numero']) . '"',
                date('d/m/Y', strtotime($f['fecha'])),
                'T' . $f['trimestre'],
                '"' . str_replace('"', '""', $f['proveedor_nombre']) . '"',
                $f['proveedor_nif'],
                $f['categoria'],
                number_format($f['base_imponible'], 2, '.', ''),
                $f['porcentaje_iva'] . '%',
                number_format($f['cuota_iva'], 2, '.', ''),
                $f['porcentaje_irpf'] . '%',
                number_format($f['cuota_irpf'], 2, '.', ''),
                number_format($f['total'], 2, '.', ''),
                '"' . str_replace('"', '""', $f['descripcion']) . '"',
            ]) . "\r\n";
        }
    }
    exit;
}

// ── Página normal ─────────────────────────────────────────────────────────────
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();
$pageTitle = 'Exportar datos';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="topbar">
  <h1><i class="bi bi-file-earmark-excel me-2"></i>Exportar datos</h1>
</div>

<div class="row g-3" style="max-width:700px">
  <div class="col-12">
    <div class="card">
      <div class="card-header"><i class="bi bi-download me-2"></i>Exportar facturas a CSV</div>
      <div class="card-body">
        <p class="text-muted mb-3" style="font-size:.88rem">Descarga las facturas filtradas en formato CSV compatible con Excel.</p>

        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Tipo</label>
            <select class="form-select" id="expTipo">
              <option value="ambas">Emitidas + Recibidas</option>
              <option value="emitidas">Solo emitidas</option>
              <option value="recibidas">Solo recibidas</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label">Período</label>
            <select class="form-select" id="expPeriodoSel" onchange="togglePeriodo()">
              <option value="anio">Año completo</option>
              <option value="trim">Trimestre</option>
              <option value="rango">Rango de fechas</option>
              <option value="todo">Todo</option>
            </select>
          </div>

          <div class="col-md-6" id="rowAnio">
            <label class="form-label">Año</label>
            <select class="form-select" id="selAnio">
              <?php foreach ([date('Y'), date('Y')-1, date('Y')-2] as $y): ?>
              <option value="<?= $y ?>"><?= $y ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div id="rowTrim" style="display:none" class="col-12">
            <div class="row g-2">
              <div class="col-md-4">
                <label class="form-label">Año</label>
                <select class="form-select" id="selAnioTrim">
                  <?php foreach ([date('Y'), date('Y')-1, date('Y')-2] as $y): ?>
                  <option value="<?= $y ?>"><?= $y ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-4">
                <label class="form-label">Trimestre</label>
                <select class="form-select" id="selTrim">
                  <option value="1">T1 (ene–mar)</option>
                  <option value="2">T2 (abr–jun)</option>
                  <option value="3">T3 (jul–sep)</option>
                  <option value="4">T4 (oct–dic)</option>
                </select>
              </div>
            </div>
          </div>

          <div id="rowRango" style="display:none" class="col-12">
            <div class="row g-2">
              <div class="col-md-4">
                <label class="form-label">Desde</label>
                <input type="date" class="form-control" id="selDesde" value="<?= date('Y-01-01') ?>">
              </div>
              <div class="col-md-4">
                <label class="form-label">Hasta</label>
                <input type="date" class="form-control" id="selHasta" value="<?= date('Y-m-d') ?>">
              </div>
            </div>
          </div>

          <div class="col-12">
            <button class="btn btn-gold" onclick="descargarPagina()">
              <i class="bi bi-file-earmark-arrow-down me-2"></i>Descargar CSV
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-12">
    <div class="card">
      <div class="card-header"><i class="bi bi-printer me-2"></i>Imprimir libros</div>
      <div class="card-body">
        <p class="text-muted mb-3" style="font-size:.88rem">Versión imprimible del libro de ventas y compras por trimestre.</p>
        <div class="d-flex gap-2 flex-wrap">
          <a href="/libros/" class="btn btn-outline-primary"><i class="bi bi-journal-text me-1"></i>Libro de ventas</a>
          <a href="/compras/" class="btn btn-outline-primary"><i class="bi bi-journal me-1"></i>Libro de compras</a>
          <a href="/libros/resumen.php" class="btn btn-outline-secondary"><i class="bi bi-bar-chart me-1"></i>Resumen fiscal</a>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
function togglePeriodo() {
    const p = document.getElementById('expPeriodoSel').value;
    document.getElementById('rowAnio').style.display  = p === 'anio'  ? '' : 'none';
    document.getElementById('rowTrim').style.display  = p === 'trim'  ? '' : 'none';
    document.getElementById('rowRango').style.display = p === 'rango' ? '' : 'none';
}
function descargarPagina() {
    const tipo = document.getElementById('expTipo').value;
    const p    = document.getElementById('expPeriodoSel').value;
    let url    = '/libros/exportar.php?download=1&tipo=' + tipo;
    if (p === 'anio')  url += '&anio=' + document.getElementById('selAnio').value;
    if (p === 'trim')  url += '&anio=' + document.getElementById('selAnioTrim').value + '&trim=' + document.getElementById('selTrim').value;
    if (p === 'rango') url += '&fecha_desde=' + document.getElementById('selDesde').value + '&fecha_hasta=' + document.getElementById('selHasta').value;
    window.location.href = url;
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

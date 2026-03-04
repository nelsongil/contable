<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$id      = (int)get('id');
$factura = $id ? getFacturaEmitida($id) : null;
$lineas  = $id ? getLineasFactura($id) : [];
$clientes = getClientes();

$isEdit  = (bool)$id;

// ── Procesar formulario ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db          = getDB();
    $clienteId   = (int)post('cliente_id');
    $cliente     = $clienteId ? getCliente($clienteId) : null;
    $fecha       = post('fecha') ?: date('Y-m-d');
    $vencimiento = post('fecha_vencimiento');
    $pct_iva     = (float)post('pct_iva', 21);
    $pct_irpf    = (float)post('pct_irpf', 0);
    $notas       = post('notas');
    $estado      = post('estado', 'emitida');

    // Líneas
    $cantidades   = $_POST['cantidad']    ?? [];
    $descripciones= $_POST['descripcion'] ?? [];
    $precios      = $_POST['precio']      ?? [];

    $base = 0;
    $lineasValidas = [];
    foreach ($cantidades as $i => $cant) {
        $desc  = trim($descripciones[$i] ?? '');
        $precio= (float)str_replace(',', '.', $precios[$i] ?? 0);
        $cant  = (float)str_replace(',', '.', $cant);
        if (!$desc || $cant == 0) continue;
        $total  = round($cant * $precio, 2);
        $base  += $total;
        $lineasValidas[] = [$i, $cant, $desc, $precio, $total];
    }

    $cuota_iva  = round($base * $pct_iva  / 100, 2);
    $cuota_irpf = round($base * $pct_irpf / 100, 2);
    $total      = $base + $cuota_iva;
    $liquido    = $total - $cuota_irpf;
    $trim       = trimestre($fecha);

    if (!$lineasValidas) {
        $error = 'Añade al menos una línea de factura.';
    } else {
        try {
            if ($isEdit) {
                $db->prepare("UPDATE facturas_emitidas SET fecha=?,fecha_vencimiento=?,cliente_id=?,
                              cliente_nombre=?,cliente_nif=?,base_imponible=?,porcentaje_iva=?,cuota_iva=?,
                              porcentaje_irpf=?,cuota_irpf=?,total=?,liquido=?,notas=?,estado=?,trimestre=?
                              WHERE id=?")
                   ->execute([$fecha, $vencimiento ?: null, $clienteId ?: null,
                              $cliente['nombre'] ?? post('cliente_nombre'),
                              $cliente['nif'] ?? '',
                              $base, $pct_iva, $cuota_iva, $pct_irpf, $cuota_irpf,
                              $total, $liquido, $notas, $estado, $trim, $id]);
                $db->prepare("DELETE FROM facturas_emitidas_lineas WHERE factura_id=?")->execute([$id]);
                $fid = $id;
            } else {
                $numero = siguienteNumeroFactura();
                $db->prepare("INSERT INTO facturas_emitidas
                              (numero,fecha,fecha_vencimiento,cliente_id,cliente_nombre,cliente_nif,
                               base_imponible,porcentaje_iva,cuota_iva,porcentaje_irpf,cuota_irpf,
                               total,liquido,notas,estado,trimestre)
                              VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
                   ->execute([$numero, $fecha, $vencimiento ?: null, $clienteId ?: null,
                              $cliente['nombre'] ?? post('cliente_nombre'),
                              $cliente['nif'] ?? '',
                              $base, $pct_iva, $cuota_iva, $pct_irpf, $cuota_irpf,
                              $total, $liquido, $notas, $estado, $trim]);
                $fid = (int)$db->lastInsertId();
            }
            foreach ($lineasValidas as [$ord, $cant, $desc, $precio, $ltotal]) {
                $db->prepare("INSERT INTO facturas_emitidas_lineas (factura_id,orden,cantidad,descripcion,precio,total)
                              VALUES (?,?,?,?,?,?)")
                   ->execute([$fid, $ord, $cant, $desc, $precio, $ltotal]);
            }
            flash($isEdit ? 'Factura actualizada.' : "Factura creada correctamente.");
            redirect('/facturas/ver.php?id=' . $fid);
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) {
                $error = "Error: El número de factura ya existe.";
            } else {
                $error = "Error al guardar: " . $e->getMessage();
            }
        }
    }
}

$pageTitle = $isEdit ? 'Editar factura ' . ($factura['numero'] ?? '') : 'Nueva factura';
require_once __DIR__ . '/../includes/header.php';

$defaultFecha = $isEdit ? ($factura['fecha'] ?? date('Y-m-d')) : date('Y-m-d');
$defaultVenc  = $isEdit ? ($factura['fecha_vencimiento'] ?? '') : date('Y-m-d', strtotime('+30 days'));
?>

<div class="topbar">
  <h1><i class="bi bi-receipt me-2"></i><?= $pageTitle ?></h1>
  <a href="<?= $isEdit ? '/facturas/ver.php?id=' . $id : '/facturas/' ?>" class="btn btn-sm btn-outline-secondary">
    <i class="bi bi-arrow-left me-1"></i>Volver
  </a>
</div>

<?php if (!empty($error)): ?>
<div class="alert alert-danger"><?= e($error) ?></div>
<?php endif; ?>

<form method="post" id="formFactura">
<div class="row g-3">

  <!-- Cabecera factura -->
  <div class="col-lg-8">
    <div class="card">
      <div class="card-header"><i class="bi bi-person-lines-fill me-2"></i>Datos del cliente</div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-12">
            <label class="form-label">Cliente</label>
            <select name="cliente_id" id="selectCliente" class="form-select">
              <option value="">— Selecciona o escribe —</option>
              <?php foreach ($clientes as $cl): ?>
              <option value="<?= $cl['id'] ?>"
                data-nif="<?= e($cl['nif']) ?>"
                data-dir="<?= e($cl['direccion'] . ', ' . $cl['cp'] . ' ' . $cl['ciudad']) ?>"
                <?= ($factura['cliente_id'] ?? 0) == $cl['id'] ? 'selected' : '' ?>>
                <?= e($cl['nombre']) ?> <?= $cl['nif'] ? '— ' . e($cl['nif']) : '' ?>
              </option>
              <?php endforeach; ?>
            </select>
            <div class="form-text">¿Cliente nuevo? <a href="/clientes/nuevo.php" target="_blank">Créalo aquí</a> y recarga.</div>
          </div>
          <div class="col-md-6" id="clienteNifRow" style="<?= !($factura['cliente_id'] ?? 0) ? 'display:none' : '' ?>">
            <label class="form-label">NIF/CIF</label>
            <input type="text" class="form-control" id="clienteNif" readonly value="<?= e($factura['cliente_nif'] ?? '') ?>">
          </div>
          <div class="col-md-6" id="clienteDirRow" style="<?= !($factura['cliente_id'] ?? 0) ? 'display:none' : '' ?>">
            <label class="form-label">Dirección</label>
            <input type="text" class="form-control" id="clienteDir" readonly value="">
          </div>
          <!-- Fallback nombre libre si no está en BD -->
          <div class="col-12" id="clienteLibreRow" style="display:none">
            <label class="form-label">Nombre (si no está en la lista)</label>
            <input type="text" name="cliente_nombre" class="form-control" placeholder="Nombre del cliente">
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Datos factura -->
  <div class="col-lg-4">
    <div class="card">
      <div class="card-header"><i class="bi bi-hash me-2"></i>Datos de la factura</div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-12">
            <div class="form-floating">
              <input type="date" name="fecha" id="fecha" class="form-control" value="<?= e($defaultFecha) ?>" placeholder="Fecha" required>
              <label for="fecha">Fecha</label>
            </div>
          </div>
          <div class="col-12">
            <div class="form-floating">
              <input type="date" name="fecha_vencimiento" id="fecha_vencimiento" class="form-control" value="<?= e($defaultVenc) ?>" placeholder="Vencimiento">
              <label for="fecha_vencimiento">Vencimiento</label>
            </div>
          </div>
          <div class="col-6">
            <label class="form-label">IVA %</label>
            <select name="pct_iva" class="form-select" id="pctIva">
              <?php 
                $defIva = (int)getConfig('empresa_iva_def', 21);
                foreach ([21, 10, 4, 0] as $t): 
              ?>
              <option value="<?= $t ?>" <?= ($factura['porcentaje_iva'] ?? $defIva) == $t ? 'selected' : '' ?>><?= $t ?>%</option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-6">
            <label class="form-label">Ret. IRPF %</label>
            <select name="pct_irpf" class="form-select" id="pctIrpf">
              <?php 
                $defIrpf = (int)getConfig('empresa_irpf_def', 15);
                foreach ([0, 7, 15, 19] as $t): 
              ?>
              <option value="<?= $t ?>" <?= ($factura['porcentaje_irpf'] ?? $defIrpf) == $t ? 'selected' : '' ?>><?= $t ?>%</option>
              <?php endforeach; ?>
            </select>
          </div>
          <?php if ($isEdit): ?>
          <div class="col-12">
            <label class="form-label">Estado</label>
            <select name="estado" class="form-select">
              <?php foreach (['borrador','emitida','pagada','cancelada'] as $est): ?>
              <option value="<?= $est ?>" <?= ($factura['estado'] ?? 'emitida') === $est ? 'selected' : '' ?>><?= ucfirst($est) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Líneas -->
  <div class="col-12">
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-list-ul me-2"></i>Líneas de factura</span>
        <button type="button" class="btn btn-sm btn-outline-light" id="btnAddLinea">
          <i class="bi bi-plus-lg me-1"></i>Añadir línea
        </button>
      </div>
      <div class="card-body p-0">
        <table class="table lineas-table mb-0" id="tablaLineas">
          <thead>
            <tr>
              <th style="width:80px">Cant.</th>
              <th>Descripción / Concepto</th>
              <th style="width:130px" class="text-end">Precio unit.</th>
              <th style="width:120px" class="text-end">Total</th>
              <th style="width:40px"></th>
            </tr>
          </thead>
          <tbody id="lineasBody">
            <?php
            $lineasInit = $lineas ?: [
                ['cantidad'=>1,'descripcion'=>'','precio'=>0,'total'=>0],
                ['cantidad'=>1,'descripcion'=>'','precio'=>0,'total'=>0],
                ['cantidad'=>1,'descripcion'=>'','precio'=>0,'total'=>0],
            ];
            foreach ($lineasInit as $i => $l): ?>
            <tr class="linea-row">
              <td><input type="number" name="cantidad[]" class="linea-cant" value="<?= e($l['cantidad']) ?>" min="0" step="0.001" placeholder="1"></td>
              <td><input type="text"   name="descripcion[]" class="linea-desc" value="<?= e($l['descripcion']) ?>" placeholder="Descripción del servicio o producto"></td>
              <td><input type="number" name="precio[]" class="linea-precio text-end" value="<?= $l['precio'] ? moneyInput($l['precio']) : '' ?>" min="0" step="0.0001" placeholder="0.00"></td>
              <td class="text-end fw-semibold linea-total"><?= $l['total'] ? money($l['total']) : '—' ?></td>
              <td><button type="button" class="btn btn-sm btn-remove btn-outline-danger"><i class="bi bi-x"></i></button></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <!-- Totales -->
      <div class="card-footer bg-white">
        <div class="row justify-content-end">
          <div class="col-md-4">
            <table class="table table-sm mb-0 text-end">
              <tr><td class="text-muted">Base imponible</td><td class="fw-semibold" id="resBase">0,00 €</td></tr>
              <tr><td class="text-muted" id="labelIva">IVA (21%)</td><td id="resIva">0,00 €</td></tr>
              <tr id="rowIrpf" style="display:none"><td class="text-muted text-danger" id="labelIrpf">Ret. IRPF (0%)</td><td class="text-danger" id="resIrpf">0,00 €</td></tr>
              <tr class="fw-bold fs-5"><td>TOTAL</td><td id="resTotal">0,00 €</td></tr>
              <tr id="rowLiquido" style="display:none"><td class="text-muted small">Líquido a cobrar</td><td class="small" id="resLiquido">0,00 €</td></tr>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Notas -->
  <div class="col-12">
    <div class="card">
      <div class="card-body">
        <label class="form-label">Notas / Observaciones</label>
        <textarea name="notas" class="form-control" rows="2" placeholder="Información adicional que aparecerá en la factura"><?= e($factura['notas'] ?? '') ?></textarea>
      </div>
    </div>
  </div>

  <div class="col-12 pb-4">
    <button type="submit" class="btn btn-gold px-5" id="btnSubmit">
      <span class="spinner-border spinner-border-sm d-none me-1" id="submitSpinner"></span>
      <i class="bi bi-check-lg me-1" id="submitIcon"></i>
      <span id="submitText"><?= $isEdit ? 'Guardar cambios' : 'Crear factura' ?></span>
    </button>
    <a href="/facturas/" class="btn btn-outline-secondary ms-2">Cancelar</a>
  </div>

</div><!-- /row -->
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // ── Autocomplete cliente ───────────────────────────────
    document.getElementById('selectCliente').addEventListener('change', function() {
        const opt = this.options[this.selectedIndex];
        const nif = opt.dataset.nif || '';
        const dir = opt.dataset.dir || '';
        document.getElementById('clienteNif').value = nif;
        document.getElementById('clienteDir').value = dir;
        document.getElementById('clienteNifRow').style.display = this.value ? '' : 'none';
        document.getElementById('clienteDirRow').style.display = this.value ? '' : 'none';
        document.getElementById('clienteLibreRow').style.display = this.value ? 'none' : '';
    });

    // ── Tom Select para búsqueda en el select de clientes ──
    if (typeof TomSelect !== 'undefined') {
        new TomSelect('#selectCliente', { create: false, sortField: 'text' });
    }

    // ── Cálculo automático de totales ─────────────────────
    function fmt(v) {
        return v.toLocaleString('es-ES', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €';
    }
    function recalc() {
        let base = 0;
        document.querySelectorAll('.linea-row').forEach(function(row) {
            const cant  = parseFloat(row.querySelector('.linea-cant').value)   || 0;
            const precio= parseFloat(row.querySelector('.linea-precio').value) || 0;
            const total = Math.round(cant * precio * 100) / 100;
            row.querySelector('.linea-total').textContent = total ? fmt(total) : '—';
            base += total;
        });
        const pctIva  = parseFloat(document.getElementById('pctIva').value)  || 0;
        const pctIrpf = parseFloat(document.getElementById('pctIrpf').value) || 0;
        const iva     = Math.round(base * pctIva  / 100 * 100) / 100;
        const irpf    = Math.round(base * pctIrpf / 100 * 100) / 100;
        const total   = base + iva;
        const liquido = total - irpf;

        document.getElementById('resBase').textContent    = fmt(base);
        document.getElementById('resIva').textContent     = fmt(iva);
        document.getElementById('resIrpf').textContent    = '-' + fmt(irpf);
        document.getElementById('resTotal').textContent   = fmt(total);
        document.getElementById('resLiquido').textContent = fmt(liquido);
        document.getElementById('labelIva').textContent   = 'IVA (' + pctIva + '%)';
        document.getElementById('labelIrpf').textContent  = 'Ret. IRPF (' + pctIrpf + '%)';
        document.getElementById('rowIrpf').style.display   = pctIrpf > 0 ? '' : 'none';
        document.getElementById('rowLiquido').style.display = pctIrpf > 0 ? '' : 'none';
    }

    document.getElementById('tablaLineas').addEventListener('input', recalc);
    document.getElementById('pctIva').addEventListener('change',  recalc);
    document.getElementById('pctIrpf').addEventListener('change', recalc);

    // ── Añadir línea ──────────────────────────────────────
    document.getElementById('btnAddLinea').addEventListener('click', function() {
        const tbody = document.getElementById('lineasBody');
        const tr    = document.createElement('tr');
        tr.className = 'linea-row';
        tr.innerHTML = `
            <td><input type="number" name="cantidad[]"     class="linea-cant"   value="1" min="0" step="0.001"></td>
            <td><input type="text"   name="descripcion[]"  class="linea-desc"   placeholder="Descripción"></td>
            <td><input type="number" name="precio[]"       class="linea-precio text-end" min="0" step="0.0001" placeholder="0.00"></td>
            <td class="text-end fw-semibold linea-total">—</td>
            <td><button type="button" class="btn btn-sm btn-remove btn-outline-danger"><i class="bi bi-x"></i></button></td>
        `;
        tbody.appendChild(tr);
        tr.querySelector('.linea-desc').focus();
    });

    // ── Eliminar línea ────────────────────────────────────
    document.getElementById('lineasBody').addEventListener('click', function(e) {
        if (e.target.closest('.btn-remove')) {
            e.target.closest('tr').remove();
            recalc();
        }
    });

    recalc();

    // ── Spinner en submit ────────────────────────────────
    document.getElementById('formFactura').addEventListener('submit', function() {
        const btn = document.getElementById('btnSubmit');
        const spinner = document.getElementById('submitSpinner');
        const icon = document.getElementById('submitIcon');
        const text = document.getElementById('submitText');
        
        btn.disabled = true;
        spinner.classList.remove('d-none');
        icon.classList.add('d-none');
        text.textContent = 'Guardando...';
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

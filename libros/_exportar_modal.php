<?php /* Partial: controles del modal de exportación CSV — incluido por facturas/index.php y compras/index.php */ ?>
<div class="mb-3">
  <label class="form-label fw-semibold">Período</label>
  <div class="d-flex flex-wrap gap-2">
    <?php foreach (['anio'=>'Año completo','trim'=>'Trimestre','rango'=>'Rango de fechas','todo'=>'Todo'] as $v=>$l): ?>
    <div class="form-check">
      <input class="form-check-input" type="radio" name="expPeriodo" id="expP_<?= $v ?>" value="<?= $v ?>"
             <?= $v==='anio'?'checked':'' ?> onchange="toggleExportPeriodo()">
      <label class="form-check-label" for="expP_<?= $v ?>"><?= $l ?></label>
    </div>
    <?php endforeach; ?>
  </div>
</div>

<div id="expRowAnio" class="mb-3">
  <label class="form-label">Año</label>
  <select class="form-select" id="expAnio">
    <?php foreach ([date('Y'), date('Y')-1, date('Y')-2] as $y): ?>
    <option value="<?= $y ?>"><?= $y ?></option>
    <?php endforeach; ?>
  </select>
</div>

<div id="expRowTrim" class="mb-3" style="display:none">
  <label class="form-label">Trimestre</label>
  <div class="d-flex gap-2">
    <select class="form-select" id="expAnioTrim">
      <?php foreach ([date('Y'), date('Y')-1, date('Y')-2] as $y): ?>
      <option value="<?= $y ?>"><?= $y ?></option>
      <?php endforeach; ?>
    </select>
    <select class="form-select" id="expTrim">
      <option value="1">T1 (ene–mar)</option>
      <option value="2">T2 (abr–jun)</option>
      <option value="3">T3 (jul–sep)</option>
      <option value="4">T4 (oct–dic)</option>
    </select>
  </div>
</div>

<div id="expRowRango" class="mb-3" style="display:none">
  <div class="row g-2">
    <div class="col-6">
      <label class="form-label">Desde</label>
      <input type="date" class="form-control" id="expDesde" value="<?= date('Y-01-01') ?>">
    </div>
    <div class="col-6">
      <label class="form-label">Hasta</label>
      <input type="date" class="form-control" id="expHasta" value="<?= date('Y-m-d') ?>">
    </div>
  </div>
</div>

<script>
function toggleExportPeriodo() {
    const p = document.querySelector('input[name="expPeriodo"]:checked').value;
    document.getElementById('expRowAnio').style.display  = p === 'anio' ? '' : 'none';
    document.getElementById('expRowTrim').style.display  = p === 'trim' ? '' : 'none';
    document.getElementById('expRowRango').style.display = p === 'rango' ? '' : 'none';
}
function initExportModal(tipo) {
    window._exportTipo = tipo;
    toggleExportPeriodo();
}
function descargarCsv(tipo) {
    const p = document.querySelector('input[name="expPeriodo"]:checked').value;
    let url = '/libros/exportar.php?download=1&tipo=' + tipo;
    if (p === 'anio')  url += '&anio=' + document.getElementById('expAnio').value;
    if (p === 'trim')  url += '&anio=' + document.getElementById('expAnioTrim').value + '&trim=' + document.getElementById('expTrim').value;
    if (p === 'rango') url += '&fecha_desde=' + document.getElementById('expDesde').value + '&fecha_hasta=' + document.getElementById('expHasta').value;
    // todo: sin parámetros de período
    window.location.href = url;
}
</script>

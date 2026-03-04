<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/functions.php';

$pageTitle = 'Copias de seguridad';
require_once __DIR__ . '/../includes/header.php';

$autoBackup    = getConfig('backup_auto', '0') === '1';
$lastAuto      = getConfig('ultimo_backup_auto', 0);
$maxManuales   = (int)getConfig('backup_max_manuales', 10);
$maxAuto       = (int)getConfig('backup_max_auto', 4);
?>

<div class="topbar">
  <h1><i class="bi bi-database-fill-gear me-2"></i>Copias de seguridad</h1>
</div>

<div class="row g-4" style="max-width: 1000px;">
  <div class="col-md-7">
    <div class="card mb-4">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="bi bi-list-task me-2"></i>Backups disponibles</span>
        <button class="btn btn-sm btn-gold" onclick="loadBackups()">
            <i class="bi bi-arrow-clockwise"></i>
        </button>
      </div>
      <div class="card-body p-0">
        <div class="table-responsive">
          <table class="table table-hover align-middle mb-0" style="font-size: .88rem;">
            <thead class="table-light">
              <tr>
                <th>Fecha y Hora</th>
                <th>Archivo</th>
                <th>Tamaño</th>
                <th class="text-end">Acciones</th>
              </tr>
            </thead>
            <tbody id="backupList">
              <tr><td colspan="4" class="text-center py-4 text-muted">Cargando...</td></tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="card border-danger-subtle shadow-sm">
      <div class="card-header bg-danger-subtle text-danger fw-bold">
        <i class="bi bi-exclamation-octagon me-2"></i>RESTAURAR SISTEMA
      </div>
      <div class="card-body p-4">
        <p class="small text-muted mb-4">
            Selecciona un archivo de backup para restaurar la base de datos completa. 
            <strong>ADVERTENCIA:</strong> Esto borrará todos los datos actuales y no se puede deshacer.
        </p>
        
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label small fw-bold">Archivo a restaurar</label>
            <select class="form-select" id="restoreFile">
                <option value="">-- Selecciona un archivo --</option>
            </select>
          </div>
          <div class="col-md-6">
            <label class="form-label small fw-bold">Escribe "CONFIRMAR"</label>
            <input type="text" class="form-control" id="confirmText" placeholder="Pista: Mayúsculas">
          </div>
          <div class="col-12 mt-4">
            <button class="btn btn-danger w-100 py-3 fw-bold shadow-sm" id="btnRestore" disabled>
                <i class="bi bi-database-fill-up me-2"></i>RESTAURAR TODO AHORA
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="col-md-5">
    <div class="card mb-4 bg-gold shadow-sm border-0">
      <div class="card-body p-4 text-center">
        <div class="mb-3"><i class="bi bi-plus-circle-fill h1"></i></div>
        <h5 class="fw-bold">Nuevo punto de control</h5>
        <p class="small opacity-75 mb-4">Crea una copia de seguridad manual de toda tu contabilidad en un clic.</p>
        <button class="btn btn-white w-100 py-2 fw-bold text-dark" onclick="createBackup()" id="btnNewBackup">
            <i class="bi bi-cloud-arrow-up-fill me-2"></i>GENERAR BACKUP AHORA
        </button>
      </div>
    </div>

    <div class="card mb-4">
      <div class="card-header"><i class="bi bi-alarm me-2"></i>Backup Automático</div>
      <div class="card-body">
        <div class="form-check form-switch mb-3">
          <input class="form-check-input" type="checkbox" id="checkAuto" <?= $autoBackup ? 'checked' : '' ?> onchange="saveAutoConfig()">
          <label class="form-check-label fw-bold" for="checkAuto">Copia semanal automática</label>
        </div>
        <p class="small text-muted mb-3">Si se activa, el sistema realizará un backup cada 7 días al abrir el panel de control.</p>
        <?php if ($lastAuto): ?>
        <div class="alert alert-light border p-2 mb-0" style="font-size: .75rem;">
            <i class="bi bi-clock-history me-1"></i> Último auto-backup: <?= date('d/m/Y H:i', $lastAuto) ?>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="card mb-4">
      <div class="card-header"><i class="bi bi-sliders me-2"></i>Límite de copias guardadas</div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-6">
            <label class="form-label small fw-bold">Copias manuales máximas</label>
            <input type="number" id="inputMaxManuales" class="form-control form-control-sm"
                   min="1" max="50" value="<?= $maxManuales ?>"
                   onchange="saveLimit('backup_max_manuales', this.value)">
            <div class="form-text">Las más antiguas se eliminan al crear una nueva.</div>
          </div>
          <div class="col-6">
            <label class="form-label small fw-bold">Copias automáticas máximas</label>
            <input type="number" id="inputMaxAuto" class="form-control form-control-sm"
                   min="1" max="20" value="<?= $maxAuto ?>"
                   onchange="saveLimit('backup_max_auto', this.value)">
            <div class="form-text">Las más antiguas se eliminan al hacer el semanal.</div>
          </div>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-header bg-light"><i class="bi bi-shield-check me-2"></i>Seguridad</div>
      <div class="card-body p-4 small">
        <ul class="mb-0 ps-3">
            <li class="mb-2">Los archivos se guardan en <code>/backups/</code>.</li>
            <li class="mb-2">Cada backup incluye los datos de empresa aunque no hayas guardado en Ajustes.</li>
            <li class="mb-2">Se recomienda descargar las copias importantes a tu ordenador personal.</li>
            <li>El sistema excluye la tabla de sesiones para mayor ligereza.</li>
        </ul>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    loadBackups();
    
    const confirmInput = document.getElementById('confirmText');
    const restoreSelect = document.getElementById('restoreFile');
    const btnRestore = document.getElementById('btnRestore');

    const checkStatus = () => {
        btnRestore.disabled = !(confirmInput.value === 'CONFIRMAR' && restoreSelect.value !== '');
    };

    confirmInput.addEventListener('input', checkStatus);
    restoreSelect.addEventListener('change', checkStatus);
    
    btnRestore.addEventListener('click', async () => {
        if (!confirm('¿Estás SEGURO? La aplicación se reiniciará y perderás los datos actuales.')) return;
        
        btnRestore.disabled = true;
        btnRestore.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Restaurando...';

        try {
            const formData = new FormData();
            formData.append('confirm', confirmInput.value);
            
            const res = await fetch(`backup_process.php?action=restore&file=${restoreSelect.value}`, {
                method: 'POST',
                body: formData
            });
            const data = await res.json();
            
            if (data.ok) {
                alert('Sistema restaurado con éxito. Serás redirigido al login.');
                location.href = '../login.php';
            } else {
                throw new Error(data.error);
            }
        } catch (err) {
            alert('Error: ' + err.message);
            btnRestore.disabled = false;
            btnRestore.innerHTML = '<i class="bi bi-database-fill-up me-2"></i>RESTAURAR TODO AHORA';
        }
    });
});

async function loadBackups() {
    const list = document.getElementById('backupList');
    const select = document.getElementById('restoreFile');
    
    try {
        const res = await fetch('backup_process.php?action=list');
        const data = await res.json();
        
        if (!data.ok) throw new Error(data.error);

        select.innerHTML = '<option value="">-- Selecciona un archivo --</option>';
        list.innerHTML = '';

        if (data.backups.length === 0) {
            list.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-muted">No hay copias disponibles.</td></tr>';
            return;
        }

        data.backups.forEach(b => {
            // Tabla
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td class="fw-bold">${b.date}</td>
                <td><code style="font-size:.7rem">${b.name}</code></td>
                <td>${b.size}</td>
                <td class="text-end">
                    <div class="btn-group btn-group-sm">
                        <a href="backup_process.php?action=download&file=${b.name}" class="btn btn-outline-secondary" title="Descargar" download><i class="bi bi-download"></i></a>
                        <button class="btn btn-outline-danger" onclick="deleteBackup('${b.name}')" title="Eliminar"><i class="bi bi-trash"></i></button>
                    </div>
                </td>
            `;
            list.appendChild(tr);

            // Select para restaurar
            const opt = document.createElement('option');
            opt.value = b.name;
            opt.textContent = `${b.date} (${b.size})`;
            select.appendChild(opt);
        });
    } catch (err) {
        list.innerHTML = `<tr><td colspan="4" class="text-center py-4 text-danger">Error: ${err.message}</td></tr>`;
    }
}

async function createBackup() {
    const btn = document.getElementById('btnNewBackup');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Generando...';

    try {
        const res = await fetch('backup_process.php?action=export');
        const data = await res.json();
        if (data.ok) {
            alert('Backup generado: ' + data.filename);
            loadBackups();
        } else throw new Error(data.error);
    } catch (err) {
        alert('Error: ' + err.message);
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="bi bi-cloud-arrow-up-fill me-2"></i>GENERAR BACKUP AHORA';
    }
}

async function deleteBackup(file) {
    if (!confirm('¿Eliminar esta copia de seguridad para siempre?')) return;
    try {
        const res = await fetch(`backup_process.php?action=delete&file=${file}`);
        const data = await res.json();
        if (data.ok) loadBackups();
        else throw new Error(data.error);
    } catch (err) {
        alert('Error: ' + err.message);
    }
}

async function saveAutoConfig() {
    const val = document.getElementById('checkAuto').checked ? '1' : '0';
    fetch('backup_process.php?action=set_config&key=backup_auto&val=' + val);
}

async function saveLimit(key, val) {
    val = Math.max(1, parseInt(val) || 1);
    await fetch(`backup_process.php?action=set_config&key=${key}&val=${val}`);
}
</script>

<style>
.btn-white { background: #fff; color: var(--verde); border: none; }
.btn-white:hover { background: #f8f9fa; color: #000; }
.bg-gold { background-color: var(--gold) !important; color: #000; }
</style>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

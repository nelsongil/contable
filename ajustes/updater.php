<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/updater.php';

// Forzar comprobación si se solicita
if (get('force_check') === '1') {
    setConfig('last_update_check', 0);
    checkForUpdates();
    redirect('updater.php');
}

$pageTitle = 'Actualización del sistema';
require_once __DIR__ . '/../includes/header.php';

$update = $_SESSION['update_available'] ?? null;
$currentVer = defined('APP_VERSION') ? APP_VERSION : '1.0';
?>

<div class="topbar">
  <h1><i class="bi bi-arrow-repeat me-2"></i>Actualización del sistema</h1>
</div>

<div class="row g-4" style="max-width: 900px;">
  <div class="col-md-7">
    <div class="card h-100">
      <div class="card-header"><i class="bi bi-info-circle me-2"></i>Estado actual</div>
      <div class="card-body p-4">
        <div class="d-flex align-items-center justify-content-between mb-4 gap-2">
          <div class="text-center p-3 border rounded bg-light flex-grow-1">
            <div class="text-muted small text-uppercase fw-bold" style="font-size: .65rem;">Tu versión</div>
            <div class="h3 mb-0 fw-bold">v<?= e($currentVer) ?></div>
          </div>
          <div class="px-2"><i class="bi bi-arrow-right h3 text-muted mb-0"></i></div>
          <div class="text-center p-3 border rounded flex-grow-1 <?= $update ? 'border-success bg-success-subtle' : 'bg-light' ?>">
            <div class="text-muted small text-uppercase fw-bold" style="font-size: .65rem;">Última disponible</div>
            <div class="h3 mb-0 fw-bold"><?= $update ? e($update['version']) : 'v' . e($currentVer) ?></div>
          </div>
        </div>

        <?php if ($update): ?>
          <div class="alert alert-success border-success bg-success-subtle d-flex gap-3">
            <i class="bi bi-stars h4 mb-0"></i>
            <div>
                <h6 class="alert-heading fw-bold mb-1">Nueva versión disponible</h6>
                <p class="small mb-0">La versión <?= e($update['version']) ?> incluye mejoras de seguridad y nuevas funcionalidades.</p>
            </div>
          </div>

          <h6 class="fw-bold mt-4 mb-2"><i class="bi bi-journal-text me-1"></i>Novedades en esta versión:</h6>
          <div class="bg-light p-3 border rounded mb-4" style="max-height: 250px; overflow-y: auto; font-size: .88rem; line-height: 1.6;">
            <?= nl2br(e($update['notes'])) ?>
          </div>

          <h6 class="fw-bold mb-3">Pasos de la actualización:</h6>
          <div class="update-steps small shadow-sm border rounded">
            <div class="p-2 border-bottom d-flex align-items-center gap-3">
                <span class="badge rounded-circle bg-success-subtle text-success p-2"><i class="bi bi-database-down"></i></span>
                <span>Copia de seguridad automática de la base de datos</span>
            </div>
            <div class="p-2 border-bottom d-flex align-items-center gap-3">
                <span class="badge rounded-circle bg-success-subtle text-success p-2"><i class="bi bi-cloud-download"></i></span>
                <span>Descarga de archivos desde GitHub</span>
            </div>
            <div class="p-2 border-bottom d-flex align-items-center gap-3">
                <span class="badge rounded-circle bg-success-subtle text-success p-2"><i class="bi bi-shield-check"></i></span>
                <span>Verificación de integridad e hilos de seguridad</span>
            </div>
            <div class="p-2 d-flex align-items-center gap-3">
                <span class="badge rounded-circle bg-success-subtle text-success p-2"><i class="bi bi-hdd-network"></i></span>
                <span>Aplicación de cambios y migraciones SQL</span>
            </div>
          </div>
        <?php else: ?>
          <div class="text-center py-5">
            <div class="bg-success-subtle text-success rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                <i class="bi bi-check-lg" style="font-size: 3rem;"></i>
            </div>
            <h5 class="fw-bold">Tu sistema está actualizado</h5>
            <p class="text-muted small">No se han detectado nuevas versiones en el servidor de nelsongil/contable.</p>
            <a href="updater.php?force_check=1" class="btn btn-outline-secondary btn-sm mt-2">
                <i class="bi bi-arrow-clockwise me-1"></i>Buscar de nuevo ahora
            </a>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="col-md-5">
    <?php if ($update): ?>
      <div class="card border-warning shadow-sm mb-4">
        <div class="card-header bg-warning border-warning text-dark py-3 fw-bold">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>ADVERTENCIAS PREVIAS
        </div>
        <div class="card-body p-4">
          <p class="small text-muted mb-4">Aunque el sistema realiza un backup automático, te recomendamos encarecidamente descargar uno manualmente antes de empezar.</p>
          
          <a href="/libros/exportar.php?backup=1" class="btn btn-outline-dark w-100 mb-4 py-2">
            <i class="bi bi-download me-2"></i>Descargar backup SQL ahora
          </a>
          
          <div class="form-check mb-4 p-3 border rounded bg-light">
            <input class="form-check-input ms-0 me-3" type="checkbox" id="checkTerms">
            <label class="form-check-label small fw-bold" for="checkTerms" style="cursor: pointer;">
                Entiendo que este es un proceso crítico y he realizado una copia de seguridad.
            </label>
          </div>

          <button class="btn btn-gold w-100 py-3 fw-bold shadow-sm" id="btnUpdate" disabled>
            <i class="bi bi-rocket-takeoff-fill me-2"></i>COMENZAR ACTUALIZACIÓN
          </button>
        </div>
      </div>
    <?php endif; ?>

    <div class="card">
      <div class="card-header bg-light text-dark"><i class="bi bi-clock-history me-2"></i>Versiones instaladas</div>
      <div class="card-body p-0">
        <div class="list-group list-group-flush small">
          <div class="list-group-item d-flex justify-content-between align-items-center p-3">
            <div>
                <div class="fw-bold">v<?= e($currentVer) ?> (Actual)</div>
                <div class="text-muted" style="font-size: .75rem;">Versión estable operativa</div>
            </div>
            <span class="badge bg-success rounded-pill">Activa</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal Progreso de Actualización -->
<div class="modal fade" id="modalUpdate" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 shadow-lg" style="border-radius: 20px; overflow: hidden;">
      <div class="modal-body p-5 text-center">
        <!-- Pantalla: Cargando -->
        <div id="u_loading">
            <div class="spinner-border text-gold mb-4" style="width: 4rem; height: 4rem; border-width: .3em;" role="status"></div>
            <h4 class="fw-bold mb-2">Actualizando sistema...</h4>
            <p class="text-muted mb-4" id="u_status">Preparando para iniciar...</p>
            
            <div class="progress mb-3" style="height: 12px; border-radius: 10px; background: #eee;">
              <div class="progress-bar progress-bar-striped progress-bar-animated bg-gold" id="u_progress" style="width: 0%"></div>
            </div>
            <div class="text-muted" style="font-size: .7rem;">Por favor, no cierres esta pestaña ni apagues el servidor.</div>
        </div>

        <!-- Pantalla: Éxito -->
        <div id="u_success" class="d-none">
            <div class="bg-success-subtle text-success rounded-circle d-inline-flex align-items-center justify-content-center mb-4" style="width: 90px; height: 90px;">
                <i class="bi bi-check-lg" style="font-size: 4rem;"></i>
            </div>
            <h3 class="fw-bold mb-2">¡Actualización exitosa!</h3>
            <p class="text-muted mb-4">Libro Contable se ha actualizado correctamente a la versión <strong id="u_finalVer"></strong>.</p>
            <button class="btn btn-success w-100 py-3 fw-bold rounded-pill shadow-sm" onclick="location.href='/index.php'">
                FINALIZAR Y VOLVER
            </button>
        </div>

        <!-- Pantalla: Error -->
        <div id="u_error" class="d-none">
            <div class="bg-danger-subtle text-danger rounded-circle d-inline-flex align-items-center justify-content-center mb-4" style="width: 90px; height: 90px;">
                <i class="bi bi-x-lg" style="font-size: 4rem;"></i>
            </div>
            <h4 class="fw-bold mb-2 text-danger">Error crítico</h4>
            <p class="text-muted mb-4" id="u_errorMsg"></p>
            <div class="alert alert-warning small text-start">
                Tu sistema puede haber quedado en un estado inconsistente. Si la aplicación no carga, restaura el último backup manual.
            </div>
            <button class="btn btn-secondary w-100 py-2 rounded-pill mt-3" data-bs-dismiss="modal">Cerrar</button>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
document.getElementById('checkTerms')?.addEventListener('change', function() {
    document.getElementById('btnUpdate').disabled = !this.checked;
});

document.getElementById('btnUpdate')?.addEventListener('click', function() {
    new bootstrap.Modal(document.getElementById('modalUpdate')).show();
    runUpdateSteps();
});

async function runUpdateSteps() {
    const status = document.getElementById('u_status');
    const progress = document.getElementById('u_progress');
    
    const updateProgress = (text, pct) => {
        status.textContent = text;
        progress.style.width = pct + '%';
    };

    try {
        const steps = [
            { id: 'backup',   label: 'Realizando copia de seguridad automática...', pct: 20 },
            { id: 'download', label: 'Descargando nueva versión desde GitHub...',   pct: 50 },
            { id: 'prepare',  label: 'Extrayendo y verificando paquetes...',        pct: 70 },
            { id: 'install',  label: 'Actualizando archivos y base de datos...',    pct: 90 },
            { id: 'finalize', label: 'Finalizando y limpiando temporales...',       pct: 100 }
        ];

        for (const step of steps) {
            updateProgress(step.label, step.pct);
            
            // Retardo artificial pequeño para que se vea el paso
            await new Promise(r => setTimeout(r, 600));

            const res = await fetch('update_process.php?step=' + step.id);
            const data = await res.json();

            if (!data.ok) {
                throw new Error(data.error || 'Fallo en el paso: ' + step.label);
            }

            if (step.id === 'finalize') {
                document.getElementById('u_finalVer').textContent = data.version;
            }
        }

        // Mostrar éxito
        document.getElementById('u_loading').classList.add('d-none');
        document.getElementById('u_success').classList.remove('d-none');

    } catch (err) {
        document.getElementById('u_loading').classList.add('d-none');
        document.getElementById('u_error').classList.remove('d-none');
        document.getElementById('u_errorMsg').textContent = err.message;
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

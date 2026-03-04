</main>

<!-- ═══ CONFIRM MODAL (global) ═══ -->
<div class="modal fade" id="bsConfirmModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content border-0 shadow">
      <div class="modal-body text-center px-4 pt-4 pb-2">
        <div class="rounded-circle d-inline-flex align-items-center justify-content-center mb-3" id="bsConfirmIcon"
             style="width:56px;height:56px;background:#fff3cd;">
          <i class="bi bi-question-lg text-warning" style="font-size:1.8rem;"></i>
        </div>
        <p class="fw-semibold mb-0" id="bsConfirmMsg"></p>
      </div>
      <div class="modal-footer border-0 justify-content-center gap-2 pb-4">
        <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn px-4" id="bsConfirmOk">Confirmar</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
<link  rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css">

<script>
// ─── Confirm Modal ───
function bsConfirm(msg, onConfirm, opts = {}) {
    document.getElementById('bsConfirmMsg').textContent = msg;
    const okBtn = document.getElementById('bsConfirmOk');
    okBtn.textContent = opts.okText || 'Confirmar';
    okBtn.className = 'btn px-4 ' + (opts.okClass || 'btn-danger');
    const icon = document.getElementById('bsConfirmIcon');
    icon.style.background = opts.danger === false ? '#d1e7dd' : '#fff3cd';
    icon.querySelector('i').className = opts.danger === false ? 'bi bi-check-lg text-success' : 'bi bi-question-lg text-warning';
    icon.querySelector('i').style.fontSize = '1.8rem';
    const modal = new bootstrap.Modal(document.getElementById('bsConfirmModal'));
    const handler = () => { modal.hide(); onConfirm(); };
    okBtn.addEventListener('click', handler, { once: true });
    modal.show();
}

// Delegación para enlaces con data-confirm
document.addEventListener('click', function(e) {
    const el = e.target.closest('a[data-confirm]');
    if (!el) return;
    e.preventDefault();
    bsConfirm(el.dataset.confirm, () => location.href = el.getAttribute('href'));
}, false);

// Delegación para formularios con data-confirm
document.addEventListener('submit', function(e) {
    const form = e.target;
    if (!form.dataset.confirm || form._bsConfirmed) return;
    e.preventDefault();
    bsConfirm(form.dataset.confirm, () => { form._bsConfirmed = true; form.submit(); });
}, false);

// ─── Inicialización de Bootstrap ───
document.addEventListener('DOMContentLoaded', () => {
    // Tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(t => new bootstrap.Tooltip(t));

    // Popovers (Logout)
    const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    popoverTriggerList.map(p => new bootstrap.Popover(p));
});

function closeLogoutPopover() {
    const btn = document.getElementById('logoutBtn');
    const popover = bootstrap.Popover.getInstance(btn);
    if (popover) popover.hide();
}

// ─── Gestión de Inactividad ───
let inactivityTimer;
let warningTimer;
const INACTIVITY_LIMIT = 60 * 60 * 1000; // 60 min
const WARNING_LIMIT = 55 * 60 * 1000;    // 55 min
const WARNING_BANNER = document.getElementById('session-warning');
const TIMER_DISPLAY = document.getElementById('session-timer');

function resetInactivity() {
    clearTimeout(inactivityTimer);
    clearTimeout(warningTimer);
    WARNING_BANNER.style.display = 'none';
    
    // Iniciar avisos
    warningTimer = setTimeout(showWarning, WARNING_LIMIT);
    inactivityTimer = setTimeout(() => window.location.href = '/login.php?logout=1&reason=timeout', INACTIVITY_LIMIT);
}

function showWarning() {
    WARNING_BANNER.style.display = 'flex';
    let timeLeft = 300; // 5 minutos
    const interval = setInterval(() => {
        timeLeft--;
        const mins = Math.floor(timeLeft / 60);
        const secs = timeLeft % 60;
        TIMER_DISPLAY.textContent = `${mins}:${secs.toString().padStart(2, '0')}`;
        if (timeLeft <= 0) clearInterval(interval);
        if (WARNING_BANNER.style.display === 'none') clearInterval(interval);
    }, 1000);
}

// Resetear con cualquier interacción
['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart'].forEach(name => {
    document.addEventListener(name, resetInactivity, true);
});
resetInactivity();

// ─── Utilidades de Tablas ───
function filterTable(inputId, tableId) {
    const input = document.getElementById(inputId);
    const table = document.getElementById(tableId);
    if (!input || !table) return;

    input.addEventListener('keyup', function() {
        const filter = input.value.toLowerCase();
        const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
        for (let row of rows) {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(filter) ? '' : 'none';
        }
    });
}

function makeSortable(table) {
    const headers = table.querySelectorAll('th.sortable');
    headers.forEach((header, index) => {
        header.addEventListener('click', () => {
            const tbody = table.querySelector('tbody');
            const rows = Array.from(tbody.querySelectorAll('tr'));
            const isAsc = header.classList.contains('asc');
            
            rows.sort((a, b) => {
                const aVal = a.children[index].textContent.trim();
                const bVal = b.children[index].textContent.trim();
                return isAsc ? bVal.localeCompare(aVal, undefined, {numeric: true}) 
                             : aVal.localeCompare(bVal, undefined, {numeric: true});
            });
            
            headers.forEach(h => h.classList.remove('asc', 'desc'));
            header.classList.add(isAsc ? 'desc' : 'asc');
            tbody.append(...rows);
        });
    });
}
</script>
</body>
</html>

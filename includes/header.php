<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/updater.php';

// Comprobar actualizaciones
try { checkForUpdates(); } catch (Throwable $e) { /* No bloquear la carga si el updater falla */ }

// Manejar descarte de notificación
if (isset($_GET['dismiss_update'])) {
    dismissUpdateNotification();
    redirect($_SERVER['PHP_SELF']);
}

$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<!-- Anti-flash: aplica el tema antes del primer render -->
<script>
(function(){
  var t = localStorage.getItem('app-theme') || 'light';
  document.documentElement.setAttribute('data-bs-theme', t);
})();
</script>
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="description" content="Libro Contable — <?= e(getConfig('empresa_sociedad', EMPRESA_SOCIEDAD)) ?>">
<meta name="apple-mobile-web-app-capable" content="yes">
<link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='6' fill='%234338CA'/%3E%3Cpath d='M9 7h14v18H9z' fill='%23F59E0B'/%3E%3Cpath d='M9 10h14M9 14h14M9 18h14M9 22h14' stroke='%234338CA' stroke-width='1.5'/%3E%3C/svg%3E">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,300..800;1,14..32,400&display=swap" rel="stylesheet">
<style>
/* ══════════════════════════════════════════════════
   LIBRO CONTABLE — Design System v2
   Stack: Bootstrap 5.3 + Inter + CSS Custom Properties
   Paleta: Indigo profesional | Modo claro + oscuro
══════════════════════════════════════════════════ */

:root {
  /* ── Brand (personalizables vía ajustes/tema.php) ── */
  --verde:   #312E81;   /* indigo-900 */
  --verde-m: #4338CA;   /* indigo-700 */
  --verde-a: #6366F1;   /* indigo-500 */
  --gold:    #F59E0B;   /* amber-500  */

  /* ── Layout ── */
  --sidebar: 240px;

  /* ── Superficies (modo claro) ── */
  --bg:        #F1F5F9;
  --surface:   #FFFFFF;
  --surface-2: #F8FAFC;
  --border:    #E2E8F0;

  /* ── Texto (modo claro) ── */
  --text:    #0F172A;
  --text-2:  #475569;
  --text-3:  #94A3B8;

  /* ── Estado ── */
  --clr-success: #059669;
  --clr-danger:  #DC2626;
  --clr-warning: #D97706;
  --clr-info:    #2563EB;

  /* ── Sidebar (siempre oscuro) ── */
  --sb-text:      rgba(199,210,254,.85);
  --sb-muted:     rgba(129,140,248,.55);
  --sb-active:    #fff;
  --sb-active-bg: rgba(99,102,241,.2);
  --sb-hover-bg:  rgba(255,255,255,.06);
  --sb-border:    rgba(255,255,255,.08);
}

/* ── Base ── */
body {
  font-family: 'Inter', sans-serif;
  background: var(--bg); color: var(--text);
  transition: background-color .25s ease, color .25s ease;
}

/* ── Accesibilidad: foco visible ── */
:focus-visible {
  outline: 2px solid var(--verde-a);
  outline-offset: 2px;
  border-radius: 4px;
}
button:focus:not(:focus-visible), a:focus:not(:focus-visible) { outline: none; }

/* ── Cursor en elementos interactivos ── */
a, button, .btn, select, label[for], [role="button"], .fact-row,
.sortable, .nav-link, .logout-btn, .theme-toggle { cursor: pointer; }

/* ── Movimiento reducido ── */
@media (prefers-reduced-motion: reduce) {
  *, *::before, *::after {
    animation-duration: .01ms !important;
    animation-iteration-count: 1 !important;
    transition-duration: .01ms !important;
  }
}

/* ══ SIDEBAR ══ */
.sidebar {
  width: var(--sidebar); height: 100vh;
  background: var(--verde);
  position: fixed; top: 0; left: 0; z-index: 100;
  display: flex; flex-direction: column;
  overflow-y: auto; overflow-x: hidden;
}
.sidebar::-webkit-scrollbar { width: 3px; }
.sidebar::-webkit-scrollbar-thumb { background: rgba(255,255,255,.12); border-radius: 2px; }

/* ── Logo banner (arriba del sidebar) ── */
.sidebar-logo-img {
  display: block; padding: .85rem 1rem .7rem;
  border-bottom: 1px solid var(--sb-border);
  text-align: center; text-decoration: none;
  background: rgba(0,0,0,.12);
}
.sidebar-logo-img img {
  max-height: 48px; width: 100%; max-width: 180px;
  object-fit: contain;
  filter: brightness(1.1) drop-shadow(0 1px 3px rgba(0,0,0,.25));
}

.sidebar-logo {
  display: block; text-decoration: none;
  padding: .95rem 1rem .8rem;
  border-bottom: 1px solid var(--sb-border);
  transition: background .15s;
}
.sidebar-logo:hover { background: var(--sb-hover-bg); }
.sidebar-logo .company { color: var(--gold); font-weight: 700; font-size: .88rem; line-height: 1.3; }
.sidebar-logo .person  { color: var(--sb-text); font-size: .74rem; margin-top: .12rem; }
.sidebar-logo .cif     { color: var(--sb-muted); font-size: .69rem; }

.nav-section {
  padding: .85rem .9rem .2rem;
  color: var(--sb-muted); font-size: .63rem;
  text-transform: uppercase; letter-spacing: .1em; font-weight: 600;
}
.sidebar .nav-link {
  color: var(--sb-text); padding: .44rem .9rem;
  font-size: .82rem; font-weight: 400;
  border-radius: 7px; margin: 1px 7px;
  display: flex; align-items: center; gap: .5rem;
  transition: background .12s, color .12s;
  position: relative;
}
.sidebar .nav-link:hover          { color: var(--sb-active); background: var(--sb-hover-bg); }
.sidebar .nav-link.active         { color: var(--sb-active); background: var(--sb-active-bg); font-weight: 600; }
.sidebar .nav-link.active::before {
  content: ''; position: absolute; left: -7px; top: 22%; bottom: 22%;
  width: 3px; border-radius: 0 3px 3px 0; background: var(--gold);
}
.sidebar .nav-link i { font-size: .93rem; width: 17px; text-align: center; flex-shrink: 0; }

.sidebar-bottom {
  margin-top: auto; padding: .85rem .9rem;
  border-top: 1px solid var(--sb-border);
}
.anio-badge {
  background: var(--gold); color: var(--verde);
  font-weight: 700; font-size: .76rem;
  padding: .2rem .65rem; border-radius: 20px;
  display: inline-block;
}
.logout-btn {
  width: 100%; background: rgba(255,255,255,.05);
  color: var(--sb-text); border: 1px solid var(--sb-border);
  border-radius: 8px; padding: .52rem .85rem;
  font-size: .82rem; transition: all .18s;
  display: flex; align-items: center; gap: .5rem;
}
.logout-btn:hover { background: rgba(239,68,68,.14); color: #fca5a5; border-color: rgba(239,68,68,.28); }

/* Toggle claro/oscuro */
.theme-toggle {
  background: none; border: none;
  color: var(--sb-muted); font-size: 1rem;
  width: 32px; height: 32px; border-radius: 7px;
  display: flex; align-items: center; justify-content: center;
  cursor: pointer; transition: background .15s, color .15s;
  flex-shrink: 0;
}
.theme-toggle:hover { background: var(--sb-hover-bg); color: var(--gold); }

/* ══ MAIN ══ */
.main { margin-left: var(--sidebar); padding: 1.75rem 2rem; min-height: 100vh; }

/* ══ TOPBAR ══ */
.topbar {
  background: var(--surface);
  border: 1px solid var(--border);
  border-radius: 14px; padding: .85rem 1.4rem;
  margin-bottom: 1.5rem;
  display: flex; align-items: center; justify-content: space-between;
  box-shadow: 0 1px 3px rgba(0,0,0,.04), 0 4px 16px rgba(0,0,0,.05);
  position: relative; z-index: 10;
  flex-wrap: wrap; gap: .65rem;
}
.topbar h1 { font-size: 1.18rem; font-weight: 700; color: var(--text); margin: 0; }

/* ══ CARDS ══ */
.card {
  border: 1px solid var(--border); border-radius: 14px;
  box-shadow: 0 1px 3px rgba(0,0,0,.04), 0 4px 16px rgba(0,0,0,.05);
  background: var(--surface);
  transition: box-shadow .25s ease;
}
.card:hover { box-shadow: 0 4px 8px rgba(0,0,0,.05), 0 12px 32px rgba(0,0,0,.08); }
.card-header {
  background: var(--verde-m); color: #fff;
  border-radius: 13px 13px 0 0 !important;
  font-weight: 600; font-size: .88rem;
  padding: .72rem 1.2rem;
  border-bottom: 2px solid var(--gold);
}
.card-body { color: var(--text); }

/* ══ KPI (global, sobrescrito por páginas) ══ */
.kpi {
  background: var(--surface); border-radius: 12px;
  padding: 1.15rem 1.35rem; border: 1px solid var(--border);
}
.kpi .label { font-size: .72rem; color: var(--text-3); text-transform: uppercase; letter-spacing: .07em; }
.kpi .value { font-size: 1.5rem; font-weight: 700; color: var(--verde-a); }
.kpi-gold .value { color: var(--gold); }
.kpi-red  .value { color: var(--clr-danger); }
.kpi .sub { font-size: .75rem; color: var(--text-3); }

/* ══ TABLAS ══ */
.table { font-size: .86rem; }
.table thead th {
  background: var(--verde); color: #fff;
  font-weight: 600; font-size: .74rem;
  text-transform: uppercase; letter-spacing: .06em;
  border: none; padding: .7rem 1rem;
}
/* dark mode table headers — sobreescrito también abajo con !important */
.table tbody tr { transition: background .2s ease; }
.table tbody tr:hover { background: rgba(99,102,241,.06); }
.table td { vertical-align: middle; padding: .65rem 1rem; border-color: var(--border); color: var(--text); }
.table .actions { opacity: 0; transition: opacity .2s ease; white-space: nowrap; }
.table tr:hover .actions { opacity: 1; }
.sortable { position: relative; user-select: none; }
.sortable:after { content: '↕'; position: absolute; right: 8px; opacity: .28; font-size: .78em; }

/* ══ BADGES ══ */
.badge-trim { background: var(--verde-a); color: #fff; font-size: .7rem; padding: .28em .58em; border-radius: 6px; }

/* ══ BOTONES ══ */
.btn { transition: all .2s ease; }
.btn-primary              { background: var(--verde-a); border-color: var(--verde-a); color: #fff; box-shadow: 0 2px 8px rgba(99,102,241,.25); }
.btn-primary:hover        { background: var(--verde-m); border-color: var(--verde-m); color: #fff; box-shadow: 0 4px 14px rgba(99,102,241,.35); transform: translateY(-1px); }
.btn-outline-primary      { color: var(--verde-a); border-color: var(--verde-a); }
.btn-outline-primary:hover{ background: var(--verde-a); border-color: var(--verde-a); color: #fff; box-shadow: 0 2px 8px rgba(99,102,241,.2); }
.btn-gold                 { background: var(--gold); border-color: var(--gold); color: #1e1b4b; font-weight: 600; box-shadow: 0 2px 8px rgba(245,158,11,.28); }
.btn-gold:hover           { filter: brightness(.93); color: #1e1b4b; box-shadow: 0 4px 14px rgba(245,158,11,.38); transform: translateY(-1px); }

/* ══ FORMULARIOS ══ */
.form-label { font-size: .8rem; font-weight: 600; color: var(--text-2); }
.form-control, .form-select {
  font-size: .87rem; border-radius: 9px;
  background: var(--surface); color: var(--text); border-color: var(--border);
  transition: border-color .2s ease, box-shadow .2s ease;
}
.form-control:focus, .form-select:focus {
  border-color: var(--verde-a); background: var(--surface); color: var(--text);
  box-shadow: 0 0 0 3px rgba(99,102,241,.14);
  outline: none;
}
.form-control::placeholder { color: var(--text-3); }
.form-floating > .form-control:focus ~ label,
.form-floating > .form-control:not(:placeholder-shown) ~ label { color: var(--verde-a); }

/* ── Líneas de factura ── */
.lineas-table input {
  border: none; background: transparent; width: 100%;
  font-size: .85rem; color: var(--text);
}
.lineas-table input:focus { outline: 1px solid var(--verde-a); border-radius: 4px; background: var(--surface-2); }
.lineas-table tr:hover .btn-remove { opacity: 1; }
.btn-remove { opacity: .3; transition: opacity .2s; }

/* ══ SESSION WARNING ══ */
#session-warning {
  position: fixed; top: 0; left: var(--sidebar); right: 0; z-index: 1050;
  background: #fef9c3; border-bottom: 1px solid #fde047; color: #713f12;
  padding: .7rem 1.2rem; display: none;
  align-items: center; justify-content: space-between;
}

/* ══ ANIMACIONES ══ */
@keyframes fadeInUp {
  from { opacity: 0; transform: translateY(14px); }
  to   { opacity: 1; transform: translateY(0); }
}
.fade-in-up { animation: fadeInUp .38s ease both; }
.delay-1 { animation-delay: .07s; }
.delay-2 { animation-delay: .13s; }
.delay-3 { animation-delay: .19s; }
.delay-4 { animation-delay: .25s; }

/* ══ HAMBURGER ══ */
#sidebarToggle {
  display: none; position: fixed; top: .75rem; left: .75rem; z-index: 1060;
  background: var(--verde); color: #fff; border: none; border-radius: 8px;
  width: 40px; height: 40px; align-items: center; justify-content: center;
  font-size: 1.3rem; box-shadow: 0 2px 8px rgba(0,0,0,.25); cursor: pointer;
  transition: background .15s;
}
#sidebarToggle:hover { background: var(--verde-m); }
#sidebarBackdrop {
  display: none; position: fixed; inset: 0;
  background: rgba(0,0,0,.5); z-index: 1039;
}
#sidebarBackdrop.show { display: block; }

/* ══ RESPONSIVE ══ */
@media (max-width: 767.98px) {
  #sidebarToggle { display: flex; }
  .sidebar { left: calc(-1 * var(--sidebar)); transition: left .25s ease; z-index: 1040; }
  .sidebar.sidebar-open { left: 0; }
  .main { margin-left: 0 !important; padding: 1rem; padding-top: 3.5rem; }
  #session-warning { left: 0; }
  .topbar { flex-wrap: wrap; gap: .5rem; }
  .topbar h1 { font-size: 1.05rem; }
  .topbar > div:last-child { width: 100%; }
  .topbar .form-control-sm { width: auto !important; flex: 1 1 100px; min-width: 80px; }
  .topbar .form-select-sm  { width: auto !important; flex: 0 0 80px; }
  .card-body.p-0 { overflow-x: auto; -webkit-overflow-scrolling: touch; }
  .kpi .value { font-size: 1.25rem; }
  .d-none-mobile { display: none !important; }
  .form-control, .form-select { font-size: 16px; }
  .btn:not(.btn-sm) { min-height: 44px; }
}
</style>
<?php
// ─── Contador de Notificaciones (Borradores) ───
$cntBorradores = (int)getDB()->query("SELECT COUNT(*) FROM facturas_emitidas WHERE estado='borrador'")->fetchColumn();
?>
<?= getThemeCSS() ?>
<style>
/* ══════════════════════════════════════════════════
   DARK MODE — debe ir DESPUÉS de getThemeCSS() porque
   ese bloque usa !important en :root (especificidad 0-1-0).
   Usamos html[data-bs-theme="dark"] (0-1-1) + !important
   para ganar la cascada en todos los casos.
══════════════════════════════════════════════════ */
html[data-bs-theme="dark"] {
  --bg:        #0F172A !important;
  --surface:   #1E293B !important;
  --surface-2: #0F172A !important;
  --border:    #334155 !important;
  --text:      #F1F5F9 !important;
  --text-2:    #94A3B8 !important;
  --text-3:    #64748B !important;
}
/* Forzar superficies oscuras en utilidades Bootstrap */
html[data-bs-theme="dark"] .bg-white,
html[data-bs-theme="dark"] .bg-light    { background-color: var(--surface) !important; }
html[data-bs-theme="dark"] .card,
html[data-bs-theme="dark"] .card-footer,
html[data-bs-theme="dark"] .card-body   { background-color: var(--surface) !important; color: var(--text) !important; }
html[data-bs-theme="dark"] .topbar      { background: var(--surface) !important; }
html[data-bs-theme="dark"] .table thead th { background: var(--verde-m) !important; }
html[data-bs-theme="dark"] .table td   { border-color: var(--border) !important; color: var(--text) !important; }
html[data-bs-theme="dark"] .table tfoot tr { background: var(--surface-2) !important; }
html[data-bs-theme="dark"] .kpi-card,
html[data-bs-theme="dark"] .kpi,
html[data-bs-theme="dark"] .section-card,
html[data-bs-theme="dark"] .chart-card  { background: var(--surface) !important; border-color: var(--border) !important; }
html[data-bs-theme="dark"] .modal-content { background-color: var(--surface) !important; }
html[data-bs-theme="dark"] .dropdown-menu { background-color: var(--surface) !important; border-color: var(--border) !important; }
html[data-bs-theme="dark"] .dropdown-item { color: var(--text) !important; }
html[data-bs-theme="dark"] .dropdown-item:hover { background-color: var(--surface-2) !important; }
html[data-bs-theme="dark"] .input-group-text { background-color: var(--surface-2) !important; border-color: var(--border) !important; color: var(--text-2) !important; }
/* Botón gold en dark mode — Bootstrap 5.3 sobreescribe btn vars */
html[data-bs-theme="dark"] .btn-gold,
html[data-bs-theme="dark"] .btn-gold:hover,
html[data-bs-theme="dark"] .btn-gold:focus,
html[data-bs-theme="dark"] .btn-gold:active { background: var(--gold) !important; border-color: var(--gold) !important; color: #1e1b4b !important; }
html[data-bs-theme="dark"] .btn-primary,
html[data-bs-theme="dark"] .btn-primary:hover { background: var(--verde-a) !important; border-color: var(--verde-a) !important; color: #fff !important; }
html[data-bs-theme="dark"] .btn-outline-secondary { color: var(--text-2) !important; border-color: var(--border) !important; }
html[data-bs-theme="dark"] .btn-outline-secondary:hover { background: var(--surface-2) !important; color: var(--text) !important; }
</style>
</head>
<body>

<!-- Sidebar backdrop (móvil) -->
<div id="sidebarBackdrop" onclick="closeSidebar()"></div>

<!-- Botón hamburguesa (móvil) -->
<button id="sidebarToggle" onclick="toggleSidebar()" aria-label="Abrir menú">
  <i class="bi bi-list"></i>
</button>

<?php
// Banner de actualización
$update = $_SESSION['update_available'] ?? null;
$dismissed = $_SESSION['update_dismissed_version'] ?? '';

if ($update && $update['version'] !== $dismissed): 
?>
<div class="alert alert-info border-0 rounded-0 m-0 d-flex align-items-center justify-content-between py-2 px-4" style="background: var(--verde-a); color: #fff; font-size: .88rem; z-index: 1100; position: relative;">
    <div>
        <i class="bi bi-arrow-repeat me-2"></i> 
        <strong>Nueva versión <?= e($update['version']) ?> disponible</strong> 
        <span class="opacity-75 ms-1">— Mejora la seguridad y funciones de tu Libro Contable.</span>
    </div>
    <div class="d-flex align-items-center gap-3">
        <a href="#" class="text-white text-decoration-underline" data-bs-toggle="modal" data-bs-target="#modalChangelog" style="font-weight: 500;">Ver novedades</a>
        <a href="/ajustes/updater.php" class="btn btn-sm btn-gold py-0 px-3" style="font-size: .75rem;">Actualizar ahora</a>
        <a href="?dismiss_update=1" class="text-white opacity-50 hover-opacity-100"><i class="bi bi-x-lg"></i></a>
    </div>
</div>

<!-- Modal Changelog -->
<div class="modal fade" id="modalChangelog" tabindex="-1">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Novedades <?= e($update['version']) ?></h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body" style="font-size: .9rem; max-height: 400px; overflow-y: auto;">
        <?= nl2br(e($update['notes'])) ?>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
        <a href="/ajustes/updater.php" class="btn btn-primary">Ir a actualizar</a>
      </div>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- ═══ SIDEBAR ═══ -->
<aside class="sidebar">
  <?php $sbLogo = getConfig('invoice_logo', ''); if ($sbLogo): ?>
  <a href="/" class="sidebar-logo-img">
    <img src="<?= e($sbLogo) ?>" alt="<?= e(getConfig('empresa_sociedad', EMPRESA_SOCIEDAD)) ?>">
  </a>
  <?php endif; ?>
  <a href="/" class="sidebar-logo">
    <div class="company"><?= e(getConfig('empresa_sociedad', EMPRESA_SOCIEDAD)) ?></div>
    <div class="person"><?= e(getConfig('empresa_nombre', EMPRESA_NOMBRE)) ?></div>
    <div class="cif">CIF: <?= e(getConfig('empresa_cif', EMPRESA_CIF)) ?></div>
  </a>

  <a href="/" class="nav-link <?= $_SERVER['PHP_SELF'] === '/index.php' ? 'active' : '' ?>">
    <i class="bi bi-speedometer2"></i> Dashboard
  </a>

  <div class="nav-section">Facturación</div>
  <a href="/facturas/" class="nav-link <?= str_contains($_SERVER['REQUEST_URI'],'/facturas/') && !str_contains($_SERVER['REQUEST_URI'],'/facturas/nueva') ? 'active' : '' ?>">
    <i class="bi bi-receipt"></i>
    <span>Facturas emitidas</span>
    <?php if ($cntBorradores > 0): ?>
    <span class="position-absolute translate-middle p-1 bg-danger border border-light rounded-circle" style="top: 15px; left: 25px;" title="<?= $cntBorradores ?> borradores pendientes" data-bs-toggle="tooltip"></span>
    <?php endif; ?>
  </a>
  <a href="/facturas/nueva.php" class="nav-link <?= str_contains($_SERVER['REQUEST_URI'],'/facturas/nueva') ? 'active' : '' ?>">
    <i class="bi bi-plus-circle"></i> Nueva factura
  </a>

  <div class="nav-section">Compras</div>
  <a href="/compras/" class="nav-link <?= str_contains($_SERVER['REQUEST_URI'],'/compras') && !str_contains($_SERVER['REQUEST_URI'],'/compras/nueva') ? 'active' : '' ?>">
    <i class="bi bi-bag"></i> Facturas recibidas
  </a>
  <a href="/compras/nueva.php" class="nav-link <?= str_contains($_SERVER['REQUEST_URI'],'/compras/nueva') ? 'active' : '' ?>">
    <i class="bi bi-plus-circle"></i> Nueva compra
  </a>

  <div class="nav-section">Agenda</div>
  <a href="/clientes/" class="nav-link <?= str_contains($_SERVER['REQUEST_URI'],'/clientes') ? 'active' : '' ?>">
    <i class="bi bi-people"></i> Clientes
  </a>
  <a href="/proveedores/" class="nav-link <?= str_contains($_SERVER['REQUEST_URI'],'/proveedores') ? 'active' : '' ?>">
    <i class="bi bi-truck"></i> Proveedores
  </a>

  <?php if (getConfig('modulo_empleados', false)): ?>
  <div class="nav-section">Empleados</div>
  <a href="/empleados/" class="nav-link <?= str_contains($_SERVER['REQUEST_URI'],'/empleados/') && !str_contains($_SERVER['REQUEST_URI'],'retenciones') && !str_contains($_SERVER['REQUEST_URI'],'modelo111') ? 'active' : '' ?>">
    <i class="bi bi-person-badge"></i> Empleados
  </a>
  <a href="/empleados/retenciones.php" class="nav-link <?= str_contains($_SERVER['REQUEST_URI'],'/empleados/retenciones') ? 'active' : '' ?>">
    <i class="bi bi-calendar3"></i> Retenciones mensuales
  </a>
  <a href="/empleados/modelo111.php" class="nav-link <?= str_contains($_SERVER['REQUEST_URI'],'/empleados/modelo111') ? 'active' : '' ?>">
    <i class="bi bi-file-earmark-text"></i> Modelo 111
  </a>
  <?php endif; ?>

  <div class="nav-section">Informes</div>
  <a href="/libros/" class="nav-link <?= str_contains($_SERVER['REQUEST_URI'],'/libros') && !str_contains($_SERVER['REQUEST_URI'],'/libros/resumen') && !str_contains($_SERVER['REQUEST_URI'],'/libros/modelo347') ? 'active' : '' ?>">
    <i class="bi bi-journal-text"></i> Libros contables
  </a>
  <a href="/libros/resumen.php" class="nav-link <?= str_contains($_SERVER['REQUEST_URI'],'/libros/resumen') ? 'active' : '' ?>">
    <i class="bi bi-bar-chart"></i> Resumen fiscal
  </a>
  <a href="/libros/modelo347.php" class="nav-link <?= str_contains($_SERVER['REQUEST_URI'],'/libros/modelo347') ? 'active' : '' ?>">
    <i class="bi bi-people"></i> Modelo 347
  </a>

  <div class="nav-section">Configuración</div>
  <a href="/ajustes/empresa.php" class="nav-link <?= str_contains($_SERVER['REQUEST_URI'],'/ajustes/empresa') ? 'active' : '' ?>">
    <i class="bi bi-building"></i> Empresa y nº
  </a>
  <a href="/ajustes/plantilla.php" class="nav-link <?= str_contains($_SERVER['REQUEST_URI'],'/ajustes/plantilla') ? 'active' : '' ?>">
    <i class="bi bi-palette"></i> Plantilla factura
  </a>
  <a href="/ajustes/tema.php" class="nav-link <?= str_contains($_SERVER['REQUEST_URI'],'/ajustes/tema') ? 'active' : '' ?>">
    <i class="bi bi-brush"></i> Tema interfaz
  </a>
  <a href="/ajustes/empleados.php" class="nav-link <?= str_contains($_SERVER['REQUEST_URI'],'/ajustes/empleados') ? 'active' : '' ?>">
    <i class="bi bi-person-gear"></i> Módulo empleados
  </a>
  <a href="/ajustes/backup.php" class="nav-link <?= str_contains($_SERVER['REQUEST_URI'],'/ajustes/backup') ? 'active' : '' ?>">
    <i class="bi bi-database-check"></i> Copias de seguridad
  </a>
  <a href="/ajustes/updater.php" class="nav-link <?= str_contains($_SERVER['REQUEST_URI'],'/ajustes/updater') ? 'active' : '' ?>">
    <i class="bi bi-arrow-repeat"></i> Actualizaciones
    <?php if ($update && $update['version'] !== $dismissed): ?>
    <span class="badge rounded-pill bg-gold text-dark ms-auto" style="font-size: .65rem;">1</span>
    <?php endif; ?>
  </a>

  <div class="sidebar-bottom">
    <div class="d-flex align-items-center justify-content-between mb-3">
      <div class="d-flex flex-column gap-1">
        <small style="color:var(--sb-muted);font-size:.62rem;text-transform:uppercase;letter-spacing:.06em;">Año Fiscal</small>
        <span class="anio-badge"><?= date('Y') ?></span>
      </div>
      <div class="d-flex align-items-center gap-1">
        <small style="color:var(--sb-muted);font-size:.7rem;max-width:80px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
          <?= e($_SESSION['usuario_user'] ?? '') ?>
        </small>
        <button class="theme-toggle" id="themeToggle" onclick="toggleTheme()" title="Cambiar tema">
          <i class="bi bi-moon-stars-fill" id="themeIcon"></i>
        </button>
      </div>
    </div>

    <button class="logout-btn"
            id="logoutBtn"
            data-bs-toggle="popover" 
            data-bs-placement="top"
            data-bs-html="true"
            data-bs-title="¿Cerrar sesión?"
            data-bs-content="<div class='d-flex gap-2'><button class='btn btn-sm btn-outline-secondary w-100' onclick='closeLogoutPopover()'>No</button><a href='?logout=1' class='btn btn-sm btn-danger w-100'>Sí, salir</a></div>">
      <i class="bi bi-box-arrow-right"></i>
      <span>Cerrar sesión</span>
    </button>
  </div>
</aside>

<div id="session-warning">
    <span><i class="bi bi-exclamation-triangle-fill me-2"></i>Tu sesión expirará en <strong id="session-timer">5:00</strong> minutos.</span>
    <button class="btn btn-sm btn-warning" onclick="resetInactivity()">Mantener sesión</button>
</div>

<!-- ═══ MAIN ═══ -->
<main class="main">
<?= showFlash() ?>

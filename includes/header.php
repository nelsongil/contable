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
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="description" content="Libro Contable — <?= e(getConfig('empresa_sociedad', EMPRESA_SOCIEDAD)) ?>">
<meta name="theme-color" content="#1A2E2A">
<meta name="apple-mobile-web-app-capable" content="yes">
<link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'%3E%3Crect width='32' height='32' rx='6' fill='%231A2E2A'/%3E%3Cpath d='M9 7h14v18H9z' fill='%23C9A84C'/%3E%3Cpath d='M9 10h14M9 14h14M9 18h14M9 22h14' stroke='%231A2E2A' stroke-width='1.5'/%3E%3C/svg%3E">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
:root {
  --verde:    #1A2E2A;
  --verde-m:  #2D5245;
  --verde-a:  #3E7B64;
  --gold:     #C9A84C;
  --bg:       #F4F7F5;
  --sidebar:  220px;
}
body { font-family: 'Inter', sans-serif; background: var(--bg); color: #1a1a1a; }

/* ── Sidebar ── */
.sidebar {
  width: var(--sidebar); height: 100vh; background: var(--verde);
  position: fixed; top: 0; left: 0; z-index: 100;
  display: flex; flex-direction: column;
  overflow-y: auto;
}
.sidebar::-webkit-scrollbar { width: 3px; }
.sidebar::-webkit-scrollbar-thumb { background: rgba(255,255,255,.2); border-radius: 2px; }
.sidebar-logo {
  display: block; text-decoration: none;
  padding: 1.4rem 1.2rem 1rem;
  border-bottom: 2px solid var(--gold);
  transition: background .15s;
}
.sidebar-logo:hover { background: rgba(255,255,255,.04); }
.sidebar-logo .company { color: var(--gold); font-weight: 700; font-size: .95rem; }
.sidebar-logo .person  { color: #aac7bd; font-size: .78rem; }
.sidebar-logo .cif     { color: #6a9488; font-size: .72rem; }

.nav-section { padding: .6rem 1rem .2rem; color: #6a9488; font-size: .7rem; text-transform: uppercase; letter-spacing: .08em; }
.sidebar .nav-link {
  color: #aac7bd; padding: .5rem 1.2rem; font-size: .85rem;
  border-radius: 0; display: flex; align-items: center; gap: .6rem;
  transition: background .15s, color .15s;
}
.sidebar .nav-link:hover, .sidebar .nav-link.active {
  color: #fff; background: var(--verde-m);
  border-left: 3px solid var(--gold);
  padding-left: calc(1.2rem - 3px);
}
.sidebar .nav-link i { font-size: 1rem; width: 18px; text-align: center; }

.sidebar-bottom { margin-top: auto; padding: 1rem; border-top: 1px solid #2a4a40; }
.sidebar-bottom .anio-badge {
  background: var(--gold); color: var(--verde); font-weight: 700;
  font-size: .8rem; padding: .25rem .7rem; border-radius: 20px;
}

/* ── Main content ── */
.main { margin-left: var(--sidebar); padding: 2rem; min-height: 100vh; }

/* ── Top bar ── */
.topbar {
  background: #fff; border-radius: 12px; padding: .8rem 1.4rem;
  margin-bottom: 1.5rem; display: flex; align-items: center;
  justify-content: space-between; box-shadow: 0 1px 4px rgba(0,0,0,.06);
  position: relative; z-index: 10;
}
.topbar h1 { font-size: 1.25rem; font-weight: 700; color: var(--verde); margin: 0; }

/* ── Cards ── */
.card { border: none; border-radius: 12px; box-shadow: 0 1px 4px rgba(0,0,0,.07); }
.card-header {
  background: var(--verde-m); color: #fff; border-radius: 12px 12px 0 0 !important;
  font-weight: 600; font-size: .9rem; padding: .75rem 1.25rem;
  border-bottom: 2px solid var(--gold);
}

/* ── KPI cards ── */
.kpi { background: #fff; border-radius: 12px; padding: 1.2rem 1.4rem; box-shadow: 0 1px 4px rgba(0,0,0,.07); }
.kpi .label { font-size: .75rem; color: #6b7280; text-transform: uppercase; letter-spacing: .06em; }
.kpi .value { font-size: 1.6rem; font-weight: 700; color: var(--verde); }
.kpi .sub   { font-size: .78rem; color: #9ca3af; }
.kpi-gold .value { color: var(--gold); }
.kpi-red  .value { color: #dc3545; }

/* ── Tables ── */
.table { font-size: .87rem; }
.table thead th {
  background: var(--verde); color: #fff; font-weight: 600; font-size: .78rem;
  text-transform: uppercase; letter-spacing: .05em; border: none; padding: .75rem 1rem;
}
.table tbody tr:hover { background: #f0f7f4; }
.table td { vertical-align: middle; padding: .65rem 1rem; }

/* ── Badges ── */
.badge-trim { background: var(--verde-a); color: #fff; font-size: .72rem; padding: .3em .6em; border-radius: 6px; }

/* ── Buttons ── */
.btn-primary   { background: var(--verde-a); border-color: var(--verde-a); }
.btn-primary:hover { background: var(--verde-m); border-color: var(--verde-m); }
.btn-gold      { background: var(--gold); border-color: var(--gold); color: var(--verde); font-weight: 600; }
.btn-gold:hover { background: #b8923e; border-color: #b8923e; color: var(--verde); }

/* ── Form ── */
.form-label { font-size: .82rem; font-weight: 600; color: #374151; }
.form-control, .form-select { font-size: .88rem; border-radius: 8px; }
.form-control:focus, .form-select:focus { border-color: var(--verde-a); box-shadow: 0 0 0 3px rgba(62,123,100,.15); }

/* ── Invoice lines table ── */
.lineas-table input { border: none; background: transparent; width: 100%; font-size: .85rem; }
.lineas-table input:focus { outline: 1px solid var(--verde-a); border-radius: 4px; }
.lineas-table tr:hover .btn-remove { opacity: 1; }
.btn-remove { opacity: .3; transition: opacity .2s; }

/* ── Animations ── */
@keyframes fadeInUp {
  from { opacity: 0; transform: translateY(20px); }
  to { opacity: 1; transform: translateY(0); }
}
.fade-in-up { animation: fadeInUp 0.5s ease both; }
.delay-1 { animation-delay: 0.1s; }
.delay-2 { animation-delay: 0.2s; }
.delay-3 { animation-delay: 0.3s; }
.delay-4 { animation-delay: 0.4s; }

/* ── Floating Labels ── */
.form-floating > .form-control:focus ~ label,
.form-floating > .form-control:not(:placeholder-shown) ~ label {
  color: var(--verde-a);
}

/* ── Table UX ── */
.table tbody tr { transition: background 0.2s; cursor: pointer; }
.table .actions { opacity: 0; transition: opacity 0.2s; white-space: nowrap; }
.table tr:hover .actions { opacity: 1; }
.sortable { cursor: pointer; position: relative; }
.sortable:after { content: '↕'; position: absolute; right: 8px; opacity: 0.3; font-size: 0.8em; }

/* ── Sidebar Updates ── */
.sidebar .nav-link { position: relative; }
.sidebar-bottom .logout-btn {
  width: 100%; text-align: left; background: rgba(255,255,255,0.05); color: #aac7bd;
  border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; padding: 0.6rem;
  font-size: 0.85rem; transition: all 0.2s;
}
.sidebar-bottom .logout-btn:hover { background: #fee2e2; color: #991b1b; border-color: #fca5a5; }

/* ── Status Banner ── */
#session-warning {
  position: fixed; top: 0; left: var(--sidebar); right: 0; z-index: 1050;
  background: #fff3cd; border-bottom: 1px solid #ffeeba; color: #856404;
  padding: 0.75rem 1.25rem; display: none; align-items: center; justify-content: space-between;
}

/* ── Hamburger toggle (oculto en desktop) ── */
#sidebarToggle {
  display: none; position: fixed; top: .75rem; left: .75rem; z-index: 1060;
  background: var(--verde); color: #fff; border: none; border-radius: 8px;
  width: 40px; height: 40px; align-items: center; justify-content: center;
  font-size: 1.3rem; box-shadow: 0 2px 8px rgba(0,0,0,.3); cursor: pointer;
  transition: background .15s;
}
#sidebarToggle:hover { background: var(--verde-m); }

/* ── Backdrop para sidebar móvil ── */
#sidebarBackdrop {
  display: none; position: fixed; inset: 0;
  background: rgba(0,0,0,.5); z-index: 1039;
}
#sidebarBackdrop.show { display: block; }

/* ── Responsive ── */
@media (max-width: 767.98px) {
  #sidebarToggle { display: flex; }
  .sidebar {
    left: calc(-1 * var(--sidebar));
    transition: left .3s ease;
    z-index: 1040;
  }
  .sidebar.sidebar-open { left: 0; }
  .main { margin-left: 0 !important; padding: 1rem; padding-top: 3.5rem; }
  #session-warning { left: 0; }
  .topbar { flex-wrap: wrap; gap: .5rem; }
  .topbar h1 { font-size: 1.05rem; }
  .topbar > div:last-child { width: 100%; }
  .topbar .form-control-sm { width: auto !important; flex: 1 1 100px; min-width: 80px; }
  .topbar .form-select-sm  { width: auto !important; flex: 0 0 80px; }
  /* Tablas: scroll horizontal en móvil */
  .card-body.p-0 { overflow-x: auto; -webkit-overflow-scrolling: touch; }
  .kpi .value { font-size: 1.3rem; }
  /* Ocultar columnas no críticas en móvil */
  .d-none-mobile { display: none !important; }
  /* P3 — 16px en inputs evita zoom automático en iOS */
  .form-control, .form-select { font-size: 16px; }
  /* P3 — Botones táctiles con área mínima de 44px */
  .btn:not(.btn-sm) { min-height: 44px; }
}
</style>
<?php
// ─── Contador de Notificaciones (Borradores) ───
$cntBorradores = (int)getDB()->query("SELECT COUNT(*) FROM facturas_emitidas WHERE estado='borrador'")->fetchColumn();
?>
<?= getThemeCSS() ?>
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
      <div class="d-flex flex-column">
        <small class="text-muted" style="font-size:0.65rem; text-transform: uppercase;">Año Fiscal</small>
        <span class="anio-badge" style="width: fit-content;"><?= date('Y') ?></span>
      </div>
      <div class="text-end">
        <small style="color:#6a9488;font-size:0.72rem;display:block;">👤 <?= htmlspecialchars($_SESSION['usuario_user'] ?? '') ?></small>
      </div>
    </div>
    
    <button class="logout-btn d-flex align-items-center justify-content-center gap-2" 
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

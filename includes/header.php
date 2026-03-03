<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= $pageTitle ?? 'Contabilidad' ?> — <?= EMPRESA_SOCIEDAD ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
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
  width: var(--sidebar); min-height: 100vh; background: var(--verde);
  position: fixed; top: 0; left: 0; z-index: 100;
  display: flex; flex-direction: column;
}
.sidebar-logo {
  padding: 1.4rem 1.2rem 1rem;
  border-bottom: 2px solid var(--gold);
}
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

/* ── Alerts ── */
.alert-success { background: #d1fae5; border-color: #6ee7b7; color: #065f46; }
.alert-danger  { background: #fee2e2; border-color: #fca5a5; color: #991b1b; }
</style>
</head>
<body>

<!-- ═══ SIDEBAR ═══ -->
<aside class="sidebar">
  <div class="sidebar-logo">
    <div class="company"><?= EMPRESA_SOCIEDAD ?></div>
    <div class="person"><?= EMPRESA_NOMBRE ?></div>
    <div class="cif">CIF: <?= EMPRESA_CIF ?></div>
  </div>

  <div class="nav-section">Facturación</div>
  <a href="/facturas/" class="nav-link <?= $currentPage==='index' && str_contains($_SERVER['REQUEST_URI'],'/facturas') ? 'active' : '' ?>">
    <i class="bi bi-receipt"></i> Facturas emitidas
  </a>
  <a href="/facturas/nueva.php" class="nav-link <?= $currentPage==='nueva' ? 'active' : '' ?>">
    <i class="bi bi-plus-circle"></i> Nueva factura
  </a>

  <div class="nav-section">Compras</div>
  <a href="/compras/" class="nav-link <?= str_contains($_SERVER['REQUEST_URI'],'/compras') ? 'active' : '' ?>">
    <i class="bi bi-bag"></i> Facturas recibidas
  </a>
  <a href="/compras/nueva.php" class="nav-link">
    <i class="bi bi-plus-circle"></i> Nueva compra
  </a>

  <div class="nav-section">Agenda</div>
  <a href="/clientes/" class="nav-link <?= str_contains($_SERVER['REQUEST_URI'],'/clientes') ? 'active' : '' ?>">
    <i class="bi bi-people"></i> Clientes
  </a>
  <a href="/proveedores/" class="nav-link <?= str_contains($_SERVER['REQUEST_URI'],'/proveedores') ? 'active' : '' ?>">
    <i class="bi bi-truck"></i> Proveedores
  </a>

  <div class="nav-section">Informes</div>
  <a href="/libros/" class="nav-link <?= str_contains($_SERVER['REQUEST_URI'],'/libros') ? 'active' : '' ?>">
    <i class="bi bi-journal-text"></i> Libros contables
  </a>
  <a href="/libros/resumen.php" class="nav-link">
    <i class="bi bi-bar-chart"></i> Resumen fiscal
  </a>
  <a href="/libros/exportar.php" class="nav-link">
    <i class="bi bi-file-earmark-excel"></i> Exportar Excel
  </a>

  <div class="sidebar-bottom">
    <div class="d-flex align-items-center justify-content-between mb-2">
      <small class="text-muted" style="font-size:.7rem">Año fiscal</small>
      <span class="anio-badge"><?= date('Y') ?></span>
    </div>
    <div class="d-flex align-items-center justify-content-between">
      <small style="color:#6a9488;font-size:.72rem">👤 <?= htmlspecialchars($_SESSION['usuario_user'] ?? '') ?></small>
      <a href="?logout=1" style="font-size:.72rem;color:#6a9488;text-decoration:none" title="Cerrar sesión">⎋ Salir</a>
    </div>
  </div>
</aside>

<!-- ═══ MAIN ═══ -->
<main class="main">
<?= showFlash() ?>

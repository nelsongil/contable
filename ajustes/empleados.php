<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = post('accion');

    if ($accion === 'activar') {
        // Crear tablas si no existen
        $db = getDB();
        $db->exec("CREATE TABLE IF NOT EXISTS `empleados` (
          `id` int NOT NULL AUTO_INCREMENT,
          `nombre` varchar(150) NOT NULL,
          `nif` varchar(20) NOT NULL DEFAULT '',
          `puesto` varchar(100) NOT NULL DEFAULT '',
          `salario_mensual` decimal(12,2) NOT NULL DEFAULT '0.00',
          `porcentaje_irpf` decimal(5,2) NOT NULL DEFAULT '0.00',
          `fecha_alta` date DEFAULT NULL,
          `activo` tinyint(1) NOT NULL DEFAULT '1',
          `creado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS `retenciones_empleados` (
          `id` int NOT NULL AUTO_INCREMENT,
          `empleado_id` int NOT NULL,
          `anio` year NOT NULL,
          `mes` tinyint unsigned NOT NULL,
          `salario_pagado` decimal(12,2) NOT NULL DEFAULT '0.00',
          `retencion_irpf` decimal(12,2) NOT NULL DEFAULT '0.00',
          `creado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
          PRIMARY KEY (`id`),
          UNIQUE KEY `uk_empleado_mes` (`empleado_id`,`anio`,`mes`),
          CONSTRAINT `fk_ret_empleado` FOREIGN KEY (`empleado_id`) REFERENCES `empleados` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        setConfig('modulo_empleados', 'true');
        flash('Módulo de empleados activado. Las tablas han sido creadas.');

    } elseif ($accion === 'desactivar') {
        setConfig('modulo_empleados', 'false');
        flash('Módulo de empleados desactivado. Los datos se conservan.', 'warning');
    }

    redirect('/ajustes/empleados.php');
}

$activo = getConfig('modulo_empleados', false);

// Contar empleados si el módulo está activo
$numEmpleados = 0;
if ($activo) {
    try {
        $numEmpleados = (int)getDB()->query("SELECT COUNT(*) FROM empleados WHERE activo=1")->fetchColumn();
    } catch (Exception $e) {
        $numEmpleados = 0;
    }
}

$pageTitle = 'Módulo Empleados';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="topbar">
  <h1><i class="bi bi-person-gear me-2"></i>Módulo de Empleados</h1>
</div>

<div class="row g-4" style="max-width:780px">
  <div class="col-12">
    <div class="card">
      <div class="card-header">Estado del módulo</div>
      <div class="card-body">
        <div class="d-flex align-items-center gap-3 mb-4">
          <?php if ($activo): ?>
          <span class="badge bg-success fs-6 px-3 py-2"><i class="bi bi-check-circle me-1"></i>Activo</span>
          <span class="text-muted"><?= $numEmpleados ?> empleado<?= $numEmpleados != 1 ? 's' : '' ?> activo<?= $numEmpleados != 1 ? 's' : '' ?></span>
          <?php else: ?>
          <span class="badge bg-secondary fs-6 px-3 py-2"><i class="bi bi-dash-circle me-1"></i>Inactivo</span>
          <span class="text-muted">El módulo no está habilitado</span>
          <?php endif; ?>
        </div>

        <div class="alert alert-info py-2 mb-4" style="font-size:.85rem">
          <i class="bi bi-info-circle me-2"></i>
          Este módulo añade gestión de empleados y calcula los datos para el <strong>Modelo 111</strong>
          (retenciones IRPF sobre rendimientos del trabajo). Incluye registro mensual de nóminas
          y resumen trimestral listo para declarar.
        </div>

        <?php if ($activo): ?>
        <div class="d-flex gap-2 mb-3">
          <a href="/empleados/" class="btn btn-gold">
            <i class="bi bi-person-badge me-1"></i>Gestionar empleados
          </a>
          <a href="/empleados/retenciones.php" class="btn btn-outline-primary">
            <i class="bi bi-calendar3 me-1"></i>Registrar retenciones
          </a>
          <a href="/empleados/modelo111.php" class="btn btn-outline-primary">
            <i class="bi bi-file-earmark-text me-1"></i>Modelo 111
          </a>
        </div>

        <hr>
        <form method="post" data-confirm="¿Desactivar el módulo? Los datos se conservarán.">
          <input type="hidden" name="accion" value="desactivar">
          <button type="submit" class="btn btn-outline-danger btn-sm">
            <i class="bi bi-dash-circle me-1"></i>Desactivar módulo
          </button>
        </form>

        <?php else: ?>
        <form method="post">
          <input type="hidden" name="accion" value="activar">
          <button type="submit" class="btn btn-gold px-4">
            <i class="bi bi-plus-circle me-1"></i>Activar módulo de empleados
          </button>
        </form>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

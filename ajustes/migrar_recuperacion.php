<?php
/**
 * Migración: Crear tabla password_reset_tokens
 * Solo accesible para admin
 */
session_start();
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

requireAdmin();

$db = getDB();
$hecho = false;
$error = '';

try {
    // 1. Verificar si existe la tabla
    $st = $db->query("SHOW TABLES LIKE 'password_reset_tokens'");
    if (!$st->fetch()) {
        // Crear tabla si no existe
        $db->exec("CREATE TABLE password_reset_tokens (
            id           INT AUTO_INCREMENT PRIMARY KEY,
            usuario_id   INT NOT NULL,
            token        VARCHAR(255) NOT NULL,
            expira_en    DATETIME NOT NULL,
            usado        TINYINT(1) DEFAULT 0,
            creado_en    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
            INDEX idx_expira (expira_en)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $hecho = true;
        $msg = 'Tabla creada correctamente.';
    } else {
        // 2. Verificar tamaño de columna token
        $st = $db->query("SHOW COLUMNS FROM password_reset_tokens LIKE 'token'");
        $col = $st->fetch();
        $tipo = $col['Type'];

        if (strpos($tipo, 'varchar(6)') !== false) {
            // Ampliar columna
            $db->exec("ALTER TABLE password_reset_tokens MODIFY COLUMN token VARCHAR(255) NOT NULL");
            $hecho = true;
            $msg = "Columna token ampliada de $tipo a VARCHAR(255).";
        } else {
            $hecho = true;
            $info = "La columna token ya tiene el tamaño correcto: $tipo";
        }
    }
} catch (Exception $e) {
    $error = 'Error: ' . $e->getMessage();
}

$pageTitle = 'Migración - Recuperación de Contraseña';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container mt-4">
  <div class="row justify-content-center">
    <div class="col-md-6">
      <div class="card">
        <div class="card-header bg-warning text-dark">
          <i class="bi bi-database me-2"></i>Migración: Recuperación de Contraseña
        </div>
        <div class="card-body">
          <?php if ($hecho && !$error): ?>
          <div class="alert alert-success">
            <i class="bi bi-check-circle-fill me-2"></i>
            <strong>¡Migración completada!</strong><br>
            La tabla <code>password_reset_tokens</code> se ha creado correctamente.
          </div>
          <p class="mb-3">Ahora los usuarios pueden recuperar su contraseña usando el sistema de código de 6 dígitos.</p>
          <a href="/index.php" class="btn btn-primary">
            <i class="bi bi-house-door me-2"></i>Volver al inicio
          </a>
          <?php elseif ($error): ?>
          <div class="alert <?= str_contains($error, 'ya existe') ? 'alert-info' : 'alert-danger' ?>">
            <i class="bi bi-info-circle-fill me-2"></i>
            <?= e($error) ?>
          </div>
          <a href="/index.php" class="btn btn-secondary">
            <i class="bi bi-arrow-left me-2"></i>Volver al inicio
          </a>
          <?php else: ?>
          <p>Ejecutando migración...</p>
          <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Cargando...</span>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

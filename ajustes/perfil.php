<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
// Accesible para todos los roles autenticados (ya chequeado por auth.php)

$db  = getDB();
$uid = currentUserId();
$err = '';

// Cargar datos actuales
$st = $db->prepare("SELECT id, nombre, email FROM usuarios WHERE id=?");
$st->execute([$uid]);
$usuario = $st->fetch();

if (!$usuario) {
    session_destroy();
    redirect('/login.php');
}

// ── Procesar formulario ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre     = trim(post('nombre'));
    $email      = trim(strtolower(post('email')));
    $passActual = post('password_actual');
    $passNueva  = post('password_nueva');
    $passConf   = post('password_confirm');

    if (!$nombre || !$email) {
        $err = 'Nombre y email son obligatorios.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $err = 'El email no tiene formato válido.';
    } else {
        // Comprobar duplicado en otro usuario
        $dup = $db->prepare("SELECT id FROM usuarios WHERE email=? AND id!=?");
        $dup->execute([$email, $uid]);
        if ($dup->fetch()) {
            $err = 'Ese email ya lo usa otro usuario.';
        }
    }

    // Si quiere cambiar contraseña
    if (!$err && $passNueva) {
        if (!$passActual) {
            $err = 'Introduce tu contraseña actual para cambiarla.';
        } elseif (strlen($passNueva) < 8 || !preg_match('/[A-Za-z]/', $passNueva) || !preg_match('/[0-9]/', $passNueva)) {
            $err = 'La nueva contraseña debe tener al menos 8 caracteres, una letra y un número.';
        } elseif ($passNueva !== $passConf) {
            $err = 'La nueva contraseña y su confirmación no coinciden.';
        } else {
            // Verificar contraseña actual
            $row = $db->prepare("SELECT password_hash FROM usuarios WHERE id=?");
            $row->execute([$uid]);
            $hash = $row->fetchColumn();
            if (!$hash || !password_verify($passActual, $hash)) {
                $err = 'La contraseña actual no es correcta.';
            }
        }
    }

    if (!$err) {
        if ($passNueva) {
            $newHash = password_hash($passNueva, PASSWORD_BCRYPT);
            $db->prepare("UPDATE usuarios SET nombre=?, email=?, password_hash=? WHERE id=?")
               ->execute([$nombre, $email, $newHash, $uid]);
        } else {
            $db->prepare("UPDATE usuarios SET nombre=?, email=? WHERE id=?")
               ->execute([$nombre, $email, $uid]);
        }
        // Actualizar sesión
        $_SESSION['usuario_nombre'] = $nombre;
        $_SESSION['usuario_email']  = $email;
        flash('Perfil actualizado correctamente.');
        redirect('/ajustes/perfil.php');
    }
}

$pageTitle = 'Mi perfil';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="topbar">
  <h1><i class="bi bi-person-circle me-2"></i>Mi perfil</h1>
</div>

<?= showFlash() ?>

<div class="row justify-content-center">
  <div class="col-md-6 col-lg-5">
    <div class="card">
      <div class="card-header fw-semibold">Datos personales</div>
      <div class="card-body">
        <?php if ($err): ?>
        <div class="alert alert-danger"><?= e($err) ?></div>
        <?php endif; ?>
        <form method="post">
          <div class="mb-3">
            <label class="form-label">Nombre</label>
            <input type="text" name="nombre" class="form-control"
                   value="<?= e(post('nombre', $usuario['nombre'])) ?>"
                   maxlength="150" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Email (se usa para el login)</label>
            <input type="email" name="email" class="form-control"
                   value="<?= e(post('email', $usuario['email'])) ?>"
                   maxlength="150" required>
          </div>

          <hr>
          <p class="text-muted" style="font-size:.85rem">Deja los campos de contraseña vacíos si no quieres cambiarla.</p>

          <div class="mb-3">
            <label class="form-label">Contraseña actual</label>
            <input type="password" name="password_actual" class="form-control" autocomplete="current-password">
          </div>
          <div class="mb-3">
            <label class="form-label">Nueva contraseña</label>
            <input type="password" name="password_nueva" class="form-control" autocomplete="new-password">
          </div>
          <div class="mb-3">
            <label class="form-label">Confirmar nueva contraseña</label>
            <input type="password" name="password_confirm" class="form-control" autocomplete="new-password">
          </div>

          <div class="mb-3">
            <label class="form-label text-muted">Rol</label>
            <div class="form-control-plaintext">
              <?= $_SESSION['usuario_rol'] === 'admin' ? '<span class="badge bg-success">Admin</span>' : '<span class="badge bg-primary">Colaborador</span>' ?>
            </div>
          </div>

          <button type="submit" class="btn btn-primary w-100">Guardar cambios</button>
        </form>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

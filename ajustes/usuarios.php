<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';
requireAdmin();

$db  = getDB();
$err = '';

// ── Acciones POST ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = post('action');

    // ── Crear usuario ─────────────────────────────────────────────────────────
    if ($action === 'crear') {
        $nombre = trim(post('nombre'));
        $email  = trim(strtolower(post('email')));
        $pass   = post('password');
        $rol    = post('rol') === 'colaborador' ? 'colaborador' : 'admin';

        if (!$nombre || !$email || !$pass) {
            $err = 'Nombre, email y contraseña son obligatorios.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $err = 'El email no tiene formato válido.';
        } elseif (strlen($pass) < 8 || !preg_match('/[A-Za-z]/', $pass) || !preg_match('/[0-9]/', $pass)) {
            $err = 'La contraseña debe tener al menos 8 caracteres, una letra y un número.';
        } else {
            // Comprobar duplicado
            $dup = $db->prepare("SELECT id FROM usuarios WHERE email = ?");
            $dup->execute([$email]);
            if ($dup->fetch()) {
                $err = 'Ya existe un usuario con ese email.';
            } else {
                $hash = password_hash($pass, PASSWORD_BCRYPT);
                $db->prepare(
                    "INSERT INTO usuarios (nombre, email, password_hash, rol, estado) VALUES (?,?,?,'$rol','activo')"
                )->execute([$nombre, $email, $hash]);
                flash('Usuario creado correctamente.');
                redirect('/ajustes/usuarios.php');
            }
        }
    }

    // ── Editar usuario ────────────────────────────────────────────────────────
    if ($action === 'editar') {
        $uid    = (int)post('id');
        $nombre = trim(post('nombre'));
        $email  = trim(strtolower(post('email')));
        $rol    = post('rol') === 'colaborador' ? 'colaborador' : 'admin';
        $estado = post('estado') === 'inactivo'  ? 'inactivo'   : 'activo';
        $pass   = post('password');

        if (!$uid || !$nombre || !$email) {
            $err = 'Nombre y email son obligatorios.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $err = 'El email no tiene formato válido.';
        } else {
            // No puede desactivar su propia cuenta
            if ($uid === currentUserId() && $estado === 'inactivo') {
                $err = 'No puedes desactivar tu propia cuenta.';
            }
            // No puede quitarse el rol admin si es el único admin activo
            if (!$err && $rol === 'colaborador' && $uid === currentUserId()) {
                $err = 'No puedes cambiar tu propio rol.';
            }
            if (!$err && $rol === 'colaborador') {
                $adminsActivos = (int)$db->query(
                    "SELECT COUNT(*) FROM usuarios WHERE rol='admin' AND estado='activo'"
                )->fetchColumn();
                $esAdmin = $db->prepare("SELECT rol FROM usuarios WHERE id=?")->execute([$uid])
                    ? $db->prepare("SELECT rol FROM usuarios WHERE id=?")->execute([$uid]) && false : false;
                $rowRol = $db->prepare("SELECT rol FROM usuarios WHERE id=?");
                $rowRol->execute([$uid]);
                $oldRol = $rowRol->fetchColumn();
                if ($oldRol === 'admin' && $adminsActivos <= 1) {
                    $err = 'No puedes degradar al único admin activo.';
                }
            }
            if (!$err) {
                // Comprobar duplicado de email en otro usuario
                $dup = $db->prepare("SELECT id FROM usuarios WHERE email = ? AND id != ?");
                $dup->execute([$email, $uid]);
                if ($dup->fetch()) {
                    $err = 'Ese email ya lo usa otro usuario.';
                }
            }
            if (!$err) {
                if ($pass) {
                    if (strlen($pass) < 8 || !preg_match('/[A-Za-z]/', $pass) || !preg_match('/[0-9]/', $pass)) {
                        $err = 'La nueva contraseña debe tener al menos 8 caracteres, una letra y un número.';
                    } else {
                        $hash = password_hash($pass, PASSWORD_BCRYPT);
                        $db->prepare(
                            "UPDATE usuarios SET nombre=?, email=?, password_hash=?, rol=?, estado=? WHERE id=?"
                        )->execute([$nombre, $email, $hash, $rol, $estado, $uid]);
                    }
                } else {
                    $db->prepare(
                        "UPDATE usuarios SET nombre=?, email=?, rol=?, estado=? WHERE id=?"
                    )->execute([$nombre, $email, $rol, $estado, $uid]);
                }
                if (!$err) {
                    flash('Usuario actualizado.');
                    redirect('/ajustes/usuarios.php');
                }
            }
        }
    }

    // ── Desbloquear ───────────────────────────────────────────────────────────
    if ($action === 'desbloquear') {
        $uid = (int)post('id');
        $db->prepare("UPDATE usuarios SET intentos_fallidos=0, bloqueado_hasta=NULL WHERE id=?")
           ->execute([$uid]);
        flash('Usuario desbloqueado.');
        redirect('/ajustes/usuarios.php');
    }
}

// ── Cargar usuarios ───────────────────────────────────────────────────────────
$usuarios = $db->query(
    "SELECT id, nombre, email, rol, estado, ultimo_acceso,
            intentos_fallidos, bloqueado_hasta
     FROM usuarios ORDER BY rol, nombre"
)->fetchAll();

// ── Usuario a editar ──────────────────────────────────────────────────────────
$editId  = (int)get('editar', 0);
$editRow = null;
if ($editId) {
    $st = $db->prepare("SELECT id, nombre, email, rol, estado FROM usuarios WHERE id=?");
    $st->execute([$editId]);
    $editRow = $st->fetch();
}

$pageTitle = 'Usuarios';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="topbar">
  <h1><i class="bi bi-people-fill me-2"></i>Usuarios</h1>
</div>

<?= showFlash() ?>

<?php if ($err): ?>
<div class="alert alert-danger"><?= e($err) ?></div>
<?php endif; ?>

<div class="row g-4">
  <!-- ── Lista de usuarios ── -->
  <div class="col-lg-7">
    <div class="card">
      <div class="card-header fw-semibold">Usuarios activos e inactivos</div>
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead class="table-light">
            <tr>
              <th>Nombre</th>
              <th>Email</th>
              <th>Rol</th>
              <th>Estado</th>
              <th>Último acceso</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($usuarios as $u): ?>
            <?php $bloqueado = $u['bloqueado_hasta'] && strtotime($u['bloqueado_hasta']) > time(); ?>
            <tr <?= $u['estado'] === 'inactivo' ? 'class="text-muted"' : '' ?>>
              <td>
                <?= e($u['nombre']) ?>
                <?php if ($u['id'] === currentUserId()): ?>
                <span class="badge bg-secondary ms-1" style="font-size:.65rem">tú</span>
                <?php endif; ?>
              </td>
              <td><?= e($u['email']) ?></td>
              <td>
                <?php if ($u['rol'] === 'admin'): ?>
                <span class="badge bg-success">Admin</span>
                <?php else: ?>
                <span class="badge bg-primary">Colaborador</span>
                <?php endif; ?>
              </td>
              <td>
                <?php if ($bloqueado): ?>
                <span class="badge bg-danger">Bloqueado</span>
                <?php elseif ($u['estado'] === 'activo'): ?>
                <span class="badge bg-success">Activo</span>
                <?php else: ?>
                <span class="badge bg-secondary">Inactivo</span>
                <?php endif; ?>
              </td>
              <td style="font-size:.82rem">
                <?= $u['ultimo_acceso'] ? date('d/m/Y H:i', strtotime($u['ultimo_acceso'])) : '—' ?>
              </td>
              <td class="text-end">
                <div class="d-flex gap-1 justify-content-end">
                  <?php if ($bloqueado): ?>
                  <form method="post" class="d-inline">
                    <input type="hidden" name="action" value="desbloquear">
                    <input type="hidden" name="id" value="<?= $u['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Desbloquear">
                      <i class="bi bi-unlock"></i>
                    </button>
                  </form>
                  <?php endif; ?>
                  <a href="?editar=<?= $u['id'] ?>" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-pencil"></i>
                  </a>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- ── Formulario crear / editar ── -->
  <div class="col-lg-5">
    <div class="card">
      <div class="card-header fw-semibold">
        <?= $editRow ? 'Editar usuario' : 'Nuevo usuario' ?>
      </div>
      <div class="card-body">
        <form method="post">
          <input type="hidden" name="action" value="<?= $editRow ? 'editar' : 'crear' ?>">
          <?php if ($editRow): ?>
          <input type="hidden" name="id" value="<?= $editRow['id'] ?>">
          <?php endif; ?>

          <div class="mb-3">
            <label class="form-label">Nombre</label>
            <input type="text" name="nombre" class="form-control"
                   value="<?= e($editRow ? $editRow['nombre'] : post('nombre', '')) ?>"
                   maxlength="150" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control"
                   value="<?= e($editRow ? $editRow['email'] : post('email', '')) ?>"
                   maxlength="150" required>
          </div>
          <div class="mb-3">
            <label class="form-label">
              <?= $editRow ? 'Nueva contraseña' : 'Contraseña' ?>
              <?php if ($editRow): ?><span class="text-muted" style="font-size:.8rem">(dejar vacío para no cambiar)</span><?php endif; ?>
            </label>
            <input type="password" name="password" class="form-control"
                   <?= $editRow ? '' : 'required' ?> autocomplete="new-password">
          </div>
          <div class="mb-3">
            <label class="form-label">Rol</label>
            <select name="rol" class="form-select">
              <option value="admin"       <?= ($editRow && $editRow['rol'] === 'admin')       || (!$editRow && post('rol','admin') === 'admin')       ? 'selected' : '' ?>>Admin</option>
              <option value="colaborador" <?= ($editRow && $editRow['rol'] === 'colaborador') || (!$editRow && post('rol','') === 'colaborador') ? 'selected' : '' ?>>Colaborador</option>
            </select>
            <div class="form-text">Admin: acceso total. Colaborador: solo facturas, compras y agenda.</div>
          </div>
          <?php if ($editRow): ?>
          <div class="mb-3">
            <label class="form-label">Estado</label>
            <select name="estado" class="form-select">
              <option value="activo"   <?= $editRow['estado'] === 'activo'   ? 'selected' : '' ?>>Activo</option>
              <option value="inactivo" <?= $editRow['estado'] === 'inactivo' ? 'selected' : '' ?>>Inactivo</option>
            </select>
          </div>
          <?php endif; ?>

          <div class="d-flex gap-2">
            <button type="submit" class="btn btn-primary">
              <?= $editRow ? 'Guardar cambios' : 'Crear usuario' ?>
            </button>
            <?php if ($editRow): ?>
            <a href="/ajustes/usuarios.php" class="btn btn-outline-secondary">Cancelar</a>
            <?php endif; ?>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

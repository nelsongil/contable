<?php
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

// Si ya está logado, redirigir según rol
if (!empty($_SESSION['usuario_id'])) {
    $dest = ($_SESSION['usuario_rol'] ?? 'admin') === 'admin' ? '/index.php' : '/facturas/';
    header('Location: ' . $dest); exit;
}

$error = '';
$msg   = '';

switch (get('reason')) {
    case 'timeout':   $error = 'Tu sesión ha expirado por inactividad. Por favor, identifícate de nuevo.'; break;
    case 'logout':    $msg   = 'Sesión cerrada correctamente.'; break;
    case 'inactivo':  $error = 'Tu cuenta está desactivada. Contacta con el administrador.'; break;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim(strtolower($_POST['email'] ?? ''));
    $pass  = $_POST['password'] ?? '';

    if (!$email || !$pass) {
        $error = 'Introduce email y contraseña.';
    } else {
        $db = getDB();

        // Buscar usuario por email (nuevo schema) o username (fallback transición)
        $st = $db->prepare(
            "SELECT id, nombre, email, password_hash, rol, estado,
                    intentos_fallidos, bloqueado_hasta
             FROM usuarios
             WHERE email = ?
             LIMIT 1"
        );
        $st->execute([$email]);
        $u = $st->fetch();

        // Fallback: si la columna email no existe aún (pre-migración) buscar por username
        if (!$u) {
            try {
                $st2 = $db->prepare(
                    "SELECT id, nombre, username AS email, password_hash, rol, estado,
                            intentos_fallidos, bloqueado_hasta
                     FROM usuarios WHERE username = ? LIMIT 1"
                );
                $st2->execute([$email]);
                $u = $st2->fetch();
            } catch (PDOException) {}
        }

        if ($u) {
            // Comprobar bloqueo por intentos fallidos
            if ($u['bloqueado_hasta'] && strtotime($u['bloqueado_hasta']) > time()) {
                $mins = (int)ceil((strtotime($u['bloqueado_hasta']) - time()) / 60);
                $error = "Cuenta bloqueada temporalmente. Vuelve a intentarlo en {$mins} min.";
            } elseif ($u['estado'] !== 'activo') {
                $error = 'Tu cuenta está desactivada. Contacta con el administrador.';
            } else {
                // Intentar verificar con password_hash; fallback a columna password antigua
                $hashField = $u['password_hash'];
                if (!$hashField) {
                    try {
                        $r = $db->prepare("SELECT `password` FROM usuarios WHERE id = ?");
                        $r->execute([$u['id']]);
                        $hashField = $r->fetchColumn() ?: '';
                    } catch (PDOException) {}
                }

                if ($hashField && password_verify($pass, $hashField)) {
                    // Login correcto — resetear intentos
                    $db->prepare("UPDATE usuarios SET intentos_fallidos=0, bloqueado_hasta=NULL, ultimo_acceso=NOW() WHERE id=?")
                       ->execute([$u['id']]);

                    session_regenerate_id(true);
                    $_SESSION['usuario_id']     = (int)$u['id'];
                    $_SESSION['usuario_nombre'] = $u['nombre'];
                    $_SESSION['usuario_email']  = $u['email'];
                    $_SESSION['usuario_rol']    = $u['rol'];

                    $dest = $u['rol'] === 'admin' ? '/index.php' : '/facturas/';
                    $redirect = get('redirect', '');
                    if ($redirect && str_starts_with($redirect, '/') && !str_contains($redirect, '//')) {
                        $dest = $redirect;
                    }
                    header('Location: ' . $dest); exit;

                } else {
                    // Contraseña incorrecta — incrementar contador
                    $intentos = (int)$u['intentos_fallidos'] + 1;
                    if ($intentos >= 3) {
                        $bloqueadoHasta = date('Y-m-d H:i:s', time() + 15 * 60);
                        $db->prepare("UPDATE usuarios SET intentos_fallidos=?, bloqueado_hasta=? WHERE id=?")
                           ->execute([$intentos, $bloqueadoHasta, $u['id']]);
                        $error = 'Demasiados intentos fallidos. Cuenta bloqueada 15 minutos.';
                    } else {
                        $db->prepare("UPDATE usuarios SET intentos_fallidos=? WHERE id=?")
                           ->execute([$intentos, $u['id']]);
                        $restantes = 3 - $intentos;
                        $error = "Credenciales incorrectas. {$restantes} intento" . ($restantes !== 1 ? 's' : '') . " restante" . ($restantes !== 1 ? 's' : '') . ".";
                    }
                    // Pausa mínima anti-timing
                    usleep(300000);
                }
            }
        } else {
            usleep(300000);
            $error = 'Credenciales incorrectas.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Acceso — <?= e(getConfig('empresa_sociedad', EMPRESA_SOCIEDAD)) ?></title>
<link rel="icon" type="image/x-icon" href="/assets/logoApp.ico">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; }
body {
  font-family: 'Inter', sans-serif;
  background: #0f1e1b;
  min-height: 100vh;
  display: flex; align-items: center; justify-content: center; padding: 1.5rem;
  background-image: radial-gradient(ellipse at 20% 50%, rgba(45,82,69,.5) 0%, transparent 60%),
                    radial-gradient(ellipse at 80% 20%, rgba(201,168,76,.12) 0%, transparent 50%);
}
.card {
  background: #fff; border-radius: 20px;
  width: 100%; max-width: 400px;
  box-shadow: 0 30px 80px rgba(0,0,0,.4);
  overflow: hidden;
}
.card-top {
  background: #1A2E2A; padding: 2.5rem 2.5rem 2rem;
  text-align: center;
  border-bottom: 3px solid #C9A84C;
}
.card-top .icon { font-size: 2.8rem; display: block; margin-bottom: .75rem; }
.card-top .company { color: #C9A84C; font-size: 1.4rem; font-weight: 700; }
.card-top .person  { color: #8ab5a6; font-size: .83rem; margin-top: .2rem; }
.card-body { padding: 2.25rem 2.5rem 2.5rem; }
label { display: block; font-size: .8rem; font-weight: 600; color: #374151; margin-bottom: .35rem; }
input {
  width: 100%; padding: .65rem 1rem;
  border: 1.5px solid #d1d5db; border-radius: 9px;
  font-size: .92rem; font-family: inherit; outline: none;
  transition: border-color .15s, box-shadow .15s;
}
input:focus { border-color: #3E7B64; box-shadow: 0 0 0 3px rgba(62,123,100,.15); }
.field { margin-bottom: 1.1rem; }
.alert {
  background: #fef2f2; border: 1px solid #fca5a5; color: #991b1b;
  border-radius: 8px; padding: .65rem 1rem; font-size: .84rem; margin-bottom: 1.25rem;
}
.alert-ok {
  background: #dcfce7; border-color: #86efac; color: #166534;
}
.btn {
  width: 100%; padding: .8rem; background: #C9A84C; color: #1A2E2A;
  border: none; border-radius: 10px; font-family: inherit;
  font-size: .97rem; font-weight: 700; cursor: pointer;
  transition: background .15s; margin-top: .3rem;
}
.btn:hover { background: #b8923e; }
.footer-note { text-align: center; font-size: .75rem; color: #9ca3af; margin-top: 1.5rem; }
</style>
</head>
<body>
<div class="card">
  <div class="card-top">
    <img src="/assets/logoApp.png" alt="Logo" style="width:72px;height:72px;object-fit:contain;margin-bottom:.75rem;border-radius:12px;">
    <div class="company"><?= e(getConfig('empresa_sociedad', EMPRESA_SOCIEDAD)) ?></div>
    <div class="person"><?= e(getConfig('empresa_nombre', EMPRESA_NOMBRE)) ?></div>
  </div>
  <div class="card-body">
    <?php if ($error): ?>
    <div class="alert">⚠ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($msg): ?>
    <div class="alert alert-ok">✓ <?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>
    <?= showFlash() ?>
    <form method="post" autocomplete="on">
      <div class="field">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" required autofocus autocomplete="email"
               value="<?= htmlspecialchars(strtolower($_POST['email'] ?? '')) ?>">
      </div>
      <div class="field">
        <label for="password">Contraseña</label>
        <div style="position:relative">
          <input type="password" id="password" name="password" required autocomplete="current-password" style="padding-right:2.5rem">
          <button type="button" onclick="togglePass()" tabindex="-1"
                  style="position:absolute;right:.6rem;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:#9ca3af;padding:0;font-size:1rem"
                  id="togglePassBtn">👁</button>
        </div>
      </div>
      <button type="submit" class="btn">Entrar →</button>
    </form>
    <div class="footer-note">Libro Contable Autónomo · <?= date('Y') ?></div>
  </div>
</div>
<script>
function togglePass() {
  const inp = document.getElementById('password');
  const btn = document.getElementById('togglePassBtn');
  if (inp.type === 'password') { inp.type = 'text';  btn.textContent = '🙈'; }
  else                         { inp.type = 'password'; btn.textContent = '👁'; }
}
</script>
</body>
</html>

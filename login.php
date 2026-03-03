<?php
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

// Si ya está logado, redirigir
if (!empty($_SESSION['usuario_id'])) {
    header('Location: index.php'); exit;
}

$error = '';
$msg = '';

if (get('reason') === 'timeout') {
    $error = 'Tu sesión ha expirado por inactividad. Por favor, identifícate de nuevo.';
} elseif (get('reason') === 'logout') {
    $msg = 'Sesión cerrada correctamente.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = trim($_POST['username'] ?? '');
    $pass = $_POST['password'] ?? '';

    if ($user && $pass) {
        $st = getDB()->prepare("SELECT id, username, password, nombre FROM usuarios WHERE username = ?");
        $st->execute([$user]);
        $u = $st->fetch();

        if ($u && password_verify($pass, $u['password'])) {
            // Login correcto
            session_regenerate_id(true);
            $_SESSION['usuario_id']   = $u['id'];
            $_SESSION['usuario_user'] = $u['username'];
            $_SESSION['usuario_nombre'] = $u['nombre'];

            // Actualizar último acceso
            getDB()->prepare("UPDATE usuarios SET ultimo_acceso = NOW() WHERE id = ?")
                   ->execute([$u['id']]);

            header('Location: index.php'); exit;
        }
        // Pequeña pausa para evitar brute force
        sleep(1);
        $error = 'Usuario o contraseña incorrectos.';
    } else {
        $error = 'Introduce usuario y contraseña.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Acceso — <?= e(getConfig('empresa_sociedad', EMPRESA_SOCIEDAD)) ?></title>
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
    <span class="icon">📒</span>
    <div class="company"><?= e(getConfig('empresa_sociedad', EMPRESA_SOCIEDAD)) ?></div>
    <div class="person"><?= e(getConfig('empresa_nombre', EMPRESA_NOMBRE)) ?></div>
  </div>
  <div class="card-body">
    <?php if ($error): ?>
    <div class="alert">⚠ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <?php if ($msg): ?>
    <div class="alert" style="background:#dcfce7;border-color:#86efac;color:#166534">✓ <?= htmlspecialchars($msg) ?></div>
    <?php endif; ?>
    <?= showFlash() ?>
    <form method="post" autocomplete="on">
      <div class="field">
        <label for="username">Usuario</label>
        <input type="text" id="username" name="username" required autofocus autocomplete="username"
               value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
      </div>
      <div class="field">
        <label for="password">Contraseña</label>
        <input type="password" id="password" name="password" required autocomplete="current-password">
      </div>
      <button type="submit" class="btn">Entrar →</button>
    </form>
    <div class="footer-note">Libro Contable Autónomo · <?= date('Y') ?></div>
  </div>
</div>
</body>
</html>

<?php
session_start();
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

// Si ya está logado, redirigir
if (!empty($_SESSION['usuario_id'])) {
    $dest = ($_SESSION['usuario_rol'] ?? 'admin') === 'admin' ? '/index.php' : '/facturas/';
    header('Location: ' . $dest);
    exit;
}

$db = getDB();
$step = get('step', 'email');
$email = '';
$error = '';
$msg = '';

error_log("[RECUPERAR] Step inicial: $step");
error_log("[RECUPERAR] REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD']);
error_log("[RECUPERAR] GET: " . print_r($_GET, true));

// ── AJAX: Reenviar código ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && get('action') === 'reenviar') {
    header('Content-Type: application/json');

    if (empty($_SESSION['reset_email'])) {
        echo json_encode(['ok' => false, 'error' => 'No hay un email en sesión.']);
        exit;
    }

    $email = $_SESSION['reset_email'];
    $st = $db->prepare("SELECT id, nombre FROM usuarios WHERE email = ? AND estado = 'activo' LIMIT 1");
    $st->execute([$email]);
    $usuario = $st->fetch();

    if (!$usuario) {
        echo json_encode(['ok' => false, 'error' => 'Usuario no encontrado.']);
        exit;
    }

    // Generar nuevo código
    $codigo = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $expira = date('Y-m-d H:i:s', strtotime('+10 minutes'));
    $tokenHash = password_hash($codigo, PASSWORD_DEFAULT);

    // Invalidar tokens anteriores
    $db->prepare("UPDATE password_reset_tokens SET usado = 1 WHERE usuario_id = ?")->execute([$usuario['id']]);

    // Guardar nuevo token
    $st = $db->prepare("INSERT INTO password_reset_tokens (usuario_id, token, expira_en) VALUES (?, ?, ?)");
    $st->execute([$usuario['id'], $tokenHash, $expira]);

    // Reenviar email
    $asunto = 'Nuevo código de recuperación - ' . getConfig('empresa_sociedad', EMPRESA_SOCIEDAD);
    $cuerpo = "
<html>
<head><style>
body{font-family:Arial,sans-serif;background:#f4f7f5;padding:20px}
.container{max-width:500px;margin:0 auto;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.15)}
.header{background:linear-gradient(135deg,#1A2E2A 0%,#2D5245 100%);padding:40px 30px;text-align:center}
.header h1{color:#C9A84C;margin:0 0 10px 0;font-size:24px}
.header p{color:#8ab5a6;margin:0;font-size:14px}
.body{padding:40px 30px}
.codigo{background:#f8f9fa;border:2px dashed #C9A84C;border-radius:12px;padding:20px;text-align:center;margin:20px 0}
.codigo-num{font-size:36px;font-weight:700;color:#1A2E2A;letter-spacing:8px}
.aviso{background:#fff3cd;border-left:4px solid #ffc107;padding:15px;margin:20px 0;font-size:13px;color:#856404;border-radius:4px}
.footer{background:#f8f9fa;padding:20px 30px;text-align:center;font-size:12px;color:#6c757d}
</style></head>
<body>
<div class='container'>
<div class='header'><h1>🔐 Nuevo Código de Recuperación</h1><p>" . getConfig('empresa_sociedad', EMPRESA_SOCIEDAD) . "</p></div>
<div class='body'>
<p>Hola <strong>" . e($usuario['nombre']) . "</strong>,</p>
<p>Aquí tienes tu nuevo código de recuperación:</p>
<div class='codigo'><div class='codigo-num'>{$codigo}</div></div>
<div class='aviso'>⏱️ Caduca en <strong>10 minutos</strong>. Solo puede usarse una vez.</div>
<p>Si no has solicitado esto, ignora el email.</p>
</div>
<div class='footer'>Libro Contable Autónomo © " . date('Y') . "</div>
</div>
</body>
</html>";

    $headers = [
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=UTF-8',
        'From: ' . getConfig('empresa_nombre', EMPRESA_NOMBRE) . ' <' . getConfig('empresa_email', EMPRESA_EMAIL) . '>',
    ];

    $enviado = @mail($email, $asunto, $cuerpo, implode("\r\n", $headers));
    echo json_encode(['ok' => $enviado]);
    exit;
}

// ── Paso 1: Solicitar email ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 'email') {
    $email = trim(strtolower($_POST['email'] ?? ''));

    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Introduce un email válido.';
    } else {
        $st = $db->prepare("SELECT id, nombre FROM usuarios WHERE email = ? AND estado = 'activo' LIMIT 1");
        $st->execute([$email]);
        $usuario = $st->fetch();

        if (!$usuario) {
            $msg = 'Si el email existe, recibirás un código de recuperación.';
        } else {
            $codigo = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $expira = date('Y-m-d H:i:s', strtotime('+10 minutes'));
            $tokenHash = password_hash($codigo, PASSWORD_DEFAULT);

            // Invalidar tokens anteriores
            $db->prepare("UPDATE password_reset_tokens SET usado = 1 WHERE usuario_id = ?")->execute([$usuario['id']]);

            $st = $db->prepare("INSERT INTO password_reset_tokens (usuario_id, token, expira_en) VALUES (?, ?, ?)");
            $st->execute([$usuario['id'], $tokenHash, $expira]);

            $asunto = 'Recuperación de contraseña - ' . getConfig('empresa_sociedad', EMPRESA_SOCIEDAD);
            $cuerpo = "
<html>
<head><style>
body{font-family:'Inter',Arial,sans-serif;background:#f4f7f5;padding:20px}
.container{max-width:500px;margin:0 auto;background:#fff;border-radius:16px;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.15)}
.header{background:linear-gradient(135deg,#1A2E2A 0%,#2D5245 100%);padding:40px 30px;text-align:center}
.header h1{color:#C9A84C;margin:0 0 10px 0;font-size:24px}
.header p{color:#8ab5a6;margin:0;font-size:14px}
.body{padding:40px 30px}
.codigo{background:#f8f9fa;border:2px dashed #C9A84C;border-radius:12px;padding:20px;text-align:center;margin:20px 0}
.codigo-num{font-size:36px;font-weight:700;color:#1A2E2A;letter-spacing:8px}
.aviso{background:#fff3cd;border-left:4px solid #ffc107;padding:15px;margin:20px 0;font-size:13px;color:#856404;border-radius:4px}
.footer{background:#f8f9fa;padding:20px 30px;text-align:center;font-size:12px;color:#6c757d}
</style></head>
<body>
<div class='container'>
<div class='header'><h1>🔐 Recuperación de Contraseña</h1><p>" . getConfig('empresa_sociedad', EMPRESA_SOCIEDAD) . "</p></div>
<div class='body'>
<p>Hola <strong>" . e($usuario['nombre']) . "</strong>,</p>
<p>Has solicitado recuperar tu contraseña. Usa el siguiente código:</p>
<div class='codigo'><div class='codigo-num'>{$codigo}</div></div>
<div class='aviso'>⏱️ Caduca en <strong>10 minutos</strong>. Solo puede usarse una vez.</div>
<p>Si no has solicitado esto, ignora el email.</p>
</div>
<div class='footer'>Libro Contable Autónomo © " . date('Y') . "</div>
</div>
</body>
</html>";

            $headers = [
                'MIME-Version: 1.0',
                'Content-type: text/html; charset=UTF-8',
                'From: ' . getConfig('empresa_nombre', EMPRESA_NOMBRE) . ' <' . getConfig('empresa_email', EMPRESA_EMAIL) . '>',
            ];

            $enviado = @mail($email, $asunto, $cuerpo, implode("\r\n", $headers));

            if ($enviado) {
                $_SESSION['reset_email'] = $email;
                $_SESSION['reset_nombre'] = $usuario['nombre'];
                $_SESSION['reset_usuario_id'] = $usuario['id'];
                redirect('/recuperar.php?step=codigo');
            } else {
                $error = 'Error al enviar el email. Verifica la configuración SMTP.';
            }
        }
    }
}

// ── Debug: Ver TODO el POST ──────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("[RECUPERAR] POST completo: " . print_r($_POST, true));
    $debugPOSTRaw = $_POST;
} else {
    $debugPOSTRaw = null;
}

// ── Paso 2: Verificar código ─────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 'verificar_codigo') {
    $codigoArr = $_POST['codigo'] ?? [];

    error_log("[RECUPERAR] codigo[] recibido: " . print_r($codigoArr, true));
    error_log("[RECUPERAR] codigo[] es array: " . (is_array($codigoArr) ? 'SI' : 'NO'));
    error_log("[RECUPERAR] codigo[] count: " . count($codigoArr));

    // Unir los 6 dígitos del array, filtrando solo números
    $codigoUsuario = '';
    foreach ($codigoArr as $valor) {
        if (is_string($valor) && ctype_digit($valor)) {
            $codigoUsuario .= $valor;
        }
    }
    $codigoUsuario = substr($codigoUsuario, 0, 6); // Asegurar máximo 6 dígitos

    // Debug: log del código recibido
    error_log("[RECUPERAR] Código unido: '$codigoUsuario'");
    error_log("[RECUPERAR] Longitud: " . strlen($codigoUsuario));
    error_log("[RECUPERAR] Usuario ID en sesión: " . ($_SESSION['reset_usuario_id'] ?? 'NO'));
    error_log("[RECUPERAR] Email en sesión: " . ($_SESSION['reset_email'] ?? 'NO'));

    // Guardar info para debug en pantalla
    $debugPost = $codigoArr;
    $debugCodigo = $codigoUsuario;
    $debugSessionOk = !empty($_SESSION['reset_usuario_id']);

    if (strlen($codigoUsuario) !== 6) {
        error_log("[RECUPERAR] Error: longitud incorrecta ($codigoUsuario)");
        $error = 'El código debe tener 6 dígitos. Recibido: "' . e($codigoUsuario) . '" (long: ' . strlen($codigoUsuario) . ')';
        $step = 'codigo';
    } elseif (empty($_SESSION['reset_usuario_id'])) {
        error_log("[RECUPERAR] Error: no hay usuario en sesión");
        $error = 'Sesión expirada. Vuelve a iniciar el proceso.';
        $step = 'email';
    } else {
        $usuarioId = $_SESSION['reset_usuario_id'];

        // Buscar tokens no usados y no expirados
        $st = $db->prepare(
            "SELECT id, token FROM password_reset_tokens
             WHERE usuario_id = ? AND usado = 0 AND expira_en > NOW()
             ORDER BY creado_en DESC LIMIT 1"
        );
        $st->execute([$usuarioId]);
        $tokenRow = $st->fetch();

        error_log("[RECUPERAR] Token encontrado: " . ($tokenRow ? 'SI' : 'NO'));
        if ($tokenRow) {
            $verifyResult = password_verify($codigoUsuario, $tokenRow['token']);
            error_log("[RECUPERAR] password_verify('$codigoUsuario', token): " . ($verifyResult ? 'TRUE' : 'FALSE'));
        } else {
            error_log("[RECUPERAR] No hay tokens válidos para usuario $usuarioId");
        }

        if (!$tokenRow || !password_verify($codigoUsuario, $tokenRow['token'])) {
            error_log("[RECUPERAR] Error: código incorrecto o expirado");
            $error = 'Código incorrecto o expirado.';
            $step = 'codigo';
        } else {
            // Marcar como usado
            $db->prepare("UPDATE password_reset_tokens SET usado = 1 WHERE id = ?")->execute([$tokenRow['id']]);

            // Guardar en sesión para el siguiente paso
            $_SESSION['reset_codigo_validado'] = true;
            error_log("[RECUPERAR] Éxito, redirigiendo a nueva contraseña");
            redirect('/recuperar.php?step=nueva');
        }
    }
}

// ── Paso 3: Guardar nueva contraseña ─────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 'guardar_nueva') {
    if (empty($_SESSION['reset_codigo_validado'])) {
        $error = 'Debes verificar el código primero.';
        redirect('/recuperar.php');
    }

    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';

    if (!$password || strlen($password) < 8) {
        $error = 'La contraseña debe tener al menos 8 caracteres.';
        $step = 'nueva';
    } elseif ($password !== $passwordConfirm) {
        $error = 'Las contraseñas no coinciden.';
        $step = 'nueva';
    } elseif (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        $error = 'La contraseña debe contener al menos una letra y un número.';
        $step = 'nueva';
    } else {
        $usuarioId = $_SESSION['reset_usuario_id'];
        $hash = password_hash($password, PASSWORD_BCRYPT);

        $db->prepare("UPDATE usuarios SET password_hash = ? WHERE id = ?")->execute([$hash, $usuarioId]);

        // Limpiar sesión
        unset($_SESSION['reset_email'], $_SESSION['reset_nombre'], $_SESSION['reset_usuario_id'], $_SESSION['reset_codigo_validado']);

        // Redirigir al login con mensaje de éxito
        header('Location: /login.php?reason=password_reset');
        exit;
    }
}

// ── Determinar step actual ───────────────────────────────────
if (!isset($step) || !in_array($step, ['email', 'codigo', 'nueva'])) {
    $step = 'email';
}
if ($step === 'codigo' && empty($_SESSION['reset_email'])) {
    redirect('/recuperar.php');
}
if ($step === 'nueva' && empty($_SESSION['reset_codigo_validado'])) {
    redirect('/recuperar.php');
}

$pageTitle = 'Recuperar Contraseña';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?> — <?= e(getConfig('empresa_sociedad', EMPRESA_SOCIEDAD)) ?></title>
<link rel="icon" type="image/x-icon" href="/assets/logoApp.ico">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
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
  background: #fff; border-radius: 24px;
  width: 100%; max-width: 440px;
  box-shadow: 0 30px 80px rgba(0,0,0,.4);
  overflow: hidden;
  animation: slideUp 0.5s ease-out;
}
@keyframes slideUp {
  from { opacity: 0; transform: translateY(30px); }
  to { opacity: 1; transform: translateY(0); }
}
.card-top {
  background: linear-gradient(135deg, #1A2E2A 0%, #2D5245 100%); padding: 2.5rem 2.5rem 2rem;
  text-align: center;
  border-bottom: 3px solid #C9A84C;
  position: relative;
  overflow: hidden;
}
.card-top::before {
  content: '';
  position: absolute;
  top: -50%; left: -50%;
  width: 200%; height: 200%;
  background: radial-gradient(circle, rgba(201,168,76,.1) 0%, transparent 70%);
  animation: pulse 3s ease-in-out infinite;
}
@keyframes pulse {
  0%, 100% { transform: scale(1); opacity: 0.5; }
  50% { transform: scale(1.1); opacity: 0.8; }
}
.card-top .logo {
  width: 80px; height: 80px;
  border-radius: 16px;
  margin-bottom: 1rem;
  position: relative;
  z-index: 1;
  background: #fff;
  padding: 8px;
  object-fit: contain;
}
.card-top .title {
  color: #C9A84C;
  font-size: 1.5rem;
  font-weight: 800;
  position: relative;
  z-index: 1;
}
.card-top .subtitle {
  color: #8ab5a6;
  font-size: 0.9rem;
  margin-top: 0.4rem;
  position: relative;
  z-index: 1;
}
.card-body { padding: 2.5rem 2.5rem 2rem; }

/* Step indicator */
.steps { display: flex; justify-content: center; gap: 8px; margin-bottom: 1.5rem; }
.step-dot {
  width: 12px; height: 12px;
  border-radius: 50%;
  background: #e5e7eb;
  transition: all 0.3s ease;
}
.step-dot.active { background: #C9A84C; transform: scale(1.2); }
.step-dot.done { background: #10b981; }

label { display: block; font-size: 0.85rem; font-weight: 600; color: #374151; margin-bottom: 0.4rem; }
input {
  width: 100%; padding: 0.85rem 1.1rem;
  border: 2px solid #e5e7eb; border-radius: 12px;
  font-size: 1rem; font-family: inherit; outline: none;
  transition: all 0.2s;
}
input:focus { border-color: #C9A84C; box-shadow: 0 0 0 4px rgba(201,168,76,.15); }

/* Código input especial */
.codigo-container {
  display: flex;
  gap: 8px;
  justify-content: center;
  margin: 1.5rem 0;
}
.codigo-input {
  width: 60px !important;
  height: 64px;
  text-align: center;
  font-size: 32px;
  font-weight: 700;
  border: 2px solid #e5e7eb;
  border-radius: 12px;
  transition: all 0.2s;
  padding: 0 !important;
  margin: 0;
}
.codigo-input:focus {
  border-color: #C9A84C;
  box-shadow: 0 0 0 4px rgba(201,168,76,.15);
  transform: scale(1.05);
}
.codigo-input.filled {
  border-color: #10b981;
  background: #ecfdf5;
}

.alert {
  background: #fef2f2; border: 1px solid #fca5a5; color: #991b1b;
  border-radius: 12px; padding: 1rem; font-size: 0.9rem; margin-bottom: 1.25rem;
  display: flex; align-items: center; gap: 8px;
}
.alert-ok {
  background: #dcfce7; border-color: #86efac; color: #166534;
}

.btn {
  width: 100%; padding: 1rem; background: linear-gradient(135deg, #C9A84C 0%, #b8923e 100%); color: #1A2E2A;
  border: none; border-radius: 14px; font-family: inherit;
  font-size: 1rem; font-weight: 700; cursor: pointer;
  transition: all 0.2s; margin-top: 0.5rem;
  position: relative;
  overflow: hidden;
}
.btn::before {
  content: '';
  position: absolute;
  top: 0; left: -100%;
  width: 100%; height: 100%;
  background: linear-gradient(90deg, transparent, rgba(255,255,255,.3), transparent);
  transition: left 0.5s;
}
.btn:hover::before { left: 100%; }
.btn:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(201,168,76,.3); }
.btn:disabled { opacity: 0.6; cursor: not-allowed; transform: none; }

.btn-secondary {
  background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%);
  color: #fff;
}

.footer-note { text-align: center; font-size: 0.75rem; color: #9ca3af; margin-top: 1.5rem; }

/* Countdown */
.countdown {
  text-align: center;
  font-size: 0.85rem;
  color: #6b7280;
  margin: 1rem 0;
}
.countdown-time {
  font-weight: 700;
  color: #1A2E2A;
}
.countdown-time.urgent {
  color: #ef4444;
  animation: blink 1s ease-in-out infinite;
}
@keyframes blink {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.5; }
}

/* Resend link */
.resend {
  text-align: center;
  margin-top: 1rem;
}
.resend a {
  color: #C9A84C;
  text-decoration: none;
  font-weight: 600;
  transition: all 0.2s;
}
.resend a:hover { color: #b8923e; text-decoration: underline; }
.resend a.disabled { color: #9ca3af; pointer-events: none; }

/* Password strength */
.password-strength {
  margin-top: 0.5rem;
  height: 4px;
  background: #e5e7eb;
  border-radius: 2px;
  overflow: hidden;
}
.password-strength-bar {
  height: 100%;
  width: 0%;
  transition: all 0.3s;
  border-radius: 2px;
}
.password-strength-bar.weak { width: 33%; background: #ef4444; }
.password-strength-bar.medium { width: 66%; background: #f59e0b; }
.password-strength-bar.strong { width: 100%; background: #10b981; }
.password-strength-text {
  font-size: 0.75rem;
  margin-top: 0.25rem;
  color: #6b7280;
}
</style>
</head>
<body>
<div class="card">
  <div class="card-top">
    <img src="/assets/logoApp.png" alt="Logo" class="logo">
    <div class="title">🔐 Recuperar Contraseña</div>
    <div class="subtitle"><?= e(getConfig('empresa_sociedad', EMPRESA_SOCIEDAD)) ?></div>
  </div>
  <div class="card-body">

<?php if ($step === 'email'): ?>
  <!-- ═══ PASO 1: SOLICITAR EMAIL ═══ -->
  <div class="steps">
    <div class="step-dot active"></div>
    <div class="step-dot"></div>
    <div class="step-dot"></div>
  </div>

  <?php if ($msg): ?>
  <div class="alert alert-ok">✓ <?= e($msg) ?></div>
  <?php endif; ?>

  <?php if ($error): ?>
  <div class="alert">⚠ <?= e($error) ?></div>
  <?php endif; ?>

  <form method="post">
    <div>
      <label for="email">📧 Email de tu cuenta</label>
      <input type="email" id="email" name="email" required autofocus
             placeholder="tu@email.com"
             value="<?= e($email) ?>">
    </div>

    <button type="submit" class="btn" id="btnEnviar">
      Enviar Código de Recuperación →
    </button>
  </form>

  <div class="footer-note">
    Te enviaremos un código de 6 dígitos que caduca en 10 minutos.
  </div>

<?php elseif ($step === 'codigo'): ?>
  <!-- ═══ PASO 2: INTRODUCIR CÓDIGO ═══ -->
  <div class="steps">
    <div class="step-dot done"></div>
    <div class="step-dot active"></div>
    <div class="step-dot"></div>
  </div>

  <?php if ($error): ?>
  <div class="alert" style="background: #fef2f2; border-color: #ef4444; color: #991b1b;">
    <strong>⚠ ERROR:</strong> <?= e($error) ?>
  </div>
  <?php endif; ?>

  <!-- DEBUG: Información visible siempre en desarrollo -->
  <div class="alert" style="background: #e0f2fe; border-color: #7dd3fc; color: #075985; font-size: 0.75rem; word-break: break-all;">
    <strong>DEBUG:</strong><br>
    <br>
    <strong>Step:</strong> <?= e($step) ?><br>
    <strong>REQUEST_METHOD:</strong> <?= e($_SERVER['REQUEST_METHOD']) ?><br>
    <br>
    <strong>Sesión:</strong><br>
    Usuario ID: <?= isset($_SESSION['reset_usuario_id']) ? (int)$_SESSION['reset_usuario_id'] : 'NO' ?><br>
    Email: <?= e($_SESSION['reset_email'] ?? 'NO') ?><br>
    <br>
    <strong>POST Raw:</strong><br>
    <?php if ($debugPOSTRaw): ?>
    <?= e(print_r($debugPOSTRaw, true)) ?>
    <?php else: ?>
    <em>No es POST (primera carga GET)</em>
    <?php endif; ?>
    <br>
    <strong>Token en BD:</strong><br>
    <?php
    if (!empty($_SESSION['reset_usuario_id'])) {
        $stDebug = $db->prepare("SELECT id, expira_en, usado FROM password_reset_tokens WHERE usuario_id = ? AND usado = 0 ORDER BY creado_en DESC LIMIT 1");
        $stDebug->execute([$_SESSION['reset_usuario_id']]);
        $tokenDebug = $stDebug->fetch();
        if ($tokenDebug) {
            echo "✅ Token existe, expira: " . e($tokenDebug['expira_en']) . ", usado: " . (int)$tokenDebug['usado'];
        } else {
            echo "❌ No hay tokens válidos en BD";
        }
    }
    ?>
  </div>

  <div style="text-align: center;">
    <p style="color: #6b7280; font-size: 0.9rem; margin-bottom: 0.5rem;">
      Hemos enviado un código a:
    </p>
    <p style="font-weight: 600; color: #1A2E2A; margin-bottom: 1rem;">
      📧 <?= e($_SESSION['reset_email'] ?? '') ?>
    </p>
  </div>

  <form method="post" id="codigoForm">
    <input type="hidden" name="step" value="verificar_codigo">

    <label style="text-align: center;">Introduce el código de 6 dígitos</label>

    <div class="codigo-container">
      <?php for ($i = 0; $i < 6; $i++): ?>
      <input type="text" class="codigo-input" name="codigo[]" maxlength="1"
             pattern="[0-9]" inputmode="numeric"
             id="codigo-<?= $i ?>" required>
      <?php endfor; ?>
    </div>

    <div class="countdown">
      El código caduca en <span class="countdown-time" id="countdown">10:00</span>
    </div>

    <button type="submit" class="btn" id="btnVerificar" disabled>
      ✓ Verificar Código
    </button>
  </form>

  <div class="resend">
    <a href="#" id="reenviarLink" onclick="reenviarCodigo(); return false;">
      🔄 Reenviar código
    </a>
  </div>

<?php elseif ($step === 'nueva'): ?>
  <!-- ═══ PASO 3: NUEVA CONTRASEÑA ═══ -->
  <div class="steps">
    <div class="step-dot done"></div>
    <div class="step-dot done"></div>
    <div class="step-dot active"></div>
  </div>

  <?php if ($error): ?>
  <div class="alert">⚠ <?= e($error) ?></div>
  <?php endif; ?>

  <div style="text-align: center; margin-bottom: 1.5rem;">
    <p style="color: #10b981; font-weight: 600;">
      ✓ Código verificado correctamente
    </p>
    <p style="color: #6b7280; font-size: 0.9rem;">
      Ahora establece tu nueva contraseña
    </p>
  </div>

  <form method="post" id="nuevaPassForm">
    <input type="hidden" name="step" value="guardar_nueva">

    <div>
      <label for="password">🔑 Nueva contraseña</label>
      <input type="password" id="password" name="password" required
             autocomplete="new-password" minlength="8"
             placeholder="Mínimo 8 caracteres">
      <div class="password-strength">
        <div class="password-strength-bar" id="strengthBar"></div>
      </div>
      <div class="password-strength-text" id="strengthText"></div>
    </div>

    <div style="margin-top: 1rem;">
      <label for="password_confirm">🔒 Confirmar contraseña</label>
      <input type="password" id="password_confirm" name="password_confirm" required
             autocomplete="new-password"
             placeholder="Repite la contraseña">
    </div>

    <button type="submit" class="btn" id="btnGuardar" disabled>
      Guardar Nueva Contraseña
    </button>
  </form>

  <div class="footer-note">
    Asegúrate de guardar la contraseña en un lugar seguro.
  </div>

<?php endif; ?>

  </div>
</div>

<script>
<?php if ($step === 'codigo'): ?>
// ─── Manejo del código de 6 dígitos ──────────────────────────
const inputs = document.querySelectorAll('.codigo-input');
const btnVerificar = document.getElementById('btnVerificar');
const form = document.getElementById('codigoForm');

// Auto-focus al primer input
if (inputs.length > 0) {
  inputs[0].focus();
}

inputs.forEach((input, index) => {
  input.addEventListener('input', (e) => {
    const value = e.target.value.replace(/[^0-9]/g, '');
    e.target.value = value;

    if (value) {
      e.target.classList.add('filled');
      if (index < 5 && value) {
        inputs[index + 1].focus();
      }
    } else {
      e.target.classList.remove('filled');
    }

    // Habilitar botón cuando todos están llenos
    const allFilled = Array.from(inputs).every(i => i.value.length === 1);
    btnVerificar.disabled = !allFilled;
  });

  input.addEventListener('keydown', (e) => {
    if (e.key === 'Backspace' && !e.target.value && index > 0) {
      inputs[index - 1].focus();
      inputs[index - 1].value = '';
      inputs[index - 1].classList.remove('filled');
    }
  });

  // Pegar código completo
  input.addEventListener('paste', (e) => {
    e.preventDefault();
    const paste = (e.clipboardData || window.clipboardData).getData('text');
    const digits = paste.replace(/[^0-9]/g, '').slice(0, 6);

    digits.split('').forEach((d, i) => {
      if (inputs[i]) {
        inputs[i].value = d;
        inputs[i].classList.add('filled');
      }
    });

    const allFilled = Array.from(inputs).every(i => i.value.length === 1);
    if (allFilled) {
      btnVerificar.disabled = false;
    }
  });
});

// ─── Countdown ───────────────────────────────────────────────
let timeLeft = 600;
const countdownEl = document.getElementById('countdown');

function updateCountdown() {
  const mins = Math.floor(timeLeft / 60);
  const secs = timeLeft % 60;
  countdownEl.textContent = `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;

  if (timeLeft <= 60) {
    countdownEl.classList.add('urgent');
  }

  if (timeLeft > 0) {
    timeLeft--;
    setTimeout(updateCountdown, 1000);
  } else {
    document.getElementById('reenviarLink').classList.remove('disabled');
    countdownEl.textContent = 'EXPIRADO';
  }
}
updateCountdown();

// ─── Reenviar código ─────────────────────────────────────────
function reenviarCodigo() {
  const link = document.getElementById('reenviarLink');
  if (link.classList.contains('disabled')) return;

  link.classList.add('disabled');
  link.textContent = '⏳ Enviando...';

  fetch('/recuperar.php?action=reenviar', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' }
  })
  .then(r => r.json())
  .then(data => {
    if (data.ok) {
      link.textContent = '✓ Código reenviado';
      timeLeft = 600;
      updateCountdown();
      setTimeout(() => {
        link.textContent = '🔄 Reenviar código';
        link.classList.remove('disabled');
      }, 3000);
    } else {
      link.textContent = 'Error al reenviar';
    }
  })
  .catch(() => {
    link.textContent = 'Error de conexión';
  });
}

<?php elseif ($step === 'nueva'): ?>
// ─── Password strength meter ─────────────────────────────────
const passwordInput = document.getElementById('password');
const strengthBar = document.getElementById('strengthBar');
const strengthText = document.getElementById('strengthText');

passwordInput.addEventListener('input', () => {
  const pwd = passwordInput.value;
  let strength = 0;

  if (pwd.length >= 8) strength++;
  if (pwd.length >= 12) strength++;
  if (/[a-z]/.test(pwd) && /[A-Z]/.test(pwd)) strength++;
  if (/\d/.test(pwd)) strength++;
  if (/[^a-zA-Z0-9]/.test(pwd)) strength++;

  strengthBar.className = 'password-strength-bar';
  if (strength <= 2) {
    strengthBar.classList.add('weak');
    strengthText.textContent = 'Contraseña débil';
    strengthText.style.color = '#ef4444';
  } else if (strength <= 3) {
    strengthBar.classList.add('medium');
    strengthText.textContent = 'Contraseña media';
    strengthText.style.color = '#f59e0b';
  } else {
    strengthBar.classList.add('strong');
    strengthText.textContent = 'Contraseña fuerte ✓';
    strengthText.style.color = '#10b981';
  }
});

// Validar coincidencia
const confirmInput = document.getElementById('password_confirm');
const btnGuardar = document.getElementById('btnGuardar');

function checkMatch() {
  const match = passwordInput.value && passwordInput.value === confirmInput.value;
  btnGuardar.disabled = !match;
  confirmInput.style.borderColor = match ? '#10b981' : '#e5e7eb';
}

passwordInput.addEventListener('input', checkMatch);
confirmInput.addEventListener('input', checkMatch);

<?php endif; ?>
</script>
</body>
</html>

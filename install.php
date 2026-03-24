<?php
/**
 * ============================================================
 *  INSTALADOR — Libro Contable Autónomo
 *  Sube este archivo a la raíz de /contable/ y ábrelo en el
 *  navegador. Se autodestruye al finalizar.
 * ============================================================
 */

define('APP_VERSION', file_exists(__DIR__ . '/VERSION') ? trim(file_get_contents(__DIR__ . '/VERSION')) : '1.5.1');
define('CONFIG_FILE', __DIR__ . '/config/database.php');
define('LOCK_FILE',   __DIR__ . '/config/.installed');
define('SQL_FILE',    __DIR__ . '/config/install.sql');

// ── Si ya está instalado, bloquear ────────────────────────
if (file_exists(LOCK_FILE)) {
    die(renderBlocked());
}

session_start();
$step   = (int)($_GET['step'] ?? 1);
$errors = [];
$data   = $_SESSION['install_data'] ?? [];

// ── Procesar pasos ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if ($step === 1) {
        // Verificar requisitos
        $step = 2;
        header('Location: ?step=2'); exit;
    }

    if ($step === 2) {
        // Datos de BD
        $d = [
            'db_host'  => trim($_POST['db_host']  ?? 'localhost'),
            'db_name'  => trim($_POST['db_name']  ?? ''),
            'db_user'  => trim($_POST['db_user']  ?? ''),
            'db_pass'  => $_POST['db_pass'] ?? '',
        ];
        // Validar conexión
        if (!$d['db_name'] || !$d['db_user']) {
            $errors[] = 'El nombre de BD y el usuario son obligatorios.';
        } else {
            try {
                $pdo = new PDO(
                    "mysql:host={$d['db_host']};charset=utf8mb4",
                    $d['db_user'], $d['db_pass'],
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                );
                $_SESSION['install_data'] = array_merge($data, $d);
                $_SESSION['install_pdo_ok'] = true;
                header('Location: ?step=3'); exit;
            } catch (PDOException $e) {
                $errors[] = 'No se pudo conectar a MySQL: ' . $e->getMessage();
            }
        }
    }

    if ($step === 3) {
        // Datos de empresa + admin
        $d = [
            'empresa_nombre'   => trim($_POST['empresa_nombre']   ?? ''),
            'empresa_sociedad' => trim($_POST['empresa_sociedad'] ?? ''),
            'empresa_cif'      => trim($_POST['empresa_cif']      ?? ''),
            'empresa_dir1'     => trim($_POST['empresa_dir1']     ?? ''),
            'empresa_dir2'     => trim($_POST['empresa_dir2']     ?? ''),
            'empresa_tel'      => trim($_POST['empresa_tel']      ?? ''),
            'empresa_email'    => trim($_POST['empresa_email']    ?? ''),
            'empresa_web'      => trim($_POST['empresa_web']      ?? ''),
            'empresa_banco'    => trim($_POST['empresa_banco']    ?? ''),
            'empresa_iban'     => trim($_POST['empresa_iban']     ?? ''),
            'admin_user'       => trim($_POST['admin_user']       ?? ''),
            'admin_pass'       => $_POST['admin_pass'] ?? '',
            'admin_pass2'      => $_POST['admin_pass2'] ?? '',
        ];
        if (!$d['empresa_nombre']) $errors[] = 'El nombre es obligatorio.';
        if (!$d['admin_user'])     $errors[] = 'El usuario administrador es obligatorio.';
        if (strlen($d['admin_pass']) < 8) $errors[] = 'La contraseña debe tener al menos 8 caracteres.';
        if ($d['admin_pass'] !== $d['admin_pass2']) $errors[] = 'Las contraseñas no coinciden.';
        if (!$errors) {
            $_SESSION['install_data'] = array_merge($data, $d);
            header('Location: ?step=4'); exit;
        }
    }

    if ($step === 4) {
        // INSTALAR
        $d = $_SESSION['install_data'];
        try {
            // 1. Conectar y crear BD si no existe
            $pdo = new PDO(
                "mysql:host={$d['db_host']};charset=utf8mb4",
                $d['db_user'], $d['db_pass'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$d['db_name']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $pdo->exec("USE `{$d['db_name']}`");

            // 2. Crear tablas (seleccionar BD primero, luego ejecutar SQL)
            $pdo->exec("USE `{$d['db_name']}`");
            $sql = file_get_contents(SQL_FILE);
            // Ejecutar sentencias una a una, ignorando comentarios y sentencias SET
            foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
                // Saltar líneas vacías, comentarios SQL y sentencias SET (NAMES, FOREIGN_KEY_CHECKS, etc.)
                if (!$stmt
                    || preg_match('/^--/', $stmt)
                    || preg_match('/^\s*SET\s+/i', $stmt)
                ) {
                    continue;
                }
                try { $pdo->exec($stmt); } catch (PDOException $e) { /* Ignorar si ya existe */ }
            }

            // 3. Crear tabla de usuarios (para login)
            $pdo->exec("CREATE TABLE IF NOT EXISTS usuarios (
                id       INT AUTO_INCREMENT PRIMARY KEY,
                username VARCHAR(80) NOT NULL UNIQUE,
                password VARCHAR(255) NOT NULL,
                nombre   VARCHAR(150),
                ultimo_acceso DATETIME,
                creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            // 4. Insertar admin
            $hash = password_hash($d['admin_pass'], PASSWORD_BCRYPT);
            $st = $pdo->prepare("INSERT IGNORE INTO usuarios (username, password, nombre) VALUES (?,?,?)");
            $st->execute([$d['admin_user'], $hash, $d['empresa_nombre']]);

            // 5. Generar SECRET_KEY aleatoria
            $secret = bin2hex(random_bytes(32));

            // 6. Escribir config/database.php
            $configContent = generateConfig($d, $secret);
            if (!is_dir(__DIR__ . '/config')) mkdir(__DIR__ . '/config', 0755, true);
            file_put_contents(CONFIG_FILE, $configContent);
            chmod(CONFIG_FILE, 0600);

            // 7. Generar .htaccess con protección extra
            generateHtaccess();

            // 8. Crear archivo de lock
            file_put_contents(LOCK_FILE, date('Y-m-d H:i:s') . "\nInstalled by: " . $d['admin_user']);
            chmod(LOCK_FILE, 0600);

            // 9. Limpiar sesión
            session_destroy();

            header('Location: ?step=5'); exit;

        } catch (Exception $e) {
            $errors[] = 'Error durante la instalación: ' . $e->getMessage();
        }
    }
}

// ── Generar config/database.php ───────────────────────────
function generateConfig(array $d, string $secret): string {
    $host    = addslashes($d['db_host']);
    $name    = addslashes($d['db_name']);
    $user    = addslashes($d['db_user']);
    $pass    = addslashes($d['db_pass']);
    $nombre  = addslashes($d['empresa_nombre']);
    $sociedad= addslashes($d['empresa_sociedad']);
    $cif     = addslashes($d['empresa_cif']);
    $dir1    = addslashes($d['empresa_dir1']);
    $dir2    = addslashes($d['empresa_dir2']);
    $tel     = addslashes($d['empresa_tel']);
    $email   = addslashes($d['empresa_email']);
    $web     = addslashes($d['empresa_web']);
    $banco   = addslashes($d['empresa_banco']);
    $iban    = addslashes($d['empresa_iban']);

    return <<<PHP
<?php
// ── Generado automáticamente por el instalador · NO EDITAR A MANO ──
// Instalado: {$_SERVER['SERVER_NAME']} · {$d['db_host']}

define('APP_VERSION', file_exists(__DIR__ . '/../VERSION') ? trim(file_get_contents(__DIR__ . '/../VERSION')) : '1.5.1');

define('DB_HOST',    '$host');
define('DB_NAME',    '$name');
define('DB_USER',    '$user');
define('DB_PASS',    '$pass');
define('DB_CHARSET', 'utf8mb4');
define('SECRET_KEY', '$secret');

define('EMPRESA_NOMBRE',   '$nombre');
define('EMPRESA_SOCIEDAD', '$sociedad');
define('EMPRESA_CIF',      '$cif');
define('EMPRESA_DIR1',     '$dir1');
define('EMPRESA_DIR2',     '$dir2');
define('EMPRESA_TEL',      '$tel');
define('EMPRESA_EMAIL',    '$email');
define('EMPRESA_WEB',      '$web');
define('EMPRESA_BANCO',    '$banco');
define('EMPRESA_IBAN',     '$iban');
define('EMPRESA_IVA',      0.21);
define('EMPRESA_IRPF',     0.15);

function getDB(): PDO {
    static \$pdo = null;
    if (\$pdo === null) {
        \$dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
        \$pdo = new PDO(\$dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }
    return \$pdo;
}
PHP;
}

// ── Generar .htaccess con seguridad completa ──────────────
function generateHtaccess(): void {
    $htaccess = <<<HTACCESS
# ── Generado por el instalador ──────────────────────────────
Options -Indexes
ServerSignature Off

# Bloquear acceso directo a archivos sensibles
<FilesMatch "\.(sql|md|log|lock|env|ini|sh|bak)$">
    Order Allow,Deny
    Deny from all
</FilesMatch>

# Bloquear carpeta config completamente
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteRule ^config/  - [F,L]
    RewriteRule ^install\.php$ - [F,L]
</IfModule>

# Cabeceras de seguridad
<IfModule mod_headers.c>
    Header always set X-Content-Type-Options  "nosniff"
    Header always set X-Frame-Options         "SAMEORIGIN"
    Header always set X-XSS-Protection        "1; mode=block"
    Header always set Referrer-Policy         "strict-origin-when-cross-origin"
    Header always set Permissions-Policy      "geolocation=(), microphone=(), camera=()"
</IfModule>

# Evitar acceso a archivos ocultos
<FilesMatch "^\.">
    Order Allow,Deny
    Deny from all
</FilesMatch>
HTACCESS;

    file_put_contents(__DIR__ . '/.htaccess', $htaccess);

    // Proteger carpeta config con su propio .htaccess
    if (!is_dir(__DIR__ . '/config')) mkdir(__DIR__ . '/config', 0755, true);
    file_put_contents(__DIR__ . '/config/.htaccess',
        "Order Allow,Deny\nDeny from all\n");
}

// ── Verificar requisitos ──────────────────────────────────
function checkRequirements(): array {
    $checks = [];
    $checks[] = ['PHP ' . PHP_VERSION, version_compare(PHP_VERSION, '8.0', '>='), 'Requiere PHP 8.0+'];
    $checks[] = ['Extensión PDO',    extension_loaded('pdo'),       'Necesaria para la BD'];
    $checks[] = ['Extensión PDO MySQL', extension_loaded('pdo_mysql'), 'Necesaria para MySQL'];
    $checks[] = ['Extensión OpenSSL',   extension_loaded('openssl'),   'Para claves seguras'];
    $checks[] = ['Extensión mbstring',  extension_loaded('mbstring'),  'Para texto UTF-8'];
    $checks[] = ['Carpeta /config escribible',
        is_writable(__DIR__ . '/config') || (!file_exists(__DIR__ . '/config') && is_writable(__DIR__)),
        'Necesaria para guardar configuración'];
    return $checks;
}

function allPassed(array $checks): bool {
    foreach ($checks as $c) if (!$c[1]) return false;
    return true;
}

// ── Página bloqueada ──────────────────────────────────────
function renderBlocked(): string {
    return <<<HTML
<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">
<title>Ya instalado</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700&display=swap" rel="stylesheet">
<style>body{font-family:Inter,sans-serif;background:#1A2E2A;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}
.box{background:#fff;border-radius:16px;padding:3rem;text-align:center;max-width:440px;box-shadow:0 20px 60px rgba(0,0,0,.3)}
.icon{font-size:3rem;margin-bottom:1rem} h2{color:#1A2E2A;margin-bottom:.5rem} p{color:#6b7280;font-size:.9rem}
.btn{display:inline-block;margin-top:1.5rem;background:#C9A84C;color:#1A2E2A;padding:.75rem 2rem;border-radius:8px;text-decoration:none;font-weight:700}</style>
</head><body><div class="box">
<div class="icon">🔒</div>
<h2>Instalación completada</h2>
<p>El instalador ha sido bloqueado por seguridad.<br>
Si necesitas reinstalar, elimina el archivo <code>config/.installed</code> desde FTP.</p>
<a href="index.php" class="btn">Ir a la aplicación →</a>
</div></body></html>
HTML;
}

// ════════════════════════════════════════════════════════════
// VISTAS HTML
// ════════════════════════════════════════════════════════════
$data = $_SESSION['install_data'] ?? [];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Instalación — Libro Contable</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
*, *::before, *::after { box-sizing: border-box; }
:root {
  --verde:  #1A2E2A;
  --verde-m:#2D5245;
  --verde-a:#3E7B64;
  --gold:   #C9A84C;
  --bg:     #0f1e1b;
}
body {
  font-family: 'Inter', sans-serif;
  background: var(--bg);
  min-height: 100vh;
  display: flex; align-items: center; justify-content: center;
  padding: 2rem;
  background-image: radial-gradient(ellipse at 20% 50%, rgba(45,82,69,.4) 0%, transparent 60%),
                    radial-gradient(ellipse at 80% 20%, rgba(201,168,76,.1) 0%, transparent 50%);
}
.installer {
  background: #fff;
  border-radius: 20px;
  width: 100%; max-width: 600px;
  box-shadow: 0 30px 80px rgba(0,0,0,.4);
  overflow: hidden;
}

/* Header */
.inst-header {
  background: var(--verde);
  padding: 2rem 2.5rem 1.5rem;
  border-bottom: 3px solid var(--gold);
}
.inst-header .logo { color: var(--gold); font-size: 1.6rem; font-weight: 700; }
.inst-header .sub  { color: #8ab5a6; font-size: .85rem; margin-top: .25rem; }

/* Steps bar */
.steps {
  display: flex; padding: 0;
  background: var(--verde-m);
}
.step-item {
  flex: 1; text-align: center; padding: .75rem .5rem;
  font-size: .72rem; color: #6a9488; font-weight: 500;
  position: relative;
  transition: all .2s;
}
.step-item.active { color: var(--gold); font-weight: 700; }
.step-item.done   { color: #4caf82; }
.step-item.active::after {
  content: ''; position: absolute; bottom: 0; left: 0; right: 0;
  height: 3px; background: var(--gold);
}
.step-num {
  display: inline-flex; align-items: center; justify-content: center;
  width: 22px; height: 22px; border-radius: 50%;
  background: rgba(255,255,255,.1); font-size: .7rem;
  margin-bottom: .25rem; font-weight: 700;
}
.step-item.active .step-num { background: var(--gold); color: var(--verde); }
.step-item.done   .step-num { background: #4caf82; color: #fff; }
.step-item.done   .step-num::before { content: '✓'; }

/* Body */
.inst-body { padding: 2.5rem; }
.inst-body h2 { font-size: 1.25rem; font-weight: 700; color: var(--verde); margin-bottom: .35rem; }
.inst-body .desc { color: #6b7280; font-size: .87rem; margin-bottom: 1.75rem; }

/* Form */
label { display: block; font-size: .8rem; font-weight: 600; color: #374151; margin-bottom: .3rem; }
input[type=text], input[type=email], input[type=password], input[type=url] {
  width: 100%; padding: .6rem .9rem; border: 1.5px solid #d1d5db;
  border-radius: 8px; font-size: .9rem; font-family: inherit;
  transition: border-color .15s, box-shadow .15s; outline: none;
}
input:focus { border-color: var(--verde-a); box-shadow: 0 0 0 3px rgba(62,123,100,.15); }
input.error { border-color: #ef4444; }
.field { margin-bottom: 1.1rem; }
.field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
.field-hint { font-size: .75rem; color: #9ca3af; margin-top: .3rem; }

/* Requirement checks */
.req-list { list-style: none; padding: 0; margin: 0; }
.req-list li {
  display: flex; align-items: center; gap: .75rem;
  padding: .6rem .9rem; border-radius: 8px; font-size: .875rem;
  margin-bottom: .4rem;
}
.req-list li.ok   { background: #f0fdf4; color: #166534; }
.req-list li.fail { background: #fef2f2; color: #991b1b; }
.req-list li .check { font-size: 1rem; }

/* Alerts */
.alert-err {
  background: #fef2f2; border: 1px solid #fca5a5; color: #991b1b;
  border-radius: 8px; padding: .75rem 1rem; font-size: .85rem;
  margin-bottom: 1.25rem;
}
.alert-err ul { margin: .4rem 0 0 1.2rem; }

/* Buttons */
.btn-next {
  width: 100%; padding: .85rem; background: var(--verde-a);
  color: #fff; border: none; border-radius: 10px; font-family: inherit;
  font-size: 1rem; font-weight: 600; cursor: pointer;
  transition: background .15s; margin-top: .5rem;
}
.btn-next:hover { background: var(--verde-m); }
.btn-next:disabled { background: #9ca3af; cursor: not-allowed; }
.btn-next.gold { background: var(--gold); color: var(--verde); }
.btn-next.gold:hover { background: #b8923e; }

/* Success */
.success-icon { font-size: 4rem; text-align: center; margin-bottom: 1rem; }
.success-list { background: #f0fdf4; border-radius: 10px; padding: 1rem 1.25rem; margin: 1.25rem 0; }
.success-list li { font-size: .85rem; color: #166534; margin-bottom: .4rem; padding-left: .3rem; }
.warn-box {
  background: #fffbeb; border: 1px solid #fcd34d; border-radius: 8px;
  padding: .75rem 1rem; font-size: .82rem; color: #92400e; margin: 1rem 0;
}
.warn-box strong { display: block; margin-bottom: .3rem; }

.pass-strength { height: 4px; border-radius: 2px; margin-top: .4rem; transition: all .3s; }
</style>
</head>
<body>

<div class="installer">

  <!-- Header -->
  <div class="inst-header">
    <div class="logo">📒 Libro Contable</div>
    <div class="sub">Asistente de instalación v<?= APP_VERSION ?></div>
  </div>

  <!-- Steps -->
  <?php
  $steps = ['Bienvenida','Base de datos','Tu empresa','Instalar','¡Listo!'];
  ?>
  <div class="steps">
    <?php foreach ($steps as $i => $label):
      $n = $i + 1;
      $cls = $n === $step ? 'active' : ($n < $step ? 'done' : '');
    ?>
    <div class="step-item <?= $cls ?>">
      <div class="step-num"><?= $n ?></div>
      <div><?= $label ?></div>
    </div>
    <?php endforeach; ?>
  </div>

  <div class="inst-body">

  <?php if (!empty($errors)): ?>
  <div class="alert-err">
    <strong>⚠ Hay errores que debes corregir:</strong>
    <ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
  </div>
  <?php endif; ?>

  <!-- ═══ STEP 1: Bienvenida + Requisitos ═══ -->
  <?php if ($step === 1):
    $checks = checkRequirements();
    $allOk  = allPassed($checks);
  ?>
  <h2>Bienvenido al instalador</h2>
  <p class="desc">Este asistente configurará la aplicación en pocos minutos. Antes comprobamos que tu servidor cumple los requisitos.</p>

  <ul class="req-list">
    <?php foreach ($checks as [$name, $ok, $desc]): ?>
    <li class="<?= $ok ? 'ok' : 'fail' ?>">
      <span class="check"><?= $ok ? '✅' : '❌' ?></span>
      <span><strong><?= htmlspecialchars($name) ?></strong><br><small><?= htmlspecialchars($desc) ?></small></span>
    </li>
    <?php endforeach; ?>
  </ul>

  <?php if ($allOk): ?>
  <form method="post">
    <button type="submit" class="btn-next" style="margin-top:1.5rem">
      Continuar → Base de datos
    </button>
  </form>
  <?php else: ?>
  <p style="color:#991b1b;font-size:.85rem;margin-top:1rem">
    ⚠ Tu servidor no cumple todos los requisitos. Contacta con WebEmpresa para activar las extensiones faltantes.
  </p>
  <?php endif; ?>


  <!-- ═══ STEP 2: Base de datos ═══ -->
  <?php elseif ($step === 2): ?>
  <h2>Conexión a la base de datos</h2>
  <p class="desc">Introduce los datos de MySQL. Los encuentras en tu panel de WebEmpresa → <strong>Bases de datos</strong>.</p>

  <form method="post">
    <div class="field">
      <label>Servidor MySQL</label>
      <input type="text" name="db_host" value="<?= htmlspecialchars($data['db_host'] ?? 'localhost') ?>" placeholder="localhost">
      <div class="field-hint">En WebEmpresa generalmente es <code>localhost</code></div>
    </div>
    <div class="field">
      <label>Nombre de la base de datos *</label>
      <input type="text" name="db_name" required value="<?= htmlspecialchars($data['db_name'] ?? '') ?>" placeholder="ej: contable2026">
    </div>
    <div class="field-row">
      <div class="field">
        <label>Usuario MySQL *</label>
        <input type="text" name="db_user" required value="<?= htmlspecialchars($data['db_user'] ?? '') ?>" placeholder="usuario_mysql">
      </div>
      <div class="field">
        <label>Contraseña MySQL</label>
        <input type="password" name="db_pass" value="" placeholder="••••••••" autocomplete="off">
      </div>
    </div>
    <button type="submit" class="btn-next">Verificar conexión →</button>
  </form>


  <!-- ═══ STEP 3: Datos empresa + admin ═══ -->
  <?php elseif ($step === 3): ?>
  <h2>Tu empresa y cuenta de acceso</h2>
  <p class="desc">Estos datos aparecerán en tus facturas y en el panel de acceso.</p>

  <form method="post" id="form3">
    <div class="field-row">
      <div class="field">
        <label>Tu nombre completo *</label>
        <input type="text" name="empresa_nombre" required value="<?= htmlspecialchars($data['empresa_nombre'] ?? '') ?>" placeholder="Nelson Ariel Gil">
      </div>
      <div class="field">
        <label>Nombre comercial / Sociedad</label>
        <input type="text" name="empresa_sociedad" value="<?= htmlspecialchars($data['empresa_sociedad'] ?? '') ?>" placeholder="Sinergia">
      </div>
    </div>
    <div class="field-row">
      <div class="field">
        <label>CIF / NIF</label>
        <input type="text" name="empresa_cif" value="<?= htmlspecialchars($data['empresa_cif'] ?? '') ?>" placeholder="04310609H">
      </div>
      <div class="field">
        <label>Teléfono</label>
        <input type="text" name="empresa_tel" value="<?= htmlspecialchars($data['empresa_tel'] ?? '') ?>" placeholder="628 68 36 64">
      </div>
    </div>
    <div class="field">
      <label>Dirección (línea 1)</label>
      <input type="text" name="empresa_dir1" value="<?= htmlspecialchars($data['empresa_dir1'] ?? '') ?>" placeholder="Urb. Jacarandas - Casa 13">
    </div>
    <div class="field">
      <label>Dirección (ciudad - CP - provincia)</label>
      <input type="text" name="empresa_dir2" value="<?= htmlspecialchars($data['empresa_dir2'] ?? '') ?>" placeholder="Nerja - 29780 - Málaga">
    </div>
    <div class="field-row">
      <div class="field">
        <label>Email</label>
        <input type="email" name="empresa_email" value="<?= htmlspecialchars($data['empresa_email'] ?? '') ?>" placeholder="info@segurizate.info">
      </div>
      <div class="field">
        <label>Web</label>
        <input type="text" name="empresa_web" value="<?= htmlspecialchars($data['empresa_web'] ?? '') ?>" placeholder="www.segurizate.info">
      </div>
    </div>
    <div class="field-row">
      <div class="field">
        <label>Banco</label>
        <input type="text" name="empresa_banco" value="<?= htmlspecialchars($data['empresa_banco'] ?? '') ?>" placeholder="Unicaja">
      </div>
      <div class="field">
        <label>IBAN</label>
        <input type="text" name="empresa_iban" value="<?= htmlspecialchars($data['empresa_iban'] ?? '') ?>" placeholder="ES63 2103 3032 55 ...">
      </div>
    </div>

    <hr style="border-color:#e5e7eb;margin:1.5rem 0">
    <p style="font-size:.85rem;color:#374151;font-weight:600;margin-bottom:1rem">🔐 Cuenta de administrador</p>

    <div class="field">
      <label>Usuario *</label>
      <input type="text" name="admin_user" required value="<?= htmlspecialchars($data['admin_user'] ?? '') ?>" placeholder="admin" autocomplete="username">
      <div class="field-hint">Con este usuario iniciarás sesión en la aplicación</div>
    </div>
    <div class="field-row">
      <div class="field">
        <label>Contraseña *</label>
        <input type="password" name="admin_pass" id="pass1" required minlength="8" placeholder="Mín. 8 caracteres" autocomplete="new-password" oninput="checkPass()">
        <div class="pass-strength" id="passStrength"></div>
      </div>
      <div class="field">
        <label>Repetir contraseña *</label>
        <input type="password" name="admin_pass2" id="pass2" required placeholder="••••••••" autocomplete="new-password" oninput="checkPass()">
      </div>
    </div>
    <div id="passMsg" style="font-size:.78rem;margin-top:-.5rem;margin-bottom:1rem"></div>

    <button type="submit" class="btn-next" id="btnStep3">Continuar → Instalar</button>
  </form>
  <script>
  function checkPass() {
    const p1 = document.getElementById('pass1').value;
    const p2 = document.getElementById('pass2').value;
    const bar = document.getElementById('passStrength');
    const msg = document.getElementById('passMsg');
    // Fuerza
    let score = 0;
    if (p1.length >= 8)  score++;
    if (p1.length >= 12) score++;
    if (/[A-Z]/.test(p1)) score++;
    if (/[0-9]/.test(p1)) score++;
    if (/[^A-Za-z0-9]/.test(p1)) score++;
    const colors = ['#ef4444','#f97316','#eab308','#22c55e','#16a34a'];
    bar.style.width = (score * 20) + '%';
    bar.style.background = colors[score - 1] || '#e5e7eb';
    if (p1 && p2 && p1 !== p2) {
      msg.style.color = '#ef4444'; msg.textContent = '⚠ Las contraseñas no coinciden';
    } else if (p1 && p2 && p1 === p2) {
      msg.style.color = '#16a34a'; msg.textContent = '✓ Contraseñas coinciden';
    } else { msg.textContent = ''; }
  }
  </script>


  <!-- ═══ STEP 4: Confirmación + instalar ═══ -->
  <?php elseif ($step === 4):
    $d = $_SESSION['install_data'] ?? [];
  ?>
  <h2>Todo listo para instalar</h2>
  <p class="desc">Revisa el resumen y pulsa <strong>Instalar ahora</strong>.</p>

  <table style="width:100%;font-size:.85rem;border-collapse:collapse;margin-bottom:1.5rem">
    <?php
    $rows = [
      'Servidor BD'     => $d['db_host']        ?? '',
      'Base de datos'   => $d['db_name']         ?? '',
      'Nombre'          => $d['empresa_nombre']  ?? '',
      'Sociedad'        => $d['empresa_sociedad']?? '',
      'CIF'             => $d['empresa_cif']     ?? '',
      'Email'           => $d['empresa_email']   ?? '',
      'Usuario admin'   => $d['admin_user']      ?? '',
      'Contraseña admin'=> '••••••••',
    ];
    $alt = false;
    foreach ($rows as $k => $v):
      $alt = !$alt;
    ?>
    <tr style="background:<?= $alt ? '#f9fafb' : '#fff' ?>">
      <td style="padding:.5rem .75rem;font-weight:600;color:#374151;width:40%"><?= htmlspecialchars($k) ?></td>
      <td style="padding:.5rem .75rem;color:#6b7280"><?= htmlspecialchars($v) ?></td>
    </tr>
    <?php endforeach; ?>
  </table>

  <div class="warn-box">
    <strong>⚠ Lo que hará el instalador:</strong>
    Creará la base de datos, las tablas, guardará tu configuración en <code>config/database.php</code>,
    generará el <code>.htaccess</code> con todas las medidas de seguridad, y
    <strong>se autobloqueará</strong> para que nadie más pueda ejecutarlo.
  </div>

  <form method="post">
    <button type="submit" class="btn-next gold">🚀 Instalar ahora</button>
    <a href="?step=3" style="display:block;text-align:center;margin-top:.75rem;font-size:.83rem;color:#6b7280">← Volver y corregir</a>
  </form>


  <!-- ═══ STEP 5: Éxito ═══ -->
  <?php elseif ($step === 5): ?>
  <div class="success-icon">🎉</div>
  <h2 style="text-align:center">¡Instalación completada!</h2>
  <p class="desc" style="text-align:center">Tu Libro Contable está listo y funcionando.</p>

  <ul class="success-list">
    <li>✅ Base de datos creada con todas las tablas</li>
    <li>✅ Cuenta de administrador configurada</li>
    <li>✅ Archivo <code>config/database.php</code> generado y protegido (permisos 600)</li>
    <li>✅ <code>.htaccess</code> con cabeceras de seguridad aplicado</li>
    <li>✅ Carpeta <code>config/</code> bloqueada para acceso web</li>
    <li>✅ Instalador bloqueado — no se puede volver a ejecutar</li>
  </ul>

  <div class="warn-box">
    <strong>🔐 Recomendaciones de seguridad adicionales:</strong>
    <ul style="margin:.5rem 0 0 1.2rem;font-size:.82rem">
      <li>Borra este archivo <code>install.php</code> por FTP ahora mismo</li>
      <li>Activa HTTPS si aún no lo tienes (WebEmpresa lo incluye gratis)</li>
      <li>Haz una copia de seguridad periódica de la BD desde phpMyAdmin</li>
    </ul>
  </div>

  <a href="index.php" class="btn-next" style="display:block;text-align:center;text-decoration:none;margin-top:1.5rem">
    Ir a la aplicación →
  </a>

  <?php endif; ?>

  </div><!-- /inst-body -->
</div><!-- /installer -->

</body>
</html>

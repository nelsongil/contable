# Skill: php-security

## Activación
Activar este skill cuando vayas a crear, editar o revisar cualquier archivo PHP del proyecto que maneje inputs de usuario, autenticación, uploads, consultas SQL o redirecciones.

---

## Modelo de seguridad del proyecto

### Autenticación
- Sesiones PHP estándar. `session_regenerate_id(true)` en login para prevenir session fixation.
- Cada página protegida verifica `$_SESSION['usuario_id']` a través de `includes/auth.php`.
- `auth.php` se carga automáticamente desde `header.php` — no hace falta incluirlo a mano si se usa `header.php`.
- Las contraseñas usan `password_hash($pass, PASSWORD_BCRYPT)` y se verifican con `password_verify()`.

### Escapado de output
```php
// SIEMPRE — nunca echo directo de variables externas
echo e($variable);           // usa htmlspecialchars($s, ENT_QUOTES, 'UTF-8')

// NUNCA
echo $_GET['nombre'];        // XSS directo
echo $_POST['campo'];        // XSS directo
```

### Inputs seguros
```php
// USAR los helpers de functions.php:
$nombre = post('nombre');         // trim($_POST['nombre']) ?? ''
$id     = (int)get('id');         // cast obligatorio para IDs
$fecha  = post('fecha', '');      // con default

// NUNCA acceder directamente:
$nombre = $_POST['nombre'];       // sin sanitizar
```

### Consultas SQL — PDO obligatorio
```php
// CORRECTO
$st = getDB()->prepare("SELECT id, nombre FROM clientes WHERE id = ?");
$st->execute([$id]);
$row = $st->fetch();

// NUNCA concatenar
$db->query("SELECT * FROM clientes WHERE id = $id");   // SQL injection
```

### Redirecciones
```php
// USAR siempre redirect() — nunca header() directo
redirect('/facturas/');

// NUNCA
header("Location: /facturas/");   // sin exit, puede continuar ejecutando
```

### Uploads de archivos
- Validar `$_FILES['file']['error'] === UPLOAD_ERR_OK`
- Verificar MIME real (no solo extensión): `mime_content_type($tmpFile)`
- Renombrar siempre el archivo (no usar nombre original del usuario)
- Limitar tamaño con `$_FILES['file']['size'] < 5 * 1024 * 1024`
- Solo permitir: `application/pdf`, `image/jpeg`, `image/png`

### Headers de seguridad (`.htaccess`)
Ya configurados en producción:
- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: SAMEORIGIN`
- `X-XSS-Protection: 1; mode=block`
- `Referrer-Policy: strict-origin-when-cross-origin`

### Archivos protegidos por `.htaccess`
- `/config/` — bloqueado completo
- `/backups/` — bloqueado
- `/tmp/` — bloqueado
- `install.php` — bloqueado después de instalar
- Extensiones `.sql`, `.md`, `.log`, `.lock`, `.env`, `.ini`, `.sh`, `.bak`, `.zip` — bloqueadas

---

## Flujo POST obligatorio (CRÍTICO)

```php
<?php
session_start();
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// POST debe procesarse ANTES de header.php (para poder redirect limpio)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = post('nombre');
    // ... validar y guardar ...
    flash('Guardado correctamente');
    redirect('/modulo/');
}

$pageTitle = 'Título';
require_once '../includes/header.php';
?>
<!-- HTML aquí -->
<?php require_once '../includes/footer.php'; ?>
```

---

## Lo que NUNCA debes hacer

| Prohibición | Razón |
|-------------|-------|
| `eval()` | Ejecución de código arbitrario |
| `shell_exec()` / `exec()` con datos de usuario | RCE (Remote Code Execution) |
| `include`/`require` con ruta basada en `$_GET`/`$_POST` | Local File Inclusion |
| MD5 o SHA1 para contraseñas | Algoritmos débiles, rainbow tables |
| `var_dump()` / `print_r()` en producción | Exposición de datos internos |
| `die()` / `exit()` sin `redirect()` previo | Deja la respuesta incompleta |
| `SELECT *` en queries | Expone columnas no esperadas, viola convenciones |
| Mostrar stack traces en producción | Expone rutas y estructura interna |

---

## Gotchas — Errores reales ocurridos en este proyecto

### 1. `session_start()` en archivos standalone (v1.1)
**Problema:** `facturas/ver.php?pdf=1` y `libros/exportar.php?download=1` no pasan por `header.php` — incluyen `functions.php` directamente. Sin `session_start()` al inicio, `flash()` y `$_SESSION` fallan silenciosamente.
**Solución:** Añadir `session_start()` como primera línea en todo archivo que acceda a `$_SESSION` sin pasar por `header.php`.

### 2. `getConfig()` devuelve `true` (bool) para el string `'1'` (v1.5)
**Problema:** `getConfig()` convierte automáticamente `'1'` → `true` y `'0'` → `false`. Si después comparas con `=== '1'`, siempre falla.
```php
// MAL — siempre false aunque DB tenga '1'
if (getConfig('backup_auto', '0') === '1') { ... }

// BIEN
if ((bool)getConfig('backup_auto', false)) { ... }
```

### 3. Proveedor guardado en tabla `clientes` (v1.2)
**Problema:** `proveedores/nuevo.php` usaba `INSERT INTO clientes` en lugar de `proveedores`.
**Regla:** Al escribir queries, verificar siempre el nombre de tabla correcto. No copiar código de clientes a proveedores sin revisar cada query.

### 4. `$pageTitle` asignado después de `header.php` (v1.1)
**Problema:** Si `$pageTitle = 'Nombre'` va después de `require_once header.php`, el título del navegador siempre muestra "Contabilidad" (el default).
**Solución:** `$pageTitle` SIEMPRE antes del `require_once '../includes/header.php'`.

### 5. `header()` sin `exit` tras redirect (patrón general)
**Problema:** `header("Location: ...")` sin `exit` continúa ejecutando el código posterior, incluyendo queries y lógica de negocio.
**Solución:** Usar siempre `redirect()` de `functions.php`, que incluye `exit`.

### 6. Encoding UTF-16 (recurrente, múltiples versiones)
**Problema:** Guardar un archivo con el Bloc de notas de Windows genera UTF-16 con BOM, que causa `json_decode()` a fallar con "No number after minus sign" y errores de parse en PHP.
**Verificación:** `file -i nombre_archivo.php` debe mostrar `charset=utf-8`, nunca `charset=utf-16`.

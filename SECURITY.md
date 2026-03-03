# Seguridad del Proyecto — Libro Contable

Documento con las directrices y medidas de seguridad implementadas.

## Modelo de seguridad
- **Autenticación**: Sesiones PHP estándar. Uso de `session_regenerate_id()` en el login para prevenir fijación de sesión.
- **Autorización**: Cada recurso verifica la existencia de `$_SESSION['usuario_id']`.
- **Protección**: Todas las páginas internas incluyen `includes/auth.php`.
- **Instalador**: El archivo `install.php` se bloquea automáticamente al detectar el archivo `config/.installed`.

## Archivos sensibles
- **`config/database.php`**: Contiene credenciales y la `SECRET_KEY`. Debe tener permisos `600`.
- **`config/.installed`**: Indica que la app ya fue configurada. Permisos `600`.
- **`.htaccess`**: Bloquea el acceso web a la carpeta `config/` y otros archivos críticos (`.sql`, `.md`, `.log`).
- **`.gitignore`**: Asegura que las credenciales locales y el lock file no se suban al repositorio.

## Inputs y outputs
- **Sanitización**: Todos los inputs pasan por `trim()` y cast de tipo (ej. `(int)`).
- **Escapado**: El output HTML se procesa con `htmlspecialchars()` a través del helper `e()`.
- **Consultas**: Uso estricto de PDO *Prepared Statements*.
- **Archivos**: Las subidas se limitan a PDF/JPG/PNG, con límite de tamaño y renombrado automático (evitar nombres originales).

## Headers de seguridad
Configurados vía `.htaccess`:
- `X-Content-Type-Options: nosniff`
- `X-Frame-Options: SAMEORIGIN`
- `X-XSS-Protection: 1; mode=block`
- `Referrer-Policy: strict-origin-when-cross-origin`

## Checklist antes de cada deploy
- [ ] `config/database.php` **NO** está incluido en el repositorio.
- [ ] No existen funciones de depuración (`var_dump`, `print_r`) en el código.
- [ ] No existen credenciales fuera de los archivos de configuración.
- [ ] El instalador está bloqueado (existe `config/.installed`).
- [ ] HTTPS está configurado y activo.
- [ ] Permisos: `755` para directorios, `644` para archivos, `600` para configuraciones.

## Lo que NUNCA debes hacer
- **No subir** configuraciones sensibles a Git.
- **No mostrar** trazas de error (stack traces) en producción.
- **No usar** algoritmos débiles como MD5 o SHA1 para contraseñas.
- **No confiar** en IDs de usuario recibidos sin validación: `(int)get('id')`.
- **No incluir** archivos dinámicamente basados en inputs directos.
- **No usar** la función `eval()`.
- **No ejecutar** comandos de sistema (`shell_exec`, etc.) con datos del usuario.

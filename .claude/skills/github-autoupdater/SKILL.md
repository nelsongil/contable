# Skill: github-autoupdater

## Activación
Activar cuando trabajes con el sistema de actualización automática: `includes/updater.php`, `ajustes/updater.php`, `ajustes/update_process.php`, o al crear nuevas releases en GitHub.

---

## Arquitectura del sistema

### Archivos involucrados

| Archivo | Rol |
|---------|-----|
| `includes/updater.php` | Función `checkForUpdates()` — consulta GitHub API, guarda en `$_SESSION` |
| `includes/header.php` | Llama `checkForUpdates()` en cada página; muestra banner si hay update |
| `ajustes/updater.php` | UI de actualización — muestra pasos, botón "Actualizar ahora" |
| `ajustes/update_process.php` | Endpoint AJAX — ejecuta los 5 pasos de actualización |
| `config/migrations/` | SQLs idempotentes que se ejecutan en el paso `install` |

---

## Flujo de detección (pasivo)

```
Cada carga de página
    ↓ includes/header.php → checkForUpdates()
    ↓ Si ya se comprobó hace < 24h → return (caché en configuracion.last_update_check)
    ↓ Si hay update en $_SESSION hace < 1h → return
    ↓ GET https://api.github.com/repos/nelsongil/contable/releases/latest
    ↓ version_compare(latestVer, APP_VERSION, '>')
    ↓ Si nueva versión → $_SESSION['update_available'] = [version, url, notes, at]
    ↓ Banner verde en header.php
```

**Cache:** `getConfig('last_update_check')` guarda el timestamp Unix. Se actualiza incluso si hay error, para no saturar la API.

**Bypass del caché (debug/testing):**
```
https://contable.nelsongil.com/ajustes/updater.php?force_check=1
```

---

## Flujo de actualización (5 pasos AJAX)

```
Usuario hace clic en "Actualizar ahora"
    ↓
ajustes/updater.php → JavaScript lanza pasos secuenciales
    ↓
POST ajustes/update_process.php?step=backup
    → generateSQLDump() → guarda en backups/backup_pre_update_YYYYMMDD_HHiiss.sql
    ↓
POST ajustes/update_process.php?step=download
    → cURL GET $updateData['url'] (zipball_url de GitHub)
    → guarda en tmp/update/update.zip
    → verifica cabecera PK (ZIP válido)
    ↓
POST ajustes/update_process.php?step=prepare
    → ZipArchive::extractTo(tmp/update/extracted/)
    ↓
POST ajustes/update_process.php?step=install
    → rcopy_recursive(extracted/repo-hash/, raíz_app/, $exclude)
    → ejecuta SQLs de config/migrations/ si existen
    ↓
POST ajustes/update_process.php?step=finalize
    → preg_replace APP_VERSION en config/database.php
    → rrmdir_recursive(tmp/update/)
    → unset($_SESSION['update_available'])
    → log en backups/update_log.txt
```

---

## Archivos protegidos (NO se sobreescriben en actualización)

```php
$exclude = [
    'config/database.php',   // credenciales del usuario
    'config/.installed',     // lock del instalador
    '.htaccess',             // configuración Apache personalizada
    'backups',               // copias de seguridad
    'tmp',                   // temporales
    'assets/logo.png',       // logo subido por el usuario
    '.git',
    '.github',
    'SECURITY.md',
    'CONVENTIONS.md'
];
```

---

## Proceso de release en GitHub (checklist)

```bash
# 1. Verificar que todos los cambios están commiteados
git status

# 2. Commit final con mensaje descriptivo
git add -A
git commit -m "Release vX.Y: descripción"

# 3. Tag semántico
git tag vX.Y

# 4. Push con tags
git push origin main --tags

# 5. Crear release en GitHub con notas de versión
gh release create vX.Y \
  --title "vX.Y — Título descriptivo" \
  --notes "## Novedades\n..."
```

**El ZIP de la release es automático:** GitHub genera el `zipball_url` con el snapshot del repo en ese tag. No hay que subir archivos manualmente.

---

## Versión en `config/database.php`

```php
define('APP_VERSION', '1.5');  // actualizado por el paso finalize
```

- Este archivo **no está en git** (excluido por `.gitignore`)
- El paso `finalize` del updater usa `preg_replace()` para actualizar el número
- La versión en el repo (ejemplo: `database.php.example`) debe mantenerse actualizada para referencia

---

## Gotchas — Errores reales ocurridos

### 1. Backup paso devolvía SQL en lugar de JSON (v1.3 → v1.4)
**Problema:** La versión antigua de `update_process.php` hacía `ob_start() + require exportar.php`. Como `exportar.php` llama `exit` después de enviar headers `Content-Type: application/sql`, el proceso se cortaba y el cliente recibía SQL en lugar de JSON.
**Síntoma:** Error JS "No number after minus sign in JSON at position 1" (el SQL empieza con `--`).
**Solución:** Refactorizar para llamar `generateSQLDump()` directamente desde `functions.php`.
**Estado:** Corregido en v1.4. Si el servidor de producción tiene la versión antigua, requiere subida manual por FTP.

### 2. `generateSQLDump()` no existía en producción al subir `update_process.php`
**Problema:** El nuevo `update_process.php` llama a `generateSQLDump()` que solo existe en `includes/functions.php` v1.4+. Si solo se sube `update_process.php` sin `functions.php`, PHP lanza "Call to undefined function".
**Solución:** Siempre subir ambos archivos juntos cuando se hace fix manual por FTP.

### 3. Encoding UTF-16 en respuesta JSON
**Problema:** Si `includes/updater.php` se guarda en UTF-16 por error, el JSON de la API de GitHub se parsea con caracteres nulos (`\u0000`), causando `json_decode()` fallando con `JSON_ERROR_CTRL_CHAR`.
**Solución implementada:** `str_replace("\0", '', $body)` + `preg_replace('/\x00/', '', $body)` + detección de UTF-16 con `mb_detect_encoding()`.

### 4. GitHub API rate limit (403)
**Síntoma:** `$_SESSION['update_error']` contiene "GitHub API devolvió HTTP 403".
**Causa:** Sin autenticación, la API de GitHub permite 60 requests/hora por IP. Raro en producción con 1 usuario, pero puede ocurrir en desarrollo si se hacen muchas recargas.
**Solución:** La caché de 24h previene esto en uso normal. Para testing usar `?force_check=1` con moderación.

### 5. La actualización no aplica cambios recientes si el tag no está actualizado
**Problema:** Si se hace commit pero no se crea un nuevo tag y release, el ZIP de GitHub sigue apuntando al snapshot anterior.
**Regla:** Todo cambio que deba llegar a producción vía updater requiere tag + `gh release create`.
**Versionado:** v1.4 no incluía los fixes de UI del commit `2e1000b` porque el tag se creó antes. Corregido en v1.5.

### 6. `$_SESSION['update_available']` persiste entre navegaciones
**Diseño:** Una vez que `$_SESSION['update_available']` se guarda, `checkForUpdates()` no lo borra hasta que el check de 24h se cumple y no hay versión nueva. Si hay update disponible, el banner persiste en todas las páginas.
**Para descartar:** El usuario puede cerrar el banner con la X, que guarda `$_SESSION['update_dismissed_version']`. El banner no reaparece hasta la siguiente versión.

---

## Estructura de `$_SESSION['update_available']`

```php
[
    'version' => 'v1.5',                    // tag de GitHub
    'url'     => 'https://...zipball...',   // URL de descarga del ZIP
    'notes'   => 'Texto del changelog',     // body del release de GitHub
    'at'      => 1704067200                 // timestamp Unix del check
]
```

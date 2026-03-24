# Skill: contable-conventions (Maestro)

## Activación
Activar al comienzo de cualquier sesión de trabajo con el proyecto Libro Contable, o cuando se necesite un recordatorio completo de todas las convenciones del proyecto.

Al activarse, leer en este orden:
1. `CONVENTIONS.md` — nomenclatura, BD, PHP, CSS
2. `SECURITY.md` — modelo de seguridad, checklist deploy, prohibiciones
3. `DATABASE.md` — esquema, tipos, relaciones, migraciones
4. `CHANGELOG.md` — historial de versiones y cambios aplicados
5. `AGENTS.md` — arquitectura, flujo de peticiones, funciones clave, reglas para IAs
6. `CLAUDE.md` — instrucciones específicas para Claude Code

---

## El proyecto en una línea

**Libro Contable** es una app PHP procedimental para autónomos españoles. Sin framework. Sin bundler. Sin Composer (excepto `vendor/pdfparser` vendored). PHP 8.0+, MySQL, Bootstrap 5.3 CDN, desplegada en WebEmpresa (Apache).

- **Producción:** `https://contable.nelsongil.com/`
- **BD producción:** `nelsongi_contable` en `localhost` (WebEmpresa)
- **Repo:** `github.com/nelsongil/contable`
- **Versión actual:** v1.5

---

## Mapa del proyecto

```
contable/
├── index.php              ← Dashboard (KPIs anuales + gráfico trimestral)
├── login.php              ← Standalone — no usa header.php
├── install.php            ← Standalone — se bloquea con config/.installed
├── .htaccess              ← Seguridad Apache (bloquea /config/, /backups/, /tmp/)
│
├── includes/
│   ├── functions.php      ← TODOS los helpers: getDB, e(), post(), money(), flash(), redirect(), CRUD
│   ├── auth.php           ← Verificación sesión + logout (?logout=1)
│   ├── header.php         ← HTML + sidebar + CSS tema (auto-incluye auth + functions)
│   ├── footer.php         ← Cierra HTML + Bootstrap JS + Tom Select + modal bsConfirm
│   └── updater.php        ← checkForUpdates() — GitHub API, caché 24h
│
├── facturas/              ← index.php, nueva.php (crear+editar), ver.php (?pdf=1)
├── compras/               ← index.php, nueva.php, importar_pdf.php (AJAX)
├── clientes/              ← index.php, nuevo.php, editar.php
├── proveedores/           ← index.php, nuevo.php, editar.php
├── empleados/             ← index.php, nuevo.php, retenciones.php, modelo111.php
├── libros/                ← index.php, resumen.php, modelo347.php, exportar.php
│
├── ajustes/
│   ├── empresa.php        ← Datos empresa + numeración facturas
│   ├── plantilla.php      ← Colores/logo factura PDF
│   ├── tema.php           ← Colores interfaz
│   ├── empleados.php      ← Toggle módulo empleados
│   ├── backup.php         ← Copias de seguridad (manual + automático)
│   ├── backup_process.php ← AJAX backup
│   ├── updater.php        ← UI actualización
│   └── update_process.php ← AJAX 5 pasos: backup→download→prepare→install→finalize
│
├── config/
│   ├── install.sql        ← DDL completo del esquema
│   ├── database.php       ← ⚠️ CREDENCIALES — nunca en git
│   ├── .installed         ← Lock instalador
│   └── migrations/        ← SQLs idempotentes para el updater
│
├── vendor/pdfparser/      ← smalot/pdfparser vendored (sin Composer)
├── assets/                ← logo.png (subido por usuario, excluido del updater)
└── tools/build_zip.php    ← Utilidad para construir ZIP de release
```

---

## Flujo de petición estándar (CRÍTICO)

```php
<?php
session_start();
require_once '../includes/functions.php';
require_once '../includes/auth.php';

// 1. POST PRIMERO — antes de header.php para poder redirect()
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // validar, guardar...
    flash('Mensaje de éxito');
    redirect('/modulo/');
}

// 2. Luego el HTML
$pageTitle = 'Título de la página';  // ← ANTES de header.php
require_once '../includes/header.php';
?>
<!-- HTML aquí -->
<?php require_once '../includes/footer.php'; ?>
```

**Excepciones:** `login.php`, `facturas/ver.php?pdf=1`, `libros/exportar.php?download=1` son standalone — no usan `header.php`, deben tener `session_start()` propio.

---

## Reglas de nomenclatura

| Elemento | Convención | Ejemplo |
|----------|-----------|---------|
| Variables PHP | `snake_case` | `$cliente_id`, `$base_imponible` |
| Funciones PHP | `camelCase` | `getCliente()`, `siguienteNumeroFactura()` |
| Constantes PHP | `MAYUSCULAS` | `EMPRESA_NOMBRE`, `DB_HOST` |
| Campos BD | `snake_case` | `fecha_vencimiento`, `cuota_iva` |
| Variables CSS | `kebab-case` | `--verde-m`, `--gold` |

---

## Paleta de colores (variables CSS `:root`)

```css
--verde:   #1A2E2A   /* Sidebar, headers de tabla */
--verde-m: #2D5245   /* Hover, card-header */
--verde-a: #3E7B64   /* Botón primary, focus */
--gold:    #C9A84C   /* Acento, botón gold, bordes dorados */
--bg:      #F4F7F5   /* Fondo general */
```

Los colores son sobreescritos en runtime por `getThemeCSS()` si el usuario los ha personalizado desde ajustes/tema.php.

---

## Helpers esenciales de `functions.php`

```php
e($s)                          // htmlspecialchars — SIEMPRE para output HTML
post('campo', $default='')     // $_POST con trim
get('campo', $default='')      // $_GET con trim
money(1234.56)                 // → "1.234,56 €"
moneyInput(1234.56)            // → "1234.56" (para inputs)
getConfig('clave', $default)   // Lee tabla configuracion (caché estática)
setConfig('clave', $valor)     // Escribe tabla configuracion
flash('msg', 'success|danger') // Guarda en $_SESSION para mostrar tras redirect
showFlash()                    // Consume y renderiza alerta Bootstrap
redirect('/ruta/')             // header Location + exit
trimestre('2026-03-15')        // → 1 (Q1)
siguienteNumeroFactura()       // → "F20260001"
generateSQLDump()              // → string SQL del backup completo
```

---

## Módulos opcionales (activables desde ajustes)

| Módulo | Config key | Activación |
|--------|-----------|------------|
| Empleados | `modulo_empleados` | ajustes/empleados.php |

Cuando está activo, el sidebar muestra la sección "Empleados" con 3 ítems.

---

## Confirmaciones y modales

Desde v1.5 no hay `confirm()` ni `alert()` del navegador. Todo usa `bsConfirm()` definida en `footer.php`:

```js
// Uso directo
bsConfirm('¿Confirmar acción?', () => { /* callback */ });

// En enlaces — automático vía data-confirm
<a href="/ruta/accion.php?id=1" data-confirm="¿Confirmar?">Eliminar</a>

// En formularios — automático vía data-confirm
<form method="post" data-confirm="¿Guardar cambios?">
```

---

## Resumen de reglas "nunca"

| Prohibición | Consecuencia |
|-------------|-------------|
| `echo $_GET/$_POST` directamente | XSS |
| Concatenar inputs en SQL | SQL injection |
| `header()` sin `exit` | Continúa ejecutando código |
| `SELECT *` | Viola convenciones, expone datos |
| `FLOAT` para dinero | Imprecisión numérica |
| `DELETE` de clientes/proveedores | Pierde histórico de facturas |
| `eval()` / `shell_exec()` con datos usuario | RCE |
| Guardar en UTF-16 | Rompe json_decode, PHP parse errors |
| Hardcodear colores hex en CSS | Rompe el sistema de temas |
| `die()`/`exit()` sin `redirect()` previo | Respuesta incompleta |

---

## Estado de documentación detectado

### Actualizado:
- `CLAUDE.md` — refleja v1.5, arquitectura correcta
- `AGENTS.md` — sección de reglas para IAs completa, pero la versión dice v1.2 (desactualizada)
- `CONVENTIONS.md` — correcto
- `SECURITY.md` — correcto

### Desactualizado o incompleto:
- **`AGENTS.md`**: dice "Actualizado: 2026-03-02 (v1.2)" pero el proyecto está en v1.5. Falta documentar: módulo empleados, updater, backup automático, bsConfirm, tabla `retenciones_empleados`, `generateSQLDump()`.
- **`DATABASE.md`**: no incluye las tablas `empleados` y `retenciones_empleados` (añadidas en v1.4).
- **`README.md`**: dice "v1.0 (Baseline)", tiene credenciales en texto plano en la primera línea (riesgo si se hace público), no menciona módulos añadidos desde v1.0.
- **`CHANGELOG.md`**: completo hasta v1.5 ✓.

### Ausente:
- No existe `config/migrations/` como directorio (referenciado en el updater y DATABASE.md pero no creado).
- No existe documentación del módulo de backup automático en ningún `.md`.

# AGENTS.md — Libro Contable PHP

Documentación técnica para IAs/LLMs que trabajen con este proyecto.
Actualizado: 2026-03-02 (v1.2)
**CRÍTICO: NUNCA usar UTF-16. Todos los archivos DEBEN ser UTF-8 sin BOM (charset=utf-8).**
**IMPORTANTE: Todos los archivos DEBEN guardarse en UTF-8 sin BOM (Nunca UTF-16).**

---

## ¿Qué es esta app?

Aplicación web PHP para gestión contable de autónomos españoles. Permite emitir facturas, registrar gastos, gestionar clientes/proveedores y generar libros contables y resúmenes fiscales (Modelos 303 y 130).

- **Stack:** PHP 8.0+, MySQL/MariaDB, Bootstrap 5.3, Tom Select, Bootstrap Icons
- **Hosting:** Diseñada para WebEmpresa (Apache + mod_rewrite)
- **URL producción:** `https://contable.nelsongil.com/`
- **BD producción:** `nelsongi_contable` en `localhost`

---

## Arquitectura

```
Petición HTTP
    ↓
.htaccess (bloquea /config/, install.php, archivos .sql/.log)
    ↓
[página].php → require_once includes/header.php
                    ↓
               auth.php      → verifica $_SESSION['usuario_id'], redirige a /login.php
                    ↓
               functions.php → incluye config/database.php (getDB, helpers)
                    ↓
               [HTML sidebar + layout]
    ↓
[lógica de negocio + HTML de la página]
    ↓
require_once includes/footer.php (cierra </body></html>)
```

### Excepción al flujo estándar
- `login.php` — standalone, no usa header.php. Tiene su propio session_start().
- `facturas/ver.php?pdf=1` — renderiza HTML sin sidebar para imprimir/PDF. Tiene su propio session_start() al inicio.
- `libros/exportar.php?download=1` — envía CSV directamente sin header.php. Tiene su propio session_start() al inicio.
- `install.php` — standalone multi-paso. Se bloquea si existe `config/.installed`.

---

## Base de datos

### Tablas

| Tabla | Descripción |
|-------|-------------|
| `clientes` | Clientes de la empresa (nombre, NIF, dirección, etc.) |
| `proveedores` | Proveedores (misma estructura que clientes) |
| `facturas_emitidas` | Facturas de venta. Incluye base, IVA, IRPF, estado, trimestre |
| `facturas_emitidas_lineas` | Líneas de cada factura emitida (N:1 con facturas_emitidas) |
| `facturas_recibidas` | Facturas de compra/gasto. Sin líneas detalladas |
| `numeracion` | Secuencia de numeración de facturas por año |
| `usuarios` | Usuarios de acceso (username, password BCrypt, ultimo_acceso) |

### Convenciones de BD
- Todos los campos monetarios son `DECIMAL(12,2)`
- El campo `trimestre` se calcula al guardar con la función PHP `trimestre(fecha)`
- `facturas_emitidas.estado`: ENUM `borrador | emitida | pagada | cancelada`
- Las facturas emitidas copian `cliente_nombre` y `cliente_nif` en el momento de creación (desnormalización intencional para histórico)
- Los registros de clientes/proveedores nunca se borran físicamente — se desactivan (`activo=0`)

### Schema
Ver `config/install.sql` para el DDL completo.

---

## Funciones clave (`includes/functions.php`)

| Función | Qué hace |
|---------|----------|
| `getDB()` | Singleton PDO con `ERRMODE_EXCEPTION` y `FETCH_ASSOC` |
| `e(string)` | `htmlspecialchars()` seguro para output en HTML |
| `post(key)` / `get(key)` | Acceso seguro a `$_POST` / `$_GET` con trim |
| `money(float)` | Formatea a `1.234,56 €` |
| `moneyInput(float)` | Formatea a `1234.56` para inputs numéricos |
| `trimestre(fecha)` | Devuelve 1-4 para una fecha dada |
| `siguienteNumeroFactura()` | Genera el siguiente `F{AÑO}{NNNN}` usando tabla `numeracion` |
| `getClientes()` / `getCliente(id)` | Leer clientes |
| `getProveedores()` / `getProveedor(id)` | Leer proveedores |
| `getFacturasEmitidas(anio, trim)` | Lista facturas con JOIN a clientes |
| `getFacturaEmitida(id)` | Una factura con JOIN a clientes |
| `getLineasFactura(facturaId)` | Líneas de una factura emitida |
| `getFacturasRecibidas(anio, trim)` | Lista compras con JOIN a proveedores |
| `resumenTrimestral(anio, trim)` | Devuelve array con ventas_base, compras_base, iva_resultado, rendimiento, etc. |
| `flash(msg, type)` | Guarda mensaje en `$_SESSION['flash']` |
| `showFlash()` | Consume y renderiza el flash como alerta Bootstrap |
| `redirect(url)` | Header Location + exit |

---

## Autenticación

- `includes/auth.php` se incluye desde `header.php`
- Verifica `$_SESSION['usuario_id']`; si no existe → `header('Location: /login.php?redirect=...')`
- Logout vía `?logout=1` en cualquier página (maneja `auth.php`)
- Las contraseñas se guardan con `password_hash(..., PASSWORD_BCRYPT)` y se verifican con `password_verify()`
- **Sesión:** `session_start()` se llama en `auth.php` si no está iniciada

---

## Numeración de facturas

Formato: `F{AÑO}{NNNN}` — Ej: `F20260001`

La función `siguienteNumeroFactura()` hace un `INSERT IGNORE` + `UPDATE` atómico en la tabla `numeracion` para evitar duplicados.

---

## Cálculo de importes

```
base_imponible = Σ(cantidad × precio) de cada línea
cuota_iva      = round(base × pct_iva / 100, 2)
cuota_irpf     = round(base × pct_irpf / 100, 2)
total          = base + cuota_iva
liquido        = total - cuota_irpf
trimestre      = ceil(mes / 3)
```

El cálculo se hace en PHP al guardar Y en JavaScript en tiempo real en el formulario.

---

## Frontend

- **Bootstrap 5.3** (CDN) — layout responsivo, cards, badges, tablas
- **Bootstrap Icons 1.11** (CDN) — iconos
- **Tom Select 2.3** (CDN) — select con búsqueda en clientes/proveedores
- **Inter** (Google Fonts) — tipografía principal
- **CSS custom** en `includes/header.php` (inline en `<style>`) — paleta verde/dorado, sidebar, KPIs
- **No hay bundler** — todo es PHP tradicional con HTML inline

### Paleta de colores
```css
--verde:   #1A2E2A  /* Verde oscuro — sidebar, headers */
--verde-m: #2D5245  /* Verde medio — hover */
--verde-a: #3E7B64  /* Verde claro — botón primary */
--gold:    #C9A84C  /* Dorado — acento, botón gold */
--bg:      #F4F7F5  /* Fondo general */
```

---

## Convenciones de código

- **PHP:** Sin framework. Todo procedural. Funciones helpers en `functions.php`.
- **SQL:** PDO con prepared statements en todas las queries con parámetros de usuario.
- **Output:** Siempre usar `e()` (alias de `htmlspecialchars`) al imprimir datos de BD en HTML.
- **Redirects:** Siempre usar `redirect()` (que hace `exit`) en lugar de `header()` directo.
- **Mensajes de éxito/error:** Usar `flash()` + `redirect()` tras formularios POST (PRG pattern).
- **$pageTitle:** Debe asignarse *antes* de `require_once header.php` para que aparezca en `<title>`.
 
 ---
 
 ## Reglas de trabajo para IAs
 
 ### Antes de tocar cualquier archivo:
 1. Leer `CONVENTIONS.md` para respetar el estilo del proyecto.
 2. Leer `SECURITY.md` para no introducir vulnerabilidades.
 3. Si modificas la base de datos, leer `DATABASE.md` y crear una migración SQL si el esquema cambia.
 
 ### Al crear archivos nuevos PHP con formulario:
 - **SIEMPRE:** `session_start()` → `require functions.php` → procesar `POST` → `redirect` si éxito → **LUEGO** incluir `header.php` → HTML.
 - **NUNCA** incluir `header.php` antes de haber procesado completamente el `POST`.
 
 ### Al añadir funcionalidades:
 - Reutilizar funciones existentes en `functions.php`.
 - Si una funcionalidad se repite en más de dos sitios, debe moverse a una función en `functions.php`.
 - No duplicar lógica de cálculo de impuestos (IVA/IRPF) que ya está centralizada.
 
 ### Al corregir bugs:
 - No cambiar nombres de variables o funciones sin actualizar TODAS las referencias en el proyecto.
 - No alterar la estructura de tablas sin crear una migración documentada.
 - Actualizar `CHANGELOG.md` con cada corrección o mejora.
 
 ### Lo que NUNCA debes hacer:
 - Subir credenciales o el archivo `config/database.php` al repositorio.
 - Hacer `echo` de variables externas (`$_GET`, `$_POST`) sin usar la función `e()`.
 - Alterar el flujo de autenticación centralizado en `auth.php`.
 - Cambiar el formato de numeración de facturas sin planificar la migración de las existentes.
 - Hardcodear colores CSS fuera de las variables `:root`.
 
 ---
 
 ## Instalador (`install.php`)

Wizard de 4 pasos:
1. **Bienvenida** — comprueba requisitos PHP
2. **BD** — datos de conexión, verifica conexión MySQL
3. **Empresa + admin** — datos de empresa y usuario administrador
4. **Instalar** — crea BD, ejecuta `install.sql`, crea tabla `usuarios`, guarda `config/database.php`, genera `.htaccess`, crea `config/.installed`

Se bloquea mientras exista `config/.installed`. Para reinstalar: borrar ese archivo vía FTP.

### Notas importantes del instalador
- Ejecuta el SQL de `install.sql` filtrando las sentencias `SET` (NAMES, FOREIGN_KEY_CHECKS) para evitar errores de contexto
- Genera `config/database.php` con `chmod 600`
- Genera `.htaccess` con bloqueo de `/config/`, `install.php`, y archivos `.sql/.log/.md`

---

## Historial de cambios

### v1.1 — 2026-03-02
- **Fix Bug #1:** `session_start()` añadido al inicio de `facturas/ver.php`
  - Causa: el bloque PDF (`?pdf=1`) llama a `functions.php` directamente sin pasar por `header.php`/`auth.php`; y la lógica de "marcar como pagada" usa `flash()` → `$_SESSION` antes del PDF
- **Fix Bug #2:** `session_start()` añadido al inicio de `libros/exportar.php`
  - Causa: el modo `?download=1` incluye `functions.php` directamente (sin `header.php`)
- **Fix Bug #3:** Enlace `/libros/compras.php` → `/compras/` en `libros/exportar.php:99`
  - Causa: `libros/compras.php` no existe; el libro de compras es `compras/index.php`
- **Fix Bug #4:** `$pageTitle` movido antes de `require_once header.php` en `clientes/nuevo.php` y `clientes/editar.php`
  - Causa: `header.php` usa `$pageTitle` en el `<title>`; si se asigna después, el título siempre es "Contabilidad"
- **Fix Bug #5:** `compras/index.php:52` — atributo `style` malformado
  - Causa: `style="max-width:160px text-muted small"` tenía clases Bootstrap mezcladas; correcto: `class="text-truncate text-muted small" style="max-width:160px"`
- **Fix Bug #6:** `install.php` — filtrado SQL extendido para saltar todas las sentencias `SET`, no solo `SET NAMES`
  - Causa: `SET FOREIGN_KEY_CHECKS = 0` requiere contexto de BD activo; ahora se salta

### v1.2 — 2026-03-02
- **Feature:** Importación de facturas de compra desde PDF
  - `compras/parse_pdf.php` (nuevo) — endpoint AJAX POST, recibe texto extraído por PDF.js, devuelve JSON con datos de la factura parcheados vía regex (NIF/CIF, fecha, importes)
  - `compras/nueva.php` — panel colapsable «Importar desde PDF» con drag & drop, PDF.js (ES module CDN), barra de progreso, auto-relleno del formulario y alerta para crear proveedor nuevo
  - `proveedores/nuevo.php` — acepta `?nombre=&nif=` en URL para pre-rellenar desde el PDF import
- **Fix:** `proveedores/nuevo.php` usaba la tabla `clientes` en lugar de `proveedores` (bug crítico latente)
- **Fix:** `proveedores/nuevo.php` `$pageTitle` movido antes de `header.php`

### v1.1 — 2026-03-02
- (ver changelog anterior)

### v1.0 — 2026-02-XX
- Primera versión: instalador, login, dashboard, facturas emitidas, compras, clientes, proveedores, libros, exportación CSV

---

## Troubleshooting frecuente

| Síntoma | Causa probable | Solución |
|---------|---------------|----------|
| `SQLSTATE[42S02] Table not found` | `install.sql` no se ejecutó | Ejecutar `install.sql` en phpMyAdmin |
| Título `<title>Contabilidad` en páginas de clientes | `$pageTitle` asignado tarde | Ya corregido en v1.1 |
| Error 404 en "Libro de compras" desde exportar | Enlace apuntaba a `libros/compras.php` | Ya corregido en v1.1 |
| CSV descarga con error de sesión | `session_start()` faltante | Ya corregido en v1.1 |
| El instalador se bloquea | Existe `config/.installed` | Borrar ese archivo vía FTP para reinstalar |
| Contraseña olvidada | — | Generar hash con `password_hash()` y actualizar en tabla `usuarios` vía phpMyAdmin |

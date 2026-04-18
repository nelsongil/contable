# CLAUDE.md

Este archivo proporciona orientación a Claude Code (claude.ai/code) cuando trabaja con el código de este repositorio.

## Idioma

Responder siempre en español, independientemente del idioma en que se formule la pregunta.

## Visión General del Proyecto

**Libro Contable** es una aplicación de contabilidad PHP ligera y autocontenida para autónomos españoles. Gestiona facturación, registro de gastos, clientes y proveedores, generación de libros contables e informes fiscales (Modelos 303/130). Sin framework, sin herramientas de compilación — PHP procedimental puro con MySQL y Bootstrap 5.3 via CDN.
SOLO PARA TENER CONTROL CONTABLE, NO REEMPLAZA A UN ASESOR FISCAL

## Workflow de Commit

Cuando el usuario pida hacer un commit (con cualquier frase como "haz el commit", "commitea", etc.), seguir siempre este orden **sin pedir confirmación adicional**:

1. **Generar el ZIP del instalador** ejecutando `powershell -File tools/build_zip.ps1` desde la raíz del proyecto.
2. **Hacer git commit** de los archivos modificados (nunca incluir `.zip`, `config/database.php`, `config/.installed` ni archivos de `.claude/`).
3. **Hacer git push** a `main` — el CI se encarga de incrementar el PATCH y crear el Release en GitHub automáticamente.

## Entorno Técnico

| Variable | Valor |
|----------|-------|
| PHP (producción) | **8.5** |
| Base de datos | **MariaDB 10.11.15-MariaDB-cll-lve** |
| Entorno local | **Laragon** (Apache + PHP 8.5 + MariaDB) |
| Indentación | **4 espacios** (nunca tabs) |
| Line endings | **LF** (Unix) — nunca CRLF |
| Encoding | UTF-8 sin BOM — obligatorio en todos los archivos |

> MariaDB 10.11 soporta `JSON` nativo, `LAST_INSERT_ID(expr)` para contadores atómicos,
> y `ON DUPLICATE KEY UPDATE`. No usar sintaxis exclusiva de MySQL 8+ (ej: `ROW_NUMBER()` sin
> compatibilidad, window functions — sí disponibles en MariaDB 10.2+).

## Comandos de Desarrollo

No existen herramientas de compilación ni gestores de paquetes. El desarrollo solo requiere un servidor web con PHP 8.5 y MariaDB.

```bash
# Verificar sintaxis PHP de un archivo
php -l includes/functions.php

# Verificar codificación del archivo (DEBE ser charset=utf-8, nunca utf-16)
file -i includes/header.php

# Comprobar todos los archivos PHP por errores de sintaxis
find . -name "*.php" -not -path "./vendor/*" | xargs -I{} php -l {}
```

**No existe suite de tests automatizados.** La validación se hace manualmente desde el navegador o mediante las comprobaciones integradas del instalador.

## Crítico: Versionado

La versión de la aplicación vive **únicamente** en el archivo `/VERSION` en la raíz del proyecto (ej: `1.5.1`). **Nunca hardcodear el número de versión** en código PHP ni en otros archivos.

**El PATCH se incrementa automáticamente** en cada `git push` a `main` mediante el workflow `.github/workflows/release.yml`. El bot commitea el nuevo VERSION con `[skip ci]` y crea el Release + ZIP en GitHub.

**Regla de oro: la numeración es automática por defecto.**
El CI siempre incrementa el PATCH en cada push. Solo se toca VERSION manualmente cuando el usuario pide explícitamente un cambio de MINOR o MAJOR; tras ese commit, el CI retoma el auto-incremento desde la nueva base.

**Resultado real al hacer un bump manual:**
- Se establece `VERSION = 1.7.0` antes del commit
- El CI incrementa PATCH en ese push → release `v1.7.1`
- Siguientes commits: `v1.7.2`, `v1.7.3`… hasta el próximo bump manual

No existe un release `x.y.0` (el CI siempre suma 1 al PATCH). El salto de MINOR/MAJOR se comunica por el cambio en esos componentes, no porque el PATCH sea exactamente 0.

**Cuándo tocar VERSION manualmente (solo si el usuario lo pide):**
- Subir MINOR o MAJOR → editar `/VERSION` antes del commit
- Evitar release en un push concreto → añadir `[no-release]` al mensaje del commit

```bash
# Ver versión actual
cat VERSION

# Subir MINOR manualmente (ejemplo: 1.6.x → 1.7.x)
# El CI convertirá 1.7.0 en 1.7.1 al hacer push
printf "1.7.0" > VERSION
```

- **MAJOR** (X.0.0): cambio arquitectural incompatible
- **MINOR** (1.X.0): nueva funcionalidad backward-compatible
- **PATCH** (1.5.X): se gestiona automáticamente por CI en cada push

Todos los componentes leen de este archivo: `config/database.php`, `install.php`, `ajustes/update_process.php`, `tools/build_zip.php`.

## Crítico: Codificación de Archivos

**TODOS los archivos DEBEN estar en UTF-8 sin BOM.** Es un requisito estricto — varios commits han sido dedicados a corregir regresiones de codificación. Nunca guardar como UTF-16 (evitar el Bloc de notas de Windows). Verificar siempre con `file -i NOMBRE_ARCHIVO` → debe mostrar `charset=utf-8`.

## Arquitectura

### Flujo de Peticiones

Toda página protegida sigue exactamente este patrón — **el procesamiento del POST debe ir antes de `header.php`** para permitir redirecciones limpias:

```php
<?php
session_start();
require_once '../includes/functions.php';
require_once '../includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // ...lógica de negocio...
    flash('Éxito');
    redirect('/modulo/');
}

$pageTitle = 'Título de la página';
require_once '../includes/header.php';
?>
<!-- HTML aquí -->
<?php require_once '../includes/footer.php'; ?>
```

`includes/header.php` carga `auth.php` y `functions.php`, renderiza el sidebar/nav completo y aplica el CSS del tema. `includes/footer.php` cierra el HTML e importa los assets CDN (Bootstrap, Chart.js, Tom Select).

### Patrón de Módulos

Las carpetas de funcionalidad (`facturas/`, `compras/`, `clientes/`, `proveedores/`) comparten la misma estructura:
- `index.php` — listado con filtros
- `nuevo.php` — formulario de creación **y** edición (edición activada por `?id=N`)
- `ver.php` — vista de detalle (solo facturas); `?pdf=1` activa el modo de salida PDF

### Archivos Clave

| Archivo | Propósito |
|---------|-----------|
| `includes/functions.php` | Todos los helpers compartidos: `getDB()`, `e()`, `post()`, `get()`, `money()`, `flash()`, `redirect()`, `getConfig()`, `setConfig()`, funciones CRUD |
| `includes/auth.php` | Verificación de sesión — incluido por `header.php` en cada página protegida |
| `includes/header.php` | Cabecera HTML + sidebar + CSS del tema (auto-incluye auth + functions) |
| `config/database.php` | Generado en la instalación — credenciales de BD + constantes de empresa + `APP_VERSION`. **Nunca hacer commit.** |
| `config/install.sql` | DDL completo del esquema de BD |
| `install.php` | Asistente de instalación de 4 pasos; se auto-bloquea mediante `config/.installed` |

### Almacén de Configuración

Los ajustes en tiempo de ejecución (colores del tema, datos de empresa, formato de facturas) residen en la tabla `configuracion` de la BD como pares clave-valor. Usar `getConfig('clave', $default)` y `setConfig('clave', $valor)` — nunca leer/escribir la tabla directamente.

### Convenciones de Base de Datos

- PDO con prepared statements siempre; nunca concatenar input del usuario en SQL
- `SELECT *` está prohibido — listar columnas explícitamente
- Importes: `DECIMAL(12,2)`. Nunca `FLOAT`.
- Borrado lógico: establecer `activo=0`, nunca `DELETE` clientes o proveedores
- Toda tabla tiene `id INT AUTO_INCREMENT` y `creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP`

### Tablas Principales de BD

| Tabla | Propósito |
|-------|-----------|
| `facturas_emitidas` | Facturas emitidas (ventas) con instantánea desnormalizada del cliente |
| `facturas_emitidas_lineas` | Líneas de factura (se eliminan en CASCADE con la factura) |
| `facturas_recibidas` | Facturas recibidas (compras/gastos) |
| `clientes` / `proveedores` | Directorios de clientes y proveedores |
| `configuracion` | Ajustes de la aplicación clave-valor |
| `numeracion` | Secuencia de numeración de facturas por año (ej. `F20260001`) |

## Convenciones de Código

**Nomenclatura:**
- Variables PHP: `snake_case` (`$cliente_id`, `$base_imponible`)
- Funciones PHP: `camelCase` (`getCliente()`, `siguienteNumeroFactura()`)
- Constantes: `MAYUSCULAS` (`EMPRESA_NOMBRE`, `DB_HOST`)
- Campos de BD: `snake_case`
- Variables CSS: `kebab-case` (`--verde-m`, `--gold`)

**Seguridad en la salida:**
- Siempre usar `e($var)` para escapar HTML — nunca `echo $_GET/$_POST` directamente
- Usar `redirect()` (no `header()` directamente) y `flash()` para notificaciones
- No usar `die()` ni `exit()` excepto tras `redirect()`

**Frontend:**
- Todos los estilos de la app están en el bloque `<style>` de `header.php`
- Usar variables CSS `:root` para colores — sin valores hex hardcodeados
- Se prefiere Vanilla JS; jQuery no está disponible

## Documentación Adicional

Para referencia más detallada, el repositorio incluye:
- `AGENTS.md` — arquitectura completa y referencia de funciones para LLMs
- `CONVENTIONS.md` — estándares de código completos
- `DATABASE.md` — esquema con ERD (Mermaid)
- `SECURITY.md` — checklist de seguridad y prácticas prohibidas
- `CHANGELOG.md` — historial de versiones (v1.0 → v1.3)

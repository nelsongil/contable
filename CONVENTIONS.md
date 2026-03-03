# Convenciones de Código — Libro Contable
**CRÍTICO: Todos los archivos DEBEN guardarse en UTF-8 sin BOM (Nunca UTF-16).**
**PROHIBICIÓN: Nunca usar write_file o herramientas que guarden en UTF-16. Verificar siempre con: file -i CHANGELOG.md (debe ser charset=utf-8).**

Documento de convenciones de código para guiar el desarrollo del proyecto.

## Estructura de archivos
- **Raíz**: Controladores principales y pantallas de acceso (`index.php`, `login.php`).
- **`config/`**: Configuración de base de datos, schema SQL y archivos de estado (`.installed`).
- **`includes/`**: Componentes reutilizables, funciones y lógica de autenticación.
- **Carpetas de módulos** (`facturas/`, `compras/`, `clientes/`, `proveedores/`, `libros/`): Lógica específica por área de negocio.

### Reglas de oro
- **Nunca mezclar lógica de negocio con presentación HTML**.
- **Procesamiento de formularios**: Todo archivo PHP que maneje un formulario debe procesar el `POST` **ANTES** de incluir `header.php` para permitir redirecciones limpias.

## Nomenclatura
- **Variables PHP**: `snake_case` (ej. `$cliente_id`, `$base_imponible`).
- **Funciones PHP**: `camelCase` (ej. `getCliente()`, `siguienteNumeroFactura()`).
- **Claves BD**: `snake_case` (ej. `cliente_id`, `fecha_vencimiento`).
- **Archivos**: `snake_case` (ej. `nueva_factura.php`). *Excepción: los archivos actuales mantienen su nombre para no romper enlaces.*
- **Constantes**: `MAYUSCULAS` (ej. `EMPRESA_NOMBRE`, `DB_HOST`).
- **Variables CSS**: `kebab-case` (ej. `--verde-m`, `--gold`).

## Base de datos
- **PDO**: Siempre usar PDO con *prepared statements*. Nunca concatenar variables directamente en el SQL.
- **SELECT**: Nunca usar `SELECT *`. Listar explícitamente las columnas necesarias.
- **Fechas**: Usar formatos `DATE` o `DATETIME` de MySQL. No guardar fechas como strings.
- **Importes**: Usar `DECIMAL(12,2)` para precisión monetaria. Nunca usar `FLOAT`.
- **Auditoría**: Toda tabla debe tener `id AUTO_INCREMENT` y `creado_en TIMESTAMP`.

## PHP
- **Sesiones**: `session_start()` debe ser la primera línea, antes de cualquier salida (output).
- **Sanitización**: Validar y sanitizar inputs con las funciones de `functions.php` (`post()`, `get()`).
- **Output**: Usar `e()` para escapar HTML. Nunca hacer `echo` de variables globales directas.
- **Redirecciones**: Usar la función `redirect()`. No usar `header("Location: ...")` directamente.
- **Mensajes**: Usar `flash()` para notificaciones antes de un `redirect`.
- **Terminación**: No usar `die()` o `exit()` excepto después de un `redirect()`.

## Seguridad
- **Errores**: Nunca mostrar errores PHP en entornos de producción.
- **Contraseñas**: Almacenar siempre con `password_hash(..., PASSWORD_BCRYPT)`.
- **Autorización**: Toda acción destructiva (borrar, editar) debe verificar la propiedad del recurso.
- **Uploads**: Validar `mimetype` real (no solo extensión), renombrar archivos al subirlos y limitar tamaños.

## CSS/Frontend
- **Colores**: Usar siempre variables `:root`. No usar colores *hardcoded*.
- **!important**: Evitar su uso salvo justificación técnica documentada.
- **Framework**: Bootstrap 5 para layout y componentes.
- **Estilos App**: Concentrados en el `<style>` de `header.php`.
- **JavaScript**: Preferir Vanilla JS (sin dependencias como jQuery).

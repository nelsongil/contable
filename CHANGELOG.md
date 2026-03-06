# Changelog

Todos los cambios notables en este proyecto serán documentados en este archivo siguiendo el formato de [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

## [1.5] - 2026-03-06

### Changed
- **Sidebar**: ahora es scrollable (`overflow-y: auto`) cuando el contenido supera la altura de pantalla; evita que items de Configuración queden ocultos con el módulo Empleados activo.
- **Menú activo**: corregidos los estados activos duplicados en varios módulos:
  - "Nueva factura" ya no se marca activa en `/compras/nueva.php`.
  - "Libros contables" ya no se marca activa al navegar a Resumen fiscal o Modelo 347.
  - "Dashboard" ya no se marca activa en páginas de submódulos con `index.php`.

### Fixed
- **Confirmaciones Bootstrap**: todos los `confirm()` y `alert()` del navegador reemplazados por modales Bootstrap (`bsConfirm`) en facturas, clientes, proveedores, empleados y backups.
- **Copia de seguridad automática**: corregida conversión de tipo en `getConfig()` que impedía persistir el estado activo del toggle de backup automático.
- **Menú**: enlace al Dashboard añadido y accesible desde cualquier módulo.
- **Dropdown**: resuelto el problema de z-index que hacía que el menú "+Nuevo ingreso/gasto" quedara detrás de las tarjetas.

## [1.4] - 2026-03-04

### Added
- **Módulo Empleados** (opcional, activable desde Ajustes): gestión de empleados, registro de retenciones mensuales y resumen Modelo 111 trimestral.
- **Modelo 347**: nuevo informe en Libros con clientes y proveedores con operaciones anuales ≥ 3.005,06 €, desglosado por trimestres.
- **CLAUDE.md**: guía técnica para Claude Code con arquitectura, comandos y convenciones del proyecto.

### Changed
- **Factura PDF**: suprimidas las cabeceras del navegador al imprimir (`@page { margin:0 }` + padding en `body`).
- **Factura PDF**: nombre y CIF de la empresa ahora aparecen junto al logo en la cabecera.
- **Factura PDF**: eliminado el recuadro redundante de datos del emisor en el pie; los datos se muestran como texto simple.
- **Factura PDF**: nota LOPD a ancho completo y fuente reducida a 6pt.
- **Factura PDF**: color de acento (`invoice_color_accent`) ahora controla la línea separadora del encabezado (antes usaba `invoice_color_gold`, clave no configurable).
- **Backup**: los datos de empresa definidos como constantes PHP se incluyen en todos los backups como `INSERT IGNORE INTO configuracion`.
- **Backup**: límite configurable de copias manuales y automáticas con rotación automática de las más antiguas.
- **Auto-updater**: paso `backup` reescrito para usar `generateSQLDump()` directamente (el método anterior con `ob_start + require` fallaba porque `exportar.php` llama a `exit`).
- **Auto-updater**: `User-Agent` dinámico usando `APP_VERSION`.
- `generateSQLDump()` centralizada en `includes/functions.php` y eliminada de los tres sitios donde estaba duplicada.
- Enlace del logo en la barra lateral actúa como botón "Volver al dashboard".

### Fixed
- Nombre del cliente en factura PDF: si `cliente_nombre` estaba vacío en la tabla (campo desnormalizado), ahora recupera el nombre desde `getCliente()` como fallback.

## [1.3] - 2026-03-03
### Added
- **Módulo Auto-updater**: Actualización automática desde GitHub con gestión de versiones.
- **Sistema de Backup (Manual/Auto)**: Exportación e importación SQL (PDO) y copias semanales automáticas.
- **Exportación de Configuración**: Traspaso de ajustes entre instalaciones vía JSON.
- **Generador LOPD**: Creación automática de textos legales para la factura.
- Nuevo importador de facturas PDF local (sin API externa) usando `smalot/pdfparser`.
- Indicadores visuales de confianza (Verde/Amarillo/Rojo) en la detección de campos del PDF.
- Documentación técnica detallada: `CONVENTIONS.md`, `SECURITY.md`, `DATABASE.md`.
- Actualización de reglas para IAs en `AGENTS.md`.

### Changed
- El procesamiento de PDFs ahora ocurre íntegramente en el servidor en lugar del navegador.
- Mejora en la detección del nombre del proveedor buscando líneas cercanas al NIF.

### Fixed
- Eliminado archivo obsoleto `compras/parse_pdf.php`.

## [1.2] - 2026-03-03
### Added
- Nuevo importador de facturas PDF local (sin API externa) usando `smalot/pdfparser`.
- Indicadores visuales de confianza (Verde/Amarillo/Rojo) en la detección de campos del PDF.
- **Módulo Auto-updater**: Actualización automática desde GitHub con gestión de versiones.
- **Sistema de Backup (Manual/Auto)**: Exportación e importación SQL (PDO) y copias semanales automáticas.
- **Exportación de Configuración**: Traspaso de ajustes entre instalaciones vía JSON.
- **Generador LOPD**: Creación automática de textos legales para la factura.
- Documentación técnica detallada: `CONVENTIONS.md`, `SECURITY.md`, `DATABASE.md`.
- Actualización de reglas para IAs en `AGENTS.md`.

### Changed
- El procesamiento de PDFs ahora ocurre íntegramente en el servidor en lugar del navegador.
- Mejora en la detección del nombre del proveedor buscando líneas cercanas al NIF.

### Fixed
- Eliminado archivo obsoleto `compras/parse_pdf.php`.

## [1.1] - 2026-03-02
### Added
- Creado `AGENTS.md` con documentación técnica inicial para IAs.

### Fixed
- `session_start()` añadido en `facturas/ver.php` para corregir errores de mensajes flash.
- `session_start()` añadido en `libros/exportar.php` (descarga CSV).
- Enlace roto en exportación de libros: `/libros/compras.php` corregido a `/compras/`.
- Título de página dinámico: `$pageTitle` ahora se asigna correctamente en clientes y proveedores antes de cargar el header.
- Atributo `style` malformado en la lista de compras.
- Instalador mejorado para filtrar sentencias `SET` conflictivas durante la importación del SQL.
- Solucionado bug crítico en creación de proveedores que guardaba datos en la tabla de clientes.

## [1.0] - 2026-02-15
### Added
- Primera versión funcional: instalador guiado, login, dashboard, facturas, compras, clientes y libros contables.

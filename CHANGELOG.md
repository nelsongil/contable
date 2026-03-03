# Changelog

Todos los cambios notables en este proyecto serán documentados en este archivo siguiendo el formato de [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

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

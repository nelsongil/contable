# Changelog

Todos los cambios notables en este proyecto serán documentados en este archivo siguiendo el formato de [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

## [1.5.6] - 2026-03-24

### Fixed
- **facturas/nueva.php — Bloqueo pagada/cancelada**: redirige a la vista de la factura con mensaje de error si se intenta editar una factura en estado `pagada` o `cancelada`; el bloqueo ocurre antes de cargar el formulario para evitar modificaciones accidentales.
- **facturas/nueva.php — Preview de número**: muestra bajo el título el número que se asignará a la nueva factura (calculado sin consumir el contador), usando el mismo formato que `siguienteNumeroFactura()`.

## [1.5.5] - 2026-03-24

### Changed
- **facturas/nueva.php — IRPF**: eliminado el campo "Ret. IRPF %" del formulario y las filas de IRPF/Líquido de los totales; el backend sigue almacenando `pct_irpf=0` para mantener compatibilidad con la BD.
- **facturas/nueva.php — Descuentos**: eliminado `min="0"` de los inputs de cantidad y precio (filas estáticas y dinámicas); ahora se pueden introducir valores negativos para aplicar descuentos como línea.
- **facturas/nueva.php — Vencimiento automático**: al cambiar la fecha de facturación, la fecha de vencimiento se recalcula automáticamente a fecha+7 días; el valor por defecto en facturas nuevas cambia de +30 a +7 días.

## [1.5.4] - 2026-03-24

### Fixed
- **install.php — Parser SQL**: eliminados comentarios `--` del SQL antes de dividir por `;`; el bug hacía que cada `CREATE TABLE` precedido de un comentario fuera descartado entero, dejando la base de datos sin tablas.
- **install.sql**: añadida tabla `configuracion` con valores por defecto (`empresa_irpf`, `last_update_check`, `factura_prefijo`, `factura_ceros`).
- **header.php**: `checkForUpdates()` envuelto en `try/catch` para evitar pantalla en blanco si el updater lanza cualquier excepción.
- **updater.php**: comprobación de `function_exists('curl_init')` antes de usar cURL; evita error fatal en hostings sin la extensión habilitada.

## [1.5.3] - 2026-03-24

### Security
- **install.php — CSRF**: añadida protección con token CSRF en los pasos 1-4 del instalador; cada formulario incluye un token de sesión verificado server-side con `hash_equals()`.
- **install.php — Auto-destrucción**: al completar la instalación, paso 5 se renderiza directamente (sin redirect) y `register_shutdown_function` renombra `install.php` a `install.php.installed` después de enviar la página — protege en servidores sin `mod_rewrite` donde el `.htaccess` no surtiría efecto. Bonus: la página de éxito detallada (checklist) ahora sí se muestra, ya que antes el lock file la bloqueaba antes de renderizarse.
- **install.php — IBAN**: validación de formato completo (`/^[A-Z]{2}[0-9]{2}[A-Z0-9]{4,30}$/`) con normalización de espacios; reemplaza la anterior comprobación de solo longitud.

## [1.5.2] - 2026-03-24

### Fixed
- **build_zip.php**: reemplazado patrón obsoleto `/\.antigravity/` (que nunca matcheaba) por `/\.claude\//`; añadidas exclusiones de `backups/`, `.htaccess`, `.well-known/`, `Thumbs.db` y todos los `.md` de la raíz.
- **install.php**: el mensaje de error de conexión a BD ya no expone detalles internos del driver PDO (host, usuario, password).
- **install.php**: validación de `db_host` vacío ahora genera error (antes solo se validaba `db_name` y `db_user`).
- **install.php**: añadida validación server-side de NIF/CIF/NIE con regex (`/^[A-Z0-9][0-9]{7}[A-Z0-9]$/i`).
- **install.php**: añadida validación server-side de email con `filter_var(..., FILTER_VALIDATE_EMAIL)`.
- **install.php**: `admin_user` validado contra patrón alfanumérico seguro (`[a-zA-Z0-9_.\-]{3,80}`).
- **install.php**: contraseña requiere ahora al menos una letra Y un número (server-side), no solo longitud mínima.
- **install.php**: añadidos atributos `maxlength` en todos los campos del formulario (150 chars para nombres, 9 para CIF, 34 para IBAN, etc.).

## [1.5.1] - 2026-03-24

### Added
- **Archivo VERSION**: fuente única de verdad para la versión. Todos los componentes leen de `/VERSION` en lugar de tener el número hardcodeado.

### Fixed
- **Auto-updater (finalize)**: escribe la nueva versión en `/VERSION` (antes solo hacía preg_replace en `config/database.php`, que fallaba en instalaciones frescas).
- **install.php**: el instalador leía la versión de una constante fija `'1.2'`; ahora lee `/VERSION`.
- **tools/build_zip.php**: leía la versión de `install.php` (obteniendo `1.2`); ahora lee `/VERSION`.
- **Dashboard**: panel "Últimas facturas" vacío por variable `$ultimas` sin definir.
- **Importación PDF compras**: campo "Concepto" nunca detectado — añadidos patrones `Concepto:`, `Descripción:`, `Objeto:`, etc.
- **Importación PDF compras**: botón "Confirmar y guardar" mostraba siempre error porque JS comprobaba `resp.redirected` en lugar del JSON `{ok:true}` devuelto por PHP.
- **Responsive**: añadido `<meta name="viewport">` ausente en todo el proyecto.
- **Responsive**: sidebar colapsable con hamburguesa en móvil, scroll horizontal en tablas, inputs 16px (anti-zoom iOS), botones táctiles 44px, modal PDF fullscreen en móvil.

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

# 📒 Libro Contable — Aplicación PHP
**v1.5**

## Requisitos
- PHP 8.0+
- MySQL 5.7+ / MariaDB 10.3+
- Servidor web con mod_rewrite (Apache) — WebEmpresa lo incluye

---

## Instalación en WebEmpresa

### 1. Ejecutar el instalador
Sube todos los archivos al hosting y abre `https://tudominio.com/contable/install.php` en el navegador.
El instalador guiado creará la BD, las tablas y el usuario administrador en 4 pasos.

> ⚠️ **Si el instalador ya se ejecutó pero las tablas no existen**, ejecuta el SQL manualmente:
> 1. phpMyAdmin → selecciona tu BD
> 2. Pestaña **SQL** → pega el contenido de `config/install.sql` → Ejecutar

### 2. Acceder
- URL: `https://tudominio.com/contable/`
- Credenciales: las que introdujiste en el instalador (paso 3)

> Si perdiste la contraseña, crea un hash nuevo con `password_hash('nueva_pass', PASSWORD_BCRYPT)` y actualiza la columna `password` en la tabla `usuarios` vía phpMyAdmin.

---

## Estructura de archivos
```
contable/
├── index.php              ← Dashboard principal
├── login.php              ← Pantalla de acceso
├── install.php            ← Instalador (se autobloquea tras ejecutarse)
├── .htaccess              ← Seguridad y configuración Apache
├── AGENTS.md              ← Documentación técnica para IAs/LLMs
├── config/
│   ├── database.php       ← ⚠️ Configuración BD (generado por install.php)
│   ├── install.sql        ← Schema de la BD
│   └── .installed         ← Lock file del instalador
├── includes/
│   ├── auth.php           ← Verificación de sesión + logout
│   ├── functions.php      ← Funciones comunes (money, flash, getDB, CRUD helpers...)
│   ├── header.php         ← Cabecera HTML + sidebar (incluye auth.php + functions.php)
│   └── footer.php         ← Cierre HTML + Bootstrap JS + Tom Select
├── facturas/
│   ├── index.php          ← Lista de facturas emitidas
│   ├── nueva.php          ← Crear/editar factura (con líneas)
│   └── ver.php            ← Ver factura + generar PDF (modo ?pdf=1)
├── compras/
│   ├── index.php          ← Lista de facturas recibidas
│   ├── nueva.php          ← Registrar/editar compra
│   └── importar_pdf.php   ← Endpoint AJAX: extrae datos de facturas PDF (smalot/pdfparser)
├── clientes/
│   ├── index.php          ← Lista de clientes
│   ├── nuevo.php          ← Crear cliente
│   └── editar.php         ← Editar cliente
├── proveedores/
│   ├── index.php          ← Lista de proveedores
│   ├── nuevo.php          ← Crear proveedor (acepta ?nombre=&nif= desde PDF import)
│   └── editar.php         ← Editar proveedor
├── empleados/             ← Módulo opcional (activar en Ajustes)
│   ├── index.php          ← Lista de empleados
│   ├── nuevo.php          ← Crear/editar empleado
│   ├── retenciones.php    ← Registro mensual de retenciones IRPF
│   └── modelo111.php      ← Resumen Modelo 111 trimestral
├── libros/
│   ├── index.php          ← Libro de ventas por trimestre
│   ├── resumen.php        ← Resumen fiscal (303/130)
│   ├── modelo347.php      ← Modelo 347 (operaciones ≥ 3.005,06 €/año)
│   └── exportar.php       ← Exportar a CSV/Excel
└── ajustes/
    ├── empresa.php        ← Datos empresa + numeración facturas
    ├── plantilla.php      ← Colores y logo de la factura PDF
    ├── tema.php           ← Colores de la interfaz
    ├── empleados.php      ← Activar/desactivar módulo empleados
    ├── backup.php         ← Copias de seguridad (manual + automático)
    └── updater.php        ← Actualización automática desde GitHub
```

---

## Funcionalidades
- ✅ Dashboard con KPIs anuales y resumen trimestral
- ✅ Gestión de clientes con búsqueda y autocompletado (Tom Select)
- ✅ Gestión de proveedores
- ✅ Facturas emitidas con líneas detalladas, IVA y retención IRPF
- ✅ Facturas recibidas (compras) con desglose de IVA editable
- ✅ Importación de facturas desde PDF (extracción automática de datos con smalot/pdfparser)
- ✅ Generación de PDF de facturas (vía impresión del navegador, `?pdf=1`)
- ✅ Libro de ventas y compras por trimestre
- ✅ Resumen fiscal trimestral (Modelos 303 y 130)
- ✅ Modelo 347 (clientes y proveedores con operaciones ≥ 3.005,06 €/año)
- ✅ Módulo Empleados opcional: gestión, retenciones mensuales y Modelo 111
- ✅ Copias de seguridad automáticas y manuales (SQL)
- ✅ Actualización automática desde GitHub (auto-updater)
- ✅ Exportación a CSV compatible con Excel

---

## Seguridad recomendada
Para proteger la app con contraseña, crea un archivo `.htpasswd` y añade al `.htaccess`:
```apache
AuthType Basic
AuthName "Acceso restringido"
AuthUserFile /ruta/absoluta/.htpasswd
Require valid-user
```
WebEmpresa permite generarlo desde su panel de control.

---

## Requisitos del servidor
- PHP 8.0+ con extensiones: `pdo_mysql`, `curl`, `zip`, `mbstring`
- MySQL 5.7+ / MariaDB 10.3+
- Apache con `mod_rewrite` habilitado

## Changelog
Ver historial completo de cambios en [CHANGELOG.md](CHANGELOG.md)

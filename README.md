# 📒 Libro Contable — Aplicación PHP

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
│   └── nueva.php          ← Registrar/editar compra
├── clientes/
│   ├── index.php          ← Lista de clientes
│   ├── nuevo.php          ← Crear cliente
│   └── editar.php         ← Editar cliente
├── proveedores/
│   ├── index.php          ← Lista de proveedores
│   ├── nuevo.php          ← Crear proveedor
│   └── editar.php         ← Editar proveedor
└── libros/
    ├── index.php          ← Libro de ventas por trimestre
    ├── resumen.php        ← Resumen fiscal (303/130)
    └── exportar.php       ← Exportar a CSV/Excel
```

---

## Funcionalidades
- ✅ Dashboard con KPIs anuales y resumen trimestral
- ✅ Gestión de clientes con búsqueda y autocompletado (Tom Select)
- ✅ Gestión de proveedores
- ✅ Facturas emitidas con líneas detalladas, IVA y retención IRPF
- ✅ Facturas recibidas (compras) con desglose de IVA editable
- ✅ Generación de PDF de facturas (vía impresión del navegador, `?pdf=1`)
- ✅ Libro de ventas y compras por trimestre
- ✅ Resumen fiscal trimestral (Modelos 303 y 130)
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

## Changelog

### v1.1 — 2026-03-02
- **Fix:** `session_start()` añadido en `facturas/ver.php` (faltaba para que `flash()` funcionara antes del modo PDF)
- **Fix:** `session_start()` añadido en `libros/exportar.php` (descarga CSV evitaba pasar por `header.php`)
- **Fix:** Enlace roto `/libros/compras.php` corregido a `/compras/` en `libros/exportar.php`
- **Fix:** `$pageTitle` movido antes de `require_once header.php` en `clientes/nuevo.php` y `clientes/editar.php` (el título HTML mostraba "Contabilidad" en lugar del título de la página)
- **Fix:** Atributo `style` malformado en `compras/index.php` — clases CSS Bootstrap separadas del atributo `style`
- **Fix:** `install.php` — filtrado SQL mejorado para ignorar todas las sentencias `SET` al ejecutar `install.sql`
- **Docs:** Creado `AGENTS.md` con documentación técnica completa para IAs

### v1.0 — 2026-02-XX
- Primera versión funcional con instalador, login, facturas, compras, clientes, proveedores y libros contables

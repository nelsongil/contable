# Skill: contable-db-conventions

## Activación
Activar cuando vayas a escribir queries SQL, crear tablas, modificar el esquema de BD, o trabajar con funciones CRUD del proyecto.

---

## Tablas del esquema actual

| Tabla | Propósito |
|-------|-----------|
| `usuarios` | Acceso a la app (username, password BCrypt, ultimo_acceso) |
| `clientes` | Directorio de clientes (soft delete con `activo`) |
| `proveedores` | Directorio de proveedores (misma estructura que clientes) |
| `facturas_emitidas` | Cabecera de facturas de venta — desnormalizada intencionalmente |
| `facturas_emitidas_lineas` | Líneas de cada factura (CASCADE DELETE con factura) |
| `facturas_recibidas` | Facturas de compra/gasto — sin líneas detalladas |
| `numeracion` | Secuencia correlativa por año (`anio` PK, `ultimo` INT) |
| `configuracion` | Pares clave-valor para ajustes en tiempo de ejecución |
| `empleados` | Empleados (opcional, módulo activable) |
| `retenciones_empleados` | Retenciones IRPF mensuales por empleado (UNIQUE anio+mes+empleado) |

---

## Convenciones de tipos

| Dato | Tipo MySQL | Ejemplo |
|------|-----------|---------|
| Importes monetarios | `DECIMAL(12,2)` | `base_imponible`, `cuota_iva`, `total` |
| Cantidades de línea | `DECIMAL(10,3)` | `cantidad` en `facturas_emitidas_lineas` |
| Porcentajes | `DECIMAL(5,2)` | `porcentaje_irpf` en `empleados` |
| Booleanos | `TINYINT(1)` | `activo` (0 o 1) |
| Fechas contables | `DATE` | `fecha` en facturas |
| Timestamps auditoría | `TIMESTAMP DEFAULT CURRENT_TIMESTAMP` | `creado_en` |
| Años | `YEAR` | `anio` en `retenciones_empleados` |
| Meses | `TINYINT UNSIGNED` | `mes` en `retenciones_empleados` |

**NUNCA usar `FLOAT` para dinero** — imprecisión en coma flotante.

---

## Convenciones generales

- **Charset**: `utf8mb4_unicode_ci` en todas las tablas y BD
- **Engine**: `InnoDB` (integridad referencial, transacciones)
- **Toda tabla tiene**: `id INT AUTO_INCREMENT PRIMARY KEY` y `creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP`
- **`SELECT *` prohibido**: listar siempre columnas explícitas
- **Borrado lógico**: `activo=0` para clientes y proveedores; nunca `DELETE`
- **PDO obligatorio**: prepared statements siempre, sin concatenación de inputs

---

## Desnormalización intencional en `facturas_emitidas`

Las facturas copian `cliente_nombre` y `cliente_nif` en el momento de emisión. Esto es **deliberado** para mantener histórico fiscal correcto aunque el cliente cambie sus datos.

```sql
-- Al crear factura: copiar datos del cliente
INSERT INTO facturas_emitidas (cliente_id, cliente_nombre, cliente_nif, ...)
VALUES (?, ?, ?, ...)
-- cliente_nombre y cliente_nif son el "snapshot" del momento
```

Si `cliente_nombre` está vacío en una factura antigua, recuperar con fallback:
```php
$cli = $factura['cliente_id'] ? getCliente($factura['cliente_id']) : null;
$clienteNombre = $factura['cliente_nombre'] ?: ($cli ? $cli['nombre'] : '');
```

---

## Numeración de facturas

Formato configurable: `{prefijo}{año}{NNNNN}` — por defecto `F20260001`

```php
// SIEMPRE usar la función — nunca calcular manualmente
$numero = siguienteNumeroFactura();
// Internamente usa getConfig() para prefijo, año, dígitos y próximo número
// Hace INSERT IGNORE + UPDATE atómico en tabla numeracion
```

**No modificar la tabla `numeracion` directamente** sin actualizar también `getConfig('factura_proximo', ...)`.

---

## Configuración dinámica (`configuracion`)

La tabla `configuracion` almacena pares clave-valor. Siempre acceder con los helpers:

```php
// Leer (con caché estática)
$val = getConfig('clave', $default);

// Escribir (INSERT ... ON DUPLICATE KEY UPDATE)
setConfig('clave', $valor);

// NUNCA leer/escribir directamente:
$db->query("SELECT valor FROM configuracion WHERE clave='x'");  // prohibido
```

### Claves importantes de configuración

| Clave | Tipo real | Uso |
|-------|-----------|-----|
| `theme_color_primary/medium/accent/gold/bg` | string hex | Colores del tema |
| `invoice_color_accent` | string hex | Color acento factura PDF |
| `factura_prefijo` | string | Prefijo número factura |
| `factura_usa_anio` | bool | Incluir año en número |
| `factura_digitos` | int | Dígitos del correlativo |
| `factura_proximo` | int | Próximo número a usar |
| `backup_auto` | bool (`'true'`/`'false'`) | Toggle backup automático |
| `modulo_empleados` | bool | Habilita módulo empleados |
| `last_update_check` | int (timestamp) | Última comprobación de updates |

**Gotcha del tipo bool:** `getConfig()` convierte `'1'`→`true`, `'0'`→`false`, `'true'`→`true`, `'false'`→`false`. Usar `(bool)getConfig(...)` para comparaciones booleanas.

---

## Funciones CRUD disponibles en `functions.php`

```php
// Clientes
getClientes(bool $soloActivos = true): array
getCliente(int $id): array|false

// Proveedores
getProveedores(bool $soloActivos = true): array
getProveedor(int $id): array|false

// Facturas emitidas
getFacturasEmitidas(int $anio = 0, int $trim = 0): array
getFacturaEmitida(int $id): array|false
getLineasFactura(int $facturaId): array

// Facturas recibidas
getFacturasRecibidas(int $anio = 0, int $trim = 0): array

// Resumen fiscal
resumenTrimestral(int $anio, int $trim): array
// Devuelve: ventas_base, ventas_iva, ventas_irpf, compras_base, compras_iva, iva_resultado, rendimiento

// Empleados
getEmpleados(bool $soloActivos = true): array
getEmpleado(int $id): array|false
resumenModelo111(int $anio, int $trim): array
// Devuelve: perceptores, base, retenciones
```

---

## Cálculo de importes (fórmulas canónicas)

```
base_imponible = Σ(cantidad × precio) de líneas
cuota_iva      = round(base × pct_iva / 100, 2)
cuota_irpf     = round(base × pct_irpf / 100, 2)
total          = base + cuota_iva
liquido        = total - cuota_irpf
trimestre      = ceil(mes / 3)           → función trimestre($fecha)
```

El cálculo se hace en PHP al guardar **y** en JavaScript en tiempo real en el formulario. Ambos deben ser consistentes.

---

## Migraciones

Cambios de esquema → crear archivo en `config/migrations/YYYY-MM-DD_descripcion.sql` con sentencias idempotentes (`IF NOT EXISTS`, `IF EXISTS`). El updater los ejecuta automáticamente en el paso `install`.

Ejemplo real: `config/migrate_empleados.sql` añadió las tablas `empleados` y `retenciones_empleados`.

---

## ERD simplificado

```
usuarios
clientes ──────────┐
                   ↓
         facturas_emitidas ──── facturas_emitidas_lineas
proveedores ───────┐
                   ↓
         facturas_recibidas
empleados ─────────┐
                   ↓
         retenciones_empleados
configuracion (standalone)
numeracion (standalone)
```

---

## Gotchas — Errores reales ocurridos

### 1. `getClientes()` y `getProveedores()` usan `SELECT *`
Las funciones CRUD helper en `functions.php` usan `SELECT *` internamente (excepción documentada para helpers generales). Al escribir queries propias, listar columnas explícitamente.

### 2. `facturas_emitidas_lineas` se elimina en CASCADE
Al hacer `DELETE FROM facturas_emitidas WHERE id=?`, las líneas se eliminan solas. No necesita DELETE separado.

### 3. La tabla `numeracion` es por año
Si hay un salto de año y no existe fila para ese año, `siguienteNumeroFactura()` hace `INSERT IGNORE` primero — funciona solo si se usa la función, no si se intenta insertar manualmente.

### 4. `configuracion` guarda bools como string `'true'`/`'false'`
```php
setConfig('backup_auto', true);    // guarda el string 'true'
setConfig('backup_auto', false);   // guarda el string 'false'
setConfig('backup_auto', 1);       // guarda el string '1'
// getConfig() convierte todos estos a bool true/false
```

### 5. `facturas_recibidas` no tiene tabla de líneas
A diferencia de `facturas_emitidas`, las compras son registros planos sin detalle de líneas. Solo hay `base_imponible`, `cuota_iva`, `total` y `descripcion`.

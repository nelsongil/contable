-- ============================================================
-- INSTALACIÓN — Libro Contable Autónomo
-- Ejecuta este script en phpMyAdmin de WebEmpresa
-- ============================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ─── Clientes ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS clientes (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    nombre      VARCHAR(150) NOT NULL,
    nif         VARCHAR(20),
    direccion   VARCHAR(200),
    ciudad      VARCHAR(100),
    cp          VARCHAR(10),
    provincia   VARCHAR(80),
    telefono    VARCHAR(20),
    email       VARCHAR(100),
    notas       TEXT,
    activo      TINYINT(1) DEFAULT 1,
    creado_en   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Proveedores ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS proveedores (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    nombre      VARCHAR(150) NOT NULL,
    nif         VARCHAR(20),
    direccion   VARCHAR(200),
    ciudad      VARCHAR(100),
    cp          VARCHAR(10),
    provincia   VARCHAR(80),
    telefono    VARCHAR(20),
    email       VARCHAR(100),
    notas       TEXT,
    activo      TINYINT(1) DEFAULT 1,
    creado_en   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Facturas emitidas (ventas) ─────────────────────────────
CREATE TABLE IF NOT EXISTS facturas_emitidas (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    numero          VARCHAR(20) NOT NULL UNIQUE,
    fecha           DATE NOT NULL,
    fecha_vencimiento DATE,
    cliente_id      INT,
    cliente_nombre  VARCHAR(150),   -- copia por si el cliente cambia
    cliente_nif     VARCHAR(20),
    base_imponible  DECIMAL(12,2) DEFAULT 0.00,
    porcentaje_iva  DECIMAL(5,2)  DEFAULT 21.00,
    cuota_iva       DECIMAL(12,2) DEFAULT 0.00,
    porcentaje_irpf DECIMAL(5,2)  DEFAULT 0.00,
    cuota_irpf      DECIMAL(12,2) DEFAULT 0.00,
    total           DECIMAL(12,2) DEFAULT 0.00,
    liquido         DECIMAL(12,2) DEFAULT 0.00,
    notas           TEXT,
    estado          ENUM('borrador','emitida','pagada','cancelada') DEFAULT 'emitida',
    trimestre       TINYINT(1),
    creado_en       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cliente_id) REFERENCES clientes(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Líneas de facturas emitidas ────────────────────────────
CREATE TABLE IF NOT EXISTS facturas_emitidas_lineas (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    factura_id  INT NOT NULL,
    orden       INT DEFAULT 0,
    cantidad    DECIMAL(10,3) DEFAULT 1.000,
    descripcion VARCHAR(300) NOT NULL,
    precio      DECIMAL(12,4) DEFAULT 0.0000,
    total       DECIMAL(12,2) DEFAULT 0.00,
    FOREIGN KEY (factura_id) REFERENCES facturas_emitidas(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Facturas recibidas (compras) ───────────────────────────
CREATE TABLE IF NOT EXISTS facturas_recibidas (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    numero           VARCHAR(50)   NOT NULL,
    categoria        VARCHAR(50)   NOT NULL DEFAULT 'general',
    fecha            DATE          NOT NULL,
    proveedor_id     INT,
    proveedor_nombre VARCHAR(150),
    proveedor_nif    VARCHAR(20),
    base_imponible   DECIMAL(12,2) DEFAULT 0.00,
    porcentaje_iva   DECIMAL(5,2)  DEFAULT 21.00,
    cuota_iva        DECIMAL(12,2) DEFAULT 0.00,
    porcentaje_irpf  DECIMAL(5,2)  DEFAULT 0.00,
    cuota_irpf       DECIMAL(12,2) DEFAULT 0.00,
    total            DECIMAL(12,2) DEFAULT 0.00,
    descripcion      VARCHAR(300),
    notas            TEXT,
    trimestre        TINYINT(1),
    creado_en        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (proveedor_id) REFERENCES proveedores(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Secuencia de numeración ────────────────────────────────
CREATE TABLE IF NOT EXISTS numeracion (
    anio        INT NOT NULL,
    ultimo      INT DEFAULT 0,
    PRIMARY KEY (anio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insertar año actual
INSERT IGNORE INTO numeracion (anio, ultimo) VALUES (YEAR(CURDATE()), 0);

-- ─── Configuración de la aplicación ─────────────────────────
CREATE TABLE IF NOT EXISTS configuracion (
    clave       VARCHAR(80) NOT NULL PRIMARY KEY,
    valor       TEXT,
    actualizado TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Valores por defecto
INSERT IGNORE INTO configuracion (clave, valor) VALUES
    ('empresa_irpf',          '15'),
    ('last_update_check',     '0'),
    ('factura_prefijo',       'F'),
    ('factura_ceros',         '4');

-- ─── Registro de migraciones aplicadas ──────────────────────
CREATE TABLE IF NOT EXISTS migration_log (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    archivo       VARCHAR(200) NOT NULL,
    version       VARCHAR(20)  NULL,
    ejecutada_en  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    estado        ENUM('ok','error') NOT NULL DEFAULT 'ok',
    error_detalle TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

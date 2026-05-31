-- ============================================================
-- Migración: Gestión de trimestres cerrados y trimestre manual
-- Versión: 2.1.0 — Idempotente con IF NOT EXISTS
-- ============================================================
-- Permite:
--   1. Registrar qué trimestres fiscales están cerrados (presentados a AEAT)
--   2. Asignar facturas manualmente a un trimestre diferente al natural
--   3. Bloquear edición/creación en trimestres cerrados
-- ============================================================

-- ------------------------------------------------------------
-- 1. Tabla: trimestres_fiscales
-- ------------------------------------------------------------
-- Registro de trimestres fiscales con su estado de presentación
-- anio + trimestre = único (ej: 2025 + 1 = Q1 2025)
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS trimestres_fiscales (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    anio            INT NOT NULL,
    trimestre       TINYINT NOT NULL CHECK (trimestre IN (1, 2, 3, 4)),
    estado          ENUM('abierto', 'cerrado') NOT NULL DEFAULT 'abierto',
    fecha_cierre    DATE NULL,                    -- Cuando se marcó como cerrado
    usuario_cierre  INT NULL,                     -- Quién lo cerró (FK a usuarios)
    notas_cierre    VARCHAR(255) NULL,            -- Motivo/notas del cierre
    creado_en       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    modificado_en   TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_trimestre_fiscal (anio, trimestre),
    KEY idx_estado (estado),
    KEY idx_anio_trimestre (anio, trimestre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 2. Tabla: auditoria_trimestres (opcional, trazabilidad)
-- ------------------------------------------------------------
-- Log de cuándo se abren/cierran trimestres
-- ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS auditoria_trimestres (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    anio          INT NOT NULL,
    trimestre     TINYINT NOT NULL,
    accion        ENUM('abrir', 'cerrar') NOT NULL,
    usuario_id    INT NULL,
    fecha         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    notas         VARCHAR(255) NULL,

    KEY idx_accion (accion),
    KEY idx_fecha (fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 3. Modificación: facturas_emitidas
-- ------------------------------------------------------------
-- Añade trimestre_manual (NULL = usar trimestre natural de la fecha)
-- ------------------------------------------------------------

ALTER TABLE facturas_emitidas
ADD COLUMN IF NOT EXISTS trimestre_manual TINYINT NULL CHECK (trimestre_manual IN (1, 2, 3, 4)) AFTER trimestre;

-- Índice para consultas por trimestre (manual o natural)
CREATE INDEX IF NOT EXISTS idx_facturas_emitidas_trimestre ON facturas_emitidas (trimestre);
CREATE INDEX IF NOT EXISTS idx_facturas_emitidas_trimestre_manual ON facturas_emitidas (trimestre_manual);

-- ------------------------------------------------------------
-- 4. Modificación: facturas_recibidas
-- ------------------------------------------------------------
-- Añade trimestre_manual (NULL = usar trimestre natural de la fecha)
-- ------------------------------------------------------------

ALTER TABLE facturas_recibidas
ADD COLUMN IF NOT EXISTS trimestre_manual TINYINT NULL CHECK (trimestre_manual IN (1, 2, 3, 4)) AFTER trimestre;

CREATE INDEX IF NOT EXISTS idx_facturas_recibidas_trimestre ON facturas_recibidas (trimestre);
CREATE INDEX IF NOT EXISTS idx_facturas_recibidas_trimestre_manual ON facturas_recibidas (trimestre_manual);

-- ------------------------------------------------------------
-- 5. Migración inicial: Crear trimestres existentes como 'abiertos'
-- ------------------------------------------------------------
-- Detecta todos los trimestres que ya tienen facturas y los crea
-- como ABIERTOS por defecto (el usuario los cerrará cuando presente)
-- ------------------------------------------------------------

INSERT INTO trimestres_fiscales (anio, trimestre, estado)
SELECT DISTINCT
    YEAR(fecha) AS anio,
    trimestre AS trimestre,
    'abierto' AS estado
FROM facturas_emitidas
ON DUPLICATE KEY UPDATE estado = VALUES(estado);

INSERT INTO trimestres_fiscales (anio, trimestre, estado)
SELECT DISTINCT
    YEAR(fecha) AS anio,
    trimestre AS trimestre,
    'abierto' AS estado
FROM facturas_recibidas
ON DUPLICATE KEY UPDATE estado = VALUES(estado);

-- Asegurar que existen los trimestres del año actual
-- (útil para instalaciones nuevas o sin facturas aún)
INSERT IGNORE INTO trimestres_fiscales (anio, trimestre, estado) VALUES
    (YEAR(CURDATE()), 1, 'abierto'),
    (YEAR(CURDATE()), 2, 'abierto'),
    (YEAR(CURDATE()), 3, 'abierto'),
    (YEAR(CURDATE()), 4, 'abierto');

-- ------------------------------------------------------------
-- Fin de la migración
-- ------------------------------------------------------------

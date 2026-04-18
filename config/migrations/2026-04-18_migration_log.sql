-- ============================================================
-- Migración: tabla migration_log para control de migraciones
-- Versión: 1.7.0 — Idempotente con IF NOT EXISTS
-- ============================================================
-- Crea la tabla en installs existentes que aún no la tienen.
-- En fresh installs ya existe por install.sql.
-- ============================================================

CREATE TABLE IF NOT EXISTS migration_log (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    archivo       VARCHAR(200) NOT NULL,
    version       VARCHAR(20)  NULL,
    ejecutada_en  TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    estado        ENUM('ok','error') NOT NULL DEFAULT 'ok',
    error_detalle TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

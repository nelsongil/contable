-- ============================================================
-- Migración: tabla usuarios — roles, email, brute-force
-- Versión: 2.0.0 — Idempotente con IF NOT EXISTS
-- ============================================================

-- ─── 1. Añadir columnas nuevas ───────────────────────────────
ALTER TABLE usuarios
    ADD COLUMN IF NOT EXISTS email             VARCHAR(150) NULL,
    ADD COLUMN IF NOT EXISTS password_hash     VARCHAR(255) NULL,
    ADD COLUMN IF NOT EXISTS rol               ENUM('admin','colaborador') NOT NULL DEFAULT 'admin',
    ADD COLUMN IF NOT EXISTS estado            ENUM('activo','inactivo')   NOT NULL DEFAULT 'activo',
    ADD COLUMN IF NOT EXISTS intentos_fallidos TINYINT UNSIGNED            NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS bloqueado_hasta   DATETIME                    NULL;

-- ─── 2. Copiar password → password_hash (columna antigua) ────
-- Solo actualiza filas donde password_hash todavía está vacío
UPDATE usuarios
    SET password_hash = `password`
    WHERE (password_hash IS NULL OR password_hash = '')
      AND `password` IS NOT NULL
      AND `password` != '';

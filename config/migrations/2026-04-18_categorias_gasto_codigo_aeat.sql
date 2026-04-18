-- ============================================================
-- Migración: añade codigo_aeat a categorias_gasto
-- Versión: 1.7.0 — Idempotente con IF NOT EXISTS / UPDATE seguro
-- ============================================================

-- ─── Añadir columna codigo_aeat ─────────────────────────────
ALTER TABLE categorias_gasto
    ADD COLUMN IF NOT EXISTS codigo_aeat VARCHAR(3) NULL
        COMMENT 'Código AEAT G01-G39 para exportación Libro Registro Facturas Recibidas'
        AFTER nombre;

-- ─── Pre-poblar las 14 categorías seed ──────────────────────
-- Solo actualiza si el campo está vacío (idempotente en re-ejecuciones)
-- Códigos según Libro Registro IRPF estimación directa (HAC/773/2019)
UPDATE categorias_gasto SET codigo_aeat = 'G01' WHERE nombre = 'Material de oficina'                         AND (codigo_aeat IS NULL OR codigo_aeat = '');
UPDATE categorias_gasto SET codigo_aeat = 'G03' WHERE nombre = 'Software / SaaS / Suscripciones'             AND (codigo_aeat IS NULL OR codigo_aeat = '');
UPDATE categorias_gasto SET codigo_aeat = 'G10' WHERE nombre = 'Servicios profesionales (asesoría, etc.)'    AND (codigo_aeat IS NULL OR codigo_aeat = '');
UPDATE categorias_gasto SET codigo_aeat = 'G16' WHERE nombre = 'Formación y bibliografía'                    AND (codigo_aeat IS NULL OR codigo_aeat = '');
UPDATE categorias_gasto SET codigo_aeat = 'G14' WHERE nombre = 'Publicidad y marketing'                      AND (codigo_aeat IS NULL OR codigo_aeat = '');
UPDATE categorias_gasto SET codigo_aeat = 'G09' WHERE nombre = 'Vehículo — uso mixto (50%)'                  AND (codigo_aeat IS NULL OR codigo_aeat = '');
UPDATE categorias_gasto SET codigo_aeat = 'G15' WHERE nombre = 'Suministros hogar — trabajo remoto (30%)'   AND (codigo_aeat IS NULL OR codigo_aeat = '');
UPDATE categorias_gasto SET codigo_aeat = 'G15' WHERE nombre = 'Telecomunicaciones — uso mixto (50%)'       AND (codigo_aeat IS NULL OR codigo_aeat = '');
UPDATE categorias_gasto SET codigo_aeat = 'G21' WHERE nombre = 'Restauración / Dietas de negocio'           AND (codigo_aeat IS NULL OR codigo_aeat = '');
UPDATE categorias_gasto SET codigo_aeat = 'G07' WHERE nombre = 'Arrendamiento local u oficina'              AND (codigo_aeat IS NULL OR codigo_aeat = '');
UPDATE categorias_gasto SET codigo_aeat = 'G12' WHERE nombre = 'Seguros profesionales'                      AND (codigo_aeat IS NULL OR codigo_aeat = '');
UPDATE categorias_gasto SET codigo_aeat = 'G09' WHERE nombre = 'Reparaciones y conservación'                AND (codigo_aeat IS NULL OR codigo_aeat = '');
UPDATE categorias_gasto SET codigo_aeat = 'G11' WHERE nombre = 'Viajes y transporte'                        AND (codigo_aeat IS NULL OR codigo_aeat = '');
UPDATE categorias_gasto SET codigo_aeat = 'G16' WHERE nombre = 'Otros gastos'                               AND (codigo_aeat IS NULL OR codigo_aeat = '');

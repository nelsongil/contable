-- ============================================================
-- Migración: categorías de gasto configurables con deducibilidad
-- Versión: 1.7.0 — Idempotente con IF NOT EXISTS
-- ============================================================

-- ─── Tabla de categorías configurables ──────────────────────
CREATE TABLE IF NOT EXISTS categorias_gasto (
    id                 INT AUTO_INCREMENT PRIMARY KEY,
    nombre             VARCHAR(100)  NOT NULL,
    pct_iva_deducible  DECIMAL(5,2)  NOT NULL DEFAULT 100.00,
    pct_irpf_deducible DECIMAL(5,2)  NOT NULL DEFAULT 100.00,
    activa             TINYINT(1)    NOT NULL DEFAULT 1,
    creado_en          TIMESTAMP     DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Seed: categorías habituales para autónomos en España ───
-- Fundamentos legales:
--   Vehículo 50%:           Art. 95.Tres LIVA + Art. 22 LIRPF (afectación parcial uso mixto)
--   Suministros hogar 30%:  Art. 30.2 LIRPF (fórmula Hacienda para actividad en domicilio)
--   Telecomunicaciones 50%: Criterio DGT — uso mixto profesional/personal habitual
INSERT IGNORE INTO categorias_gasto
    (nombre,                                   pct_iva_deducible, pct_irpf_deducible) VALUES
('Material de oficina',                                  100.00,            100.00),
('Software / SaaS / Suscripciones',                     100.00,            100.00),
('Servicios profesionales (asesoría, etc.)',            100.00,            100.00),
('Formación y bibliografía',                            100.00,            100.00),
('Publicidad y marketing',                              100.00,            100.00),
('Vehículo — uso mixto (50%)',                           50.00,             50.00),
('Suministros hogar — trabajo remoto (30%)',             30.00,             30.00),
('Telecomunicaciones — uso mixto (50%)',                 50.00,             50.00),
('Restauración / Dietas de negocio',                   100.00,            100.00),
('Arrendamiento local u oficina',                      100.00,            100.00),
('Seguros profesionales',                              100.00,            100.00),
('Reparaciones y conservación',                        100.00,            100.00),
('Viajes y transporte',                                100.00,            100.00),
('Otros gastos',                                       100.00,            100.00);

-- ─── Añadir campos de deducibilidad a facturas_recibidas ─────
-- categoria_gasto_id: FK opcional — el usuario puede no categorizar
-- pct_iva_deducible:  se copia desde la categoría, editable manualmente por factura
-- pct_irpf_deducible: ídem para la parte de IRPF
ALTER TABLE facturas_recibidas
    ADD COLUMN IF NOT EXISTS categoria_gasto_id  INT          NULL
        AFTER categoria,
    ADD COLUMN IF NOT EXISTS pct_iva_deducible   DECIMAL(5,2) NOT NULL DEFAULT 100.00
        AFTER cuota_iva,
    ADD COLUMN IF NOT EXISTS pct_irpf_deducible  DECIMAL(5,2) NOT NULL DEFAULT 100.00
        AFTER cuota_irpf;

-- FK en dos sentencias separadas para idempotencia compatible con MariaDB 10.11
-- ADD CONSTRAINT IF NOT EXISTS no está soportado para FOREIGN KEY en MariaDB;
-- el patrón DROP IF EXISTS + ADD es equivalente y funciona desde MariaDB 10.1.4
ALTER TABLE facturas_recibidas
    DROP FOREIGN KEY IF EXISTS fk_fr_categoria_gasto;

ALTER TABLE facturas_recibidas
    ADD CONSTRAINT fk_fr_categoria_gasto
    FOREIGN KEY (categoria_gasto_id) REFERENCES categorias_gasto(id) ON DELETE SET NULL;

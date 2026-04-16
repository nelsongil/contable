-- Migración: Categorías y retención IRPF en facturas recibidas
-- Necesaria para Modelo 115 (arrendamientos) y categorización de gastos
-- Idempotente: usa IF NOT EXISTS para no fallar si ya existe la columna

ALTER TABLE facturas_recibidas ADD COLUMN IF NOT EXISTS categoria       VARCHAR(50)   NOT NULL DEFAULT 'general'   AFTER numero;
ALTER TABLE facturas_recibidas ADD COLUMN IF NOT EXISTS porcentaje_irpf DECIMAL(5,2)  NOT NULL DEFAULT 0.00        AFTER porcentaje_iva;
ALTER TABLE facturas_recibidas ADD COLUMN IF NOT EXISTS cuota_irpf      DECIMAL(12,2) NOT NULL DEFAULT 0.00        AFTER cuota_iva;
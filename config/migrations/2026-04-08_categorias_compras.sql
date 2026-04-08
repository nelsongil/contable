-- Migración: Categorías y retención IRPF en facturas recibidas
-- Necesaria para Modelo 115 (arrendamientos) y categorización de gastos

ALTER TABLE facturas_recibidas
  ADD COLUMN categoria       VARCHAR(50)   NOT NULL DEFAULT 'general'   AFTER numero,
  ADD COLUMN porcentaje_irpf DECIMAL(5,2)  NOT NULL DEFAULT 0.00        AFTER porcentaje_iva,
  ADD COLUMN cuota_irpf      DECIMAL(12,2) NOT NULL DEFAULT 0.00        AFTER cuota_iva;
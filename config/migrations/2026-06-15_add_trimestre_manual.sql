-- Migración: Añadir columna trimestre_manual a facturas_emitidas y facturas_recibidas
-- Fecha: 2026-06-15
-- Propósito: Regularizar schema - la migración 2026-05-31 pudo no ejecutarse en algunas instalaciones
-- Idempotente: usa IF NOT EXISTS, segura para ejecutar múltiples veces

-- Añadir columna trimestre_manual en facturas_emitidas si no existe
ALTER TABLE facturas_emitidas
ADD COLUMN IF NOT EXISTS trimestre_manual TINYINT NULL DEFAULT NULL AFTER trimestre;

-- Añadir columna trimestre_manual en facturas_recibidas si no existe
ALTER TABLE facturas_recibidas
ADD COLUMN IF NOT EXISTS trimestre_manual TINYINT NULL DEFAULT NULL AFTER trimestre;

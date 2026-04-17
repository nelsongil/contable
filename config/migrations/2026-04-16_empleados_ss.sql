-- Migración: Seguridad Social en empleados y cuota autónomo
-- Añade % SS por empleado, importes SS en retenciones, y tabla de cuota autónomo mensual
-- Idempotente: usa IF NOT EXISTS en todos los ALTER

-- Porcentajes SS en ficha del empleado (defecto 2024: empresa 29.90%, empleado 6.47%)
ALTER TABLE empleados
  ADD COLUMN IF NOT EXISTS porcentaje_ss_empresa  DECIMAL(5,2) NOT NULL DEFAULT 29.90 AFTER porcentaje_irpf,
  ADD COLUMN IF NOT EXISTS porcentaje_ss_empleado DECIMAL(5,2) NOT NULL DEFAULT 6.47  AFTER porcentaje_ss_empresa;

-- Importes SS reales en la nómina mensual
ALTER TABLE retenciones_empleados
  ADD COLUMN IF NOT EXISTS ss_empleado DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER retencion_irpf,
  ADD COLUMN IF NOT EXISTS ss_empresa  DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER ss_empleado;

-- Cuota mensual del autónomo (SS propia del empresario)
CREATE TABLE IF NOT EXISTS cuotas_autonomo (
  id        INT AUTO_INCREMENT PRIMARY KEY,
  anio      YEAR NOT NULL,
  mes       TINYINT UNSIGNED NOT NULL,
  importe   DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_autonomo_mes (anio, mes)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

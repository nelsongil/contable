-- Migración: Módulo de Empleados
-- Ejecutar manualmente en phpMyAdmin o activar desde Ajustes > Módulo Empleados

CREATE TABLE IF NOT EXISTS `empleados` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nombre` varchar(150) NOT NULL,
  `nif` varchar(20) NOT NULL DEFAULT '',
  `puesto` varchar(100) NOT NULL DEFAULT '',
  `salario_mensual` decimal(12,2) NOT NULL DEFAULT '0.00',
  `porcentaje_irpf` decimal(5,2) NOT NULL DEFAULT '0.00',
  `fecha_alta` date DEFAULT NULL,
  `activo` tinyint(1) NOT NULL DEFAULT '1',
  `creado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `retenciones_empleados` (
  `id` int NOT NULL AUTO_INCREMENT,
  `empleado_id` int NOT NULL,
  `anio` year NOT NULL,
  `mes` tinyint unsigned NOT NULL,
  `salario_pagado` decimal(12,2) NOT NULL DEFAULT '0.00',
  `retencion_irpf` decimal(12,2) NOT NULL DEFAULT '0.00',
  `creado_en` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_empleado_mes` (`empleado_id`,`anio`,`mes`),
  CONSTRAINT `fk_ret_empleado` FOREIGN KEY (`empleado_id`) REFERENCES `empleados` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

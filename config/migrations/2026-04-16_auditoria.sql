-- ============================================================
-- Migración: tabla de auditoría contable
-- Versión: 1.7.0
-- Registra quién, qué, cuándo en operaciones financieras.
-- ============================================================

CREATE TABLE IF NOT EXISTS auditoria (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    tabla       VARCHAR(50)  NOT NULL,
    registro_id INT,
    accion      VARCHAR(20)  NOT NULL,         -- 'crear','editar','cancelar','pagar','importar'
    datos_antes JSON,
    datos_despues JSON,
    usuario     VARCHAR(100),
    usuario_id  INT,
    ip          VARCHAR(45),
    creado_en   TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tabla_id   (tabla, registro_id),
    INDEX idx_creado_en  (creado_en),
    INDEX idx_usuario_id (usuario_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

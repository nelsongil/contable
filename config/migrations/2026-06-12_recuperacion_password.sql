-- Migración: Tabla para recuperación de contraseñas con código temporal
-- Creada: 2026-06-12

CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    usuario_id   INT NOT NULL,
    token        VARCHAR(6) NOT NULL,
    expira_en    DATETIME NOT NULL,
    usado        TINYINT(1) DEFAULT 0,
    creado_en    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    INDEX idx_token (token),
    INDEX idx_expira (expira_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

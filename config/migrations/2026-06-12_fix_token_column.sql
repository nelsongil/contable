-- Fix: Ampliar columna token a VARCHAR(255) para almacenar hash bcrypt completo
-- El hash de password_hash() tiene 60 caracteres, VARCHAR(6) lo truncaba

ALTER TABLE password_reset_tokens
MODIFY COLUMN token VARCHAR(255) NOT NULL;

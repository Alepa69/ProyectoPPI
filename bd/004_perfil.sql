-- Migración incremental: columnas de perfil de usuario (teléfono ya existía en 001_estructura.sql)
ALTER TABLE usuarios
    ADD COLUMN IF NOT EXISTS biografia VARCHAR(500) NULL AFTER telefono,
    ADD COLUMN IF NOT EXISTS foto_perfil VARCHAR(255) NULL AFTER biografia;

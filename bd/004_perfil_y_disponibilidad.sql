-- Datos públicos del perfil y noches bloqueadas por el anfitrión.
ALTER TABLE usuarios
    ADD COLUMN IF NOT EXISTS biografia VARCHAR(500) NULL AFTER telefono,
    ADD COLUMN IF NOT EXISTS foto_perfil VARCHAR(255) NULL AFTER biografia;

CREATE TABLE IF NOT EXISTS bloqueos_alojamiento (
    id_bloqueo INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_alojamiento INT UNSIGNED NOT NULL,
    fecha_inicio DATE NOT NULL,
    fecha_fin DATE NOT NULL,
    CONSTRAINT fk_bloqueo_alojamiento FOREIGN KEY (id_alojamiento)
        REFERENCES alojamientos(id_alojamiento) ON DELETE CASCADE,
    CONSTRAINT ck_bloqueo_fechas CHECK (fecha_fin > fecha_inicio),
    KEY idx_bloqueo_periodo (id_alojamiento, fecha_inicio, fecha_fin)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

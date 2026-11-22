-- Migracion incremental v2 (estilo Airbnb) - Proyecto PPI
-- Destino: MariaDB 10.4+; InnoDB; utf8mb4.
-- Compatible con instalaciones existentes creadas con 001_estructura.sql v1.
-- Idempotente: puede ejecutarse de forma segura sobre una BD ya poblada.
SET NAMES utf8mb4;

-- 1. Ampliar tabla alojamientos conservando PK/FK y columnas existentes
ALTER TABLE alojamientos
    ADD COLUMN IF NOT EXISTS tipo_espacio ENUM('alojamiento_entero','habitacion_privada','habitacion_compartida') NOT NULL DEFAULT 'alojamiento_entero' AFTER id_tipo,
    ADD COLUMN IF NOT EXISTS ciudad VARCHAR(100) NOT NULL DEFAULT '' AFTER municipio,
    ADD COLUMN IF NOT EXISTS referencias VARCHAR(500) NULL AFTER direccion,
    MODIFY COLUMN latitud DECIMAL(10,7) NULL,
    MODIFY COLUMN longitud DECIMAL(10,7) NULL,
    ADD COLUMN IF NOT EXISTS capacidad_personas SMALLINT UNSIGNED NOT NULL DEFAULT 1 AFTER capacidad,
    ADD COLUMN IF NOT EXISTS num_habitaciones SMALLINT UNSIGNED NOT NULL DEFAULT 1 AFTER capacidad_personas,
    ADD COLUMN IF NOT EXISTS num_camas SMALLINT UNSIGNED NOT NULL DEFAULT 1 AFTER num_habitaciones,
    ADD COLUMN IF NOT EXISTS num_banos DECIMAL(3,1) NOT NULL DEFAULT 1.0 AFTER num_camas,
    ADD COLUMN IF NOT EXISTS hora_checkin TIME NOT NULL DEFAULT '15:00:00' AFTER num_banos,
    ADD COLUMN IF NOT EXISTS hora_checkout TIME NOT NULL DEFAULT '11:00:00' AFTER hora_checkin,
    ADD COLUMN IF NOT EXISTS estancia_minima SMALLINT UNSIGNED NOT NULL DEFAULT 1 AFTER hora_checkout,
    ADD COLUMN IF NOT EXISTS permite_mascotas TINYINT(1) NOT NULL DEFAULT 0 AFTER estancia_minima,
    ADD COLUMN IF NOT EXISTS permite_fumar TINYINT(1) NOT NULL DEFAULT 0 AFTER permite_mascotas,
    ADD COLUMN IF NOT EXISTS permite_eventos TINYINT(1) NOT NULL DEFAULT 0 AFTER permite_fumar,
    ADD COLUMN IF NOT EXISTS reglas_adicionales TEXT NULL AFTER permite_eventos,
    ADD COLUMN IF NOT EXISTS precio_noche DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER precio_persona_noche,
    ADD COLUMN IF NOT EXISTS tarifa_limpieza DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER precio_noche;

ALTER TABLE alojamientos
    ADD INDEX IF NOT EXISTS idx_alojamiento_airbnb (estado, tipo_espacio, precio_noche),
    ADD INDEX IF NOT EXISTS idx_alojamiento_ciudad (ciudad);

-- Sincronizar valores iniciales en registros existentes si aun estan por defecto
UPDATE alojamientos
SET
    ciudad = CASE WHEN ciudad = '' THEN municipio ELSE ciudad END,
    capacidad_personas = CASE WHEN capacidad_personas = 1 AND capacidad > 1 THEN capacidad ELSE capacidad_personas END,
    precio_noche = CASE WHEN precio_noche = 0.00 AND precio_persona_noche > 0 THEN precio_persona_noche ELSE precio_noche END;

-- 2. Tabla de multiples imagenes por alojamiento
CREATE TABLE IF NOT EXISTS alojamiento_imagenes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    alojamiento_id INT UNSIGNED NOT NULL,
    ruta_o_url VARCHAR(500) NOT NULL,
    es_portada TINYINT(1) NOT NULL DEFAULT 0,
    orden SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_img_alojamiento FOREIGN KEY (alojamiento_id) REFERENCES alojamientos(id_alojamiento) ON DELETE CASCADE,
    KEY idx_img_alojamiento_orden (alojamiento_id, es_portada, orden)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Migrar fotos heredadas si alojamiento_imagenes no las tiene aun
INSERT INTO alojamiento_imagenes (alojamiento_id, ruta_o_url, es_portada, orden, creado_en)
SELECT f.id_alojamiento, f.ruta_archivo, IF(f.posicion = 1, 1, 0), f.posicion, f.creado_en
FROM fotos_alojamiento f
WHERE NOT EXISTS (
    SELECT 1
    FROM alojamiento_imagenes ai
    WHERE ai.alojamiento_id = f.id_alojamiento
      AND ai.ruta_o_url = f.ruta_archivo
);

-- 3. Catalogo de amenidades y tabla puente N:M
CREATE TABLE IF NOT EXISTS amenidades (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    categoria VARCHAR(60) NOT NULL DEFAULT 'Basicos',
    icono VARCHAR(80) NULL,
    UNIQUE KEY uq_amenidad_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS alojamiento_amenidades (
    alojamiento_id INT UNSIGNED NOT NULL,
    amenidad_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (alojamiento_id, amenidad_id),
    CONSTRAINT fk_aa_alojamiento FOREIGN KEY (alojamiento_id) REFERENCES alojamientos(id_alojamiento) ON DELETE CASCADE,
    CONSTRAINT fk_aa_amenidad FOREIGN KEY (amenidad_id) REFERENCES amenidades(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Carga idempotente del catalogo base de amenidades
INSERT IGNORE INTO amenidades (nombre, categoria, icono) VALUES
    ('Wifi de alta velocidad', 'Basicos', 'wifi'),
    ('Aire acondicionado', 'Basicos', 'snowflake'),
    ('Calefaccion', 'Basicos', 'flame'),
    ('Agua caliente', 'Basicos', 'droplets'),
    ('Televisor / Smart TV', 'Basicos', 'tv'),
    ('Zona de trabajo', 'Basicos', 'laptop'),
    ('Lavadora', 'Basicos', 'shirt'),
    ('Secadora', 'Basicos', 'wind'),
    ('Cocina equipada', 'Cocina y comedor', 'utensils'),
    ('Refrigerador', 'Cocina y comedor', 'box'),
    ('Microondas', 'Cocina y comedor', 'microwave'),
    ('Cafetera', 'Cocina y comedor', 'coffee'),
    ('Vajilla y cubiertos', 'Cocina y comedor', 'utensils-crossed'),
    ('Estacionamiento gratuito', 'Instalaciones', 'car'),
    ('Piscina', 'Instalaciones', 'waves'),
    ('Jacuzzi', 'Instalaciones', 'bath'),
    ('Gimnasio', 'Instalaciones', 'dumbbell'),
    ('Acceso a la playa', 'Instalaciones', 'umbrella'),
    ('Detector de humo', 'Seguridad', 'bell-ring'),
    ('Detector de monoxido de carbono', 'Seguridad', 'shield-alert'),
    ('Botiquin de primeros auxilios', 'Seguridad', 'cross'),
    ('Extintor de incendios', 'Seguridad', 'flame-kindling'),
    ('Cerradura inteligente / llegada autonoma', 'Seguridad', 'key-round'),
    ('Patio o balcon', 'Exterior', 'trees'),
    ('Parrilla / BBQ', 'Exterior', 'beef'),
    ('Jardin privado', 'Exterior', 'flower-2');

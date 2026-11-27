-- ============================================================================
-- HALOJA / Plataforma de alojamientos PPI - Script Unificado (Estructura + Datos)
-- Destino: MariaDB 10.4+; InnoDB; utf8mb4.
-- Incluye:
--   1. Estructura completa (v1 + migracion v2 + mensajes) de forma idempotente.
--   2. Limpieza de casas, reservas, mensajes y usuarios de prueba anteriores,
--      conservando unicamente al administrador alexisss.15.2005.
--   3. Catalogos base (tipos, servicios, metodos de pago, amenidades).
--   4. 20 usuarios anfitriones (bot1 a bot20) y 20 casas en ciudades principales
--      de El Salvador con coordenadas GPS, fotos, amenidades, servicios y pagos.
-- ============================================================================

SET NAMES utf8mb4;

-- ----------------------------------------------------------------------------
-- 1. ESTRUCTURA COMPLETA DE TABLAS (Idempotente)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS usuarios (
    id_usuario INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(100) NOT NULL,
    apellido VARCHAR(100) NOT NULL,
    correo VARCHAR(191) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    telefono VARCHAR(25) NULL,
    rol ENUM('usuario','administrador') NOT NULL DEFAULT 'usuario',
    estado ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_usuario_correo (correo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS tipos_alojamiento (
    id_tipo INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(60) NOT NULL,
    UNIQUE KEY uq_tipo_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS alojamientos (
    id_alojamiento INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_anfitrion INT UNSIGNED NOT NULL,
    id_tipo INT UNSIGNED NOT NULL,
    tipo_espacio ENUM('alojamiento_entero','habitacion_privada','habitacion_compartida') NOT NULL DEFAULT 'alojamiento_entero',
    titulo VARCHAR(150) NOT NULL,
    descripcion TEXT NOT NULL,
    pais VARCHAR(80) NOT NULL DEFAULT 'El Salvador',
    departamento VARCHAR(100) NOT NULL,
    municipio VARCHAR(100) NOT NULL,
    ciudad VARCHAR(100) NOT NULL DEFAULT '',
    direccion VARCHAR(255) NOT NULL,
    referencias VARCHAR(500) NULL,
    latitud DECIMAL(10,7) NULL,
    longitud DECIMAL(10,7) NULL,
    capacidad SMALLINT UNSIGNED NOT NULL,
    capacidad_personas SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    num_habitaciones SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    num_camas SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    num_banos DECIMAL(3,1) NOT NULL DEFAULT 1.0,
    hora_checkin TIME NOT NULL DEFAULT '15:00:00',
    hora_checkout TIME NOT NULL DEFAULT '11:00:00',
    estancia_minima SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    permite_mascotas TINYINT(1) NOT NULL DEFAULT 0,
    permite_fumar TINYINT(1) NOT NULL DEFAULT 0,
    permite_eventos TINYINT(1) NOT NULL DEFAULT 0,
    reglas_adicionales TEXT NULL,
    precio_persona_noche DECIMAL(10,2) NOT NULL,
    precio_noche DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    tarifa_limpieza DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    estado ENUM('borrador','publicado','inactivo','bloqueado') NOT NULL DEFAULT 'borrador',
    motivo_bloqueo VARCHAR(500) NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_alojamiento_anfitrion FOREIGN KEY (id_anfitrion) REFERENCES usuarios(id_usuario) ON DELETE RESTRICT,
    CONSTRAINT fk_alojamiento_tipo FOREIGN KEY (id_tipo) REFERENCES tipos_alojamiento(id_tipo) ON DELETE RESTRICT,
    CONSTRAINT ck_alojamiento_latitud CHECK (latitud IS NULL OR latitud BETWEEN -90 AND 90),
    CONSTRAINT ck_alojamiento_longitud CHECK (longitud IS NULL OR longitud BETWEEN -180 AND 180),
    CONSTRAINT ck_alojamiento_capacidad CHECK (capacidad > 0),
    CONSTRAINT ck_alojamiento_capacidad_personas CHECK (capacidad_personas > 0),
    CONSTRAINT ck_alojamiento_num_banos CHECK (num_banos > 0),
    CONSTRAINT ck_alojamiento_estancia_minima CHECK (estancia_minima > 0),
    CONSTRAINT ck_alojamiento_precio CHECK (precio_persona_noche > 0),
    CONSTRAINT ck_alojamiento_precio_noche CHECK (precio_noche >= 0),
    CONSTRAINT ck_alojamiento_tarifa_limpieza CHECK (tarifa_limpieza >= 0),
    KEY idx_alojamiento_catalogo (estado, id_tipo, precio_persona_noche),
    KEY idx_alojamiento_airbnb (estado, tipo_espacio, precio_noche),
    KEY idx_alojamiento_ubicacion (pais, departamento, municipio),
    KEY idx_alojamiento_ciudad (ciudad)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Asegura columnas e indices v2 si la BD venia de una instalacion v1 anterior
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

CREATE TABLE IF NOT EXISTS fotos_alojamiento (
    id_foto INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_alojamiento INT UNSIGNED NOT NULL,
    ruta_archivo VARCHAR(255) NOT NULL,
    posicion TINYINT UNSIGNED NOT NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_foto_alojamiento FOREIGN KEY (id_alojamiento) REFERENCES alojamientos(id_alojamiento) ON DELETE RESTRICT,
    CONSTRAINT ck_foto_posicion CHECK (posicion BETWEEN 1 AND 8),
    UNIQUE KEY uq_foto_posicion (id_alojamiento, posicion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS servicios (
    id_servicio INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(80) NOT NULL,
    UNIQUE KEY uq_servicio_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS alojamiento_servicio (
    id_alojamiento INT UNSIGNED NOT NULL,
    id_servicio INT UNSIGNED NOT NULL,
    PRIMARY KEY (id_alojamiento, id_servicio),
    CONSTRAINT fk_as_alojamiento FOREIGN KEY (id_alojamiento) REFERENCES alojamientos(id_alojamiento) ON DELETE RESTRICT,
    CONSTRAINT fk_as_servicio FOREIGN KEY (id_servicio) REFERENCES servicios(id_servicio) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS metodos_pago (
    id_metodo_pago INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(80) NOT NULL,
    UNIQUE KEY uq_metodo_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS alojamiento_metodo_pago (
    id_alojamiento INT UNSIGNED NOT NULL,
    id_metodo_pago INT UNSIGNED NOT NULL,
    PRIMARY KEY (id_alojamiento, id_metodo_pago),
    CONSTRAINT fk_amp_alojamiento FOREIGN KEY (id_alojamiento) REFERENCES alojamientos(id_alojamiento) ON DELETE RESTRICT,
    CONSTRAINT fk_amp_metodo FOREIGN KEY (id_metodo_pago) REFERENCES metodos_pago(id_metodo_pago) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reservas (
    id_reserva INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_alojamiento INT UNSIGNED NOT NULL,
    id_solicitante INT UNSIGNED NOT NULL,
    fecha_entrada DATE NOT NULL,
    fecha_salida DATE NOT NULL,
    cantidad_huespedes SMALLINT UNSIGNED NOT NULL,
    precio_persona_noche_aplicado DECIMAL(10,2) NOT NULL,
    total DECIMAL(14,2) NOT NULL,
    estado ENUM('pendiente','aceptada','rechazada','cancelada') NOT NULL DEFAULT 'pendiente',
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    respondido_en DATETIME NULL,
    id_cancelado_por INT UNSIGNED NULL,
    cancelado_en DATETIME NULL,
    motivo_cancelacion VARCHAR(500) NULL,
    CONSTRAINT fk_reserva_alojamiento FOREIGN KEY (id_alojamiento) REFERENCES alojamientos(id_alojamiento) ON DELETE RESTRICT,
    CONSTRAINT fk_reserva_solicitante FOREIGN KEY (id_solicitante) REFERENCES usuarios(id_usuario) ON DELETE RESTRICT,
    CONSTRAINT fk_reserva_cancelador FOREIGN KEY (id_cancelado_por) REFERENCES usuarios(id_usuario) ON DELETE RESTRICT,
    CONSTRAINT ck_reserva_fechas CHECK (fecha_salida > fecha_entrada),
    CONSTRAINT ck_reserva_huespedes CHECK (cantidad_huespedes > 0),
    CONSTRAINT ck_reserva_tarifa CHECK (precio_persona_noche_aplicado > 0),
    CONSTRAINT ck_reserva_total CHECK (
        total = DATEDIFF(fecha_salida, fecha_entrada)
                * cantidad_huespedes * precio_persona_noche_aplicado
    ),
    CONSTRAINT ck_reserva_cancelacion CHECK (
        (estado = 'cancelada' AND id_cancelado_por IS NOT NULL AND cancelado_en IS NOT NULL)
        OR
        (estado <> 'cancelada' AND id_cancelado_por IS NULL AND cancelado_en IS NULL)
    ),
    KEY idx_reserva_disponibilidad (id_alojamiento, estado, fecha_entrada, fecha_salida),
    KEY idx_reserva_solicitante (id_solicitante, estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS mensajes (
    id_mensaje INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_reserva INT UNSIGNED NOT NULL,
    id_emisor INT UNSIGNED NOT NULL,
    mensaje TEXT NOT NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_mensaje_reserva FOREIGN KEY (id_reserva) REFERENCES reservas(id_reserva) ON DELETE CASCADE,
    CONSTRAINT fk_mensaje_emisor FOREIGN KEY (id_emisor) REFERENCES usuarios(id_usuario) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------------------
-- 2. LIMPIEZA TOTAL DE DATOS DE PRUEBA (Conservando solo a alexisss.15.2005)
--    Orden hijo -> padre para respetar todas las FOREIGN KEY activas.
-- ----------------------------------------------------------------------------
DELETE FROM mensajes;
DELETE FROM reservas;
DELETE FROM alojamiento_imagenes;
DELETE FROM fotos_alojamiento;
DELETE FROM alojamiento_amenidades;
DELETE FROM alojamiento_servicio;
DELETE FROM alojamiento_metodo_pago;
DELETE FROM alojamientos;
DELETE FROM tipos_alojamiento;
DELETE FROM servicios;
DELETE FROM metodos_pago;
DELETE FROM amenidades;
DELETE FROM usuarios WHERE correo NOT LIKE 'alexisss.15.2005%';

-- Reinicio de contadores AUTO_INCREMENT en tablas vaciadas
ALTER TABLE alojamientos AUTO_INCREMENT = 1;
ALTER TABLE alojamiento_imagenes AUTO_INCREMENT = 1;
ALTER TABLE fotos_alojamiento AUTO_INCREMENT = 1;
ALTER TABLE reservas AUTO_INCREMENT = 1;
ALTER TABLE mensajes AUTO_INCREMENT = 1;
ALTER TABLE tipos_alojamiento AUTO_INCREMENT = 1;
ALTER TABLE servicios AUTO_INCREMENT = 1;
ALTER TABLE metodos_pago AUTO_INCREMENT = 1;
ALTER TABLE amenidades AUTO_INCREMENT = 1;

-- ----------------------------------------------------------------------------
-- 3. CARGA DE CATALOGOS, ADMIN Y 20 CASAS CON SUS 20 ANFITRIONES BOT
-- ----------------------------------------------------------------------------
START TRANSACTION;

-- 3.1 Catalogos base
INSERT INTO tipos_alojamiento (nombre) VALUES
('Casa'),
('Apartamento'),
('Habitacion');

INSERT INTO servicios (nombre) VALUES
('Wifi'),
('Estacionamiento'),
('Aire acondicionado'),
('Cocina'),
('Piscina'),
('Television'),
('Agua caliente'),
('Lavadora');

INSERT INTO metodos_pago (nombre) VALUES
('Efectivo'),
('Transferencia bancaria');

INSERT INTO amenidades (nombre, categoria, icono) VALUES
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

-- 3.2 Garantizar rol administrador al usuario alexisss.15.2005 si ya existia,
--     o crearlo automaticamente si se ejecuta en una base de datos nueva.
UPDATE usuarios
SET rol = 'administrador', estado = 'activo'
WHERE correo LIKE 'alexisss.15.2005%';

INSERT INTO usuarios (nombre, apellido, correo, password_hash, telefono, rol, estado)
SELECT
    'Alexis',
    'González',
    'alexisss.15.2005@gmail.com',
    '$2y$10$RfMVTAH/LERhwkUEve7L0eUmZJGpV.vvfBa8jHz.6fCPe0856wfDe',
    '7000-0000',
    'administrador',
    'activo'
FROM DUAL
WHERE NOT EXISTS (
    SELECT 1 FROM usuarios WHERE correo LIKE 'alexisss.15.2005%'
);

-- 3.3 20 Usuarios anfitriones propietarios (bot1 a bot20)
INSERT INTO usuarios (nombre, apellido, correo, password_hash, telefono, rol, estado, creado_en, actualizado_en) VALUES
('Bot1',  'Anfitrión', 'bot1@haloja.test',  '$2y$10$RfMVTAH/LERhwkUEve7L0eUmZJGpV.vvfBa8jHz.6fCPe0856wfDe', '7101-0001', 'usuario', 'activo', '2026-11-01 09:00:00', '2026-11-01 09:00:00'),
('Bot2',  'Anfitrión', 'bot2@haloja.test',  '$2y$10$RfMVTAH/LERhwkUEve7L0eUmZJGpV.vvfBa8jHz.6fCPe0856wfDe', '7101-0002', 'usuario', 'activo', '2026-11-02 09:00:00', '2026-11-02 09:00:00'),
('Bot3',  'Anfitrión', 'bot3@haloja.test',  '$2y$10$RfMVTAH/LERhwkUEve7L0eUmZJGpV.vvfBa8jHz.6fCPe0856wfDe', '7101-0003', 'usuario', 'activo', '2026-11-03 09:00:00', '2026-11-03 09:00:00'),
('Bot4',  'Anfitrión', 'bot4@haloja.test',  '$2y$10$RfMVTAH/LERhwkUEve7L0eUmZJGpV.vvfBa8jHz.6fCPe0856wfDe', '7101-0004', 'usuario', 'activo', '2026-11-04 09:00:00', '2026-11-04 09:00:00'),
('Bot5',  'Anfitrión', 'bot5@haloja.test',  '$2y$10$RfMVTAH/LERhwkUEve7L0eUmZJGpV.vvfBa8jHz.6fCPe0856wfDe', '7101-0005', 'usuario', 'activo', '2026-11-05 09:00:00', '2026-11-05 09:00:00'),
('Bot6',  'Anfitrión', 'bot6@haloja.test',  '$2y$10$RfMVTAH/LERhwkUEve7L0eUmZJGpV.vvfBa8jHz.6fCPe0856wfDe', '7101-0006', 'usuario', 'activo', '2026-11-06 09:00:00', '2026-11-06 09:00:00'),
('Bot7',  'Anfitrión', 'bot7@haloja.test',  '$2y$10$RfMVTAH/LERhwkUEve7L0eUmZJGpV.vvfBa8jHz.6fCPe0856wfDe', '7101-0007', 'usuario', 'activo', '2026-11-07 09:00:00', '2026-11-07 09:00:00'),
('Bot8',  'Anfitrión', 'bot8@haloja.test',  '$2y$10$RfMVTAH/LERhwkUEve7L0eUmZJGpV.vvfBa8jHz.6fCPe0856wfDe', '7101-0008', 'usuario', 'activo', '2026-11-08 09:00:00', '2026-11-08 09:00:00'),
('Bot9',  'Anfitrión', 'bot9@haloja.test',  '$2y$10$RfMVTAH/LERhwkUEve7L0eUmZJGpV.vvfBa8jHz.6fCPe0856wfDe', '7101-0009', 'usuario', 'activo', '2026-11-09 09:00:00', '2026-11-09 09:00:00'),
('Bot10', 'Anfitrión', 'bot10@haloja.test', '$2y$10$RfMVTAH/LERhwkUEve7L0eUmZJGpV.vvfBa8jHz.6fCPe0856wfDe', '7101-0010', 'usuario', 'activo', '2026-11-10 09:00:00', '2026-11-10 09:00:00'),
('Bot11', 'Anfitrión', 'bot11@haloja.test', '$2y$10$RfMVTAH/LERhwkUEve7L0eUmZJGpV.vvfBa8jHz.6fCPe0856wfDe', '7101-0011', 'usuario', 'activo', '2026-11-11 09:00:00', '2026-11-11 09:00:00'),
('Bot12', 'Anfitrión', 'bot12@haloja.test', '$2y$10$RfMVTAH/LERhwkUEve7L0eUmZJGpV.vvfBa8jHz.6fCPe0856wfDe', '7101-0012', 'usuario', 'activo', '2026-11-12 09:00:00', '2026-11-12 09:00:00'),
('Bot13', 'Anfitrión', 'bot13@haloja.test', '$2y$10$RfMVTAH/LERhwkUEve7L0eUmZJGpV.vvfBa8jHz.6fCPe0856wfDe', '7101-0013', 'usuario', 'activo', '2026-11-13 09:00:00', '2026-11-13 09:00:00'),
('Bot14', 'Anfitrión', 'bot14@haloja.test', '$2y$10$RfMVTAH/LERhwkUEve7L0eUmZJGpV.vvfBa8jHz.6fCPe0856wfDe', '7101-0014', 'usuario', 'activo', '2026-11-14 09:00:00', '2026-11-14 09:00:00'),
('Bot15', 'Anfitrión', 'bot15@haloja.test', '$2y$10$RfMVTAH/LERhwkUEve7L0eUmZJGpV.vvfBa8jHz.6fCPe0856wfDe', '7101-0015', 'usuario', 'activo', '2026-11-15 09:00:00', '2026-11-15 09:00:00'),
('Bot16', 'Anfitrión', 'bot16@haloja.test', '$2y$10$RfMVTAH/LERhwkUEve7L0eUmZJGpV.vvfBa8jHz.6fCPe0856wfDe', '7101-0016', 'usuario', 'activo', '2026-11-16 09:00:00', '2026-11-16 09:00:00'),
('Bot17', 'Anfitrión', 'bot17@haloja.test', '$2y$10$RfMVTAH/LERhwkUEve7L0eUmZJGpV.vvfBa8jHz.6fCPe0856wfDe', '7101-0017', 'usuario', 'activo', '2026-11-17 09:00:00', '2026-11-17 09:00:00'),
('Bot18', 'Anfitrión', 'bot18@haloja.test', '$2y$10$RfMVTAH/LERhwkUEve7L0eUmZJGpV.vvfBa8jHz.6fCPe0856wfDe', '7101-0018', 'usuario', 'activo', '2026-11-18 09:00:00', '2026-11-18 09:00:00'),
('Bot19', 'Anfitrión', 'bot19@haloja.test', '$2y$10$RfMVTAH/LERhwkUEve7L0eUmZJGpV.vvfBa8jHz.6fCPe0856wfDe', '7101-0019', 'usuario', 'activo', '2026-11-19 09:00:00', '2026-11-19 09:00:00'),
('Bot20', 'Anfitrión', 'bot20@haloja.test', '$2y$10$RfMVTAH/LERhwkUEve7L0eUmZJGpV.vvfBa8jHz.6fCPe0856wfDe', '7101-0020', 'usuario', 'activo', '2026-11-20 09:00:00', '2026-11-20 09:00:00');

-- 3.4 20 Casas en las principales ciudades y destinos de El Salvador (IDs 1 al 20)
INSERT INTO alojamientos (
    id_alojamiento, id_anfitrion, id_tipo, tipo_espacio, titulo, descripcion,
    pais, departamento, municipio, ciudad, direccion, referencias,
    latitud, longitud, capacidad, capacidad_personas, num_habitaciones,
    num_camas, num_banos, hora_checkin, hora_checkout, estancia_minima,
    permite_mascotas, permite_fumar, permite_eventos, reglas_adicionales,
    precio_persona_noche, precio_noche, tarifa_limpieza, estado,
    motivo_bloqueo, creado_en, actualizado_en
) VALUES
(
    1, (SELECT id_usuario FROM usuarios WHERE correo = 'bot1@haloja.test' LIMIT 1), 1, 'alojamiento_entero',
    'Casa Moderna con Jardín en Colonia Escalón',
    'Residencia amplia de dos niveles en zona segura de San Salvador. Cuenta con jardín privado, área de BBQ, escritorio para teletrabajo y cochera techada para dos vehículos.',
    'El Salvador', 'San Salvador', 'San Salvador', 'San Salvador',
    '89 Avenida Norte y 9a Calle Poniente, Col. Escalón', 'A 3 minutos del Redondel Masferrer y Plaza Futura',
    13.7028000, -89.2395000, 6, 6, 3, 4, 2.5, '15:00:00', '11:00:00', 1,
    1, 0, 0, 'Respetar normas de silencio después de las 10:00 p. m.',
    125.00, 125.00, 20.00, 'publicado', NULL, '2026-11-26 10:00:00', '2026-11-26 10:00:00'
),
(
    2, (SELECT id_usuario FROM usuarios WHERE correo = 'bot2@haloja.test' LIMIT 1), 1, 'alojamiento_entero',
    'Casa Familiar cerca del Paseo El Carmen',
    'Acogedora casa remodelada en residencial privada de Santa Tecla. Espacios iluminados, patio interior con terraza y fácil acceso a restaurantes, parques y centros comerciales.',
    'El Salvador', 'La Libertad', 'Santa Tecla', 'Santa Tecla',
    '5a Calle Poniente #24, Col. Quezaltepec', 'A 4 minutos del Paseo El Carmen y Parque San Martín',
    13.6769000, -89.2797000, 5, 5, 3, 3, 2.0, '14:00:00', '11:00:00', 1,
    0, 0, 0, 'No se permiten fiestas ni fumar en interiores.',
    78.00, 78.00, 12.00, 'publicado', NULL, '2026-11-25 18:00:00', '2026-11-25 18:00:00'
),
(
    3, (SELECT id_usuario FROM usuarios WHERE correo = 'bot3@haloja.test' LIMIT 1), 1, 'alojamiento_entero',
    'Residencia Ejecutiva en Antiguo Cuscatlán',
    'Casa contemporánea en urbanización cerrada con seguridad 24/7. Ideal para familias o viajes de negocios gracias a su conectividad rápida hacia Multiplaza, La Gran Vía y Santa Elena.',
    'El Salvador', 'La Libertad', 'Antiguo Cuscatlán', 'Antiguo Cuscatlán',
    'Calle Conchagua #18, Urb. Santa Elena', 'A 5 minutos de la Embajada de Estados Unidos',
    13.6688000, -89.2531000, 6, 6, 3, 4, 2.5, '15:00:00', '12:00:00', 1,
    0, 0, 0, 'Registro obligatorio en caseta de vigilancia al ingresar.',
    98.00, 98.00, 15.00, 'publicado', NULL, '2026-11-25 12:30:00', '2026-11-25 12:30:00'
),
(
    4, (SELECT id_usuario FROM usuarios WHERE correo = 'bot4@haloja.test' LIMIT 1), 1, 'alojamiento_entero',
    'Casa Histórica Restaurada en Santa Ana',
    'Amplia vivienda con estilo neoclásico a pocas cuadras del centro histórico santaneco. Incluye patio central con plantas, cocina equipada y parqueo interno privado.',
    'El Salvador', 'Santa Ana', 'Santa Ana', 'Santa Ana',
    '2a Avenida Sur #45, Barrio San Juan', 'A 5 cuadras de la Catedral y Teatro Nacional de Santa Ana',
    13.9942000, -89.5597000, 6, 6, 3, 4, 2.0, '15:00:00', '11:00:00', 1,
    1, 0, 0, 'Cuidar el mobiliario de madera y apagar aires al salir.',
    65.00, 65.00, 10.00, 'publicado', NULL, '2026-11-24 16:00:00', '2026-11-24 16:00:00'
),
(
    5, (SELECT id_usuario FROM usuarios WHERE correo = 'bot5@haloja.test' LIMIT 1), 1, 'alojamiento_entero',
    'Casa Climatizada con Piscina en San Miguel',
    'Perfecta para el clima cálido de oriente. Cuenta con piscina privada, aire acondicionado en todas las habitaciones, terraza techada con parrilla y estacionamiento cerrado.',
    'El Salvador', 'San Miguel', 'San Miguel', 'San Miguel',
    'Colonia Ciudad Jardín, Calle Los Almendros #12', 'A 5 minutos de Metrocentro San Miguel',
    13.4833000, -88.1833000, 7, 7, 3, 5, 2.5, '14:00:00', '11:00:00', 1,
    1, 0, 0, 'No ingresar envases de vidrio al área de la piscina.',
    82.00, 82.00, 15.00, 'publicado', NULL, '2026-11-24 09:40:00', '2026-11-24 09:40:00'
),
(
    6, (SELECT id_usuario FROM usuarios WHERE correo = 'bot6@haloja.test' LIMIT 1), 1, 'alojamiento_entero',
    'Casa Amplia con Corredor en Sonsonate',
    'Vivienda fresca y espaciosa en zona residencial tranquila, punto estratégico para visitar la Ruta de las Flores y las playas de Los Cóbanos y Acajutla.',
    'El Salvador', 'Sonsonate', 'Sonsonate', 'Sonsonate',
    'Colonia Sensunapán, Pasaje 3 #19', 'A 3 minutos del Parque Rafael Campo y Plaza Ferroviaria',
    13.7189000, -89.7242000, 5, 5, 2, 3, 2.0, '15:00:00', '11:00:00', 1,
    0, 0, 0, 'Mantener portón cerrado durante la noche.',
    60.00, 60.00, 10.00, 'publicado', NULL, '2026-11-23 19:20:00', '2026-11-23 19:20:00'
),
(
    7, (SELECT id_usuario FROM usuarios WHERE correo = 'bot7@haloja.test' LIMIT 1), 1, 'alojamiento_entero',
    'Casa de Clima Fresco en Ahuachapán',
    'Casa acogedora cerca del centro de Ahuachapán y a pocos minutos de los ausoles y termales. Dispone de agua caliente, cafetera con café de altura y jardín trasero.',
    'El Salvador', 'Ahuachapán', 'Ahuachapán', 'Ahuachapán',
    '4a Calle Oriente, Barrio El Calvario #31', 'A 3 cuadras del Parque La Concordia',
    13.9214000, -89.8450000, 5, 5, 2, 3, 1.5, '14:00:00', '11:00:00', 1,
    1, 0, 0, 'Se acepta una mascota pequeña bajo supervisión.',
    58.00, 58.00, 10.00, 'publicado', NULL, '2026-11-23 11:15:00', '2026-11-23 11:15:00'
),
(
    8, (SELECT id_usuario FROM usuarios WHERE correo = 'bot8@haloja.test' LIMIT 1), 1, 'alojamiento_entero',
    'Casa Familiar con Vista al Volcán en San Vicente',
    'Cómoda casa con patio arbolado y terraza mirando hacia el Volcán Chinchontepec. Ideal para familias que visitan la ciudad, la Laguna de Apastepeque o el Parque Amapulapa.',
    'El Salvador', 'San Vicente', 'San Vicente', 'San Vicente',
    '6a Avenida Norte #14, Barrio San Francisco', 'A 4 minutos del Parque Central y la Torre Vicentina',
    13.6422000, -88.7842000, 5, 5, 3, 3, 2.0, '14:00:00', '11:00:00', 1,
    1, 0, 0, 'No fumar dentro de las habitaciones.',
    55.00, 55.00, 8.00, 'publicado', NULL, '2026-11-22 15:00:00', '2026-11-22 15:00:00'
),
(
    9, (SELECT id_usuario FROM usuarios WHERE correo = 'bot9@haloja.test' LIMIT 1), 1, 'alojamiento_entero',
    'Casa Residencial Céntrica en Zacatecoluca',
    'Vivienda moderna con aire acondicionado, wifi rápido y cochera privada. Excelente ubicación a 20 minutos del Aeropuerto Internacional y a 25 minutos de la Costa del Sol.',
    'El Salvador', 'La Paz', 'Zacatecoluca', 'Zacatecoluca',
    'Avenida José Simeón Cañas #22, Barrio El Centro', 'A 3 cuadras de la Catedral Nuestra Señora de los Pobres',
    13.5000000, -88.8667000, 4, 4, 2, 3, 1.5, '15:00:00', '11:00:00', 1,
    0, 0, 0, 'Horario de entrada flexible con aviso previo.',
    52.00, 52.00, 8.00, 'publicado', NULL, '2026-11-21 17:45:00', '2026-11-21 17:45:00'
),
(
    10, (SELECT id_usuario FROM usuarios WHERE correo = 'bot10@haloja.test' LIMIT 1), 1, 'alojamiento_entero',
    'Casa Colonial con Patio Tropical en Usulután',
    'Casa amplia con habitaciones climatizadas, cocina equipada y corredor con hamacas. Base ideal para explorar la Bahía de Jiquilisco, Alegría y la Laguna de Alegría.',
    'El Salvador', 'Usulután', 'Usulután', 'Usulután',
    '3a Calle Poniente #17, Barrio El Calvario', 'A 2 cuadras del Parque Raúl Francisco Munguía',
    13.3500000, -88.4500000, 6, 6, 3, 4, 2.0, '14:00:00', '11:00:00', 1,
    1, 0, 0, 'Apagar luces y aires acondicionados al salir.',
    58.00, 58.00, 10.00, 'publicado', NULL, '2026-11-21 10:10:00', '2026-11-21 10:10:00'
),
(
    11, (SELECT id_usuario FROM usuarios WHERE correo = 'bot11@haloja.test' LIMIT 1), 1, 'alojamiento_entero',
    'Casa Panorámica frente al Golfo en La Unión',
    'Casa de descanso con balcón amplio hacia el Golfo de Fonseca, piscina pequeña y área de parrilla. Perfecta para grupos que planean tours a las islas o el Volcán Conchagua.',
    'El Salvador', 'La Unión', 'La Unión', 'La Unión',
    'Barrio Las Flores, Calle hacia el Muelle Municipal #9', 'A 5 minutos del Parque de la Familia en La Unión',
    13.3369000, -87.8439000, 6, 6, 3, 4, 2.5, '15:00:00', '12:00:00', 1,
    1, 0, 0, 'No se permiten fiestas con música alta después de las 10:00 p. m.',
    85.00, 85.00, 15.00, 'publicado', NULL, '2026-11-20 14:30:00', '2026-11-20 14:30:00'
),
(
    12, (SELECT id_usuario FROM usuarios WHERE correo = 'bot12@haloja.test' LIMIT 1), 1, 'alojamiento_entero',
    'Casa de Montaña con Terraza en Chalatenango',
    'Propiedad rodeada de vegetación y aire puro en zona alta de Chalatenango. Cuenta con terraza panorámica, agua caliente, wifi estable y espacio para fogatas familiares.',
    'El Salvador', 'Chalatenango', 'Chalatenango', 'Chalatenango',
    'Barrio El Chile, Calle Principal #28', 'A 5 minutos de la Plaza Central de Chalatenango',
    14.0333000, -88.9333000, 5, 5, 2, 3, 2.0, '15:00:00', '11:00:00', 1,
    1, 0, 0, 'Apagar completamente la fogata antes de retirarse a descansar.',
    62.00, 62.00, 10.00, 'publicado', NULL, '2026-11-19 16:20:00', '2026-11-19 16:20:00'
),
(
    13, (SELECT id_usuario FROM usuarios WHERE correo = 'bot13@haloja.test' LIMIT 1), 1, 'alojamiento_entero',
    'Casa Mirador en las Faldas del Cerro en Cojutepeque',
    'Vivienda cómoda con clima agradable todo el año y vista hacia el valle. Equipada con cocina completa, lavadora, patio privado y estacionamiento seguro.',
    'El Salvador', 'Cuscatlán', 'Cojutepeque', 'Cojutepeque',
    'Barrio Concepción, Calle hacia el Cerro de las Pavas #11', 'A 4 minutos del parque central y mirador',
    13.7167000, -88.9333000, 4, 4, 2, 3, 1.5, '14:00:00', '11:00:00', 1,
    0, 0, 0, 'Mantener puertas cerradas al salir.',
    50.00, 50.00, 8.00, 'publicado', NULL, '2026-11-18 13:00:00', '2026-11-18 13:00:00'
),
(
    14, (SELECT id_usuario FROM usuarios WHERE correo = 'bot14@haloja.test' LIMIT 1), 1, 'alojamiento_entero',
    'Casa Tradicional con Jardín en Sensuntepeque',
    'Casa amplia y luminosa en barrio tranquilo de Sensuntepeque. Ofrece corredor amplio, jardín con plantas ornamentales, agua caliente y wifi de alta velocidad.',
    'El Salvador', 'Cabañas', 'Sensuntepeque', 'Sensuntepeque',
    'Barrio Santa Bárbara, 2a Calle Oriente #15', 'A 3 cuadras del Parque Luciano Hernández',
    13.8667000, -88.6333000, 5, 5, 2, 3, 1.5, '15:00:00', '11:00:00', 1,
    1, 0, 0, 'Respetar la tranquilidad del vecindario.',
    48.00, 48.00, 8.00, 'publicado', NULL, '2026-11-17 11:30:00', '2026-11-17 11:30:00'
),
(
    15, (SELECT id_usuario FROM usuarios WHERE correo = 'bot15@haloja.test' LIMIT 1), 1, 'alojamiento_entero',
    'Casa Descanso Ruta de la Paz en San Francisco Gotera',
    'Excelente punto de partida hacia Perquín y el Río Sapo. Casa totalmente equipada con aire acondicionado, patio con parrilla, cocina completa y cochera techada.',
    'El Salvador', 'Morazán', 'San Francisco Gotera', 'San Francisco Gotera',
    'Barrio La Cruz, Avenida Thompson Norte #20', 'A 4 minutos del Parque La Concordia de Gotera',
    13.6950000, -88.1061000, 5, 5, 2, 3, 2.0, '14:00:00', '11:00:00', 1,
    1, 0, 0, 'No fumar dentro de la casa.',
    54.00, 54.00, 10.00, 'publicado', NULL, '2026-11-16 15:40:00', '2026-11-16 15:40:00'
),
(
    16, (SELECT id_usuario FROM usuarios WHERE correo = 'bot16@haloja.test' LIMIT 1), 1, 'alojamiento_entero',
    'Casa de Playa con Piscina en El Tunco, La Libertad',
    'Espectacular casa costera con piscina privada, rancho hamaquero, cocina amplia y acceso rápido a la playa. Ideal para grupos de amigos, familias o amantes del surf.',
    'El Salvador', 'La Libertad', 'Tamanique', 'La Libertad',
    'Calle Principal Playa El Tunco, Lote 14', 'A 150 metros de la piedra icónica de El Tunco',
    13.4932000, -89.3818000, 8, 8, 4, 5, 3.5, '15:00:00', '12:00:00', 2,
    1, 0, 1, 'No ingresar vidrio a la piscina. Eventos requieren aviso previo.',
    195.00, 195.00, 30.00, 'publicado', NULL, '2026-11-15 18:00:00', '2026-11-15 18:00:00'
),
(
    17, (SELECT id_usuario FROM usuarios WHERE correo = 'bot17@haloja.test' LIMIT 1), 1, 'alojamiento_entero',
    'Casa Colonial con Patio Interior en Suchitoto',
    'Encantadora casa de teja y corredores amplios en el corazón cultural de Suchitoto. A pasos de galerías, cafés, el Lago Suchitlán y la Iglesia Santa Lucía.',
    'El Salvador', 'Cuscatlán', 'Suchitoto', 'Suchitoto',
    '4a Calle Oriente #18, Barrio El Centro', 'A media cuadra de la Iglesia Santa Lucía',
    13.9378000, -89.0279000, 5, 5, 2, 3, 2.0, '15:00:00', '11:00:00', 1,
    1, 0, 0, 'Cuidar las puertas y pisos coloniales originales.',
    78.00, 78.00, 14.00, 'publicado', NULL, '2026-11-14 09:30:00', '2026-11-14 09:30:00'
),
(
    18, (SELECT id_usuario FROM usuarios WHERE correo = 'bot18@haloja.test' LIMIT 1), 1, 'alojamiento_entero',
    'Casa Cabaña entre Cafetales en Concepción de Ataco',
    'Refugio acogedor en la Ruta de las Flores con chimenea funcional, deck de madera, agua caliente y vistas verdes. Ideal para fines de semana frescos en familia o pareja.',
    'El Salvador', 'Ahuachapán', 'Concepción de Ataco', 'Ataco',
    'Cantón El Arco, desvío Mirador de la Cruz', 'A 4 minutos en vehículo del parque central de Ataco',
    13.8703000, -89.8486000, 4, 4, 2, 3, 1.5, '15:00:00', '11:00:00', 1,
    1, 0, 0, 'Apagar la chimenea antes de dormir o salir de la propiedad.',
    90.00, 90.00, 15.00, 'publicado', NULL, '2026-11-13 17:10:00', '2026-11-13 17:10:00'
),
(
    19, (SELECT id_usuario FROM usuarios WHERE correo = 'bot19@haloja.test' LIMIT 1), 1, 'alojamiento_entero',
    'Casa Urbana Práctica en Soyapango',
    'Casa funcional en residencial privada con portón eléctrico, wifi de fibra óptica, cocina equipada y parqueo techado. Ubicación estratégica cerca de Plaza Mundo y el Bulevar del Ejército.',
    'El Salvador', 'San Salvador', 'Soyapango', 'Soyapango',
    'Urbanización Los Alpes, Pasaje B #10', 'A 5 minutos de Plaza Mundo Soyapango',
    13.7102000, -89.1399000, 4, 4, 2, 2, 1.5, '14:00:00', '11:00:00', 1,
    0, 0, 0, 'Prohibido fumar en interiores y estacionar frente a vecinos.',
    45.00, 45.00, 8.00, 'publicado', NULL, '2026-11-12 12:00:00', '2026-11-12 12:00:00'
),
(
    20, (SELECT id_usuario FROM usuarios WHERE correo = 'bot20@haloja.test' LIMIT 1), 1, 'alojamiento_entero',
    'Casa Quinta con Muelle en Lago de Coatepeque',
    'Residencia vacacional de dos niveles frente a las aguas turquesas del lago. Dispone de muelle privado, amplia terraza panorámica, jacuzzi exterior y espacio para reuniones familiares.',
    'El Salvador', 'Santa Ana', 'El Congo', 'El Congo',
    'Calle perimetral del Lago de Coatepeque, Km 54', 'Contiguo a zona de restaurantes panorámicos del lago',
    13.8635000, -89.5472000, 10, 10, 4, 6, 4.0, '14:00:00', '12:00:00', 2,
    1, 0, 1, 'Uso obligatorio de chaleco salvavidas en el muelle para menores.',
    220.00, 220.00, 35.00, 'publicado', NULL, '2026-11-11 10:00:00', '2026-11-11 10:00:00'
);

-- 3.5 Imagenes de las 20 casas (alojamiento_imagenes)
INSERT INTO alojamiento_imagenes (alojamiento_id, ruta_o_url, es_portada, orden, creado_en) VALUES
(1,  'https://images.unsplash.com/photo-1600596542815-ffad4c1539a9?auto=format&fit=crop&w=900&q=80', 1, 1, '2026-11-26 10:00:00'),
(1,  'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?auto=format&fit=crop&w=900&q=80', 0, 2, '2026-11-26 10:00:00'),
(2,  'https://images.unsplash.com/photo-1568605114967-8130f3a36994?auto=format&fit=crop&w=900&q=80', 1, 1, '2026-11-25 18:00:00'),
(2,  'https://images.unsplash.com/photo-1560448204-e02f11c3d0e2?auto=format&fit=crop&w=900&q=80', 0, 2, '2026-11-25 18:00:00'),
(3,  'https://images.unsplash.com/photo-1600607687939-ce8a6c25118c?auto=format&fit=crop&w=900&q=80', 1, 1, '2026-11-25 12:30:00'),
(3,  'https://images.unsplash.com/photo-1560185007-cde436f6a4d0?auto=format&fit=crop&w=900&q=80', 0, 2, '2026-11-25 12:30:00'),
(4,  'https://images.unsplash.com/photo-1580587771525-78b9dba3b914?auto=format&fit=crop&w=900&q=80', 1, 1, '2026-11-24 16:00:00'),
(4,  'https://images.unsplash.com/photo-1522708323590-d24dbb6b0267?auto=format&fit=crop&w=900&q=80', 0, 2, '2026-11-24 16:00:00'),
(5,  'https://images.unsplash.com/photo-1613490493576-7fde63acd811?auto=format&fit=crop&w=900&q=80', 1, 1, '2026-11-24 09:40:00'),
(5,  'https://images.unsplash.com/photo-1560185127-6ed189bf02f4?auto=format&fit=crop&w=900&q=80', 0, 2, '2026-11-24 09:40:00'),
(6,  'https://images.unsplash.com/photo-1564013799919-ab600027ffc6?auto=format&fit=crop&w=900&q=80', 1, 1, '2026-11-23 19:20:00'),
(6,  'https://images.unsplash.com/photo-1505693416388-ac5ce068fe85?auto=format&fit=crop&w=900&q=80', 0, 2, '2026-11-23 19:20:00'),
(7,  'https://images.unsplash.com/photo-1518780664697-55e3ad937233?auto=format&fit=crop&w=900&q=80', 1, 1, '2026-11-23 11:15:00'),
(7,  'https://images.unsplash.com/photo-1540518614846-7eded433c457?auto=format&fit=crop&w=900&q=80', 0, 2, '2026-11-23 11:15:00'),
(8,  'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?auto=format&fit=crop&w=900&q=80', 1, 1, '2026-11-22 15:00:00'),
(8,  'https://images.unsplash.com/photo-1560184897-ae75f418493e?auto=format&fit=crop&w=900&q=80', 0, 2, '2026-11-22 15:00:00'),
(9,  'https://images.unsplash.com/photo-1502672260266-1c1ef2d93688?auto=format&fit=crop&w=900&q=80', 1, 1, '2026-11-21 17:45:00'),
(9,  'https://images.unsplash.com/photo-1560448204-e02f11c3d0e2?auto=format&fit=crop&w=900&q=80', 0, 2, '2026-11-21 17:45:00'),
(10, 'https://images.unsplash.com/photo-1568605114967-8130f3a36994?auto=format&fit=crop&w=900&q=80', 1, 1, '2026-11-21 10:10:00'),
(10, 'https://images.unsplash.com/photo-1522708323590-d24dbb6b0267?auto=format&fit=crop&w=900&q=80', 0, 2, '2026-11-21 10:10:00'),
(11, 'https://images.unsplash.com/photo-1499793983690-e29da59ef1c2?auto=format&fit=crop&w=900&q=80', 1, 1, '2026-11-20 14:30:00'),
(11, 'https://images.unsplash.com/photo-1512917774080-9991f1c4c750?auto=format&fit=crop&w=900&q=80', 0, 2, '2026-11-20 14:30:00'),
(12, 'https://images.unsplash.com/photo-1542718610-a1d656d1884c?auto=format&fit=crop&w=900&q=80', 1, 1, '2026-11-19 16:20:00'),
(12, 'https://images.unsplash.com/photo-1518780664697-55e3ad937233?auto=format&fit=crop&w=900&q=80', 0, 2, '2026-11-19 16:20:00'),
(13, 'https://images.unsplash.com/photo-1580587771525-78b9dba3b914?auto=format&fit=crop&w=900&q=80', 1, 1, '2026-11-18 13:00:00'),
(13, 'https://images.unsplash.com/photo-1505693416388-ac5ce068fe85?auto=format&fit=crop&w=900&q=80', 0, 2, '2026-11-18 13:00:00'),
(14, 'https://images.unsplash.com/photo-1600596542815-ffad4c1539a9?auto=format&fit=crop&w=900&q=80', 1, 1, '2026-11-17 11:30:00'),
(14, 'https://images.unsplash.com/photo-1540518614846-7eded433c457?auto=format&fit=crop&w=900&q=80', 0, 2, '2026-11-17 11:30:00'),
(15, 'https://images.unsplash.com/photo-1564013799919-ab600027ffc6?auto=format&fit=crop&w=900&q=80', 1, 1, '2026-11-16 15:40:00'),
(15, 'https://images.unsplash.com/photo-1560185007-cde436f6a4d0?auto=format&fit=crop&w=900&q=80', 0, 2, '2026-11-16 15:40:00'),
(16, 'https://images.unsplash.com/photo-1512917774080-9991f1c4c750?auto=format&fit=crop&w=900&q=80', 1, 1, '2026-11-15 18:00:00'),
(16, 'https://images.unsplash.com/photo-1499793983690-e29da59ef1c2?auto=format&fit=crop&w=900&q=80', 0, 2, '2026-11-15 18:00:00'),
(17, 'https://images.unsplash.com/photo-1600607687939-ce8a6c25118c?auto=format&fit=crop&w=900&q=80', 1, 1, '2026-11-14 09:30:00'),
(17, 'https://images.unsplash.com/photo-1580587771525-78b9dba3b914?auto=format&fit=crop&w=900&q=80', 0, 2, '2026-11-14 09:30:00'),
(18, 'https://images.unsplash.com/photo-1542718610-a1d656d1884c?auto=format&fit=crop&w=900&q=80', 1, 1, '2026-11-13 17:10:00'),
(18, 'https://images.unsplash.com/photo-1560184897-ae75f418493e?auto=format&fit=crop&w=900&q=80', 0, 2, '2026-11-13 17:10:00'),
(19, 'https://images.unsplash.com/photo-1560185127-6ed189bf02f4?auto=format&fit=crop&w=900&q=80', 1, 1, '2026-11-12 12:00:00'),
(19, 'https://images.unsplash.com/photo-1560184897-ae75f418493e?auto=format&fit=crop&w=900&q=80', 0, 2, '2026-11-12 12:00:00'),
(20, 'https://images.unsplash.com/photo-1613490493576-7fde63acd811?auto=format&fit=crop&w=900&q=80', 1, 1, '2026-11-11 10:00:00'),
(20, 'https://images.unsplash.com/photo-1564013799919-ab600027ffc6?auto=format&fit=crop&w=900&q=80', 0, 2, '2026-11-11 10:00:00');

-- Sincronizar fotos_alojamiento con alojamiento_imagenes
INSERT INTO fotos_alojamiento (id_alojamiento, ruta_archivo, posicion, creado_en)
SELECT alojamiento_id, ruta_o_url, orden, creado_en
FROM alojamiento_imagenes;

-- 3.6 Amenidades por alojamiento
INSERT INTO alojamiento_amenidades (alojamiento_id, amenidad_id) VALUES
(1,  1), (1,  2), (1,  4), (1,  5), (1,  6), (1,  7), (1,  9), (1, 10), (1, 14), (1, 25), (1, 26),
(2,  1), (2,  2), (2,  4), (2,  5), (2,  7), (2,  9), (2, 10), (2, 14), (2, 19), (2, 24),
(3,  1), (3,  2), (3,  4), (3,  5), (3,  6), (3,  9), (3, 11), (3, 14), (3, 23), (3, 24),
(4,  1), (4,  2), (4,  4), (4,  5), (4,  9), (4, 12), (4, 13), (4, 14), (4, 24), (4, 26),
(5,  1), (5,  2), (5,  5), (5,  7), (5,  9), (5, 10), (5, 14), (5, 15), (5, 24), (5, 25),
(6,  1), (6,  2), (6,  4), (6,  5), (6,  9), (6, 10), (6, 14), (6, 21), (6, 24),
(7,  1), (7,  4), (7,  5), (7,  9), (7, 12), (7, 14), (7, 21), (7, 24), (7, 26),
(8,  1), (8,  2), (8,  4), (8,  5), (8,  7), (8,  9), (8, 10), (8, 14), (8, 24), (8, 26),
(9,  1), (9,  2), (9,  5), (9,  6), (9,  9), (9, 10), (9, 11), (9, 14), (9, 23),
(10, 1), (10, 2), (10, 5), (10, 9), (10, 10), (10, 13), (10, 14), (10, 24), (10, 26),
(11, 1), (11, 2), (11, 5), (11, 9), (11, 10), (11, 14), (11, 15), (11, 24), (11, 25),
(12, 1), (12, 3), (12, 4), (12, 9), (12, 12), (12, 14), (12, 19), (12, 24), (12, 25),
(13, 1), (13, 4), (13, 5), (13, 7), (13, 9), (13, 10), (13, 14), (13, 24),
(14, 1), (14, 4), (14, 5), (14, 9), (14, 12), (14, 14), (14, 21), (14, 26),
(15, 1), (15, 2), (15, 4), (15, 5), (15, 9), (15, 10), (15, 14), (15, 25),
(16, 1), (16, 2), (16, 5), (16, 9), (16, 10), (16, 14), (16, 15), (16, 18), (16, 24), (16, 25),
(17, 1), (17, 4), (17, 5), (17, 9), (17, 12), (17, 13), (17, 14), (17, 24), (17, 26),
(18, 1), (18, 3), (18, 4), (18, 9), (18, 12), (18, 14), (18, 19), (18, 22), (18, 24), (18, 25),
(19, 1), (19, 2), (19, 5), (19, 6), (19, 7), (19, 9), (19, 10), (19, 14), (19, 23),
(20, 1), (20, 2), (20, 4), (20, 5), (20, 9), (20, 10), (20, 14), (20, 15), (20, 16), (20, 25), (20, 26);

-- 3.7 Servicios por alojamiento
INSERT INTO alojamiento_servicio (id_alojamiento, id_servicio) VALUES
(1,  1), (1,  2), (1,  3), (1,  4), (1,  6), (1,  7), (1,  8),
(2,  1), (2,  2), (2,  3), (2,  4), (2,  6), (2,  7), (2,  8),
(3,  1), (3,  2), (3,  3), (3,  4), (3,  6), (3,  7),
(4,  1), (4,  2), (4,  3), (4,  4), (4,  6), (4,  7),
(5,  1), (5,  2), (5,  3), (5,  4), (5,  5), (5,  6), (5,  8),
(6,  1), (6,  2), (6,  3), (6,  4), (6,  6), (6,  7),
(7,  1), (7,  2), (7,  4), (7,  6), (7,  7),
(8,  1), (8,  2), (8,  3), (8,  4), (8,  6), (8,  7), (8,  8),
(9,  1), (9,  2), (9,  3), (9,  4), (9,  6),
(10, 1), (10, 2), (10, 3), (10, 4), (10, 6),
(11, 1), (11, 2), (11, 3), (11, 4), (11, 5), (11, 6),
(12, 1), (12, 2), (12, 4), (12, 7),
(13, 1), (13, 2), (13, 4), (13, 6), (13, 7), (13, 8),
(14, 1), (14, 2), (14, 4), (14, 6), (14, 7),
(15, 1), (15, 2), (15, 3), (15, 4), (15, 6), (15, 7),
(16, 1), (16, 2), (16, 3), (16, 4), (16, 5), (16, 6),
(17, 1), (17, 2), (17, 4), (17, 6), (17, 7),
(18, 1), (18, 2), (18, 4), (18, 7),
(19, 1), (19, 2), (19, 3), (19, 4), (19, 6), (19, 8),
(20, 1), (20, 2), (20, 3), (20, 4), (20, 5), (20, 6), (20, 7);

-- 3.8 Metodos de pago por alojamiento (1 = Efectivo, 2 = Transferencia bancaria)
INSERT INTO alojamiento_metodo_pago (id_alojamiento, id_metodo_pago) VALUES
(1, 1), (1, 2),
(2, 1), (2, 2),
(3, 2),
(4, 1), (4, 2),
(5, 1), (5, 2),
(6, 1), (6, 2),
(7, 1), (7, 2),
(8, 1), (8, 2),
(9, 1), (9, 2),
(10, 1), (10, 2),
(11, 1), (11, 2),
(12, 1), (12, 2),
(13, 1), (13, 2),
(14, 1), (14, 2),
(15, 1), (15, 2),
(16, 1), (16, 2),
(17, 1), (17, 2),
(18, 1), (18, 2),
(19, 1), (19, 2),
(20, 2);

alter table usuarios
  add column if not exists telefono varchar(20) null default null,
  add column if not exists biografia text null default null,
  add column if not exists foto_perfil varchar(255) null default null;

COMMIT;
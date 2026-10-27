-- Plataforma de alojamientos PPI - modelo v1
-- Destino: MariaDB 10.4+; InnoDB; utf8mb4.
-- Ejecutar UNA SOLA VEZ dentro de una base NUEVA seleccionada en phpMyAdmin.
-- No incluye CREATE DATABASE ni USE: permite nombres con prefijo del hosting.
-- No elimina tablas existentes. No repetir para actualizar una instalacion.
-- Requiere mantener activadas las comprobaciones CHECK y FOREIGN KEY.
SET NAMES utf8mb4;

CREATE TABLE usuarios (
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

CREATE TABLE tipos_alojamiento (
    id_tipo INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(60) NOT NULL,
    UNIQUE KEY uq_tipo_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE alojamientos (
    id_alojamiento INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_anfitrion INT UNSIGNED NOT NULL,
    id_tipo INT UNSIGNED NOT NULL,
    titulo VARCHAR(150) NOT NULL,
    descripcion TEXT NOT NULL,
    pais VARCHAR(80) NOT NULL DEFAULT 'El Salvador',
    departamento VARCHAR(100) NOT NULL,
    municipio VARCHAR(100) NOT NULL,
    direccion VARCHAR(255) NOT NULL,
    latitud DECIMAL(10,7) NOT NULL,
    longitud DECIMAL(10,7) NOT NULL,
    capacidad SMALLINT UNSIGNED NOT NULL,
    precio_persona_noche DECIMAL(10,2) NOT NULL,
    estado ENUM('borrador','publicado','inactivo','bloqueado') NOT NULL DEFAULT 'borrador',
    motivo_bloqueo VARCHAR(500) NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_alojamiento_anfitrion FOREIGN KEY (id_anfitrion) REFERENCES usuarios(id_usuario) ON DELETE RESTRICT,
    CONSTRAINT fk_alojamiento_tipo FOREIGN KEY (id_tipo) REFERENCES tipos_alojamiento(id_tipo) ON DELETE RESTRICT,
    CONSTRAINT ck_alojamiento_latitud CHECK (latitud BETWEEN -90 AND 90),
    CONSTRAINT ck_alojamiento_longitud CHECK (longitud BETWEEN -180 AND 180),
    CONSTRAINT ck_alojamiento_capacidad CHECK (capacidad > 0),
    CONSTRAINT ck_alojamiento_precio CHECK (precio_persona_noche > 0),
    KEY idx_alojamiento_catalogo (estado, id_tipo, precio_persona_noche),
    KEY idx_alojamiento_ubicacion (pais, departamento, municipio)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE fotos_alojamiento (
    id_foto INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_alojamiento INT UNSIGNED NOT NULL,
    ruta_archivo VARCHAR(255) NOT NULL,
    posicion TINYINT UNSIGNED NOT NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_foto_alojamiento FOREIGN KEY (id_alojamiento) REFERENCES alojamientos(id_alojamiento) ON DELETE RESTRICT,
    CONSTRAINT ck_foto_posicion CHECK (posicion BETWEEN 1 AND 8),
    UNIQUE KEY uq_foto_posicion (id_alojamiento, posicion)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE servicios (
    id_servicio INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(80) NOT NULL,
    UNIQUE KEY uq_servicio_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE alojamiento_servicio (
    id_alojamiento INT UNSIGNED NOT NULL,
    id_servicio INT UNSIGNED NOT NULL,
    PRIMARY KEY (id_alojamiento, id_servicio),
    CONSTRAINT fk_as_alojamiento FOREIGN KEY (id_alojamiento) REFERENCES alojamientos(id_alojamiento) ON DELETE RESTRICT,
    CONSTRAINT fk_as_servicio FOREIGN KEY (id_servicio) REFERENCES servicios(id_servicio) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE metodos_pago (
    id_metodo_pago INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(80) NOT NULL,
    UNIQUE KEY uq_metodo_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE alojamiento_metodo_pago (
    id_alojamiento INT UNSIGNED NOT NULL,
    id_metodo_pago INT UNSIGNED NOT NULL,
    PRIMARY KEY (id_alojamiento, id_metodo_pago),
    CONSTRAINT fk_amp_alojamiento FOREIGN KEY (id_alojamiento) REFERENCES alojamientos(id_alojamiento) ON DELETE RESTRICT,
    CONSTRAINT fk_amp_metodo FOREIGN KEY (id_metodo_pago) REFERENCES metodos_pago(id_metodo_pago) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE reservas (
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

-- IMPORTANTE: las tablas no impiden por si solas reservas superpuestas.
-- Implementar autorizacion, reglas y transacciones descritas en GUIA_EQUIPO.md.

-- Ejecutar UNA SOLA VEZ despues de 001_estructura.sql.
-- Catalogos iniciales propuestos; no son usuarios ni pagos reales.
SET NAMES utf8mb4;
START TRANSACTION;
INSERT INTO tipos_alojamiento (nombre) VALUES ('Casa'), ('Apartamento'), ('Habitacion');
INSERT INTO servicios (nombre) VALUES
('Wifi'), ('Estacionamiento'), ('Aire acondicionado'),
('Cocina'), ('Piscina'), ('Television'), ('Agua caliente'), ('Lavadora');
INSERT INTO metodos_pago (nombre) VALUES ('Efectivo'), ('Transferencia bancaria');
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
COMMIT;

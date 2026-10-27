-- Ejecutar UNA SOLA VEZ despues de 001_estructura.sql.
-- Catalogos iniciales propuestos; no son usuarios ni pagos reales.
SET NAMES utf8mb4;
START TRANSACTION;
INSERT INTO tipos_alojamiento (nombre) VALUES ('Casa'), ('Apartamento'), ('Habitacion');
INSERT INTO servicios (nombre) VALUES
('Wifi'), ('Estacionamiento'), ('Aire acondicionado'),
('Cocina'), ('Piscina'), ('Television'), ('Agua caliente'), ('Lavadora');
INSERT INTO metodos_pago (nombre) VALUES ('Efectivo'), ('Transferencia bancaria');
COMMIT;

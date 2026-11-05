-- CARLOS
SET NAMES utf8mb4;
START TRANSACTION;

-- Cliente de prueba (no puede ser el mismo anfitrión)
INSERT INTO usuarios (nombre, apellido, correo, password_hash, telefono, rol, estado)
VALUES ('Ana', 'López', 'cliente@test.com', '$2y$10$abcdefghijklmnopqrstuv', '7777-1234', 'usuario', 'activo')
ON DUPLICATE KEY UPDATE id_usuario = LAST_INSERT_ID(id_usuario);

SET @host_id    = (SELECT id_usuario FROM usuarios WHERE correo = 'anfitrion@test.com' LIMIT 1);
SET @cliente_id = (SELECT id_usuario FROM usuarios WHERE correo = 'cliente@test.com' LIMIT 1);

-- Alojamiento 1 (Casa San Benito, precio 45)
INSERT INTO reservas
(id_alojamiento, id_solicitante, fecha_entrada, fecha_salida, cantidad_huespedes,
 precio_persona_noche_aplicado, total, estado)
SELECT id_alojamiento, @cliente_id, '2026-12-10', '2026-12-13', 2, 45.00, 270.00, 'pendiente'
FROM alojamientos WHERE titulo = 'Moderna Casa en San Benito con Jardín' LIMIT 1;

-- Alojamiento 2 (Apartamento Zona Rosa, precio 35)
INSERT INTO reservas
(id_alojamiento, id_solicitante, fecha_entrada, fecha_salida, cantidad_huespedes,
 precio_persona_noche_aplicado, total, estado)
SELECT id_alojamiento, @cliente_id, '2026-12-20', '2026-12-22', 1, 35.00, 70.00, 'pendiente'
FROM alojamientos WHERE titulo = 'Apartamento Ejecutivo cerca de la Zona Rosa' LIMIT 1;

-- Alojamiento 3 (Casa Escalón, precio 25) — ya aceptada
INSERT INTO reservas
(id_alojamiento, id_solicitante, fecha_entrada, fecha_salida, cantidad_huespedes,
 precio_persona_noche_aplicado, total, estado, respondido_en)
SELECT id_alojamiento, @cliente_id, '2027-01-05', '2027-01-08', 3, 25.00, 225.00, 'aceptada', NOW()
FROM alojamientos WHERE titulo = 'Acogedora Residencial Cerca de Escalón' LIMIT 1;

COMMIT;
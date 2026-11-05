SET NAMES utf8mb4;
START TRANSACTION;

-- 1. Insertar un anfitrión de prueba (si aún no tienes usuarios)
INSERT INTO usuarios (nombre, apellido, correo, password_hash, rol, estado)
VALUES ('Carlos', 'Mendoza', 'anfitrion@test.com', '$2y$10$abcdefghijklmnopqrstuv', 'usuario', 'activo')
ON DUPLICATE KEY UPDATE id_usuario=LAST_INSERT_ID(id_usuario);

SET @host_id = LAST_INSERT_ID();
-- Obtener el ID del tipo 'Casa' y 'Apartamento' desde la tabla tipos_alojamiento
SET @tipo_casa = (SELECT id_tipo FROM tipos_alojamiento WHERE nombre = 'Casa' LIMIT 1);
SET @tipo_apto = (SELECT id_tipo FROM tipos_alojamiento WHERE nombre = 'Apartamento' LIMIT 1);

-- 2. Insertar 3 casas en San Salvador (estado = 'publicado')
INSERT INTO alojamientos
(id_anfitrion, id_tipo, titulo, descripcion, pais, departamento, municipio, direccion, latitud, longitud, capacidad, precio_persona_noche, estado)
VALUES
(
  @host_id,
  @tipo_casa,
  'Moderna Casa en San Benito con Jardín',
  'Hermosa casa ubicada en el corazón de San Benito, ideal para familias o grupos de trabajo. Cuenta con parqueo privado, excelente iluminación y terraza amplia.',
  'El Salvador', 'San Salvador', 'San Salvador', 'Colonia San Benito, Calle La Mascota #123',
  13.6924000, -89.2389000, 4, 45.00, 'publicado'
),
(
  @host_id,
  @tipo_apto,
  'Apartamento Ejecutivo cerca de la Zona Rosa',
  'Apartamento totalmente amueblado y equipado en zona segura y residencial. Cercano a restaurantes, centros comerciales y transporte.',
  'El Salvador', 'San Salvador', 'San Salvador', 'Zona Rosa, Avenida Las Magnolias #45',
  13.6978000, -89.2431000, 2, 35.00, 'publicado'
),
(
  @host_id,
  @tipo_casa,
  'Acogedora Residencial Cerca de Escalón',
  'Espaciosa casa residencial con seguridad 24/7, cocina completa, acceso a Internet de alta velocidad y ambiente muy tranquilo.',
  'El Salvador', 'San Salvador', 'San Salvador', 'Paseo General Escalón #890',
  13.7056000, -89.2485000, 6, 25.00, 'publicado'
);

-- 3. Insertar las fotos principales (posicion = 1) para cada alojamiento usando placeholder de Unsplash
INSERT INTO fotos_alojamiento (id_alojamiento, ruta_archivo, posicion)
SELECT id_alojamiento, 'https://images.unsplash.com/photo-1580587771525-78b9dba3b914?auto=format&fit=crop&w=800&q=80', 1
FROM alojamientos WHERE titulo = 'Moderna Casa en San Benito con Jardín';

INSERT INTO fotos_alojamiento (id_alojamiento, ruta_archivo, posicion)
SELECT id_alojamiento, 'https://images.unsplash.com/photo-1502672260266-1c1ef2d93688?auto=format&fit=crop&w=800&q=80', 1
FROM alojamientos WHERE titulo = 'Apartamento Ejecutivo cerca de la Zona Rosa';

INSERT INTO fotos_alojamiento (id_alojamiento, ruta_archivo, posicion)
SELECT id_alojamiento, 'https://images.unsplash.com/photo-1568605117036-5fe5e7bab0b7?auto=format&fit=crop&w=800&q=80', 1
FROM alojamientos WHERE titulo = 'Acogedora Residencial Cerca de Escalón';

COMMIT;

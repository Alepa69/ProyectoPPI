START TRANSACTION;

-- --------------------------------------------------------
-- 1. USUARIOS ADICIONALES (IDs 2 al 5)
-- Nota: Usan el mismo password_hash que tu usuario Daniel (id 1)
-- para que puedas iniciar sesión con cualquiera usando tu misma contraseña.
-- --------------------------------------------------------
INSERT INTO `usuarios` (
  `id_usuario`, `nombre`, `apellido`, `correo`, `password_hash`,
  `telefono`, `rol`, `estado`, `creado_en`, `actualizado_en`
) VALUES
(2, 'Sofía', 'Méndez', 'sofia.mendez@haloja.test', '$2y$10$RfMVTAH/LERhwkUEve7L0eUmZJGpV.vvfBa8jHz.6fCPe0856wfDe', '7845-1201', 'usuario', 'activo', '2026-11-10 09:15:00', '2026-11-10 09:15:00'),
(3, 'Carlos', 'Rivas', 'carlos.rivas@haloja.test', '$2y$10$RfMVTAH/LERhwkUEve7L0eUmZJGpV.vvfBa8jHz.6fCPe0856wfDe', '7120-4489', 'usuario', 'activo', '2026-11-11 14:30:00', '2026-11-11 14:30:00'),
(4, 'Andrea', 'Castillo', 'andrea.castillo@haloja.test', '$2y$10$RfMVTAH/LERhwkUEve7L0eUmZJGpV.vvfBa8jHz.6fCPe0856wfDe', '7690-3310', 'usuario', 'activo', '2026-11-12 11:05:00', '2026-11-12 11:05:00'),
(5, 'Mateo', 'Hernández', 'mateo.hernandez@haloja.test', '$2y$10$RfMVTAH/LERhwkUEve7L0eUmZJGpV.vvfBa8jHz.6fCPe0856wfDe', '7901-8824', 'usuario', 'activo', '2026-11-14 16:40:00', '2026-11-14 16:40:00');

-- --------------------------------------------------------
-- 2. ALOJAMIENTOS (IDs 2 al 10)
-- Variedad de zonas, precios ($28 - $260), tipos y fechas de creación
-- --------------------------------------------------------
INSERT INTO `alojamientos` (
  `id_alojamiento`, `id_anfitrion`, `id_tipo`, `tipo_espacio`, `titulo`, `descripcion`,
  `pais`, `departamento`, `municipio`, `ciudad`, `direccion`, `referencias`,
  `latitud`, `longitud`, `capacidad`, `capacidad_personas`, `num_habitaciones`,
  `num_camas`, `num_banos`, `hora_checkin`, `hora_checkout`, `estancia_minima`,
  `permite_mascotas`, `permite_fumar`, `permite_eventos`, `reglas_adicionales`,
  `precio_persona_noche`, `precio_noche`, `tarifa_limpieza`, `estado`,
  `motivo_bloqueo`, `creado_en`, `actualizado_en`
) VALUES
(
  2, 2, 2, 'alojamiento_entero',
  'Loft Moderno en Zona Rosa',
  'Apartamento tipo loft completamente equipado en el corazón de la Zona Rosa. A pasos de restaurantes, cafés y centros corporativos. Cuenta con balcón panorámico, escritorio ergonómico para teletrabajo y seguridad privada 24/7.',
  'El Salvador', 'San Salvador', 'San Salvador', 'San Salvador',
  'Boulevard del Hipódromo #412, Col. San Benito', 'Frente a plaza gastronómica y a 2 cuadras del Museo MARTE',
  13.6929000, -89.2368000, 3, 3, 1, 2, 1.5, '15:00:00', '11:00:00', 1,
  0, 0, 0, 'No se permiten fiestas. Mantener silencio en pasillos después de las 10:00 p. m.',
  85.00, 85.00, 15.00, 'publicado', NULL, '2026-11-22 05:30:00', '2026-11-22 05:30:00'
),
(
  3, 3, 1, 'alojamiento_entero',
  'Casa Minimalista con Jardín en Escalón',
  'Residencia amplia de diseño contemporáneo ubicada en zona privada de Colonia Escalón. Perfecta para familias o grupos ejecutivos que buscan tranquilidad, jardín privado con área de BBQ y parqueo techado para 2 vehículos.',
  'El Salvador', 'San Salvador', 'San Salvador', 'San Salvador',
  '89 Avenida Norte y 9a Calle Poniente, Col. Escalón', 'A 3 minutos de Plaza Futura y Redondel Masferrer',
  13.7042000, -89.2425000, 6, 6, 3, 4, 2.5, '14:00:00', '11:00:00', 2,
  1, 0, 0, 'Se acepta una mascota mediana. Apagar aires acondicionados al salir de la propiedad.',
  135.00, 135.00, 25.00, 'publicado', NULL, '2026-11-21 18:10:00', '2026-11-21 18:10:00'
),
(
  4, 2, 2, 'alojamiento_entero',
  'Apartamento Ejecutivo en Antiguo Cuscatlán',
  'Espacio luminoso y funcional en torre residencial nueva con piscina y gimnasio. Excelente conectividad hacia Santa Tecla y San Salvador. Ideal para estancias de trabajo o visitas familiares cortas.',
  'El Salvador', 'La Libertad', 'Antiguo Cuscatlán', 'Antiguo Cuscatlán',
  'Calle La Mascota y Av. Jerusalén, Torre Mirador', 'A 5 minutos de Multiplaza y La Gran Vía',
  13.6731000, -89.2469000, 4, 4, 2, 2, 2.0, '15:00:00', '12:00:00', 1,
  0, 0, 0, 'Registro obligatorio en caseta de vigilancia con documento de identidad.',
  68.00, 68.00, 12.00, 'publicado', NULL, '2026-11-20 15:45:00', '2026-11-20 15:45:00'
),
(
  5, 4, 3, 'habitacion_privada',
  'Habitación Privada Cerca de El Cafetalón',
  'Habitación cómoda con entrada independiente, baño privado, escritorio y wifi de fibra óptica en residencial segura de Santa Tecla. A pocos minutos del Paseo El Carmen y transporte público.',
  'El Salvador', 'La Libertad', 'Santa Tecla', 'Santa Tecla',
  '7a Avenida Norte, Residencial San Antonio', 'A dos cuadras del Parque Deportivo El Cafetalón',
  13.6768000, -89.2896000, 2, 2, 1, 1, 1.0, '14:00:00', '11:00:00', 1,
  0, 0, 0, 'No se permiten visitas nocturnas sin aviso previo. Uso compartido de cocina y lavadora.',
  28.00, 28.00, 5.00, 'publicado', NULL, '2026-11-19 10:20:00', '2026-11-19 10:20:00'
),
(
  6, 1, 1, 'alojamiento_entero',
  'Villa Frente al Mar en Playa El Tunco',
  'Espectacular casa de playa con piscina privada, rancho hamaquero y acceso directo a la arena. Disfruta de los mejores atardeceres y olas para surfear con todas las comodidades modernas.',
  'El Salvador', 'La Libertad', 'Tamanique', 'Playa El Tunco',
  'Calle Principal El Tunco, Lote 14', 'A 150 metros de la piedra icónica de El Tunco',
  13.4932000, -89.3818000, 8, 8, 4, 5, 3.5, '15:00:00', '12:00:00', 2,
  1, 0, 1, 'No ingresar vidrio al área de la piscina. Eventos pequeños requieren coordinación previa.',
  220.00, 220.00, 35.00, 'publicado', NULL, '2026-11-18 20:00:00', '2026-11-18 20:00:00'
),
(
  7, 3, 1, 'alojamiento_entero',
  'Quinta con Muelle en Lago de Coatepeque',
  'Casa vacacional de dos niveles con vista ininterrumpida al lago, muelle privado, kayaks disponibles y amplia terraza para convivios familiares. Clima fresco y entorno natural privilegiado.',
  'El Salvador', 'Santa Ana', 'El Congo', 'Lago de Coatepeque',
  'Calle perimetral del Lago de Coatepeque, Km 54', 'Contiguo a zona de restaurantes panorámicos',
  13.8635000, -89.5472000, 10, 10, 4, 6, 4.0, '14:00:00', '12:00:00', 2,
  1, 0, 1, 'Uso obligatorio de chaleco salvavidas en el muelle para menores de edad.',
  260.00, 260.00, 40.00, 'publicado', NULL, '2026-11-17 12:15:00', '2026-11-17 12:15:00'
),
(
  8, 4, 1, 'alojamiento_entero',
  'Casa Colonial con Patio en Suchitoto',
  'Encantadora vivienda restaurada con techos de teja, corredor amplio y jardín interior lleno de plantas tropicales. Ubicada a pasos del centro histórico, galerías de arte y el Teatro Alejandro Cotto.',
  'El Salvador', 'Cuscatlán', 'Suchitoto', 'Suchitoto',
  '4a Calle Oriente #18, Barrio El Centro', 'A media cuadra de la Iglesia Santa Lucía',
  13.9378000, -89.0279000, 5, 5, 2, 3, 2.0, '15:00:00', '11:00:00', 1,
  1, 0, 0, 'Cuidar el mobiliario antiguo de madera y respetar el horario de descanso del barrio.',
  75.00, 75.00, 15.00, 'publicado', NULL, '2026-11-16 09:00:00', '2026-11-16 09:00:00'
),
(
  9, 5, 1, 'alojamiento_entero',
  'Cabaña de Montaña en Ruta de las Flores',
  'Acogedora cabaña rodeada de cafetales en Concepción de Ataco. Incluye chimenea funcional, deck de madera para fogatas nocturnas, cafetera con café local de altura y agua caliente.',
  'El Salvador', 'Ahuachapán', 'Concepción de Ataco', 'Ataco',
  'Cantón El Arco, desvío Mirador de la Cruz', 'A 4 minutos en vehículo del parque central de Ataco',
  13.8703000, -89.8486000, 4, 4, 2, 3, 1.5, '15:00:00', '11:00:00', 1,
  1, 0, 0, 'Apagar completamente la chimenea antes de dormir o salir de la cabaña.',
  95.00, 95.00, 18.00, 'publicado', NULL, '2026-11-15 17:30:00', '2026-11-15 17:30:00'
),
(
  10, 5, 2, 'alojamiento_entero',
  'Estudio con Vista al Lago de Ilopango',
  'Estudio moderno ideal para escapadas de fin de semana cerca de San Salvador. Cuenta con terraza privada mirando hacia el lago, cocina equipada, aire acondicionado y estacionamiento seguro.',
  'El Salvador', 'San Salvador', 'Ilopango', 'Ilopango',
  'Carretera a Apulo, Residencial Brisas del Lago', 'A 5 minutos del malecón turístico de Apulo',
  13.6922000, -89.0928000, 2, 2, 1, 1, 1.0, '14:00:00', '11:00:00', 1,
  0, 0, 0, 'Prohibido fumar dentro del estudio; permitido únicamente en la terraza abierta.',
  52.00, 52.00, 10.00, 'publicado', NULL, '2026-11-14 13:50:00', '2026-11-14 13:50:00'
);

-- --------------------------------------------------------
-- 3. IMÁGENES (Tabla alojamiento_imagenes)
-- --------------------------------------------------------
INSERT INTO `alojamiento_imagenes` (`alojamiento_id`, `ruta_o_url`, `es_portada`, `orden`, `creado_en`) VALUES
(2, 'https://images.unsplash.com/photo-1502672260266-1c1ef2d93688?auto=format&fit=crop&w=900&q=80', 1, 1, '2026-11-22 05:30:00'),
(2, 'https://images.unsplash.com/photo-1560448204-e02f11c3d0e2?auto=format&fit=crop&w=900&q=80', 0, 2, '2026-11-22 05:30:00'),
(3, 'https://images.unsplash.com/photo-1600596542815-ffad4c1539a9?auto=format&fit=crop&w=900&q=80', 1, 1, '2026-11-21 18:10:00'),
(3, 'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?auto=format&fit=crop&w=900&q=80', 0, 2, '2026-11-21 18:10:00'),
(4, 'https://images.unsplash.com/photo-1522708323590-d24dbb6b0267?auto=format&fit=crop&w=900&q=80', 1, 1, '2026-11-20 15:45:00'),
(4, 'https://images.unsplash.com/photo-1560185007-cde436f6a4d0?auto=format&fit=crop&w=900&q=80', 0, 2, '2026-11-20 15:45:00'),
(5, 'https://images.unsplash.com/photo-1505693416388-ac5ce068fe85?auto=format&fit=crop&w=900&q=80', 1, 1, '2026-11-19 10:20:00'),
(5, 'https://images.unsplash.com/photo-1540518614846-7eded433c457?auto=format&fit=crop&w=900&q=80', 0, 2, '2026-11-19 10:20:00'),
(6, 'https://images.unsplash.com/photo-1512917774080-9991f1c4c750?auto=format&fit=crop&w=900&q=80', 1, 1, '2026-11-18 20:00:00'),
(6, 'https://images.unsplash.com/photo-1499793983690-e29da59ef1c2?auto=format&fit=crop&w=900&q=80', 0, 2, '2026-11-18 20:00:00'),
(7, 'https://images.unsplash.com/photo-1564013799919-ab600027ffc6?auto=format&fit=crop&w=900&q=80', 1, 1, '2026-11-17 12:15:00'),
(7, 'https://images.unsplash.com/photo-1613490493576-7fde63acd811?auto=format&fit=crop&w=900&q=80', 0, 2, '2026-11-17 12:15:00'),
(8, 'https://images.unsplash.com/photo-1580587771525-78b9dba3b914?auto=format&fit=crop&w=900&q=80', 1, 1, '2026-11-16 09:00:00'),
(8, 'https://images.unsplash.com/photo-1600607687939-ce8a6c25118c?auto=format&fit=crop&w=900&q=80', 0, 2, '2026-11-16 09:00:00'),
(9, 'https://images.unsplash.com/photo-1542718610-a1d656d1884c?auto=format&fit=crop&w=900&q=80', 1, 1, '2026-11-15 17:30:00'),
(9, 'https://images.unsplash.com/photo-1518780664697-55e3ad937233?auto=format&fit=crop&w=900&q=80', 0, 2, '2026-11-15 17:30:00'),
(10, 'https://images.unsplash.com/photo-1560185127-6ed189bf02f4?auto=format&fit=crop&w=900&q=80', 1, 1, '2026-11-14 13:50:00'),
(10, 'https://images.unsplash.com/photo-1560184897-ae75f418493e?auto=format&fit=crop&w=900&q=80', 0, 2, '2026-11-14 13:50:00');

-- --------------------------------------------------------
-- 4. IMÁGENES (Tabla fotos_alojamiento - sincronizada)
-- --------------------------------------------------------
INSERT INTO `fotos_alojamiento` (`id_alojamiento`, `ruta_archivo`, `posicion`, `creado_en`) VALUES
(2, 'https://images.unsplash.com/photo-1502672260266-1c1ef2d93688?auto=format&fit=crop&w=900&q=80', 1, '2026-11-22 05:30:00'),
(2, 'https://images.unsplash.com/photo-1560448204-e02f11c3d0e2?auto=format&fit=crop&w=900&q=80', 2, '2026-11-22 05:30:00'),
(3, 'https://images.unsplash.com/photo-1600596542815-ffad4c1539a9?auto=format&fit=crop&w=900&q=80', 1, '2026-11-21 18:10:00'),
(3, 'https://images.unsplash.com/photo-1600585154340-be6161a56a0c?auto=format&fit=crop&w=900&q=80', 2, '2026-11-21 18:10:00'),
(4, 'https://images.unsplash.com/photo-1522708323590-d24dbb6b0267?auto=format&fit=crop&w=900&q=80', 1, '2026-11-20 15:45:00'),
(4, 'https://images.unsplash.com/photo-1560185007-cde436f6a4d0?auto=format&fit=crop&w=900&q=80', 2, '2026-11-20 15:45:00'),
(5, 'https://images.unsplash.com/photo-1505693416388-ac5ce068fe85?auto=format&fit=crop&w=900&q=80', 1, '2026-11-19 10:20:00'),
(5, 'https://images.unsplash.com/photo-1540518614846-7eded433c457?auto=format&fit=crop&w=900&q=80', 2, '2026-11-19 10:20:00'),
(6, 'https://images.unsplash.com/photo-1512917774080-9991f1c4c750?auto=format&fit=crop&w=900&q=80', 1, '2026-11-18 20:00:00'),
(6, 'https://images.unsplash.com/photo-1499793983690-e29da59ef1c2?auto=format&fit=crop&w=900&q=80', 2, '2026-11-18 20:00:00'),
(7, 'https://images.unsplash.com/photo-1564013799919-ab600027ffc6?auto=format&fit=crop&w=900&q=80', 1, '2026-11-17 12:15:00'),
(7, 'https://images.unsplash.com/photo-1613490493576-7fde63acd811?auto=format&fit=crop&w=900&q=80', 2, '2026-11-17 12:15:00'),
(8, 'https://images.unsplash.com/photo-1580587771525-78b9dba3b914?auto=format&fit=crop&w=900&q=80', 1, '2026-11-16 09:00:00'),
(8, 'https://images.unsplash.com/photo-1600607687939-ce8a6c25118c?auto=format&fit=crop&w=900&q=80', 2, '2026-11-16 09:00:00'),
(9, 'https://images.unsplash.com/photo-1542718610-a1d656d1884c?auto=format&fit=crop&w=900&q=80', 1, '2026-11-15 17:30:00'),
(9, 'https://images.unsplash.com/photo-1518780664697-55e3ad937233?auto=format&fit=crop&w=900&q=80', 2, '2026-11-15 17:30:00'),
(10, 'https://images.unsplash.com/photo-1560185127-6ed189bf02f4?auto=format&fit=crop&w=900&q=80', 1, '2026-11-14 13:50:00'),
(10, 'https://images.unsplash.com/photo-1560184897-ae75f418493e?auto=format&fit=crop&w=900&q=80', 2, '2026-11-14 13:50:00');

-- --------------------------------------------------------
-- 5. AMENIDADES POR ALOJAMIENTO
-- --------------------------------------------------------
INSERT INTO `alojamiento_amenidades` (`alojamiento_id`, `amenidad_id`) VALUES
(2, 1), (2, 2), (2, 4), (2, 5), (2, 6), (2, 9), (2, 12), (2, 14), (2, 23), (2, 24),
(3, 1), (3, 2), (3, 4), (3, 5), (3, 7), (3, 9), (3, 10), (3, 14), (3, 19), (3, 25), (3, 26),
(4, 1), (4, 2), (4, 4), (4, 5), (4, 6), (4, 9), (4, 11), (4, 14), (4, 15), (4, 17), (4, 23),
(5, 1), (5, 2), (5, 4), (5, 6), (5, 7), (5, 10), (5, 19), (5, 21),
(6, 1), (6, 2), (6, 5), (6, 9), (6, 10), (6, 14), (6, 15), (6, 18), (6, 24), (6, 25),
(7, 1), (7, 2), (7, 4), (7, 5), (7, 9), (7, 10), (7, 14), (7, 15), (7, 16), (7, 25), (7, 26),
(8, 1), (8, 4), (8, 5), (8, 9), (8, 12), (8, 13), (8, 14), (8, 21), (8, 24), (8, 26),
(9, 1), (9, 3), (9, 4), (9, 9), (9, 12), (9, 14), (9, 19), (9, 22), (9, 24), (9, 25),
(10, 1), (10, 2), (10, 4), (10, 5), (10, 9), (10, 10), (10, 14), (10, 24);

-- --------------------------------------------------------
-- 6. SERVICIOS POR ALOJAMIENTO
-- --------------------------------------------------------
INSERT INTO `alojamiento_servicio` (`id_alojamiento`, `id_servicio`) VALUES
(1, 1), (1, 2), (1, 3), (1, 4), (1, 8),
(2, 1), (2, 2), (2, 3), (2, 4), (2, 6), (2, 7),
(3, 1), (3, 2), (3, 3), (3, 4), (3, 6), (3, 7), (3, 8),
(4, 1), (4, 2), (4, 3), (4, 4), (4, 5), (4, 6), (4, 7),
(5, 1), (5, 3), (5, 7), (5, 8),
(6, 1), (6, 2), (6, 3), (6, 4), (6, 5), (6, 6),
(7, 1), (7, 2), (7, 3), (7, 4), (7, 5), (7, 6), (7, 7),
(8, 1), (8, 2), (8, 4), (8, 6), (8, 7),
(9, 1), (9, 2), (9, 4), (9, 7),
(10, 1), (10, 2), (10, 3), (10, 4), (10, 6);

-- --------------------------------------------------------
-- 7. MÉTODOS DE PAGO POR ALOJAMIENTO
-- --------------------------------------------------------
INSERT INTO `alojamiento_metodo_pago` (`id_alojamiento`, `id_metodo_pago`) VALUES
(1, 1), (1, 2),
(2, 2),
(3, 1), (3, 2),
(4, 2),
(5, 1), (5, 2),
(6, 1), (6, 2),
(7, 2),
(8, 1), (8, 2),
(9, 1), (9, 2),
(10, 1), (10, 2);

-- --------------------------------------------------------
-- 8. RESERVAS DE PRUEBA (Para probar vistas de Reservas)
-- --------------------------------------------------------
INSERT INTO `reservas` (
  `id_alojamiento`, `id_solicitante`, `fecha_entrada`, `fecha_salida`,
  `cantidad_huespedes`, `precio_persona_noche_aplicado`, `total`, `estado`,
  `creado_en`, `respondido_en`, `id_cancelado_por`, `cancelado_en`, `motivo_cancelacion`
) VALUES
-- 2 noches * 4 huéspedes * $220.00 = $1760.00
(6, 2, '2026-12-05', '2026-12-07', 4, 220.00, 1760.00, 'pendiente', '2026-11-22 06:10:00', NULL, NULL, NULL, NULL),
-- 2 noches * 2 huéspedes * $85.00 = $340.00
(2, 1, '2026-12-12', '2026-12-14', 2, 85.00, 340.00, 'aceptada', '2026-11-21 19:20:00', '2026-11-21 20:05:00', NULL, NULL, NULL),
-- 2 noches * 2 huéspedes * $95.00 = $380.00
(9, 1, '2026-12-20', '2026-12-22', 2, 95.00, 380.00, 'pendiente', '2026-11-22 06:40:00', NULL, NULL, NULL, NULL),
-- 2 noches * 3 huéspedes * $135.00 = $810.00
(3, 5, '2026-11-28', '2026-11-30', 3, 135.00, 810.00, 'cancelada', '2026-11-18 11:00:00', NULL, 5, '2026-11-19 08:30:00', 'Cambio de fechas del viaje familiar');

COMMIT;
-- Avisos individuales. Los avisos mostrados siguen siendo novedades hasta abrir la reserva.
CREATE TABLE IF NOT EXISTS avisos_reserva (
    id_aviso INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_reserva INT UNSIGNED NOT NULL,
    id_usuario INT UNSIGNED NOT NULL,
    tipo ENUM('solicitada','confirmada','rechazada','cancelada_anfitrion','cancelada_huesped','retirada') NOT NULL,
    mostrado_en DATETIME NULL,
    leido_en DATETIME NULL,
    UNIQUE KEY uq_aviso_reserva_tipo (id_reserva, tipo),
    KEY idx_aviso_usuario_lectura (id_usuario, leido_en, id_aviso),
    CONSTRAINT fk_aviso_reserva FOREIGN KEY (id_reserva) REFERENCES reservas(id_reserva) ON DELETE CASCADE,
    CONSTRAINT fk_aviso_usuario FOREIGN KEY (id_usuario) REFERENCES usuarios(id_usuario) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Recupera solicitudes pendientes y decisiones recientes que ya existían al actualizar.
INSERT IGNORE INTO avisos_reserva (id_reserva, id_usuario, tipo)
SELECT r.id_reserva, a.id_anfitrion, 'solicitada'
FROM reservas r JOIN alojamientos a ON a.id_alojamiento = r.id_alojamiento
WHERE r.estado = 'pendiente' AND r.fecha_entrada >= UTC_DATE() - INTERVAL 1 DAY;

INSERT IGNORE INTO avisos_reserva (id_reserva, id_usuario, tipo)
SELECT r.id_reserva, r.id_solicitante, IF(r.estado = 'aceptada', 'confirmada', 'rechazada')
FROM reservas r
WHERE (r.estado = 'aceptada' AND r.fecha_salida >= UTC_DATE() - INTERVAL 1 DAY)
    OR (r.estado IN ('aceptada','rechazada') AND r.respondido_en >= UTC_TIMESTAMP() - INTERVAL 7 DAY);

INSERT IGNORE INTO avisos_reserva (id_reserva, id_usuario, tipo)
SELECT r.id_reserva, IF(r.id_cancelado_por = a.id_anfitrion, r.id_solicitante, a.id_anfitrion),
       CASE WHEN r.id_cancelado_por = a.id_anfitrion THEN 'cancelada_anfitrion'
            WHEN r.respondido_en IS NULL THEN 'retirada' ELSE 'cancelada_huesped' END
FROM reservas r JOIN alojamientos a ON a.id_alojamiento = r.id_alojamiento
WHERE r.estado = 'cancelada' AND r.cancelado_en >= UTC_TIMESTAMP() - INTERVAL 7 DAY;

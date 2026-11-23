CREATE TABLE mensajes (
    id_mensaje INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    id_reserva INT UNSIGNED NOT NULL,
    id_emisor INT UNSIGNED NOT NULL,
    mensaje TEXT NOT NULL,
    creado_en TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_reserva) REFERENCES reservas(id_reserva) ON DELETE CASCADE,
    FOREIGN KEY (id_emisor) REFERENCES usuarios(id_usuario) ON DELETE CASCADE
) ENGINE=InnoDB;
<?php

class ChatDAO
{
    private mysqli $conexion;

    public function __construct(mysqli $conexion)
    {
        $this->conexion = $conexion;
    }

    public function obtenerReservaParaChat(int $idReserva): ?array
    {
        $sql = "SELECT r.id_reserva, r.id_solicitante, r.estado,
                       a.titulo AS alojamiento, a.id_anfitrion
                FROM reservas r
                JOIN alojamientos a ON a.id_alojamiento = r.id_alojamiento
                WHERE r.id_reserva = ?
                LIMIT 1";
        $stmt = $this->conexion->prepare($sql);
        $stmt->bind_param('i', $idReserva);
        $stmt->execute();
        $reserva = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        return $reserva ?: null;
    }

    public function enviarMensaje(int $idReserva, int $idEmisor, string $mensaje): bool
    {
        $sql = "INSERT INTO mensajes (id_reserva, id_emisor, mensaje) VALUES (?, ?, ?)";
        $stmt = $this->conexion->prepare($sql);
        $stmt->bind_param('iis', $idReserva, $idEmisor, $mensaje);
        $ok = $stmt->execute();
        $stmt->close();

        return $ok;
    }

    public function listarMensajes(int $idReserva): array
    {
        $sql = "SELECT m.id_emisor, m.mensaje, m.creado_en, u.nombre, u.apellido
                FROM mensajes m
                JOIN usuarios u ON u.id_usuario = m.id_emisor
                WHERE m.id_reserva = ?
                ORDER BY m.creado_en ASC";
        $stmt = $this->conexion->prepare($sql);
        $stmt->bind_param('i', $idReserva);
        $stmt->execute();
        $mensajes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $mensajes;
    }
}

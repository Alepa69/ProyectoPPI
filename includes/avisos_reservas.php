<?php

function registrarAvisoReserva(mysqli $conexion, int $reserva, int $usuario, string $tipo): void
{
    $stmt = $conexion->prepare('INSERT INTO avisos_reserva (id_reserva, id_usuario, tipo) VALUES (?, ?, ?)');
    $stmt->bind_param('iis', $reserva, $usuario, $tipo);
    $stmt->execute();
}

function avisosDeReservas(mysqli $conexion, int $usuario, DateTimeImmutable $ahora): array
{
    $momento = $ahora->format('Y-m-d H:i:s');
    $stmt = $conexion->prepare("
        SELECT n.id_aviso, n.tipo, n.id_reserva, n.mostrado_en, a.titulo
        FROM avisos_reserva n
        JOIN usuarios u ON u.id_usuario = n.id_usuario AND u.estado = 'activo'
        JOIN reservas r ON r.id_reserva = n.id_reserva
        JOIN alojamientos a ON a.id_alojamiento = r.id_alojamiento
        WHERE n.id_usuario = ? AND n.leido_en IS NULL
            AND (n.tipo <> 'solicitada' OR (r.estado = 'pendiente' AND TIMESTAMP(r.fecha_entrada, a.hora_checkin) > ?))
            AND (n.tipo <> 'confirmada' OR r.estado = 'aceptada')
        ORDER BY n.id_aviso DESC
    ");
    $stmt->bind_param('is', $usuario, $momento);
    $stmt->execute();

    $avisos = [];
    foreach ($stmt->get_result() as $aviso) {
        $rol = match ($aviso['tipo']) {
            'solicitada', 'retirada', 'cancelada_huesped' => 'anfitrion',
            default => 'huesped',
        };
        $titulo = match ($aviso['tipo']) {
            'solicitada' => 'Nueva solicitud de reserva',
            'confirmada' => 'El anfitrión aceptó tu reserva',
            'rechazada' => 'El anfitrión rechazó tu solicitud',
            'cancelada_anfitrion' => 'El anfitrión canceló tu reserva',
            'cancelada_huesped' => 'El huésped canceló la reserva',
            'retirada' => 'El huésped retiró su solicitud',
        };
        $idReserva = (int) $aviso['id_reserva'];
        $url = $aviso['tipo'] === 'solicitada'
            ? 'panel_anfitrion.php?estado=pendiente#reserva-' . $idReserva
            : 'reserva.php?id=' . $idReserva;
        $avisos[] = [
            'id' => (int) $aviso['id_aviso'],
            'reserva' => $idReserva,
            'rol' => $rol,
            'titulo' => $titulo,
            'alojamiento' => $aviso['titulo'],
            'url' => $url,
            'mostrar' => $aviso['mostrado_en'] === null,
        ];
    }
    return $avisos;
}

function marcarAvisoReserva(mysqli $conexion, int $usuario, int $aviso, bool $leido): void
{
    $lectura = $leido ? ', leido_en = COALESCE(leido_en, UTC_TIMESTAMP())' : '';
    $stmt = $conexion->prepare("
        UPDATE avisos_reserva SET mostrado_en = COALESCE(mostrado_en, UTC_TIMESTAMP()) $lectura
        WHERE id_aviso = ? AND id_usuario = ?
    ");
    $stmt->bind_param('ii', $aviso, $usuario);
    $stmt->execute();
}

function leerAvisosDeReserva(mysqli $conexion, int $usuario, int $reserva): void
{
    $stmt = $conexion->prepare("
        UPDATE avisos_reserva SET leido_en = UTC_TIMESTAMP(), mostrado_en = COALESCE(mostrado_en, UTC_TIMESTAMP())
        WHERE id_usuario = ? AND id_reserva = ? AND leido_en IS NULL
    ");
    $stmt->bind_param('ii', $usuario, $reserva);
    $stmt->execute();
}

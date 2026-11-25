<?php
require_once __DIR__ . '/formularios.php';
require_once __DIR__ . '/avisos_reservas.php';

function relojReservas(): DateTimeImmutable
{
    return new DateTimeImmutable('now', new DateTimeZone('America/El_Salvador'));
}

function fechaReserva($valor): ?DateTimeImmutable
{
    if (!is_string($valor)) {
        return null;
    }

    $fecha = DateTimeImmutable::createFromFormat('!Y-m-d', $valor, relojReservas()->getTimezone());
    return $fecha && $fecha->format('Y-m-d') === $valor ? $fecha : null;
}

function momentoEntrada(array $reserva): DateTimeImmutable
{
    return new DateTimeImmutable(
        $reserva['fecha_entrada'] . ' ' . ($reserva['hora_checkin'] ?? '15:00:00'),
        relojReservas()->getTimezone()
    );
}

function momentoSalida(array $reserva): DateTimeImmutable
{
    return new DateTimeImmutable(
        $reserva['fecha_salida'] . ' ' . ($reserva['hora_checkout'] ?? '11:00:00'),
        relojReservas()->getTimezone()
    );
}

function puedeRetirarOCancelar(array $reserva, bool $esAnfitrion, DateTimeImmutable $ahora): bool
{
    $entrada = momentoEntrada($reserva);
    if ($reserva['estado'] === 'pendiente') {
        return !$esAnfitrion && $ahora < $entrada;
    }
    if ($reserva['estado'] !== 'aceptada') {
        return false;
    }
    return $esAnfitrion ? $ahora < $entrada : $ahora <= $entrada->modify('-24 hours');
}

function bloquearAlojamiento(mysqli $conexion, int $id): array
{
    // Las operaciones de disponibilidad toman este bloqueo antes de tocar una reserva.
    $stmt = $conexion->prepare("
        SELECT a.*, u.estado AS estado_anfitrion
        FROM alojamientos a JOIN usuarios u ON u.id_usuario = a.id_anfitrion
        WHERE a.id_alojamiento = ? FOR UPDATE
    ");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $alojamiento = $stmt->get_result()->fetch_assoc();
    if (!$alojamiento) {
        throw new DomainException('El alojamiento ya no está disponible.');
    }
    return $alojamiento;
}

function hayReservaConfirmada(mysqli $conexion, int $id, string $entrada, string $salida): bool
{
    $stmt = $conexion->prepare("
        SELECT 1 FROM reservas
        WHERE id_alojamiento = ? AND estado = 'aceptada'
          AND fecha_entrada < ? AND fecha_salida > ?
        LIMIT 1 FOR UPDATE
    ");
    $stmt->bind_param('iss', $id, $salida, $entrada);
    $stmt->execute();
    return (bool) $stmt->get_result()->fetch_row();
}

function hayBloqueoDeFechas(mysqli $conexion, int $id, string $entrada, string $salida): bool
{
    $stmt = $conexion->prepare("
        SELECT 1 FROM bloqueos_alojamiento
        WHERE id_alojamiento = ? AND fecha_inicio < ? AND fecha_fin > ?
        LIMIT 1 FOR UPDATE
    ");
    $stmt->bind_param('iss', $id, $salida, $entrada);
    $stmt->execute();
    return (bool) $stmt->get_result()->fetch_row();
}

function gestionarReserva(mysqli $conexion, int $id, int $usuario, string $accion, bool $esAnfitrion, string $motivo): string
{
    $stmt = $conexion->prepare('SELECT id_alojamiento FROM reservas WHERE id_reserva = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $idAlojamiento = (int) ($stmt->get_result()->fetch_row()[0] ?? 0);
    if (!$idAlojamiento) {
        throw new DomainException('La reserva no está disponible.');
    }

    $conexion->begin_transaction();
    try {
        $casa = bloquearAlojamiento($conexion, $idAlojamiento);
        $stmt = $conexion->prepare('SELECT * FROM reservas WHERE id_reserva = ? FOR UPDATE');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $reserva = $stmt->get_result()->fetch_assoc();
        $titular = $esAnfitrion ? $casa['id_anfitrion'] : ($reserva['id_solicitante'] ?? 0);
        if (!$reserva || (int) $titular !== $usuario) {
            throw new DomainException('La reserva no está disponible para esta cuenta.');
        }

        $reserva['hora_checkin'] = $casa['hora_checkin'];
        $ahora = relojReservas();
        if ($accion === 'cancelar') {
            if (!puedeRetirarOCancelar($reserva, $esAnfitrion, $ahora)) {
                throw new DomainException('La reserva ya no se puede retirar o cancelar en este momento.');
            }
            $eraPendiente = $reserva['estado'] === 'pendiente';
            $motivo = $motivo ?: ($eraPendiente ? 'Retirada por el huésped' : ($esAnfitrion ? 'Cancelada por el anfitrión' : 'Cancelada por el huésped'));
            $stmt = $conexion->prepare("
                UPDATE reservas SET estado = 'cancelada', id_cancelado_por = ?,
                    cancelado_en = UTC_TIMESTAMP(), motivo_cancelacion = ? WHERE id_reserva = ?
            ");
            $stmt->bind_param('isi', $usuario, $motivo, $id);
            $mensaje = $eraPendiente ? 'Solicitud retirada.' : 'Reserva cancelada.';
            $idDestino = $esAnfitrion ? (int) $reserva['id_solicitante'] : (int) $casa['id_anfitrion'];
            $tipoAviso = $esAnfitrion ? 'cancelada_anfitrion' : ($eraPendiente ? 'retirada' : 'cancelada_huesped');
        } else {
            if (!$esAnfitrion || $reserva['estado'] !== 'pendiente' || $ahora >= momentoEntrada($reserva)) {
                throw new DomainException('Esta solicitud ya no se puede responder.');
            }
            if ($accion === 'aceptar') {
                if ($casa['estado'] !== 'publicado' || $casa['estado_anfitrion'] !== 'activo') {
                    throw new DomainException('Publica el alojamiento antes de aceptar solicitudes.');
                }
                if ((int) $reserva['cantidad_huespedes'] > (int) $casa['capacidad']) {
                    throw new DomainException('La solicitud supera la capacidad actual del alojamiento.');
                }
                if (hayReservaConfirmada($conexion, $idAlojamiento, $reserva['fecha_entrada'], $reserva['fecha_salida'])
                    || hayBloqueoDeFechas($conexion, $idAlojamiento, $reserva['fecha_entrada'], $reserva['fecha_salida'])) {
                    throw new DomainException('Las fechas ya no están disponibles.');
                }
            }
            $estado = $accion === 'aceptar' ? 'aceptada' : 'rechazada';
            $stmt = $conexion->prepare('UPDATE reservas SET estado = ?, respondido_en = UTC_TIMESTAMP() WHERE id_reserva = ?');
            $stmt->bind_param('si', $estado, $id);
            $mensaje = $estado === 'aceptada' ? 'Reserva confirmada.' : 'Solicitud rechazada.';
            $idDestino = (int) $reserva['id_solicitante'];
            $tipoAviso = $estado === 'aceptada' ? 'confirmada' : 'rechazada';
        }

        $stmt->execute();
        // El aviso se guarda solo si la transición de la reserva se confirma.
        registrarAvisoReserva($conexion, $id, $idDestino, $tipoAviso);
        $conexion->commit();
        return $mensaje;
    } catch (Throwable $error) {
        $conexion->rollback();
        throw $error;
    }
}

function estadoVisibleReserva(array $reserva, DateTimeImmutable $ahora): array
{
    return match ($reserva['estado']) {
        'pendiente' => $ahora >= momentoEntrada($reserva)
            ? ['Vencida', 'secondary'] : ['Pendiente', 'warning text-dark'],
        'aceptada' => $ahora >= momentoSalida($reserva)
            ? ['Pasada', 'secondary'] : ['Confirmada', 'success'],
        'rechazada' => ['Rechazada', 'secondary'],
        'cancelada' => ['Cancelada', 'danger'],
    };
}

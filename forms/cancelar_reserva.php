<?php
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../config/sesion.php';

// Solo permitimos entrar aquí si se envió el formulario por POST

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../panel_cliente.php');
    exit;
}

verificar_csrf();

date_default_timezone_set('America/El_Salvador');

$idCliente = (int) $_SESSION['id_usuario'];
$idReserva = (int) ($_POST['id_reserva'] ?? 0);

if ($idReserva <= 0) {
    $_SESSION['flash'] = ['tipo' => 'danger', 'texto' => 'Reserva no válida.'];
    header('Location: ../panel_cliente.php');
    exit;
}

// Actualizamos el estado solo si la reserva es de este cliente y está pendiente o aceptada
$conexion->begin_transaction();
try {
    // Bloquear la reserva y consultar la hora de check-in del alojamiento
    $stmt = $conexion->prepare("
        SELECT r.id_reserva, r.id_solicitante, r.fecha_entrada, r.estado, a.hora_checkin
        FROM reservas r
        JOIN alojamientos a ON a.id_alojamiento = r.id_alojamiento
        WHERE r.id_reserva = ?
        FOR UPDATE
    ");
    $stmt->bind_param('i', $idReserva);
    $stmt->execute();
    $reserva = $stmt->get_result()->fetch_assoc();

    if (!$reserva || (int) $reserva['id_solicitante'] !== $idCliente) {
        throw new RuntimeException('No tienes permiso para cancelar esta reserva.');
    }

    if ($reserva['estado'] !== 'pendiente' && $reserva['estado'] !== 'aceptada') {
        throw new RuntimeException('Esta reserva ya no puede cancelarse.');
    }

    $zona = new DateTimeZone('America/El_Salvador');
    $ahora = new DateTimeImmutable('now', $zona);
    $horaEntrada = $reserva['hora_checkin'] ?: '15:00:00';
    $entrada = new DateTimeImmutable($reserva['fecha_entrada'] . ' ' . $horaEntrada, $zona);

    if ($reserva['estado'] === 'pendiente' && $ahora >= $entrada) {
        throw new RuntimeException('La fecha de entrada ya pasó, no se puede retirar.');
    }

    if ($reserva['estado'] === 'aceptada' && $ahora > $entrada->modify('-24 hours')) {
        throw new RuntimeException('Solo puedes cancelar una reserva aceptada al menos 24 horas antes de la entrada.');
    }

    $motivo = $reserva['estado'] === 'pendiente'
        ? 'Solicitud retirada por el cliente'
        : 'Cancelada por el cliente';

    $stmtUp = $conexion->prepare("
        UPDATE reservas
        SET estado = 'cancelada',
            id_cancelado_por = ?,
            cancelado_en = NOW(),
            motivo_cancelacion = ?
        WHERE id_reserva = ?
    ");
    $stmtUp->bind_param('isi', $idCliente, $motivo, $idReserva);
    $stmtUp->execute();

    $conexion->commit();
    $_SESSION['flash'] = ['tipo' => 'success', 'texto' => 'La reserva fue cancelada correctamente.'];
} catch (RuntimeException $e) {
    $conexion->rollback();
    $_SESSION['flash'] = ['tipo' => 'warning', 'texto' => $e->getMessage()];
} catch (Throwable $e) {
    $conexion->rollback();
    $_SESSION['flash'] = ['tipo' => 'danger', 'texto' => 'No se pudo cancelar la reserva.'];
}

header('Location: ../panel_cliente.php');
exit;

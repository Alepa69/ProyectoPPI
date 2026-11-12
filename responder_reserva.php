<?php // CARLOS
require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/config/sesion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: panel_anfitrion.php');
    exit;
}

$idReserva = (int) ($_POST['id_reserva'] ?? 0);
$accion    = $_POST['accion'] ?? '';
$idUsuario = (int) $_SESSION['id_usuario'];

$accionesValidas = ['aceptar', 'rechazar', 'cancelar'];
if ($idReserva <= 0 || !in_array($accion, $accionesValidas, true)) {
    $_SESSION['flash'] = ['tipo' => 'danger', 'texto' => 'Solicitud inválida.'];
    header('Location: panel_anfitrion.php');
    exit;
}

try {
    $conexion->begin_transaction();

    // Bloquear el alojamiento primero (mismo orden que las demas operaciones)
    $stmt = $conexion->prepare(
        "SELECT a.id_alojamiento, a.id_anfitrion, a.capacidad
         FROM alojamientos a
         JOIN reservas r ON r.id_alojamiento = a.id_alojamiento
         WHERE r.id_reserva = ?
         FOR UPDATE"
    );
    $stmt->bind_param('i', $idReserva);
    $stmt->execute();
    $alojamiento = $stmt->get_result()->fetch_assoc();

    if (!$alojamiento || (int) $alojamiento['id_anfitrion'] !== $idUsuario) {
        throw new RuntimeException('No tienes permiso sobre esta reserva.');
    }

    // Bloquear la reserva
    $stmt = $conexion->prepare(
        "SELECT id_reserva, id_alojamiento, fecha_entrada, fecha_salida,
                cantidad_huespedes, estado
         FROM reservas WHERE id_reserva = ? FOR UPDATE"
    );
    $stmt->bind_param('i', $idReserva);
    $stmt->execute();
    $reserva = $stmt->get_result()->fetch_assoc();

    if (!$reserva) {
        throw new RuntimeException('La reserva no existe.');
    }

    if ($accion === 'aceptar') {
        if ($reserva['estado'] !== 'pendiente') {
            throw new RuntimeException('Solo se pueden aceptar reservas pendientes.');
        }
        if ($reserva['fecha_entrada'] < date('Y-m-d')) {
            throw new RuntimeException('La fecha de entrada ya pasó.');
        }
        if ((int) $reserva['cantidad_huespedes'] > (int) $alojamiento['capacidad']) {
            throw new RuntimeException('Los huéspedes superan la capacidad actual del alojamiento.');
        }

        // Verificar cruce con reservas aceptadas
        $stmt = $conexion->prepare(
            "SELECT id_reserva FROM reservas
             WHERE id_alojamiento = ?
               AND estado = 'aceptada'
               AND fecha_entrada < ?
               AND fecha_salida > ?
             FOR UPDATE"
        );
        $stmt->bind_param('iss', $reserva['id_alojamiento'], $reserva['fecha_salida'], $reserva['fecha_entrada']);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            throw new RuntimeException('Ya existe otra reserva aceptada en esas fechas.');
        }

        $stmt = $conexion->prepare(
            "UPDATE reservas SET estado = 'aceptada', respondido_en = NOW()
             WHERE id_reserva = ?"
        );
        $stmt->bind_param('i', $idReserva);
        $stmt->execute();
        $mensaje = 'Reserva aceptada.';

    } elseif ($accion === 'rechazar') {
        if ($reserva['estado'] !== 'pendiente') {
            throw new RuntimeException('Solo se pueden rechazar reservas pendientes.');
        }
        $stmt = $conexion->prepare(
            "UPDATE reservas SET estado = 'rechazada', respondido_en = NOW()
             WHERE id_reserva = ?"
        );
        $stmt->bind_param('i', $idReserva);
        $stmt->execute();
        $mensaje = 'Reserva rechazada.';

    } else { // cancelar
        if ($reserva['estado'] !== 'aceptada') {
            throw new RuntimeException('Solo se pueden cancelar reservas aceptadas.');
        }
        if ($reserva['fecha_entrada'] < date('Y-m-d')) {
            throw new RuntimeException('La reserva ya comenzó, no se puede cancelar.');
        }
        $stmt = $conexion->prepare(
            "UPDATE reservas
             SET estado = 'cancelada', id_cancelado_por = ?, cancelado_en = NOW(),
                 motivo_cancelacion = 'Cancelada por el anfitrión'
             WHERE id_reserva = ?"
        );
        $stmt->bind_param('ii', $idUsuario, $idReserva);
        $stmt->execute();
        $mensaje = 'Reserva cancelada.';
    }

    $conexion->commit();
    $_SESSION['flash'] = ['tipo' => 'success', 'texto' => $mensaje];

} catch (RuntimeException $e) {
    $conexion->rollback();
    $_SESSION['flash'] = ['tipo' => 'warning', 'texto' => $e->getMessage()];
} catch (Throwable $e) {
    $conexion->rollback();
    error_log('Error al responder reserva: ' . $e->getMessage());
    $_SESSION['flash'] = ['tipo' => 'danger', 'texto' => 'No se pudo procesar la solicitud.'];
}

header('Location: panel_anfitrion.php');
exit;
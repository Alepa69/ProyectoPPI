<?php
require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/config/sesion.php';

// Solo permitimos entrar aquí si se envió el formulario por POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: panel_cliente.php');
    exit;
}

$idCliente = (int) $_SESSION['id_usuario'];
$idReserva = (int) ($_POST['id_reserva'] ?? 0);

if ($idReserva <= 0) {
    $_SESSION['flash'] = [
        'tipo' => 'danger',
        'texto' => 'Reserva no válida.'
    ];
    header('Location: panel_cliente.php');
    exit;
}

// Actualizamos el estado solo si la reserva es de este cliente y está pendiente o aceptada
$sql = "UPDATE reservas
        SET estado = 'cancelada'
        WHERE id_reserva = ?
          AND id_solicitante = ?
          AND estado IN ('pendiente', 'aceptada')";

$stmt = $conexion->prepare($sql);
$stmt->bind_param('ii', $idReserva, $idCliente);
$stmt->execute();

// affected_rows === 1 significa que sí se encontró y actualizó la fila
if ($stmt->affected_rows === 1) {
    $_SESSION['flash'] = [
        'tipo' => 'success',
        'texto' => 'La reserva fue cancelada correctamente.'
    ];
} else {
    $_SESSION['flash'] = [
        'tipo' => 'warning',
        'texto' => 'No se pudo cancelar la reserva.'
    ];
}

// Regresamos al panel del cliente
header('Location: panel_cliente.php');
exit;
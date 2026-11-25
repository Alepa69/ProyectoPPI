<?php
require_once __DIR__ . '/config/sesion.php';
require_once __DIR__ . '/includes/reservas.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: panel_anfitrion.php');
    exit;
}

$id = filter_input(INPUT_POST, 'id_reserva', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$accion = $_POST['accion'] ?? '';
$motivo = is_string($_POST['motivo'] ?? null) ? trim($_POST['motivo']) : '';
$volver = ($_POST['desde'] ?? '') === 'detalle' && $id
    ? 'reserva.php?id=' . $id : 'panel_anfitrion.php';

try {
    if (!$id || !in_array($accion, ['aceptar', 'rechazar', 'cancelar'], true) || !tokenFormularioValido()) {
        throw new DomainException('Recarga la página y vuelve a intentarlo.');
    }
    if (mb_strlen($motivo) > 500) {
        throw new DomainException('El motivo puede tener hasta 500 caracteres.');
    }
    $mensaje = gestionarReserva($conexion, $id, (int) $_SESSION['id_usuario'], $accion, true, $motivo);
    $_SESSION['flash'] = ['tipo' => 'success', 'texto' => $mensaje];
} catch (DomainException $error) {
    $_SESSION['flash'] = ['tipo' => 'warning', 'texto' => $error->getMessage()];
} catch (Throwable $error) {
    error_log('Respuesta de reserva: ' . $error->getMessage());
    $_SESSION['flash'] = ['tipo' => 'danger', 'texto' => 'No se pudo responder. Inténtalo de nuevo.'];
}

header('Location: ' . $volver, true, 303);
exit;

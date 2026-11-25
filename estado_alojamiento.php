<?php
require_once __DIR__ . '/config/sesion.php';
require_once __DIR__ . '/includes/reservas.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: mis_alojamientos.php');
    exit;
}

$id = filter_input(INPUT_POST, 'id_alojamiento', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$accion = $_POST['accion'] ?? '';
$enTransaccion = false;
try {
    if (!$id || !in_array($accion, ['retirar', 'reactivar'], true) || !tokenFormularioValido()) {
        throw new DomainException('Recarga la página y vuelve a intentarlo.');
    }

    $conexion->begin_transaction();
    $enTransaccion = true;
    $casa = bloquearAlojamiento($conexion, $id);
    if ((int) $casa['id_anfitrion'] !== (int) $_SESSION['id_usuario']) {
        throw new DomainException('El alojamiento no está disponible para esta cuenta.');
    }

    $anterior = $accion === 'retirar' ? 'publicado' : 'inactivo';
    $estado = $accion === 'retirar' ? 'inactivo' : 'publicado';
    if ($casa['estado'] !== $anterior) {
        throw new DomainException('El alojamiento no se puede cambiar desde su estado actual.');
    }
    // Retirarlo del catálogo conserva las solicitudes y estancias registradas.
    $stmt = $conexion->prepare('UPDATE alojamientos SET estado = ? WHERE id_alojamiento = ?');
    $stmt->bind_param('si', $estado, $id);
    $stmt->execute();
    $conexion->commit();
    $_SESSION['alojamiento_flash'] = ['tipo' => 'success', 'texto' => $accion === 'retirar'
        ? 'El alojamiento se retiró del catálogo. Sus reservas siguen disponibles en el panel.'
        : 'El alojamiento volvió al catálogo.'];
} catch (Throwable $error) {
    if ($enTransaccion) {
        $conexion->rollback();
    }
    if (!$error instanceof DomainException) {
        error_log('Estado del alojamiento: ' . $error->getMessage());
    }
    $_SESSION['alojamiento_flash'] = ['tipo' => 'warning', 'texto' => $error instanceof DomainException
        ? $error->getMessage() : 'No se pudo cambiar el estado del alojamiento.'];
}

header('Location: mis_alojamientos.php', true, 303);
exit;

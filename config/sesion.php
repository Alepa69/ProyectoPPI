<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$idSesion = (int) ($_SESSION['id_usuario'] ?? 0);
if ($idSesion > 0) {
    require_once __DIR__ . '/conexion.php';
    $sesionUsuario = $conexion->prepare("SELECT nombre FROM usuarios WHERE id_usuario = ? AND estado = 'activo'");
    $sesionUsuario->bind_param('i', $idSesion);
    $sesionUsuario->execute();
    $cuentaSesion = $sesionUsuario->get_result()->fetch_assoc();
    $sesionUsuario->close();
    if ($cuentaSesion) {
        $_SESSION['nombre'] = $cuentaSesion['nombre'];
        return;
    }
    $_SESSION = [];
}

header('Location: login.php');
exit;

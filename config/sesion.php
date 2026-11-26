<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$idSesion = (int) ($_SESSION['id_usuario'] ?? 0);
if ($idSesion <= 0) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/conexion.php';

// Verificar que la cuenta siga existiendo y esté activa
$stmtSesion = $conexion->prepare("SELECT nombre, rol, estado FROM usuarios WHERE id_usuario = ? LIMIT 1");
$stmtSesion->bind_param('i', $idSesion);
$stmtSesion->execute();
$cuentaSesion = $stmtSesion->get_result()->fetch_assoc();
$stmtSesion->close();

if (!$cuentaSesion || $cuentaSesion['estado'] !== 'activo') {
    $_SESSION = [];
    header('Location: login.php?suspendido=1');
    exit;
}

$_SESSION['nombre'] = $cuentaSesion['nombre'];
$_SESSION['rol'] = $cuentaSesion['rol'];

// Genera el token CSRF (falsificación de solicitud entre sitios)
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Verifica el token enviado por POST
function verificar_csrf(): void
{
    $enviado = $_POST['csrf_token'] ?? '';
    $guardado = $_SESSION['csrf_token'] ?? '';
    if ($guardado === '' || !hash_equals($guardado, (string) $enviado)) {
        http_response_code(403);
        exit('Token de seguridad inválido. Recarga la página e inténtalo de nuevo.');
    }
}

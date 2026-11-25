<?php
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/sesion.php';

$idAdmin = (int) ($_SESSION['id_usuario'] ?? 0);

$stmtAdmin = $conexion->prepare("
    SELECT id_usuario, nombre, apellido, rol, estado
    FROM usuarios
    WHERE id_usuario = ?
    LIMIT 1
");
$stmtAdmin->bind_param('i', $idAdmin);
$stmtAdmin->execute();
$adminActual = $stmtAdmin->get_result()->fetch_assoc();
$stmtAdmin->close();

if (!$adminActual || $adminActual['rol'] !== 'administrador' || $adminActual['estado'] !== 'activo') {
    header('Location: index.php');
    exit;
}

if (empty($_SESSION['csrf_admin'])) {
    $_SESSION['csrf_admin'] = bin2hex(random_bytes(32));
}
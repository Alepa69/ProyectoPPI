<?php // CARLOS
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Sesion de prueba mientras no exista el login
// Hay que reemplazarlo por $_SESSION['id_usuario'] cuando se implemente el login.
if (!isset($_SESSION['id_usuario'])) {
    $_SESSION['id_usuario'] = 1;
    $_SESSION['nombre'] = 'Carlos';
}
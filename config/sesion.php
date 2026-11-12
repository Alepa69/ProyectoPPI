<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Si no ha iniciado sesión, redirigir automáticamente al login
if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit();
}
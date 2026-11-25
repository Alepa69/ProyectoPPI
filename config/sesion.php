<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Si no ha iniciado sesión, redirigir automáticamente al login
if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.php");
    exit();
}

// Genera el token CSRF (falsificacion de solicitud entre sitios) 
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }   //sino, genera 32bytes randoms y los convierte en una cadena hexadecimal
    return $_SESSION['csrf_token'];
}


function verificar_csrf(): void
{
    $enviado = $_POST['csrf_token'] ?? '';
    $guardado = $_SESSION['csrf_token'] ?? '';
    if ($guardado === '' || !hash_equals($guardado, (string)$enviado)) {
        //hash_equals compara dos cadenas de manera segura 
        http_response_code(403);
        exit('Token de seguridad inválido. Recarga la página e inténtalo de nuevo.');
    }
}
<?php
// Ejecutar este archivo únicamente desde la misma computadora.
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit('Esta prueba solo se puede ejecutar desde localhost.');
}

session_start();

if (empty($_SESSION['token_usuario_prueba'])) {
    $_SESSION['token_usuario_prueba'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token'] ?? '';

    if (!is_string($token) || !hash_equals($_SESSION['token_usuario_prueba'], $token)) {
        http_response_code(403);
        exit('Recarga la página y vuelve a intentarlo.');
    }

    require_once __DIR__ . '/config/conexion.php';

    // El sufijo evita repetir un correo que ya exista.
    $correo = 'huesped.' . bin2hex(random_bytes(4)) . '@example.test';
    $hash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);

    $insertar = $conexion->prepare("
        INSERT INTO usuarios (nombre, apellido, correo, password_hash, rol, estado)
        VALUES ('Huésped', 'Prueba', ?, ?, 'usuario', 'activo')
    ");
    $insertar->bind_param('ss', $correo, $hash);
    $insertar->execute();

    // listing.php lee esta misma clave para identificar al solicitante.
    session_regenerate_id(true);
    $_SESSION['id_usuario'] = (int) $conexion->insert_id;

    unset($_SESSION['token_usuario_prueba']);
    header('Location: index.php#alojamientos', true, 303);
    exit;
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Usuario de prueba | Davinci</title>
</head>
<body>
    <h1>Probar una reserva como huésped</h1>
    <form method="post">
        <input type="hidden" name="token"
               value="<?= htmlspecialchars($_SESSION['token_usuario_prueba'], ENT_QUOTES, 'UTF-8') ?>">
        <button type="submit">Crear usuario activo y entrar</button>
    </form>
</body>
</html>
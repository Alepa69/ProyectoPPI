<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificamos si el usuario tiene al menos una casa (para saber si puede ser anfitrión)
$esAnfitrionReal = false;
$modoActual = $_SESSION['modo'] ?? 'huesped';

if (isset($_SESSION['id_usuario'])) {
    if (!isset($conexion)) {
        require_once __DIR__ . '/../config/conexion.php';
    }
    $stmtCheck = $conexion->prepare("SELECT COUNT(*) as total FROM alojamientos WHERE id_anfitrion = ?");
    $stmtCheck->bind_param('i', $_SESSION['id_usuario']);
    $stmtCheck->execute();
    $esAnfitrionReal = $stmtCheck->get_result()->fetch_assoc()['total'] > 0;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) : 'HALOJA' ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/tema-davinci.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <style>
        body { background-color: #f8f9fa; }
        /* Forzar texto blanco en el menú */
        .navbar-nav .nav-link { color: rgba(255, 255, 255, 0.8) !important; }
        .navbar-nav .nav-link:hover, .navbar-nav .nav-link.active { color: #ffffff !important; }
    </style>
</head>
<body>

    <nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm mb-4">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">HALOJA</a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#menuNavbar">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="menuNavbar">
                <div class="navbar-nav ms-auto align-items-center">
                    
                    <?php if (isset($_SESSION['id_usuario'])): ?>
                        
                        <?php if ($modoActual === 'anfitrion'): ?>
                            <!-- ===== MODO ANFITRIÓN ===== -->
                            <a class="nav-link" href="panel_anfitrion.php">Reservas</a>
                            <a class="nav-link" href="mis_alojamientos.php">Mis Alojamientos</a>
                            <!-- Botón para volver a modo huésped -->
                            <a class="nav-link fw-bold text-white ms-lg-3" href="cambiar_modo.php?modo=huesped">
                                Cambiar a modo Huésped
                            </a>

                        <?php else: ?>
                            <!-- ===== MODO HUÉSPED ===== -->
                            <a class="nav-link" href="index.php">Inicio</a>
                            <a class="nav-link" href="modulos/reservas/mis_solicitudes.php">Mis reservas</a>
                            <a class="nav-link" href="publicar_casa.php">Publicar casa</a>

                            <?php if ($esAnfitrionReal): ?>
                                <!-- Si ya tiene casas, le damos el botón para cambiar a modo anfitrión -->
                                <a class="nav-link fw-bold text-white ms-lg-3" href="cambiar_modo.php?modo=anfitrion">
                                    Cambiar a modo Anfitrión
                                </a>
                            <?php endif; ?>
                        <?php endif; ?>

                        <!-- Botones comunes (Perfil y Salir) -->
                        <a class="nav-link text-white ms-lg-3" href="perfil.php">
                            Perfil: <?= htmlspecialchars($_SESSION['nombre']) ?>
                        </a>
                        <a class="nav-link text-white" href="cerrarsesion.php">Cerrar sesión</a>
                    
                    <?php else: ?>
                        <!-- ===== NO LOGUEADO ===== -->
                        <a class="nav-link" href="index.php">Inicio</a>
                        <a class="nav-link" href="registro.php">Registro</a>
                        <a class="nav-link text-white" href="login.php">Iniciar sesión</a>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </nav>

    <div class="container mb-5">
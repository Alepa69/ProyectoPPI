<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) : 'HALOJA' ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!--mapaaaaa-->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <style>body { background-color: #f8f9fa; }</style>
</head>
<body>

    <nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm mb-4">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">HALOJA</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link <?= (basename($_SERVER['PHP_SELF']) == 'index.php') ? 'active' : '' ?>" href="index.php">Inicio</a>
                <a class="nav-link <?= (basename($_SERVER['PHP_SELF']) == 'publicar_casa.php') ? 'active' : '' ?>" href="publicar_casa.php">Publicar casa</a>
                <a class="nav-link <?= (basename($_SERVER['PHP_SELF']) == 'panel_anfitrion.php') ? 'active' : '' ?>" href="panel_anfitrion.php">Mis reservas</a>
                
                <a class="nav-link <?= (basename($_SERVER['PHP_SELF']) == 'registro.php') ? 'active' : '' ?>" href="registro.php">Registro</a>
                <a class="nav-link <?= (basename($_SERVER['PHP_SELF']) == 'login.php') ? 'active' : '' ?>" href="login.php">Inicio sesion</a>
                <a class="nav-link <?= (basename($_SERVER['PHP_SELF']) == 'cerrarsesion.php') ? 'active' : '' ?>" href="cerrarsesion.php">Cerrar sesion</a>

                <?php if (isset($_SESSION['nombre'])): ?>
                    <span class="navbar-text text-white fw-bold ms-3">
                        Cuenta: <?= htmlspecialchars($_SESSION['nombre']) ?>
                    </span>
                <?php endif; ?>
            </div>
        </div>
    </nav>

    <div class="container mb-5">
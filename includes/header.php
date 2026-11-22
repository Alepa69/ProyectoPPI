<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body>

    <nav class="navbar navbar-expand-xl navbar-davinci efecto-cristal sticky-top shadow-sm">
        <div class="container-fluid px-4">
            
         <!-- LOGO HALOJA -->
            <div class="bloque-izquierdo">
                <a class="navbar-brand marca-davinci text-decoration-none m-0" href="index.php">
                    <img src="assets/img/logo-haloja.png" alt="" width="36" height="36">
                    <span>HALOJA</span>
                </a>
            </div>
            
            <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#menuPrincipal">
                <span class="navbar-toggler-icon"></span>
            </button>

            <!-- CONTENEDOR CENTRAL Y DERECHO -->
            <div class="collapse navbar-collapse" id="menuPrincipal">
                
                <!-- BLOQUE CENTRAL: Enlaces Funcionales -->
                <div class="bloque-central mx-auto mt-3 mt-xl-0">
                    <ul class="navbar-nav d-flex gap-xl-4 p-0">
                        <?php if (isset($_SESSION['id_usuario']) && $modoActual === 'anfitrion'): ?>
                            <!-- Vistas del anfitrion -->
                            <li class="nav-item">
                                <a class="nav-link accion-texto" href="panel_anfitrion.php">
                                    <i class="bi bi-calendar-check fs-5"></i> Reservas
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link accion-texto" href="mis_alojamientos.php">
                                    <i class="bi bi-houses fs-5"></i> Mis Alojamientos
                                </a>
                            </li>
                        <?php else: ?>
                            <!-- Vistas del huesped -->
                            <?php if (isset($_SESSION['id_usuario'])): ?>
                                <li class="nav-item">
                                    <a class="nav-link accion-texto" href="modulos/reservas/mis_solicitudes.php">
                                        <i class="bi bi-luggage fs-5"></i> Mis reservas
                                    </a>
                                </li>
                                <li class="nav-item">
                                    <a class="nav-link accion-texto" href="publicar_casa.php">
                                        <i class="bi bi-house-add fs-5"></i> Publicar casa
                                    </a>
                                </li>
                            <?php else: ?>
                                <li class="nav-item">
                                    <a class="nav-link accion-texto" href="publicar_casa.php">Pon tu espacio en Haloja</a>
                                </li>
                            <?php endif; ?>
                        <?php endif; ?>
                    </ul>
                </div>

                <!-- PARTE DERECHA: Avatar y sesion usuario -->
                <div class="bloque-derecho mt-3 mt-xl-0 pb-2 pb-xl-0">
                    <?php if (isset($_SESSION['id_usuario'])): ?>
                        
                        <!-- Dropdown (tresflechasbroder) de Usuario -->
                        <div class="dropdown">
                            <button class="btn bg-white border rounded-pill d-flex align-items-center gap-2 py-1 px-2 shadow-sm" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="border-color: #dee2e6 !important;">
                                <i class="bi bi-list fs-5 ms-1" style="color: #173e3a;"></i>
                                <div class="text-white rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 32px; height: 32px; font-size: 14px; background-color: #173e3a;">
                                    <?= strtoupper(substr($_SESSION['nombre'], 0, 1)) ?>
                                </div>
                            </button>
                            
                            <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2 rounded-4 p-2" style="min-width: 220px; background-color: #faf9f6;">
                                <li><h6 class="dropdown-header fw-bold fs-6" style="color: #173e3a;">Hola, <?= htmlspecialchars($_SESSION['nombre']) ?></h6></li>
                                <li><a class="dropdown-item rounded py-2 d-flex align-items-center gap-2" href="perfil.php" style="color: #344b4a;"><i class="bi bi-person fs-5"></i> Mi perfil</a></li>
                                
                                <li><hr class="dropdown-divider"></li>
                                
                                <!-- CAMBIO DE MODO SIEMPRE DENTRO DEL DROPDOWN -->
                                <?php if ($modoActual === 'anfitrion'): ?>
                                    <li><a class="dropdown-item rounded py-2 d-flex align-items-center gap-2 fw-medium" href="cambiar_modo.php?modo=huesped" style="color: #173e3a;"><i class="bi bi-arrow-repeat fs-5"></i> Modo Huésped</a></li>
                                <?php else: ?>
                                    <?php if ($esAnfitrionReal): ?>
                                        <li><a class="dropdown-item rounded py-2 d-flex align-items-center gap-2 fw-medium" href="cambiar_modo.php?modo=anfitrion" style="color: #173e3a;"><i class="bi bi-arrow-repeat fs-5"></i> Modo Anfitrión</a></li>
                                    <?php endif; ?>
                                <?php endif; ?>

                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item rounded py-2 text-danger d-flex align-items-center gap-2" href="cerrarsesion.php"><i class="bi bi-box-arrow-right fs-5"></i> Cerrar sesión</a></li>
                            </ul>
                        </div>

                    <?php else: ?>
                        <!-- usuarios q no se han logeado -->
                        <div class="d-flex align-items-center gap-3">
                            <a href="registro.php" class="accion-texto">Registro</a>
                            <a href="login.php" class="btn boton-ingresar shadow-sm">
                                <i class="bi bi-person-circle fs-5"></i> Ingresar
                            </a>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </nav>

    <div class="container mb-5">
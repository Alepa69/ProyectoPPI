<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$esAnfitrionReal = false;
$modoActual = $_SESSION['modo'] ?? 'huesped';
$paginaActual = basename($_SERVER['PHP_SELF']);

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
    <link rel="stylesheet" href="assets/css/tema-haloja.css?v=3">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body>

    <nav class="navbar navbar-davinci efecto-cristal sticky-top shadow-sm">
        <div class="container-fluid px-4 d-flex align-items-center justify-content-between">
            
            <!-- IZQUIERDA: Marca y Logo -->
            <div class="bloque-izquierdo">
                <a class="navbar-brand marca-davinci text-decoration-none m-0" href="index.php">
                    <img src="assets/img/logo-davinci.png" alt="Logo Haloja" width="36" height="36">
                    <span>HALOJA</span>
                </a>
            </div>

<?php
// Detectamos qué filtro de tipo está activo en la URL (si existe)
$tipoActivo = $_GET['tipo'] ?? '';
$enInicio = ($paginaActual === 'index.php');
?>

<?php
// Detectamos el id_tipo activo en la URL (0 = Todos)
$tipoActivo = isset($_GET['tipo']) ? (int)$_GET['tipo'] : 0;
$enInicio = ($paginaActual === 'index.php');
?>

<!-- CENTRO: Opciones según el modo -->
<div class="bloque-central d-none d-lg-flex justify-content-center">
    <?php if (isset($_SESSION['id_usuario']) && $modoActual === 'anfitrion'): ?>
        
        <!-- MODO ANFITRIÓN -->
        <ul class="navbar-nav flex-row gap-2">
            <li class="nav-item">
                <a class="nav-link accion-texto <?= $paginaActual === 'panel_anfitrion.php' ? 'active' : '' ?>" href="panel_anfitrion.php">
                    <i class="bi bi-calendar-check fs-5"></i> Reservas recibidas
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link accion-texto <?= $paginaActual === 'mis_alojamientos.php' ? 'active' : '' ?>" href="mis_alojamientos.php">
                    <i class="bi bi-houses fs-5"></i> Mis alojamientos
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link accion-texto <?= $paginaActual === 'publicar_casa.php' ? 'active' : '' ?>" href="publicar_casa.php">
                    <i class="bi bi-house-add fs-5"></i> Publicar casa
                </a>
            </li>
        </ul>

    <?php else: ?>

        <!-- MODO HUÉSPED / VISITANTE: Tipos de Alojamiento por id_tipo -->
        <ul class="navbar-nav flex-row gap-2">
            <li class="nav-item">
                <a class="nav-link accion-texto <?= ($enInicio && $tipoActivo === 0) ? 'active' : '' ?>" href="index.php">
                    <i class="bi bi-grid fs-5"></i> Todos
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link accion-texto <?= ($enInicio && $tipoActivo === 1) ? 'active' : '' ?>" href="index.php?tipo=1">
                    <i class="bi bi-house-door fs-5"></i> Casa
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link accion-texto <?= ($enInicio && $tipoActivo === 2) ? 'active' : '' ?>" href="index.php?tipo=2">
                    <i class="bi bi-building fs-5"></i> Apartamento
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link accion-texto <?= ($enInicio && $tipoActivo === 3) ? 'active' : '' ?>" href="index.php?tipo=3">
                    <i class="bi bi-door-open fs-5"></i> Habitación
                </a>
            </li>
        </ul>

    <?php endif; ?>
</div>

            <!-- DERECHA: Menú de usuario o Acceso -->
            <div class="bloque-derecho d-flex align-items-center justify-content-end">
                <?php if (isset($_SESSION['id_usuario'])): ?>
                    
                    <div class="dropdown">
                        <!-- Botón píldora con las 3 rayitas e inicial -->
                        <button class="btn bg-white border rounded-pill d-flex align-items-center gap-2 py-1 px-2 shadow-sm boton-perfil-pill" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-list fs-5 ms-1" style="color: var(--dv-forest);"></i>
                            <div class="text-white rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 34px; height: 34px; font-size: 14px; background-color: var(--dv-forest);">
                                <?= strtoupper(substr($_SESSION['nombre'], 0, 1)) ?>
                            </div>
                        </button>
                        
<!-- Menú desplegable -->
<ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 mt-2 rounded-4 p-2 menu-haloja" style="min-width: 245px;">
    
    <!-- Saludo e indicador de modo actual -->
    <li class="px-3 py-2 d-flex align-items-center justify-content-between">
        <span class="fw-bold" style="color: var(--dv-forest-deep);">
            Hola, <?= htmlspecialchars($_SESSION['nombre']) ?>
        </span>
        <span class="badge" style="background-color: var(--dv-forest-soft); color: var(--dv-forest);">
            <?= $modoActual === 'anfitrion' ? 'Anfitrión' : 'Huésped' ?>
        </span>
    </li>

    <li>
        <a class="dropdown-item rounded-3 py-2 d-flex align-items-center gap-2" href="perfil.php">
            <i class="bi bi-person fs-5"></i> Mi perfil
        </a>
    </li>

    <?php if ($modoActual === 'anfitrion'): ?>
        <!-- En Modo Anfitrión: en PC ya están en el header, así que solo se muestran aquí en celular (d-lg-none) -->
        <li class="d-lg-none"><hr class="dropdown-divider my-1"></li>
        <li class="d-lg-none">
            <a class="dropdown-item rounded-3 py-2 d-flex align-items-center gap-2" href="panel_anfitrion.php">
                <i class="bi bi-calendar-check fs-5"></i> Reservas recibidas
            </a>
        </li>
        <li class="d-lg-none">
            <a class="dropdown-item rounded-3 py-2 d-flex align-items-center gap-2" href="mis_alojamientos.php">
                <i class="bi bi-houses fs-5"></i> Mis alojamientos
            </a>
        </li>
        <li class="d-lg-none">
            <a class="dropdown-item rounded-3 py-2 d-flex align-items-center gap-2" href="publicar_casa.php">
                <i class="bi bi-house-add fs-5"></i> Publicar casa
            </a>
        </li>
    <?php else: ?>
        <!-- En Modo Huésped: solo opciones de huésped -->
        <li><hr class="dropdown-divider my-1"></li>
        <li>
            <a class="dropdown-item rounded-3 py-2 d-flex align-items-center gap-2" href="modulos/reservas/mis_solicitudes.php">
                <i class="bi bi-luggage fs-5"></i> Mis reservas
            </a>
        </li>
        <li>
            <a class="dropdown-item rounded-3 py-2 d-flex align-items-center gap-2" href="publicar_casa.php">
                <i class="bi bi-house-add fs-5"></i> Publicar casa
            </a>
        </li>
    <?php endif; ?>

    <!-- Cambio de modo -->
    <?php if ($esAnfitrionReal): ?>
        <li><hr class="dropdown-divider my-1"></li>
        <?php if ($modoActual === 'anfitrion'): ?>
            <li>
                <a class="dropdown-item rounded-3 py-2 d-flex align-items-center gap-2 fw-semibold" href="cambiar_modo.php?modo=huesped" style="color: var(--dv-forest);">
                    <i class="bi bi-arrow-repeat fs-5"></i> Cambiar a Huésped
                </a>
            </li>
        <?php else: ?>
            <li>
                <a class="dropdown-item rounded-3 py-2 d-flex align-items-center gap-2 fw-semibold" href="cambiar_modo.php?modo=anfitrion" style="color: var(--dv-forest);">
                    <i class="bi bi-arrow-repeat fs-5"></i> Cambiar a Anfitrión
                </a>
            </li>
        <?php endif; ?>
    <?php endif; ?>

    <li><hr class="dropdown-divider my-1"></li>

    <li>
        <a class="dropdown-item rounded-3 py-2 text-danger d-flex align-items-center gap-2" href="cerrarsesion.php">
            <i class="bi bi-box-arrow-right fs-5"></i> Cerrar sesión
        </a>
    </li>
</ul>
                    </div>

                <?php else: ?>
                    <!-- Usuarios no logueados -->
                    <div class="d-flex align-items-center gap-3">
                        <a href="registro.php" class="accion-texto">Registro</a>
                        <a href="login.php" class="btn boton-ingresar shadow-sm">
                            <i class="bi bi-person-circle fs-5"></i> Ingresar
                        </a>
                    </div>
                <?php endif; ?>
            </div>

        </div>

        <!-- Barra de categorías solo visible en celulares/tablets (Modo Huésped) -->
    <?php if (!isset($_SESSION['id_usuario']) || $modoActual !== 'anfitrion'): ?>
        <div class="d-flex d-lg-none overflow-auto border-top px-3 py-2 gap-2" style="scrollbar-width: none;">
            <a class="accion-texto py-1 px-3 flex-shrink-0 <?= ($enInicio && $tipoActivo === '') ? 'active' : '' ?>" href="index.php">
                <i class="bi bi-grid"></i> Todos
            </a>
            <a class="accion-texto py-1 px-3 flex-shrink-0 <?= ($enInicio && $tipoActivo === 'Casa') ? 'active' : '' ?>" href="index.php?tipo=Casa">
                <i class="bi bi-house-door"></i> Casa
            </a>
            <a class="accion-texto py-1 px-3 flex-shrink-0 <?= ($enInicio && $tipoActivo === 'Apartamento') ? 'active' : '' ?>" href="index.php?tipo=Apartamento">
                <i class="bi bi-building"></i> Apartamento
            </a>
            <a class="accion-texto py-1 px-3 flex-shrink-0 <?= ($enInicio && $tipoActivo === 'Habitación') ? 'active' : '' ?>" href="index.php?tipo=Habitación">
                <i class="bi bi-door-open"></i> Habitación
            </a>
        </div>
    <?php endif; ?>
</nav>

    </nav>

    <div class="container mb-5">
<?php
require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/config/sesion.php'; // Asegura que esté logueado

$idUsuario = (int) $_SESSION['id_usuario'];

// 1. Obtener datos del usuario
$stmt = $conexion->prepare("SELECT nombre, apellido, correo, rol, estado FROM usuarios WHERE id_usuario = ?");
$stmt->bind_param('i', $idUsuario);
$stmt->execute();
$usuario = $stmt->get_result()->fetch_assoc();

// 2. Obtener estadísticas de Cliente (Reservas solicitadas)
$stmt = $conexion->prepare("
    SELECT estado, COUNT(*) as total 
    FROM reservas 
    WHERE id_solicitante = ? 
    GROUP BY estado
");
$stmt->bind_param('i', $idUsuario);
$stmt->execute();
$reservasCliente = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Procesar estadísticas
$statsCliente = ['pendiente' => 0, 'aceptada' => 0, 'rechazada' => 0, 'cancelada' => 0];
foreach ($reservasCliente as $r) {
    $statsCliente[$r['estado']] = $r['total'];
}

// 3. Obtener estadísticas de Anfitrión (Si tiene alojamientos)
$stmt = $conexion->prepare("
    SELECT COUNT(DISTINCT a.id_alojamiento) as total_alojamientos,
           COUNT(r.id_reserva) as total_reservas
    FROM alojamientos a
    LEFT JOIN reservas r ON r.id_alojamiento = a.id_alojamiento
    WHERE a.id_anfitrion = ?
");
$stmt->bind_param('i', $idUsuario);
$stmt->execute();
$statsAnfitrion = $stmt->get_result()->fetch_assoc();
$esAnfitrion = (int)$statsAnfitrion['total_alojamientos'] > 0;

$pageTitle = 'Mi Perfil';
include __DIR__ . '/includes/header.php';
?>

<div class="row">
    <!-- Columna Izquierda: Información Personal -->
    <div class="col-md-4 mb-4">
        <div class="card shadow-sm border-0">
            <div class="card-body text-center">
                <div class="mb-3">
                    <span class="display-1 text-secondary">👤</span>
                </div>
                <h3 class="h4"><?= htmlspecialchars($usuario['nombre'] . ' ' . $usuario['apellido']) ?></h3>
                <p class="text-muted"><?= htmlspecialchars($usuario['correo']) ?></p>
                <span class="badge bg-primary">Rol: <?= ucfirst($usuario['rol']) ?></span>
                <span class="badge bg-success"><?= ucfirst($usuario['estado']) ?></span>
                
                <hr>
                <a href="editar_perfil.php" class="btn btn-outline-dark w-100">Editar mis datos</a>
            </div>
        </div>
    </div>

    <!-- Columna Derecha: Actividad -->
    <div class="col-md-8">
        <!-- Sección Cliente -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0">Mi actividad como Huésped</h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-6 col-md-3 mb-3">
                        <h6 class="text-muted small">Pendientes</h6>
                        <span class="h3 text-warning"><?= $statsCliente['pendiente'] ?></span>
                    </div>
                    <div class="col-6 col-md-3 mb-3">
                        <h6 class="text-muted small">Aceptadas</h6>
                        <span class="h3 text-success"><?= $statsCliente['aceptada'] ?></span>
                    </div>
                    <div class="col-6 col-md-3 mb-3">
                        <h6 class="text-muted small">Rechazadas</h6>
                        <span class="h3 text-secondary"><?= $statsCliente['rechazada'] ?></span>
                    </div>
                    <div class="col-6 col-md-3 mb-3">
                        <h6 class="text-muted small">Canceladas</h6>
                        <span class="h3 text-danger"><?= $statsCliente['cancelada'] ?></span>
                    </div>
                </div>
                <div class="text-end mt-3">
                    <a href="modulos/reservas/mis_solicitudes.php" class="btn btn-sm btn-outline-primary">Ver mis solicitudes de reserva</a>
                </div>
            </div>
        </div>

        <!-- Sección Anfitrión (Condicional) -->
        <?php if ($esAnfitrion): ?>
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white">
                <h5 class="mb-0">Mi actividad como Anfitrión</h5>
            </div>
            <div class="card-body">
                <div class="row text-center align-items-center">
                    <div class="col-md-6 mb-3">
                        <h6 class="text-muted small">Alojamientos publicados</h6>
                        <span class="h3 text-primary"><?= $statsAnfitrion['total_alojamientos'] ?></span>
                    </div>
                    <div class="col-md-6 mb-3">
                        <h6 class="text-muted small">Reservas recibidas (histórico)</h6>
                        <span class="h3 text-primary"><?= $statsAnfitrion['total_reservas'] ?></span>
                    </div>
                </div>
                <div class="text-end mt-3">
                    <!-- Cambiamos el enlace para que active el modo anfitrión en la sesión -->
                    <a href="cambiar_modo.php?modo=anfitrion" class="btn btn-dark rounded-pill px-4">
                        ⚙️ Ir al Panel de Anfitrión
                    </a>
                </div>
            </div>
        </div>
        <?php else: ?>
        
        <!-- Tarjeta para Convertirse en Anfitrión -->
        <div class="card shadow-sm border-0 rounded-4 overflow-hidden">
            <!-- Cabecera con degradado verde oscuro -->
            <div class="p-4 text-center" style="background-image: linear-gradient(135deg, #0d2e21, #1a5c42);">
                <h4 class="fw-bold mb-2 text-white">Completa tu perfil de Anfitrión</h4>
                <p class="mb-0 text-white small" style="opacity: 0.85;">Es importante que completes tu perfil para poder recibir solicitudes de reserva y conectar con huéspedes.</p>
            </div>
            
            <div class="card-body p-4 text-center bg-white">
                <div class="mb-3">
                    <span style="font-size: 4rem;">🏡</span>
                </div>
                <h5 class="fw-bold text-dark">Conviértete en Anfitrión</h5>
                <p class="text-muted small mb-4">
                    Publica tu alojamiento, gestiona tus reservas y comienza a generar ingresos. 
                    Es totalmente gratis y tomará solo unos minutos.
                </p>
                
                <div class="d-grid gap-2 col-md-8 mx-auto">
                    <a href="publicar_casa.php" class="btn btn-dark fw-bold rounded-pill py-2">
                        Empezar ahora
                    </a>
                    <button class="btn btn-outline-secondary btn-sm rounded-pill mt-2" type="button" data-bs-toggle="collapse" data-bs-target="#consejosAnfitrion">
                        Leer consejos y reglas
                    </button>
                </div>

                <!-- Acordeón con consejos (Oculto por defecto) -->
                <div class="collapse mt-3" id="consejosAnfitrion">
                    <div class="text-start bg-light p-3 rounded-3 small text-muted">
                        <h6 class="fw-bold text-dark">Consejos para ser un gran anfitrión:</h6>
                        <ul class="mb-0 ps-3">
                            <li>Sube fotos claras y atractivas de tu espacio.</li>
                            <li>Sé detallado en la descripción y las reglas de la casa.</li>
                            <li>Responde a las solicitudes de reserva en menos de 24 horas.</li>
                            <li>Mantén una comunicación amable y respetuosa con los huéspedes.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        
        <?php endif; ?>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
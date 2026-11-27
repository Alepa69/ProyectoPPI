<?php
require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/config/sesion.php';
require_once __DIR__ . '/dao/UsuarioDAO.php';

$idUsuario = (int) $_SESSION['id_usuario'];
$usuarioDao = new UsuarioDAO($conexion);

$usuario = $usuarioDao->obtenerPorId($idUsuario);
$statsCliente = $usuarioDao->obtenerEstadisticasHuesped($idUsuario);
$statsAnfitrion = $usuarioDao->obtenerEstadisticasAnfitrion($idUsuario);
$esAnfitrion = (int) $statsAnfitrion['total_alojamientos'] > 0;

$flash = $_SESSION['perfil_flash'] ?? '';
unset($_SESSION['perfil_flash']);

$pageTitle = 'Mi Perfil';
include __DIR__ . '/includes/header.php';
?>

<?php if ($flash !== ''): ?>
    <div class="alert alert-success" role="status"><?= htmlspecialchars($flash) ?></div>
<?php endif; ?>

<div class="row">
    <!-- Columna Izquierda: Información Personal -->
    <div class="col-md-4 mb-4">
        <div class="card shadow-sm border-0">
            <div class="card-body text-center">
                <div class="mb-3">
                    <?php if (!empty($usuario['foto_perfil'])): ?>
                        <img src="<?= htmlspecialchars($usuario['foto_perfil']) ?>" alt="Foto de perfil" class="rounded-circle object-fit-cover shadow-sm" width="96" height="96">
                    <?php else: ?>
                        <span class="display-1 text-secondary">👤</span>
                    <?php endif; ?>
                </div>
                <h3 class="h4"><?= htmlspecialchars($usuario['nombre'] . ' ' . $usuario['apellido']) ?></h3>
                <p class="text-muted mb-1"><?= htmlspecialchars($usuario['correo']) ?></p>
                <p class="text-muted small mb-2">
                    <i class="bi bi-telephone"></i>
                    <?= !empty($usuario['telefono']) ? htmlspecialchars($usuario['telefono']) : 'Teléfono sin agregar' ?>
                </p>

                <span class="badge bg-primary">Rol: <?= ucfirst($usuario['rol']) ?></span>
                <span class="badge bg-success"><?= ucfirst($usuario['estado']) ?></span>

                <?php if (!empty($usuario['biografia'])): ?>
                    <hr>
                    <p class="text-start small text-muted mb-0 text-break"><?= nl2br(htmlspecialchars($usuario['biografia'])) ?></p>
                <?php endif; ?>

                <hr>
                <div class="d-grid gap-2">
                    <a href="forms/editar_perfil.php" class="btn btn-dark rounded-pill px-4">Editar mi perfil</a>
                    <?php if ($esAnfitrion): ?>
                        <a href="perfil_anfitrion.php?id=<?= $idUsuario ?>" class="btn btn-outline-dark rounded-pill btn-sm">Ver mi perfil público</a>
                    <?php endif; ?>
                    <?php if ($usuario['rol'] === 'administrador'): ?>
                        <a href="admin.php" class="btn btn-outline-success rounded-pill btn-sm">Ir al Panel Admin</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="d-grid mt-3">
            <a href="index.php" class="btn btn-outline-success rounded-pill py-2 d-flex align-items-center justify-content-center gap-2 shadow-sm">
                <i class="bi bi-arrow-left"></i> Volver al inicio
            </a>
        </div>
    </div>

    <!-- Columna Derecha: Actividad -->
    <div class="col-md-8">
        <!-- Sección Huésped -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0">Mi actividad como Huésped</h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-6 col-md-3 mb-3">
                        <h6 class="text-muted small">Pendientes</h6>
                        <span class="h3 text-warning"><?= (int) $statsCliente['pendiente'] ?></span>
                    </div>
                    <div class="col-6 col-md-3 mb-3">
                        <h6 class="text-muted small">Aceptadas</h6>
                        <span class="h3 text-success"><?= (int) $statsCliente['aceptada'] ?></span>
                    </div>
                    <div class="col-6 col-md-3 mb-3">
                        <h6 class="text-muted small">Rechazadas</h6>
                        <span class="h3 text-secondary"><?= (int) $statsCliente['rechazada'] ?></span>
                    </div>
                    <div class="col-6 col-md-3 mb-3">
                        <h6 class="text-muted small">Canceladas</h6>
                        <span class="h3 text-danger"><?= (int) $statsCliente['cancelada'] ?></span>
                    </div>
                </div>
                <div class="text-end mt-3">
                    <a href="panel_cliente.php" class="btn btn-dark rounded-pill px-4">
                        Ver mis solicitudes de reserva
                    </a>
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
                        <span class="h3 text-primary"><?= (int) $statsAnfitrion['total_alojamientos'] ?></span>
                    </div>
                    <div class="col-md-6 mb-3">
                        <h6 class="text-muted small">Reservas recibidas (histórico)</h6>
                        <span class="h3 text-primary"><?= (int) $statsAnfitrion['total_reservas'] ?></span>
                    </div>
                </div>
                <div class="text-end mt-3">
                    <a href="forms/cambiar_modo.php?modo=anfitrion" class="btn btn-dark rounded-pill px-4">
                        ⚙️ Ir al Panel de Anfitrión
                    </a>
                </div>
            </div>
        </div>
        <?php else: ?>

        <!-- Tarjeta para Convertirse en Anfitrión -->
        <div class="card shadow-sm border-0 rounded-4 overflow-hidden">
            <div class="p-4 text-center" style="background-image: linear-gradient(135deg, #0d2e21, #1a5c42);">
                <h4 class="fw-bold mb-2 text-white">Completa tu perfil de Anfitrión</h4>
                <p class="mb-0 text-white small" style="opacity: 0.85;">Publica tu primer alojamiento para empezar a recibir solicitudes de reserva.</p>
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
                    <a href="forms/publicar_casa.php" class="btn btn-dark fw-bold rounded-pill py-2">
                        Empezar ahora
                    </a>
                    <button class="btn btn-outline-secondary btn-sm rounded-pill mt-2" type="button" data-bs-toggle="collapse" data-bs-target="#consejosAnfitrion">
                        Leer consejos y reglas
                    </button>
                </div>

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

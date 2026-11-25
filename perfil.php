<?php
require_once __DIR__ . '/config/sesion.php';
require_once __DIR__ . '/includes/reservas.php';

$id = (int) $_SESSION['id_usuario'];
$stmt = $conexion->prepare('SELECT nombre, apellido, correo, telefono, biografia, foto_perfil FROM usuarios WHERE id_usuario = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$usuario = $stmt->get_result()->fetch_assoc();

$stmt = $conexion->prepare("
    SELECT SUM(estado = 'publicado') AS publicados, COUNT(*) AS alojamientos
    FROM alojamientos WHERE id_anfitrion = ?
");
$stmt->bind_param('i', $id);
$stmt->execute();
$actividad = $stmt->get_result()->fetch_assoc();
$ahora = relojReservas()->format('Y-m-d H:i:s');
$stmt = $conexion->prepare("
    SELECT CASE WHEN r.estado = 'aceptada' AND TIMESTAMP(r.fecha_salida, a.hora_checkout) <= ?
                THEN 'pasadas' ELSE r.estado END AS categoria, COUNT(*) AS total
    FROM reservas r JOIN alojamientos a ON a.id_alojamiento = r.id_alojamiento
    WHERE r.id_solicitante = ? GROUP BY categoria
");
$stmt->bind_param('si', $ahora, $id);
$stmt->execute();
$conteo = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'total', 'categoria');

$flash = $_SESSION['perfil_flash'] ?? '';
unset($_SESSION['perfil_flash']);
$pageTitle = 'Mi perfil';
require __DIR__ . '/includes/header.php';
?>
<h1 class="h2 mb-4">Mi perfil</h1>
<?php if ($flash): ?><p class="alert alert-success" role="status"><?= e($flash) ?></p><?php endif; ?>

<div class="row g-4">
    <div class="col-lg-5">
        <section class="card h-100 shadow-sm rounded-4">
            <div class="card-body p-4">
                <?php if ($usuario['foto_perfil']): ?>
                    <img class="rounded-circle object-fit-cover mb-3" src="<?= e($usuario['foto_perfil']) ?>" width="88" height="88" alt="">
                <?php endif; ?>
                <h2 class="h4"><?= e($usuario['nombre'] . ' ' . $usuario['apellido']) ?></h2>
                <dl class="mb-3">
                    <dt>Correo electrónico</dt>
                    <dd class="text-break"><?= e($usuario['correo']) ?></dd>
                    <dt>Teléfono</dt>
                    <dd><?= e($usuario['telefono'] ?: 'Sin agregar') ?></dd>
                </dl>
                <?php if ($usuario['biografia']): ?><p class="text-break"><?= nl2br(e($usuario['biografia'])) ?></p><?php endif; ?>
                <a href="editar_perfil.php" class="btn btn-outline-dark">Editar mi perfil</a>
            </div>
        </section>
    </div>

    <div class="col-lg-7">
        <section class="card shadow-sm rounded-4 mb-3">
            <div class="card-body p-4">
                <h2 class="h4">Como huésped</h2>
                <p class="text-muted">Consulta la respuesta del anfitrión y los detalles de tus estancias.</p>
                <ul class="list-inline d-flex flex-wrap gap-2 mb-3">
                    <?php foreach (['pendiente' => 'Pendientes', 'aceptada' => 'Confirmadas', 'pasadas' => 'Pasadas', 'rechazada' => 'Rechazadas', 'cancelada' => 'Canceladas'] as $estado => $etiqueta): ?>
                        <li><a class="badge text-bg-light text-decoration-none border" href="panel_cliente.php?estado=<?= e($estado) ?>"><?= e($etiqueta) ?> · <?= (int) ($conteo[$estado] ?? 0) ?></a></li>
                    <?php endforeach; ?>
                </ul>
                <a href="panel_cliente.php" class="btn btn-outline-dark" data-avisos-reserva="huesped">Ver mis solicitudes de reserva</a>
            </div>
        </section>
        <section class="card shadow-sm rounded-4">
            <div class="card-body p-4">
                <h2 class="h4">Como anfitrión</h2>
                <?php if ((int) $actividad['alojamientos'] > 0): ?>
                    <p class="text-muted"><?= (int) $actividad['publicados'] ?> alojamientos publicados</p>
                    <a href="cambiar_modo.php?modo=anfitrion" class="btn btn-dark" data-avisos-reserva="anfitrion">Ir al panel de anfitrión</a>
                <?php else: ?>
                    <p class="text-muted">Desde el panel puedes publicar tu primer alojamiento y gestionar las solicitudes que recibas.</p>
                    <a href="cambiar_modo.php?modo=anfitrion" class="btn btn-dark">Convertirme en anfitrión</a>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>

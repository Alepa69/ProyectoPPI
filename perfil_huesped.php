<?php
require_once __DIR__ . '/config/sesion.php';
require_once __DIR__ . '/includes/formularios.php';

$idReserva = (int) filter_input(INPUT_GET, 'reserva', FILTER_VALIDATE_INT);
$idAnfitrion = (int) $_SESSION['id_usuario'];
$stmt = $conexion->prepare("
    SELECT u.nombre, u.apellido, u.foto_perfil, u.biografia, u.creado_en, a.titulo
    FROM reservas r
    JOIN alojamientos a ON a.id_alojamiento = r.id_alojamiento
    JOIN usuarios u ON u.id_usuario = r.id_solicitante
    WHERE r.id_reserva = ? AND a.id_anfitrion = ?
");
$stmt->bind_param('ii', $idReserva, $idAnfitrion);
$stmt->execute();
$huesped = $stmt->get_result()->fetch_assoc();
if (!$huesped) {
    http_response_code(404);
    exit('Este perfil no está disponible.');
}

$_SESSION['modo'] = 'anfitrion';
$pageTitle = 'Perfil del huésped';
require __DIR__ . '/includes/header.php';
?>
<a class="btn btn-outline-secondary btn-sm mb-3" href="reserva.php?id=<?= $idReserva ?>">Volver a la reserva</a>
<div class="row">
    <div class="col-lg-7">
        <section class="card rounded-4 p-4">
            <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
                <?php if ($huesped['foto_perfil']): ?>
                    <img class="reserva-avatar reserva-avatar-grande" src="<?= e($huesped['foto_perfil']) ?>" alt="">
                <?php else: ?>
                    <span class="reserva-avatar reserva-avatar-grande" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($huesped['nombre'], 0, 1))) ?></span>
                <?php endif; ?>
                <div>
                    <p class="mb-1 text-muted">Huésped · <?= e($huesped['titulo']) ?></p>
                    <h1 class="h3 mb-1"><?= e($huesped['nombre'] . ' ' . $huesped['apellido']) ?></h1>
                    <p class="mb-0 text-muted">En HALOJA desde <?= e(substr($huesped['creado_en'], 0, 4)) ?></p>
                </div>
            </div>
            <?php if ($huesped['biografia']): ?>
                <h2 class="h5">Sobre el huésped</h2>
                <p class="mb-0 text-break"><?= nl2br(e($huesped['biografia'])) ?></p>
            <?php endif; ?>
        </section>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>

<?php
require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/includes/formularios.php';

$id = (int) filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$stmt = $conexion->prepare("
    SELECT u.nombre, u.biografia, u.foto_perfil, u.creado_en, a.id_alojamiento, a.titulo, a.municipio
    FROM usuarios u JOIN alojamientos a ON a.id_anfitrion = u.id_usuario
    WHERE u.id_usuario = ? AND u.estado = 'activo' AND a.estado = 'publicado'
    ORDER BY a.creado_en DESC
");
$stmt->bind_param('i', $id);
$stmt->execute();
$anuncios = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
if (!$anuncios) {
    http_response_code(404);
    exit('Este perfil de anfitrión no está disponible.');
}

$perfil = $anuncios[0];
$pageTitle = 'Conoce al anfitrión';
require __DIR__ . '/includes/header.php';
?>
<div class="row g-4">
    <div class="col-md-5">
        <section class="card rounded-4 p-4">
            <?php if ($perfil['foto_perfil']): ?><img class="rounded-circle object-fit-cover mb-3" src="<?= e($perfil['foto_perfil']) ?>" width="88" height="88" alt=""><?php endif; ?>
            <h1 class="h3"><?= e($perfil['nombre']) ?></h1>
            <p class="text-muted">En HALOJA desde <?= e(substr($perfil['creado_en'], 0, 4)) ?></p>
            <?php if ($perfil['biografia']): ?><p class="text-break mb-0"><?= nl2br(e($perfil['biografia'])) ?></p><?php endif; ?>
        </section>
    </div>
    <div class="col-md-7">
        <h2 class="h4 mb-3">Alojamientos publicados</h2>
        <div class="list-group">
            <?php foreach ($anuncios as $anuncio): ?>
                <a class="list-group-item list-group-item-action py-3" href="listing.php?id=<?= (int) $anuncio['id_alojamiento'] ?>">
                    <strong><?= e($anuncio['titulo']) ?></strong><span class="d-block small text-muted"><?= e($anuncio['municipio']) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>

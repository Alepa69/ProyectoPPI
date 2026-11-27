<?php
require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/dao/UsuarioDAO.php';

$id = (int) filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if ($id <= 0) {
    http_response_code(404);
    exit('Perfil de anfitrión no encontrado.');
}

$usuarioDao = new UsuarioDAO($conexion);
$anuncios = $usuarioDao->obtenerPerfilPublicoAnfitrion($id);

if (!$anuncios) {
    http_response_code(404);
    exit('Este perfil de anfitrión no está disponible.');
}

$perfil = $anuncios[0];
$pageTitle = 'Anfitrión: ' . $perfil['nombre'];
include __DIR__ . '/includes/header.php';
?>

<div class="mb-3">
    <a href="javascript:history.back()" class="btn btn-outline-secondary btn-sm rounded-pill">&larr; Volver</a>
</div>

<div class="row g-4">
    <div class="col-md-5">
        <section class="card rounded-4 shadow-sm border-0 p-4">
            <?php if (!empty($perfil['foto_perfil'])): ?>
                <img class="rounded-circle object-fit-cover mb-3" src="<?= htmlspecialchars($perfil['foto_perfil']) ?>" width="88" height="88" alt="Foto de <?= htmlspecialchars($perfil['nombre']) ?>">
            <?php endif; ?>
            <h1 class="h3 mb-1"><?= htmlspecialchars($perfil['nombre'] . ' ' . $perfil['apellido']) ?></h1>
            <p class="text-muted small">En HALOJA desde <?= htmlspecialchars(substr($perfil['creado_en'], 0, 4)) ?></p>
            <?php if (!empty($perfil['biografia'])): ?>
                <p class="text-break mb-0"><?= nl2br(htmlspecialchars($perfil['biografia'])) ?></p>
            <?php else: ?>
                <p class="text-muted small mb-0">Este anfitrión aún no ha agregado una presentación.</p>
            <?php endif; ?>
        </section>
    </div>

    <div class="col-md-7">
        <h2 class="h4 mb-3">Alojamientos publicados (<?= count($anuncios) ?>)</h2>
        <div class="list-group shadow-sm">
            <?php foreach ($anuncios as $anuncio): ?>
                <a class="list-group-item list-group-item-action py-3" href="listing.php?id=<?= (int) $anuncio['id_alojamiento'] ?>">
                    <strong><?= htmlspecialchars($anuncio['titulo']) ?></strong>
                    <span class="d-block small text-muted"><?= htmlspecialchars($anuncio['municipio'] . ', ' . $anuncio['departamento']) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>

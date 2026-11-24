<?php
require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/config/sesion.php';

// Forzar modo anfitrión
$_SESSION['modo'] = 'anfitrion';

$idAnfitrion = (int) $_SESSION['id_usuario'];

$stmt = $conexion->prepare("
    SELECT a.id_alojamiento, a.titulo, a.municipio, a.departamento, a.precio_persona_noche, a.estado,
           COALESCE(f.ruta_archivo, 'https://picsum.photos/seed/casa/400/220') AS foto_principal
    FROM alojamientos a
    LEFT JOIN fotos_alojamiento f ON a.id_alojamiento = f.id_alojamiento AND f.posicion = 1
    WHERE a.id_anfitrion = ?
    ORDER BY a.creado_en DESC
");
$stmt->bind_param('i', $idAnfitrion);
$stmt->execute();
$alojamientos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Mis Alojamientos';
include __DIR__ . '/includes/header.php';
?>

<!-- Botón Volver -->
<div class="mb-3">
    <a href="index.php" class="btn btn-outline-secondary rounded-pill btn-sm">
        &larr; Volver al panel
    </a>
</div>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0">Mis Alojamientos</h2>
    <a href="publicar_casa.php" class="btn btn-dark rounded-pill px-4">+ Publicar nuevo alojamiento</a>
</div>

<?php if (empty($alojamientos)): ?>
    <div class="text-center py-5">
        <span style="font-size: 5rem;">🏡</span>
        <h3 class="mt-3 fw-bold">No tienes ningún anuncio</h3>
        <p class="text-muted">Comienza a generar ingresos publicando tu primera propiedad en HALOJA.</p>
        <a href="publicar_casa.php" class="btn btn-dark rounded-pill mt-3 px-4">Completar mi anuncio</a>
    </div>
<?php else: ?>
    <div class="row g-4">
        <?php foreach ($alojamientos as $casa): ?>
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 shadow-sm border-0 rounded-4 overflow-hidden">
                    <img src="<?= htmlspecialchars($casa['foto_principal']) ?>" class="card-img-top object-fit-cover" style="height: 200px;" alt="Foto de <?= htmlspecialchars($casa['titulo']) ?>">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h5 class="card-title fw-bold mb-0"><?= htmlspecialchars($casa['titulo']) ?></h5>
                            <span class="badge bg-<?= $casa['estado'] === 'publicado' ? 'success' : 'secondary' ?>">
                                <?= ucfirst($casa['estado']) ?>
                            </span>
                        </div>
                        <p class="text-muted small mb-3"><?= htmlspecialchars($casa['municipio']) ?>, <?= htmlspecialchars($casa['departamento']) ?></p>
                        <h6 class="fw-bold text-primary">$<?= number_format((float)$casa['precio_persona_noche'], 2) ?> <small class="text-muted fw-normal">/ noche</small></h6>
                    </div>
                    <div class="card-footer bg-white border-top-0 pt-0 pb-3 d-flex gap-2">
                        <!-- Botón Ver publicación -->
                        <a href="listing.php?id=<?= (int)$casa['id_alojamiento'] ?>" class="btn btn-outline-dark w-50 rounded-pill btn-sm">Ver</a>
                        <!-- Botón Editar -->
                        <a href="editar_casa.php?id=<?= (int)$casa['id_alojamiento'] ?>" class="btn btn-outline-primary w-50 rounded-pill btn-sm">Editar</a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
<?php
require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/config/sesion.php';

// Forzar modo anfitrión
$_SESSION['modo'] = 'anfitrion';

$idAnfitrion = (int) $_SESSION['id_usuario'];

// Procesar activación / desactivación del alojamiento propio
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();

    $idAlojamiento = (int) ($_POST['id_alojamiento'] ?? 0);
    $nuevoEstado = ($_POST['nuevo_estado'] ?? '') === 'inactivo' ? 'inactivo' : 'publicado';

    if ($idAlojamiento > 0) {
        // Solo se permite cambiar entre publicado e inactivo (no si está bloqueado por admin)
        $stmtEstado = $conexion->prepare("
            UPDATE alojamientos
            SET estado = ?
            WHERE id_alojamiento = ?
              AND id_anfitrion = ?
              AND estado IN ('publicado', 'inactivo')
        ");
        $stmtEstado->bind_param('sii', $nuevoEstado, $idAlojamiento, $idAnfitrion);
        $stmtEstado->execute();

        if ($stmtEstado->affected_rows === 1) {
            $texto = $nuevoEstado === 'inactivo'
                ? 'Alojamiento desactivado. Ya no aparece en el catálogo.'
                : 'Alojamiento publicado nuevamente en el catálogo.';
            $_SESSION['flash'] = ['tipo' => 'success', 'texto' => $texto];
        } else {
            $_SESSION['flash'] = ['tipo' => 'warning', 'texto' => 'No se pudo cambiar el estado del alojamiento.'];
        }
    }

    header('Location: mis_alojamientos.php');
    exit;
}

$stmt = $conexion->prepare("
    SELECT a.id_alojamiento, a.titulo,
           COALESCE(NULLIF(a.ciudad, ''), a.municipio) AS municipio,
           a.departamento,
           COALESCE(NULLIF(a.precio_noche, 0), a.precio_persona_noche) AS precio_persona_noche,
           a.estado, a.motivo_bloqueo,
           COALESCE(
               (SELECT ai.ruta_o_url
                FROM alojamiento_imagenes ai
                WHERE ai.alojamiento_id = a.id_alojamiento
                  AND ai.es_portada = 1
                ORDER BY ai.orden ASC, ai.id ASC
                LIMIT 1),
               (SELECT ai2.ruta_o_url
                FROM alojamiento_imagenes ai2
                WHERE ai2.alojamiento_id = a.id_alojamiento
                ORDER BY ai2.orden ASC, ai2.id ASC
                LIMIT 1),
               f.ruta_archivo,
               'https://picsum.photos/seed/casa/400/220'
           ) AS foto_principal
    FROM alojamientos a
    LEFT JOIN fotos_alojamiento f ON a.id_alojamiento = f.id_alojamiento AND f.posicion = 1
    WHERE a.id_anfitrion = ?
    ORDER BY a.creado_en DESC
");
$stmt->bind_param('i', $idAnfitrion);
$stmt->execute();
$alojamientos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$pageTitle = 'Mis Alojamientos';
include __DIR__ . '/includes/header.php';
?>

<!-- Botón Volver -->
<div class="mb-3">
    <a href="index.php" class="btn btn-outline-secondary rounded-pill btn-sm">
        &larr; Volver al catálogo
    </a>
</div>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2 class="mb-0">Mis Alojamientos</h2>
    <a href="publicar_casa.php" class="btn btn-dark rounded-pill px-4">+ Publicar nuevo alojamiento</a>
</div>

<?php if ($flash): ?>
    <div class="alert alert-<?= htmlspecialchars($flash['tipo']) ?>">
        <?= htmlspecialchars($flash['texto']) ?>
    </div>
<?php endif; ?>

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
            <?php
            $badgeClass = match ($casa['estado']) {
                'publicado' => 'success',
                'inactivo' => 'secondary',
                'bloqueado' => 'danger',
                default => 'warning text-dark',
            };
            ?>
            <div class="col-md-6 col-lg-4">
                <div class="card h-100 shadow-sm border-0 rounded-4 overflow-hidden">
                    <img src="<?= htmlspecialchars($casa['foto_principal']) ?>" class="card-img-top object-fit-cover" style="height: 200px;" loading="lazy" alt="Foto de <?= htmlspecialchars($casa['titulo']) ?>">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2 gap-2">
                            <h5 class="card-title fw-bold mb-0"><?= htmlspecialchars($casa['titulo']) ?></h5>
                            <span class="badge bg-<?= $badgeClass ?>">
                                <?= ucfirst($casa['estado']) ?>
                            </span>
                        </div>
                        <p class="text-muted small mb-2"><?= htmlspecialchars($casa['municipio']) ?>, <?= htmlspecialchars($casa['departamento']) ?></p>
                        <h6 class="fw-bold text-primary">$<?= number_format((float) $casa['precio_persona_noche'], 2) ?> <small class="text-muted fw-normal">/ persona · noche</small></h6>
                        <?php if ($casa['estado'] === 'bloqueado' && !empty($casa['motivo_bloqueo'])): ?>
                            <div class="small text-danger mt-2">
                                <strong>Motivo:</strong> <?= htmlspecialchars($casa['motivo_bloqueo']) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="card-footer bg-white border-top-0 pt-0 pb-3 d-flex gap-2">
                        <?php if ($casa['estado'] === 'publicado'): ?>
                            <a href="listing.php?id=<?= (int) $casa['id_alojamiento'] ?>" class="btn btn-outline-dark flex-fill rounded-pill btn-sm">Ver</a>
                        <?php endif; ?>
                        <a href="editar_casa.php?id=<?= (int) $casa['id_alojamiento'] ?>" class="btn btn-outline-primary flex-fill rounded-pill btn-sm">Editar</a>
                        <?php if ($casa['estado'] === 'publicado' || $casa['estado'] === 'inactivo'): ?>
                            <form method="POST" action="mis_alojamientos.php" class="flex-fill d-flex">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                <input type="hidden" name="id_alojamiento" value="<?= (int) $casa['id_alojamiento'] ?>">
                                <input type="hidden" name="nuevo_estado" value="<?= $casa['estado'] === 'publicado' ? 'inactivo' : 'publicado' ?>">
                                <button type="submit" class="btn btn-sm rounded-pill w-100 <?= $casa['estado'] === 'publicado' ? 'btn-outline-danger' : 'btn-outline-success' ?>">
                                    <?= $casa['estado'] === 'publicado' ? 'Desactivar' : 'Activar' ?>
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>

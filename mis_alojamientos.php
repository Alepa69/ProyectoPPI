<?php
require_once __DIR__ . '/config/sesion.php';
require_once __DIR__ . '/includes/formularios.php';

$_SESSION['modo'] = 'anfitrion';
$id = (int) $_SESSION['id_usuario'];
$stmt = $conexion->prepare("
    SELECT a.id_alojamiento, a.titulo, a.municipio, a.departamento, a.precio_persona_noche, a.estado,
        COALESCE(
            (SELECT i.ruta_o_url FROM alojamiento_imagenes i WHERE i.alojamiento_id = a.id_alojamiento
             ORDER BY i.es_portada DESC, i.orden, i.id LIMIT 1),
            (SELECT f.ruta_archivo FROM fotos_alojamiento f WHERE f.id_alojamiento = a.id_alojamiento
             ORDER BY f.posicion LIMIT 1)
        ) AS portada
    FROM alojamientos a WHERE a.id_anfitrion = ? ORDER BY a.creado_en DESC
");
$stmt->bind_param('i', $id);
$stmt->execute();
$alojamientos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$flash = $_SESSION['alojamiento_flash'] ?? null;
unset($_SESSION['alojamiento_flash']);

$pageTitle = 'Mis alojamientos';
require __DIR__ . '/includes/header.php';
?>
<a href="panel_anfitrion.php" class="btn btn-outline-secondary btn-sm mb-3">Volver al panel</a>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <h1 class="h2 mb-0">Mis alojamientos</h1>
    <a href="publicar_casa.php?desde=anfitrion" class="btn btn-dark">Publicar nuevo alojamiento</a>
</div>
<?php if ($flash): ?><p class="alert alert-<?= e($flash['tipo']) ?>" role="status"><?= e($flash['texto']) ?></p><?php endif; ?>
<?php if (!$alojamientos): ?>
    <p class="card rounded-4 p-4">Aquí podrás ver y administrar los alojamientos que publiques.</p>
<?php else: ?>
    <div class="row g-4">
        <?php foreach ($alojamientos as $casa): ?>
            <?php $publicado = $casa['estado'] === 'publicado'; ?>
            <div class="col-md-6 col-lg-4">
                <article class="card h-100 rounded-4 overflow-hidden shadow-sm">
                    <?php if ($casa['portada']): ?>
                        <img src="<?= e($casa['portada']) ?>" class="card-img-top object-fit-cover" style="height: 200px" alt="" loading="lazy">
                    <?php endif; ?>
                    <div class="card-body">
                        <span class="badge bg-<?= $publicado ? 'success' : 'secondary' ?> mb-2"><?= e(ucfirst($casa['estado'])) ?></span>
                        <h2 class="h5"><?= e($casa['titulo']) ?></h2>
                        <p class="text-muted small mb-1"><?= e($casa['municipio'] . ', ' . $casa['departamento']) ?></p>
                        <p class="mb-0"><strong>$<?= number_format((float) $casa['precio_persona_noche'], 2) ?></strong> por persona y noche</p>
                    </div>
                    <div class="card-footer bg-white d-flex flex-wrap gap-2">
                        <?php if ($publicado): ?>
                            <a href="listing.php?id=<?= (int) $casa['id_alojamiento'] ?>" class="btn btn-outline-dark btn-sm">Ver</a>
                        <?php endif; ?>
                        <a href="editar_casa.php?id=<?= (int) $casa['id_alojamiento'] ?>" class="btn btn-outline-dark btn-sm">Editar</a>
                        <a href="calendario_anfitrion.php?id=<?= (int) $casa['id_alojamiento'] ?>" class="btn btn-outline-dark btn-sm">Calendario</a>
                        <?php if ($publicado || $casa['estado'] === 'inactivo'): ?>
                            <form method="post" action="estado_alojamiento.php" <?php if ($publicado): ?>onsubmit="return confirm('¿Quitar este alojamiento del catálogo? Se conservará el historial de reservas.');"<?php endif; ?>>
                                <input type="hidden" name="csrf" value="<?= e(tokenFormulario()) ?>">
                                <input type="hidden" name="id_alojamiento" value="<?= (int) $casa['id_alojamiento'] ?>">
                                <?php if ($publicado): ?>
                                    <button class="btn btn-outline-danger btn-sm" name="accion" value="retirar">Eliminar del catálogo</button>
                                <?php else: ?>
                                    <button class="btn btn-outline-dark btn-sm" name="accion" value="reactivar">Volver a publicar</button>
                                <?php endif; ?>
                            </form>
                        <?php endif; ?>
                    </div>
                </article>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>

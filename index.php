<?php
require_once __DIR__. '/config/conexion.php';

$sql = "SELECT
    a.id_alojamiento,
    a.titulo,
    COALESCE(NULLIF(a.ciudad, ''), a.municipio) AS municipio,
    a.departamento,
    COALESCE(NULLIF(a.precio_noche, 0), a.precio_persona_noche) AS precio_persona_noche,
    u.nombre AS anfitrion_nombre,
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
JOIN usuarios u ON a.id_anfitrion = u.id_usuario
LEFT JOIN fotos_alojamiento f ON a.id_alojamiento = f.id_alojamiento AND f.posicion = 1
WHERE a.estado = 'publicado'
ORDER BY a.creado_en DESC";

$resultado = $conexion->query($sql);
$pageTitle = 'Menu principal'; // Definido una sola vez aquí adentro
?>

<?php include __DIR__ . '/includes/header.php'; ?>

    <div>
      <h2>Casas disponibles</h2>
      <aside>
        <!--valida si hay disponibles-->
        <?php if ($resultado->num_rows === 0): ?>
        <p class="text-muted">No se han encontrado casas disponibles</p>
        <?php endif; ?>

        <!--bucle de casas-->
        <?php while ($p = $resultado->fetch_assoc()):?>

          <a href="listing.php?id=<?= (int)$p['id_alojamiento'] ?>" class="card text-decoration-none text-dark border shadow-sm mb-3 overflow-hidden">
                <img src="<?= htmlspecialchars($p['foto_principal']) ?>" class="card-img-top object-fit-cover" style="height: 180px;" alt="Foto de <?= htmlspecialchars($p['titulo']) ?>">
                <div class="card-body">
                    <h3 class="h6 fw-bold mb-1 text-dark"><?= htmlspecialchars($p['titulo']) ?></h3>
                    <p class="text-muted small mb-2">📍 <?= htmlspecialchars($p['municipio']) ?>, <?= htmlspecialchars($p['departamento']) ?></p>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="fw-bold text-primary">$<?= number_format((float)$p['precio_persona_noche'], 2) ?> <small class="text-muted fw-normal">/ noche</small></span>
                    </div>
                    <span class="text-muted small d-block mt-2">Anfitrión: <?= htmlspecialchars($p['anfitrion_nombre']) ?></span>
                </div>
            </a>

        <?php endwhile; ?>
      </aside>
<!--mapa-->
      <div id="map" class="col-12 col-md-7 col-lg-8 p-0 bg-secondary-subtle" style="height: 300px;"></div>
    </div>

<?php include __DIR__ . '/includes/footer.php'; ?>
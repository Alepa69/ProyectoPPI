<?php
require_once __DIR__. '/config/conexion.php';

$sql = "select 
a.id_alojamiento, 
a.titulo, a.municipio, 
a.departamento, 
a.precio_persona_noche, 
u.nombre AS anfitrion_nombre,
COALESCE(f.ruta_archivo, 'https://picsum.photos/seed/casa/400/220') AS foto_principal

from alojamientos a 
join usuarios u on a.id_anfitrion = u.id_usuario
left join fotos_alojamiento f on a.id_alojamiento = f.id_alojamiento and f.posicion=1

where a.estado = 'publicado'
order by a.creado_en DESC";

$resultado = $conexion->query($sql);
$pageTitle='Menu principal';
?>

<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Airbnb Temu</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
   
    body { background-color: #f8f9fa; }
  </style>
</head>

<body>
  <div class="container my-4">
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
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="js/map.js"></script>
</body>

</html>
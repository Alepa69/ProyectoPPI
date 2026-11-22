<?php
require_once __DIR__ . '/config/conexion.php';

// Filtros del listado
$tipo = isset($_GET['tipo']) ? (int)$_GET['tipo'] : 0;
$casa_titulo = isset($_GET['casa_titulo']) ? trim($_GET['casa_titulo']) : '';
$precio_minimo = isset($_GET['precio_minimo']) ? trim($_GET['precio_minimo']) : '';
$precio_maximo = isset($_GET['precio_maximo']) ? trim($_GET['precio_maximo']) : '';
$orden = isset($_GET['orden']) ? $_GET['orden'] : '';

$params = [];
$types = '';
$precioExpr = "COALESCE(NULLIF(a.precio_noche, 0), a.precio_persona_noche)";

$sql = "SELECT
    a.id_alojamiento,
    a.titulo,
    COALESCE(NULLIF(a.ciudad, ''), a.municipio) AS municipio,
    a.departamento,
    {$precioExpr} AS precio_persona_noche,
    a.latitud,
    a.longitud,
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
WHERE a.estado = 'publicado'";

if ($tipo > 0) {
    $sql .= " AND a.id_tipo = ?";
    $params[] = $tipo;
    $types .= "i";
}
if (!empty($casa_titulo)) {
    $sql .= " AND a.titulo LIKE ?";
    $params[] = "%" . $casa_titulo . "%";
    $types .= "s";
}
if ($precio_minimo !== '') {
    $sql .= " AND {$precioExpr} >= ?";
    $params[] = (float)$precio_minimo;
    $types .= "d";
}
if ($precio_maximo !== '') {
    $sql .= " AND {$precioExpr} <= ?";
    $params[] = (float)$precio_maximo;
    $types .= "d";
}

if (!empty($casa_titulo)) {
    $sql .= " AND a.titulo LIKE ?";
    $params[] = "%" . $casa_titulo . "%";
    $types .= "s";
}
if ($precio_minimo !== '') {
    $sql .= " AND {$precioExpr} >= ?";
    $params[] = (float)$precio_minimo;
    $types .= "d";
}
if ($precio_maximo !== '') {
    $sql .= " AND {$precioExpr} <= ?";
    $params[] = (float)$precio_maximo;
    $types .= "d";
}

if ($orden === 'precio_asc') {
    $sql .= " ORDER BY precio_persona_noche ASC";
} elseif ($orden === 'precio_desc') {
    $sql .= " ORDER BY precio_persona_noche DESC";
} else {
    $sql .= " ORDER BY a.creado_en DESC";
}

$stmt = $conexion->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$resultado = $stmt->get_result();
$casas = $resultado->fetch_all(MYSQLI_ASSOC);
$pageTitle = 'Menú principal';
?>

<?php include __DIR__ . '/includes/header.php'; ?>

<form action="" method="GET" class="card shadow-sm border-0 rounded-4 p-3 mb-4">
        <?php if ($tipo > 0): ?>
            <input type="hidden" name="tipo" value="<?= $tipo ?>">
        <?php endif; ?>
        <div class="row g-3 align-items-end">
            <div class="col-12 col-md-4">
                <label for="casa_titulo" class="form-label small fw-semibold">Buscar por nombre</label>
                <input type="text" id="casa_titulo" name="casa_titulo" class="form-control rounded-pill"
                       placeholder="Zona rosa" value="<?= htmlspecialchars($casa_titulo) ?>">
            </div>
            <div class="col-6 col-md-2">
                <label for="precio_minimo" class="form-label small fw-semibold">Precio min</label>
                <input type="number" id="precio_minimo" name="precio_minimo" class="form-control rounded-pill"
                       placeholder="0" min="0" value="<?= htmlspecialchars($precio_minimo) ?>">
            </div>
            <div class="col-6 col-md-2">
                <label for="precio_maximo" class="form-label small fw-semibold">Precio max</label>
                <input type="number" id="precio_maximo" name="precio_maximo" class="form-control rounded-pill"
                       placeholder="500" min="0" value="<?= htmlspecialchars($precio_maximo) ?>">
            </div>
            <div class="col-8 col-md-2">
                <label for="orden" class="form-label small fw-semibold">Ordenar por</label>
                <select name="orden" id="orden" class="form-select rounded-pill">
                    <option value="">Mas recientes</option>
                    <option value="precio_asc" <?= $orden === 'precio_asc' ? 'selected' : '' ?>>Menor a mayor</option>
                    <option value="precio_desc" <?= $orden === 'precio_desc' ? 'selected' : '' ?>>Mayor a menor</option>
                </select>
            </div>
            <div class="col-4 col-md-2">
                <button class="btn btn-brand w-100">Buscar</button>
            </div>
        </div>
    </form>

    <div>
      <h2>Casas disponibles</h2>
      <aside>
        <?php if (count($casas) === 0): ?>
        <p class="text-muted">No se han encontrado casas disponibles</p>
        <?php endif; ?>

        <?php foreach ($casas as $p): ?>
          <div class="card border shadow-sm mb-3 overflow-hidden">
                <img src="<?= htmlspecialchars($p['foto_principal']) ?>" class="card-img-top object-fit-cover" style="height: 180px;" alt="Foto de <?= htmlspecialchars($p['titulo']) ?>">
                <div class="card-body">
                    <h3 class="h6 fw-bold mb-1 text-dark"><?= htmlspecialchars($p['titulo']) ?></h3>
                    <p class="text-muted small mb-2">nombre:<?= htmlspecialchars($p['municipio']) ?>, <?= htmlspecialchars($p['departamento']) ?></p>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="fw-bold text-primary">$<?= number_format((float)$p['precio_persona_noche'], 2) ?> <small class="text-muted fw-normal">la noche</small></span>
                    </div>
                    <span class="text-muted small d-block mt-2 mb-3">Anfitrion: <?= htmlspecialchars($p['anfitrion_nombre']) ?></span>
                    <a href="listing.php?id=<?= (int)$p['id_alojamiento'] ?>" class="btn btn-outline-success btn-sm">ver detalles</a>
                </div>
            </div>
        <?php endforeach; ?>
      </aside>

      <div id="map" style="height: 400px;"></div>
    </div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    const casas = <?= json_encode($casas) ?>;
    const map = L.map('map').setView([13.69, -89.23], 12);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);

    casas.forEach(c => {
        if (c.latitud === null || c.longitud === null || c.latitud === '' || c.longitud === '') return;
        const lat = parseFloat(c.latitud);
        const lng = parseFloat(c.longitud);
        if (Number.isNaN(lat) || Number.isNaN(lng)) return;

        const popup = document.createElement('div');
        const link = document.createElement('a');
        link.href = 'listing.php?id=' + encodeURIComponent(c.id_alojamiento);
        link.textContent = c.titulo;
        popup.appendChild(link);
        popup.appendChild(document.createElement('br'));
        popup.appendChild(document.createTextNode('$' + c.precio_persona_noche + ' / noche'));

        L.marker([lat, lng]).addTo(map).bindPopup(popup);
    });
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>

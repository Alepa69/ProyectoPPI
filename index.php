<?php
require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/includes/portada_alojamiento.php';

// Filtros del listado
$tipo = isset($_GET['tipo']) ? (int)$_GET['tipo'] : 0;
$casa_titulo = isset($_GET['casa_titulo']) ? trim($_GET['casa_titulo']) : '';
$departamento = isset($_GET['departamento']) ? trim($_GET['departamento']) : '';
$precio_minimo = isset($_GET['precio_minimo']) ? trim($_GET['precio_minimo']) : '';
$precio_maximo = isset($_GET['precio_maximo']) ? trim($_GET['precio_maximo']) : '';
$orden = isset($_GET['orden']) ? $_GET['orden'] : '';

$mapLat = 13.69;
$mapLng = -89.23;

switch ($departamento) {
    case 'Ahuachapán':
        $mapLat = 13.9214;
        $mapLng = -89.8450;
        break;
    case 'Cabañas':
        $mapLat = 13.8767;
        $mapLng = -88.6297;
        break;
    case 'Chalatenango':
        $mapLat = 14.0333;
        $mapLng = -88.9333;
        break;
    case 'Cuscatlán':
        $mapLat = 13.7167;
        $mapLng = -88.9333;
        break;
    case 'La Libertad':
        $mapLat = 13.6769;
        $mapLng = -89.2797;
        break;
    case 'La Paz':
        $mapLat = 13.5000;
        $mapLng = -88.8667;
        break;
    case 'La Unión':
        $mapLat = 13.3369;
        $mapLng = -87.8439;
        break;
    case 'Morazán':
        $mapLat = 13.6967;
        $mapLng = -88.1061;
        break;
    case 'San Miguel':
        $mapLat = 13.4833;
        $mapLng = -88.1833;
        break;
    case 'San Salvador':
        $mapLat = 13.6929;
        $mapLng = -89.2182;
        break;
    case 'San Vicente':
        $mapLat = 13.6333;
        $mapLng = -88.8000;
        break;
    case 'Santa Ana':
        $mapLat = 13.9942;
        $mapLng = -89.5597;
        break;
    case 'Sonsonate':
        $mapLat = 13.7189;
        $mapLng = -89.7242;
        break;
    case 'Usulután':
        $mapLat = 13.3500;
        $mapLng = -88.4500;
        break;
}

$params = [];
$types = '';
$precioExpr = 'a.precio_persona_noche';
$portadaExpr = PORTADA_ALOJAMIENTO_SQL;

$sql = "SELECT
    a.id_alojamiento,
    a.titulo,
    COALESCE(NULLIF(a.ciudad, ''), a.municipio) AS municipio,
    a.departamento,
    {$precioExpr} AS precio_persona_noche,
    a.latitud,
    a.longitud,
    u.nombre AS anfitrion_nombre,
    COALESCE($portadaExpr, 'https://picsum.photos/seed/casa/400/220') AS foto_principal
FROM alojamientos a
JOIN usuarios u ON a.id_anfitrion = u.id_usuario
WHERE a.estado = 'publicado' AND u.estado = 'activo'";

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
if (!empty($departamento)) {
    $sql .= " AND a.departamento = ?";
    $params[] = $departamento;
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
$heroImagen = 'assets/img/ilopango.jpg';
?>

<?php include __DIR__ . '/includes/header.php'; ?>

<!-- OPCIÓN 1 (diseño sin bordes):  class="hero-haloja mb-5" -->
<!-- OPCIÓN 2 (borde a borde):  class="hero-haloja hero-haloja--full mb-5" -->
<section class="hero-haloja hero-haloja--full mb-5">
    <img src="<?= htmlspecialchars($heroImagen, ENT_QUOTES, 'UTF-8') ?>" alt="" class="hero-haloja__bg">
    <div class="hero-haloja__contenido">
        <span class="hero-haloja__badge">Destinos únicos en El Salvador</span>
        <h1 class="hero-haloja__titulo">
            Encuentra tu <span class="hero-haloja__acento">lugar</span> en el mundo
        </h1>
        <p class="hero-haloja__subtitulo">
            Casas, cabañas y apartamentos con anfitriones que te hacen sentir en casa.
        </p>

        <form action="" method="GET" class="hero-buscador">
            <?php if ($tipo > 0): ?>
                <input type="hidden" name="tipo" value="<?= $tipo ?>">
            <?php endif; ?>

            <div class="hero-buscador__campo hero-buscador__campo--grande">
                <label for="casa_titulo" class="hero-buscador__label">Buscar por nombre</label>
                <input type="text" id="casa_titulo" name="casa_titulo" class="form-control"
                       placeholder="Ej. Zona Rosa, Escalón..." value="<?= htmlspecialchars($casa_titulo) ?>">
            </div>

            <div class="hero-buscador__campo hero">
                <label for="departamento" class="hero-buscador__label">Departamento</label>
                <select name="departamento" id="departamento" class="form-select">
                    <option value="">Todos</option>
                    <option value="Ahuachapán" <?= $departamento === 'Ahuachapán' ? 'selected' : '' ?>>Ahuachapán</option>
                    <option value="Cabañas" <?= $departamento === 'Cabañas' ? 'selected' : '' ?>>Cabañas</option>
                    <option value="Chalatenango" <?= $departamento === 'Chalatenango' ? 'selected' : '' ?>>Chalatenango</option>
                    <option value="Cuscatlán" <?= $departamento === 'Cuscatlán' ? 'selected' : '' ?>>Cuscatlán</option>
                    <option value="La Libertad" <?= $departamento === 'La Libertad' ? 'selected' : '' ?>>La Libertad</option>
                    <option value="La Paz" <?= $departamento === 'La Paz' ? 'selected' : '' ?>>La Paz</option>
                    <option value="La Unión" <?= $departamento === 'La Unión' ? 'selected' : '' ?>>La Unión</option>
                    <option value="Morazán" <?= $departamento === 'Morazán' ? 'selected' : '' ?>>Morazán</option>
                    <option value="San Miguel" <?= $departamento === 'San Miguel' ? 'selected' : '' ?>>San Miguel</option>
                    <option value="San Salvador" <?= $departamento === 'San Salvador' ? 'selected' : '' ?>>San Salvador</option>
                    <option value="San Vicente" <?= $departamento === 'San Vicente' ? 'selected' : '' ?>>San Vicente</option>
                    <option value="Santa Ana" <?= $departamento === 'Santa Ana' ? 'selected' : '' ?>>Santa Ana</option>
                    <option value="Sonsonate" <?= $departamento === 'Sonsonate' ? 'selected' : '' ?>>Sonsonate</option>
                    <option value="Usulután" <?= $departamento === 'Usulután' ? 'selected' : '' ?>>Usulután</option>
                </select>
            </div>

            <div class="hero-buscador__campo">
                <label for="precio_minimo" class="hero-buscador__label">Precio mín.</label>
                <input type="number" id="precio_minimo" name="precio_minimo" class="form-control"
                       placeholder="$0" min="0" value="<?= htmlspecialchars($precio_minimo) ?>">
            </div>

            <div class="hero-buscador__campo">
                <label for="precio_maximo" class="hero-buscador__label">Precio máx.</label>
                <input type="number" id="precio_maximo" name="precio_maximo" class="form-control"
                       placeholder="$500" min="0" value="<?= htmlspecialchars($precio_maximo) ?>">
            </div>

            <div class="hero-buscador__campo">
                <label for="orden" class="hero-buscador__label">Ordenar por</label>
                <select name="orden" id="orden" class="form-select">
                    <option value="">Más recientes</option>
                    <option value="precio_asc" <?= $orden === 'precio_asc' ? 'selected' : '' ?>>Menor a mayor</option>
                    <option value="precio_desc" <?= $orden === 'precio_desc' ? 'selected' : '' ?>>Mayor a menor</option>
                </select>
            </div>

            <div class="hero-buscador__accion">
                <button type="submit" class="btn hero-buscador__btn">
                    <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true">
                        <path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001q.044.06.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1 1 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0"/>
                    </svg>
                    <span>Buscar</span>
                </button>
            </div>
        </form>
    </div>
</section>

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
                        <span class="fw-bold text-primary">$<?= number_format((float)$p['precio_persona_noche'], 2) ?> <small class="text-muted fw-normal">por persona y noche</small></span>
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
    const map = L.map('map').setView([<?= $mapLat ?>, <?= $mapLng ?>], 12);

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
        popup.appendChild(document.createTextNode('$' + c.precio_persona_noche + ' por persona y noche'));

        L.marker([lat, lng]).addTo(map).bindPopup(popup);
    });
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>

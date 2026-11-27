<?php
require_once __DIR__ . '/config/conexion.php';

// Filtros del listado
$tipo = isset($_GET['tipo']) ? (int) $_GET['tipo'] : 0;
$casa_titulo = isset($_GET['casa_titulo']) ? trim($_GET['casa_titulo']) : '';
$departamento = isset($_GET['departamento']) ? trim($_GET['departamento']) : '';
$huespedes = (isset($_GET['huespedes']) && $_GET['huespedes'] !== '') ? max(0, (int) $_GET['huespedes']) : 0;
$precio_minimo = isset($_GET['precio_minimo']) ? trim($_GET['precio_minimo']) : '';
$precio_maximo = isset($_GET['precio_maximo']) ? trim($_GET['precio_maximo']) : '';
$orden = isset($_GET['orden']) ? $_GET['orden'] : '';
$errorFiltro = '';

$precioMinValido = ($precio_minimo !== '' && is_numeric($precio_minimo) && (float) $precio_minimo >= 0) ? (float) $precio_minimo : null;
$precioMaxValido = ($precio_maximo !== '' && is_numeric($precio_maximo) && (float) $precio_maximo >= 0) ? (float) $precio_maximo : null;

if ($precio_minimo !== '' && $precioMinValido === null) {
    $errorFiltro = 'El precio mínimo debe ser un número mayor o igual a 0.';
} elseif ($precio_maximo !== '' && $precioMaxValido === null) {
    $errorFiltro = 'El precio máximo debe ser un número mayor o igual a 0.';
} elseif ($precioMinValido !== null && $precioMaxValido !== null && $precioMinValido > $precioMaxValido) {
    $errorFiltro = 'El precio mínimo no puede ser mayor al precio máximo.';
}

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
$precioExpr = "COALESCE(NULLIF(a.precio_noche, 0), a.precio_persona_noche)";

$sql = "SELECT
    a.id_alojamiento,
    a.titulo,
    COALESCE(NULLIF(a.ciudad, ''), a.municipio) AS municipio,
    a.departamento,
    a.capacidad,
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
WHERE a.estado = 'publicado'
  AND u.estado = 'activo'";

if ($tipo > 0) {
    $sql .= " AND a.id_tipo = ?";
    $params[] = $tipo;
    $types .= "i";
}
if (!empty($casa_titulo)) {
    $sql .= " AND (a.titulo LIKE ? OR a.municipio LIKE ? OR a.ciudad LIKE ?)";
    $busqueda = "%" . $casa_titulo . "%";
    $params[] = $busqueda;
    $params[] = $busqueda;
    $params[] = $busqueda;
    $types .= "sss";
}
if (!empty($departamento)) {
    $sql .= " AND a.departamento = ?";
    $params[] = $departamento;
    $types .= "s";
}
if ($huespedes > 0) {
    $sql .= " AND a.capacidad >= ?";
    $params[] = $huespedes;
    $types .= "i";
}
if ($precioMinValido !== null) {
    $sql .= " AND {$precioExpr} >= ?";
    $params[] = $precioMinValido;
    $types .= "d";
}
if ($precioMaxValido !== null) {
    $sql .= " AND {$precioExpr} <= ?";
    $params[] = $precioMaxValido;
    $types .= "d";
}

if ($orden === 'precio_asc') {
    $sql .= " ORDER BY precio_persona_noche ASC, a.id_alojamiento DESC";
} elseif ($orden === 'precio_desc') {
    $sql .= " ORDER BY precio_persona_noche DESC, a.id_alojamiento DESC";
} else {
    $sql .= " ORDER BY a.creado_en DESC, a.id_alojamiento DESC";
}

$cargarMas = ($_GET['cargar_mas'] ?? '') === '1';
$cantidad = 3;
$desde = 0;
if ($cargarMas) {
    $cantidad = max(1, min(12, (int) ($_GET['cantidad'] ?? 3)));
    $desde = max(0, (int) ($_GET['desde'] ?? 0));
}

// Pedimos una casa extra para saber si todavía quedan más.
$sql .= " LIMIT ? OFFSET ?";
$params[] = $cantidad + 1;
$params[] = $desde;
$types .= 'ii';

$stmt = $conexion->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$resultado = $stmt->get_result();
$casas = $resultado->fetch_all(MYSQLI_ASSOC);
$hayMas = count($casas) > $cantidad;
if ($hayMas) {
    array_pop($casas);
}

if ($cargarMas) {
    // Usamos las mismas tarjetas que se muestran al abrir el index.
    ob_start();
    include __DIR__ . '/includes/tarjetas-inicio.php';
    $tarjetas = ob_get_clean();

    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['tarjetas' => $tarjetas, 'casas' => $casas, 'hayMas' => $hayMas]);
    exit;
}

$pageTitle = 'Menú principal';
$heroImagen = 'assets/img/ilopango.jpg';
?>

<?php include __DIR__ . '/includes/header.php'; ?>

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
                <label for="casa_titulo" class="hero-buscador__label">Buscar lugar</label>
                <input type="text" id="casa_titulo" name="casa_titulo" class="form-control"
                       placeholder="Ej. Zona Rosa, Escalón..." value="<?= htmlspecialchars($casa_titulo) ?>">
            </div>

            <div class="hero-buscador__campo">
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
                <label for="huespedes" class="hero-buscador__label">Huéspedes</label>
                <input type="number" id="huespedes" name="huespedes" class="form-control"
                       placeholder="Cualquiera" min="1" value="<?= $huespedes > 0 ? $huespedes : '' ?>">
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

    <div id="alojamientos">
      <h2>Alojamientos disponibles</h2>
      <?php if ($errorFiltro !== ''): ?>
        <div class="alert alert-warning"><?= htmlspecialchars($errorFiltro) ?></div>
      <?php endif; ?>
      <?php if (count($casas) === 0): ?>
        <p class="text-muted">No se han encontrado alojamientos disponibles con esos filtros.</p>
      <?php endif; ?>
      <aside id="lista-alojamientos">
        <?php include __DIR__ . '/includes/tarjetas-inicio.php'; ?>
      </aside>

      <?php if ($hayMas): ?>
        <div class="text-center">
          <button type="button" id="mostrar-mas" class="btn btn-outline-success" aria-controls="lista-alojamientos" hidden>Mostrar más</button>
          <p id="mensaje-alojamientos" class="text-danger mt-2 mb-0" role="status"></p>
        </div>
      <?php endif; ?>

      <div id="map"
           style="height: 420px;"
           data-lat="<?= $mapLat ?>"
           data-lng="<?= $mapLng ?>"
           data-casas="<?= htmlspecialchars(json_encode($casas), ENT_QUOTES, 'UTF-8') ?>"></div>
    </div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="assets/js/mapa-inicio.js"></script>
<script src="assets/js/alojamientos-inicio.js"></script>
<?php include __DIR__ . '/includes/footer.php'; ?>

<?php
require_once __DIR__ . '/config/conexion.php';

// 1. Leer los filtros enviados desde el formulario.
$casa_titulo = trim(filter_input(INPUT_GET, 'casa_titulo') ?? '');
$precio_minimo = trim(filter_input(INPUT_GET, 'precio_minimo') ?? '');
$precio_maximo = trim(filter_input(INPUT_GET, 'precio_maximo') ?? '');
$orden = filter_input(INPUT_GET, 'orden') ?? '';

$errores = [];
$casas = [];

// 2. Validar los precios antes de consultar.
foreach ([$precio_minimo, $precio_maximo] as $precio) {
    if (
        $precio !== ''
        && (
            !is_numeric($precio)
            || (float) $precio < 0
            || (float) $precio > 99999999.99
        )
    ) {
        $errores[] = 'Los precios deben estar entre 0 y 99,999,999.99.';
        break;
    }
}

if (
    empty($errores)
    && $precio_minimo !== ''
    && $precio_maximo !== ''
    && (float) $precio_minimo > (float) $precio_maximo
) {
    $errores[] = 'El precio mínimo no puede superar al precio máximo.';
}

// 3. Preparar la consulta de alojamientos publicados.
$sql = "
    SELECT
        a.id_alojamiento,
        a.titulo,
        a.municipio,
        a.departamento,
        a.capacidad,
        a.precio_persona_noche,
        a.latitud,
        a.longitud,
        u.nombre AS anfitrion_nombre,
        f.ruta_archivo AS foto_principal
    FROM alojamientos AS a
    INNER JOIN usuarios AS u
        ON a.id_anfitrion = u.id_usuario
    LEFT JOIN fotos_alojamiento AS f
        ON a.id_alojamiento = f.id_alojamiento
        AND f.posicion = 1
    WHERE a.estado = 'publicado'
";

$params = [];
$types = '';

// Agregar los filtros que el visitante haya completado.
if ($casa_titulo !== '') {
    $sql .= " AND a.titulo LIKE ?";
    $params[] = '%' . $casa_titulo . '%';
    $types .= 's';
}

if ($precio_minimo !== '') {
    $sql .= " AND a.precio_persona_noche >= ?";
    $params[] = $precio_minimo;
    $types .= 'd';
}

if ($precio_maximo !== '') {
    $sql .= " AND a.precio_persona_noche <= ?";
    $params[] = $precio_maximo;
    $types .= 'd';
}

// Elegir un orden permitido.
if ($orden === 'precio_asc') {
    $sql .= " ORDER BY a.precio_persona_noche ASC, a.id_alojamiento DESC";
} elseif ($orden === 'precio_desc') {
    $sql .= " ORDER BY a.precio_persona_noche DESC, a.id_alojamiento DESC";
} else {
    $sql .= " ORDER BY a.creado_en DESC, a.id_alojamiento DESC";
}

// Ejecutar solamente si los filtros son válidos.
if (empty($errores)) {
    $stmt = $conexion->prepare($sql);

    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $casas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$hayPaginaDetalle = is_file(__DIR__ . '/listing.php');
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>DaVinci | Alojamientos</title>

    <!-- Bootstrap y estilos del proyecto -->
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/inicio.css">
    <link rel="stylesheet" href="assets/css/menu.css">

    <!-- Estilos necesarios para mostrar el mapa -->
    <link rel="stylesheet"
          href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
</head>

<body id="inicio">

    <?php require __DIR__ . '/includes/menu.php'; ?>

    <main>

        <!-- Bienvenida -->
        <section class="portada py-5">
            <div class="container py-4">
                <p class="text-uppercase fw-semibold mb-2">
                    Alojamientos en El Salvador
                </p>

                <h1 class="display-5 fw-bold">
                    Encuentra dónde quedarte
                </h1>

                <p class="lead">
                    Explora casas, apartamentos y habitaciones
                    para tu próxima estancia.
                </p>

                <a class="btn btn-light btn-lg" href="#alojamientos">
                    Explorar alojamientos
                </a>
            </div>
        </section>

        <!-- Filtros y tarjetas -->
        <section class="container py-5" id="alojamientos">

            <h2 class="mb-3">Alojamientos publicados</h2>

            <p class="text-muted">
                Los filtros de precio corresponden a la tarifa
                por persona y noche.
            </p>

            <form action="index.php#alojamientos"
                  method="get"
                  class="card shadow-sm border-0 rounded-4 p-3 mb-4">

                <div class="row g-3 align-items-end">

                    <div class="col-12 col-md-4">
                        <label for="casa_titulo" class="form-label">
                            Buscar por nombre
                        </label>

                        <input
                            type="text"
                            id="casa_titulo"
                            name="casa_titulo"
                            class="form-control"
                            placeholder="Ejemplo: Zona Rosa"
                            value="<?= htmlspecialchars($casa_titulo, ENT_QUOTES, 'UTF-8') ?>"
                        >
                    </div>

                    <div class="col-6 col-md-2">
                        <label for="precio_minimo" class="form-label">
                            Precio mínimo
                        </label>

                        <input
                            type="number"
                            id="precio_minimo"
                            name="precio_minimo"
                            class="form-control"
                            min="0"
                            max="99999999.99"
                            step="0.01"
                            placeholder="$0"
                            value="<?= htmlspecialchars($precio_minimo, ENT_QUOTES, 'UTF-8') ?>"
                        >
                    </div>

                    <div class="col-6 col-md-2">
                        <label for="precio_maximo" class="form-label">
                            Precio máximo
                        </label>

                        <input
                            type="number"
                            id="precio_maximo"
                            name="precio_maximo"
                            class="form-control"
                            min="0"
                            max="99999999.99"
                            step="0.01"
                            placeholder="$500"
                            value="<?= htmlspecialchars($precio_maximo, ENT_QUOTES, 'UTF-8') ?>"
                        >
                    </div>

                    <div class="col-12 col-md-4">
                        <label for="orden" class="form-label">
                            Ordenar por
                        </label>

                        <select name="orden" id="orden" class="form-select">
                            <option value="">Más recientes</option>

                            <option value="precio_asc"
                                <?= $orden === 'precio_asc' ? 'selected' : '' ?>>
                                Precio: menor a mayor
                            </option>

                            <option value="precio_desc"
                                <?= $orden === 'precio_desc' ? 'selected' : '' ?>>
                                Precio: mayor a menor
                            </option>
                        </select>
                    </div>

                    <div class="col-12 d-flex gap-2">
                        <button type="submit" class="btn btn-success">
                            Buscar
                        </button>

                        <a href="index.php#alojamientos"
                           class="btn btn-outline-secondary">
                            Limpiar filtros
                        </a>
                    </div>

                </div>
            </form>

            <?php foreach ($errores as $error): ?>
                <p class="alert alert-danger" role="alert">
                    <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                </p>
            <?php endforeach; ?>

            <?php if (empty($errores)): ?>

                <p class="text-muted">
                    Resultados encontrados: <?= count($casas) ?>
                </p>

                <?php if (empty($casas)): ?>
                    <p class="alert alert-light border">
                        No se encontraron alojamientos con esos filtros.
                    </p>
                <?php else: ?>

                    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">

                        <?php foreach ($casas as $alojamiento): ?>
                            <div class="col">
                                <article class="card h-100 shadow-sm overflow-hidden">

                                    <?php if (!empty($alojamiento['foto_principal'])): ?>
                                        <img
                                            src="<?= htmlspecialchars($alojamiento['foto_principal'], ENT_QUOTES, 'UTF-8') ?>"
                                            alt="Foto de <?= htmlspecialchars($alojamiento['titulo'], ENT_QUOTES, 'UTF-8') ?>"
                                            class="card-img-top object-fit-cover"
                                            style="height: 180px;"
                                            loading="lazy"
                                        >
                                    <?php else: ?>
                                        <div
                                            class="bg-light text-muted d-flex align-items-center justify-content-center"
                                            style="height: 180px;">
                                            Sin fotografía
                                        </div>
                                    <?php endif; ?>

                                    <div class="card-body d-flex flex-column">

                                        <h3 class="h5">
                                            <?= htmlspecialchars($alojamiento['titulo'], ENT_QUOTES, 'UTF-8') ?>
                                        </h3>

                                        <p class="text-muted mb-1">
                                            <?= htmlspecialchars($alojamiento['municipio'], ENT_QUOTES, 'UTF-8') ?>,
                                            <?= htmlspecialchars($alojamiento['departamento'], ENT_QUOTES, 'UTF-8') ?>
                                        </p>

                                        <p class="mb-1">
                                            Hasta <?= (int) $alojamiento['capacidad'] ?>
                                            huéspedes
                                        </p>

                                        <p class="small text-muted mb-3">
                                            Anfitrión:
                                            <?= htmlspecialchars($alojamiento['anfitrion_nombre'], ENT_QUOTES, 'UTF-8') ?>
                                        </p>

                                        <p class="mt-auto mb-0">
                                            <strong class="text-success">
                                                $<?= number_format((float) $alojamiento['precio_persona_noche'], 2) ?>
                                            </strong>

                                            <span class="small text-muted">
                                                por persona y noche
                                            </span>
                                        </p>

                                        <?php if ($hayPaginaDetalle): ?>
                                            <a
                                                class="btn btn-outline-success mt-3 align-self-start"
                                                href="listing.php?id=<?= (int) $alojamiento['id_alojamiento'] ?>">
                                                Ver detalle
                                            </a>
                                        <?php endif; ?>

                                    </div>
                                </article>
                            </div>
                        <?php endforeach; ?>

                    </div>

                <?php endif; ?>
            <?php endif; ?>
        </section>

        <!-- El mapa muestra los mismos alojamientos que las tarjetas -->
        <section class="container pb-5">
            <h2 class="mb-3">Ubicación de los alojamientos</h2>

            <div id="map"
                 class="rounded border"
                 style="height: 400px;"
                 aria-label="Mapa de alojamientos">
            </div>
        </section>

        <section class="container pb-5" id="como-funciona">
            <h2 class="mb-3">Cómo funciona</h2>

            <ol class="ps-3">
                <li class="mb-2">
                    Encuentra un alojamiento según su ubicación,
                    precio y capacidad.
                </li>
                <li class="mb-2">
                    Inicia sesión y envía una solicitud con tus
                    fechas y número de huéspedes.
                </li>
                <li>
                    Consulta el estado de tu solicitud cuando
                    el anfitrión responda.
                </li>
            </ol>
        </section>

        <section class="seccion-anfitriones py-5" id="anfitriones">
            <div class="container">
                <h2>¿Tienes un alojamiento?</h2>

                <p class="mb-0">
                    Como anfitrión podrás publicar tu espacio con fotos,
                    ubicación, precio y capacidad, y responder las
                    solicitudes recibidas.
                </p>
            </div>
        </section>

    </main>

    <footer class="container py-4 text-secondary">
        HALOJA · Proyecto de Programación Para Internet · <?= date('Y') ?>
    </footer>

    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

    <script>
        // PHP entrega a JavaScript los resultados de la consulta.
        const casas = <?= json_encode(
            $casas,
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        ) ?>;

        const mapa = L.map('map').setView([13.69, -89.23], 12);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
        }).addTo(mapa);

        const posiciones = [];

        casas.forEach(function (casa) {
            const latitud = Number(casa.latitud);
            const longitud = Number(casa.longitud);

            // Comprobar las coordenadas antes de crear el marcador.
            if (
                casa.latitud === null || casa.longitud === null ||
                !Number.isFinite(latitud) || !Number.isFinite(longitud) ||
                latitud < -90 || latitud > 90 ||
                longitud < -180 || longitud > 180
            ) {
                return;
            }

            const contenido = document.createElement('div');

            const titulo = document.createElement('strong');
            titulo.textContent = casa.titulo;

            const precio = document.createElement('p');
            precio.className = 'mb-0';
            precio.textContent =
                '$' + Number(casa.precio_persona_noche).toFixed(2) +
                ' por persona y noche';

            contenido.append(titulo, precio);

            L.marker([latitud, longitud])
                .addTo(mapa)
                .bindPopup(contenido);

            posiciones.push([latitud, longitud]);
        });

        // Ajustar el mapa para que se vean los marcadores encontrados.
        if (posiciones.length > 0) {
            mapa.fitBounds(posiciones, {
                padding: [30, 30],
                maxZoom: 14
            });
        }
    </script>

</body>
</html>
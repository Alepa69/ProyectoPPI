<?php
require_once __DIR__ . '/config/conexion.php';

$sql = "
    SELECT
        a.id_alojamiento,
        a.titulo,
        a.municipio,
        a.departamento,
        a.capacidad,
        a.precio_persona_noche,
        u.nombre AS anfitrion_nombre,
        f.ruta_archivo AS foto_principal
    FROM alojamientos AS a
    INNER JOIN usuarios AS u
        ON a.id_anfitrion = u.id_usuario
    LEFT JOIN fotos_alojamiento AS f
        ON a.id_alojamiento = f.id_alojamiento
        AND f.posicion = 1
    WHERE a.estado = 'publicado'
    ORDER BY a.creado_en DESC, a.id_alojamiento DESC
";

$resultado = $conexion->query($sql);

// Mostrar el enlace al detalle cuando ese archivo ya exista.
$hayPaginaDetalle = is_file(__DIR__ . '/listing.php');
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Davinci | Alojamientos</title>

    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/inicio.css">
</head>
<body id="inicio">

    <?php require __DIR__ . '/includes/menu.php'; ?>

    <main>
        <section class="portada py-5">
            <div class="container py-4">
                <p class="text-uppercase fw-semibold mb-2">
                    Alojamientos en El Salvador
                </p>
                <h1 class="display-5 fw-bold">Encuentra dónde quedarte</h1>
                <p class="lead">
                    Explora casas, apartamentos y habitaciones para tu próxima estancia.
                </p>
                <a class="btn btn-light btn-lg" href="#alojamientos">
                    Explorar alojamientos
                </a>
            </div>
        </section>

        <section class="container py-5" id="alojamientos">
            <h2 class="mb-3">Alojamientos publicados</h2>

            <?php if ($resultado->num_rows === 0): ?>
                <p class="text-muted">Todavía no hay alojamientos publicados.</p>
            <?php else: ?>
                <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">

                    <?php while ($alojamiento = $resultado->fetch_assoc()): ?>
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
                                    <div class="bg-light text-muted d-flex align-items-center justify-content-center"
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
                                        Hasta <?= (int) $alojamiento['capacidad'] ?> huéspedes
                                    </p>

                                    <p class="small text-muted mb-3">
                                        Anfitrión:
                                        <?= htmlspecialchars($alojamiento['anfitrion_nombre'], ENT_QUOTES, 'UTF-8') ?>
                                    </p>

                                    <p class="mt-auto mb-0">
                                        <strong class="text-success">
                                            $<?= number_format((float) $alojamiento['precio_persona_noche'], 2) ?>
                                        </strong>
                                        <span class="small text-muted">por persona y noche</span>
                                    </p>

                                    <?php if ($hayPaginaDetalle): ?>
                                        <a class="btn btn-outline-success mt-3 align-self-start"
                                           href="listing.php?id=<?= (int) $alojamiento['id_alojamiento'] ?>">
                                            Ver detalle
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </article>
                        </div>
                    <?php endwhile; ?>

                </div>
            <?php endif; ?>
        </section>

        <section class="container pb-5" id="como-funciona">
            <h2 class="mb-3">Cómo funciona</h2>
            <ol class="ps-3">
                <li class="mb-2">Encuentra un alojamiento según su ubicación, precio y capacidad.</li>
                <li class="mb-2">Inicia sesión y envía una solicitud con tus fechas y número de huéspedes.</li>
                <li>Consulta el estado de tu solicitud cuando el anfitrión responda.</li>
            </ol>
        </section>

        <section class="seccion-anfitriones py-5" id="anfitriones">
            <div class="container">
                <h2>¿Tienes un alojamiento?</h2>
                <p class="mb-0">
                    Como anfitrión podrás publicar tu espacio con fotos, ubicación,
                    precio y capacidad, y responder las solicitudes recibidas.
                </p>
            </div>
        </section>
    </main>

    <footer class="container py-4 text-secondary">
        Davinci · Proyecto de Programación Para Internet
    </footer>

    <script src="assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
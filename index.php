<?php
function escapar($texto) {
    return htmlspecialchars((string) $texto, ENT_QUOTES, 'UTF-8');
}

$alojamientos = [
    [
        'id' => 1,
        'nombre' => 'Casa Brisa del Mar',
        'tipo' => 'Casa',
        'ubicacion' => 'La Libertad',
        'precio' => 95,
        'capacidad' => 6,
        'habitaciones' => 3,
        'imagen' => 'https://images.unsplash.com/photo-1613490493576-7fde63acd811?auto=format&fit=crop&w=900&q=80'
    ],
    [
        'id' => 2,
        'nombre' => 'Apartamento Vista Urbana',
        'tipo' => 'Apartamento',
        'ubicacion' => 'San Salvador',
        'precio' => 55,
        'capacidad' => 3,
        'habitaciones' => 2,
        'imagen' => 'https://images.unsplash.com/photo-1522708323590-d24dbb6b0267?auto=format&fit=crop&w=900&q=80'
    ],
    [
        'id' => 3,
        'nombre' => 'Rancho Los Atardeceres',
        'tipo' => 'Rancho',
        'ubicacion' => 'Costa del Sol',
        'precio' => 140,
        'capacidad' => 10,
        'habitaciones' => 4,
        'imagen' => 'https://images.unsplash.com/photo-1600596542815-ffad4c1539a9?auto=format&fit=crop&w=900&q=80'
    ],
    [
        'id' => 4,
        'nombre' => 'Cabaña Entre Pinos',
        'tipo' => 'Cabaña',
        'ubicacion' => 'Chalatenango',
        'precio' => 70,
        'capacidad' => 4,
        'habitaciones' => 2,
        'imagen' => 'https://images.unsplash.com/photo-1449158743715-0a90ebb6d2d8?auto=format&fit=crop&w=900&q=80'
    ]
];

$categorias = [
    'Casa' => 'bi-house-door',
    'Apartamento' => 'bi-buildings',
    'Rancho' => 'bi-sun',
    'Cabaña' => 'bi-tree'
];

$destino = is_string($_GET['destino'] ?? null)
    ? trim($_GET['destino'])
    : '';

$tipo = is_string($_GET['tipo'] ?? null)
    ? $_GET['tipo']
    : '';

if (!array_key_exists($tipo, $categorias)) {
    $tipo = '';
}

$huespedes = filter_var(
    $_GET['huespedes'] ?? 1,
    FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1, 'max_range' => 20]]
);

if ($huespedes === false) {
    $huespedes = 1;
}

$resultados = array_filter(
    $alojamientos,
    function ($alojamiento) use ($destino, $tipo, $huespedes) {
        $coincideDestino = $destino === ''
            || stripos(
                $alojamiento['ubicacion'] . ' ' . $alojamiento['nombre'],
                $destino
            ) !== false;

        $coincideTipo = $tipo === ''
            || $alojamiento['tipo'] === $tipo;

        $coincideCapacidad = $alojamiento['capacidad'] >= $huespedes;

        return $coincideDestino && $coincideTipo && $coincideCapacidad;
    }
);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>HALOJA | Encuentra tu próxima estancia</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
        rel="stylesheet"
    >

    <style>
        :root {
            --principal: #183e3b;
            --principal-oscuro: #102d2a;
            --acento: #d6a66c;
            --fondo: #faf9f6;
            --texto: #243936;
        }

        body {
            background: var(--fondo);
            color: var(--texto);
            font-family: system-ui, sans-serif;
        }

        .navbar {
            background: var(--fondo);
            border-bottom: 1px solid #e7e8e2;
        }

        .navbar-brand {
            color: var(--principal);
            font-size: 1.8rem;
            font-weight: 800;
            letter-spacing: -1px;
        }

        .navbar-brand:hover {
            color: var(--principal);
        }

        .marca-icono {
            color: var(--acento);
        }

        .navbar .nav-link {
            color: #53645e;
            font-weight: 500;
        }

        .navbar .nav-link:hover {
            color: var(--principal);
        }

        .btn-principal {
            background: var(--principal);
            color: white;
            border: 1px solid var(--principal);
        }

        .btn-principal:hover,
        .btn-principal:focus-visible {
            background: var(--principal-oscuro);
            border-color: var(--principal-oscuro);
            color: white;
        }

        .hero {
            background: var(--principal);
            border-radius: 28px;
            overflow: hidden;
        }

        .hero-texto {
            padding: 3rem;
        }

        .hero h1 {
            color: white;
            font-size: clamp(2.3rem, 4.5vw, 4.2rem);
            font-weight: 750;
            line-height: 1.12;
            letter-spacing: -1.5px;
        }

        .hero h1 span {
            color: #e7be8e;
        }

        .hero p {
            color: #d3e1db;
            max-width: 430px;
            line-height: 1.8;
        }

        .hero-etiqueta {
            color: #ead0ae;
            font-size: .8rem;
            letter-spacing: 2px;
        }

        .hero-imagen {
            width: 100%;
            height: 100%;
            min-height: 420px;
            object-fit: cover;
        }

        .buscador {
            background: white;
            border: 1px solid #e7e8e2;
            border-radius: 20px;
            box-shadow: 0 12px 35px rgba(24, 62, 59, .06);
        }

        .form-label {
            font-size: .85rem;
            font-weight: 650;
        }

        .form-control,
        .form-select {
            padding: .8rem 1rem;
            border-color: #dde3dd;
            border-radius: 12px;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--principal);
            box-shadow: 0 0 0 .2rem rgba(24, 62, 59, .12);
        }

        .categoria {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 11px 19px;
            border: 1px solid #dce2db;
            border-radius: 30px;
            background: white;
            color: #53645e;
            text-decoration: none;
        }

        .categoria:hover,
        .categoria.activa {
            color: white;
            background: var(--principal);
            border-color: var(--principal);
        }

        .alojamiento {
            background: white;
            border: 1px solid #e7e8e2;
            border-radius: 20px;
            overflow: hidden;
            transition: transform .2s, box-shadow .2s;
        }

        .alojamiento:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(24, 62, 59, .08);
        }

        .alojamiento-imagen {
            width: 100%;
            height: 225px;
            object-fit: cover;
        }

        .etiqueta-tipo {
            position: absolute;
            top: 15px;
            left: 15px;
            padding: 7px 12px;
            border-radius: 25px;
            background: white;
            color: var(--principal);
            font-size: .75rem;
            font-weight: 700;
        }

        .ubicacion,
        .caracteristicas {
            color: #64756d;
            font-size: .87rem;
        }

        .precio {
            font-size: 1.2rem;
            font-weight: 750;
            color: var(--principal);
        }

        .seccion-anfitrion {
            background: #eee9df;
            border-radius: 24px;
        }

        footer {
            border-top: 1px solid #e1e5dd;
            color: #64756d;
        }

        @media (max-width: 767px) {
            .hero-texto {
                padding: 2rem;
            }

            .hero-imagen {
                min-height: 240px;
                max-height: 280px;
            }
        }
    </style>
</head>

<body>
    <!-- MENÚ PRINCIPAL -->
    <nav class="navbar navbar-expand-lg py-3" aria-label="Menú principal">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <i
                    class="bi bi-compass marca-icono me-1"
                    aria-hidden="true"
                ></i>
                HALOJA
            </a>

            <button
                class="navbar-toggler"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#menuPrincipal"
                aria-controls="menuPrincipal"
                aria-expanded="false"
                aria-label="Abrir menú"
            >
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="menuPrincipal">
                <ul class="navbar-nav mx-auto gap-lg-3">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php" aria-current="page">
                            Inicio
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link" href="#alojamientos">
                            Explorar alojamientos
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link" href="mis-reservas.php">
                            Mis reservas
                        </a>
                    </li>
                </ul>

                <div class="d-flex flex-wrap gap-2 mt-3 mt-lg-0">
                    <a
                        href="publicar.php"
                        class="btn btn-outline-dark rounded-pill px-3"
                    >
                        Publicar alojamiento
                    </a>

                    <a
                        href="login.php"
                        class="btn btn-principal rounded-pill px-3"
                    >
                        <i class="bi bi-person me-1" aria-hidden="true"></i>
                        Ingresar
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <main class="container py-4 py-lg-5">
        <!-- PRESENTACIÓN -->
        <section class="hero mb-4">
            <div class="row g-0 align-items-stretch">
                <div class="col-lg-6">
                    <div class="hero-texto">
                        <div class="hero-etiqueta mb-3">
                            LUGARES PARA DESCONECTAR
                        </div>

                        <h1 class="mb-4">
                            Tu próxima historia<br>
                            empieza <span>aquí.</span>
                        </h1>

                        <p class="mb-4">
                            Descubre casas, apartamentos y ranchos
                            para tus vacaciones, escapadas o días
                            de descanso.
                        </p>

                        <a
                            href="#buscador"
                            class="btn btn-light rounded-pill px-4 py-3 fw-semibold"
                        >
                            Encuentra tu estancia
                            <i
                                class="bi bi-arrow-up-right ms-2"
                                aria-hidden="true"
                            ></i>
                        </a>
                    </div>
                </div>

                <div class="col-lg-6">
                    <img
                        src="https://images.unsplash.com/photo-1613490493576-7fde63acd811?auto=format&fit=crop&w=1200&q=85"
                        alt="Casa con piscina rodeada de vegetación"
                        class="hero-imagen"
                        fetchpriority="high"
                    >
                </div>
            </div>
        </section>

        <!-- BUSCADOR -->
        <section id="buscador" class="buscador p-4 mb-5">
            <form method="GET" action="index.php#alojamientos">
                <div class="row g-3 align-items-end">
                    <div class="col-md-6 col-lg-4">
                        <label for="destino" class="form-label">
                            <i class="bi bi-geo-alt me-1" aria-hidden="true"></i>
                            ¿A dónde quieres ir?
                        </label>

                        <input
                            type="search"
                            id="destino"
                            name="destino"
                            class="form-control"
                            placeholder="Ciudad, zona o alojamiento"
                            value="<?= escapar($destino) ?>"
                        >
                    </div>

                    <div class="col-md-6 col-lg-3">
                        <label for="tipo" class="form-label">
                            Tipo de alojamiento
                        </label>

                        <select id="tipo" name="tipo" class="form-select">
                            <option value="">Todos los tipos</option>

                            <?php foreach ($categorias as $categoria => $icono): ?>
                                <option
                                    value="<?= escapar($categoria) ?>"
                                    <?= $tipo === $categoria ? 'selected' : '' ?>
                                >
                                    <?= escapar($categoria) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6 col-lg-2">
                        <label for="huespedes" class="form-label">
                            Huéspedes
                        </label>

                        <input
                            type="number"
                            id="huespedes"
                            name="huespedes"
                            class="form-control"
                            min="1"
                            max="20"
                            value="<?= escapar($huespedes) ?>"
                            required
                        >
                    </div>

                    <div class="col-md-6 col-lg-3">
                        <button
                            type="submit"
                            class="btn btn-principal w-100 rounded-3 py-3"
                        >
                            <i class="bi bi-search me-2" aria-hidden="true"></i>
                            Buscar alojamiento
                        </button>
                    </div>
                </div>
            </form>
        </section>

        <!-- CATEGORÍAS -->
        <div class="d-flex flex-wrap gap-2 mb-4">
            <?php
            $filtrosBase = [
                'destino' => $destino,
                'huespedes' => $huespedes
            ];
            ?>

            <a
                href="index.php?<?= escapar(http_build_query($filtrosBase)) ?>#alojamientos"
                class="categoria <?= $tipo === '' ? 'activa' : '' ?>"
            >
                <i class="bi bi-grid" aria-hidden="true"></i>
                Todos
            </a>

            <?php foreach ($categorias as $categoria => $icono): ?>
                <?php
                $parametros = http_build_query(
                    array_merge($filtrosBase, ['tipo' => $categoria])
                );
                ?>

                <a
                    href="index.php?<?= escapar($parametros) ?>#alojamientos"
                    class="categoria <?= $tipo === $categoria ? 'activa' : '' ?>"
                >
                    <i
                        class="bi <?= escapar($icono) ?>"
                        aria-hidden="true"
                    ></i>
                    <?= escapar($categoria) ?>
                </a>
            <?php endforeach; ?>
        </div>

        <!-- ALOJAMIENTOS -->
        <section id="alojamientos" class="mb-5">
            <div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-4">
                <div>
                    <h2 class="fw-bold mb-2">Encuentra tu lugar ideal</h2>

                    <p class="text-secondary mb-0">
                        Espacios para descansar, compartir y descubrir.
                    </p>
                </div>

                <span class="small text-secondary">
                    <?= count($resultados) ?> alojamientos encontrados
                </span>
            </div>

            <div class="row g-4">
                <?php foreach ($resultados as $alojamiento): ?>
                    <div class="col-md-6 col-xl-3">
                        <article class="alojamiento h-100 d-flex flex-column">
                            <div class="position-relative">
                                <img
                                    src="<?= escapar($alojamiento['imagen']) ?>"
                                    alt="<?= escapar($alojamiento['nombre']) ?>"
                                    class="alojamiento-imagen"
                                    loading="lazy"
                                >

                                <span class="etiqueta-tipo">
                                    <?= escapar($alojamiento['tipo']) ?>
                                </span>
                            </div>

                            <div class="p-3 d-flex flex-column flex-grow-1">
                                <div class="ubicacion mb-2">
                                    <i
                                        class="bi bi-geo-alt me-1"
                                        aria-hidden="true"
                                    ></i>
                                    <?= escapar($alojamiento['ubicacion']) ?>
                                </div>

                                <h3 class="h6 fw-bold mb-3">
                                    <?= escapar($alojamiento['nombre']) ?>
                                </h3>

                                <div class="caracteristicas d-flex flex-wrap gap-3 mb-4">
                                    <span>
                                        <i
                                            class="bi bi-people me-1"
                                            aria-hidden="true"
                                        ></i>
                                        <?= escapar($alojamiento['capacidad']) ?>
                                        huéspedes
                                    </span>

                                    <span>
                                        <i
                                            class="bi bi-door-open me-1"
                                            aria-hidden="true"
                                        ></i>
                                        <?= escapar($alojamiento['habitaciones']) ?>
                                        hab.
                                    </span>
                                </div>

                                <div class="mt-auto pt-3 border-top">
                                    <div class="mb-3">
                                        <span class="precio">
                                            $<?= number_format($alojamiento['precio'], 2) ?>
                                        </span>

                                        <span class="small text-secondary">
                                            / noche
                                        </span>
                                    </div>

                                    <a
                                        href="detalle.php?id=<?= (int) $alojamiento['id'] ?>"
                                        class="btn btn-principal w-100 rounded-pill"
                                        aria-label="Ver <?= escapar($alojamiento['nombre']) ?>"
                                    >
                                        Ver alojamiento
                                    </a>
                                </div>
                            </div>
                        </article>
                    </div>
                <?php endforeach; ?>

                <?php if (count($resultados) === 0): ?>
                    <div class="col-12">
                        <div class="bg-white border rounded-4 p-5 text-center">
                            <i
                                class="bi bi-search fs-1 text-secondary"
                                aria-hidden="true"
                            ></i>

                            <h3 class="h5 mt-3">
                                No encontramos alojamientos
                            </h3>

                            <p class="text-secondary">
                                Prueba con otro destino, tipo o cantidad de huéspedes.
                            </p>

                            <a href="index.php#alojamientos" class="btn btn-principal">
                                Limpiar filtros
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- PUBLICAR ALOJAMIENTO -->
        <section class="seccion-anfitrion p-4 p-md-5">
            <div class="row align-items-center g-4">
                <div class="col-md-8">
                    <h2 class="h3 fw-bold">
                        Tu espacio puede ser su próxima escapada
                    </h2>

                    <p class="text-secondary mb-0">
                        Publica tu casa, apartamento o rancho y
                        recibe solicitudes de personas que buscan
                        un lugar como el tuyo.
                    </p>
                </div>

                <div class="col-md-4 text-md-end">
                    <a
                        href="publicar.php"
                        class="btn btn-principal rounded-pill px-4 py-3"
                    >
                        Publicar mi alojamiento
                        <i
                            class="bi bi-arrow-right ms-2"
                            aria-hidden="true"
                        ></i>
                    </a>
                </div>
            </div>
        </section>
    </main>

    <footer class="py-4">
        <div class="container d-flex flex-wrap justify-content-between gap-3">
            <span class="small">
                &copy; <?= date('Y') ?> HALOJA Alojamientos.
            </span>

            <div class="d-flex flex-wrap gap-3 small">
                <a href="terminos.php" class="text-secondary text-decoration-none">
                    Términos y condiciones
                </a>

                <a href="privacidad.php" class="text-secondary text-decoration-none">
                    Privacidad
                </a>
            </div>
        </div>
    </footer>

    <script
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
    ></script>
</body>
</html>
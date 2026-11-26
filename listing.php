<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/config/conexion.php';

date_default_timezone_set('America/El_Salvador');

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id || $id <= 0) {
    http_response_code(404);
    exit('Alojamiento no encontrado.');
}

function escapar($texto)
{
    return htmlspecialchars((string) $texto, ENT_QUOTES, 'UTF-8');
}

// Verificar si el usuario actual es administrador activo
$idUsuario = (int) ($_SESSION['id_usuario'] ?? 0);
$esAdmin = false;

if ($idUsuario > 0) {
    $stmtRol = $conexion->prepare("SELECT rol, estado FROM usuarios WHERE id_usuario = ? LIMIT 1");
    $stmtRol->bind_param('i', $idUsuario);
    $stmtRol->execute();
    $datosRol = $stmtRol->get_result()->fetch_assoc();
    $stmtRol->close();

    $esAdmin = $datosRol && $datosRol['rol'] === 'administrador' && $datosRol['estado'] === 'activo';
}

$adminFlash = null;
if ($esAdmin) {
    if (empty($_SESSION['csrf_admin'])) {
        $_SESSION['csrf_admin'] = bin2hex(random_bytes(32));
    }
    if (!empty($_SESSION['admin_flash'])) {
        $adminFlash = $_SESSION['admin_flash'];
        unset($_SESSION['admin_flash']);
    }
}

// El administrador puede ver publicaciones en cualquier estado; los demás solo publicadas de anfitriones activos
$filtroEstado = $esAdmin ? '' : " AND a.estado = 'publicado' AND u.estado = 'activo'";

$consulta = $conexion->prepare("
    SELECT
        a.id_alojamiento, a.titulo, a.descripcion, a.pais,
        a.departamento, a.municipio, a.ciudad, a.direccion, a.referencias,
        a.latitud, a.longitud, a.tipo_espacio, a.capacidad,
        a.capacidad_personas, a.num_habitaciones, a.num_camas, a.num_banos,
        a.hora_checkin, a.hora_checkout, a.estancia_minima,
        a.permite_mascotas, a.permite_fumar, a.permite_eventos,
        a.reglas_adicionales, a.precio_persona_noche, a.precio_noche,
        a.tarifa_limpieza, a.id_anfitrion, a.estado, a.motivo_bloqueo,
        u.nombre AS nombre_anfitrion, u.apellido AS apellido_anfitrion,
        u.foto_perfil AS foto_anfitrion, u.creado_en AS anfitrion_desde,
        t.nombre AS tipo_alojamiento
    FROM alojamientos AS a
    INNER JOIN usuarios AS u ON u.id_usuario = a.id_anfitrion
    LEFT JOIN tipos_alojamiento AS t ON t.id_tipo = a.id_tipo
    WHERE a.id_alojamiento = ?{$filtroEstado}
    LIMIT 1
");
$consulta->bind_param('i', $id);
$consulta->execute();
$casa = $consulta->get_result()->fetch_assoc();
$consulta->close();

if (!$casa) {
    http_response_code(404);
    exit('Este alojamiento no está publicado o no existe.');
}

// Cargar fotos (tabla nueva con respaldo de tabla anterior)
$fotos = [];
$consultaFotos = $conexion->prepare("
    SELECT ruta_o_url AS ruta
    FROM alojamiento_imagenes
    WHERE alojamiento_id = ?
    ORDER BY es_portada DESC, orden ASC, id ASC
");
$consultaFotos->bind_param('i', $id);
$consultaFotos->execute();
$resFotos = $consultaFotos->get_result();
while ($foto = $resFotos->fetch_assoc()) {
    $ruta = trim((string) ($foto['ruta'] ?? ''));
    if ($ruta !== '' && !in_array($ruta, $fotos, true)) {
        $fotos[] = $ruta;
    }
}
$consultaFotos->close();

if (!$fotos) {
    $consultaLegacy = $conexion->prepare("
        SELECT ruta_archivo AS ruta
        FROM fotos_alojamiento
        WHERE id_alojamiento = ?
        ORDER BY posicion ASC
    ");
    $consultaLegacy->bind_param('i', $id);
    $consultaLegacy->execute();
    $resLegacy = $consultaLegacy->get_result();
    while ($foto = $resLegacy->fetch_assoc()) {
        $ruta = trim((string) ($foto['ruta'] ?? ''));
        if ($ruta !== '' && !in_array($ruta, $fotos, true)) {
            $fotos[] = $ruta;
        }
    }
    $consultaLegacy->close();
}

// Cargar amenidades
$amenidades = [];
$consultaAmenidades = $conexion->prepare("
    SELECT am.nombre, am.categoria
    FROM alojamiento_amenidades AS aa
    INNER JOIN amenidades AS am ON am.id = aa.amenidad_id
    WHERE aa.alojamiento_id = ?
    ORDER BY am.categoria ASC, am.nombre ASC
");
$consultaAmenidades->bind_param('i', $id);
$consultaAmenidades->execute();
$amenidades = $consultaAmenidades->get_result()->fetch_all(MYSQLI_ASSOC);
$consultaAmenidades->close();

// Cargar métodos de pago informativos aceptados
$metodosPago = [];
$consultaMetodos = $conexion->prepare("
    SELECT mp.nombre
    FROM alojamiento_metodo_pago AS amp
    INNER JOIN metodos_pago AS mp ON mp.id_metodo_pago = amp.id_metodo_pago
    WHERE amp.id_alojamiento = ?
    ORDER BY mp.id_metodo_pago ASC
");
$consultaMetodos->bind_param('i', $id);
$consultaMetodos->execute();
$metodosPago = $consultaMetodos->get_result()->fetch_all(MYSQLI_ASSOC);
$consultaMetodos->close();

$esAnfitrion = $idUsuario === (int) $casa['id_anfitrion'];
$zonaSV = new DateTimeZone('America/El_Salvador');
$ahora = new DateTimeImmutable('now', $zonaSV);
$hoy = new DateTimeImmutable('today', $zonaSV);
$error = '';
$confirmacion = $_SESSION['confirmacion_reserva'][$id] ?? '';
unset($_SESSION['confirmacion_reserva'][$id]);

$nombreAnfitrion = trim($casa['nombre_anfitrion'] . ' ' . $casa['apellido_anfitrion']);
$iniciales = mb_strtoupper(
    mb_substr($casa['nombre_anfitrion'], 0, 1, 'UTF-8')
    . mb_substr($casa['apellido_anfitrion'], 0, 1, 'UTF-8'),
    'UTF-8'
);
$ciudad = trim((string) ($casa['ciudad'] ?: $casa['municipio']));
$ubicacionTexto = implode(', ', array_filter([
    $ciudad,
    $casa['departamento'],
    $casa['pais'],
]));
$hayCoordenadas = $casa['latitud'] !== null && $casa['longitud'] !== null;
$consultaMapa = $hayCoordenadas
    ? $casa['latitud'] . ',' . $casa['longitud']
    : implode(', ', array_filter([
        $casa['direccion'], $ciudad, $casa['departamento'], $casa['pais'],
    ]));
$mapaEmbed = 'https://www.google.com/maps?q=' . rawurlencode($consultaMapa) . '&output=embed';
$mapaEnlace = 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($consultaMapa);
$fechaAnfitrion = !empty($casa['anfitrion_desde'])
    ? date('d/m/Y', strtotime($casa['anfitrion_desde']))
    : 'No disponible';

$tipoEspacio = [
    'alojamiento_entero' => 'Alojamiento completo',
    'habitacion_privada' => 'Habitación privada',
    'habitacion_compartida' => 'Habitación compartida',
][$casa['tipo_espacio']] ?? 'Alojamiento';

$precioPorPersonaNoche = (float) $casa['precio_persona_noche'];
$tarifaLimpieza = (float) $casa['tarifa_limpieza'];
$totalFotos = count($fotos);
$fotosVisibles = min($totalFotos, 5);

if (empty($_SESSION['csrf_reserva'])) {
    $_SESSION['csrf_reserva'] = bin2hex(random_bytes(32));
}

// Procesar solicitud de reserva (en pasos claros)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $entradaStr = trim((string) ($_POST['entrada'] ?? ''));
    $salidaStr = trim((string) ($_POST['salida'] ?? ''));
    $huespedes = (int) ($_POST['huespedes'] ?? 0);
    $token = (string) ($_POST['csrf'] ?? '');

    $entrada = DateTimeImmutable::createFromFormat('!Y-m-d', $entradaStr, $zonaSV);
    $salida = DateTimeImmutable::createFromFormat('!Y-m-d', $salidaStr, $zonaSV);
    $fechasValidas = $entrada && $salida && $entrada->format('Y-m-d') === $entradaStr && $salida->format('Y-m-d') === $salidaStr;

    // Verificar que el usuario esté activo
    $usuarioActivo = false;
    if ($idUsuario > 0) {
        $stmtU = $conexion->prepare("SELECT estado FROM usuarios WHERE id_usuario = ? LIMIT 1");
        $stmtU->bind_param('i', $idUsuario);
        $stmtU->execute();
        $uFila = $stmtU->get_result()->fetch_assoc();
        $stmtU->close();
        $usuarioActivo = $uFila && $uFila['estado'] === 'activo';
    }

    if (!$idUsuario || !$usuarioActivo || $esAnfitrion) {
        $error = 'Inicia sesión con una cuenta activa distinta a la del anfitrión.';
    } elseif ($casa['estado'] !== 'publicado') {
        $error = 'Este alojamiento no está disponible para reservas en este momento.';
    } elseif (!hash_equals($_SESSION['csrf_reserva'], $token)) {
        $error = 'Recarga la página y vuelve a intentarlo.';
    } elseif (!$fechasValidas || $entrada < $hoy || $salida <= $entrada) {
        $error = 'Elige una fecha de entrada desde hoy y una salida posterior.';
    } elseif ($entradaStr === $hoy->format('Y-m-d') && $ahora >= new DateTimeImmutable($entradaStr . ' ' . ($casa['hora_checkin'] ?: '15:00:00'), $zonaSV)) {
        $error = 'La hora de entrada para hoy ya pasó. Elige a partir de mañana.';
    } elseif ($huespedes < 1 || $huespedes > (int) $casa['capacidad']) {
        $error = 'La cantidad de huéspedes debe estar entre 1 y ' . (int) $casa['capacidad'] . '.';
    } else {
        $noches = (int) $entrada->diff($salida)->days;
        if ($noches < (int) $casa['estancia_minima']) {
            $error = 'Este alojamiento requiere una estancia mínima de ' . (int) $casa['estancia_minima'] . ' noche(s).';
        } else {
            // 1. Verificar que no haya una reserva aceptada que se cruce
            $stmtCruce = $conexion->prepare("
                SELECT id_reserva
                FROM reservas
                WHERE id_alojamiento = ?
                  AND estado = 'aceptada'
                  AND fecha_entrada < ?
                  AND fecha_salida > ?
                LIMIT 1
            ");
            $stmtCruce->bind_param('iss', $id, $salidaStr, $entradaStr);
            $stmtCruce->execute();
            $hayCruce = $stmtCruce->get_result()->num_rows > 0;
            $stmtCruce->close();

            if ($hayCruce) {
                $error = 'Esas fechas ya están ocupadas por otra reserva aceptada.';
            } else {
                // 2. Calcular total desde el precio guardado en la base de datos
                $total = round($noches * $huespedes * $precioPorPersonaNoche, 2);

                // 3. Guardar solicitud en estado pendiente
                $guardar = $conexion->prepare("
                    INSERT INTO reservas (
                        id_alojamiento, id_solicitante, fecha_entrada, fecha_salida,
                        cantidad_huespedes, precio_persona_noche_aplicado, total, estado
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, 'pendiente')
                ");
                $guardar->bind_param('iissidd', $id, $idUsuario, $entradaStr, $salidaStr, $huespedes, $precioPorPersonaNoche, $total);

                if ($guardar->execute() && $guardar->affected_rows === 1) {
                    $_SESSION['confirmacion_reserva'][$id] =
                        'Solicitud enviada por $' . number_format($total, 2) . '. Quedó pendiente de respuesta del anfitrión.';
                    header('Location: listing.php?id=' . $id, true, 303);
                    exit;
                }

                $error = 'No se pudo enviar la solicitud. Intenta de nuevo.';
            }
        }
    }
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= escapar($casa['titulo']) ?> | HALOJA</title>
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/inicio.css">
    <link rel="stylesheet" href="assets/css/tema-haloja.css">
    <link rel="stylesheet" href="assets/css/listing.css">
</head>
<body>
<main class="listing-page">
    <div class="container listing-container">
        <div class="listing-topbar">
            <a href="index.php#alojamientos" class="listing-back-link">
                <span aria-hidden="true">←</span> Volver a alojamientos
            </a>

            <div class="d-flex flex-wrap align-items-center gap-2">
                <?php if ($esAnfitrion): ?>
                    <a href="editar_casa.php?id=<?= (int) $id ?>" class="btn btn-outline-success btn-sm">Editar publicación</a>
                <?php endif; ?>

                <?php if ($esAdmin): ?>
                    <a href="admin.php?tab=alojamientos" class="btn btn-outline-dark btn-sm">Panel Admin</a>
                    <span class="badge <?= $casa['estado'] === 'publicado' ? 'bg-success' : 'bg-danger' ?>">
                        Estado: <?= escapar(ucfirst($casa['estado'])) ?>
                    </span>

                    <form method="post" action="admin_acciones.php" class="d-inline-flex gap-2">
                        <input type="hidden" name="csrf" value="<?= escapar($_SESSION['csrf_admin']) ?>">
                        <input type="hidden" name="tipo" value="alojamiento">
                        <input type="hidden" name="id" value="<?= (int) $id ?>">
                        <input type="hidden" name="volver_a" value="listing.php?id=<?= (int) $id ?>">

                        <?php if ($casa['estado'] === 'publicado'): ?>
                            <button type="button" class="btn btn-warning btn-sm" onclick="bloquearPublicacion(this.form)">
                                Desactivar publicación
                            </button>
                        <?php else: ?>
                            <button type="submit" name="accion" value="activar" class="btn btn-success btn-sm">
                                Reactivar publicación
                            </button>
                        <?php endif; ?>

                        <button type="submit" name="accion" value="eliminar" class="btn btn-danger btn-sm"
                                onclick="return confirm('¿Eliminar permanentemente esta publicación y sus reservas asociadas?')">
                            Borrar publicación
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($adminFlash): ?>
            <div class="alert alert-<?= escapar($adminFlash['tipo']) ?> alert-dismissible fade show mt-3" role="alert">
                <?= escapar($adminFlash['mensaje']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
            </div>
        <?php endif; ?>

        <?php if ($esAdmin && $casa['estado'] !== 'publicado'): ?>
            <div class="alert alert-warning mt-3" role="alert">
                <strong>Vista de administrador:</strong> Esta publicación tiene estado <strong><?= escapar($casa['estado']) ?></strong> y está oculta para los demás usuarios.
                <?php if (!empty($casa['motivo_bloqueo'])): ?>
                    <br><span>Motivo registrado: <?= escapar($casa['motivo_bloqueo']) ?></span>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <header class="listing-heading">
            <div>
                <p class="listing-eyebrow">HALOJA · ALOJAMIENTOS</p>
                <h1><?= escapar($casa['titulo']) ?></h1>
                <p class="listing-location-line">
                    <span aria-hidden="true">⌖</span>
                    <?= escapar($ubicacionTexto) ?>
                    <span class="listing-heading-dot">·</span>
                    <?= escapar($tipoEspacio) ?>
                </p>
            </div>
        </header>

        <?php if ($totalFotos > 0): ?>
            <section class="listing-gallery listing-gallery--<?= $totalFotos === 1 ? 'single' : ($totalFotos === 2 ? 'two' : ($totalFotos === 3 ? 'three' : 'multi')) ?>" aria-label="Galería de fotografías del alojamiento">
                <?php for ($i = 0; $i < $fotosVisibles; $i++): ?>
                    <button type="button" class="listing-gallery-photo listing-gallery-photo--<?= $i ?>"
                            data-gallery-index="<?= $i ?>" aria-label="Abrir fotografía <?= $i + 1 ?> de <?= $totalFotos ?>">
                        <img src="<?= escapar($fotos[$i]) ?>" alt="<?= escapar($casa['titulo']) ?> - fotografía <?= $i + 1 ?>"
                             <?= $i === 0 ? 'fetchpriority="high"' : 'loading="lazy"' ?>>
                        <?php if ($i === $fotosVisibles - 1 && $totalFotos > 1): ?>
                            <span class="listing-gallery-more">Ver las <?= $totalFotos ?> fotos</span>
                        <?php endif; ?>
                    </button>
                <?php endfor; ?>
            </section>
        <?php else: ?>
            <div class="listing-gallery-empty"><span aria-hidden="true">⌂</span><p>Este alojamiento todavía no tiene fotografías disponibles.</p></div>
        <?php endif; ?>

        <div class="row g-4 g-xl-5 listing-layout">
            <div class="col-lg-8 listing-main-column">
                <section class="listing-highlights" aria-label="Características principales">
                    <div class="listing-highlight"><span class="listing-highlight-icon" aria-hidden="true">♙</span><div><strong><?= (int) $casa['capacidad'] ?></strong><span>huéspedes</span></div></div>
                    <div class="listing-highlight"><span class="listing-highlight-icon" aria-hidden="true">▦</span><div><strong><?= (int) $casa['num_habitaciones'] ?></strong><span>habitaciones</span></div></div>
                    <div class="listing-highlight"><span class="listing-highlight-icon" aria-hidden="true">▤</span><div><strong><?= (int) $casa['num_camas'] ?></strong><span>camas</span></div></div>
                    <div class="listing-highlight"><span class="listing-highlight-icon" aria-hidden="true">◫</span><div><strong><?= escapar(number_format((float) $casa['num_banos'], 1)) ?></strong><span>baños</span></div></div>
                </section>

                <section class="listing-section listing-about">
                    <div class="listing-section-heading"><div><p class="listing-eyebrow">CONOCE EL LUGAR</p><h2>Acerca de este alojamiento</h2></div></div>
                    <p class="listing-description"><?= nl2br(escapar($casa['descripcion'])) ?></p>
                    <div class="listing-facts">
                        <div><span>Tipo de alojamiento</span><strong><?= escapar($casa['tipo_alojamiento'] ?: 'No especificado') ?></strong></div>
                        <div><span>Espacio</span><strong><?= escapar($tipoEspacio) ?></strong></div>
                        <div><span>Estancia mínima</span><strong><?= (int) $casa['estancia_minima'] ?> noche(s)</strong></div>
                    </div>
                </section>

                <section class="listing-section">
                    <div class="listing-section-heading"><div><p class="listing-eyebrow">COMODIDAD DURANTE TU ESTANCIA</p><h2>Lo que ofrece este lugar</h2></div></div>
                    <?php if ($amenidades): ?>
                        <div class="listing-amenities-grid">
                            <?php foreach ($amenidades as $amenidad): ?>
                                <div class="listing-amenity"><span class="listing-amenity-check" aria-hidden="true">✓</span><span><?= escapar($amenidad['nombre']) ?></span></div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="listing-muted-note">El anfitrión no ha agregado amenidades a esta publicación.</p>
                    <?php endif; ?>
                </section>

                <section class="listing-section">
                    <div class="listing-section-heading"><div><p class="listing-eyebrow">ACUERDO DIRECTO</p><h2>Métodos de pago informativos</h2></div></div>
                    <?php if ($metodosPago): ?>
                        <div class="d-flex flex-wrap gap-2">
                            <?php foreach ($metodosPago as $mp): ?>
                                <span class="badge bg-light text-dark border px-3 py-2">✓ <?= escapar($mp['nombre']) ?></span>
                            <?php endforeach; ?>
                        </div>
                        <p class="listing-muted-note mt-2">El pago se coordina directamente con el anfitrión fuera de la plataforma.</p>
                    <?php else: ?>
                        <p class="listing-muted-note">Consultar métodos de pago directamente con el anfitrión.</p>
                    <?php endif; ?>
                </section>

                <section class="listing-section listing-host-section">
                    <div class="listing-section-heading"><div><p class="listing-eyebrow">TU ANFITRIÓN</p><h2>Conoce a <?= escapar($casa['nombre_anfitrion']) ?></h2></div></div>
                    <a href="perfil_anfitrion.php?id=<?= (int) $casa['id_anfitrion'] ?>" class="listing-host-card text-decoration-none">
                        <?php if (!empty($casa['foto_anfitrion'])): ?>
                            <img src="<?= escapar($casa['foto_anfitrion']) ?>" alt="Foto de <?= escapar($nombreAnfitrion) ?>" class="rounded-circle object-fit-cover" width="58" height="58">
                        <?php else: ?>
                            <div class="listing-host-avatar" aria-hidden="true"><?= escapar($iniciales) ?></div>
                        <?php endif; ?>
                        <div class="listing-host-info">
                            <strong><?= escapar($nombreAnfitrion) ?></strong>
                            <span>Anfitrión en HALOJA</span>
                            <small>Cuenta creada el <?= escapar($fechaAnfitrion) ?></small>
                        </div>
                        <span class="listing-host-label">Ver perfil</span>
                    </a>
                </section>

                <section class="listing-section">
                    <div class="listing-section-heading"><div><p class="listing-eyebrow">ANTES DE RESERVAR</p><h2>Reglas de la casa</h2></div></div>
                    <div class="listing-rules-grid">
                        <div class="listing-rule"><span class="listing-rule-icon" aria-hidden="true">🐾</span><div><strong>Mascotas</strong><span><?= (int) $casa['permite_mascotas'] === 1 ? 'Permitidas' : 'No permitidas' ?></span></div></div>
                        <div class="listing-rule"><span class="listing-rule-icon" aria-hidden="true">🚭</span><div><strong>Fumar</strong><span><?= (int) $casa['permite_fumar'] === 1 ? 'Permitido' : 'No permitido' ?></span></div></div>
                        <div class="listing-rule"><span class="listing-rule-icon" aria-hidden="true">🎉</span><div><strong>Eventos</strong><span><?= (int) $casa['permite_eventos'] === 1 ? 'Permitidos' : 'No permitidos' ?></span></div></div>
                        <div class="listing-rule"><span class="listing-rule-icon" aria-hidden="true">🌙</span><div><strong>Estancia mínima</strong><span><?= (int) $casa['estancia_minima'] ?> noche(s)</span></div></div>
                    </div>
                    <div class="listing-schedule">
                        <div><span>Entrada (check-in)</span><strong><?= escapar(substr((string) $casa['hora_checkin'], 0, 5)) ?></strong></div>
                        <div><span>Salida (check-out)</span><strong><?= escapar(substr((string) $casa['hora_checkout'], 0, 5)) ?></strong></div>
                    </div>
                    <?php if (!empty($casa['reglas_adicionales'])): ?>
                        <div class="listing-extra-rules"><strong>Reglas adicionales del anfitrión</strong><p><?= nl2br(escapar($casa['reglas_adicionales'])) ?></p></div>
                    <?php endif; ?>
                </section>

                <section class="listing-section">
                    <div class="listing-section-heading"><div><p class="listing-eyebrow">DÓNDE TE ALOJARÁS</p><h2>Ubicación</h2><p class="listing-section-subtitle"><?= escapar($ubicacionTexto) ?></p></div></div>
                    <div class="listing-map-wrap">
                        <iframe src="<?= escapar($mapaEmbed) ?>" title="Mapa de ubicación de <?= escapar($casa['titulo']) ?>"
                                loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
                    </div>
                    <?php if (!empty($casa['referencias'])): ?>
                        <p class="listing-map-reference"><strong>Referencias:</strong> <?= escapar($casa['referencias']) ?></p>
                    <?php endif; ?>
                    <a class="listing-map-link" href="<?= escapar($mapaEnlace) ?>" target="_blank" rel="noopener noreferrer">Abrir ubicación en Google Maps <span aria-hidden="true">↗</span></a>
                </section>
            </div>

            <aside class="col-lg-4 listing-aside">
                <div class="listing-booking-card">
                    <div class="listing-price-block">
                        <div><strong>$<?= number_format($precioPorPersonaNoche, 2) ?></strong><span>por persona / noche</span></div>
                        <span class="listing-price-tag">USD</span>
                    </div>
                    <?php if ($tarifaLimpieza > 0): ?>
                        <div class="listing-cleaning-fee"><span>Tarifa de limpieza indicada</span><strong>$<?= number_format($tarifaLimpieza, 2) ?></strong></div>
                    <?php endif; ?>
                    <div class="listing-booking-divider"></div>
                    <h2>Solicitar reserva</h2>
                    <p class="listing-booking-intro">Elige tus fechas y la cantidad de huéspedes para calcular el total y enviar tu solicitud.</p>

                    <?php if ($confirmacion): ?>
                        <p class="alert alert-success" role="status"><?= escapar($confirmacion) ?></p>
                    <?php endif; ?>
                    <?php if ($error): ?>
                        <p class="alert alert-danger" role="alert"><?= escapar($error) ?></p>
                    <?php endif; ?>

                    <?php if ($casa['estado'] !== 'publicado'): ?>
                        <p class="listing-login-note">Las reservas están deshabilitadas porque esta publicación no se encuentra activa.</p>
                    <?php elseif (!$idUsuario): ?>
                        <p class="listing-login-note">Inicia sesión para solicitar una reserva.</p>
                        <a href="login.php" class="btn btn-success w-100 mt-2">Iniciar sesión</a>
                    <?php elseif ($esAnfitrion): ?>
                        <p class="listing-login-note">Este alojamiento pertenece a tu cuenta.</p>
                        <a href="editar_casa.php?id=<?= (int) $id ?>" class="btn btn-outline-success w-100 mt-2">Editar mi publicación</a>
                    <?php else: ?>
                        <form method="post" action="listing.php?id=<?= (int) $id ?>" class="listing-booking-form">
                            <input type="hidden" name="csrf" value="<?= escapar($_SESSION['csrf_reserva']) ?>">
                            <label for="entrada" class="form-label">Fecha de entrada</label>
                            <input type="date" name="entrada" id="entrada" class="form-control" min="<?= $hoy->format('Y-m-d') ?>" required>
                            <label for="salida" class="form-label">Fecha de salida</label>
                            <input type="date" name="salida" id="salida" class="form-control" min="<?= $hoy->modify('+1 day')->format('Y-m-d') ?>" required>
                            <label for="huespedes" class="form-label">Número de huéspedes</label>
                            <input type="number" name="huespedes" id="huespedes" class="form-control" min="1" max="<?= (int) $casa['capacidad'] ?>" value="1" required>
                            <p class="listing-capacity-note">Este alojamiento admite hasta <?= (int) $casa['capacidad'] ?> huésped(es).</p>

                            <div id="resumenCalculo" class="alert alert-light border small d-none mb-3"></div>

                            <button type="submit" class="btn btn-success w-100">Enviar solicitud</button>
                            <p class="listing-booking-footnote">La solicitud quedará pendiente hasta que el anfitrión responda.</p>
                        </form>
                    <?php endif; ?>
                </div>
            </aside>
        </div>
    </div>
</main>

<?php if ($totalFotos > 0): ?>
<div class="modal fade listing-gallery-modal" id="listingGalleryModal" tabindex="-1" aria-labelledby="listingGalleryModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="listingGalleryModalLabel">Fotos del alojamiento</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div id="listingPhotoCarousel" class="carousel slide" data-bs-interval="false" data-bs-touch="true">
                    <div class="carousel-inner">
                        <?php foreach ($fotos as $indice => $foto): ?>
                            <div class="carousel-item <?= $indice === 0 ? 'active' : '' ?>">
                                <img src="<?= escapar($foto) ?>" class="d-block w-100" alt="<?= escapar($casa['titulo']) ?> - fotografía <?= $indice + 1 ?>">
                                <div class="listing-modal-photo-count"><?= $indice + 1 ?> / <?= $totalFotos ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php if ($totalFotos > 1): ?>
                        <button class="carousel-control-prev" type="button" data-bs-target="#listingPhotoCarousel" data-bs-slide="prev" aria-label="Fotografía anterior"><span class="carousel-control-prev-icon" aria-hidden="true"></span></button>
                        <button class="carousel-control-next" type="button" data-bs-target="#listingPhotoCarousel" data-bs-slide="next" aria-label="Fotografía siguiente"><span class="carousel-control-next-icon" aria-hidden="true"></span></button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script src="assets/js/bootstrap.bundle.min.js"></script>
<script>
    // Visor de fotografías
    const galleryModalElement = document.getElementById('listingGalleryModal');
    const photoCarouselElement = document.getElementById('listingPhotoCarousel');

    if (galleryModalElement && photoCarouselElement && window.bootstrap) {
        const galleryModal = bootstrap.Modal.getOrCreateInstance(galleryModalElement);
        const carouselItems = Array.from(photoCarouselElement.querySelectorAll('.carousel-item'));

        document.querySelectorAll('[data-gallery-index]').forEach(function (button) {
            button.addEventListener('click', function () {
                const selectedIndex = Number(button.dataset.galleryIndex || 0);
                carouselItems.forEach(function (item, index) {
                    item.classList.toggle('active', index === selectedIndex);
                });
                galleryModal.show();
            });
        });
    }

    // Cálculo en vivo del importe estimado (noches x huéspedes x precio)
    const inputEntrada = document.getElementById('entrada');
    const inputSalida = document.getElementById('salida');
    const inputHuespedes = document.getElementById('huespedes');
    const resumenCalculo = document.getElementById('resumenCalculo');
    const precioPersonaNoche = <?= json_encode($precioPorPersonaNoche) ?>;

    function actualizarCalculo() {
        if (!inputEntrada || !inputSalida || !inputHuespedes || !resumenCalculo) return;
        if (!inputEntrada.value || !inputSalida.value) {
            resumenCalculo.classList.add('d-none');
            return;
        }
        const f1 = new Date(inputEntrada.value + 'T00:00:00');
        const f2 = new Date(inputSalida.value + 'T00:00:00');
        const noches = Math.round((f2 - f1) / (1000 * 60 * 60 * 24));
        const huespedes = parseInt(inputHuespedes.value || '1', 10);

        if (noches > 0 && huespedes > 0) {
            const total = (noches * huespedes * precioPersonaNoche).toFixed(2);
            resumenCalculo.innerHTML = noches + ' noche(s) × ' + huespedes + ' huésped(es) × $' +
                precioPersonaNoche.toFixed(2) + ' = <strong>$' + total + ' USD</strong>';
            resumenCalculo.classList.remove('d-none');
        } else {
            resumenCalculo.classList.add('d-none');
        }
    }

    if (inputEntrada && inputSalida) {
        inputEntrada.addEventListener('change', function () {
            if (this.value) {
                const fechaMinSalida = new Date(this.value + 'T00:00:00');
                fechaMinSalida.setDate(fechaMinSalida.getDate() + 1);
                const minStr = fechaMinSalida.getFullYear() + '-'
                    + String(fechaMinSalida.getMonth() + 1).padStart(2, '0') + '-'
                    + String(fechaMinSalida.getDate()).padStart(2, '0');
                inputSalida.min = minStr;

                if (inputSalida.value && inputSalida.value < minStr) {
                    inputSalida.value = minStr;
                }
            }
            actualizarCalculo();
        });
        inputSalida.addEventListener('change', actualizarCalculo);
        if (inputHuespedes) {
            inputHuespedes.addEventListener('input', actualizarCalculo);
        }
    }

    <?php if ($esAdmin): ?>
    function bloquearPublicacion(form) {
        const motivo = prompt('Indica el motivo de la desactivación/bloqueo (opcional):', 'Incumplimiento de normas de la plataforma');
        if (motivo === null) return;

        const inputAccion = document.createElement('input');
        inputAccion.type = 'hidden';
        inputAccion.name = 'accion';
        inputAccion.value = 'suspender';

        const inputMotivo = document.createElement('input');
        inputMotivo.type = 'hidden';
        inputMotivo.name = 'motivo';
        inputMotivo.value = motivo.trim();

        form.appendChild(inputAccion);
        form.appendChild(inputMotivo);
        form.submit();
    }
    <?php endif; ?>
</script>
</body>
</html>

<?php
session_start();
require_once __DIR__ . '/config/conexion.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);

if (!$id) {
    http_response_code(404);
    exit('Alojamiento no encontrado.');
}

$consulta = $conexion->prepare("
    SELECT a.titulo, a.descripcion, a.municipio, a.departamento,
           a.capacidad, a.precio_persona_noche, a.id_anfitrion,
           u.nombre AS anfitrion, f.ruta_archivo AS foto
    FROM alojamientos AS a
    JOIN usuarios AS u ON u.id_usuario = a.id_anfitrion
    LEFT JOIN fotos_alojamiento AS f
        ON f.id_alojamiento = a.id_alojamiento AND f.posicion = 1
    WHERE a.id_alojamiento = ? AND a.estado = 'publicado'
");
$consulta->bind_param('i', $id);
$consulta->execute();
$casa = $consulta->get_result()->fetch_assoc();

if (!$casa) {
    http_response_code(404);
    exit('Este alojamiento no está publicado o no existe.');
}

// Id para verificar
$idUsuario = (int) ($_SESSION['id_usuario'] ?? 0);
$esAnfitrion = $idUsuario === (int) $casa['id_anfitrion'];
$hoy = new DateTimeImmutable('today', new DateTimeZone('America/El_Salvador'));
$error = '';
$confirmacion = $_SESSION['confirmacion_reserva'][$id] ?? '';
unset($_SESSION['confirmacion_reserva'][$id]);

if (empty($_SESSION['csrf_reserva'])) {
    $_SESSION['csrf_reserva'] = bin2hex(random_bytes(32));
}

// Verifica fecha de entrada y salida
function leerFecha($valor): ?DateTimeImmutable
{
    if (!is_string($valor)) {
        return null;
    }

    $fecha = DateTimeImmutable::createFromFormat(
        '!Y-m-d',
        $valor,
        new DateTimeZone('America/El_Salvador')
    );

    return $fecha && $fecha->format('Y-m-d') === $valor ? $fecha : null;
}

function escapar($texto): string
{
    return htmlspecialchars((string) $texto, ENT_QUOTES, 'UTF-8');
}

// Luego de enviar el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $entrada = leerFecha($_POST['entrada'] ?? null);
    $salida = leerFecha($_POST['salida'] ?? null);
    $huespedes = filter_input(INPUT_POST, 'huespedes', FILTER_VALIDATE_INT);
    $token = $_POST['csrf'] ?? '';

    if (!$idUsuario || $esAnfitrion) {
        $error = 'Inicia sesión con una cuenta distinta a la del anfitrión.';
    } elseif (!is_string($token) || !hash_equals($_SESSION['csrf_reserva'], $token)) {
        $error = 'Recarga la página y vuelve a intentarlo.';
    } elseif (!$entrada || !$salida || $entrada < $hoy || $salida <= $entrada) {
        $error = 'Elige una entrada desde hoy y una salida posterior.';
    } elseif (!$huespedes || $huespedes < 1 || $huespedes > (int) $casa['capacidad']) {
        $error = 'La cantidad de huéspedes debe estar dentro de la capacidad.';
    } else {
        $fechaEntrada = $entrada->format('Y-m-d');
        $fechaSalida = $salida->format('Y-m-d');

        // La base toma la tarifa del alojamiento y calcula el total.
        $guardar = $conexion->prepare("
            INSERT INTO reservas (
                id_alojamiento, id_solicitante, fecha_entrada, fecha_salida,
                cantidad_huespedes, precio_persona_noche_aplicado, total, estado
            )
            SELECT a.id_alojamiento, u.id_usuario, datos.entrada, datos.salida,
                   datos.huespedes, a.precio_persona_noche,
                   DATEDIFF(datos.salida, datos.entrada)
                       * datos.huespedes * a.precio_persona_noche,
                   'pendiente'
            FROM alojamientos AS a
            JOIN usuarios AS u ON u.id_usuario = ? AND u.estado = 'activo'
            CROSS JOIN (SELECT ? AS entrada, ? AS salida, ? AS huespedes) AS datos
            WHERE a.id_alojamiento = ?
              AND a.estado = 'publicado'
              AND a.id_anfitrion <> u.id_usuario
              AND a.capacidad >= datos.huespedes
              AND NOT EXISTS (
                  SELECT 1
                  FROM reservas AS r
                  WHERE r.id_alojamiento = a.id_alojamiento
                    AND r.estado = 'aceptada'
                    AND r.fecha_entrada < datos.salida
                    AND r.fecha_salida > datos.entrada
              )
        ");
        $guardar->bind_param('issii', $idUsuario, $fechaEntrada, $fechaSalida, $huespedes, $id);
        $guardar->execute();

        if ($guardar->affected_rows === 1) {
            $_SESSION['confirmacion_reserva'][$id] =
                'Solicitud enviada. Quedó pendiente de respuesta del anfitrión.';

            // Volver por GET evita reenviar la solicitud al recargar.
            header('Location: listing.php?id=' . $id, true, 303);
            exit;
        }

        $error = 'No se pudo solicitar la reserva. Revisa tu cuenta y prueba otras fechas.';
    }
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= escapar($casa['titulo']) ?> | Davinci</title>
    <link rel="stylesheet" href="assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="assets/css/inicio.css">
</head>
<body>
    <main class="container py-4">
        <a href="index.php#alojamientos" class="btn btn-outline-secondary mb-4">
            Volver a alojamientos
        </a>

        <div class="row g-4">
            <section class="col-lg-8">
                <h1 class="h2"><?= escapar($casa['titulo']) ?></h1>
                <p class="text-muted">
                    <?= escapar($casa['municipio']) ?>,
                    <?= escapar($casa['departamento']) ?>
                    · Hasta <?= (int) $casa['capacidad'] ?> huéspedes
                </p>

                <?php if (!empty($casa['foto'])): ?>
                    <img src="<?= escapar($casa['foto']) ?>"
                         alt="Foto de <?= escapar($casa['titulo']) ?>"
                         class="img-fluid w-100 rounded object-fit-cover mb-4"
                         style="max-height: 420px;">
                <?php endif; ?>

                <h2 class="h5">Sobre el alojamiento</h2>
                <p><?= nl2br(escapar($casa['descripcion'])) ?></p>
                <p><strong>Anfitrión:</strong> <?= escapar($casa['anfitrion']) ?></p>
            </section>

            <aside class="col-lg-5">
                <div class="card shadow-sm">
                    <div class="card-body">
                        <h2 class="h5">Solicitar reserva</h2>
                        <p>
                            <strong>$<?= number_format((float) $casa['precio_persona_noche'], 2) ?></strong>
                            por persona y noche
                        </p>

                        <?php if ($confirmacion): ?>
                            <p class="alert alert-success" role="status">
                                <?= escapar($confirmacion) ?>
                            </p>
                        <?php endif; ?>

                        <?php if ($error): ?>
                            <p class="alert alert-danger" role="alert"><?= escapar($error) ?></p>
                        <?php endif; ?>

                        <?php if (!$idUsuario): ?>
                            <p class="mb-0">Inicia sesión para solicitar una reserva.</p>
                            <?php if (is_file(__DIR__ . '/modulos/acceso/login.php')): ?>
                                <a href="modulos/acceso/login.php" class="btn btn-success mt-3">
                                    Iniciar sesión
                                </a>
                            <?php endif; ?>
                        <?php elseif ($esAnfitrion): ?>
                            <p class="mb-0">Este alojamiento es tuyo.</p>
                        <?php else: ?>
                            <form method="post" action="listing.php?id=<?= $id ?>">
                                <input type="hidden" name="csrf"
                                       value="<?= escapar($_SESSION['csrf_reserva']) ?>">

                                <label for="entrada" class="form-label">Entrada</label>
                                <input type="date" name="entrada" id="entrada"
                                       class="form-control mb-3"
                                       min="<?= $hoy->format('Y-m-d') ?>" required>

                                <label for="salida" class="form-label">Salida</label>
                                <input type="date" name="salida" id="salida"
                                       class="form-control mb-3"
                                       min="<?= $hoy->modify('+1 day')->format('Y-m-d') ?>" required>

                                <label for="huespedes" class="form-label">Huéspedes</label>
                                <input type="number" name="huespedes" id="huespedes"
                                       class="form-control mb-3" min="1"
                                       max="<?= (int) $casa['capacidad'] ?>" value="1" required>

                                <button type="submit" class="btn btn-success w-100">
                                    Enviar solicitud
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </aside>
        </div>
    </main>
</body>
</html>
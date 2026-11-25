<?php
if (!isset($esAnfitrion)) {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/reservas.php';
require_once __DIR__ . '/portada_alojamiento.php';

$id = (int) $_SESSION['id_usuario'];
$ahora = relojReservas();
$filtros = ['todas' => 'Todas', 'pendiente' => 'Pendientes', 'aceptada' => 'Confirmadas',
    'pasadas' => 'Pasadas', 'rechazada' => 'Rechazadas', 'cancelada' => 'Canceladas'];
$filtro = is_string($_GET['estado'] ?? null) ? $_GET['estado'] : 'todas';
$filtro = isset($filtros[$filtro]) ? $filtro : 'todas';
$panel = $esAnfitrion ? 'panel_anfitrion.php' : 'panel_cliente.php';

$titular = $esAnfitrion ? 'a.id_anfitrion' : 'r.id_solicitante';
$persona = $esAnfitrion ? 'r.id_solicitante' : 'a.id_anfitrion';
$portadaExpr = PORTADA_ALOJAMIENTO_SQL;
$rolAvisos = $esAnfitrion ? 'anfitrion' : 'huesped';
$estado = match ($filtro) {
    'todas' => '',
    'pasadas' => "AND r.estado = 'aceptada' AND TIMESTAMP(r.fecha_salida, a.hora_checkout) <= ?",
    'aceptada' => "AND r.estado = 'aceptada' AND TIMESTAMP(r.fecha_salida, a.hora_checkout) > ?",
    default => 'AND r.estado = ?',
};
// La fecha del panel sigue el reloj de El Salvador, aunque la zona de MariaDB sea distinta.
$sql = "
    SELECT r.id_reserva, r.fecha_entrada, r.fecha_salida, r.cantidad_huespedes, r.total, r.estado,
           a.titulo, a.hora_checkin, a.hora_checkout, u.nombre AS nombre_persona, u.foto_perfil,
           $portadaExpr AS portada
    FROM reservas r
    JOIN alojamientos a ON a.id_alojamiento = r.id_alojamiento
    JOIN usuarios u ON u.id_usuario = $persona
    WHERE $titular = ? $estado
    ORDER BY CASE
        WHEN r.estado = 'pendiente' AND TIMESTAMP(r.fecha_entrada, a.hora_checkin) > ? THEN 0
        WHEN r.estado = 'aceptada' AND TIMESTAMP(r.fecha_salida, a.hora_checkout) > ? THEN 1
        ELSE 2 END, r.fecha_entrada ASC, r.id_reserva DESC
";
$stmt = $conexion->prepare($sql);
$fechaHora = $ahora->format('Y-m-d H:i:s');
if ($filtro === 'todas') {
    $stmt->bind_param('iss', $id, $fechaHora, $fechaHora);
} else {
    $valorFiltro = in_array($filtro, ['aceptada', 'pasadas'], true) ? $fechaHora : $filtro;
    $stmt->bind_param('isss', $id, $valorFiltro, $fechaHora, $fechaHora);
}
$stmt->execute();
$reservas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$pageTitle = $esAnfitrion ? 'Reservas recibidas' : 'Mis reservas';
require __DIR__ . '/header.php';
?>
<div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4">
    <h1 class="h2 mb-0"><?= e($pageTitle) ?></h1>
    <?php if ($esAnfitrion): ?>
        <div class="d-flex flex-wrap gap-2">
            <a class="btn btn-outline-dark" href="mis_alojamientos.php">Mis alojamientos</a>
            <a class="btn btn-outline-dark" href="calendario_anfitrion.php">Calendario</a>
            <a class="btn btn-dark" href="publicar_casa.php?desde=anfitrion">Publicar alojamiento</a>
        </div>
    <?php else: ?>
        <a class="btn btn-outline-dark" href="index.php">Explorar alojamientos</a>
    <?php endif; ?>
</div>
<?php if ($flash): ?><p class="alert alert-<?= e($flash['tipo']) ?>" role="status"><?= e($flash['texto']) ?></p><?php endif; ?>
<nav class="nav nav-pills gap-1 mb-4" aria-label="Filtrar reservas">
    <?php foreach ($filtros as $valor => $etiqueta): ?>
        <a class="nav-link <?= $filtro === $valor ? 'active' : '' ?>" href="<?= e($panel) ?>?estado=<?= e($valor) ?>" <?= $filtro === $valor ? 'aria-current="page"' : '' ?>><?= e($etiqueta) ?></a>
    <?php endforeach; ?>
</nav>
<?php if (!$reservas): ?>
    <div class="card p-4 rounded-4"><p class="mb-0"><?= $esAnfitrion ? 'No hay solicitudes en esta vista. Recibirás reservas cuando los huéspedes soliciten fechas en tus anuncios.' : 'No hay reservas en esta vista.' ?></p></div>
<?php else: ?>
    <div class="row g-3">
        <?php foreach ($reservas as $reserva): ?>
            <?php [$estadoVisible, $color] = estadoVisibleReserva($reserva, $ahora); ?>
            <div class="col-md-6">
                <article id="reserva-<?= (int) $reserva['id_reserva'] ?>" class="card h-100 rounded-4 overflow-hidden shadow-sm">
                    <?php if ($reserva['portada']): ?>
                        <img src="<?= e($reserva['portada']) ?>" class="reserva-portada" alt="Portada de <?= e($reserva['titulo']) ?>" loading="lazy">
                    <?php endif; ?>
                    <div class="card-body p-4">
                        <span class="badge bg-<?= e($color) ?> mb-2"><?= e($estadoVisible) ?></span>
                        <h2 class="h5"><?= e($reserva['titulo']) ?></h2>
                        <p class="d-flex align-items-center gap-2 text-muted mb-2">
                            <?php if ($reserva['foto_perfil']): ?>
                                <img class="reserva-avatar" src="<?= e($reserva['foto_perfil']) ?>" alt="" loading="lazy">
                            <?php else: ?>
                                <span class="reserva-avatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($reserva['nombre_persona'], 0, 1))) ?></span>
                            <?php endif; ?>
                            <span><?= $esAnfitrion ? 'Huésped' : 'Anfitrión' ?> · <?= e($reserva['nombre_persona']) ?></span>
                        </p>
                        <p class="mb-1"><time datetime="<?= e($reserva['fecha_entrada']) ?>"><?= e($reserva['fecha_entrada']) ?></time> al <time datetime="<?= e($reserva['fecha_salida']) ?>"><?= e($reserva['fecha_salida']) ?></time></p>
                        <p><?= (int) $reserva['cantidad_huespedes'] ?> huésped(es) · $<?= number_format((float) $reserva['total'], 2) ?></p>
                        <div class="d-flex flex-wrap gap-2">
                            <a class="btn btn-outline-dark btn-sm" href="reserva.php?id=<?= (int) $reserva['id_reserva'] ?>" data-avisos-reserva="<?= $rolAvisos ?>:<?= (int) $reserva['id_reserva'] ?>">Ver reserva</a>
                            <?php if ($esAnfitrion): ?>
                                <a class="btn btn-outline-dark btn-sm" href="perfil_huesped.php?reserva=<?= (int) $reserva['id_reserva'] ?>">Ver perfil del huésped</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </article>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php require __DIR__ . '/footer.php'; ?>

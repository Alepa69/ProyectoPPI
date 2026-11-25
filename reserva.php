<?php
require_once __DIR__ . '/config/sesion.php';
require_once __DIR__ . '/includes/reservas.php';
require_once __DIR__ . '/includes/portada_alojamiento.php';

$id = (int) filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$usuario = (int) $_SESSION['id_usuario'];
$portadaExpr = PORTADA_ALOJAMIENTO_SQL;
$stmt = $conexion->prepare("
    SELECT r.*, a.titulo, a.estado AS estado_alojamiento, a.id_anfitrion, a.hora_checkin, a.hora_checkout,
           a.municipio, a.departamento, a.direccion, a.referencias,
           h.nombre AS nombre_anfitrion, h.foto_perfil AS foto_anfitrion,
           g.nombre AS nombre_huesped, g.foto_perfil AS foto_huesped, g.biografia AS bio_huesped,
           $portadaExpr AS portada
    FROM reservas r JOIN alojamientos a ON a.id_alojamiento = r.id_alojamiento
    JOIN usuarios h ON h.id_usuario = a.id_anfitrion JOIN usuarios g ON g.id_usuario = r.id_solicitante
    WHERE r.id_reserva = ? AND (r.id_solicitante = ? OR a.id_anfitrion = ?)
");
$stmt->bind_param('iii', $id, $usuario, $usuario);
$stmt->execute();
$reserva = $stmt->get_result()->fetch_assoc();
if (!$reserva) {
    http_response_code(404);
    exit('No encontramos esa reserva.');
}
leerAvisosDeReserva($conexion, $usuario, $id);

$esAnfitrion = (int) $reserva['id_anfitrion'] === $usuario;
$volver = $esAnfitrion ? 'panel_anfitrion.php' : 'panel_cliente.php';
$_SESSION['modo'] = $esAnfitrion ? 'anfitrion' : 'huesped';
$ahora = relojReservas();
[$estado, $color] = estadoVisibleReserva($reserva, $ahora);
$puedeResponder = $esAnfitrion && $reserva['estado'] === 'pendiente' && $ahora < momentoEntrada($reserva);
$puedeCancelar = puedeRetirarOCancelar($reserva, $esAnfitrion, $ahora);
$accionCancelar = $reserva['estado'] === 'pendiente' ? 'Retirar solicitud' : 'Cancelar reserva';
$entrada = fechaReserva($reserva['fecha_entrada']);
$noches = $entrada->diff(fechaReserva($reserva['fecha_salida']))->days;
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);
$pageTitle = 'Detalle de reserva';
require __DIR__ . '/includes/header.php';
?>
<a href="<?= e($volver) ?>" class="btn btn-outline-secondary btn-sm mb-3" data-avisos-reserva="<?= $esAnfitrion ? 'anfitrion' : 'huesped' ?>">Volver a reservas</a>
<?php if ($flash): ?><p class="alert alert-<?= e($flash['tipo']) ?>" role="status"><?= e($flash['texto']) ?></p><?php endif; ?>
<div class="row g-4">
    <div class="col-lg-7">
        <article class="card rounded-4 overflow-hidden">
            <?php if ($reserva['portada']): ?>
                <img class="reserva-portada reserva-portada-detalle" src="<?= e($reserva['portada']) ?>" alt="Portada de <?= e($reserva['titulo']) ?>">
            <?php endif; ?>
            <div class="card-body p-4">
            <div><span class="badge bg-<?= e($color) ?>"><?= e($estado) ?></span></div>
            <h1 class="h3 mt-2"><?= e($reserva['titulo']) ?></h1>
            <p class="text-muted">Reserva #<?= $id ?> · <?= e($reserva['municipio'] . ', ' . $reserva['departamento']) ?></p>
            <dl>
                <dt>Entrada</dt><dd><?= e($reserva['fecha_entrada']) ?> a las <?= e(substr($reserva['hora_checkin'], 0, 5)) ?></dd>
                <dt>Salida</dt><dd><?= e($reserva['fecha_salida']) ?> a las <?= e(substr($reserva['hora_checkout'], 0, 5)) ?></dd>
                <dt>Huéspedes</dt><dd><?= (int) $reserva['cantidad_huespedes'] ?></dd>
                <dt>Total guardado</dt>
                <dd><?= $noches ?> noches × <?= (int) $reserva['cantidad_huespedes'] ?> huésped(es) × $<?= number_format((float) $reserva['precio_persona_noche_aplicado'], 2) ?> = <strong>$<?= number_format((float) $reserva['total'], 2) ?></strong></dd>
                <?php if ($reserva['estado'] === 'aceptada' && !$esAnfitrion): ?>
                    <dt>Dirección del alojamiento</dt><dd><?= e($reserva['direccion']) ?> <?= e($reserva['referencias']) ?></dd>
                <?php endif; ?>
                <?php if ($reserva['motivo_cancelacion']): ?><dt>Motivo</dt><dd><?= e($reserva['motivo_cancelacion']) ?></dd><?php endif; ?>
            </dl>
            <div class="d-flex flex-wrap gap-2">
                <?php if ($reserva['estado_alojamiento'] === 'publicado'): ?>
                    <a class="btn btn-outline-dark btn-sm" href="listing.php?id=<?= (int) $reserva['id_alojamiento'] ?>">Ver anuncio</a>
                <?php endif; ?>
                <?php if (in_array($reserva['estado'], ['pendiente', 'aceptada'], true)): ?>
                    <a class="btn btn-outline-dark btn-sm" href="chat.php?id_reserva=<?= $id ?>">Mensajes</a>
                <?php endif; ?>
            </div>
            </div>
        </article>
    </div>
    <div class="col-lg-5">
        <aside class="card rounded-4 p-4">
            <h2 class="h5"><?= $esAnfitrion ? 'Huésped' : 'Anfitrión' ?></h2>
            <?php
            $foto = $esAnfitrion ? $reserva['foto_huesped'] : $reserva['foto_anfitrion'];
            $nombrePersona = $esAnfitrion ? $reserva['nombre_huesped'] : $reserva['nombre_anfitrion'];
            ?>
            <div class="d-flex flex-wrap align-items-center gap-3 mb-3">
                <?php if ($foto): ?>
                    <img class="reserva-avatar reserva-avatar-grande" src="<?= e($foto) ?>" alt="">
                <?php else: ?>
                    <span class="reserva-avatar reserva-avatar-grande" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($nombrePersona, 0, 1))) ?></span>
                <?php endif; ?>
                <div>
                    <p class="fw-semibold mb-1"><?= e($nombrePersona) ?></p>
                    <?php if ($esAnfitrion): ?><a class="btn btn-outline-dark btn-sm" href="perfil_huesped.php?reserva=<?= $id ?>">Ver perfil del huésped</a><?php endif; ?>
                </div>
            </div>
            <?php if ($esAnfitrion && $reserva['bio_huesped']): ?><p class="text-break"><?= nl2br(e($reserva['bio_huesped'])) ?></p><?php endif; ?>
            <?php if ($puedeResponder): ?>
                <form method="post" action="responder_reserva.php" class="d-flex flex-wrap gap-2">
                    <input type="hidden" name="csrf" value="<?= e(tokenFormulario()) ?>">
                    <input type="hidden" name="desde" value="detalle">
                    <input type="hidden" name="id_reserva" value="<?= $id ?>">
                    <?php if ($reserva['estado_alojamiento'] === 'publicado'): ?>
                        <button class="btn btn-dark" name="accion" value="aceptar">Confirmar</button>
                    <?php endif; ?>
                    <button class="btn btn-outline-secondary" name="accion" value="rechazar">Rechazar</button>
                </form>
            <?php elseif ($puedeCancelar): ?>
                <form method="post" action="<?= $esAnfitrion ? 'responder_reserva.php' : 'cancelar_reserva.php' ?>" onsubmit="return confirm('¿Confirmas esta acción sobre la reserva?');">
                    <input type="hidden" name="csrf" value="<?= e(tokenFormulario()) ?>">
                    <input type="hidden" name="desde" value="detalle">
                    <input type="hidden" name="id_reserva" value="<?= $id ?>">
                    <label class="form-label" for="motivo">Motivo (opcional)</label>
                    <textarea class="form-control mb-2" id="motivo" name="motivo" rows="2" maxlength="500"></textarea>
                    <button class="btn btn-outline-danger" name="accion" value="cancelar"><?= e($accionCancelar) ?></button>
                </form>
            <?php endif; ?>
            <?php if (!$esAnfitrion && $reserva['estado'] === 'aceptada' && $ahora < momentoEntrada($reserva)): ?>
                <p class="small text-muted mt-3 mb-0">Plazo para cancelar como huésped: <?= e(momentoEntrada($reserva)->modify('-24 hours')->format('d/m/Y H:i')) ?>, hora de El Salvador.</p>
            <?php endif; ?>
        </aside>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>

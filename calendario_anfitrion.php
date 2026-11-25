<?php
require_once __DIR__ . '/config/sesion.php';
require_once __DIR__ . '/includes/reservas.php';

$_SESSION['modo'] = 'anfitrion';
$usuario = (int) $_SESSION['id_usuario'];
$hoy = relojReservas()->setTime(0, 0);
$stmt = $conexion->prepare('SELECT id_alojamiento, titulo FROM alojamientos WHERE id_anfitrion = ? ORDER BY titulo');
$stmt->bind_param('i', $usuario);
$stmt->execute();
$casas = array_column($stmt->get_result()->fetch_all(MYSQLI_ASSOC), 'titulo', 'id_alojamiento');
$id = (int) filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$id = $id ?: (int) array_key_first($casas);
if ($id && !isset($casas[$id])) {
    http_response_code(404);
    exit('No encontramos ese alojamiento.');
}
$mesPedido = $_GET['mes'] ?? '';
$mes = is_string($mesPedido) && preg_match('/^20\d{2}-(0[1-9]|1[0-2])$/D', $mesPedido)
    ? fechaReserva($mesPedido . '-01') : $hoy->modify('first day of this month');
$fechaMes = $mes->format('Y-m');
$url = 'calendario_anfitrion.php?id=' . $id . '&mes=';
$rutaActual = $url . $fechaMes;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $enTransaccion = false;
    try {
        $accion = $_POST['accion'] ?? '';
        if (!$id || !in_array($accion, ['bloquear', 'liberar'], true) || !tokenFormularioValido()) {
            throw new DomainException('Recarga la página y vuelve a intentarlo.');
        }
        $conexion->begin_transaction();
        $enTransaccion = true;
        $casa = bloquearAlojamiento($conexion, $id);
        if ((int) $casa['id_anfitrion'] !== $usuario) {
            throw new DomainException('El alojamiento no está disponible para esta cuenta.');
        }

        if ($accion === 'bloquear') {
            $inicio = fechaReserva($_POST['inicio'] ?? null);
            $fin = fechaReserva($_POST['fin'] ?? null);
            if (!$inicio || !$fin || $inicio < $hoy || $fin <= $inicio || $fin->format('Y') > 2099) {
                throw new DomainException('Elige una primera noche desde hoy y una fecha de salida posterior.');
            }
            $desde = $inicio->format('Y-m-d');
            $hasta = $fin->format('Y-m-d');
            if (hayReservaConfirmada($conexion, $id, $desde, $hasta) || hayBloqueoDeFechas($conexion, $id, $desde, $hasta)) {
                throw new DomainException('Algunas noches ya están reservadas o bloqueadas.');
            }
            $stmt = $conexion->prepare('INSERT INTO bloqueos_alojamiento (id_alojamiento, fecha_inicio, fecha_fin) VALUES (?, ?, ?)');
            $stmt->bind_param('iss', $id, $desde, $hasta);
        } else {
            $bloqueo = (int) filter_input(INPUT_POST, 'id_bloqueo', FILTER_VALIDATE_INT);
            $stmt = $conexion->prepare('DELETE FROM bloqueos_alojamiento WHERE id_bloqueo = ? AND id_alojamiento = ?');
            $stmt->bind_param('ii', $bloqueo, $id);
        }
        $stmt->execute();
        if ($stmt->affected_rows !== 1) {
            throw new DomainException('No encontramos ese bloqueo.');
        }
        $conexion->commit();
        $_SESSION['calendario_flash'] = ['tipo' => 'success', 'texto' => $accion === 'bloquear' ? 'Noches bloqueadas.' : 'Bloqueo retirado.'];
    } catch (Throwable $error) {
        if ($enTransaccion) {
            $conexion->rollback();
        }
        if (!$error instanceof DomainException) {
            error_log('Calendario: ' . $error->getMessage());
        }
        $_SESSION['calendario_flash'] = ['tipo' => 'warning', 'texto' => $error instanceof DomainException
            ? $error->getMessage() : 'No se pudo actualizar el calendario.'];
    }
    header('Location: ' . $rutaActual, true, 303);
    exit;
}

$inicioMes = $mes->format('Y-m-d');
$finMes = $mes->modify('+1 month')->format('Y-m-d');
$ocupacion = [];
if ($id) {
    $stmt = $conexion->prepare("
        SELECT fecha_entrada AS inicio, fecha_salida AS fin, 'Reservada' AS etiqueta
        FROM reservas WHERE id_alojamiento = ? AND estado = 'aceptada' AND fecha_entrada < ? AND fecha_salida > ?
        UNION ALL
        SELECT fecha_inicio, fecha_fin, 'Bloqueada'
        FROM bloqueos_alojamiento WHERE id_alojamiento = ? AND fecha_inicio < ? AND fecha_fin > ?
    ");
    $stmt->bind_param('ississ', $id, $finMes, $inicioMes, $id, $finMes, $inicioMes);
    $stmt->execute();
    $ocupacion = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt = $conexion->prepare('SELECT * FROM bloqueos_alojamiento WHERE id_alojamiento = ? AND fecha_fin > ? ORDER BY fecha_inicio');
    $hoyTexto = $hoy->format('Y-m-d');
    $stmt->bind_param('is', $id, $hoyTexto);
    $stmt->execute();
    $bloqueos = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}
$nombresMes = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
$flash = $_SESSION['calendario_flash'] ?? null;
unset($_SESSION['calendario_flash']);
$pageTitle = 'Calendario del anfitrión';
require __DIR__ . '/includes/header.php';
?>
<a href="panel_anfitrion.php" class="btn btn-outline-secondary btn-sm mb-3">Volver al panel</a>
<h1 class="h2 mb-4">Calendario del anfitrión</h1>
<?php if ($flash): ?><p class="alert alert-<?= e($flash['tipo']) ?>" role="status"><?= e($flash['texto']) ?></p><?php endif; ?>
<?php if (!$casas): ?>
    <p>Publica un alojamiento para gestionar su disponibilidad.</p>
<?php else: ?>
    <form class="d-flex align-items-end flex-wrap gap-2 mb-4">
        <div><label class="form-label" for="id">Alojamiento</label>
            <select class="form-select" id="id" name="id">
                <?php foreach ($casas as $clave => $titulo): ?><option value="<?= (int) $clave ?>" <?= $clave === $id ? 'selected' : '' ?>><?= e($titulo) ?></option><?php endforeach; ?>
            </select>
        </div>
        <input type="hidden" name="mes" value="<?= e($fechaMes) ?>">
        <button class="btn btn-outline-dark">Ver calendario</button>
    </form>
    <div class="d-flex justify-content-between align-items-center mb-2">
        <a class="btn btn-outline-secondary btn-sm" href="<?= e($url . $mes->modify('-1 month')->format('Y-m')) ?>">Anterior</a>
        <h2 class="h5 mb-0"><?= e($nombresMes[(int) $mes->format('n') - 1] . ' ' . $mes->format('Y')) ?></h2>
        <a class="btn btn-outline-secondary btn-sm" href="<?= e($url . $mes->modify('+1 month')->format('Y-m')) ?>">Siguiente</a>
    </div>
    <div class="table-responsive">
        <table class="table table-bordered bg-white align-middle" style="min-width: 31rem; table-layout: fixed">
            <thead><tr><?php foreach (['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'] as $dia): ?><th scope="col"><?= e($dia) ?></th><?php endforeach; ?></tr></thead>
            <tbody>
                <?php
                $desfase = (int) $mes->format('N') - 1;
                $celdas = (int) (ceil(((int) $mes->format('t') + $desfase) / 7) * 7);
                for ($celda = 0; $celda < $celdas; $celda++):
                    $numero = $celda - $desfase + 1;
                    $fecha = $mes->modify(($numero - 1) . ' days')->format('Y-m-d');
                    $etiqueta = '';
                    foreach ($ocupacion as $periodo) {
                        if ($periodo['inicio'] <= $fecha && $fecha < $periodo['fin']) {
                            $etiqueta = $periodo['etiqueta'];
                            break;
                        }
                    }
                    ?>
                    <?php if ($celda % 7 === 0): ?><tr><?php endif; ?>
                    <td class="<?= $etiqueta === 'Reservada' ? 'table-success' : ($etiqueta === 'Bloqueada' ? 'table-secondary' : '') ?>">
                        <?php if ($numero >= 1 && $numero <= (int) $mes->format('t')): ?>
                            <time datetime="<?= e($fecha) ?>"><?= $numero ?></time>
                            <?php if ($etiqueta): ?><small class="d-block"><?= e($etiqueta) ?></small><?php endif; ?>
                        <?php endif; ?>
                    </td>
                    <?php if ($celda % 7 === 6): ?></tr><?php endif; ?>
                <?php endfor; ?>
            </tbody>
        </table>
    </div>
    <form method="post" class="card p-3 mb-3">
        <input type="hidden" name="csrf" value="<?= e(tokenFormulario()) ?>">
        <h2 class="h5">Bloquear noches</h2>
        <div class="d-flex flex-wrap align-items-end gap-3">
            <div><label class="form-label" for="inicio">Primera noche</label><input class="form-control" required type="date" id="inicio" name="inicio" min="<?= e($hoyTexto) ?>"></div>
            <div><label class="form-label" for="fin">Fecha de salida</label><input class="form-control" required type="date" id="fin" name="fin" min="<?= e($hoy->modify('+1 day')->format('Y-m-d')) ?>"></div>
            <button class="btn btn-dark" name="accion" value="bloquear">Bloquear</button>
        </div>
    </form>
    <?php if ($bloqueos): ?>
        <h2 class="h5">Bloqueos activos</h2>
        <?php foreach ($bloqueos as $bloqueo): ?>
            <form method="post" class="d-flex align-items-center justify-content-between border-bottom py-2 gap-2">
                <input type="hidden" name="csrf" value="<?= e(tokenFormulario()) ?>">
                <input type="hidden" name="id_bloqueo" value="<?= (int) $bloqueo['id_bloqueo'] ?>">
                <span><?= e($bloqueo['fecha_inicio']) ?> al <?= e($bloqueo['fecha_fin']) ?></span>
                <button class="btn btn-outline-secondary btn-sm" name="accion" value="liberar">Quitar bloqueo</button>
            </form>
        <?php endforeach; ?>
    <?php endif; ?>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>

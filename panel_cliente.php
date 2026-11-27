<?php
require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/config/sesion.php';

date_default_timezone_set('America/El_Salvador');

$idCliente = (int) $_SESSION['id_usuario'];
$filtro = $_GET['estado'] ?? 'todas';
$estadosValidos = ['todas', 'pendiente', 'aceptada', 'rechazada', 'cancelada'];

if (!in_array($filtro, $estadosValidos, true)) {
    $filtro = 'todas';
}

// Reservas solicitadas por el usuario
$sql = "SELECT r.id_reserva, r.fecha_entrada, r.fecha_salida,
               r.cantidad_huespedes, r.total, r.estado,
               a.id_alojamiento, a.titulo AS alojamiento, a.hora_checkin,
               u.nombre AS anfitrion_nombre, u.apellido AS anfitrion_apellido, u.correo AS anfitrion_correo
        FROM reservas r
        JOIN alojamientos a ON a.id_alojamiento = r.id_alojamiento
        JOIN usuarios u ON u.id_usuario = a.id_anfitrion
        WHERE r.id_solicitante = ?";

if ($filtro === 'todas') {
    $sql .= " ORDER BY r.creado_en DESC";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param('i', $idCliente);
} else {
    $sql .= " AND r.estado = ? ORDER BY r.creado_en DESC";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param('is', $idCliente, $filtro);
}

$stmt->execute();
$reservas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$ahora = new DateTimeImmutable('now', new DateTimeZone('America/El_Salvador'));

$pageTitle = 'Mis reservas';
include __DIR__ . '/includes/header.php';
?>
<h2 class="mb-3">Mis reservas</h2>

<?php if ($flash): ?>
    <div class="alert alert-<?= htmlspecialchars($flash['tipo']) ?>">
        <?= htmlspecialchars($flash['texto']) ?>
    </div>
<?php endif; ?>

<ul class="nav nav-pills mb-3">
    <?php foreach ($estadosValidos as $e): ?>
        <li class="nav-item">
            <a class="nav-link <?= $filtro === $e ? 'active' : '' ?>"
               href="panel_cliente.php?estado=<?= $e ?>">
                <?= ucfirst($e) ?>
            </a>
        </li>
    <?php endforeach; ?>
</ul>

<?php if (empty($reservas)): ?>
    <p class="text-muted">No tienes reservas en esta categoría.</p>
<?php else: ?>
    <div class="table-responsive">
        <table class="table table-hover align-middle bg-white shadow-sm">
            <thead class="table-dark">
                <tr>
                    <th>Alojamiento</th>
                    <th>Anfitrión</th>
                    <th>Entrada</th>
                    <th>Salida</th>
                    <th>Huéspedes</th>
                    <th>Total</th>
                    <th>Estado</th>
                    <th>Chat</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reservas as $r): ?>
                    <?php
                    $color = match ($r['estado']) {
                        'pendiente' => 'warning text-dark',
                        'aceptada'  => 'success',
                        'rechazada' => 'secondary',
                        'cancelada' => 'danger',
                        default     => 'dark',
                    };

                    // Regla: pendiente puede retirarse antes de entrar; aceptada al menos 24h antes
                    $horaEntrada = $r['hora_checkin'] ?: '15:00:00';
                    $momentoEntrada = new DateTimeImmutable($r['fecha_entrada'] . ' ' . $horaEntrada, new DateTimeZone('America/El_Salvador'));
                    $puedeCancelar = false;
                    if ($r['estado'] === 'pendiente') {
                        $puedeCancelar = $ahora < $momentoEntrada;
                    } elseif ($r['estado'] === 'aceptada') {
                        $puedeCancelar = $ahora <= $momentoEntrada->modify('-24 hours');
                    }
                    ?>
                    <tr>
                        <td>
                            <a href="listing.php?id=<?= (int) $r['id_alojamiento'] ?>">
                                <?= htmlspecialchars($r['alojamiento']) ?>
                            </a>
                        </td>
                        <td>
                            <?= htmlspecialchars($r['anfitrion_nombre'] . ' ' . $r['anfitrion_apellido']) ?><br>
                            <small class="text-muted"><?= htmlspecialchars($r['anfitrion_correo']) ?></small>
                        </td>
                        <td><?= htmlspecialchars($r['fecha_entrada']) ?></td>
                        <td><?= htmlspecialchars($r['fecha_salida']) ?></td>
                        <td><?= (int) $r['cantidad_huespedes'] ?></td>
                        <td>$<?= number_format((float) $r['total'], 2) ?></td>
                        <td>
                            <span class="badge bg-<?= $color ?>">
                                <?= ucfirst($r['estado']) ?>
                            </span>
                        </td>
                        <td>
                            <?php if ($r['estado'] === 'pendiente' || $r['estado'] === 'aceptada'): ?>
                                <a href="chat.php?id_reserva=<?= (int) $r['id_reserva'] ?>" class="btn btn-sm btn-outline-primary">Chat</a>
                            <?php else: ?>
                                <span class="text-muted small">—</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($puedeCancelar): ?>
                                <form method="POST" action="forms/cancelar_reserva.php" class="d-inline"
                                      onsubmit="return confirm('¿Seguro que quieres cancelar esta reserva?');">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
                                    <input type="hidden" name="id_reserva" value="<?= (int) $r['id_reserva'] ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                        <?= $r['estado'] === 'pendiente' ? 'Retirar' : 'Cancelar' ?>
                                    </button>
                                </form>
                            <?php else: ?>
                                <span class="text-muted small">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>

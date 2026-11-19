<?php
require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/config/sesion.php';

// 1. Guardamos el ID del usuario que inició sesión
$idCliente = (int) $_SESSION['id_usuario'];

// 2. Revisamos qué filtro eligió en los botones de arriba (por defecto "todas")
$filtro = $_GET['estado'] ?? 'todas';
$estadosValidos = ['todas', 'pendiente', 'aceptada', 'rechazada', 'cancelada'];

if (!in_array($filtro, $estadosValidos)) {
    $filtro = 'todas';
}

// 3. Consultamos las reservas donde este usuario es el solicitante
if ($filtro === 'todas') {
    $sql = "SELECT r.id_reserva, r.fecha_entrada, r.fecha_salida,
                   r.cantidad_huespedes, r.total, r.estado,
                   a.id_alojamiento, a.titulo AS alojamiento,
                   u.nombre AS anfitrion_nombre, u.apellido AS anfitrion_apellido, u.correo AS anfitrion_correo
            FROM reservas r
            JOIN alojamientos a ON a.id_alojamiento = r.id_alojamiento
            JOIN usuarios u ON u.id_usuario = a.id_anfitrion
            WHERE r.id_solicitante = ?
            ORDER BY r.creado_en DESC";

    $stmt = $conexion->prepare($sql);
    $stmt->bind_param('i', $idCliente);
} else {
    $sql = "SELECT r.id_reserva, r.fecha_entrada, r.fecha_salida,
                   r.cantidad_huespedes, r.total, r.estado,
                   a.id_alojamiento, a.titulo AS alojamiento,
                   u.nombre AS anfitrion_nombre, u.apellido AS anfitrion_apellido, u.correo AS anfitrion_correo
            FROM reservas r
            JOIN alojamientos a ON a.id_alojamiento = r.id_alojamiento
            JOIN usuarios u ON u.id_usuario = a.id_anfitrion
            WHERE r.id_solicitante = ? AND r.estado = ?
            ORDER BY r.creado_en DESC";

    $stmt = $conexion->prepare($sql);
    $stmt->bind_param('is', $idCliente, $filtro);
}

$stmt->execute();
$reservas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// 4. Si venimos de cancelar una reserva, mostramos el mensaje y lo borramos de la sesión
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$pageTitle = 'Panel del cliente';
include __DIR__ . '/includes/header.php';
?>

<h2 class="mb-3">Mis reservas</h2>

<?php if ($flash): ?>
    <div class="alert alert-<?= htmlspecialchars($flash['tipo']) ?>">
        <?= htmlspecialchars($flash['texto']) ?>
    </div>
<?php endif; ?>

<!-- Botones para filtrar por estado -->
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

<!-- Tabla de reservas -->
<?php if (empty($reservas)): ?>
    <p class="text-muted">No tienes reservas para mostrar.</p>
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
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reservas as $r): ?>
                    <?php
                    // Elegimos el color de la etiqueta según el estado
                    $color = 'dark';
                    if ($r['estado'] === 'pendiente') {
                        $color = 'warning text-dark';
                    } elseif ($r['estado'] === 'aceptada') {
                        $color = 'success';
                    } elseif ($r['estado'] === 'rechazada') {
                        $color = 'secondary';
                    } elseif ($r['estado'] === 'cancelada') {
                        $color = 'danger';
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
                                <form method="POST" action="cancelar_reserva.php" class="d-inline"
                                      onsubmit="return confirm('¿Seguro que quieres cancelar esta reserva?');">
                                    <input type="hidden" name="id_reserva" value="<?= (int) $r['id_reserva'] ?>">
                                    <button class="btn btn-sm btn-outline-danger">Cancelar</button>
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
<?php // CARLOS
require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/config/sesion.php';

// Forzar modo anfitrión
$_SESSION['modo'] = 'anfitrion';

$idAnfitrion = (int) $_SESSION['id_usuario'];
$filtro = $_GET['estado'] ?? 'todas';
$estadosValidos = ['todas', 'pendiente', 'aceptada', 'rechazada', 'cancelada'];
if (!in_array($filtro, $estadosValidos, true)) {
    $filtro = 'todas';
}

// 1. Verificar si el usuario ya tiene alojamientos publicados
$stmtCheck = $conexion->prepare("SELECT COUNT(*) as total FROM alojamientos WHERE id_anfitrion = ?");
$stmtCheck->bind_param('i', $idAnfitrion);
$stmtCheck->execute();
$totalAlojamientos = $stmtCheck->get_result()->fetch_assoc()['total'];

// 2. Consultar reservas (solo si tiene alojamientos)
$reservas = [];
if ($totalAlojamientos > 0) {
    $sql = "SELECT r.id_reserva, r.fecha_entrada, r.fecha_salida, r.cantidad_huespedes,
                   r.total, r.estado, r.creado_en,
                   a.id_alojamiento, a.titulo AS alojamiento,
                   u.nombre AS cliente_nombre, u.apellido AS cliente_apellido, u.correo AS cliente_correo
            FROM reservas r
            JOIN alojamientos a ON a.id_alojamiento = r.id_alojamiento
            JOIN usuarios u ON u.id_usuario = r.id_solicitante
            WHERE a.id_anfitrion = ?";
    $params = [$idAnfitrion];
    $tipos = 'i';

    if ($filtro !== 'todas') {
        $sql .= " AND r.estado = ?";
        $params[] = $filtro;
        $tipos .= 's';
    }
    $sql .= " ORDER BY r.creado_en DESC";

    $stmt = $conexion->prepare($sql);
    $stmt->bind_param($tipos, ...$params);
    $stmt->execute();
    $reservas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$pageTitle = 'Panel del anfitrión';
include __DIR__ . '/includes/header.php';
?>

<?php if ($totalAlojamientos === 0): ?>
    
    <div class="text-center py-5 mt-4">
        <div class="mb-4">
            <span style="font-size: 6rem;">📖</span>
        </div>
        <h2 class="fw-bold mb-3">No tienes ninguna reserva</h2>
        <p class="text-muted fs-5 mb-4">Para recibir reservas, tendrás<br>que completar y publicar tu anuncio.</p>
        <a href="publicar_casa.php" class="btn btn-dark rounded-pill px-5 py-2 fw-bold">
            Completa tu anuncio
        </a>
    </div>

<?php else: ?>
    
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="mb-0">Reservas de mis alojamientos</h2>
        <!-- Botón para ir a ver sus casas -->
        <a href="mis_alojamientos.php" class="btn btn-outline-dark rounded-pill">
            Ver mis alojamientos publicados
        </a>
    </div>

    <?php if ($flash): ?>
        <div class="alert alert-<?= htmlspecialchars($flash['tipo']) ?>">
            <?= htmlspecialchars($flash['texto']) ?>
        </div>
    <?php endif; ?>

    <ul class="nav nav-pills mb-3">
        <?php foreach ($estadosValidos as $e): ?>
            <li class="nav-item">
                <a class="nav-link <?= $filtro === $e ? 'active' : '' ?>"
                   href="panel_anfitrion.php?estado=<?= $e ?>">
                   <?= ucfirst($e) ?>
                </a>
            </li>
        <?php endforeach; ?>
    </ul>

    <?php if (empty($reservas)): ?>
        <!-- Si tiene casas, pero no hay reservas en este filtro -->
        <div class="text-center py-5 bg-white rounded-4 shadow-sm border-0 mt-4">
            <span style="font-size: 4rem;">📅</span>
            <h4 class="mt-3 fw-bold">No hay reservas para mostrar</h4>
            <p class="text-muted">Cuando alguien solicite reservar tu alojamiento, aparecerá aquí.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle bg-white shadow-sm">
                <thead class="table-dark">
                    <tr>
                        <th>Alojamiento</th>
                        <th>Cliente</th>
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
                        <tr>
                            <td><?= htmlspecialchars($r['alojamiento']) ?></td>
                            <td>
                                <?= htmlspecialchars($r['cliente_nombre'] . ' ' . $r['cliente_apellido']) ?><br>
                                <small class="text-muted"><?= htmlspecialchars($r['cliente_correo']) ?></small>
                            </td>
                            <td><?= htmlspecialchars($r['fecha_entrada']) ?></td>
                            <td><?= htmlspecialchars($r['fecha_salida']) ?></td>
                            <td><?= (int) $r['cantidad_huespedes'] ?></td>
                            <td>$<?= number_format((float) $r['total'], 2) ?></td>
                            <td>
                                <span class="badge bg-<?= match($r['estado']) {
                                    'pendiente' => 'warning text-dark',
                                    'aceptada'  => 'success',
                                    'rechazada' => 'secondary',
                                    'cancelada' => 'danger',
                                } ?>"><?= ucfirst($r['estado']) ?></span>
                            </td>
                            <td>
                                <?php if ($r['estado'] === 'pendiente'): ?>
                                    <form method="POST" action="responder_reserva.php" class="d-inline">
                                        <input type="hidden" name="id_reserva" value="<?= (int) $r['id_reserva'] ?>">
                                        <input type="hidden" name="accion" value="aceptar">
                                        <button class="btn btn-sm btn-success">Aceptar</button>
                                    </form>
                                    <form method="POST" action="responder_reserva.php" class="d-inline">
                                        <input type="hidden" name="id_reserva" value="<?= (int) $r['id_reserva'] ?>">
                                        <input type="hidden" name="accion" value="rechazar">
                                        <button class="btn btn-sm btn-outline-secondary">Rechazar</button>
                                    </form>
                                <?php elseif ($r['estado'] === 'aceptada'): ?>
                                    <form method="POST" action="responder_reserva.php" class="d-inline"
                                          onsubmit="return confirm('¿Cancelar esta reserva?');">
                                        <input type="hidden" name="id_reserva" value="<?= (int) $r['id_reserva'] ?>">
                                        <input type="hidden" name="accion" value="cancelar">
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

<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
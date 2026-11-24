<?php
require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/config/sesion.php';

$idCliente = (int) $_SESSION['id_usuario'];
$filtro = $_GET['estado'] ?? 'todas';
$estadosValidos = ['todas', 'pendiente', 'aceptada', 'rechazada', 'cancelada'];

if (!in_array($filtro, $estadosValidos)) {
    $filtro = 'todas';
}
//reservas del usuario
if($filtro==='todas') {
    $sql="SELECT r.id_reserva, r.fecha_entrada, r.fecha_salida,
                r.cantidad_huespedes, r.total, r.estado,
                a.id_alojamiento, a.titulo as alojamiento,
                u.nombre as anfitrion_nombre, u.apellido as anfitrion_apellido, u.correo as anfitrion_correo
            from reservas r
            join alojamientos a on a.id_alojamiento = r.id_alojamiento
            join usuarios u on u.id_usuario = a.id_anfitrion
            where r.id_solicitante = ?
            order by r.creado_en DESC";

    $stmt = $conexion->prepare($sql);
    $stmt->bind_param('i', $idCliente);
} else {
    $sql="SELECT r.id_reserva, r.fecha_entrada, r.fecha_salida,r.cantidad_huespedes, r.total, r.estado,a.id_alojamiento, a.titulo as alojamiento,
            u.nombre as anfitrion_nombre, u.apellido as anfitrion_apellido, u.correo as anfitrion_correo
            from reservas r
            join alojamientos a on a.id_alojamiento = r.id_alojamiento
            join usuarios u on u.id_usuario = a.id_anfitrion
            where r.id_solicitante = ? and r.estado = ?
            order by r.creado_en DESC";

    $stmt = $conexion->prepare($sql);
    $stmt->bind_param('is', $idCliente, $filtro);}

$stmt->execute();
$reservas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

//quitamos reserva si se borro
$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

$pageTitle = 'Panel del cliente';
include __DIR__ . '/includes/header.php';
?>
<h2 class="mb-3">Mis reservas</h2>
<?php if ($flash): ?>
    <div class="alert alert-<?=htmlspecialchars($flash['tipo']) ?>">
        <?=htmlspecialchars($flash['texto']) ?>
    </div>
<?php endif; ?>

//filtrar
<ul class="nav nav-pills mb-3">
    <?php foreach ($estadosValidos as $e): ?>
        <li class="nav-item">
            <a class="nav-link<?=$filtro===$e ?'active':''?>"
               href="panel_cliente.php?estado=<?=$e ?>">
                <?=ucfirst($e)?>
            </a>
        </li>
    <?php endforeach; ?>
</ul>

<!--Tabla de reservas -->
<?php if (empty($reservas)): ?>
    <p class="text-muted">No tienes reservas</p>
<?php else:?>
    <div class="table-responsive">
        <table class="table table-hover align-middle bg-white shadow-sm">
            <thead class="table-dark">
                <tr>
                    <th>Alojamiento</th>
                    <th>Anfitrion</th>
                    <th>Entrada</th>
                    <th>Salida</th>
                    <th>Huespedes</th>
                    <th>Total</th>
                    <th>Estado</th>
                    
                    <th>Chat</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($reservas as $r): ?>
                    <?php
                    //color de etiquetas
                    $color='dark';
                    if($r['estado']==='pendiente') {
                        $color='warning text-dark';
                    }elseif($r['estado']==='aceptada') {
                        $color='success';
                    }elseif($r['estado']==='rechazada') {
                        $color='secondary';
                    }elseif($r['estado']==='cancelada') {
                        $color='danger';
                    }
                    ?>
                    <tr>
                        <td>
                            <a href="listing.php?id=<?=(int)$r['id_alojamiento']?>">
                                <?=htmlspecialchars($r['alojamiento'])?>
                            </a>
                        </td>
                        <td>
                            <?=htmlspecialchars($r['anfitrion_nombre'] . ' ' . $r['anfitrion_apellido']) ?><br>
                            <small class="text-muted"><?=htmlspecialchars($r['anfitrion_correo']) ?></small>
                        </td>
                        <td><?=htmlspecialchars($r['fecha_entrada'])?></td>
                        <td><?=htmlspecialchars($r['fecha_salida'])?></td>
                        <td><?=(int) $r['cantidad_huespedes'] ?></td>
                        <td>$<?=number_format((float) $r['total'],2) ?></td>
                        <td>
                            <span class="badge bg-<?= $color?>">
                                <?= ucfirst($r['estado'])?>
                            </span>
                        </td>
                        <td>
                            <?php if ($r['estado']==='pendiente'||$r['estado']==='aceptada'):?>
                                <form method="POST" action="cancelar_reserva.php" class="d-inline"
                                      onsubmit="return confirm('¿Seguro que quieres cancelar esta reserva?');">
                                    <input type="hidden" name="id_reserva" value="<?= (int) $r['id_reserva'] ?>">
                                    
<a href="chat.php?id_reserva=<?= (int) $r['id_reserva'] ?>" class="btn btn-sm btn-outline-primary">Chat</a>

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
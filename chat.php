<?php
require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/config/sesion.php';

$idUsuario = (int) $_SESSION['id_usuario'];
$idReserva = (int) ($_GET['id_reserva'] ?? 0);

if ($idReserva<=0) {
    exit('Reserva no valida');
}

//buscar cliente y anfitrion de la reserva
$sqlReserva = "SELECT r.id_reserva, r.id_solicitante, r.estado,
                      a.titulo as alojamiento, a.id_anfitrion
               from reservas r
               join alojamientos a on a.id_alojamiento = r.id_alojamiento
               where r.id_reserva = ?";

$stmt = $conexion->prepare($sqlReserva);
$stmt->bind_param('i', $idReserva);
$stmt->execute();
$reserva = $stmt->get_result()->fetch_assoc();

//reserva no existe o no es del usuario
if (!$reserva) {
    exit('La reserva no existe.');
}

$esCliente = ($idUsuario === (int)$reserva['id_solicitante']);
$esAnfitrion = ($idUsuario === (int)$reserva['id_anfitrion']);

// Solo el cliente o el anfitrión de la reserva pueden ver o escribir en este chat
if (!$esCliente && !$esAnfitrion) {
    http_response_code(403);
    exit('No tienes permiso para acceder a este chat.');
}

//guarda el mensaje
if ($_SERVER['REQUEST_METHOD']==='POST') {
    verificar_csrf();
    $texto=trim($_POST['mensaje']??'');

    if ($texto!=='') {
        $sqlInsert="INSERT INTO mensajes (id_reserva,id_emisor, mensaje)
VALUES (?,?,?)";
        $stmtInsert=$conexion->prepare($sqlInsert);
        $stmtInsert->bind_param('iis',$idReserva,$idUsuario,$texto);
        $stmtInsert->execute();
    }
    //para que no se ponga blanca la pantalla al enviar mensaje
    header('Location: chat.php?id_reserva='.$idReserva);
    exit;
}

//consulta los mensajes en orden
$sqlMensajes="SELECT m.id_emisor,m.mensaje, m.creado_en,u.nombre, u.apellido
                from mensajes m
                join usuarios u on u.id_usuario=m.id_emisor
                where m.id_reserva=?
                order by m.creado_en ASC";

$stmtMsg=$conexion->prepare($sqlMensajes);
$stmtMsg->bind_param('i', $idReserva);
$stmtMsg->execute();
$mensajes=$stmtMsg->get_result()->fetch_all(MYSQLI_ASSOC);

//para que te regresa al panel en el que estabas
$urlVolver=$esCliente?'panel_cliente.php':'panel_anfitrion.php';

$pageTitle = 'Chat de reserva';
include __DIR__ . '/includes/header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <!--casa del chat-->
        <h2 class="h4 mb-0">Chat: <?= htmlspecialchars($reserva['alojamiento']) ?></h2>
        <!--reserva y num-->
        <small class="text-muted">Reserva #<?= (int) $reserva['id_reserva'] ?>(<?= htmlspecialchars($reserva['estado']) ?>)</small>
    </div>
    <a href="<?= $urlVolver ?>" class="btn btn-outline-secondary btn-sm">Volver al panel</a>
</div>

<!--mensajes-->
<div class="card shadow-sm mb-3">
    <div class="card-body" id="caja-mensajes" style="height: 400px; overflow-y: auto;">
        <?php if (empty($mensajes)): ?>
            <p class="text-muted text-center mt-5">Aun no hay mensajes</p>
        <?php else:?>
            <?php foreach ($mensajes as $m): ?>
                <?php $esMio=((int)$m['id_emisor']===$idUsuario);?>

                <div class="d-flex mb-2 <?= $esMio ? 'justify-content-end' : 'justify-content-start' ?>">
                    <div class="p-2 rounded <?= $esMio ? 'bg-primary text-white' : 'bg-light border' ?>" style="max-width: 75%;">
                        <div class="small fw-bold">
                            <?= $esMio ? 'Tu' : htmlspecialchars($m['nombre'] . ' ' . $m['apellido']) ?>
                        </div>
                        <div><?= nl2br(htmlspecialchars($m['mensaje'])) ?></div>
                        <div class="small <?= $esMio ? 'text-white-50' : 'text-muted' ?>" style="font-size: 0.75rem;">
                            <?= htmlspecialchars($m['creado_en']) ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!--enciar mensaje-->
<form method="POST" action="chat.php?id_reserva=<?= $idReserva ?>" class="d-flex gap-2">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">
    <input type="text" name="mensaje" class="form-control" placeholder="Escribe un mensaje..." required autocomplete="off">
    <button type="submit" class="btn btn-success">Enviar</button>
</form>

<script>
    // Scrollea hasta el último mensaje
    const caja = document.getElementById('caja-mensajes');
    if (caja) {
        caja.scrollTop = caja.scrollHeight;
    }
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
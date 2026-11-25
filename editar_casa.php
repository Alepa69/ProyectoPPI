<?php
require_once __DIR__ . '/config/sesion.php';
require_once __DIR__ . '/includes/reservas.php';

$idUsuario = (int) $_SESSION['id_usuario'];

// Validar ID
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    header('Location: mis_alojamientos.php');
    exit;
}

// Verificar que la casa exista y sea del usuario
$stmt = $conexion->prepare("SELECT * FROM alojamientos WHERE id_alojamiento = ? AND id_anfitrion = ?");
$stmt->bind_param('ii', $id, $idUsuario);
$stmt->execute();
$casa = $stmt->get_result()->fetch_assoc();

if (!$casa) {
    http_response_code(404);
    exit('Alojamiento no encontrado o no tienes permiso para editarlo.');
}

// Obtener tipos para el select
$resultadoTipos = $conexion->query("SELECT id_tipo, nombre FROM tipos_alojamiento ORDER BY id_tipo ASC");
$tipos = $resultadoTipos ? $resultadoTipos->fetch_all(MYSQLI_ASSOC) : [];

$mensajeError = "";

// Procesar el formulario
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_tipo = (int)($_POST['id_tipo'] ?? 0);
    $titulo = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $departamento = trim($_POST['departamento'] ?? '');
    $municipio = trim($_POST['municipio'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');
    $capacidad = (int)($_POST['capacidad'] ?? 0);
    $precio = (float)($_POST['precio'] ?? 0);
    $latitud = ($_POST['latitud'] ?? '') !== '' ? (float) $_POST['latitud'] : 13.6929403;
    $longitud = ($_POST['longitud'] ?? '') !== '' ? (float) $_POST['longitud'] : -89.2181911;

    if (!tokenFormularioValido()) {
        $mensajeError = "Recarga la página antes de guardar los cambios.";
    } elseif (empty($titulo) || empty($descripcion) || empty($departamento) || empty($municipio) || empty($direccion)) {
        $mensajeError = "Por favor completa todos los campos de texto obligatorios.";
    } elseif ($id_tipo <= 0) {
        $mensajeError = "Por favor selecciona un tipo de alojamiento.";
    } elseif ($capacidad <= 0 || $precio <= 0) {
        $mensajeError = "La capacidad y el precio deben ser mayores a 0.";
    } else {
        $conexion->begin_transaction();
        try {
            $actual = bloquearAlojamiento($conexion, $id);
            if ((int) $actual['id_anfitrion'] !== $idUsuario) {
                throw new DomainException('Este alojamiento no pertenece a tu cuenta.');
            }
            $horaActual = relojReservas()->format('Y-m-d H:i:s');
            $reservaMayor = $conexion->prepare("
                SELECT 1 FROM reservas WHERE id_alojamiento = ? AND estado = 'aceptada'
                    AND cantidad_huespedes > ? AND TIMESTAMP(fecha_salida, ?) > ?
                LIMIT 1 FOR UPDATE
            ");
            $reservaMayor->bind_param('iiss', $id, $capacidad, $actual['hora_checkout'], $horaActual);
            $reservaMayor->execute();
            if ($reservaMayor->get_result()->fetch_row()) {
                throw new DomainException('La capacidad no puede ser menor a la de una reserva confirmada que sigue vigente.');
            }

            $stmtUpdate = $conexion->prepare("
                UPDATE alojamientos SET id_tipo = ?, titulo = ?, descripcion = ?, departamento = ?,
                    municipio = ?, direccion = ?, latitud = ?, longitud = ?,
                    capacidad = ?, capacidad_personas = ?, precio_persona_noche = ?, precio_noche = ?
                WHERE id_alojamiento = ? AND id_anfitrion = ?
            ");
            $stmtUpdate->bind_param(
                'isssssddiiddii',
                $id_tipo, $titulo, $descripcion, $departamento, $municipio, $direccion, $latitud, $longitud,
                $capacidad, $capacidad, $precio, $precio, $id, $idUsuario
            );
            $stmtUpdate->execute();
            $conexion->commit();
            $_SESSION['alojamiento_flash'] = ['tipo' => 'success', 'texto' => 'Cambios guardados en el alojamiento.'];
            header('Location: mis_alojamientos.php', true, 303);
            exit;
        } catch (DomainException $error) {
            $conexion->rollback();
            $mensajeError = $error->getMessage();
        } catch (Throwable $error) {
            $conexion->rollback();
            error_log('Editar alojamiento: ' . $error->getMessage());
            $mensajeError = "Error al guardar los cambios en la base de datos.";
        }
    }
}

$pageTitle = 'Editar: ' . $casa['titulo'];
?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<?php include __DIR__ . '/includes/header.php'; ?>

<div class="row justify-content-center">
    <div class="col-lg-8 col-md-10">
        
        <!-- Botón volver -->
        <div class="mb-3">
            <a href="mis_alojamientos.php" class="btn btn-outline-secondary rounded-pill btn-sm">
                &larr; Volver a mis alojamientos
            </a>
        </div>

        <div class="card shadow-sm border-0">
            <div class="card-header bg-primary text-white py-3">
                <h5 class="card-title mb-0 fw-semibold">Editar alojamiento</h5>
            </div>
            <div class="card-body p-4">

                <?php if (!empty($mensajeError)): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($mensajeError) ?></div>
                <?php endif; ?>

                <form method="POST" action="editar_casa.php?id=<?= $id ?>">
                    <input type="hidden" name="csrf" value="<?= e(tokenFormulario()) ?>">
                    <div class="row g-3">
                        <div class="col-12">
                            <label for="titulo" class="form-label fw-semibold">Título del alojamiento *</label>
                            <input type="text" class="form-control" id="titulo" name="titulo" value="<?= htmlspecialchars($casa['titulo']) ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label for="id_tipo" class="form-label fw-semibold">Tipo de alojamiento *</label>
                            <select class="form-select" id="id_tipo" name="id_tipo" required>
                                <?php foreach ($tipos as $tipo): ?>
                                    <option value="<?= (int)$tipo['id_tipo'] ?>" <?= $casa['id_tipo'] == $tipo['id_tipo'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($tipo['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label for="capacidad" class="form-label fw-semibold">Capacidad máxima (personas) *</label>
                            <input type="number" class="form-control" id="capacidad" name="capacidad" min="1" max="100" value="<?= (int)$casa['capacidad'] ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label for="departamento" class="form-label fw-semibold">Departamento *</label>
                            <input type="text" class="form-control" id="departamento" name="departamento" value="<?= htmlspecialchars($casa['departamento']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label for="municipio" class="form-label fw-semibold">Municipio *</label>
                            <input type="text" class="form-control" id="municipio" name="municipio" value="<?= htmlspecialchars($casa['municipio']) ?>" required>
                        </div>

                        <div class="col-12">
                            <label for="direccion" class="form-label fw-semibold">Dirección *</label>
                            <input type="text" class="form-control" id="direccion" name="direccion" value="<?= htmlspecialchars($casa['direccion']) ?>" required>
                        </div>

                        <!-- Mapa -->
                        <div class="col-12 mt-3">
                            <div class="card bg-light border">
                                <div class="card-body">
                                    <label class="form-label fw-semibold">Ubicación en el mapa *</label>
                                    <div id="mapaSelector" style="height: 300px; width: 100%; border-radius: 8px;" class="border mb-3"></div>
                                    <div class="row g-2">
                                        <div class="col-md-6">
                                            <label for="latitud" class="form-label small fw-semibold">Latitud</label>
                                            <input type="number" step="any" class="form-control form-control-sm" id="latitud" name="latitud" value="<?= htmlspecialchars($casa['latitud']) ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="longitud" class="form-label small fw-semibold">Longitud</label>
                                            <input type="number" step="any" class="form-control form-control-sm" id="longitud" name="longitud" value="<?= htmlspecialchars($casa['longitud']) ?>" required>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label for="precio" class="form-label fw-semibold">Precio por persona y noche (USD) *</label>
                            <input type="number" step="0.01" min="0.01" class="form-control" id="precio" name="precio" value="<?= htmlspecialchars($casa['precio_persona_noche']) ?>" required>
                        </div>

                        <div class="col-12">
                            <label for="descripcion" class="form-label fw-semibold">Descripción del alojamiento *</label>
                            <textarea class="form-control" id="descripcion" name="descripcion" rows="4" required><?= htmlspecialchars($casa['descripcion']) ?></textarea>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top">
                        <a href="mis_alojamientos.php" class="btn btn-outline-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-primary px-4">Guardar Cambios</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
const inputLat = document.getElementById('latitud');
const inputLng = document.getElementById('longitud');
const latInicial = parseFloat(inputLat.value) || 13.6929403;
const lngInicial = parseFloat(inputLng.value) || -89.2181911;
const mapa = L.map('mapaSelector').setView([latInicial, lngInicial], 13);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(mapa);
const marcador = L.marker([latInicial, lngInicial], { draggable: true }).addTo(mapa);

function actualizarInputs(lat, lng) {
    inputLat.value = Number(lat).toFixed(7);
    inputLng.value = Number(lng).toFixed(7);
}
marcador.on('dragend', function (e) {
    const pos = e.target.getLatLng();
    actualizarInputs(pos.lat, pos.lng);
});
mapa.on('click', function (e) {
    marcador.setLatLng(e.latlng);
    actualizarInputs(e.latlng.lat, e.latlng.lng);
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>

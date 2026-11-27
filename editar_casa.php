<?php
require_once __DIR__ . '/config/sesion.php'; //verificacion que el usuario haya iniciado sesion
require_once __DIR__ . '/config/conexion.php';
//garantiza los datos que llama faltan, el script se detenga 
$idUsuario = (int) $_SESSION['id_usuario'];

// validar id de usuario 
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    header('Location: mis_alojamientos.php');
    exit;
}

// verifica que la casa exista y sea del usuario
$stmt = $conexion->prepare("SELECT * FROM alojamientos WHERE id_alojamiento = ? AND id_anfitrion = ?");
$stmt->bind_param('ii', $id, $idUsuario);
$stmt->execute();
$casa = $stmt->get_result()->fetch_assoc();

if (!$casa) {
    http_response_code(404);
    exit('Alojamiento no encontrado o no tienes permiso para editarlo.');
}

// cargar tipos de alojamiento de alojamiento
$resTiposAlojamiento = $conexion->query("SELECT id_tipo, nombre FROM tipos_alojamiento ORDER BY id_tipo ASC");
$tipos = $resTiposAlojamiento ? $resTiposAlojamiento->fetch_all(MYSQLI_ASSOC) : [];
//metodos de pago 
$resMetodos = $conexion->query("SELECT id_metodo_pago, nombre FROM metodos_pago ORDER BY id_metodo_pago ASC");
$metodosPago = $resMetodos ? $resMetodos->fetch_all(MYSQLI_ASSOC) : [];

// obtener metodos de pago seleccionados para este alojamiento (consulta preparada)
$stmtMtdPgo = $conexion->prepare("SELECT id_metodo_pago FROM alojamiento_metodo_pago WHERE id_alojamiento = ?");
$stmtMtdPgo->bind_param('i', $id);
//ejecutar la consulta 
$stmtMtdPgo->execute();
// extraer los metodos de pago seleccionados en un array
$metodosSeleccionados = array_column($stmtMtdPgo->get_result()->fetch_all(MYSQLI_ASSOC), 'id_metodo_pago');

$departamentosSV = [
    'Ahuachapán', 'Cabañas', 'Chalatenango', 'Cuscatlán',
    'La Libertad', 'La Paz', 'La Unión', 'Morazán',
    'San Miguel', 'San Salvador', 'San Vicente', 'Santa Ana',
    'Sonsonate', 'Usulután'
];

$mensajeError = "";

// proceso completo de la edicion del formulario, validacion de datos y actualizacion de la base de datos
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf(); //verifiacion de formulario propio y no de sitio externo

    $id_tipo = (int) ($_POST['id_tipo'] ?? 0);
    $titulo = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $departamento = trim($_POST['departamento'] ?? '');
    $municipio = trim($_POST['municipio'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');
    $capacidad = (int) ($_POST['capacidad'] ?? 0);
    $precio = (float) ($_POST['precio'] ?? 0);
    $latRaw = trim($_POST['latitud'] ?? '');
    $lngRaw = trim($_POST['longitud'] ?? '');
    $latitud = ($latRaw !== '' && is_numeric($latRaw)) ? (float) $latRaw : null;
    $longitud = ($lngRaw !== '' && is_numeric($lngRaw)) ? (float) $lngRaw : null;

    $tiposValidosIds = array_map('intval', array_column($tipos, 'id_tipo'));
    $metodosValidosIds = array_map('intval', array_column($metodosPago, 'id_metodo_pago'));

    $nuevosMtdosPago = [];
    if (!empty($_POST['metodos_pago']) && is_array($_POST['metodos_pago'])) {
        foreach ($_POST['metodos_pago'] as $idMp) {
            $entero = (int) $idMp;
            if ($entero > 0 && in_array($entero, $metodosValidosIds, true)) {
                $nuevosMtdosPago[] = $entero;
            }
        }
        $nuevosMtdosPago = array_unique($nuevosMtdosPago);
    }

    if (empty($titulo) || empty($descripcion) || empty($departamento) || empty($municipio) || empty($direccion)) {
        $mensajeError = "Por favor completa todos los campos de texto obligatorios.";
    } elseif (mb_strlen($titulo) > 150 || mb_strlen($departamento) > 100 || mb_strlen($municipio) > 100 || mb_strlen($direccion) > 255) {
        $mensajeError = "Título (máx. 150), departamento/municipio (máx. 100) o dirección (máx. 255) superan el límite.";
    } elseif ($id_tipo <= 0 || !in_array($id_tipo, $tiposValidosIds, true)) {
        $mensajeError = "Por favor selecciona un tipo de alojamiento válido.";
    } elseif ($latitud === null || $longitud === null || $latitud < -90 || $latitud > 90 || $longitud < -180 || $longitud > 180) {
        $mensajeError = "Por favor marca una ubicación válida en el mapa.";
    } elseif ($capacidad < 1 || $capacidad > 100 || $precio <= 0 || $precio > 99999) {
        $mensajeError = "La capacidad (1 a 100) y el precio ($0.01 a $99,999) deben estar dentro del rango válido.";
    } elseif (empty($nuevosMtdosPago)) {
        $mensajeError = "Selecciona al menos un método de pago informativo.";
    } else {
        // Evitar reducir capacidad por debajo de reservas aceptadas vigentes o futuras
        $stmtCap = $conexion->prepare("
            SELECT COALESCE(MAX(cantidad_huespedes), 0) AS max_huespedes
            FROM reservas
            WHERE id_alojamiento = ? AND estado = 'aceptada' AND fecha_salida >= CURDATE()
        ");
        $stmtCap->bind_param('i', $id);
        $stmtCap->execute();
        $maxReservado = (int) $stmtCap->get_result()->fetch_assoc()['max_huespedes'];

        if ($capacidad < $maxReservado) {
            $mensajeError = "No puedes reducir la capacidad a {$capacidad} porque ya tienes una reserva aceptada para {$maxReservado} huéspedes.";
        } else {
            $conexion->begin_transaction();
            try {
                // Actualizar columnas principales y mantener sincronizadas las heredadas
                $sql = "UPDATE alojamientos SET
                            id_tipo = ?, titulo = ?, descripcion = ?, departamento = ?,
                            municipio = ?, ciudad = ?, direccion = ?, latitud = ?, longitud = ?,
                            capacidad = ?, capacidad_personas = ?, precio_persona_noche = ?, precio_noche = ?
                        WHERE id_alojamiento = ? AND id_anfitrion = ?";

                $stmtUpdate = $conexion->prepare($sql);
                $stmtUpdate->bind_param(
                    "issssssddiiddii",
                    $id_tipo, $titulo, $descripcion, $departamento,
                    $municipio, $municipio, $direccion, $latitud, $longitud,
                    $capacidad, $capacidad, $precio, $precio, $id, $idUsuario
                );
                $stmtUpdate->execute();

                // Actualizar métodos de pago informativos
                $stmtDel = $conexion->prepare("DELETE FROM alojamiento_metodo_pago WHERE id_alojamiento = ?");
                $stmtDel->bind_param('i', $id);
                $stmtDel->execute();

                $stmtIns = $conexion->prepare("INSERT INTO alojamiento_metodo_pago (id_alojamiento, id_metodo_pago) VALUES (?, ?)");
                foreach ($nuevosMtdosPago as $mpId) {
                    $stmtIns->bind_param('ii', $id, $mpId);
                    $stmtIns->execute();
                }

                $conexion->commit();
                $_SESSION['flash'] = ['tipo' => 'success', 'texto' => 'Cambios guardados correctamente.'];
                header('Location: mis_alojamientos.php');
                exit;
            } catch (Throwable $e) {
                $conexion->rollback();
                $mensajeError = "Error al guardar los cambios en la base de datos.";
            }
        }
    }

    // Mantener selección en caso de error
    $metodosSeleccionados = $nuevosMtdosPago;
}

$pageTitle = 'Editar: ' . $casa['titulo'];
include __DIR__ . '/includes/header.php';
?>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

<div class="row justify-content-center">
    <div class="col-lg-8 col-md-10">

        <!-- Botón volver -->
        <div class="mb-3">
            <a href="mis_alojamientos.php" class="btn btn-outline-secondary rounded-pill btn-sm">
                &larr; Volver a mis alojamientos
            </a>
        </div>

        <?php if ($casa['estado'] === 'bloqueado'): ?>
            <div class="alert alert-danger">
                <strong>Publicación desactivada por administración.</strong>
                <?= !empty($casa['motivo_bloqueo']) ? 'Motivo: ' . htmlspecialchars($casa['motivo_bloqueo']) : '' ?>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm border-0">
            <div class="card-header bg-primary text-white py-3">
                <h5 class="card-title mb-0 fw-semibold">Editar alojamiento</h5>
            </div>
            <div class="card-body p-4">

                <?php if (!empty($mensajeError)): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($mensajeError) ?></div>
                <?php endif; ?>

                <form method="POST" action="editar_casa.php?id=<?= $id ?>">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">

                    <div class="row g-3">
                        <div class="col-12">
                            <label for="titulo" class="form-label fw-semibold">Título del alojamiento *</label>
                            <input type="text" class="form-control" id="titulo" name="titulo" value="<?= htmlspecialchars($casa['titulo']) ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label for="id_tipo" class="form-label fw-semibold">Tipo de alojamiento *</label>
                            <select class="form-select" id="id_tipo" name="id_tipo" required>
                                <?php foreach ($tipos as $tipo): ?>
                                    <option value="<?= (int) $tipo['id_tipo'] ?>" <?= $casa['id_tipo'] == $tipo['id_tipo'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($tipo['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label for="capacidad" class="form-label fw-semibold">Capacidad máxima (personas) *</label>
                            <input type="number" class="form-control" id="capacidad" name="capacidad" min="1" max="100" value="<?= (int) $casa['capacidad'] ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label for="departamento" class="form-label fw-semibold">Departamento *</label>
                            <select class="form-select" id="departamento" name="departamento" required>
                                <?php if (!in_array($casa['departamento'], $departamentosSV, true)): ?>
                                    <option value="<?= htmlspecialchars($casa['departamento']) ?>" selected><?= htmlspecialchars($casa['departamento']) ?></option>
                                <?php endif; ?>
                                <?php foreach ($departamentosSV as $dep): ?>
                                    <option value="<?= htmlspecialchars($dep) ?>" <?= $casa['departamento'] === $dep ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($dep) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="municipio" class="form-label fw-semibold">Ciudad / Municipio *</label>
                            <input type="text" class="form-control" id="municipio" name="municipio" value="<?= htmlspecialchars($casa['ciudad'] ?: $casa['municipio']) ?>" required>
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
                                    <div id="mapaSelector" style="height: 300px; width: 100%; border-radius: 8px;" class="border"></div>
                                    <input type="hidden" id="latitud" name="latitud" value="<?= htmlspecialchars((string) ($casa['latitud'] ?? '')) ?>">
                                    <input type="hidden" id="longitud" name="longitud" value="<?= htmlspecialchars((string) ($casa['longitud'] ?? '')) ?>">
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label for="precio" class="form-label fw-semibold">Precio por persona / noche ($ USD) *</label>
                            <input type="number" step="0.01" min="0.01" class="form-control" id="precio" name="precio" value="<?= htmlspecialchars($casa['precio_persona_noche']) ?>" required>
                        </div>

                        <div class="col-12">
                            <label class="form-label fw-semibold d-block">Métodos de pago informativos aceptados *</label>
                            <div class="row g-2">
                                <?php foreach ($metodosPago as $mp): ?>
                                    <div class="col-md-6">
                                        <div class="form-check border rounded p-2 ps-4">
                                            <input class="form-check-input" type="checkbox" name="metodos_pago[]"
                                                   value="<?= (int) $mp['id_metodo_pago'] ?>"
                                                   id="mp_<?= (int) $mp['id_metodo_pago'] ?>"
                                                   <?= in_array((int) $mp['id_metodo_pago'], $metodosSeleccionados, true) ? 'checked' : '' ?>>
                                            <label class="form-check-label fw-semibold" for="mp_<?= (int) $mp['id_metodo_pago'] ?>">
                                                <?= htmlspecialchars($mp['nombre']) ?>
                                            </label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
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
actualizarInputs(latInicial, lngInicial);
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

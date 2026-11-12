<?php
require_once __DIR__ . '/config/sesion.php';
require_once __DIR__ . '/config/conexion.php';

$mensajeError = "";
if (empty($_SESSION['id_usuario'])) {
    header('Location: modulos/acceso/login.php');
    exit;
}
$id_anfitrion = (int) $_SESSION['id_usuario'];

//tipo alojamiento
$resultadoTipos = $conexion->query("select id_tipo, nombre from tipos_alojamiento order by id_tipo asc");
$tipos = $resultadoTipos ? $resultadoTipos->fetch_all(MYSQLI_ASSOC) : [];

//guardarla casa
if ($_SERVER['REQUEST_METHOD']==='POST') {
    $id_tipo=(int)($_POST['id_tipo']?? 0);
    $titulo=trim($_POST['titulo'] ?? '');
    $descripcion=trim($_POST['descripcion']??'');
    $departamento=trim($_POST['departamento']??'');
    $municipio=trim($_POST['municipio']??'');
    $direccion=trim($_POST['direccion']?? '');
    $capacidad=(int)($_POST['capacidad']?? 0);
    $precio=(float)($_POST['precio']?? 0);

    
    $latitud=($_POST['latitud'] ?? '') !== '' ? (float) $_POST['latitud'] : 13.6929403;//san salvador
    $longitud=($_POST['longitud'] ?? '') !== '' ? (float) $_POST['longitud'] : -89.2181911;

    $foto='';
    if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
        $extension = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
        $permitidas = ['jpg', 'jpeg', 'png', 'webp'];

        if (!in_array($extension, $permitidas)) {
            $mensajeError = "Solo se permiten imágenes en formato JPG, PNG o WEBP.";
        } else {
            $carpeta = __DIR__ . '/uploads/casas/';
            if (!is_dir($carpeta)) {
                mkdir($carpeta, 0777, true);
            }

            $nombreArchivo = 'casa_' . uniqid() . '.' . $extension;
            if (move_uploaded_file($_FILES['foto']['tmp_name'], $carpeta . $nombreArchivo)) {
                $foto = 'uploads/casas/' . $nombreArchivo;
            } else {
                $mensajeError = "No se pudo guardar la imagen en la carpeta.";
            }
        }
    }
//validqciones
    if ($mensajeError !== "") {

    } elseif (empty($titulo) || empty($descripcion) || empty($departamento) || empty($municipio) || empty($direccion)) {
        $mensajeError = "Por favor completa todos los campos de texto obligatorios.";
    } elseif ($id_tipo <= 0) {
        $mensajeError = "Por favor selecciona un tipo de alojamiento.";
    } elseif ($capacidad <= 0 || $precio <= 0) {
        $mensajeError = "La capacidad y el precio deben ser mayores a 0.";
    } else {
    
        $sql=
        "insert into alojamientos (
        id_anfitrion, id_tipo, titulo, descripcion, pais,
        departamento, municipio, direccion, latitud, longitud,capacidad, precio_persona_noche, estado) values (?, ?, ?, ?, 'El Salvador', ?, ?, ?, ?, ?, ?, ?, 'publicado')";

        $stmt = $conexion->prepare($sql);
        $stmt->bind_param(
            "iisssssddid",
            $id_anfitrion,
            $id_tipo,
            $titulo,
            $descripcion,
            $departamento,
            $municipio,
            $direccion,
            $latitud,
            $longitud,
            $capacidad,
            $precio
        );

        if ($stmt->execute()) {
            $id_alojamiento = $stmt->insert_id;
//guarda la ruta de la foto
            if (!empty($foto)) {
                $sqlFoto = "insert into fotos_alojamiento (id_alojamiento, ruta_archivo, posicion) values (?, ?, 1)";
                $stmtFoto = $conexion->prepare($sqlFoto);
                $stmtFoto->bind_param("is", $id_alojamiento, $foto);
                $stmtFoto->execute();
            }

            header('Location: index.php');
            exit;
        } else {
            $mensajeError = "Error al guardar la casa en la base de datos.";
        }
    }
}

$pageTitle = 'Publicar nueva casa';
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

<?php include __DIR__ . '/includes/header.php'; ?>

<div class="row justify-content-center">
    <div class="col-lg-8 col-md-10">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-primary text-white py-3">
                <h5 class="card-title mb-0 fw-semibold">Publicar un nuevo alojamiento</h5>
            </div>
            <div class="card-body p-4">

                <?php if (!empty($mensajeError)): ?>
                    <div class="alert alert-danger">
                        <?= htmlspecialchars($mensajeError) ?>
                    </div>
                <?php endif; ?>

                <!--Para subir archivos ;p del multipart-->
                <form method="POST" action="publicar_casa.php" enctype="multipart/form-data">
                    <div class="row g-3">

                        <div class="col-12">
                            <label for="titulo" class="form-label fw-semibold">Título del alojamiento *</label>
                            <input type="text" class="form-control" id="titulo" name="titulo"
                                   value="<?= htmlspecialchars($_POST['titulo'] ?? '') ?>"
                                   placeholder="Ej: Hermosa cabaña con vista al lago" required>
                        </div>

                        <div class="col-md-6">
                            <label for="id_tipo" class="form-label fw-semibold">Tipo de alojamiento *</label>
                            <select class="form-select" id="id_tipo" name="id_tipo" required>
                                <option value="">Selecciona un tipo...</option>
                                <?php foreach ($tipos as $tipo): ?>
                                    <option value="<?= (int)$tipo['id_tipo'] ?>"
                                        <?= (isset($_POST['id_tipo']) && (int)$_POST['id_tipo'] === (int)$tipo['id_tipo']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($tipo['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label for="capacidad" class="form-label fw-semibold">Capacidad máxima (personas) *</label>
                            <input type="number" class="form-control" id="capacidad" name="capacidad"
                                   min="1" max="100"
                                   value="<?= (int)($_POST['capacidad'] ?? 1) ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label for="departamento" class="form-label fw-semibold">Departamento *</label>
                            <input type="text" class="form-control" id="departamento" name="departamento"
                                   value="<?= htmlspecialchars($_POST['departamento'] ?? '') ?>"
                                   placeholder="Ej: San Salvador, La Libertad..." required>
                        </div>
                        <div class="col-md-6">
                            <label for="municipio" class="form-label fw-semibold">Municipio *</label>
                            <input type="text" class="form-control" id="municipio" name="municipio"
                                   value="<?= htmlspecialchars($_POST['municipio'] ?? '') ?>"
                                   placeholder="Ej: Santa Tecla, San Vicente..." required>
                        </div>

                        <div class="col-12">
                            <label for="direccion" class="form-label fw-semibold">Direccion*</label>
                            <input type="text" class="form-control" id="direccion" name="direccion"
                                   value="<?= htmlspecialchars($_POST['direccion'] ?? '') ?>"
                                   placeholder="Ej: Colonia Escalón, Calle El Mirador #123" required>
                        </div>

                        <!--mapaaaa-->
                        <div class="col-12 mt-3">
                            <div class="card bg-light border">
                                <div class="card-body">
                                    <label class="form-label fw-semibold">Ubicacion en el mapa *</label>
                                    <p class="text-muted small mb-2">
                                        Clic en el mapa para marcar una ubicacion
                                    </p>

                                    <div id="mapaSelector" style="height: 300px; width: 100%; border-radius: 8px;" class="border mb-3"></div>

                                    <div class="row g-2">
                                        <div class="col-md-6">
                                            <label for="latitud" class="form-label small fw-semibold">Latitud</label>
                                            <input type="number" step="any" class="form-control form-control-sm" id="latitud" name="latitud"
                                                   value="<?= htmlspecialchars($_POST['latitud'] ?? '13.6929403') ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="longitud" class="form-label small fw-semibold">Longitud</label>
                                            <input type="number" step="any" class="form-control form-control-sm" id="longitud" name="longitud"
                                                   value="<?= htmlspecialchars($_POST['longitud'] ?? '-89.2181911') ?>" required>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label for="precio" class="form-label fw-semibold">Precio por noche ($ USD) *</label>
                            <input type="number" step="0.01" min="0.01" class="form-control" id="precio" name="precio"
                                   value="<?= htmlspecialchars($_POST['precio'] ?? '') ?>"
                                   placeholder="0.00" required>
                        </div>
                        <div class="col-md-6">
                            <label for="foto" class="form-label fw-semibold">Subir foto principal (Opcional)</label>
                            <input type="file" class="form-control" id="foto" name="foto" accept="image/jpeg,image/png,image/webp">
                        </div>

                        <div class="col-12">
                            <label for="descripcion" class="form-label fw-semibold">Descripción del alojamiento *</label>
                            <textarea class="form-control" id="descripcion" name="descripcion" rows="4"
                                      placeholder="Describe los espacios, comodidades y reglas del lugar..." required><?= htmlspecialchars($_POST['descripcion'] ?? '') ?></textarea>
                        </div>

                    </div>

                    <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top">
                        <a href="index.php" class="btn btn-outline-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-primary px-4">Guardar y Publicar</button>
                    </div>
                </form>

            </div>
        </div>
    </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>//js
const inputLat=document.getElementById('latitud');
const inputLng=document.getElementById('longitud');
const latInicial=parseFloat(inputLat.value) || 13.6929403;
const lngInicial=parseFloat(inputLng.value) || -89.2181911;
const mapa=L.map('mapaSelector').setView([latInicial, lngInicial], 13);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(mapa);
const marcador = L.marker([latInicial, lngInicial], { draggable: true }).addTo(mapa);

function actualizarInputs(lat, lng) {
    inputLat.value = Number(lat).toFixed(7);
    inputLng.value = Number(lng).toFixed(7);
}
marcador.on('dragend',function (e) {
    const pos = e.target.getLatLng();
    actualizarInputs(pos.lat, pos.lng);
});
mapa.on('click',function (e) {
    marcador.setLatLng(e.latlng);
    actualizarInputs(e.latlng.lat, e.latlng.lng);
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
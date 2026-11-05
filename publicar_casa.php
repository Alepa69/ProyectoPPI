<?php

require_once __DIR__ . '/config/conexion.php';

$mensajeError = "";

// 1. Obtener tipos de alojamiento (Casa, Apartamento, Habitación)
$tipos = [];
$resultadoTipos = $conexion->query("SELECT id_tipo, nombre FROM tipos_alojamiento ORDER BY id_tipo ASC");
if ($resultadoTipos && $resultadoTipos->num_rows > 0) {
    while ($fila = $resultadoTipos->fetch_assoc()) {
        $tipos[] = $fila;
    }
} else {
    // Si la tabla de catálogos estuviera vacía, sembramos los tipos por defecto
    $conexion->query("INSERT INTO tipos_alojamiento (nombre) VALUES ('Casa'), ('Apartamento'), ('Habitacion')");
    $resultadoTipos = $conexion->query("SELECT id_tipo, nombre FROM tipos_alojamiento ORDER BY id_tipo ASC");
    if ($resultadoTipos) {
        while ($fila = $resultadoTipos->fetch_assoc()) {
            $tipos[] = $fila;
        }   
    }
}

// 2. Obtener un ID de anfitrión válido de la tabla usuarios
$id_anfitrion = null;
$resultadoUsuario = $conexion->query("SELECT id_usuario FROM usuarios WHERE estado = 'activo' LIMIT 1");
if ($resultadoUsuario && $filaUsuario = $resultadoUsuario->fetch_assoc()) {
    $id_anfitrion = (int)$filaUsuario['id_usuario'];
} else {
    // Si no hay usuarios activos, buscar cualquier usuario existente
    $resultadoCualquierUsuario = $conexion->query("SELECT id_usuario FROM usuarios LIMIT 1");
    if ($resultadoCualquierUsuario && $filaCualquierUsuario = $resultadoCualquierUsuario->fetch_assoc()) {
        $id_anfitrion = (int)$filaCualquierUsuario['id_usuario'];
    } else {
        // Crear un anfitrión de prueba si la tabla usuarios estuviera vacía
        $conexion->query("INSERT INTO usuarios (nombre, apellido, correo, password_hash, rol, estado) 
                          VALUES ('Anfitrión', 'Prueba', 'anfitrion@haloja.local', '$2y$10$abcdefghijklmnopqrstuv', 'usuario', 'activo')");
        $id_anfitrion = (int)$conexion->insert_id;
    }
}

// 3. Procesar el formulario cuando se envía por POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_tipo = isset($_POST['id_tipo']) ? (int)$_POST['id_tipo'] : 0;
    $titulo = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $departamento = trim($_POST['departamento'] ?? '');
    $municipio = trim($_POST['municipio'] ?? '');
    $direccion = trim($_POST['direccion'] ?? '');
    $capacidad = isset($_POST['capacidad']) ? (int)$_POST['capacidad'] : 0;
    $precio = isset($_POST['precio']) ? (float)$_POST['precio'] : 0.0;
    $foto = trim($_POST['foto'] ?? '');

    // Coordenadas recibidas del mapa o campos de texto
    $latitud = isset($_POST['latitud']) && $_POST['latitud'] !== '' ? (float)$_POST['latitud'] : 13.6929403;
    $longitud = isset($_POST['longitud']) && $_POST['longitud'] !== '' ? (float)$_POST['longitud'] : -89.2181911;

    // Validaciones
    if (empty($titulo)) {
        $mensajeError = "Por favor ingresa el título del alojamiento.";
    } elseif ($id_tipo <= 0) {
        $mensajeError = "Por favor selecciona un tipo de alojamiento.";
    } elseif (empty($descripcion)) {
        $mensajeError = "Por favor ingresa una descripción para el alojamiento.";
    } elseif (empty($departamento) || empty($municipio)) {
        $mensajeError = "El departamento y municipio son obligatorios.";
    } elseif (empty($direccion)) {
        $mensajeError = "Por favor ingresa la dirección del alojamiento.";
    } elseif ($capacidad <= 0) {
        $mensajeError = "La capacidad máxima debe ser de al menos 1 persona.";
    } elseif ($precio <= 0) {
        $mensajeError = "El precio por noche debe ser mayor a 0.";
    } elseif ($latitud < -90 || $latitud > 90 || $longitud < -180 || $longitud > 180) {
        $mensajeError = "Las coordenadas geográficas ingresadas no son válidas.";
    } else {
        try {
            // Inserción segura con sentencias preparadas
            $sentencia = $conexion->prepare("
                INSERT INTO alojamientos (
                    id_anfitrion, id_tipo, titulo, descripcion, pais,
                    departamento, municipio, direccion, latitud, longitud,
                    capacidad, precio_persona_noche, estado
                ) VALUES (?, ?, ?, ?, 'El Salvador', ?, ?, ?, ?, ?, ?, ?, 'publicado')
            ");

            if (!$sentencia) {
                throw new Exception("Error al preparar la consulta: " . $conexion->error);
            }

            $sentencia->bind_param(
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

            if ($sentencia->execute()) {
                $id_alojamiento = $sentencia->insert_id;
                $sentencia->close();

                // Si se proporcionó una URL de foto, guardarla en fotos_alojamiento (posicion = 1)
                if (!empty($foto)) {
                    $sentenciaFoto = $conexion->prepare("
                        INSERT INTO fotos_alojamiento (id_alojamiento, ruta_archivo, posicion)
                        VALUES (?, ?, 1)
                    ");
                    if ($sentenciaFoto) {
                        $sentenciaFoto->bind_param("is", $id_alojamiento, $foto);
                        $sentenciaFoto->execute();
                        $sentenciaFoto->close();
                    }
                }

                // Redireccionar al inicio
                header('Location: index.php');
                exit;
            } else {
                $mensajeError = "Error al guardar el alojamiento: " . $sentencia->error;
            }
        } catch (mysqli_sql_exception $excepcion) {
            $mensajeError = "Error de base de datos: " . $excepcion->getMessage();
        } catch (Exception $excepcion) {
            $mensajeError = $excepcion->getMessage();
        }
    }
}

// Título dinámico para la cabecera
$tituloPagina = 'Publicar nueva casa';
$pageTitle = $tituloPagina;
?>

<!-- Estilos de Leaflet para el mapa dinámico -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

<?php include __DIR__ . '/includes/header.php'; ?>

<div class="row justify-content-center">
    <div class="col-lg-8 col-md-10">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-primary text-white py-3">
                <h5 class="card-title mb-0 fw-semibold">🏡 Publicar un nuevo alojamiento</h5>
            </div>
            <div class="card-body p-4">
                
                <?php if (!empty($mensajeError)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <strong>Error:</strong> <?= htmlspecialchars($mensajeError) ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
                    </div>
                <?php endif; ?>

                <form method="POST" action="publicar_casa.php" class="needs-validation" novalidate>
                    <div class="row g-3">
                        
                        <!-- Título -->
                        <div class="col-12">
                            <label for="titulo" class="form-label fw-semibold">Título del alojamiento <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="titulo" name="titulo" 
                                   value="<?= isset($_POST['titulo']) ? htmlspecialchars($_POST['titulo']) : '' ?>" 
                                   placeholder="Ej: Hermosa cabaña con vista al lago" maxlength="150" required>
                        </div>

                        <!-- Tipo de Alojamiento -->
                        <div class="col-md-6">
                            <label for="id_tipo" class="form-label fw-semibold">Tipo de alojamiento <span class="text-danger">*</span></label>
                            <select class="form-select" id="id_tipo" name="id_tipo" required>
                                <option value="" disabled <?= !isset($_POST['id_tipo']) ? 'selected' : '' ?>>Selecciona un tipo...</option>
                                <?php foreach ($tipos as $tipo): ?>
                                    <option value="<?= (int)$tipo['id_tipo'] ?>" 
                                        <?= (isset($_POST['id_tipo']) && (int)$_POST['id_tipo'] === (int)$tipo['id_tipo']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($tipo['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Capacidad máxima -->
                        <div class="col-md-6">
                            <label for="capacidad" class="form-label fw-semibold">Capacidad máxima (personas) <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="capacidad" name="capacidad" 
                                   min="1" max="100" 
                                   value="<?= isset($_POST['capacidad']) ? (int)$_POST['capacidad'] : 1 ?>" required>
                        </div>

                        <!-- Departamento -->
                        <div class="col-md-6">
                            <label for="departamento" class="form-label fw-semibold">Departamento <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="departamento" name="departamento" 
                                   value="<?= isset($_POST['departamento']) ? htmlspecialchars($_POST['departamento']) : '' ?>" 
                                   placeholder="Ej: San Salvador, La Libertad..." maxlength="100" required>
                        </div>

                        <!-- Municipio -->
                        <div class="col-md-6">
                            <label for="municipio" class="form-label fw-semibold">Municipio <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="municipio" name="municipio" 
                                   value="<?= isset($_POST['municipio']) ? htmlspecialchars($_POST['municipio']) : '' ?>" 
                                   placeholder="Ej: Santa Tecla, San Salvador..." maxlength="100" required>
                        </div>

                        <!-- Dirección exacta -->
                        <div class="col-12">
                            <label for="direccion" class="form-label fw-semibold">Dirección exacta <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="direccion" name="direccion" 
                                   value="<?= isset($_POST['direccion']) ? htmlspecialchars($_POST['direccion']) : '' ?>" 
                                   placeholder="Ej: Colonia Escalón, Calle El Mirador #123" maxlength="255" required>
                        </div>

                        <!-- Mapa Dinámico y Coordenadas -->
                        <div class="col-12 mt-3">
                            <div class="card bg-light border shadow-none">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                                        <label class="form-label fw-semibold mb-0">
                                            📍 Ubicación en el mapa <span class="text-danger">*</span>
                                        </label>
                                        <span class="badge bg-secondary-subtle text-secondary border">
                                            Haz clic en el mapa o arrastra el marcador
                                        </span>
                                    </div>
                                    <p class="text-muted small mb-2">
                                        Selecciona la ubicación exacta de tu casa. Las coordenadas de <strong>latitud</strong> y <strong>longitud</strong> se autocompletarán automáticamente abajo.
                                    </p>

                                    <!-- Contenedor del mapa -->
                                    <div id="mapaSelector" style="height: 320px; width: 100%; border-radius: 8px;" class="border shadow-sm mb-3"></div>

                                    <!-- Campos de coordenadas -->
                                    <div class="row g-2">
                                        <div class="col-md-6">
                                            <label for="latitud" class="form-label small fw-semibold">Latitud <span class="text-danger">*</span></label>
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text">Lat</span>
                                                <input type="number" step="any" class="form-control" id="latitud" name="latitud" 
                                                       value="<?= isset($_POST['latitud']) ? htmlspecialchars($_POST['latitud']) : '13.6929403' ?>" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="longitud" class="form-label small fw-semibold">Longitud <span class="text-danger">*</span></label>
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text">Lng</span>
                                                <input type="number" step="any" class="form-control" id="longitud" name="longitud" 
                                                       value="<?= isset($_POST['longitud']) ? htmlspecialchars($_POST['longitud']) : '-89.2181911' ?>" required>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Precio por noche -->
                        <div class="col-md-6">
                            <label for="precio" class="form-label fw-semibold">Precio por noche ($ USD) <span class="text-danger">*</span></label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" step="0.01" min="0.01" class="form-control" id="precio" name="precio" 
                                       value="<?= isset($_POST['precio']) ? htmlspecialchars($_POST['precio']) : '' ?>" 
                                       placeholder="0.00" required>
                            </div>
                        </div>

                        <!-- URL de la foto -->
                        <div class="col-md-6">
                            <label for="foto" class="form-label fw-semibold">URL de la foto principal (Opcional)</label>
                            <input type="url" class="form-control" id="foto" name="foto" 
                                   value="<?= isset($_POST['foto']) ? htmlspecialchars($_POST['foto']) : '' ?>" 
                                   placeholder="https://ejemplo.com/foto.jpg">
                        </div>

                        <!-- Descripción -->
                        <div class="col-12">
                            <label for="descripcion" class="form-label fw-semibold">Descripción del alojamiento <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="descripcion" name="descripcion" rows="4" 
                                      placeholder="Describe los espacios, comodidades, reglas y atractivos del lugar..." required><?= isset($_POST['descripcion']) ? htmlspecialchars($_POST['descripcion']) : '' ?></textarea>
                        </div>

                    </div>

                    <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top">
                        <a href="index.php" class="btn btn-outline-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-primary px-4 fw-semibold">
                            💾 Guardar y Publicar
                        </button>
                    </div>
                </form>

            </div>
        </div>
    </div>
</div>

<!-- Scripts de Leaflet y lógica del mapa interactivo -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const entradaLatitud = document.getElementById('latitud');
    const entradaLongitud = document.getElementById('longitud');

    // Coordenadas iniciales (desde los campos o San Salvador por defecto)
    let latitudInicial = parseFloat(entradaLatitud.value);
    let longitudInicial = parseFloat(entradaLongitud.value);

    if (isNaN(latitudInicial) || isNaN(longitudInicial)) {
        latitudInicial = 13.6929403;
        longitudInicial = -89.2181911;
    }

    // Inicializar mapa centrado en las coordenadas

    const mapa = L.map('mapaSelector').setView([latitudInicial, longitudInicial], 13);

    // Cargar capas de OpenStreetMap
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
    }).addTo(mapa);

    // Marcador arrastrable
    const marcador = L.marker([latitudInicial, longitudInicial], {
        draggable: true
    }).addTo(mapa);

    marcador.bindPopup("<b>Ubicación seleccionada</b><br>Arrastra o haz clic para cambiarla.").openPopup();

    // Función para actualizar los campos al mover el marcador
    function actualizarCoordenadas(latitud, longitud) {
        entradaLatitud.value = Number(latitud).toFixed(7);
        entradaLongitud.value = Number(longitud).toFixed(7);
    }

    // Evento 1: Al terminar de arrastrar el marcador
    marcador.on('dragend', function (evento) {
        const posicion = evento.target.getLatLng();
        actualizarCoordenadas(posicion.lat, posicion.lng);
        marcador.openPopup();
    });

    // Evento 2: Al hacer clic en cualquier parte del mapa
    mapa.on('click', function (evento) {
        marcador.setLatLng(evento.latlng);
        actualizarCoordenadas(evento.latlng.lat, evento.latlng.lng);
        marcador.openPopup();
    });

    // Evento 3: Si el usuario escribe manualmente en los campos, mover el marcador
    function moverMarcadorDesdeEntradas() {
        const latitud = parseFloat(entradaLatitud.value);
        const longitud = parseFloat(entradaLongitud.value);

        if (!isNaN(latitud) && !isNaN(longitud) && latitud >= -90 && latitud <= 90 && longitud >= -180 && longitud <= 180) {
            marcador.setLatLng([latitud, longitud]);
            mapa.panTo([latitud, longitud]);
        }
    }

    entradaLatitud.addEventListener('input', moverMarcadorDesdeEntradas);
    entradaLongitud.addEventListener('input', moverMarcadorDesdeEntradas);

    // Corregir tamaño del mapa si el contenedor tardó en renderizarse
    setTimeout(function () {
        mapa.invalidateSize();
    }, 250);
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>

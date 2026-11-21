<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/conexion.php';

$mensajeError = "";

// Tipos de alojamiento: Casa, Apartamento, Habitacion
$tipos = [];
$resultadoTipos = $conexion->query("SELECT id_tipo, nombre FROM tipos_alojamiento ORDER BY id_tipo ASC");
if ($resultadoTipos && $resultadoTipos->num_rows > 0) {
    while ($fila = $resultadoTipos->fetch_assoc()) {
        $tipos[] = $fila;
    }
} else {
    $conexion->query("INSERT INTO tipos_alojamiento (nombre) VALUES ('Casa'), ('Apartamento'), ('Habitacion')");
    $resultadoTipos = $conexion->query("SELECT id_tipo, nombre FROM tipos_alojamiento ORDER BY id_tipo ASC");
    if ($resultadoTipos) {
        while ($fila = $resultadoTipos->fetch_assoc()) {
            $tipos[] = $fila;
        }
    }
}

// ID de anfitrion: priorizar usuario autenticado en sesion, con respaldo activo
$id_anfitrion = !empty($_SESSION['id_usuario']) ? (int)$_SESSION['id_usuario'] : null;
if ($id_anfitrion === null) {
    $resultadoUsuario = $conexion->query("SELECT id_usuario FROM usuarios WHERE estado = 'activo' LIMIT 1");
    if ($resultadoUsuario && $filaUsuario = $resultadoUsuario->fetch_assoc()) {
        $id_anfitrion = (int)$filaUsuario['id_usuario'];
    } else {
        $resultadoCualquierUsuario = $conexion->query("SELECT id_usuario FROM usuarios LIMIT 1");
        if ($resultadoCualquierUsuario && $filaCualquierUsuario = $resultadoCualquierUsuario->fetch_assoc()) {
            $id_anfitrion = (int)$filaCualquierUsuario['id_usuario'];
        } else {
            $conexion->query("INSERT INTO usuarios (nombre, apellido, correo, password_hash, rol, estado) 
                              VALUES ('Anfitrión', 'Prueba', 'anfitrion@haloja.local', '$2y$10$abcdefghijklmnopqrstuv', 'usuario', 'activo')");
            $id_anfitrion = (int)$conexion->insert_id;
        }
    }
}

// Controlador POST modularizado
require_once __DIR__ . '/procesar_publicacion.php';
if ($mensajeError === '' && !empty($_SESSION['error_publicacion'])) {
    $mensajeError = (string)$_SESSION['error_publicacion'];
    unset($_SESSION['error_publicacion']);
}

// Cargar amenidades dinamicamente agrupadas por categoria
$amenidadesPorCategoria = [];
try {
    $resAmenidades = $conexion->query("SELECT id, nombre, categoria, icono FROM amenidades ORDER BY categoria ASC, id ASC");
    if ($resAmenidades) {
        while ($am = $resAmenidades->fetch_assoc()) {
            $amenidadesPorCategoria[$am['categoria']][] = $am;
        }
    }
} catch (Throwable $e) {
    $amenidadesPorCategoria = [];
}

$tituloPagina = 'Publicar alojamiento';
$pageTitle = $tituloPagina;

include __DIR__ . '/includes/header.php';
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

<style>
    .wizard-card { border-radius: 1rem; overflow: hidden; }
    .wizard-step-badge {
        width: 28px; height: 28px; font-size: .8rem; font-weight: 700;
        display: inline-flex; align-items: center; justify-content: center;
        border-radius: 50%; background: #e9ecef; color: #495057; transition: all .2s;
    }
    .wizard-step-label.active .wizard-step-badge { background: #212529; color: #fff; }
    .wizard-step-label.done .wizard-step-badge { background: #198754; color: #fff; }
    .wizard-step-label.active { color: #212529; font-weight: 600; }
    .choice-card {
        cursor: pointer; border: 2px solid #dee2e6; border-radius: .75rem;
        transition: border-color .15s, background-color .15s;
    }
    .choice-card:hover { border-color: #adb5bd; }
    .btn-check:checked + .choice-card {
        border-color: #212529; background-color: #f8f9fa; box-shadow: 0 0 0 1px #212529;
    }
    .counter-row { border-bottom: 1px solid #f1f3f5; padding: .9rem 0; }
    .counter-row:last-child { border-bottom: 0; }
    .counter-btn {
        width: 36px; height: 36px; border-radius: 50%; font-weight: 700;
        display: inline-flex; align-items: center; justify-content: center; padding: 0;
    }
    .counter-val { min-width: 2.5rem; text-align: center; font-weight: 600; font-size: 1.05rem; }
    .amenity-tile {
        cursor: pointer; border: 1.5px solid #dee2e6; border-radius: .65rem;
        padding: .65rem .85rem; transition: all .15s; user-select: none;
    }
    .amenity-tile:hover { border-color: #6c757d; }
    .btn-check:checked + .amenity-tile {
        border-color: #212529; background-color: #f1f3f5; font-weight: 600;
    }
    .preview-card { position: relative; border-radius: .75rem; overflow: hidden; border: 2px solid #dee2e6; }
    .preview-card.is-cover { border-color: #198754; }
    .preview-card img { width: 100%; height: 140px; object-fit: cover; display: block; }
    .preview-remove {
        position: absolute; top: 6px; right: 6px; width: 26px; height: 26px;
        border-radius: 50%; padding: 0; line-height: 1; font-weight: 700;
    }
</style>

<div class="row justify-content-center">
    <div class="col-lg-9 col-xl-8">
        <div class="card shadow-sm border-0 wizard-card">
            <div class="card-header bg-white border-bottom py-3 px-4">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h1 class="h5 mb-0 fw-bold">🏡 Publica tu espacio</h1>
                    <span class="badge text-bg-dark" id="stepCounterText">Paso 1 de 5</span>
                </div>
                <div class="progress mb-3" style="height: 6px;">
                    <div class="progress-bar bg-dark" id="wizardProgressBar" role="progressbar" style="width: 20%;" aria-valuenow="20" aria-valuemin="0" aria-valuemax="100"></div>
                </div>
                <div class="d-flex justify-content-between small text-muted flex-wrap gap-2" id="wizardStepLabels">
                    <span class="wizard-step-label active" data-step-indicator="1"><span class="wizard-step-badge me-1">1</span> Esenciales</span>
                    <span class="wizard-step-label" data-step-indicator="2"><span class="wizard-step-badge me-1">2</span> Ubicación</span>
                    <span class="wizard-step-label" data-step-indicator="3"><span class="wizard-step-badge me-1">3</span> Espacios</span>
                    <span class="wizard-step-label" data-step-indicator="4"><span class="wizard-step-badge me-1">4</span> Amenidades</span>
                    <span class="wizard-step-label" data-step-indicator="5"><span class="wizard-step-badge me-1">5</span> Fotos y tarifa</span>
                </div>
            </div>

            <div class="card-body p-4">
                <div id="wizardAlertBox" class="alert alert-danger <?= empty($mensajeError) ? 'd-none' : '' ?>" role="alert">
                    <?= htmlspecialchars($mensajeError) ?>
                </div>

                <form id="wizardForm" method="POST" action="procesar_publicacion.php" enctype="multipart/form-data" novalidate>

                    <!-- PASO 1: Esenciales -->
                    <section class="wizard-step" data-step="1">
                        <h2 class="h5 fw-bold mb-1">Cuéntanos sobre tu alojamiento</h2>
                        <p class="text-muted small mb-4">Empieza por el título, tipo de propiedad y el tipo de espacio que ofreces.</p>

                        <div class="mb-3">
                            <label for="titulo" class="form-label fw-semibold">Título del anuncio <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="titulo" name="titulo" maxlength="150"
                                   placeholder="Ej: Villa moderna frente al mar en El Tunco" required>
                        </div>

                        <div class="mb-3">
                            <label for="id_tipo" class="form-label fw-semibold">Tipo de propiedad <span class="text-danger">*</span></label>
                            <select class="form-select" id="id_tipo" name="id_tipo" required>
                                <option value="" disabled selected>Selecciona un tipo...</option>
                                <?php foreach ($tipos as $tipo): ?>
                                    <option value="<?= (int)$tipo['id_tipo'] ?>"><?= htmlspecialchars($tipo['nombre']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold d-block">Tipo de espacio <span class="text-danger">*</span></label>
                            <div class="row g-2">
                                <div class="col-md-4">
                                    <input type="radio" class="btn-check" name="tipo_espacio" id="espEntero" value="alojamiento_entero" checked>
                                    <label class="choice-card p-3 w-100 h-100 d-block" for="espEntero">
                                        <div class="fw-semibold">🏠 Alojamiento entero</div>
                                        <div class="text-muted small mt-1">Los huéspedes disponen de todo el lugar.</div>
                                    </label>
                                </div>
                                <div class="col-md-4">
                                    <input type="radio" class="btn-check" name="tipo_espacio" id="espPrivada" value="habitacion_privada">
                                    <label class="choice-card p-3 w-100 h-100 d-block" for="espPrivada">
                                        <div class="fw-semibold">🛏️ Habitación privada</div>
                                        <div class="text-muted small mt-1">Cuarto propio y áreas comunes compartidas.</div>
                                    </label>
                                </div>
                                <div class="col-md-4">
                                    <input type="radio" class="btn-check" name="tipo_espacio" id="espCompartida" value="habitacion_compartida">
                                    <label class="choice-card p-3 w-100 h-100 d-block" for="espCompartida">
                                        <div class="fw-semibold">🛋️ Habitación compartida</div>
                                        <div class="text-muted small mt-1">Espacio compartido con otros huéspedes.</div>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="mb-2">
                            <label for="descripcion" class="form-label fw-semibold">Descripción <span class="text-danger">*</span></label>
                            <textarea class="form-control" id="descripcion" name="descripcion" rows="4"
                                      placeholder="Describe los puntos destacados, el ambiente y lo que hace único al espacio..." required></textarea>
                        </div>
                    </section>

                    <!-- PASO 2: Ubicación -->
                    <section class="wizard-step d-none" data-step="2">
                        <h2 class="h5 fw-bold mb-1">¿Dónde está ubicado?</h2>
                        <p class="text-muted small mb-4">Indica la dirección y, opcionalmente, ajusta el pin exacto en el mapa.</p>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="departamento" class="form-label fw-semibold">Departamento <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="departamento" name="departamento" maxlength="100"
                                       placeholder="Ej: La Libertad" required>
                            </div>
                            <div class="col-md-6">
                                <label for="ciudad" class="form-label fw-semibold">Ciudad / Municipio <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="ciudad" name="ciudad" maxlength="100"
                                       placeholder="Ej: Tamanique / El Tunco" required>
                                <input type="hidden" name="municipio" id="municipio">
                            </div>
                            <div class="col-12">
                                <label for="direccion" class="form-label fw-semibold">Dirección exacta <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="direccion" name="direccion" maxlength="255"
                                       placeholder="Calle, colonia, número de casa..." required>
                            </div>
                            <div class="col-12">
                                <label for="referencias" class="form-label fw-semibold">Referencias de llegada <small class="text-muted fw-normal">(Opcional)</small></label>
                                <input type="text" class="form-control" id="referencias" name="referencias" maxlength="500"
                                       placeholder="Ej: Portón negro frente al parque central">
                            </div>
                            <div class="col-12">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <label class="form-label fw-semibold mb-0">Pin en mapa <small class="text-muted fw-normal">(Opcional — clic para ubicar)</small></label>
                                    <button type="button" class="btn btn-link btn-sm text-decoration-none p-0" id="btnLimpiarCoords">Limpiar coordenadas</button>
                                </div>
                                <div id="mapPublicar" class="border rounded-3 bg-light" style="height: 250px;"></div>
                                <input type="hidden" id="latitud" name="latitud">
                                <input type="hidden" id="longitud" name="longitud">
                            </div>
                        </div>
                    </section>

                    <!-- PASO 3: Capacidad y distribución -->
                    <section class="wizard-step d-none" data-step="3">
                        <h2 class="h5 fw-bold mb-1">Capacidad y distribución</h2>
                        <p class="text-muted small mb-4">Define cuántos huéspedes pueden hospedarse y la distribución del espacio.</p>

                        <div class="px-2">
                            <div class="counter-row d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-semibold">Huéspedes (capacidad máxima)</div>
                                    <small class="text-muted">Mínimo 1 persona</small>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <button type="button" class="btn btn-outline-secondary counter-btn" data-target="capacidad_personas" data-delta="-1">−</button>
                                    <span class="counter-val" id="disp_capacidad_personas">2</span>
                                    <button type="button" class="btn btn-outline-secondary counter-btn" data-target="capacidad_personas" data-delta="1">+</button>
                                    <input type="hidden" name="capacidad_personas" id="capacidad_personas" value="2" data-min="1" data-max="50" data-step="1">
                                </div>
                            </div>

                            <div class="counter-row d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-semibold">Habitaciones / cuartos</div>
                                    <small class="text-muted">Usa 0 si es tipo estudio</small>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <button type="button" class="btn btn-outline-secondary counter-btn" data-target="num_habitaciones" data-delta="-1">−</button>
                                    <span class="counter-val" id="disp_num_habitaciones">1</span>
                                    <button type="button" class="btn btn-outline-secondary counter-btn" data-target="num_habitaciones" data-delta="1">+</button>
                                    <input type="hidden" name="num_habitaciones" id="num_habitaciones" value="1" data-min="0" data-max="30" data-step="1">
                                </div>
                            </div>

                            <div class="counter-row d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-semibold">Camas</div>
                                    <small class="text-muted">Mínimo 1 cama</small>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <button type="button" class="btn btn-outline-secondary counter-btn" data-target="num_camas" data-delta="-1">−</button>
                                    <span class="counter-val" id="disp_num_camas">1</span>
                                    <button type="button" class="btn btn-outline-secondary counter-btn" data-target="num_camas" data-delta="1">+</button>
                                    <input type="hidden" name="num_camas" id="num_camas" value="1" data-min="1" data-max="50" data-step="1">
                                </div>
                            </div>

                            <div class="counter-row d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-semibold">Baños</div>
                                    <small class="text-muted">Permite medios baños (0.5)</small>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <button type="button" class="btn btn-outline-secondary counter-btn" data-target="num_banos" data-delta="-0.5">−</button>
                                    <span class="counter-val" id="disp_num_banos">1.0</span>
                                    <button type="button" class="btn btn-outline-secondary counter-btn" data-target="num_banos" data-delta="0.5">+</button>
                                    <input type="hidden" name="num_banos" id="num_banos" value="1.0" data-min="0.5" data-max="30" data-step="0.5">
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- PASO 4: Amenidades y reglas -->
                    <section class="wizard-step d-none" data-step="4">
                        <h2 class="h5 fw-bold mb-1">Amenidades y reglas de la casa</h2>
                        <p class="text-muted small mb-3">Marca lo que incluye tu espacio y define los horarios de llegada y salida.</p>

                        <?php if (!empty($amenidadesPorCategoria)): ?>
                            <?php foreach ($amenidadesPorCategoria as $categoria => $items): ?>
                                <div class="mb-3">
                                    <div class="fw-semibold small text-uppercase text-muted mb-2"><?= htmlspecialchars($categoria) ?></div>
                                    <div class="row g-2">
                                        <?php foreach ($items as $am): ?>
                                            <div class="col-6 col-md-4">
                                                <input type="checkbox" class="btn-check" name="amenidades[]"
                                                       id="am_<?= (int)$am['id'] ?>" value="<?= (int)$am['id'] ?>">
                                                <label class="amenity-tile w-100 h-100 d-flex align-items-center gap-2 small" for="am_<?= (int)$am['id'] ?>">
                                                    <span>✓</span>
                                                    <span><?= htmlspecialchars($am['nombre']) ?></span>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="alert alert-warning small">No hay amenidades cargadas aún en el catálogo.</div>
                        <?php endif; ?>

                        <hr class="my-4">

                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label for="hora_checkin" class="form-label fw-semibold">Hora Check-in <span class="text-danger">*</span></label>
                                <input type="time" class="form-control" id="hora_checkin" name="hora_checkin" value="15:00" required>
                            </div>
                            <div class="col-md-4">
                                <label for="hora_checkout" class="form-label fw-semibold">Hora Check-out <span class="text-danger">*</span></label>
                                <input type="time" class="form-control" id="hora_checkout" name="hora_checkout" value="11:00" required>
                            </div>
                            <div class="col-md-4">
                                <label for="estancia_minima" class="form-label fw-semibold">Estancia mínima (noches)</label>
                                <input type="number" class="form-control" id="estancia_minima" name="estancia_minima" min="1" max="365" value="1" required>
                            </div>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-md-4">
                                <div class="form-check form-switch border rounded p-2 ps-5">
                                    <input class="form-check-input" type="checkbox" id="permite_mascotas" name="permite_mascotas" value="1">
                                    <label class="form-check-label small fw-semibold" for="permite_mascotas">🐾 Permite mascotas</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check form-switch border rounded p-2 ps-5">
                                    <input class="form-check-input" type="checkbox" id="permite_fumar" name="permite_fumar" value="1">
                                    <label class="form-check-label small fw-semibold" for="permite_fumar">🚬 Permite fumar</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check form-switch border rounded p-2 ps-5">
                                    <input class="form-check-input" type="checkbox" id="permite_eventos" name="permite_eventos" value="1">
                                    <label class="form-check-label small fw-semibold" for="permite_eventos">🎉 Permite eventos</label>
                                </div>
                            </div>
                        </div>

                        <div>
                            <label for="reglas_adicionales" class="form-label fw-semibold">Reglas adicionales <small class="text-muted fw-normal">(Opcional)</small></label>
                            <textarea class="form-control" id="reglas_adicionales" name="reglas_adicionales" rows="2"
                                      placeholder="Ej: Silencio después de las 10:00 p.m., no ingresar con arena a las habitaciones..."></textarea>
                        </div>
                    </section>

                    <!-- PASO 5: Imágenes y tarifas -->
                    <section class="wizard-step d-none" data-step="5">
                        <h2 class="h5 fw-bold mb-1">Fotos y tarifas</h2>
                        <p class="text-muted small mb-4">Agrega al menos 1 foto (por URL o archivo), elige tu foto de portada y fija el precio.</p>

                        <div class="card bg-light border-0 p-3 mb-3">
                            <div class="row g-2 align-items-end mb-2">
                                <div class="col-md-9">
                                    <label for="nuevaUrlInput" class="form-label small fw-semibold mb-1">Agregar imagen por URL (recomendado)</label>
                                    <input type="url" class="form-control" id="nuevaUrlInput" placeholder="https://ejemplo.com/foto-casa.jpg">
                                </div>
                                <div class="col-md-3 d-grid">
                                    <button type="button" class="btn btn-outline-dark" id="btnAgregarUrl">+ Agregar URL</button>
                                </div>
                            </div>

                            <div class="mt-2">
                                <label for="inputImagenesFiles" class="form-label small fw-semibold mb-1">O subir imágenes desde tu dispositivo (JPG, PNG, WEBP — máx. 5 MB)</label>
                                <input type="file" class="form-control" id="inputImagenesFiles" name="imagenes[]" accept="image/jpeg,image/png,image/webp" multiple>
                            </div>

                            <div id="urlsHiddenContainer"></div>
                            <input type="hidden" name="imagen_portada" id="imagenPortadaIndex" value="0">
                        </div>

                        <div class="row g-3 mb-4" id="galeriaPreview"></div>

                        <hr class="my-3">

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="precio_noche" class="form-label fw-semibold">Precio por noche (USD $) <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" id="precio_noche" name="precio_noche" step="0.01" min="0.01" placeholder="45.00" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="tarifa_limpieza" class="form-label fw-semibold">Tarifa de limpieza (USD $)</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" id="tarifa_limpieza" name="tarifa_limpieza" step="0.01" min="0" value="0.00">
                                </div>
                            </div>
                        </div>
                    </section>

                    <!-- Navegación del Wizard -->
                    <div class="d-flex justify-content-between align-items-center pt-4 mt-4 border-top">
                        <button type="button" class="btn btn-outline-secondary px-4 d-none" id="btnAnterior">← Atrás</button>
                        <a href="index.php" class="btn btn-link text-muted text-decoration-none" id="btnCancelar">Cancelar</a>
                        <div class="ms-auto d-flex gap-2">
                            <button type="button" class="btn btn-dark px-4" id="btnSiguiente">Siguiente →</button>
                            <button type="submit" class="btn btn-success px-4 fw-semibold d-none" id="btnPublicar" disabled>🚀 Publicar alojamiento</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(() => {
    const form = document.getElementById('wizardForm');
    const steps = Array.from(document.querySelectorAll('.wizard-step'));
    const indicators = Array.from(document.querySelectorAll('[data-step-indicator]'));
    const progressBar = document.getElementById('wizardProgressBar');
    const stepCounterText = document.getElementById('stepCounterText');
    const alertBox = document.getElementById('wizardAlertBox');

    const btnAnterior = document.getElementById('btnAnterior');
    const btnCancelar = document.getElementById('btnCancelar');
    const btnSiguiente = document.getElementById('btnSiguiente');
    const btnPublicar = document.getElementById('btnPublicar');

    let currentStep = 1;
    const totalSteps = steps.length;

    const showAlert = (msg) => {
        alertBox.textContent = msg;
        alertBox.classList.remove('d-none');
        alertBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    };
    const clearAlert = () => {
        alertBox.classList.add('d-none');
        alertBox.textContent = '';
    };

    // --- Sincronizar ciudad -> municipio ---
    const ciudadInput = document.getElementById('ciudad');
    const municipioHidden = document.getElementById('municipio');
    ciudadInput.addEventListener('input', () => { municipioHidden.value = ciudadInput.value; });

    // --- Mapa Leaflet en Paso 2 ---
    const latInput = document.getElementById('latitud');
    const lngInput = document.getElementById('longitud');
    const map = L.map('mapPublicar').setView([13.6929403, -89.2181911], 11);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap'
    }).addTo(map);
    let marker = null;

    const setPin = (lat, lng, updateInputs = true) => {
        if (marker) {
            marker.setLatLng([lat, lng]);
        } else {
            marker = L.marker([lat, lng], { draggable: true }).addTo(map);
            marker.on('dragend', () => {
                const p = marker.getLatLng();
                latInput.value = p.lat.toFixed(6);
                lngInput.value = p.lng.toFixed(6);
            });
        }
        if (updateInputs) {
            latInput.value = lat.toFixed(6);
            lngInput.value = lng.toFixed(6);
        }
    };

    setPin(13.6929403, -89.2181911);

    map.on('click', (e) => setPin(e.latlng.lat, e.latlng.lng));
    document.getElementById('btnLimpiarCoords').addEventListener('click', () => {
        latInput.value = '';
        lngInput.value = '';
        if (marker) { map.removeLayer(marker); marker = null; }
    });

    // --- Contadores +/- (Paso 3) ---
    const updateCounterButtons = (input) => {
        const min = parseFloat(input.dataset.min);
        const max = parseFloat(input.dataset.max);
        const val = parseFloat(input.value);
        const target = input.id;
        const disp = document.getElementById('disp_' + target);
        disp.textContent = input.dataset.step === '0.5' ? val.toFixed(1) : String(Math.round(val));
        document.querySelectorAll(`.counter-btn[data-target="${target}"]`).forEach(btn => {
            const d = parseFloat(btn.dataset.delta);
            btn.disabled = (d < 0 && val <= min) || (d > 0 && val >= max);
        });
    };

    document.querySelectorAll('.counter-btn').forEach(btn => {
        const input = document.getElementById(btn.dataset.target);
        updateCounterButtons(input);
        btn.addEventListener('click', () => {
            const min = parseFloat(input.dataset.min);
            const max = parseFloat(input.dataset.max);
            const delta = parseFloat(btn.dataset.delta);
            const next = Math.min(max, Math.max(min, parseFloat(input.value) + delta));
            input.value = input.dataset.step === '0.5' ? next.toFixed(1) : String(Math.round(next));
            updateCounterButtons(input);
        });
    });

    // --- Gestor de múltiples imágenes (Paso 5) ---
    let urlItems = [];
    let fileItems = [];
    let coverKey = null;
    let uidSeq = 1;

    const urlInput = document.getElementById('nuevaUrlInput');
    const fileInput = document.getElementById('inputImagenesFiles');
    const urlsContainer = document.getElementById('urlsHiddenContainer');
    const coverIndexInput = document.getElementById('imagenPortadaIndex');
    const gallery = document.getElementById('galeriaPreview');

    const isValidHttpUrl = (str) => {
        try {
            const u = new URL(str);
            return u.protocol === 'http:' || u.protocol === 'https:';
        } catch {
            return false;
        }
    };

    const syncImagesStateAndRender = () => {
        const ordered = [...urlItems, ...fileItems];
        if (ordered.length === 0) {
            coverKey = null;
        } else if (!ordered.some(item => item.key === coverKey)) {
            coverKey = ordered[0].key;
        }

        // Hidden inputs para URLs en orden exacto
        urlsContainer.innerHTML = '';
        urlItems.forEach(item => {
            const hidden = document.createElement('input');
            hidden.type = 'hidden';
            hidden.name = 'imagenes_url[]';
            hidden.value = item.src;
            urlsContainer.appendChild(hidden);
        });

        // FileList sincronizado via DataTransfer en orden exacto
        const dt = new DataTransfer();
        fileItems.forEach(item => dt.items.add(item.file));
        fileInput.files = dt.files;

        // Indice de portada relativo a [...urlItems, ...fileItems]
        const coverIdx = Math.max(0, ordered.findIndex(item => item.key === coverKey));
        coverIndexInput.value = String(coverIdx);

        // Render de galería
        gallery.innerHTML = '';
        ordered.forEach((item, index) => {
            const isCover = item.key === coverKey;
            const col = document.createElement('div');
            col.className = 'col-6 col-md-4';

            const card = document.createElement('div');
            card.className = `preview-card bg-white ${isCover ? 'is-cover' : ''}`;

            const img = document.createElement('img');
            img.src = item.src;
            img.alt = `Imagen ${index + 1}`;
            img.onerror = () => { img.src = 'https://picsum.photos/seed/error/300/200'; };

            const btnRemove = document.createElement('button');
            btnRemove.type = 'button';
            btnRemove.className = 'btn btn-danger btn-sm preview-remove';
            btnRemove.textContent = '×';
            btnRemove.title = 'Eliminar imagen';
            btnRemove.addEventListener('click', () => {
                if (item.kind === 'url') {
                    urlItems = urlItems.filter(x => x.key !== item.key);
                } else {
                    URL.revokeObjectURL(item.src);
                    fileItems = fileItems.filter(x => x.key !== item.key);
                }
                syncImagesStateAndRender();
            });

            const footer = document.createElement('div');
            footer.className = 'p-2 d-flex justify-content-between align-items-center gap-1';

            if (isCover) {
                const badge = document.createElement('span');
                badge.className = 'badge text-bg-success';
                badge.textContent = '★ Portada';
                footer.appendChild(badge);
            } else {
                const btnCover = document.createElement('button');
                btnCover.type = 'button';
                btnCover.className = 'btn btn-outline-dark btn-sm py-0 px-2';
                btnCover.textContent = 'Usar portada';
                btnCover.addEventListener('click', () => {
                    coverKey = item.key;
                    syncImagesStateAndRender();
                });
                footer.appendChild(btnCover);
            }

            const orderTag = document.createElement('small');
            orderTag.className = 'text-muted';
            orderTag.textContent = `#${index + 1}`;
            footer.appendChild(orderTag);

            card.append(img, btnRemove, footer);
            col.appendChild(card);
            gallery.appendChild(col);
        });
    };

    const addUrlFromInput = () => {
        const val = urlInput.value.trim();
        if (!val) return true;
        if (!isValidHttpUrl(val)) {
            showAlert('Ingresa una URL de imagen válida que inicie con http:// o https://.');
            return false;
        }
        urlItems.push({ key: 'u_' + (uidSeq++), kind: 'url', src: val });
        urlInput.value = '';
        clearAlert();
        syncImagesStateAndRender();
        return true;
    };

    document.getElementById('btnAgregarUrl').addEventListener('click', addUrlFromInput);
    urlInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') { e.preventDefault(); addUrlFromInput(); }
    });

    fileInput.addEventListener('change', (e) => {
        const allowed = ['image/jpeg', 'image/png', 'image/webp'];
        const maxSize = 5 * 1024 * 1024;
        for (const f of Array.from(e.target.files)) {
            if (!allowed.includes(f.type)) {
                showAlert(`Formato no permitido en ${f.name}. Usa JPG, PNG o WEBP.`);
                continue;
            }
            if (f.size > maxSize) {
                showAlert(`El archivo ${f.name} supera los 5 MB.`);
                continue;
            }
            fileItems.push({ key: 'f_' + (uidSeq++), kind: 'file', file: f, src: URL.createObjectURL(f) });
        }
        syncImagesStateAndRender();
    });

    // --- Navegación y validación por paso ---
    const validateStep = (step) => {
        clearAlert();
        if (step === 1) {
            if (!document.getElementById('titulo').value.trim()) {
                showAlert('Ingresa un título para tu alojamiento.'); return false;
            }
            if (!document.getElementById('id_tipo').value) {
                showAlert('Selecciona un tipo de propiedad.'); return false;
            }
            if (!document.getElementById('descripcion').value.trim()) {
                showAlert('Ingresa una descripción para tu alojamiento.'); return false;
            }
        } else if (step === 2) {
            if (!document.getElementById('departamento').value.trim() || !ciudadInput.value.trim()) {
                showAlert('El departamento y la ciudad/municipio son obligatorios.'); return false;
            }
            if (!document.getElementById('direccion').value.trim()) {
                showAlert('La dirección exacta es obligatoria.'); return false;
            }
            const lat = latInput.value.trim();
            const lng = lngInput.value.trim();
            if (lat !== '' && (isNaN(lat) || Number(lat) < -90 || Number(lat) > 90)) {
                showAlert('La latitud debe estar entre -90 y 90.'); return false;
            }
            if (lng !== '' && (isNaN(lng) || Number(lng) < -180 || Number(lng) > 180)) {
                showAlert('La longitud debe estar entre -180 y 180.'); return false;
            }
            municipioHidden.value = ciudadInput.value.trim();
        } else if (step === 3) {
            if (parseInt(document.getElementById('capacidad_personas').value, 10) < 1) {
                showAlert('La capacidad debe ser de al menos 1 persona.'); return false;
            }
            if (parseInt(document.getElementById('num_camas').value, 10) < 1) {
                showAlert('Debe haber al menos 1 cama.'); return false;
            }
            if (parseFloat(document.getElementById('num_banos').value) < 0.5) {
                showAlert('Debe haber al menos 0.5 baños.'); return false;
            }
        } else if (step === 4) {
            if (!document.getElementById('hora_checkin').value || !document.getElementById('hora_checkout').value) {
                showAlert('Define los horarios de check-in y check-out.'); return false;
            }
            if (parseInt(document.getElementById('estancia_minima').value, 10) < 1) {
                showAlert('La estancia mínima debe ser de al menos 1 noche.'); return false;
            }
        } else if (step === 5) {
            if (urlInput.value.trim() !== '' && !addUrlFromInput()) {
                return false;
            }
            if (urlItems.length + fileItems.length < 1) {
                showAlert('Agrega al menos 1 imagen del alojamiento (URL o archivo).'); return false;
            }
            const precio = parseFloat(document.getElementById('precio_noche').value);
            const limpieza = parseFloat(document.getElementById('tarifa_limpieza').value || '0');
            if (isNaN(precio) || precio <= 0) {
                showAlert('El precio por noche debe ser mayor a $0.'); return false;
            }
            if (isNaN(limpieza) || limpieza < 0) {
                showAlert('La tarifa de limpieza no puede ser negativa.'); return false;
            }
        }
        return true;
    };

    let publishUnlockTimer = null;

    const renderStep = (step, shouldClearAlert = true) => {
        if (shouldClearAlert) {
            clearAlert();
        }
        currentStep = step;
        steps.forEach(s => s.classList.toggle('d-none', Number(s.dataset.step) !== step));
        indicators.forEach(ind => {
            const n = Number(ind.dataset.stepIndicator);
            ind.classList.toggle('active', n === step);
            ind.classList.toggle('done', n < step);
        });

        const pct = Math.round((step / totalSteps) * 100);
        progressBar.style.width = pct + '%';
        progressBar.setAttribute('aria-valuenow', String(pct));
        stepCounterText.textContent = `Paso ${step} de ${totalSteps}`;

        btnAnterior.classList.toggle('d-none', step === 1);
        btnCancelar.classList.toggle('d-none', step > 1);
        btnSiguiente.classList.toggle('d-none', step === totalSteps);
        btnPublicar.classList.toggle('d-none', step !== totalSteps);

        if (publishUnlockTimer) {
            clearTimeout(publishUnlockTimer);
            publishUnlockTimer = null;
        }
        btnPublicar.disabled = true;
        if (step === totalSteps) {
            publishUnlockTimer = setTimeout(() => {
                publishUnlockTimer = null;
                if (currentStep === totalSteps) {
                    btnPublicar.disabled = false;
                }
            }, 250);
        }

        if (step === 2) {
            setTimeout(() => map.invalidateSize(), 150);
        }
    };

    btnSiguiente.addEventListener('click', () => {
        if (validateStep(currentStep) && currentStep < totalSteps) {
            renderStep(currentStep + 1);
        }
    });

    btnAnterior.addEventListener('click', () => {
        if (currentStep > 1) renderStep(currentStep - 1);
    });

    form.addEventListener('keydown', (e) => {
        if (e.key !== 'Enter') return;
        const target = e.target;
        if (!target || target.tagName !== 'INPUT' || target.id === 'nuevaUrlInput') return;
        if (currentStep < totalSteps) {
            e.preventDefault();
            if (validateStep(currentStep)) {
                renderStep(currentStep + 1);
            }
        }
    });

    // Envío asíncrono con JSON para conservar datos si hay error del servidor
    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (currentStep < totalSteps) {
            if (validateStep(currentStep)) {
                renderStep(currentStep + 1);
            }
            return;
        }
        for (let step = 1; step < totalSteps; step++) {
            if (!validateStep(step)) {
                renderStep(step, false);
                return;
            }
        }
        if (!validateStep(5)) return;
        syncImagesStateAndRender();

        btnPublicar.disabled = true;
        btnPublicar.textContent = 'Publicando...';
        try {
            const resp = await fetch(form.action, {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: new FormData(form)
            });
            const data = await resp.json();
            if (resp.ok && data.ok) {
                window.location.href = data.redirect || 'index.php';
                return;
            }
            showAlert(data.error || 'No se pudo publicar el alojamiento.');
        } catch (err) {
            showAlert('Error de conexión al publicar. Intenta nuevamente.');
        } finally {
            btnPublicar.disabled = currentStep !== totalSteps;
            btnPublicar.textContent = '🚀 Publicar alojamiento';
        }
    });

    renderStep(1, false);
})();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>

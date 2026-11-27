<?php
require_once __DIR__ . '/config/sesion.php';
require_once __DIR__ . '/config/conexion.php';

$mensajeError = $_SESSION['error_publicacion'] ?? '';
unset($_SESSION['error_publicacion']);

// Cargar tipos de alojamiento
$tipos = [];
$resTipos = $conexion->query("SELECT id_tipo, nombre FROM tipos_alojamiento ORDER BY id_tipo ASC");
if ($resTipos) {
    $tipos = $resTipos->fetch_all(MYSQLI_ASSOC);
}

// Cargar amenidades agrupadas por categoría
$amenidadesPorCategoria = [];
$resAmenidades = $conexion->query("SELECT id, nombre, categoria FROM amenidades ORDER BY categoria ASC, id ASC");
if ($resAmenidades) {
    while ($am = $resAmenidades->fetch_assoc()) {
        $amenidadesPorCategoria[$am['categoria']][] = $am;
    }
}

// Cargar métodos de pago informativos
$metodosPago = [];
$resMetodos = $conexion->query("SELECT id_metodo_pago, nombre FROM metodos_pago ORDER BY id_metodo_pago ASC");
if ($resMetodos) {
    $metodosPago = $resMetodos->fetch_all(MYSQLI_ASSOC);
}

$departamentosSV = [
    'Ahuachapán', 'Cabañas', 'Chalatenango', 'Cuscatlán',
    'La Libertad', 'La Paz', 'La Unión', 'Morazán',
    'San Miguel', 'San Salvador', 'San Vicente', 'Santa Ana',
    'Sonsonate', 'Usulután'
];

$pageTitle = 'Publicar alojamiento';
include __DIR__ . '/includes/header.php';
?>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

<style>
    .wizard-card { border-radius: 1rem; overflow: hidden; }
    .wizard-step-badge {
        width: 28px; height: 28px; font-size: .8rem; font-weight: 700;
        display: inline-flex; align-items: center; justify-content: center;
        border-radius: 50%; background: #e9ecef; color: #495057;
    }
    .wizard-step-label.active .wizard-step-badge { background: #212529; color: #fff; }
    .wizard-step-label.done .wizard-step-badge { background: #198754; color: #fff; }
    .wizard-step-label.active { color: #212529; font-weight: 600; }
    .choice-card {
        cursor: pointer; border: 2px solid #dee2e6; border-radius: .75rem;
    }
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
        padding: .65rem .85rem; user-select: none;
    }
    .btn-check:checked + .amenity-tile {
        border-color: #212529; background-color: #f1f3f5; font-weight: 600;
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
                    <div class="progress-bar bg-dark" id="wizardProgressBar" style="width: 20%;"></div>
                </div>
                <div class="d-flex justify-content-between small text-muted flex-wrap gap-2">
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

                <form id="wizardForm" method="POST" action="procesar_publicacion.php" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">

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
                                    <option value="<?= (int) $tipo['id_tipo'] ?>"><?= htmlspecialchars($tipo['nombre']) ?></option>
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
                        <p class="text-muted small mb-4">Indica la dirección y haz clic en el mapa para ubicar tu alojamiento.</p>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="departamento" class="form-label fw-semibold">Departamento <span class="text-danger">*</span></label>
                                <select class="form-select" id="departamento" name="departamento" required>
                                    <option value="" disabled selected>Selecciona un departamento...</option>
                                    <?php foreach ($departamentosSV as $dep): ?>
                                        <option value="<?= htmlspecialchars($dep) ?>"><?= htmlspecialchars($dep) ?></option>
                                    <?php endforeach; ?>
                                </select>
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
                                    <label class="form-label fw-semibold mb-0">Pin en mapa <span class="text-danger">*</span> <small class="text-muted fw-normal">(Haz clic para colocar el pin)</small></label>
                                    <button type="button" class="btn btn-link btn-sm text-decoration-none p-0" id="btnLimpiarCoords">Limpiar pin</button>
                                </div>
                                <div id="mapPublicar" class="border rounded-3 bg-light" style="height: 260px;"></div>
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
                                                       id="am_<?= (int) $am['id'] ?>" value="<?= (int) $am['id'] ?>">
                                                <label class="amenity-tile w-100 h-100 d-flex align-items-center gap-2 small" for="am_<?= (int) $am['id'] ?>">
                                                    <span>✓</span>
                                                    <span><?= htmlspecialchars($am['nombre']) ?></span>
                                                </label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
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

                    <!-- PASO 5: Imágenes, tarifas y métodos de pago -->
                    <section class="wizard-step d-none" data-step="5">
                        <h2 class="h5 fw-bold mb-1">Fotos, tarifa y métodos de pago</h2>
                        <p class="text-muted small mb-4">Agrega al menos 1 foto (máximo 8), define la tarifa y elige cómo aceptas el pago.</p>

                        <div class="card bg-light border-0 p-3 mb-3">
                            <div class="mb-3">
                                <label for="inputImagenesFiles" class="form-label small fw-semibold">Subir imágenes desde tu dispositivo (JPG, PNG, WEBP — máx. 5 MB cada una)</label>
                                <input type="file" class="form-control" id="inputImagenesFiles" name="imagenes[]" accept="image/jpeg,image/png,image/webp" multiple>
                            </div>

                            <div>
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <label class="form-label small fw-semibold mb-0">O agregar enlaces (URLs) de imágenes</label>
                                    <button type="button" class="btn btn-outline-dark btn-sm" id="btnAgregarUrl">+ Otra URL</button>
                                </div>
                                <div id="listaUrls" class="d-flex flex-column gap-2">
                                    <input type="url" class="form-control" name="imagenes_url[]" placeholder="https://ejemplo.com/foto-casa.jpg">
                                </div>
                            </div>
                        </div>

                        <hr class="my-3">

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label for="precio_noche" class="form-label fw-semibold">Precio por persona / noche (USD $) <span class="text-danger">*</span></label>
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

                        <div class="mt-3">
                            <label class="form-label fw-semibold d-block">Métodos de pago informativos aceptados <span class="text-danger">*</span></label>
                            <small class="text-muted d-block mb-2">El pago se coordina directamente con el huésped; la plataforma no procesa cobros en línea.</small>
                            <div class="row g-2">
                                <?php foreach ($metodosPago as $mp): ?>
                                    <div class="col-md-6">
                                        <div class="form-check border rounded p-2 ps-4">
                                            <input class="form-check-input metodo-pago-check" type="checkbox"
                                                   name="metodos_pago[]" value="<?= (int) $mp['id_metodo_pago'] ?>"
                                                   id="mp_<?= (int) $mp['id_metodo_pago'] ?>">
                                            <label class="form-check-label fw-semibold" for="mp_<?= (int) $mp['id_metodo_pago'] ?>">
                                                <?= htmlspecialchars($mp['nombre']) ?>
                                            </label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
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
const form = document.getElementById('wizardForm');
const steps = document.querySelectorAll('.wizard-step');
const indicators = document.querySelectorAll('[data-step-indicator]');
const progressBar = document.getElementById('wizardProgressBar');
const stepCounterText = document.getElementById('stepCounterText');
const alertBox = document.getElementById('wizardAlertBox');

const btnAnterior = document.getElementById('btnAnterior');
const btnCancelar = document.getElementById('btnCancelar');
const btnSiguiente = document.getElementById('btnSiguiente');
const btnPublicar = document.getElementById('btnPublicar');

let currentStep = 1;
const totalSteps = steps.length;

function mostrarAlerta(msg) {
    alertBox.textContent = msg;
    alertBox.classList.remove('d-none');
    alertBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function limpiarAlerta() {
    alertBox.classList.add('d-none');
    alertBox.textContent = '';
}

// Sincronizar ciudad hacia campo oculto municipio
const ciudadInput = document.getElementById('ciudad');
const municipioHidden = document.getElementById('municipio');
ciudadInput.addEventListener('input', function () {
    municipioHidden.value = ciudadInput.value;
});

// Mapa Leaflet para elegir coordenadas
const latInput = document.getElementById('latitud');
const lngInput = document.getElementById('longitud');
const map = L.map('mapPublicar').setView([13.6929403, -89.2181911], 11);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '&copy; OpenStreetMap'
}).addTo(map);
let marker = null;

function colocarPin(lat, lng) {
    if (marker) {
        marker.setLatLng([lat, lng]);
    } else {
        marker = L.marker([lat, lng], { draggable: true }).addTo(map);
        marker.on('dragend', function () {
            const p = marker.getLatLng();
            latInput.value = p.lat.toFixed(6);
            lngInput.value = p.lng.toFixed(6);
        });
    }
    latInput.value = lat.toFixed(6);
    lngInput.value = lng.toFixed(6);
}

colocarPin(13.6929403, -89.2181911);

map.on('click', function (e) {
    colocarPin(e.latlng.lat, e.latlng.lng);
});

document.getElementById('btnLimpiarCoords').addEventListener('click', function () {
    latInput.value = '';
    lngInput.value = '';
    if (marker) {
        map.removeLayer(marker);
        marker = null;
    }
});

// Contadores +/- del Paso 3
document.querySelectorAll('.counter-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
        const input = document.getElementById(btn.dataset.target);
        const min = parseFloat(input.dataset.min);
        const max = parseFloat(input.dataset.max);
        const delta = parseFloat(btn.dataset.delta);
        const step = input.dataset.step;
        let next = parseFloat(input.value) + delta;
        if (next < min) next = min;
        if (next > max) next = max;

        input.value = step === '0.5' ? next.toFixed(1) : String(Math.round(next));
        document.getElementById('disp_' + input.id).textContent = input.value;
    });
});

// Agregar otro campo de URL de imagen
document.getElementById('btnAgregarUrl').addEventListener('click', function () {
    const lista = document.getElementById('listaUrls');
    if (lista.children.length >= 8) {
        mostrarAlerta('Puedes agregar máximo 8 imágenes.');
        return;
    }
    const input = document.createElement('input');
    input.type = 'url';
    input.name = 'imagenes_url[]';
    input.className = 'form-control';
    input.placeholder = 'https://ejemplo.com/otra-foto.jpg';
    lista.appendChild(input);
});

// Validación por paso
function validarPaso(step) {
    limpiarAlerta();

    if (step === 1) {
        if (!document.getElementById('titulo').value.trim()) {
            mostrarAlerta('Ingresa un título para tu alojamiento.'); return false;
        }
        if (!document.getElementById('id_tipo').value) {
            mostrarAlerta('Selecciona un tipo de propiedad.'); return false;
        }
        if (!document.getElementById('descripcion').value.trim()) {
            mostrarAlerta('Ingresa una descripción para tu alojamiento.'); return false;
        }
    } else if (step === 2) {
        if (!document.getElementById('departamento').value.trim() || !ciudadInput.value.trim()) {
            mostrarAlerta('El departamento y la ciudad/municipio son obligatorios.'); return false;
        }
        if (!document.getElementById('direccion').value.trim()) {
            mostrarAlerta('La dirección exacta es obligatoria.'); return false;
        }
        const lat = latInput.value.trim();
        const lng = lngInput.value.trim();
        if (lat === '' || lng === '' || isNaN(lat) || isNaN(lng)) {
            mostrarAlerta('Haz clic en el mapa para marcar la ubicación de tu alojamiento.'); return false;
        }
        municipioHidden.value = ciudadInput.value.trim();
    } else if (step === 4) {
        if (!document.getElementById('hora_checkin').value || !document.getElementById('hora_checkout').value) {
            mostrarAlerta('Define los horarios de check-in y check-out.'); return false;
        }
        const estancia = parseInt(document.getElementById('estancia_minima').value, 10);
        if (isNaN(estancia) || estancia < 1 || estancia > 365) {
            mostrarAlerta('La estancia mínima debe estar entre 1 y 365 noches.'); return false;
        }
    } else if (step === 5) {
        const archivos = document.getElementById('inputImagenesFiles').files.length;
        const urls = Array.from(document.querySelectorAll('input[name="imagenes_url[]"]'))
            .filter(inp => inp.value.trim() !== '').length;

        if (archivos + urls < 1) {
            mostrarAlerta('Agrega al menos 1 imagen (archivo o URL).'); return false;
        }
        if (archivos + urls > 8) {
            mostrarAlerta('Puedes agregar un máximo de 8 imágenes.'); return false;
        }

        const precio = parseFloat(document.getElementById('precio_noche').value);
        if (isNaN(precio) || precio <= 0) {
            mostrarAlerta('El precio por persona/noche debe ser mayor a $0.'); return false;
        }

        const metodosMarcados = document.querySelectorAll('.metodo-pago-check:checked').length;
        if (metodosMarcados < 1) {
            mostrarAlerta('Selecciona al menos un método de pago informativo.'); return false;
        }
    }

    return true;
}

let publishUnlockTimer = null;

function mostrarPaso(step, limpiarAlertas = true) {
    if (limpiarAlertas) {
        limpiarAlerta();
    }
    currentStep = step;
    steps.forEach(function (s) {
        s.classList.toggle('d-none', Number(s.dataset.step) !== step);
    });
    indicators.forEach(function (ind) {
        const n = Number(ind.dataset.stepIndicator);
        ind.classList.toggle('active', n === step);
        ind.classList.toggle('done', n < step);
    });

    const pct = Math.round((step / totalSteps) * 100);
    progressBar.style.width = pct + '%';
    stepCounterText.textContent = 'Paso ' + step + ' de ' + totalSteps;

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
        publishUnlockTimer = setTimeout(function () {
            publishUnlockTimer = null;
            if (currentStep === totalSteps) {
                btnPublicar.disabled = false;
            }
        }, 250);
    }

    if (step === 2) {
        setTimeout(function () { map.invalidateSize(); }, 150);
    }
}

btnSiguiente.addEventListener('click', function () {
    if (validarPaso(currentStep) && currentStep < totalSteps) {
        mostrarPaso(currentStep + 1);
    }
});

btnAnterior.addEventListener('click', function () {
    if (currentStep > 1) {
        mostrarPaso(currentStep - 1);
    }
});

form.addEventListener('keydown', function (e) {
    if (e.key !== 'Enter') return;
    const target = e.target;
    if (!target || target.tagName !== 'INPUT') return;
    if (currentStep < totalSteps) {
        e.preventDefault();
        if (validarPaso(currentStep)) {
            mostrarPaso(currentStep + 1);
        }
    }
});

form.addEventListener('submit', function (e) {
    if (currentStep < totalSteps) {
        e.preventDefault();
        if (validarPaso(currentStep)) {
            mostrarPaso(currentStep + 1);
        }
        return;
    }
    if (publishUnlockTimer !== null) {
        e.preventDefault();
        return;
    }
    for (let step = 1; step < totalSteps; step++) {
        if (!validarPaso(step)) {
            e.preventDefault();
            mostrarPaso(step, false);
            return;
        }
    }
    if (!validarPaso(5)) {
        e.preventDefault();
    }
});

mostrarPaso(1, false);
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>

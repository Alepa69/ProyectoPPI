// Asistente de 5 pasos y mapa Leaflet en publicar_casa.php
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

if (form && steps.length > 0) {
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
                .filter(function (inp) { return inp.value.trim() !== ''; }).length;

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
}

(() => {
    const contenedor = document.getElementById('avisos-reservas');
    if (!contenedor) return;

    document.body.append(contenedor);
    const tarjeta = contenedor.querySelector('.aviso-reserva');
    const enlace = tarjeta.querySelector('a');
    const titulo = tarjeta.querySelector('[data-aviso-titulo]');
    const alojamiento = tarjeta.querySelector('[data-aviso-alojamiento]');
    const accion = tarjeta.querySelector('.aviso-reserva-accion');
    const estado = contenedor.querySelector('[data-aviso-estado]');
    const destinos = document.querySelectorAll('[data-avisos-reserva]');
    const mostrados = new Set();
    const leidos = new Set();
    let avisos = [];
    let actual;
    let temporizador;
    let consultando = false;

    function guardar(aviso, accionAviso) {
        const body = new URLSearchParams({
            id: aviso.id,
            accion: accionAviso,
            csrf: contenedor.dataset.csrf,
        });
        fetch('aviso_reservas.php', { method: 'POST', body, keepalive: true }).catch(() => {});
    }

    function actualizarIndicadores() {
        const pendientes = new Set();
        for (const aviso of avisos) {
            if (leidos.has(aviso.id)) continue;
            pendientes.add(aviso.rol);
            pendientes.add(aviso.rol + ':' + aviso.reserva);
        }
        for (const destino of destinos) {
            destino.classList.toggle('con-aviso-reserva', pendientes.has(destino.dataset.avisosReserva));
        }
    }

    function ocultar() {
        clearTimeout(temporizador);
        tarjeta.hidden = true;
        actual = undefined;
    }

    function mostrarSiguiente() {
        if (document.hidden || actual) return;
        const aviso = avisos.find((item) => item.mostrar && !mostrados.has(item.id) && !leidos.has(item.id));
        if (!aviso) return;

        actual = aviso;
        mostrados.add(aviso.id);
        enlace.href = aviso.url;
        titulo.textContent = aviso.titulo;
        alojamiento.textContent = aviso.alojamiento;
        accion.textContent = aviso.url.startsWith('panel_anfitrion.php') ? 'Ver reservas recibidas →' : 'Ver reserva →';
        tarjeta.hidden = false;
        estado.textContent = aviso.titulo + '. ' + aviso.alojamiento;
        guardar(aviso, 'mostrar');

        temporizador = setTimeout(() => {
            ocultar();
            mostrarSiguiente();
        }, 5000);
    }

    async function actualizar() {
        if (document.hidden || consultando) return;
        consultando = true;

        try {
            const respuesta = await fetch('aviso_reservas.php', { cache: 'no-store' });
            if (respuesta.status === 401) {
                avisos = [];
            } else if (respuesta.ok) {
                avisos = (await respuesta.json()).avisos;
            } else {
                return;
            }
            if (actual && !avisos.some((aviso) => aviso.id === actual.id)) ocultar();
            actualizarIndicadores();
            mostrarSiguiente();
        } catch {
            // Una desconexión temporal conserva los indicadores hasta la próxima consulta.
        } finally {
            consultando = false;
        }
    }

    enlace.addEventListener('click', () => {
        if (!actual) return;
        leidos.add(actual.id);
        guardar(actual, 'leer');
        ocultar();
        actualizarIndicadores();
    });
    tarjeta.querySelector('button').addEventListener('click', () => {
        ocultar();
        mostrarSiguiente();
    });

    window.setInterval(actualizar, 10000);
    window.addEventListener('focus', actualizar);
    window.addEventListener('pageshow', (evento) => {
        if (evento.persisted) actualizar();
    });
    document.addEventListener('visibilitychange', actualizar);
    actualizar();
})();

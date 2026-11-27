const listaCasas = document.getElementById('lista-alojamientos');
const botonMostrarMas = document.getElementById('mostrar-mas');
const mensajeAlojamientos = document.getElementById('mensaje-alojamientos');

function casasEnTresFilas() {
    // La cuadrícula tiene más columnas en pantallas anchas.
    const columnas = getComputedStyle(listaCasas).gridTemplateColumns.split(' ').length;
    return columnas * 3;
}

async function cargarCasas(cantidad) {
    botonMostrarMas.disabled = true;
    botonMostrarMas.textContent = 'Cargando…';
    mensajeAlojamientos.textContent = '';

    // Partimos de la URL actual para conservar los filtros y el orden.
    const url = new URL(window.location.href);
    url.searchParams.set('cargar_mas', '1');
    url.searchParams.set('desde', listaCasas.children.length);
    url.searchParams.set('cantidad', cantidad);

    try {
        const respuesta = await fetch(url);
        if (!respuesta.ok) {
            throw new Error('La solicitud falló.');
        }

        const datos = await respuesta.json();
        listaCasas.insertAdjacentHTML('beforeend', datos.tarjetas);
        botonMostrarMas.hidden = !datos.hayMas;
        agregarCasasAlMapa(datos.casas);
    } catch (error) {
        mensajeAlojamientos.textContent = 'No se pudieron cargar más alojamientos. Intenta de nuevo.';
    } finally {
        botonMostrarMas.disabled = false;
        botonMostrarMas.textContent = 'Mostrar más';
    }
}

if (botonMostrarMas) {
    botonMostrarMas.hidden = false;
    botonMostrarMas.addEventListener('click', function () {
        cargarCasas(casasEnTresFilas());
    });

    // PHP muestra tres casas. Completamos las primeras tres filas si hay más columnas.
    const casasQueFaltan = casasEnTresFilas() - listaCasas.children.length;
    if (casasQueFaltan > 0) {
        cargarCasas(casasQueFaltan);
    }
}

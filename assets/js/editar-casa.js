// Mapa Leaflet para actualizar coordenadas en editar_casa.php
const inputLat = document.getElementById('latitud');
const inputLng = document.getElementById('longitud');
const mapaContenedor = document.getElementById('mapaSelector');

if (inputLat && inputLng && mapaContenedor && window.L) {
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
}

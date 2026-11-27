// Mapa interactivo del catálogo principal (index.php)
const mapEl = document.getElementById('map');
let mapaInicio;

if (mapEl && window.L) {
    const mapLat = parseFloat(mapEl.dataset.lat) || 13.69;
    const mapLng = parseFloat(mapEl.dataset.lng) || -89.23;
    const casas = JSON.parse(mapEl.dataset.casas || '[]');

    mapaInicio = L.map('map').setView([mapLat, mapLng], 12);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(mapaInicio);
    agregarCasasAlMapa(casas);
}

function agregarCasasAlMapa(casas) {
    if (!mapaInicio) return;

    casas.forEach(function (c) {
        if (c.latitud === null || c.longitud === null || c.latitud === '' || c.longitud === '') return;
        const lat = parseFloat(c.latitud);
        const lng = parseFloat(c.longitud);
        if (Number.isNaN(lat) || Number.isNaN(lng)) return;

        const popup = document.createElement('div');
        popup.style.width = '190px';

        const img = document.createElement('img');
        img.src = c.foto_principal;
        img.alt = c.titulo;
        img.style.width = '100%';
        img.style.height = '105px';
        img.style.objectFit = 'cover';
        img.style.borderRadius = '8px';
        img.style.marginBottom = '6px';
        popup.appendChild(img);

        const link = document.createElement('a');
        link.href = 'listing.php?id=' + encodeURIComponent(c.id_alojamiento);
        link.textContent = c.titulo;
        link.style.display = 'block';
        popup.appendChild(link);

        const precio = document.createElement('span');
        precio.textContent = '$' + Number(c.precio_persona_noche).toFixed(2) + ' / persona · noche';
        precio.style.fontSize = '0.82rem';
        precio.style.color = '#495057';
        popup.appendChild(precio);

        L.marker([lat, lng]).addTo(mapaInicio).bindPopup(popup);
    });
}

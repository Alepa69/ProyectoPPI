// Visor de galería y cálculo de reserva en listing.php
const galleryModalElement = document.getElementById('listingGalleryModal');
const photoCarouselElement = document.getElementById('listingPhotoCarousel');

if (galleryModalElement && photoCarouselElement && window.bootstrap) {
    const galleryModal = bootstrap.Modal.getOrCreateInstance(galleryModalElement);
    const carouselItems = Array.from(photoCarouselElement.querySelectorAll('.carousel-item'));

    document.querySelectorAll('[data-gallery-index]').forEach(function (button) {
        button.addEventListener('click', function () {
            const selectedIndex = Number(button.dataset.galleryIndex || 0);
            carouselItems.forEach(function (item, index) {
                item.classList.toggle('active', index === selectedIndex);
            });
            galleryModal.show();
        });
    });
}

// Cálculo en vivo del importe estimado (noches x huéspedes x precio)
const bookingForm = document.querySelector('.listing-booking-form');
const inputEntrada = document.getElementById('entrada');
const inputSalida = document.getElementById('salida');
const inputHuespedes = document.getElementById('huespedes');
const resumenCalculo = document.getElementById('resumenCalculo');

if (bookingForm && inputEntrada && inputSalida && resumenCalculo) {
    const precioPersonaNoche = parseFloat(bookingForm.dataset.precio || '0');
    const estanciaMinima = parseInt(bookingForm.dataset.estanciaMinima || '1', 10);

    function actualizarCalculo() {
        if (!inputEntrada.value || !inputSalida.value || !inputHuespedes) {
            resumenCalculo.classList.add('d-none');
            return;
        }
        const f1 = new Date(inputEntrada.value + 'T00:00:00');
        const f2 = new Date(inputSalida.value + 'T00:00:00');
        const noches = Math.round((f2 - f1) / (1000 * 60 * 60 * 24));
        const huespedes = parseInt(inputHuespedes.value || '1', 10);

        if (noches > 0 && huespedes > 0) {
            const total = (noches * huespedes * precioPersonaNoche).toFixed(2);
            resumenCalculo.innerHTML = noches + ' noche(s) × ' + huespedes + ' huésped(es) × $' +
                precioPersonaNoche.toFixed(2) + ' = <strong>$' + total + ' USD</strong>';
            resumenCalculo.classList.remove('d-none');
        } else {
            resumenCalculo.classList.add('d-none');
        }
    }

    inputEntrada.addEventListener('change', function () {
        if (this.value) {
            const fechaMinSalida = new Date(this.value + 'T00:00:00');
            fechaMinSalida.setDate(fechaMinSalida.getDate() + estanciaMinima);
            const minStr = fechaMinSalida.getFullYear() + '-'
                + String(fechaMinSalida.getMonth() + 1).padStart(2, '0') + '-'
                + String(fechaMinSalida.getDate()).padStart(2, '0');
            inputSalida.min = minStr;

            if (inputSalida.value && inputSalida.value < minStr) {
                inputSalida.value = minStr;
            }
        }
        actualizarCalculo();
    });

    inputSalida.addEventListener('change', actualizarCalculo);
    if (inputHuespedes) {
        inputHuespedes.addEventListener('input', actualizarCalculo);
    }
}

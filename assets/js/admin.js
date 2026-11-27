// Acción de bloqueo administrativo de publicaciones (admin.php y listing.php)
function bloquearPublicacion(form) {
    const motivo = prompt('Indica el motivo de la suspensión/bloqueo (opcional):', 'Incumplimiento de normas de la plataforma');
    if (motivo === null) return;

    const inputAccion = document.createElement('input');
    inputAccion.type = 'hidden';
    inputAccion.name = 'accion';
    inputAccion.value = 'suspender';

    const inputMotivo = document.createElement('input');
    inputMotivo.type = 'hidden';
    inputMotivo.name = 'motivo';
    inputMotivo.value = motivo.trim();

    form.appendChild(inputAccion);
    form.appendChild(inputMotivo);
    form.submit();
}

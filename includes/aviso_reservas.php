<?php
require_once __DIR__ . '/formularios.php';

if (empty($_SESSION['id_usuario'])) {
    return;
}
?>
<div id="avisos-reservas" class="avisos-reservas" data-csrf="<?= e(tokenFormulario()) ?>">
    <div class="aviso-reserva" hidden>
        <a class="aviso-reserva-enlace" href="panel_cliente.php">
            <span class="aviso-reserva-icono" aria-hidden="true">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7">
                    <rect x="3.5" y="5.5" width="17" height="15" rx="2"></rect>
                    <path d="M8 3.5v4m8-4v4M3.5 10h17m-12 5 2 2 4-4" stroke-linecap="round" stroke-linejoin="round"></path>
                </svg>
            </span>
            <span class="aviso-reserva-texto">
                <strong data-aviso-titulo></strong>
                <span data-aviso-alojamiento></span>
                <span class="aviso-reserva-accion">Ver reserva <span aria-hidden="true">→</span></span>
            </span>
        </a>
        <button class="aviso-reserva-cerrar" type="button" aria-label="Cerrar aviso">×</button>
    </div>
    <span class="visually-hidden" data-aviso-estado role="status"></span>
</div>
<script src="assets/js/aviso-reservas.js?v=2" defer></script>

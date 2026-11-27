// Desplazar automáticamente al último mensaje en chat.php
const cajaMensajes = document.getElementById('caja-mensajes');
if (cajaMensajes) {
    cajaMensajes.scrollTop = cajaMensajes.scrollHeight;
}

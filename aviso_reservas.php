<?php
session_start(['read_and_close' => true]);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$usuario = (int) ($_SESSION['id_usuario'] ?? 0);
if (!$usuario) {
    http_response_code(401);
    exit;
}

require __DIR__ . '/config/conexion.php';
require __DIR__ . '/includes/reservas.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $accion = $_POST['accion'] ?? '';
    if (!$id || !in_array($accion, ['mostrar', 'leer'], true) || !tokenFormularioValido()) {
        http_response_code(400);
        exit;
    }
    marcarAvisoReserva($conexion, $usuario, $id, $accion === 'leer');
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET, POST');
    http_response_code(405);
    exit;
}

echo json_encode(['avisos' => avisosDeReservas($conexion, $usuario, relojReservas())], JSON_UNESCAPED_UNICODE);

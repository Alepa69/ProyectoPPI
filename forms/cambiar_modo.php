<?php
require_once __DIR__ . '/../config/sesion.php';

$modo = $_GET['modo'] ?? 'huesped';
if ($modo === 'anfitrion') {
    $_SESSION['modo'] = 'anfitrion';
    header('Location: ../panel_anfitrion.php');
} else {
    $_SESSION['modo'] = 'huesped';
    header('Location: ../index.php');
}
exit;

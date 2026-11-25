<?php
require_once __DIR__ . '/config/sesion.php';
$_SESSION['modo'] = 'huesped';

$esAnfitrion = false;
require __DIR__ . '/includes/panel_reservas.php';

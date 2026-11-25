<?php
require_once __DIR__ . '/config/sesion.php';
$_SESSION['modo'] = 'anfitrion';

$esAnfitrion = true;
require __DIR__ . '/includes/panel_reservas.php';

<?php
$rutaConfig = __DIR__ . '/base.local.php'; 
if (!file_exists($rutaConfig)) {
    die("Error: Falta el archivo config/base.local.php. Configura tus credenciales como base.example.php");
}

$config = require $rutaConfig;

$conexion = new mysqli(
    $config['servidor'],
    $config['usuario'],
    $config['clave'],
    $config['base_datos'],
    $config['puerto']
);

if ($conexion->connect_error) {
    die("Error de conexión: " . $conexion->connect_error);
}

$conexion->set_charset("utf8mb4");
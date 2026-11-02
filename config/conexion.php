<?php

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$rutaConfiguracion = __DIR__ . '/base.local.php';

if (!is_file($rutaConfiguracion)) {
    http_response_code(500);
    exit('No se encontró config/base.local.php');
}

$config = require $rutaConfiguracion;

try {
    $conexion = new mysqli(
        $config['servidor'],
        $config['usuario'],
        $config['clave'],
        $config['base_datos'],
        $config['puerto']
    );

    $conexion->set_charset('utf8mb4');

} catch (mysqli_sql_exception $error) {
    error_log('Error de base de datos: ' . $error->getMessage());

    http_response_code(500);
    exit('No se pudo conectar. Revisa los datos de base.local.php');
}
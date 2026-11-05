<?php
$servidor = "localhost";
$usuario = "root";
$laclave = "";
$bbdd = "holaja";

$conexion = new mysqli($servidor, $usuario, $laclave, $bbdd);

$conexion->set_charset("utf8mb4");

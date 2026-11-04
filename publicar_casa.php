<?php
// Asegúrate de incluir aquí tu archivo de conexión a la base de datos
// include 'conexion.php';

$mensajeError = "";

// Solo ejecutamos el código si se envió el formulario
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    
    $titulo = $conexion->real_escape_string($_POST['titulo']); 
    $municipio = $conexion->real_escape_string($_POST['municipio']); 
    $departamento = $conexion->real_escape_string($_POST['departamento']); 
    $precio = (float)$_POST['precio']; 
    $foto = $conexion->real_escape_string($_POST['foto']); 

    // Asignamos un ID de anfitrión por defecto
    $id_anfitrion = 1; 

    // Consulta SQL
    $sqlAlojamiento = "INSERT INTO alojamientos (id_anfitrion, titulo, municipio, departamento, precio_persona_noche, estado) VALUES ($id_anfitrion, '$titulo', '$municipio', '$departamento', $precio, 'publicado')"; 

    // Ejecutar la inserción 
    if ($conexion->query($sqlAlojamiento)) { 
        $id_alojamiento = $conexion->insert_id; 
        
        // Guardar foto
        if (!empty($foto)) { 
            $sqlFoto = "INSERT INTO fotos_alojamiento (id_alojamiento, ruta_archivo, posicion) VALUES ($id_alojamiento, '$foto', 1)"; 
            $conexion->query($sqlFoto); 
        } 
        
        // Redireccionar al inicio
        header('Location: index.php'); 
        exit; 
    } else { 
        $mensajeError = "Error al guardar en la base de datos: " . $conexion->error; 
    } 
} 

// Título dinámico para la cabecera 
$pageTitle = 'Publicar nueva casa'; 
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($pageTitle) ? htmlspecialchars($pageTitle) : 'Airbnb Temu' ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>body { background-color: #f8f9fa; }</style>
</head>
<body>

    <!-- Barra de navegación -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark shadow-sm mb-4">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">🏡 Airbnb Temu</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link <?= (basename($_SERVER['PHP_SELF']) == 'index.php') ? 'active' : '' ?>" href="index.php">Inicio</a>
                <a class="nav-link <?= (basename($_SERVER['PHP_SELF']) == 'publicar_casa.php') ? 'active' : '' ?>" href="publicar_casa.php">Publicar casa</a>
            </div>
        </div>
    </nav>

    <div class="container mb-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow">
                    <div class="card-header bg-primary text-white">
                        🏡 Publicar una nueva casa
                    </div>
                    <div class="card-body">
                        
                        <?php if(!empty($mensajeError)): ?>
                            <div class="alert alert-danger" role="alert">
                                <?= htmlspecialchars($mensajeError) ?>
                            </div>
                        <?php endif; ?>

                        <!-- Formulario con los campos necesarios -->
                        <form method="POST" action="publicar_casa.php">
                            <div class="mb-3">
                                <label for="titulo" class="form-label">Título del alojamiento</label>
                                <input type="text" class="form-control" id="titulo" name="titulo" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="municipio" class="form-label">Municipio</label>
                                <input type="text" class="form-control" id="municipio" name="municipio" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="departamento" class="form-label">Departamento</label>
                                <input type="text" class="form-control" id="departamento" name="departamento" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="precio" class="form-label">Precio por persona/noche ($)</label>
                                <input type="number" step="0.01" class="form-control" id="precio" name="precio" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="foto" class="form-label">URL de la foto (Opcional)</label>
                                <input type="text" class="form-control" id="foto" name="foto">
                            </div>
                            
                            <button type="submit" class="btn btn-primary mt-3">Guardar Casa</button>
                        </form>
                        
                    </div>
                </div>
            </div>
        </div>
    </div>

</body>
</html>
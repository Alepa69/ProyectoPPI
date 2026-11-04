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

    <!-- Barra de navegación con enlaces dinámicos -->
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
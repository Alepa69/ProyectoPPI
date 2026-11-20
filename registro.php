<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/conexion.php';
$mensaje="";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $nombre = trim($_POST['nombre']??'');
    $apellido = trim($_POST['apellido']??'');
    $correo = trim($_POST['correo'] ??'');
    $clave = trim($_POST['clave'] ??'');
    $clave2 = trim($_POST['clave2'] ??'');

    if (!empty($nombre)&&!empty($apellido)&&!empty($correo)&&!empty($clave)&&!empty($clave2)) {
        
        if ($clave !== $clave2) {
            $mensaje = "<div class='alert alert-warning'>Las contraseñas no coinciden.</div>";
        } elseif (strlen($clave) < 8 || !preg_match('/[A-Z]/', $clave) || !preg_match('/[0-9]/', $clave)) {
            $mensaje = "<div class='alert alert-warning'>La contraseña debe tener al menos 8 caracteres, una mayúscula y un número.</div>";
        } else {
            //encriptado
            $contraEncriptada = password_hash($clave, PASSWORD_DEFAULT);

            $sql="insert into
            usuarios (nombre, apellido, correo, password_hash)
            values(?, ?, ?, ?)";

            $stmt =$conexion->prepare($sql);
            $stmt->bind_param("ssss", $nombre, $apellido, $correo, $contraEncriptada);

            try {
                if ($stmt->execute()) {
                    $mensaje = "<div class='alert alert-success'>Cuenta creada, inicia sesion <a href='login.php' class='alert-link'>iniciar sesion</a>.</div>";
                }
            } catch (mysqli_sql_exception $e) {
                
            //errores cuenta ya existe
                if ($e->getCode()===1062) {
                    $mensaje = "<div class='alert alert-danger'>Este correo ya esta en uso</div>";
                } else {
                    $mensaje = "<div class='alert alert-danger'>Error al crear la cuenta.</div>";
                }
            
            $stmt->close();
            }
        }
    } else {
        $mensaje = "<div class='alert alert-warning'>Por favor completa todos los campos.</div>";
    }
}

$pageTitle = "Registro de Usuario HALOJA";
require_once __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h2 class="mb-4 text-center">Crear Cuenta</h2>

                <?= $mensaje ?>

                <form action="registro.php" method="POST">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nombre:</label>
                            <input type="text" name="nombre" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Apellido:</label>
                            <input type="text" name="apellido" class="form-control" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Correo electronico:</label>
                        <input type="email" name="correo" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Contraseña:</label>
                        <input type="password" name="clave" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Confirmar Contraseña:</label>
                        <input type="password" name="clave2" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-dark w-100">Registrarme</button>
                </form>

                <p class="mt-3 text-center mb-0">
                    <a href="login.php">¿Ya tenias cuenta? Inicia sesion aqui</a>
                </p>
            </div>
        </div>
    </div>
</div>

</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
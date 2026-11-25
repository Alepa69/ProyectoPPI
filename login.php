<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/conexion.php';
$error="";
$alojamiento = (int) filter_input(INPUT_GET, 'alojamiento', FILTER_VALIDATE_INT);

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $correo=trim($_POST['correo']?? '');
    $clave =trim($_POST['clave']?? '');


    if(!empty($correo)&&!empty($clave)){
        //buscar usuario
        $sql = "
        select id_usuario, nombre, apellido, correo, password_hash, rol
        from usuarios
        where correo=? and estado='activo'";
        $stmt=$conexion->prepare($sql);
        $stmt->bind_param("s",$correo);
        $stmt->execute();
        $resultado = $stmt->get_result();

        if ($fila=$resultado->fetch_assoc()) {
            //comparar contra con el hash de la bd
            if (password_verify($clave, $fila['password_hash'])) {
                session_regenerate_id(true);
                $_SESSION = [];
                //credenciales guardadas
                $_SESSION['id_usuario']=$fila['id_usuario'];
                $_SESSION['nombre']=$fila['nombre'];
                $_SESSION['apellido']=$fila['apellido'];
                $_SESSION['correo']= $fila['correo'];
                $_SESSION['rol']=$fila['rol'];

                header('Location: ' . ($alojamiento > 0 ? 'listing.php?id=' . $alojamiento : 'index.php'), true, 303);
                exit();
            } else {
                $error="El correo o la contraseña no son correctos.";
            }
        } else {
            $error="El correo o la contraseña no son correctos.";
        }
        $stmt->close();
    } else {
        $error="Falta correo o contraseña";
    }
}

$pageTitle = "Iniciar sesion en HALOJA";
require_once __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-5">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h2 class="mb-4 text-center">Inicio de Sesion</h2>

                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form action="login.php<?= $alojamiento > 0 ? '?alojamiento=' . $alojamiento : '' ?>" method="POST">
                    <div class="mb-3">
                        <label class="form-label">Correo:</label>
                        <input type="email" name="correo" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Contraseña:</label>
                        <input type="password" name="clave" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-dark w-100">Ingresar</button>
                </form>

                <p class="mt-3 text-center mb-0">
                    <a href="registro.php">¿No tienes cuenta? Registrate aqui</a>
                </p>
            </div>
        </div>
    </div>
</div>

</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

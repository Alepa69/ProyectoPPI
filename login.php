<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Si ya inició sesión, redirigir al inicio
if (!empty($_SESSION['id_usuario'])) {
    header("Location: index.php");
    exit();
}

if (empty($_SESSION['csrf_auth'])) {
    $_SESSION['csrf_auth'] = bin2hex(random_bytes(32));
}

require_once __DIR__ . '/config/conexion.php';
$error = "";

if (isset($_GET['suspendido'])) {
    $error = "Tu cuenta ha sido suspendida. Contacta al administrador.";
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $token = $_POST['csrf'] ?? '';
    $correo = trim($_POST['correo'] ?? '');
    $clave = trim($_POST['clave'] ?? '');

    if (!hash_equals($_SESSION['csrf_auth'], (string)$token)) {
        $error = "Sesión de formulario inválida. Recarga la página.";
    } elseif (!empty($correo) && !empty($clave)) {
        // buscar usuario
        $sql = "
        select id_usuario, nombre, apellido, correo, password_hash, rol, estado
        from usuarios
        where correo = ?";
        $stmt = $conexion->prepare($sql);
        $stmt->bind_param("s", $correo);
        $stmt->execute();
        $resultado = $stmt->get_result();

        if ($fila = $resultado->fetch_assoc()) {
            // comparar contra con el hash de la bd
            if (password_verify($clave, $fila['password_hash'])) {
                if ($fila['estado'] !== 'activo') {
                    $error = "Tu cuenta ha sido suspendida. Contacta al administrador.";
                } else {
                    session_regenerate_id(true);
                    // credenciales guardadas
                    $_SESSION['id_usuario'] = $fila['id_usuario'];
                    $_SESSION['nombre'] = $fila['nombre'];
                    $_SESSION['apellido'] = $fila['apellido'];
                    $_SESSION['correo'] = $fila['correo'];
                    $_SESSION['rol'] = $fila['rol'];

                    header("Location: index.php");
                    exit();
                }
            } else {
                $error = "Contraseña incorrecta";
            }
        } else {
            $error = "Ese correo no esta registrado";
        }
        $stmt->close();
    } else {
        $error = "Falta correo o contraseña";
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

                <form action="login.php" method="POST">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf_auth']) ?>">
                    <div class="mb-3">
                        <label class="form-label">Correo:</label>
                        <input type="email" name="correo" class="form-control" maxlength="191" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="loginClave">Contraseña:</label>
                        <div class="input-group">
                            <input type="password" id="loginClave" name="clave" class="form-control" required>
                            <button type="button" class="btn btn-outline-secondary" id="btnVerLoginClave" title="Ver por 3 segundos">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        <div class="form-text">Mínimo 8 caracteres, una mayúscula y un número.</div>
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
<script src="assets/js/auth.js"></script>
</body>
</html>
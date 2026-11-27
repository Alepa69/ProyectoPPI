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
$mensaje="";
$registroExitoso = false;
$nombre = "";
$apellido = "";
$correo = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $token = $_POST['csrf'] ?? '';
    $nombre = trim($_POST['nombre']??'');
    $apellido = trim($_POST['apellido']??'');
    $correo = trim($_POST['correo'] ??'');
    $clave = trim($_POST['clave'] ??'');
    $clave2 = trim($_POST['clave2'] ??'');

    if (!hash_equals($_SESSION['csrf_auth'], (string)$token)) {
        $mensaje = "<div class='alert alert-danger'>Sesión de formulario inválida. Recarga la página.</div>";
    } elseif (!empty($nombre)&&!empty($apellido)&&!empty($correo)&&!empty($clave)&&!empty($clave2)) {
        if (mb_strlen($nombre) > 100 || mb_strlen($apellido) > 100) {
            $mensaje = "<div class='alert alert-warning'>Nombre y apellido deben tener máximo 100 caracteres.</div>";
        } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL) || strlen($correo) > 191) {
            $mensaje = "<div class='alert alert-warning'>Ingresa un correo electrónico válido (máximo 191 caracteres).</div>";
        } elseif ($clave !== $clave2) {
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
                    $registroExitoso = true;
                    $nombre = "";
                    $apellido = "";
                    $correo = "";
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
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf_auth']) ?>">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Nombre:</label>
                            <input type="text" name="nombre" class="form-control" maxlength="100" value="<?= htmlspecialchars($nombre) ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Apellido:</label>
                            <input type="text" name="apellido" class="form-control" maxlength="100" value="<?= htmlspecialchars($apellido) ?>" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Correo electronico:</label>
                        <input type="email" name="correo" class="form-control" maxlength="191" value="<?= htmlspecialchars($correo) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="regClave">Contraseña:</label>
                        <input type="password" id="regClave" name="clave" class="form-control" minlength="8" required>
                        <div class="form-text">Debe tener al menos <strong>8 caracteres</strong>, una <strong>mayúscula</strong> y un <strong>número</strong>.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="regClave2">Confirmar Contraseña:</label>
                        <input type="password" id="regClave2" name="clave2" class="form-control" minlength="8" required>
                        <button type="button" class="btn btn-outline-secondary btn-sm mt-2" id="btnVerClaves">
                            <i class="bi bi-eye"></i> Ver ambas contraseñas (3 s)
                        </button>
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

<?php if ($registroExitoso): ?>
<div class="modal fade" id="modalExito" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body text-center p-4">
                <h4 class="mb-3">Cuenta creada con exito</h4>
                <p>Tu cuenta ha sido registrada correctamente.</p>
                <a href="login.php" class="btn btn-dark px-4">Iniciar sesión</a>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const btnVerClaves = document.getElementById('btnVerClaves');
const regClave = document.getElementById('regClave');
const regClave2 = document.getElementById('regClave2');
let timerVerClaves = null;

if (btnVerClaves && regClave && regClave2) {
    btnVerClaves.addEventListener('click', function () {
        const ocultas = regClave.type === 'password';
        if (timerVerClaves) clearTimeout(timerVerClaves);

        if (ocultas) {
            regClave.type = 'text';
            regClave2.type = 'text';
            btnVerClaves.innerHTML = '<i class="bi bi-eye-slash"></i> Ocultar contraseñas';
            timerVerClaves = setTimeout(function () {
                regClave.type = 'password';
                regClave2.type = 'password';
                btnVerClaves.innerHTML = '<i class="bi bi-eye"></i> Ver ambas contraseñas (3 s)';
            }, 3000);
        } else {
            regClave.type = 'password';
            regClave2.type = 'password';
            btnVerClaves.innerHTML = '<i class="bi bi-eye"></i> Ver ambas contraseñas (3 s)';
        }
    });
}
</script>

<?php if ($registroExitoso): ?>
<script>
    var modal = new bootstrap.Modal(document.getElementById('modalExito'));
    modal.show();
</script>
<?php endif; ?>
</body>
</html>
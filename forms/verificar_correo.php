<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Si ya inició sesión, redirigir al inicio
if (!empty($_SESSION['id_usuario'])) {
    header('Location: ../index.php');
    exit;
}

if (empty($_SESSION['csrf_auth'])) {
    $_SESSION['csrf_auth'] = bin2hex(random_bytes(32));
}

require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../config/correo.php';
require_once __DIR__ . '/../dao/UsuarioDAO.php';

$usuarioDao = new UsuarioDAO($conexion);
$pendiente = $_SESSION['registro_pendiente'] ?? null;
$registroExitoso = false;
$mensaje = '';

if (!$pendiente) {
    header('Location: ../registro.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = (string) ($_POST['csrf'] ?? '');
    $accion = (string) ($_POST['accion'] ?? 'verificar');

    if (!hash_equals($_SESSION['csrf_auth'], $token)) {
        $mensaje = "<div class='alert alert-danger'>Sesión de formulario inválida. Recarga la página.</div>";
    } elseif ($accion === 'reenviar') {
        $restante = 60 - (time() - (int) ($pendiente['ultimo_envio'] ?? 0));
        if ($restante > 0) {
            $mensaje = "<div class='alert alert-warning'>Espera {$restante} segundos antes de solicitar otro código.</div>";
        } else {
            $codigoNuevo = (string) random_int(100000, 999999);
            $envio = enviarCodigoVerificacion($pendiente['correo'], $pendiente['nombre'], $codigoNuevo);

            if ($envio['ok']) {
                $_SESSION['registro_pendiente']['codigo'] = $codigoNuevo;
                $_SESSION['registro_pendiente']['expira'] = time() + 600;
                $_SESSION['registro_pendiente']['intentos'] = 0;
                $_SESSION['registro_pendiente']['ultimo_envio'] = time();
                $_SESSION['registro_pendiente']['codigo_simulado'] = !empty($envio['simulado']) ? $codigoNuevo : null;
                $pendiente = $_SESSION['registro_pendiente'];

                $mensaje = "<div class='alert alert-success'>Te enviamos un nuevo código de verificación.</div>";
            } else {
                $mensaje = "<div class='alert alert-danger'>" . htmlspecialchars($envio['mensaje']) . "</div>";
            }
        }
    } else {
        $codigoIngresado = trim((string) ($_POST['codigo'] ?? ''));

        if (time() > (int) ($pendiente['expira'] ?? 0)) {
            $mensaje = "<div class='alert alert-warning'>El código venció (10 minutos). Solicita uno nuevo.</div>";
        } elseif ((int) ($pendiente['intentos'] ?? 0) >= 5) {
            $mensaje = "<div class='alert alert-danger'>Superaste los 5 intentos permitidos. Solicita un código nuevo.</div>";
        } elseif (!preg_match('/^\d{6}$/', $codigoIngresado)) {
            $mensaje = "<div class='alert alert-warning'>Ingresa el código numérico de 6 dígitos.</div>";
        } elseif (!hash_equals((string) ($pendiente['codigo'] ?? ''), $codigoIngresado)) {
            $_SESSION['registro_pendiente']['intentos'] = (int) ($pendiente['intentos'] ?? 0) + 1;
            $pendiente = $_SESSION['registro_pendiente'];
            $quedan = max(0, 5 - $pendiente['intentos']);
            $mensaje = "<div class='alert alert-danger'>Código incorrecto. Te quedan {$quedan} intento(s).</div>";
        } else {
            try {
                if ($usuarioDao->registrar(
                    $pendiente['nombre'],
                    $pendiente['apellido'],
                    $pendiente['correo'],
                    $pendiente['password_hash']
                )) {
                    unset($_SESSION['registro_pendiente']);
                    $registroExitoso = true;
                } else {
                    $mensaje = "<div class='alert alert-danger'>Error al crear la cuenta.</div>";
                }
            } catch (mysqli_sql_exception $e) {
                if ($e->getCode() === 1062) {
                    unset($_SESSION['registro_pendiente']);
                    $mensaje = "<div class='alert alert-danger'>Este correo ya fue registrado. <a href='../login.php' class='alert-link'>Inicia sesión aquí</a>.</div>";
                } else {
                    $mensaje = "<div class='alert alert-danger'>Error al crear la cuenta.</div>";
                }
            }
        }
    }
}

$pageTitle = 'Verificar correo - HALOJA';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-5">
        <div class="card shadow-sm">
            <div class="card-body p-4">
                <h2 class="mb-3 text-center">Verifica tu correo</h2>
                <p class="text-muted text-center small mb-4">
                    Enviamos un código de 6 dígitos a <strong><?= htmlspecialchars($pendiente['correo']) ?></strong>. Tienes 10 minutos para ingresarlo.
                </p>

                <?php if (!empty($pendiente['codigo_simulado']) && !$registroExitoso): ?>
                    <div class="alert alert-info small">
                        <strong>Modo prueba local:</strong> Tu código de verificación es <strong><?= htmlspecialchars($pendiente['codigo_simulado']) ?></strong>.
                    </div>
                <?php endif; ?>

                <?= $mensaje ?>

                <?php if (!$registroExitoso): ?>
                    <form action="verificar_correo.php" method="POST">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf_auth']) ?>">
                        <input type="hidden" name="accion" value="verificar">
                        <div class="mb-3">
                            <label class="form-label" for="codigoVerificacion">Código de verificación:</label>
                            <input type="text"
                                   id="codigoVerificacion"
                                   name="codigo"
                                   class="form-control text-center fs-4 fw-bold"
                                   style="letter-spacing: 6px;"
                                   inputmode="numeric"
                                   pattern="\d{6}"
                                   maxlength="6"
                                   placeholder="000000"
                                   required
                                   autofocus>
                        </div>
                        <button type="submit" class="btn btn-dark w-100">Verificar y crear cuenta</button>
                    </form>

                    <form action="verificar_correo.php" method="POST" class="mt-3 text-center">
                        <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf_auth']) ?>">
                        <input type="hidden" name="accion" value="reenviar">
                        <button type="submit" class="btn btn-link btn-sm text-decoration-none">
                            ¿No recibiste el código? Reenviar código
                        </button>
                    </form>

                    <p class="mt-2 text-center mb-0 small">
                        <a href="../registro.php?cancelar=1" class="text-muted">Usar otro correo</a>
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if ($registroExitoso): ?>
<div class="modal fade" id="modalExito" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-body text-center p-4">
                <h4 class="mb-3">Cuenta creada con éxito</h4>
                <p>Tu correo fue verificado y tu cuenta ha sido registrada correctamente.</p>
                <a href="../login.php" class="btn btn-dark px-4">Iniciar sesión</a>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

</div>
<script src="../assets/js/bootstrap.bundle.min.js"></script>
<script src="../assets/js/auth.js"></script>
</body>
</html>

<?php
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../config/sesion.php';
require_once __DIR__ . '/../config/imagenes.php';

$idUsuario = (int) $_SESSION['id_usuario'];
$error = '';

// Obtener datos actuales
$stmt = $conexion->prepare("SELECT nombre, apellido, correo, telefono, biografia, foto_perfil, password_hash FROM usuarios WHERE id_usuario = ?");
$stmt->bind_param('i', $idUsuario);
$stmt->execute();
$usuario = $stmt->get_result()->fetch_assoc();

$datos = $usuario;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verificar_csrf();

    $nombre = trim($_POST['nombre'] ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $correo = trim($_POST['correo'] ?? '');
    $telefono = trim($_POST['telefono'] ?? '');
    $biografia = trim($_POST['biografia'] ?? '');
    $claveActual = $_POST['clave_actual'] ?? '';
    $claveNueva = $_POST['clave_nueva'] ?? '';
    $claveConfirmar = $_POST['clave_confirmar'] ?? '';

    $datos['nombre'] = $nombre;
    $datos['apellido'] = $apellido;
    $datos['correo'] = $correo;
    $datos['telefono'] = $telefono;
    $datos['biografia'] = $biografia;

    // Validaciones básicas
    if ($nombre === '' || $apellido === '' || mb_strlen($nombre) > 100 || mb_strlen($apellido) > 100) {
        $error = 'Ingresa un nombre y un apellido válidos (máximo 100 caracteres).';
    } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL) || strlen($correo) > 191) {
        $error = 'Ingresa un correo electrónico válido.';
    } elseif ($telefono !== '' && (!preg_match('/^\+?[\d ().-]{7,25}$/', $telefono) || strlen(preg_replace('/\D/', '', $telefono)) < 7)) {
        $error = 'Ingresa un teléfono válido, por ejemplo 7845-1201 o +503 2222-2222.';
    } elseif (mb_strlen($biografia) > 500) {
        $error = 'La presentación puede tener máximo 500 caracteres.';
    } elseif (($claveNueva !== '' || $correo !== $usuario['correo']) && !password_verify($claveActual, $usuario['password_hash'])) {
        $error = 'Ingresa tu contraseña actual para cambiar el correo o la contraseña.';
    }

    // Validar nueva contraseña si se escribió
    $nuevoHash = $usuario['password_hash'];
    if ($error === '' && $claveNueva !== '') {
        if ($claveNueva !== $claveConfirmar) {
            $error = 'Las nuevas contraseñas no coinciden.';
        } elseif (strlen($claveNueva) < 8 || !preg_match('/[A-Z]/', $claveNueva) || !preg_match('/[0-9]/', $claveNueva)) {
            $error = 'La nueva contraseña debe tener al menos 8 caracteres, una mayúscula y un número.';
        } else {
            $nuevoHash = password_hash($claveNueva, PASSWORD_DEFAULT);
        }
    }

    // Procesar foto de perfil (opcional)
    $fotoGuardar = !empty($_POST['quitar_foto']) ? null : $usuario['foto_perfil'];
    $rutaArchivoNuevo = null;

    if ($error === '' && isset($_FILES['foto_perfil']) && ($_FILES['foto_perfil']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $archivo = $_FILES['foto_perfil'];
        $extPermitidas = ['jpg', 'jpeg', 'png', 'webp'];
        $ext = strtolower(pathinfo($archivo['name'] ?? '', PATHINFO_EXTENSION));
        $pesoMax = 3 * 1024 * 1024; // 3 MB

        if (($archivo['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            $error = 'Error al subir la foto de perfil.';
        } elseif (($archivo['size'] ?? 0) <= 0 || $archivo['size'] > $pesoMax) {
            $error = 'La foto de perfil no puede superar los 3 MB.';
        } elseif (!in_array($ext, $extPermitidas, true) || @getimagesize($archivo['tmp_name']) === false) {
            $error = 'Usa una foto JPG, PNG o WEBP válida.';
        } else {
            $carpeta = __DIR__ . '/../uploads/perfiles';
            $resultadoImg = optimizarYGuardarImagen($archivo['tmp_name'], $carpeta, 'perfil_', $ext, 400, 400, 82);

            if (!$resultadoImg['ok']) {
                $error = $resultadoImg['error'];
            } else {
                $rutaRelativa = 'uploads/perfiles/' . $resultadoImg['archivo'];
                $rutaAbsoluta = $carpeta . '/' . $resultadoImg['archivo'];
                $fotoGuardar = $rutaRelativa;
                $rutaArchivoNuevo = $rutaAbsoluta;
            }
        }
    }

    if ($error === '') {
        try {
            $sql = "UPDATE usuarios
                    SET nombre = ?, apellido = ?, correo = ?,
                        telefono = NULLIF(?, ''), biografia = NULLIF(?, ''),
                        foto_perfil = ?, password_hash = ?
                    WHERE id_usuario = ?";
            $stmtUp = $conexion->prepare($sql);
            $stmtUp->bind_param('sssssssi', $nombre, $apellido, $correo, $telefono, $biografia, $fotoGuardar, $nuevoHash, $idUsuario);
            $stmtUp->execute();

            // Borrar foto anterior si cambió
            if (!empty($usuario['foto_perfil']) && $usuario['foto_perfil'] !== $fotoGuardar) {
                $rutaVieja = __DIR__ . '/../' . $usuario['foto_perfil'];
                if (is_file($rutaVieja) && strpos(realpath($rutaVieja) ?: '', realpath(__DIR__ . '/../uploads/perfiles') ?: '') === 0) {
                    @unlink($rutaVieja);
                }
            }

            $_SESSION['nombre'] = $nombre;
            $_SESSION['apellido'] = $apellido;
            $_SESSION['correo'] = $correo;
            $_SESSION['perfil_flash'] = 'Tu perfil fue actualizado correctamente.';

            header('Location: ../perfil.php');
            exit;
        } catch (mysqli_sql_exception $e) {
            if ($rutaArchivoNuevo && is_file($rutaArchivoNuevo)) {
                @unlink($rutaArchivoNuevo);
            }
            if ($e->getCode() === 1062) {
                $error = 'El correo ya está en uso por otra cuenta.';
            } else {
                $error = 'Error al actualizar los datos.';
            }
        }
    }
}

$pageTitle = 'Editar Perfil';
include __DIR__ . '/../includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <h4 class="mb-0">Editar mi perfil</h4>
            </div>
            <div class="card-body">
                <?php if ($error !== ''): ?>
                    <div class="alert alert-danger" role="alert"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <form method="POST" action="editar_perfil.php" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrf_token()) ?>">

                    <h6 class="text-muted mb-3">Datos de la cuenta</h6>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label" for="nombre">Nombre</label>
                            <input type="text" id="nombre" name="nombre" class="form-control" maxlength="100" value="<?= htmlspecialchars($datos['nombre']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="apellido">Apellido</label>
                            <input type="text" id="apellido" name="apellido" class="form-control" maxlength="100" value="<?= htmlspecialchars($datos['apellido']) ?>" required>
                        </div>
                        <div class="col-md-7">
                            <label class="form-label" for="correo">Correo electrónico</label>
                            <input type="email" id="correo" name="correo" class="form-control" maxlength="191" value="<?= htmlspecialchars($datos['correo']) ?>" required>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label" for="telefono">Teléfono</label>
                            <input type="tel" id="telefono" name="telefono" class="form-control" maxlength="25" placeholder="Ej: 7845-1201" value="<?= htmlspecialchars((string) ($datos['telefono'] ?? '')) ?>">
                        </div>
                    </div>

                    <h6 class="text-muted mb-3 pt-2 border-top">Presentación y foto</h6>
                    <div class="mb-3">
                        <label class="form-label" for="biografia">Sobre mí <small class="text-muted fw-normal">(Opcional)</small></label>
                        <textarea id="biografia" name="biografia" class="form-control" rows="3" maxlength="500" placeholder="Cuéntales a otros usuarios un poco sobre ti..."><?= htmlspecialchars((string) ($datos['biografia'] ?? '')) ?></textarea>
                    </div>

                    <div class="mb-4">
                        <label class="form-label" for="foto_perfil">Foto de perfil (JPG, PNG, WEBP — máx. 3 MB)</label>
                        <input type="file" id="foto_perfil" name="foto_perfil" class="form-control" accept="image/jpeg,image/png,image/webp">
                        <?php if (!empty($usuario['foto_perfil'])): ?>
                            <div class="form-check mt-2">
                                <input class="form-check-input" type="checkbox" id="quitar_foto" name="quitar_foto" value="1">
                                <label class="form-check-label small" for="quitar_foto">Quitar la foto actual</label>
                            </div>
                        <?php endif; ?>
                    </div>

                    <h6 class="text-muted mb-3 pt-2 border-top">Seguridad (necesaria para cambiar correo o contraseña)</h6>
                    <div class="row g-3 mb-4">
                        <div class="col-12">
                            <label class="form-label" for="clave_actual">Contraseña actual</label>
                            <input type="password" id="clave_actual" name="clave_actual" class="form-control" placeholder="Ingresa tu contraseña actual para confirmar cambios sensibles">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="clave_nueva">Nueva contraseña</label>
                            <input type="password" id="clave_nueva" name="clave_nueva" class="form-control" minlength="8">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="clave_confirmar">Confirmar nueva contraseña</label>
                            <input type="password" id="clave_confirmar" name="clave_confirmar" class="form-control">
                        </div>
                    </div>

                    <div class="d-flex justify-content-between">
                        <a href="../perfil.php" class="btn btn-outline-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-dark">Guardar cambios</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>

<?php
require_once __DIR__ . '/config/sesion.php';
require_once __DIR__ . '/includes/formularios.php';

$id = (int) $_SESSION['id_usuario'];
$stmt = $conexion->prepare('SELECT nombre, apellido, correo, telefono, biografia, foto_perfil, password_hash FROM usuarios WHERE id_usuario = ?');
$stmt->bind_param('i', $id);
$stmt->execute();
$usuario = $stmt->get_result()->fetch_assoc();
$datos = $usuario;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach (['nombre', 'apellido', 'correo', 'telefono', 'biografia'] as $campo) {
        $datos[$campo] = is_string($_POST[$campo] ?? null) ? trim($_POST[$campo]) : '';
    }
    $claveActual = is_string($_POST['clave_actual'] ?? null) ? trim($_POST['clave_actual']) : '';
    $claveNueva = is_string($_POST['clave_nueva'] ?? null) ? trim($_POST['clave_nueva']) : '';
    $claveConfirmar = is_string($_POST['clave_confirmar'] ?? null) ? trim($_POST['clave_confirmar']) : '';
    $fotoNueva = null;
    $rutaNueva = null;
    try {
        if (!tokenFormularioValido()) {
            throw new DomainException('Recarga la página y vuelve a guardar.');
        }
        if ($datos['nombre'] === '' || $datos['apellido'] === '' || mb_strlen($datos['nombre']) > 100 || mb_strlen($datos['apellido']) > 100) {
            throw new DomainException('Ingresa un nombre y un apellido de hasta 100 caracteres.');
        }
        if (!filter_var($datos['correo'], FILTER_VALIDATE_EMAIL) || strlen($datos['correo']) > 191) {
            throw new DomainException('Ingresa un correo electrónico válido.');
        }
        $telefono = $datos['telefono'];
        if ($telefono !== '' && (!preg_match('/^\+?[\d ().-]{7,25}$/D', $telefono)
            || strlen(preg_replace('/\D/', '', $telefono)) < 7)) {
            throw new DomainException('Ingresa un teléfono válido, por ejemplo +503 2222-2222.');
        }
        if (mb_strlen($datos['biografia']) > 500) {
            throw new DomainException('La presentación puede tener hasta 500 caracteres.');
        }
        if (($claveNueva !== '' || $datos['correo'] !== $usuario['correo']) && !password_verify($claveActual, $usuario['password_hash'])) {
            throw new DomainException('Confirma tu contraseña actual para cambiar el correo o la contraseña.');
        }
        $hash = $usuario['password_hash'];
        if ($claveNueva !== '') {
            if ($claveNueva !== $claveConfirmar || strlen($claveNueva) < 8 || !preg_match('/[A-Z]/', $claveNueva) || !preg_match('/[0-9]/', $claveNueva)) {
                throw new DomainException('Confirma una contraseña de al menos 8 caracteres, con una mayúscula y un número.');
            }
            $hash = password_hash($claveNueva, PASSWORD_DEFAULT);
        }

        $foto = isset($_POST['quitar_foto']) ? null : $usuario['foto_perfil'];
        $archivo = $_FILES['foto_perfil'] ?? null;
        if ($archivo && $archivo['error'] !== UPLOAD_ERR_NO_FILE) {
            $tipos = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            $info = $archivo['error'] === UPLOAD_ERR_OK && $archivo['size'] <= 3 * 1024 * 1024
                ? @getimagesize($archivo['tmp_name']) : false;
            $extension = $tipos[$info['mime'] ?? ''] ?? null;
            if (!$extension || $info[0] > 4096 || $info[1] > 4096) {
                throw new DomainException('Usa una foto JPG, PNG o WEBP de hasta 3 MB y 4096 píxeles por lado.');
            }
            $carpeta = __DIR__ . '/uploads/perfiles';
            if (!is_dir($carpeta) && !@mkdir($carpeta, 0755, true) && !is_dir($carpeta)) {
                throw new RuntimeException('No se pudo preparar la carpeta de perfiles.');
            }
            $fotoNueva = 'uploads/perfiles/' . bin2hex(random_bytes(16)) . '.' . $extension;
            if (!move_uploaded_file($archivo['tmp_name'], __DIR__ . '/' . $fotoNueva)) {
                throw new RuntimeException('No se pudo guardar la foto de perfil.');
            }
            $rutaNueva = __DIR__ . '/' . $fotoNueva;
            $foto = $fotoNueva;
        }

        // El correo y el teléfono se guardan en la cuenta privada.
        $stmt = $conexion->prepare("
            UPDATE usuarios SET nombre = ?, apellido = ?, correo = ?, telefono = NULLIF(?, ''),
                biografia = NULLIF(?, ''), foto_perfil = ?, password_hash = ?
            WHERE id_usuario = ?
        ");
        $stmt->bind_param('sssssssi', $datos['nombre'], $datos['apellido'], $datos['correo'], $telefono, $datos['biografia'], $foto, $hash, $id);
        $stmt->execute();
        if ($usuario['foto_perfil'] !== $foto && preg_match('~^uploads/perfiles/[0-9a-f]{32}\.(jpg|png|webp)$~D', $usuario['foto_perfil'] ?? '')) {
            @unlink(__DIR__ . '/' . $usuario['foto_perfil']);
        }

        $_SESSION['nombre'] = $datos['nombre'];
        $_SESSION['apellido'] = $datos['apellido'];
        $_SESSION['correo'] = $datos['correo'];
        $_SESSION['perfil_flash'] = 'Tu perfil se actualizó correctamente.';
        header('Location: perfil.php', true, 303);
        exit;
    } catch (DomainException $problema) {
        $error = $problema->getMessage();
    } catch (Throwable $problema) {
        if ($rutaNueva) {
            @unlink($rutaNueva);
        }
        if ($problema instanceof mysqli_sql_exception && $problema->getCode() === 1062) {
            $error = 'Ese correo ya está en uso.';
        } else {
            error_log('Editar perfil: ' . $problema->getMessage());
            $error = 'No se pudieron guardar los cambios. Inténtalo de nuevo.';
        }
    }
}

$pageTitle = 'Editar mi perfil';
require __DIR__ . '/includes/header.php';
?>
<div class="mx-auto" style="max-width: 44rem">
    <h1 class="h2 mb-4">Editar mi perfil</h1>
    <?php if ($error): ?><p class="alert alert-danger" role="alert"><?= e($error) ?></p><?php endif; ?>
    <form method="post" enctype="multipart/form-data" class="card shadow-sm rounded-4 p-4">
        <input type="hidden" name="csrf" value="<?= e(tokenFormulario()) ?>">
        <h2 class="h5">Datos de la cuenta</h2>
        <div class="row g-3 mb-4">
            <div class="col-sm-6"><label class="form-label" for="nombre">Nombre</label><input required class="form-control" id="nombre" name="nombre" maxlength="100" autocomplete="given-name" value="<?= e($datos['nombre']) ?>"></div>
            <div class="col-sm-6"><label class="form-label" for="apellido">Apellido</label><input required class="form-control" id="apellido" name="apellido" maxlength="100" autocomplete="family-name" value="<?= e($datos['apellido']) ?>"></div>
            <div class="col-sm-7"><label class="form-label" for="correo">Correo electrónico</label><input required type="email" class="form-control" id="correo" name="correo" maxlength="191" autocomplete="email" value="<?= e($datos['correo']) ?>"></div>
            <div class="col-sm-5"><label class="form-label" for="telefono">Teléfono</label><input type="tel" class="form-control" id="telefono" name="telefono" maxlength="25" autocomplete="tel" value="<?= e($datos['telefono']) ?>"></div>
        </div>
        <h2 class="h5 pt-3 border-top">Presentación</h2>
        <p class="text-muted small">Tu foto y presentación pueden aparecer cuando alguien consulte tu perfil de anfitrión o una reserva compartida contigo.</p>
        <label class="form-label" for="biografia">Sobre mí</label>
        <textarea class="form-control mb-3" id="biografia" name="biografia" maxlength="500" rows="3"><?= e($datos['biografia']) ?></textarea>
        <label class="form-label" for="foto_perfil">Foto de perfil</label>
        <input type="file" class="form-control mb-2" id="foto_perfil" name="foto_perfil" accept="image/jpeg,image/png,image/webp">
        <?php if ($usuario['foto_perfil']): ?><label class="form-check-label mb-2"><input type="checkbox" class="form-check-input" name="quitar_foto"> Quitar la foto actual</label><?php endif; ?>

        <h2 class="h5 pt-3 mt-3 border-top">Contraseña</h2>
        <p class="text-muted small">Usa tu contraseña actual para cambiar el correo o la contraseña.</p>
        <label class="form-label" for="clave_actual">Contraseña actual</label>
        <input type="password" class="form-control mb-3" id="clave_actual" name="clave_actual" autocomplete="current-password">
        <div class="row g-3 mb-4">
            <div class="col-sm-6"><label class="form-label" for="clave_nueva">Contraseña nueva</label><input type="password" class="form-control" id="clave_nueva" name="clave_nueva" autocomplete="new-password" minlength="8"></div>
            <div class="col-sm-6"><label class="form-label" for="clave_confirmar">Confirmar contraseña nueva</label><input type="password" class="form-control" id="clave_confirmar" name="clave_confirmar" autocomplete="new-password"></div>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <button class="btn btn-dark">Guardar cambios</button>
            <a href="perfil.php" class="btn btn-outline-secondary">Volver al perfil</a>
        </div>
    </form>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>

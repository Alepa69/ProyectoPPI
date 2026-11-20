<?php
require_once __DIR__ . '/config/conexion.php';
require_once __DIR__ . '/config/sesion.php';

$idUsuario = (int) $_SESSION['id_usuario'];
$mensaje = '';

// Obtener datos actuales
$stmt = $conexion->prepare("SELECT nombre, apellido, correo, password_hash FROM usuarios WHERE id_usuario = ?");
$stmt->bind_param('i', $idUsuario);
$stmt->execute();
$usuario = $stmt->get_result()->fetch_assoc();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre = trim($_POST['nombre'] ?? '');
    $apellido = trim($_POST['apellido'] ?? '');
    $correo = trim($_POST['correo'] ?? '');
    $claveActual = $_POST['clave_actual'] ?? '';
    $claveNueva = $_POST['clave_nueva'] ?? '';
    $claveConfirmar = $_POST['clave_confirmar'] ?? '';

    // Validaciones básicas
    if (empty($nombre) || empty($apellido) || empty($correo)) {
        $mensaje = "<div class='alert alert-warning'>Nombre, apellido y correo son obligatorios.</div>";
    } else {
        // Si intenta cambiar la contraseña
        $actualizarClave = false;
        $nuevoHash = $usuario['password_hash'];

        if (!empty($claveNueva)) {
            if (!password_verify($claveActual, $usuario['password_hash'])) {
                $mensaje = "<div class='alert alert-danger'>La contraseña actual es incorrecta.</div>";
            } elseif ($claveNueva !== $claveConfirmar) {
                $mensaje = "<div class='alert alert-warning'>Las nuevas contraseñas no coinciden.</div>";
            } elseif (strlen($claveNueva) < 8 || !preg_match('/[A-Z]/', $claveNueva) || !preg_match('/[0-9]/', $claveNueva)) {
                $mensaje = "<div class='alert alert-warning'>La nueva contraseña debe tener al menos 8 caracteres, una mayúscula y un número.</div>";
            } else {
                $nuevoHash = password_hash($claveNueva, PASSWORD_DEFAULT);
                $actualizarClave = true;
            }
        }

        // Si no hay errores, actualizar
        if (empty($mensaje)) {
            try {
                $sql = "UPDATE usuarios SET nombre = ?, apellido = ?, correo = ?";
                $params = [$nombre, $apellido, $correo];
                $types = "sss";

                if ($actualizarClave) {
                    $sql .= ", password_hash = ?";
                    $params[] = $nuevoHash;
                    $types .= "s";
                }

                $sql .= " WHERE id_usuario = ?";
                $params[] = $idUsuario;
                $types .= "i";

                $stmt = $conexion->prepare($sql);
                $stmt->bind_param($types, ...$params);
                $stmt->execute();

                // Actualizar sesión si cambió el nombre
                $_SESSION['nombre'] = $nombre;
                $_SESSION['apellido'] = $apellido;

                $mensaje = "<div class='alert alert-success'>Datos actualizados correctamente.</div>";
                // Recargar datos
                $usuario['nombre'] = $nombre;
                $usuario['apellido'] = $apellido;
                $usuario['correo'] = $correo;
            } catch (mysqli_sql_exception $e) {
                if ($e->getCode() === 1062) {
                    $mensaje = "<div class='alert alert-danger'>El correo ya está en uso por otra cuenta.</div>";
                } else {
                    $mensaje = "<div class='alert alert-danger'>Error al actualizar los datos.</div>";
                }
            }
        }
    }
}

$pageTitle = 'Editar Perfil';
include __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-md-8">
        <div class="card shadow-sm border-0">
            <div class="card-header bg-white py-3">
                <h4 class="mb-0">Editar mis datos</h4>
            </div>
            <div class="card-body">
                <?= $mensaje ?>
                <form method="POST" action="editar_perfil.php">
                    <h6 class="text-muted mb-3">Información Personal</h6>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label">Nombre</label>
                            <input type="text" name="nombre" class="form-control" value="<?= htmlspecialchars($usuario['nombre']) ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Apellido</label>
                            <input type="text" name="apellido" class="form-control" value="<?= htmlspecialchars($usuario['apellido']) ?>" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Correo Electrónico</label>
                            <input type="email" name="correo" class="form-control" value="<?= htmlspecialchars($usuario['correo']) ?>" required>
                        </div>
                    </div>

                    <h6 class="text-muted mb-3">Cambiar Contraseña (Opcional)</h6>
                    <div class="row g-3 mb-4">
                        <div class="col-12">
                            <label class="form-label">Contraseña Actual</label>
                            <input type="password" name="clave_actual" class="form-control" placeholder="Ingresa tu contraseña actual para cambiarla">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Nueva Contraseña</label>
                            <input type="password" name="clave_nueva" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Confirmar Nueva Contraseña</label>
                            <input type="password" name="clave_confirmar" class="form-control">
                        </div>
                    </div>

                    <div class="d-flex justify-content-between">
                        <a href="perfil.php" class="btn btn-outline-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-dark">Guardar Cambios</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
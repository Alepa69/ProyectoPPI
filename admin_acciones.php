<?php
require_once __DIR__ . '/config/admin_guard.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: admin.php');
    exit;
}

$token = (string) ($_POST['csrf'] ?? '');
if (!hash_equals($_SESSION['csrf_admin'], $token)) {
    $_SESSION['admin_flash'] = ['tipo' => 'danger', 'mensaje' => 'Sesión de formulario inválida. Intenta nuevamente.'];
    header('Location: admin.php');
    exit;
}

$tipo = (string) ($_POST['tipo'] ?? '');
$accion = (string) ($_POST['accion'] ?? '');
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$motivo = trim((string) ($_POST['motivo'] ?? ''));
$volverA = (string) ($_POST['volver_a'] ?? 'admin.php');

// Evita redirecciones externas maliciosas
if (!preg_match('/^(admin\.php(\?tab=(usuarios|alojamientos))?|listing\.php\?id=[1-9]\d*)$/', $volverA)) {
    $volverA = 'admin.php';
}

if (!$id || !in_array($tipo, ['usuario', 'alojamiento'], true) || !in_array($accion, ['suspender', 'activar', 'eliminar'], true)) {
    $_SESSION['admin_flash'] = ['tipo' => 'danger', 'mensaje' => 'Parámetros o acción no válidos.'];
    header('Location: ' . $volverA);
    exit;
}

try {
    if ($tipo === 'usuario') {
        if ($id === $idAdmin) {
            throw new RuntimeException('No puedes suspender ni eliminar tu propia cuenta de administrador.');
        }

        // Verificar que el usuario exista
        $stmtExiste = $conexion->prepare("SELECT id_usuario FROM usuarios WHERE id_usuario = ? LIMIT 1");
        $stmtExiste->bind_param('i', $id);
        $stmtExiste->execute();
        $existeUsuario = $stmtExiste->get_result()->fetch_assoc();
        $stmtExiste->close();

        if (!$existeUsuario) {
            throw new RuntimeException('El usuario seleccionado no existe.');
        }

        if ($accion === 'suspender') {
            $stmt = $conexion->prepare("UPDATE usuarios SET estado = 'inactivo' WHERE id_usuario = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            $_SESSION['admin_flash'] = ['tipo' => 'warning', 'mensaje' => 'Usuario suspendido (inactivo).'];
        } elseif ($accion === 'activar') {
            $stmt = $conexion->prepare("UPDATE usuarios SET estado = 'activo' WHERE id_usuario = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            $_SESSION['admin_flash'] = ['tipo' => 'success', 'mensaje' => 'Usuario activado correctamente.'];
        } elseif ($accion === 'eliminar') {
            $conexion->begin_transaction();

            // 1. Limpiar dependencias de los alojamientos y reservas del usuario
            $consultasHijas = [
                "DELETE FROM reservas WHERE id_alojamiento IN (SELECT id_alojamiento FROM alojamientos WHERE id_anfitrion = ?)",
                "DELETE FROM fotos_alojamiento WHERE id_alojamiento IN (SELECT id_alojamiento FROM alojamientos WHERE id_anfitrion = ?)",
                "DELETE FROM alojamiento_servicio WHERE id_alojamiento IN (SELECT id_alojamiento FROM alojamientos WHERE id_anfitrion = ?)",
                "DELETE FROM alojamiento_metodo_pago WHERE id_alojamiento IN (SELECT id_alojamiento FROM alojamientos WHERE id_anfitrion = ?)",
                "DELETE FROM alojamientos WHERE id_anfitrion = ?",
                "DELETE FROM reservas WHERE id_cancelado_por = ?",
                "DELETE FROM reservas WHERE id_solicitante = ?",
                "DELETE FROM usuarios WHERE id_usuario = ?",
            ];

            foreach ($consultasHijas as $sql) {
                $stmt = $conexion->prepare($sql);
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $stmt->close();
            }

            $conexion->commit();
            $_SESSION['admin_flash'] = ['tipo' => 'success', 'mensaje' => 'Usuario eliminado permanentemente.'];
        }
    } elseif ($tipo === 'alojamiento') {
        // Verificar que el alojamiento exista y conocer el estado del anfitrión
        $stmtAloj = $conexion->prepare("
            SELECT a.id_alojamiento, u.estado AS estado_anfitrion
            FROM alojamientos a
            JOIN usuarios u ON u.id_usuario = a.id_anfitrion
            WHERE a.id_alojamiento = ?
            LIMIT 1
        ");
        $stmtAloj->bind_param('i', $id);
        $stmtAloj->execute();
        $alojInfo = $stmtAloj->get_result()->fetch_assoc();
        $stmtAloj->close();

        if (!$alojInfo) {
            throw new RuntimeException('La publicación seleccionada no existe.');
        }

        if ($accion === 'suspender') {
            $motivoFinal = $motivo !== '' ? mb_substr($motivo, 0, 500) : 'Suspendido por un administrador';
            $stmt = $conexion->prepare("UPDATE alojamientos SET estado = 'bloqueado', motivo_bloqueo = ? WHERE id_alojamiento = ?");
            $stmt->bind_param('si', $motivoFinal, $id);
            $stmt->execute();
            $stmt->close();
            $_SESSION['admin_flash'] = ['tipo' => 'warning', 'mensaje' => 'Publicación bloqueada y ocultada del catálogo.'];
        } elseif ($accion === 'activar') {
            if ($alojInfo['estado_anfitrion'] !== 'activo') {
                throw new RuntimeException('No puedes activar esta publicación porque la cuenta del anfitrión está suspendida.');
            }
            $stmt = $conexion->prepare("UPDATE alojamientos SET estado = 'publicado', motivo_bloqueo = NULL WHERE id_alojamiento = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            $_SESSION['admin_flash'] = ['tipo' => 'success', 'mensaje' => 'Publicación activada nuevamente.'];
        } elseif ($accion === 'eliminar') {
            $conexion->begin_transaction();

            $consultasCasa = [
                "DELETE FROM reservas WHERE id_alojamiento = ?",
                "DELETE FROM fotos_alojamiento WHERE id_alojamiento = ?",
                "DELETE FROM alojamiento_servicio WHERE id_alojamiento = ?",
                "DELETE FROM alojamiento_metodo_pago WHERE id_alojamiento = ?",
                "DELETE FROM alojamientos WHERE id_alojamiento = ?",
            ];

            foreach ($consultasCasa as $sql) {
                $stmt = $conexion->prepare($sql);
                $stmt->bind_param('i', $id);
                $stmt->execute();
                $stmt->close();
            }

            $conexion->commit();
            $_SESSION['admin_flash'] = ['tipo' => 'success', 'mensaje' => 'Publicación eliminada permanentemente.'];
            $volverA = 'admin.php?tab=alojamientos';
        }
    }
} catch (Throwable $e) {
    if ($conexion->errno) {
        $conexion->rollback();
    }
    $_SESSION['admin_flash'] = ['tipo' => 'danger', 'mensaje' => 'No se pudo completar la acción: ' . $e->getMessage()];
}

header('Location: ' . $volverA, true, 303);
exit;
<?php
require_once __DIR__ . '/config/admin_guard.php';

$buscar = trim((string) ($_GET['buscar'] ?? ''));
$tab = ($_GET['tab'] ?? 'usuarios') === 'alojamientos' ? 'alojamientos' : 'usuarios';
$estadoUsuario = (string) ($_GET['estado_usuario'] ?? '');
$estadoCasa = (string) ($_GET['estado_casa'] ?? '');

$like = '%' . $buscar . '%';

// 1. Consulta de usuarios
$sqlUsuarios = "
    SELECT u.id_usuario, u.nombre, u.apellido, u.correo, u.rol, u.estado, u.creado_en,
           COUNT(DISTINCT a.id_alojamiento) AS total_alojamientos
    FROM usuarios u
    LEFT JOIN alojamientos a ON a.id_anfitrion = u.id_usuario
    WHERE (CONCAT(u.nombre, ' ', u.apellido) LIKE ? OR u.correo LIKE ?)
";
$tiposU = 'ss';
$paramsU = [$like, $like];

if (in_array($estadoUsuario, ['activo', 'inactivo'], true)) {
    $sqlUsuarios .= " AND u.estado = ?";
    $tiposU .= 's';
    $paramsU[] = $estadoUsuario;
}
$sqlUsuarios .= " GROUP BY u.id_usuario ORDER BY u.id_usuario DESC";

$stmtU = $conexion->prepare($sqlUsuarios);
$stmtU->bind_param($tiposU, ...$paramsU);
$stmtU->execute();
$usuarios = $stmtU->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtU->close();

// 2. Consulta de publicaciones
$sqlCasas = "
    SELECT a.id_alojamiento, a.titulo, a.ciudad, a.municipio, a.departamento,
           a.precio_persona_noche, a.estado, a.motivo_bloqueo, a.creado_en,
           u.nombre AS nombre_anfitrion, u.apellido AS apellido_anfitrion, u.correo AS correo_anfitrion
    FROM alojamientos a
    INNER JOIN usuarios u ON u.id_usuario = a.id_anfitrion
    WHERE (a.titulo LIKE ? OR a.ciudad LIKE ? OR a.departamento LIKE ? OR CONCAT(u.nombre, ' ', u.apellido) LIKE ?)
";
$tiposA = 'ssss';
$paramsA = [$like, $like, $like, $like];

if (in_array($estadoCasa, ['publicado', 'borrador', 'inactivo', 'bloqueado'], true)) {
    $sqlCasas .= " AND a.estado = ?";
    $tiposA .= 's';
    $paramsA[] = $estadoCasa;
}
$sqlCasas .= " ORDER BY a.id_alojamiento DESC";

$stmtA = $conexion->prepare($sqlCasas);
$stmtA->bind_param($tiposA, ...$paramsA);
$stmtA->execute();
$alojamientos = $stmtA->get_result()->fetch_all(MYSQLI_ASSOC);
$stmtA->close();

$flash = $_SESSION['admin_flash'] ?? null;
unset($_SESSION['admin_flash']);

$pageTitle = 'Panel de Administración';
include __DIR__ . '/includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-4">
    <div>
        <h2 class="h4 mb-0">Panel de Administración</h2>
        <small class="text-muted">Gestión de usuarios y publicaciones</small>
    </div>
    <div class="d-flex gap-2">
        <a href="perfil.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3">Mi perfil</a>
        <a href="index.php" class="btn btn-outline-success btn-sm rounded-pill px-3">Volver al inicio</a>
    </div>
</div>

<?php if ($flash): ?>
    <div class="alert alert-<?= htmlspecialchars($flash['tipo']) ?> alert-dismissible fade show" role="alert">
        <?= htmlspecialchars($flash['mensaje']) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
    </div>
<?php endif; ?>

<!-- Barra de búsqueda y filtros -->
<form method="get" class="card shadow-sm border-0 mb-4">
    <div class="card-body">
        <input type="hidden" name="tab" value="<?= htmlspecialchars($tab) ?>">
        <div class="row g-2 align-items-center">
            <div class="col-md-5">
                <input type="text" name="buscar" class="form-control"
                       placeholder="Buscar por nombre, correo, título o ubicación..."
                       value="<?= htmlspecialchars($buscar) ?>">
            </div>
            <div class="col-md-3">
                <select name="estado_usuario" class="form-select">
                    <option value="">Estado usuario (todos)</option>
                    <option value="activo" <?= $estadoUsuario === 'activo' ? 'selected' : '' ?>>Activo</option>
                    <option value="inactivo" <?= $estadoUsuario === 'inactivo' ? 'selected' : '' ?>>Inactivo (suspendido)</option>
                </select>
            </div>
            <div class="col-md-2">
                <select name="estado_casa" class="form-select">
                    <option value="">Estado casa (todos)</option>
                    <option value="publicado" <?= $estadoCasa === 'publicado' ? 'selected' : '' ?>>Publicado</option>
                    <option value="bloqueado" <?= $estadoCasa === 'bloqueado' ? 'selected' : '' ?>>Bloqueado</option>
                    <option value="inactivo" <?= $estadoCasa === 'inactivo' ? 'selected' : '' ?>>Inactivo</option>
                    <option value="borrador" <?= $estadoCasa === 'borrador' ? 'selected' : '' ?>>Borrador</option>
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button class="btn btn-dark w-100" type="submit">Filtrar</button>
                <?php if ($buscar !== '' || $estadoUsuario !== '' || $estadoCasa !== ''): ?>
                    <a href="admin.php?tab=<?= htmlspecialchars($tab) ?>" class="btn btn-outline-secondary">Limpiar</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</form>

<!-- Pestañas -->
<ul class="nav nav-tabs mb-3" role="tablist">
    <li class="nav-item">
        <button class="nav-link <?= $tab === 'usuarios' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#tab-usuarios" type="button">
            Usuarios (<?= count($usuarios) ?>)
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link <?= $tab === 'alojamientos' ? 'active' : '' ?>" data-bs-toggle="tab" data-bs-target="#tab-alojamientos" type="button">
            Publicaciones (<?= count($alojamientos) ?>)
        </button>
    </li>
</ul>

<div class="tab-content">
    <!-- TAB USUARIOS -->
    <div class="tab-pane fade <?= $tab === 'usuarios' ? 'show active' : '' ?>" id="tab-usuarios">
        <div class="card shadow-sm border-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Usuario</th>
                            <th>Rol</th>
                            <th>Casas</th>
                            <th>Estado</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$usuarios): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">No se encontraron usuarios.</td></tr>
                        <?php endif; ?>

                        <?php foreach ($usuarios as $u): ?>
                            <?php $esMismoAdmin = ((int) $u['id_usuario'] === $idAdmin); ?>
                            <tr>
                                <td>#<?= (int) $u['id_usuario'] ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($u['nombre'] . ' ' . $u['apellido']) ?></strong><br>
                                    <small class="text-muted"><?= htmlspecialchars($u['correo']) ?></small>
                                </td>
                                <td>
                                    <span class="badge <?= $u['rol'] === 'administrador' ? 'bg-dark' : 'bg-secondary' ?>">
                                        <?= ucfirst($u['rol']) ?>
                                    </span>
                                </td>
                                <td><?= (int) $u['total_alojamientos'] ?></td>
                                <td>
                                    <span class="badge <?= $u['estado'] === 'activo' ? 'bg-success' : 'bg-warning text-dark' ?>">
                                        <?= ucfirst($u['estado']) ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <?php if ($esMismoAdmin): ?>
                                        <span class="text-muted small">Tu cuenta</span>
                                    <?php else: ?>
                                        <form method="post" action="admin_acciones.php" class="d-inline">
                                            <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf_admin']) ?>">
                                            <input type="hidden" name="tipo" value="usuario">
                                            <input type="hidden" name="id" value="<?= (int) $u['id_usuario'] ?>">
                                            <input type="hidden" name="volver_a" value="admin.php?tab=usuarios">
                                            <?php if ($u['estado'] === 'activo'): ?>
                                                <button type="submit" name="accion" value="suspender" class="btn btn-warning btn-sm">Suspender</button>
                                            <?php else: ?>
                                                <button type="submit" name="accion" value="activar" class="btn btn-success btn-sm">Activar</button>
                                            <?php endif; ?>
                                            <button type="submit" name="accion" value="eliminar" class="btn btn-outline-danger btn-sm"
                                                    onclick="return confirm('¿Eliminar permanentemente a este usuario junto con sus publicaciones y reservas? Esta acción no se puede deshacer.')">
                                                Eliminar
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- TAB PUBLICACIONES -->
    <div class="tab-pane fade <?= $tab === 'alojamientos' ? 'show active' : '' ?>" id="tab-alojamientos">
        <div class="card shadow-sm border-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Publicación</th>
                            <th>Anfitrión</th>
                            <th>Precio / noche</th>
                            <th>Estado</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$alojamientos): ?>
                            <tr><td colspan="6" class="text-center text-muted py-4">No se encontraron publicaciones.</td></tr>
                        <?php endif; ?>

                        <?php foreach ($alojamientos as $a): ?>
                            <?php
                            $badgeEstado = match ($a['estado']) {
                                'publicado' => 'bg-success',
                                'bloqueado' => 'bg-danger',
                                'inactivo'  => 'bg-warning text-dark',
                                default     => 'bg-secondary',
                            };
                            ?>
                            <tr>
                                <td>#<?= (int) $a['id_alojamiento'] ?></td>
                                <td>
                                    <a href="listing.php?id=<?= (int) $a['id_alojamiento'] ?>" class="fw-semibold text-decoration-none">
                                        <?= htmlspecialchars($a['titulo']) ?>
                                    </a><br>
                                    <small class="text-muted">
                                        <?= htmlspecialchars(($a['ciudad'] ?: $a['municipio']) . ', ' . $a['departamento']) ?>
                                    </small>
                                    <?php if (!empty($a['motivo_bloqueo'])): ?>
                                        <div class="small text-danger mt-1">Motivo: <?= htmlspecialchars($a['motivo_bloqueo']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars($a['nombre_anfitrion'] . ' ' . $a['apellido_anfitrion']) ?><br>
                                    <small class="text-muted"><?= htmlspecialchars($a['correo_anfitrion']) ?></small>
                                </td>
                                <td>$<?= number_format((float) $a['precio_persona_noche'], 2) ?></td>
                                <td>
                                    <span class="badge <?= $badgeEstado ?>"><?= ucfirst($a['estado']) ?></span>
                                </td>
                                <td class="text-end">
                                    <form method="post" action="admin_acciones.php" class="d-inline">
                                        <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['csrf_admin']) ?>">
                                        <input type="hidden" name="tipo" value="alojamiento">
                                        <input type="hidden" name="id" value="<?= (int) $a['id_alojamiento'] ?>">
                                        <input type="hidden" name="volver_a" value="admin.php?tab=alojamientos">

                                        <?php if ($a['estado'] === 'publicado'): ?>
                                            <button type="button" class="btn btn-warning btn-sm"
                                                    onclick="bloquearPublicacion(this.form)">
                                                Suspender
                                            </button>
                                        <?php else: ?>
                                            <button type="submit" name="accion" value="activar" class="btn btn-success btn-sm">
                                                Activar
                                            </button>
                                        <?php endif; ?>

                                        <button type="submit" name="accion" value="eliminar" class="btn btn-outline-danger btn-sm"
                                                onclick="return confirm('¿Eliminar permanentemente esta publicación y sus reservas asociadas?')">
                                            Eliminar
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function bloquearPublicacion(form) {
    const motivo = prompt('Indica el motivo de la suspensión/bloqueo (opcional):', 'Incumplimiento de normas de la plataforma');
    if (motivo === null) return;

    const inputAccion = document.createElement('input');
    inputAccion.type = 'hidden';
    inputAccion.name = 'accion';
    inputAccion.value = 'suspender';

    const inputMotivo = document.createElement('input');
    inputMotivo.type = 'hidden';
    inputMotivo.name = 'motivo';
    inputMotivo.value = motivo.trim();

    form.appendChild(inputAccion);
    form.appendChild(inputMotivo);
    form.submit();
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
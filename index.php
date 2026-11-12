<?php
require_once __DIR__. '/config/conexion.php';

//sql para mostrar las casas
//si hay filtro pos se aplica

$casa_titulo=isset($_GET['casa_titulo']) ? trim($_GET['casa_titulo']):'';
$precio_minimo=isset($_GET['precio_minimo']) ? trim($_GET['precio_minimo']) : ''; 
$precio_maximo=isset($_GET['precio_maximo']) ? trim($_GET['precio_maximo']) : '';

//
$orden=isset($_GET['orden']) ? $_GET['orden']: '';

$params=[];
$types='';

$sql="select 
a.id_alojamiento, 
a.titulo, a.municipio, 
a.departamento, 
a.precio_persona_noche, 

a.latitud,a.longitud,

u.nombre AS anfitrion_nombre,
COALESCE(f.ruta_archivo, 'https://picsum.photos/seed/casa/400/220') AS foto_principal

from alojamientos a 
join usuarios u on a.id_anfitrion = u.id_usuario
left join fotos_alojamiento f on a.id_alojamiento = f.id_alojamiento and f.posicion=1

where a.estado = 'publicado'";
//titulo
if (!empty($casa_titulo)) {
    $sql .= " and a.titulo like ?";
    $params[]= "%". $casa_titulo . "%";
    $types .= "s";
}
//min
if ($precio_minimo !== '') {
    $sql .= " and a.precio_persona_noche >= ?";
    $params[] = $precio_minimo;
    $types .= "d";
}
//max
if ($precio_maximo !== '') {
    $sql .= " and a.precio_persona_noche <= ?";
    $params[] = $precio_maximo;
    $types .= "d";
}
//orden
if ($orden === 'precio_asc') {
    $sql .= " order by a.precio_persona_noche ASC";
} elseif ($orden === 'precio_desc') {
    $sql .= " order by a.precio_persona_noche DESC";
} else {
    $sql .= " order by a.creado_en DESC";
}
$stmt = $conexion->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$resultado = $stmt->get_result();
$casas = $resultado->fetch_all(MYSQLI_ASSOC);
$pageTitle = 'Menú principal';
?>

<?php include __DIR__ . '/includes/header.php'; ?>
    

    <form action="" method="GET" class="card shadow-sm border-0 rounded-4 p-3 mb-4">
    <div class="row g-3 align-items-end">
        <div class="col-12 col-md-4">
            <label for="casa_titulo" class="form-label small fw-semibold">Buscar por nombre</label>
            <input type="text" id="casa_titulo" name="casa_titulo" class="form-control rounded-pill"
                   placeholder="Zona rosa" value="<?= htmlspecialchars($casa_titulo)?>">
        </div>
        <div class="col-6 col-md-2">
            <label for="precio_minimo" class="form-label small fw-semibold">Precio min</label>
            <input type="number" id="precio_minimo" name="precio_minimo" class="form-control rounded-pill"
                   placeholder="0" min="0" value="<?= htmlspecialchars($precio_minimo)?>">
        </div>
        <div class="col-6 col-md-2">
            <label for="precio_maximo" class="form-label small fw-semibold">Precio max</label>
            <input type="number" id="precio_maximo" name="precio_maximo" class="form-control rounded-pill"
                   placeholder="500" min="0" value="<?= htmlspecialchars($precio_maximo)?>">
        </div>
        <div class="col-8 col-md-2">
            <label for="orden" class="form-label small fw-semibold">Ordenar por</label>
            <select name="orden" id="orden" class="form-select rounded-pill">
                <option value="">Mas recientes</option>
                <option value="precio_asc"<?= $orden==='precio_asc'?'selected' :'' ?>>Menor a mayor</option>
                <option value="precio_desc"<?= $orden==='precio_desc'?'selected':'' ?>>Mayor a menor</option>
            </select>
        </div>
        <div class="col-4 col-md-2">
            <button class="btn btn-brand w-100">Buscar</button>
        </div>
    </div>
</form>



    <div>
      <h2>Casas disponibles</h2>
      <aside>
        <!--valida si hay casas-->
        <?php if (count($casas)===0): ?>
        <p class="text-muted">No se han encontrado casas disponibles</p>
        <?php endif; ?>

        <!--daniel cambie while por foreach-->
        <?php foreach ($casas as $p):?>

          <a href="listing.php?id=<?= (int)$p['id_alojamiento'] ?>" class="card text-decoration-none text-dark border shadow-sm mb-3 overflow-hidden">
                <img src="<?=htmlspecialchars($p['foto_principal'])?>" class="card-img-top object-fit-cover" style="height: 180px;" alt="Foto de <?= htmlspecialchars($p['titulo']) ?>">
                <div class="card-body">
                    <h3 class="h6 fw-bold mb-1 text-dark"><?= htmlspecialchars($p['titulo']) ?></h3>
                    <p class="text-muted small mb-2">nombe:<?= htmlspecialchars($p['municipio']) ?>, <?= htmlspecialchars($p['departamento']) ?></p>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="fw-bold text-primary">$<?= number_format((float)$p['precio_persona_noche'], 2) ?> <small class="text-muted fw-normal">la noche</small></span>
                    </div>
                    <span class="text-muted small d-block mt-2">Anfitrion: <?= htmlspecialchars($p['anfitrion_nombre']) ?></span>
                </div>
            </a>

        <?php endforeach;?>
      </aside>
<!--mapa no-->
<div id="map" style="height: 400px;"></div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
    const casas=<?=json_encode($casas)?>;

    const map = L.map('map').setView([13.69, -89.23],12);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);

    casas.forEach(c => {
        L.marker([c.latitud, c.longitud])
            .addTo(map)
            .bindPopup(c.titulo + '<br>$' + c.precio_persona_noche + ' / noche');
    });
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
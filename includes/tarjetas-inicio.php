<?php foreach ($casas as $p): ?>
    <div class="card border shadow-sm mb-3 overflow-hidden">
        <img src="<?= htmlspecialchars($p['foto_principal']) ?>" class="card-img-top object-fit-cover" style="height: 180px;" loading="lazy" alt="Foto de <?= htmlspecialchars($p['titulo']) ?>">
        <div class="card-body">
            <h3 class="h6 fw-bold mb-1 text-dark"><?= htmlspecialchars($p['titulo']) ?></h3>
            <p class="text-muted small mb-2"><?= htmlspecialchars($p['municipio']) ?>, <?= htmlspecialchars($p['departamento']) ?> · Hasta <?= (int) $p['capacidad'] ?> huéspedes</p>
            <div class="d-flex justify-content-between align-items-center">
                <span class="fw-bold text-primary">$<?= number_format((float) $p['precio_persona_noche'], 2) ?> <small class="text-muted fw-normal">/ persona · noche</small></span>
            </div>
            <span class="text-muted small d-block mt-2 mb-3">Anfitrión: <?= htmlspecialchars($p['anfitrion_nombre']) ?></span>
            <a href="listing.php?id=<?= (int) $p['id_alojamiento'] ?>" class="btn btn-outline-success btn-sm">Ver detalles</a>
        </div>
    </div>
<?php endforeach; ?>

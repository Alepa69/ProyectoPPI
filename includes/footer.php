<?php
$baseFooter = $base ?? (basename(dirname($_SERVER['SCRIPT_NAME'] ?? '')) === 'forms' ? '../' : '');
?>
<footer class="border-top mt-5 py-4 text-muted small">
  <div class="container d-flex flex-column flex-md-row justify-content-between align-items-center gap-2">
    <div>
      <span>© 2026 <strong>ProyectoPPI</strong> · Proyecto Universitario (Ciclo IV)</span>
      <span class="mx-2">·</span>
      <a href="<?= $baseFooter ?>index.php" class="text-decoration-none text-muted">Inicio</a>
    </div>

    <div class="d-flex align-items-center gap-3 fw-semibold text-dark">
      <span>🌐 Español (SV)</span>
    </div>
  </div>
</footer>

<script src="<?= $baseFooter ?>assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>

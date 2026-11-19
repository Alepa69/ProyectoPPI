<nav class="navbar navbar-expand-xl navbar-davinci"
     aria-label="Menú principal">

    <div class="container-fluid px-4">

        <!-- Marca e icono -->
        <a class="navbar-brand marca-davinci" href="index.php">
            <img src="assets/img/logo-davinci.png"
                 alt=""
                 width="36"
                 height="36">

            <span>HALOJA</span>
        </a>

        <!-- Botón para abrir el menú en celular -->
        <button class="navbar-toggler"
                type="button"
                data-bs-toggle="collapse"
                data-bs-target="#menuPrincipal"
                aria-controls="menuPrincipal"
                aria-expanded="false"
                aria-label="Abrir menú">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse" id="menuPrincipal">

            <!-- Enlaces del centro -->
            <ul class="navbar-nav enlaces-davinci gap-xl-4">
                <li class="nav-item">
                    <a class="nav-link" href="index.php">
                        Inicio
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link" href="index.php#alojamientos">
                        Explorar alojamientos
                    </a>
                </li>

                <li class="nav-item">
                    <a class="nav-link"
                       href="modulos/reservas/mis_solicitudes.php">
                        Mis reservas
                    </a>
                </li>

                <li class="nav-item">
                     <a class="nav-link" href="panel_anfitrion.php">
                        Panel del anfitrión
                    </a>
                </li>
            </ul>

            <!-- Botones de la derecha -->
            <div class="acciones-davinci">
                <a class="btn boton-anfitrion"
                   href="publicar_casa.php">
                    Publicar casa
                </a>

                <a class="btn boton-ingresar"
                   href="modulos/acceso/login.php">

                    <svg width="19" height="19"
                         viewBox="0 0 24 24"
                         fill="none"
                         stroke="currentColor"
                         stroke-width="1.8"
                         aria-hidden="true">
                        <circle cx="12" cy="8" r="4"></circle>
                        <path d="M4 21v-2a8 8 0 0 1 16 0v2z"></path>
                    </svg>

                    Ingresar
                </a>
            </div>

        </div>
    </div>
</nav>
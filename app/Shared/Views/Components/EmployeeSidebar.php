<?php
/*
 * Sidebar única del panel del empleado.
 *
 * Es un Offcanvas responsive de Bootstrap (offcanvas-lg): en escritorio
 * (>= lg) se muestra como columna estática a la izquierda; en tablet y
 * móvil (< lg) desaparece de la pantalla y se abre desde la izquierda
 * mediante el botón hamburguesa del Topbar. No existe una versión móvil:
 * este mismo elemento es la única fuente del menú en ambos entornos.
 *
 * Contiene únicamente la navegación del empleado. El usuario y el
 * logout aparecen en el Topbar (compartido con AdminLayout).
 */
$pageActive = $pageActive ?? '';
?>
<nav class="col-lg-2 sidebar employee-sidebar offcanvas-start offcanvas-lg p-0"
     id="sidebarMenu" tabindex="-1" aria-labelledby="sidebarMenuLabel">
    <div class="offcanvas-header justify-content-end">
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" data-bs-target="#sidebarMenu" aria-label="Cerrar menú"></button>
    </div>
    <div class="offcanvas-body">
        <div class="position-sticky pt-3 w-100">
            <div class="text-center py-4">
                <i class="bi bi-person-badge fs-1 text-primary"></i>
                <h5 class="text-white mt-2 mb-0" id="sidebarMenuLabel">Panel Empleado</h5>
            </div>

            <hr class="text-secondary mx-3">

            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link <?= $pageActive === 'employee-dashboard' ? 'active' : '' ?>" href="/employee">
                        <i class="bi bi-speedometer2"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $pageActive === 'profile' ? 'active' : '' ?>" href="/profile">
                        <i class="bi bi-person-circle"></i> Perfil
                    </a>
                </li>
            </ul>

            <hr class="text-secondary mx-3">

            <ul class="nav flex-column mb-2">
                <li class="nav-item">
                    <a class="nav-link" href="/employee">
                        <i class="bi bi-house"></i> Volver al sitio
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

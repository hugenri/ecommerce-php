<?php
/*
 * Sidebar única del panel de administración.
 *
 * Es un Offcanvas responsive de Bootstrap (offcanvas-lg): en escritorio
 * (>= lg) se muestra como columna estática a la izquierda; en tablet y
 * móvil (< lg) desaparece de la pantalla y se abre desde la izquierda
 * mediante el botón hamburguesa del Topbar. No existe una versión móvil:
 * este mismo elemento es la única fuente del menú en ambos entornos.
 *
 * Contiene únicamente navegación: el menú dinámico (AdminMenu) y
 * "Volver al sitio". El usuario y el logout viven en el Topbar.
 */
use App\Shared\Support\AdminMenu;

$pageActive = $pageActive ?? '';
?>
<nav class="col-lg-2 sidebar offcanvas-start offcanvas-lg p-0"
     id="sidebarMenu" tabindex="-1" aria-labelledby="sidebarMenuLabel">
    <div class="offcanvas-header justify-content-end">
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" data-bs-target="#sidebarMenu" aria-label="Cerrar menú"></button>
    </div>
    <div class="offcanvas-body">
        <div class="position-sticky pt-3 w-100">
            <div class="text-center py-4">
                <i class="bi bi-shield-lock fs-1 text-primary"></i>
                <h5 class="text-white mt-2 mb-0" id="sidebarMenuLabel">Admin Panel</h5>
            </div>

            <hr class="text-secondary mx-3">

            <ul class="nav flex-column">
                <?php foreach (AdminMenu::items() as $item): ?>
                    <?php if (!empty($item['children'])): ?>
                        <?php $parentActive = $pageActive === $item['key'] || str_starts_with($pageActive, $item['key'] . '.'); ?>
                        <li class="nav-item">
                            <a class="nav-link d-flex justify-content-between align-items-center <?= $parentActive ? 'active' : '' ?>"
                               data-bs-toggle="collapse" href="#menu-<?= $item['key'] ?>" role="button"
                               aria-expanded="<?= $parentActive ? 'true' : 'false' ?>" aria-controls="menu-<?= $item['key'] ?>">
                                <span><i class="bi bi-<?= $item['icon'] ?>"></i> <?= htmlspecialchars($item['label']) ?></span>
                                <i class="bi bi-chevron-down small"></i>
                            </a>
                            <ul class="nav flex-column collapse <?= $parentActive ? 'show' : '' ?>" id="menu-<?= $item['key'] ?>">
                                <?php foreach ($item['children'] as $child): ?>
                                    <li class="nav-item">
                                        <a class="nav-link ps-4 <?= $pageActive === $child['key'] ? 'active' : '' ?>" href="<?= htmlspecialchars($child['url']) ?>">
                                            <?= htmlspecialchars($child['label']) ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link <?= $pageActive === $item['key'] ? 'active' : '' ?>" href="<?= htmlspecialchars($item['url']) ?>">
                                <i class="bi bi-<?= $item['icon'] ?>"></i> <?= htmlspecialchars($item['label']) ?>
                            </a>
                        </li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ul>

            <hr class="text-secondary mx-3">

            <ul class="nav flex-column mb-2">
                <li class="nav-item">
                    <a class="nav-link" href="/admin">
                        <i class="bi bi-house"></i> Volver al sitio
                    </a>
                </li>
            </ul>
        </div>
    </div>
</nav>

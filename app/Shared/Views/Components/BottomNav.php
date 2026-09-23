<?php
$headerIsLoggedIn = $headerIsLoggedIn ?? false;
$headerCustomerName = $headerCustomerName ?? '';
?>
<nav class="bottom-nav d-lg-none" aria-label="Navegación principal móvil">
    <div class="bottom-nav-inner">
        <button type="button"
                class="bottom-nav-item"
                data-catalog-categories-toggle
                data-catalog-categories-anchor="bottom"
                aria-expanded="false"
                aria-controls="catalogCategoriesPanel">
            <i class="bi bi-grid" aria-hidden="true"></i>
            <span>Categorías</span>
        </button>
        <div class="bottom-nav-item bottom-nav-dropdown dropdown dropup">
            <a class="bottom-nav-link" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Cuenta">
                <i class="bi bi-person-circle" aria-hidden="true"></i>
                <span>Cuenta</span>
            </a>
            <?php
            $accountMenuClass = 'dropdown-menu-end';
            require $sharedViewsPath . '/Components/AccountMenu.php';
            ?>
        </div>
    </div>
</nav>
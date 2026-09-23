<?php
$siteSettings = $siteSettings ?? null;
$searchQuery = $searchQuery ?? '';

$hideCartIcon = in_array($pageActive ?? '', ['checkout', 'pago'], true);

$storeName = 'Tienda';
$storeLogo = '';
if ($siteSettings !== null) {
    $storeName = $siteSettings->getStoreName() !== ''
        ? $siteSettings->getStoreName()
        : 'Tienda';
    $storeLogo = $siteSettings->getLogo() ?? '';
}
?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <?php if ($storeLogo !== ''): ?>
            <a class="navbar-brand flex-shrink-0" href="/">
                <img src="<?= htmlspecialchars(\App\Shared\Support\ImageHelper::url($storeLogo)) ?>" alt="<?= htmlspecialchars($storeName) ?>" class="d-inline-block align-text-top" style="height: 32px; width: auto;">
            </a>
        <?php else: ?>
            <a class="navbar-brand fw-bold flex-shrink-0" href="/"><i class="bi bi-shop"></i> <?= htmlspecialchars($storeName) ?></a>
        <?php endif; ?>
        <ul class="navbar-nav d-flex flex-row align-items-center flex-shrink-0 gap-2 order-2 order-lg-3 ms-lg-4">
            <?php if (!$hideCartIcon): ?>
                <li class="nav-item">
                    <a class="nav-link nav-icon" href="/cart" aria-label="Carrito" title="Carrito"
                       data-cart-count="<?= (int) $headerCartCount ?>"
                       data-cart-count-authoritative="<?= $headerCartCountProvided ? '1' : '0' ?>">
                        <span class="cart-icon">
                            <i class="bi bi-cart3 fs-5" aria-hidden="true"></i>
                            <span class="badge rounded-pill bg-danger cart-badge" <?= $headerCartCount > 0 ? '' : 'hidden' ?>><?= (int) $headerCartCount ?></span>
                        </span>
                    </a>
                </li>
            <?php endif; ?>
            <li class="nav-item dropdown d-none d-lg-flex">
                <a class="nav-link nav-icon" href="#" role="button"
                   data-bs-toggle="dropdown" aria-expanded="false" aria-label="Cuenta" title="Cuenta">
                    <i class="bi bi-person-circle fs-5 lh-1" aria-hidden="true"></i>
                </a>
                <?php
                $accountMenuClass = 'dropdown-menu-end';
                require $sharedViewsPath . '/Components/AccountMenu.php';
                ?>
            </li>
        </ul>
        <form class="search-form my-2 my-lg-0 order-1 order-lg-2" action="/shop" method="GET" role="search" data-search-form>
            <div class="search-box">
                <input type="search" class="form-control search-input" name="search"
                       value="<?= htmlspecialchars($searchQuery) ?>"
                       placeholder="Buscar productos..." aria-label="Buscar productos"
                       autocomplete="off" maxlength="100" data-search-input>
                <button type="submit" class="btn search-btn" aria-label="Buscar">
                    <i class="bi bi-search"></i>
                </button>
                <div class="search-dropdown" hidden data-search-dropdown></div>
            </div>
        </form>
        <div class="collapse navbar-collapse d-none d-lg-flex order-lg-1" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <li class="nav-item">
                    <button type="button"
                            class="nav-link catalog-categories-trigger"
                            data-catalog-categories-toggle
                            aria-expanded="false"
                            aria-controls="catalogCategoriesPanel">
                        Categorías
                        <i class="bi bi-chevron-down catalog-categories-arrow" aria-hidden="true"></i>
                    </button>
                </li>
            </ul>
        </div>
    </div>
</nav>
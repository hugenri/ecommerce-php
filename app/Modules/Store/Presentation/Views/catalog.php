<?php
$pageTitle = 'Catálogo - Tienda';
$pageActive = 'catalog';
ob_start();

$currentCategoryId = ($currentCategory === '' || $currentCategory === null) ? null : (int) $currentCategory;
$currentSubcategoryId = ($currentSubcategory === '' || $currentSubcategory === null) ? null : (int) $currentSubcategory;
$currentSort = is_string($currentSort) ? $currentSort : 'newest';
$isSearch = $search !== '';
$totalProducts = (int) ($meta['total'] ?? 0);
$countWord = $totalProducts === 1 ? 'producto' : 'productos';

$activeCategory = null;
foreach ($categories as $cat) {
    if ($currentCategoryId !== null && $currentCategoryId === $cat->getCategoryId()) {
        $activeCategory = $cat;
        break;
    }
}

$activeSubcategory = null;
foreach ($subcategories as $sub) {
    if ($currentSubcategoryId !== null && $currentSubcategoryId === $sub->getSubcategoryId()) {
        $activeSubcategory = $sub;
        break;
    }
}

$contextName = '';
if ($isSearch) {
    $contextName = $search;
} elseif ($activeSubcategory !== null) {
    $contextName = $activeSubcategory->getName();
} elseif ($activeCategory !== null) {
    $contextName = $activeCategory->getName();
}

$pageUrl = static function (int $page) use ($search, $currentCategoryId, $currentSubcategoryId, $currentSort): string {
    $params = ['page' => $page];
    if ($search !== '') {
        $params['search'] = $search;
    }
    if ($currentCategoryId !== null) {
        $params['category_id'] = $currentCategoryId;
    }
    if ($currentSubcategoryId !== null) {
        $params['subcategory_id'] = $currentSubcategoryId;
    }
    $params['sort'] = $currentSort;
    return '?' . http_build_query($params);
};
?>

<div class="container py-4">
    <nav class="catalog-breadcrumb mb-3" aria-label="Ruta de navegación">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/">Inicio</a></li>
            <?php if ($isSearch): ?>
                <li class="breadcrumb-item active" aria-current="page">Resultados de búsqueda</li>
            <?php elseif ($activeCategory !== null): ?>
                <?php if ($activeSubcategory === null): ?>
                    <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($activeCategory->getName()) ?></li>
                <?php else: ?>
                    <li class="breadcrumb-item"><a href="/shop?category_id=<?= $activeCategory->getCategoryId() ?>"><?= htmlspecialchars($activeCategory->getName()) ?></a></li>
                    <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($activeSubcategory->getName()) ?></li>
                <?php endif; ?>
            <?php endif; ?>
        </ol>
    </nav>

    <div class="catalog-toolbar d-flex flex-wrap align-items-center gap-2 mb-3">
        <p class="catalog-count mb-0">
            <?php if ($contextName !== ''): ?>
                Mostrando resultados para <strong><?= htmlspecialchars($contextName) ?></strong>: <?= $totalProducts ?> <?= $countWord ?>
            <?php else: ?>
                Mostrando <?= $totalProducts ?> <?= $countWord ?>
            <?php endif; ?>
        </p>

        <form method="GET" action="/shop" class="catalog-sort-form d-flex align-items-center gap-2 ms-auto">
            <?php if ($search !== ''): ?>
                <input type="hidden" name="search" value="<?= htmlspecialchars($search) ?>">
            <?php endif; ?>
            <?php if ($currentCategoryId !== null): ?>
                <input type="hidden" name="category_id" value="<?= $currentCategoryId ?>">
            <?php endif; ?>
            <?php if ($currentSubcategoryId !== null): ?>
                <input type="hidden" name="subcategory_id" value="<?= $currentSubcategoryId ?>">
            <?php endif; ?>
            <label for="sort" class="form-label mb-0">Ordenar por:</label>
            <select class="form-select form-select-sm catalog-sort-select" id="sort" name="sort" onchange="this.form.submit()">
                <option value="newest" <?= $currentSort === 'newest' ? 'selected' : '' ?>>Más recientes</option>
                <option value="price_asc" <?= $currentSort === 'price_asc' ? 'selected' : '' ?>>Menor precio</option>
                <option value="price_desc" <?= $currentSort === 'price_desc' ? 'selected' : '' ?>>Mayor precio</option>
                <option value="name_asc" <?= $currentSort === 'name_asc' ? 'selected' : '' ?>>Nombre A-Z</option>
            </select>
        </form>
    </div>

    <?php if (!empty($products)): ?>
        <div class="row g-4">
            <?php foreach ($products as $p): ?>
                <?php $product = $p; ?>
                <?php $addToCartRedirect = '/shop'; ?>
                <?php require $sharedViewsPath . '/Components/ProductCard.php'; ?>
            <?php endforeach; ?>
        </div>

        <?php if (($meta['last_page'] ?? 1) > 1): ?>
        <nav class="mt-4" aria-label="Paginación del catálogo">
            <ul class="pagination justify-content-center">
                <?php for ($i = 1; $i <= ($meta['last_page'] ?? 1); $i++): ?>
                    <li class="page-item <?= ($meta['current_page'] ?? 1) === $i ? 'active' : '' ?>">
                        <a class="page-link" href="<?= $pageUrl($i) ?>"><?= $i ?></a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
        <?php endif; ?>

    <?php else: ?>
        <div class="text-center py-5">
            <i class="bi bi-search fs-1 text-muted"></i>
            <?php if ($isSearch): ?>
                <p class="mt-2 mb-1">No encontramos productos para "<?= htmlspecialchars($search) ?>".</p>
                <p class="text-muted">Prueba con otro término de búsqueda.</p>
            <?php else: ?>
                <p class="mt-2">No se encontraron productos.</p>
            <?php endif; ?>
            <a href="/shop" class="btn btn-outline-primary">Limpiar filtros</a>
        </div>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
require $sharedViewsPath . '/Layouts/PublicLayout.php';
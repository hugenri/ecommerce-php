<?php

$pageTitle = 'Inicio - Tienda';
$pageActive = 'home';
$viewCss = ['/public/css/home.css'];
$viewJs = ['/public/js/home.js'];
ob_start();
?>

<?php require $sharedViewsPath . '/Components/HeroBanner.php'; ?>

<?php if (!empty($categories)): ?>
<section class="py-5">
    <div class="container">
        <h2 class="mb-4">Categorías</h2>
        <div class="categories-scroll">
            <button type="button" class="carousel-btn prev" aria-label="Categorías anteriores"><i class="bi bi-chevron-left"></i></button>
            <div class="categories-track">
                <?php foreach ($categories as $cat): ?>
                    <?php $categoryImage = \App\Shared\Support\ImageHelper::url($cat->getImage()); ?>
                    <div class="category-col">
                        <a href="/shop?category_id=<?= $cat->getCategoryId() ?>" class="card category-card h-100 text-center p-3">
                            <div class="card-body">
                                <img src="<?= htmlspecialchars($categoryImage ?: '/public/images/default-category.svg') ?>" class="category-img" alt="<?= htmlspecialchars($cat->getName()) ?>">
                                <h6 class="card-title mt-2"><?= htmlspecialchars($cat->getName()) ?></h6>
                            </div>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" class="carousel-btn next" aria-label="Siguientes categorías"><i class="bi bi-chevron-right"></i></button>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if (!empty($recentProducts)): ?>
<section class="py-5 bg-light">
    <div class="container">
        <h2 class="mb-4">Productos recientes</h2>
        <div class="row g-4">
            <?php foreach ($recentProducts as $p): ?>
                <div class="col-6 col-md-4 col-lg-3">
                    <div class="card product-card">
                        <div class="card-img-wrapper">
                            <a href="/product/<?= $p->getProductId() ?>" class="product-image-link" aria-label="Ver detalle de <?= htmlspecialchars($p->getName()) ?>">
                                <?php if ($p->getImage()): ?>
                                    <img src="<?= htmlspecialchars($p->getImage()) ?>" class="card-img-top product-img" alt="<?= htmlspecialchars($p->getName()) ?>">
                                <?php else: ?>
                                    <div class="product-img d-flex align-items-center justify-content-center bg-light">
                                        <i class="bi bi-image fs-1 text-muted"></i>
                                    </div>
                                <?php endif; ?>
                            </a>
                            <?php if ($p->getDiscount() > 0): ?>
                                <span class="discount-badge badge bg-danger">-<?= (int) $p->getDiscount() ?>%</span>
                            <?php endif; ?>
                        </div>
                        <div class="card-body d-flex flex-column">
                            <h6 class="card-title"><?= htmlspecialchars($p->getName()) ?></h6>
                            <p class="card-text text-muted small flex-grow-1"><?= htmlspecialchars(mb_substr($p->getDescription() ?? '', 0, 80)) ?></p>
                            <div class="d-flex justify-content-between align-items-center mt-auto">
                                <div>
                                    <?php if ($p->getDiscount() > 0): ?>
                                        <small class="text-muted text-decoration-line-through">$<?= number_format($p->getPrice(), 2) ?></small><br>
                                        <span class="fs-5 fw-bold text-danger">$<?= number_format($p->getPrice() * (1 - $p->getDiscount() / 100), 2) ?></span>
                                    <?php else: ?>
                                        <span class="fs-5 fw-bold text-primary">$<?= number_format($p->getPrice(), 2) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="mt-2 d-grid gap-1">
                                <?php if ($p->getStock() > 0): ?>
                                    <form method="POST" action="/cart/add">
                                        <input type="hidden" name="product_id" value="<?= $p->getProductId() ?>">
                                        <button type="submit" class="btn btn-primary btn-sm w-100">
                                            <i class="bi bi-cart-plus"></i> Agregar al carrito
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <button class="btn btn-secondary btn-sm w-100" disabled>Agotado</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div class="text-center mt-4">
            <a href="/shop" class="btn btn-primary">Ver todos los productos</a>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if (!empty($saleProducts)): ?>
<section class="py-5">
    <div class="container">
        <h2 class="mb-4">Ofertas</h2>
        <div class="row g-4">
            <?php foreach ($saleProducts as $p): ?>
                <div class="col-6 col-md-4 col-lg-3">
                    <div class="card product-card border-danger">
                        <div class="card-img-wrapper">
                            <a href="/product/<?= $p->getProductId() ?>" class="product-image-link" aria-label="Ver detalle de <?= htmlspecialchars($p->getName()) ?>">
                                <?php if ($p->getImage()): ?>
                                    <img src="<?= htmlspecialchars($p->getImage()) ?>" class="card-img-top product-img" alt="<?= htmlspecialchars($p->getName()) ?>">
                                <?php else: ?>
                                    <div class="product-img d-flex align-items-center justify-content-center bg-light">
                                        <i class="bi bi-image fs-1 text-muted"></i>
                                    </div>
                                <?php endif; ?>
                            </a>
                            <span class="discount-badge badge bg-danger">-<?= (int) $p->getDiscount() ?>%</span>
                        </div>
                        <div class="card-body d-flex flex-column">
                            <h6 class="card-title"><?= htmlspecialchars($p->getName()) ?></h6>
                            <div class="mt-auto">
                                <small class="text-muted text-decoration-line-through">$<?= number_format($p->getPrice(), 2) ?></small><br>
                                <span class="fs-5 fw-bold text-danger">$<?= number_format($p->getPrice() * (1 - $p->getDiscount() / 100), 2) ?></span>
                            </div>
                            <div class="mt-2 d-grid gap-1">
                                <?php if ($p->getStock() > 0): ?>
                                    <form method="POST" action="/cart/add">
                                        <input type="hidden" name="product_id" value="<?= $p->getProductId() ?>">
                                        <button type="submit" class="btn btn-danger btn-sm w-100">
                                            <i class="bi bi-cart-plus"></i> Agregar al carrito
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <button class="btn btn-secondary btn-sm w-100" disabled>Agotado</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?php
$content = ob_get_clean();
require $sharedViewsPath . '/Layouts/PublicLayout.php';

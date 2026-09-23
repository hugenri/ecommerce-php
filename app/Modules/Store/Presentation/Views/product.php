<?php
$pageTitle = htmlspecialchars($product->getName()) . ' - Tienda';
$viewCss = ['/public/css/product.css'];
$viewJs = ['/public/js/product.js'];
ob_start();
?>

<div class="container py-4">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/">Inicio</a></li>
            <li class="breadcrumb-item"><a href="/shop">Catálogo</a></li>
            <?php if ($categoryName): ?>
                <li class="breadcrumb-item"><?= htmlspecialchars($categoryName) ?></li>
            <?php endif; ?>
            <li class="breadcrumb-item active"><?= htmlspecialchars($product->getName()) ?></li>
        </ol>
    </nav>

    <div class="row g-4">
        <div class="col-md-6">
            <?php if (!empty($images)): ?>
                <img src="<?= htmlspecialchars($images[0]->getImage()) ?>" class="gallery-img mb-3" id="mainImage" alt="<?= htmlspecialchars($product->getName()) ?>">
                <?php if (count($images) > 1): ?>
                    <div class="d-flex gap-2 flex-wrap">
                        <?php foreach ($images as $i => $img): ?>
                            <img src="<?= htmlspecialchars($img->getImage()) ?>" class="gallery-thumb <?= $i === 0 ? 'active' : '' ?>" alt="Thumbnail">
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php elseif ($product->getImage()): ?>
                <img src="<?= htmlspecialchars($product->getImage()) ?>" class="gallery-img" alt="<?= htmlspecialchars($product->getName()) ?>">
            <?php else: ?>
                <div class="gallery-img d-flex align-items-center justify-content-center bg-light">
                    <i class="bi bi-image fs-1 text-muted"></i>
                </div>
            <?php endif; ?>
        </div>

        <div class="col-md-6">
            <h2><?= htmlspecialchars($product->getName()) ?></h2>

            <table class="table table-borderless mt-3">
                <tr>
                    <td class="text-muted">Código</td>
                    <td><?= htmlspecialchars($product->getProductCode()) ?></td>
                </tr>
                
                <tr>
                    <td class="text-muted">Disponibilidad</td>
                    <td>
                        <?php if ($product->getStock() > 0): ?>
                            <span class="text-success"><i class="bi bi-check-circle"></i> En stock (<?= $product->getStock() ?> uds.)</span>
                        <?php else: ?>
                            <span class="text-danger"><i class="bi bi-x-circle"></i> Agotado</span>
                        <?php endif; ?>
                    </td>
                </tr>
            </table>

            <div class="mt-3">
                <?php if ($product->getDiscount() > 0): ?>
                    <small class="text-muted text-decoration-line-through fs-5">$<?= number_format($product->getPrice(), 2) ?></small>
                    <span class="badge bg-danger ms-2">-<?= (int) $product->getDiscount() ?>%</span>
                    <p class="fs-2 fw-bold text-danger mt-1">$<?= number_format($product->getPrice() * (1 - $product->getDiscount() / 100), 2) ?></p>
                <?php else: ?>
                    <p class="fs-2 fw-bold text-primary">$<?= number_format($product->getPrice(), 2) ?></p>
                <?php endif; ?>
            </div>

           

            <div class="mt-4 d-flex gap-2">
                <?php if ($product->getStock() > 0): ?>
                    <form method="POST" action="/cart/add" class="d-flex gap-2 align-items-center">
                        <input type="hidden" name="product_id" value="<?= $product->getProductId() ?>">
                        <input type="hidden" name="redirect" value="/product/<?= $product->getProductId() ?>">
                        <label class="visually-hidden" for="quantity">Cantidad</label>
                        <div class="qty-stepper input-group" data-quantity-stepper data-min="1" data-max="<?= (int) $product->getStock() ?>">
                            <button type="button" class="btn btn-outline-secondary" data-qty-dec aria-label="Disminuir cantidad">−</button>
                            <input type="text" class="form-control qty-input" data-qty-input id="quantity" name="quantity" value="1" inputmode="numeric" readonly aria-label="Cantidad">
                            <button type="button" class="btn btn-outline-secondary" data-qty-inc aria-label="Aumentar cantidad">+</button>
                        </div>
                        <button type="submit" class="btn btn-primary"><i class="bi bi-cart-plus"></i> Agregar al carrito</button>
                    </form>
                <?php else: ?>
                    <button class="btn btn-secondary" disabled><i class="bi bi-cart-x"></i> Agotado</button>
                <?php endif; ?>
                <a href="/shop" class="btn btn-outline-primary"><i class="bi bi-arrow-left"></i> Seguir comprando</a>
            </div>
        </div>
    </div>

    <div class="row mt-5">
        <div class="col-lg-6">
         <?php if ($product->getDescription()): ?>
                <div class="mt-3">
                    <h5>Descripción</h5>
                    <p class="text-muted"><?= nl2br(htmlspecialchars($product->getDescription())) ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require $sharedViewsPath . '/Layouts/PublicLayout.php';

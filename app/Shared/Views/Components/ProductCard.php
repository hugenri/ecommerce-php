<?php
/*
 * Tarjeta de producto reutilizable del catálogo público.
 *
 * Espera:
 *   $product             App\Modules\Products\Domain\Product
 *   $addToCartRedirect   string  (opcional) URL de retorno tras agregar al carrito
 */
$addToCartRedirect = $addToCartRedirect ?? '';
?>
<div class="col-6 col-md-4">
    <div class="card product-card">
        <div class="card-img-wrapper">
            <a href="/product/<?= $product->getProductId() ?>" class="product-image-link" aria-label="Ver detalle de <?= htmlspecialchars($product->getName()) ?>">
                <?php if ($product->getImage()): ?>
                    <img src="<?= htmlspecialchars($product->getImage()) ?>" class="card-img-top product-img" alt="<?= htmlspecialchars($product->getName()) ?>">
                <?php else: ?>
                    <div class="product-img d-flex align-items-center justify-content-center bg-light">
                        <i class="bi bi-image fs-1 text-muted"></i>
                    </div>
                <?php endif; ?>
            </a>
            <?php if ($product->getDiscount() > 0): ?>
                <span class="discount-badge badge bg-danger">-<?= (int) $product->getDiscount() ?>%</span>
            <?php endif; ?>
        </div>
        <div class="card-body d-flex flex-column">
            <h6 class="card-title"><?= htmlspecialchars($product->getName()) ?></h6>
            <div class="mt-auto">
                <?php if ($product->getDiscount() > 0): ?>
                    <small class="text-muted text-decoration-line-through">$<?= number_format($product->getPrice(), 2) ?></small><br>
                    <span class="fs-5 fw-bold text-danger">$<?= number_format($product->getPrice() * (1 - $product->getDiscount() / 100), 2) ?></span>
                <?php else: ?>
                    <span class="fs-5 fw-bold text-primary">$<?= number_format($product->getPrice(), 2) ?></span>
                <?php endif; ?>
            </div>
            <?php if ($product->getStock() === 0): ?>
                <span class="badge bg-warning text-dark mt-2">Agotado</span>
            <?php endif; ?>
            <div class="mt-2 d-grid gap-1">
                <?php if ($product->getStock() > 0): ?>
                    <form method="POST" action="/cart/add">
                        <input type="hidden" name="product_id" value="<?= $product->getProductId() ?>">
                        <?php if ($addToCartRedirect !== ''): ?>
                            <input type="hidden" name="redirect" value="<?= htmlspecialchars($addToCartRedirect) ?>">
                        <?php endif; ?>
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

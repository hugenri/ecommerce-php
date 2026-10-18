<?php
/*
 * Tarjeta de producto reutilizable del catálogo público.
 *
 * Espera:
 *   $product             App\Modules\Products\Domain\Product
 *   $addToCartRedirect   string  (opcional) URL de retorno tras agregar al carrito
 *   $cartQuantity        int     (opcional) unidades del producto ya en el carrito
 */
$addToCartRedirect = $addToCartRedirect ?? '';
$cartQuantity = (int) ($cartQuantity ?? 0);
$stock = (int) $product->getStock();
$available = max(0, $stock - $cartQuantity);
$addDisabled = $available <= 0;
$buttonLabel = $stock <= 0 ? 'Agotado' : ($available <= 0 ? 'Stock máximo alcanzado' : 'Agregar al carrito');
$buttonIcon = $addDisabled ? 'bi-cart-x' : 'bi-cart-plus';
?>
<div class="col-6 col-md-4">
    <div class="card product-card" data-product-card data-product-id="<?= $product->getProductId() ?>" data-stock="<?= $stock ?>">
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
            <small class="mt-2 text-muted" data-product-stock-info>
                <?php if ($stock <= 0): ?>Sin stock<?php else: ?>Disponible: <?= $available ?> uds.<?php endif; ?>
            </small>
            <div class="mt-2 d-grid gap-1">
                <form method="POST" action="/cart/add">
                    <input type="hidden" name="product_id" value="<?= $product->getProductId() ?>">
                    <?php if ($addToCartRedirect !== ''): ?>
                        <input type="hidden" name="redirect" value="<?= htmlspecialchars($addToCartRedirect) ?>">
                    <?php endif; ?>
                    <button type="submit" class="btn btn-primary btn-sm w-100" data-add-to-cart-btn <?= $addDisabled ? 'disabled' : '' ?>>
                        <i class="bi <?= $buttonIcon ?>"></i> <span data-add-to-cart-label><?= $buttonLabel ?></span>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

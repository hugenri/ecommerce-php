<?php
$pageTitle = 'Carrito - Tienda';
$viewCss = ['/public/css/cart.css'];
$viewJs = ['/public/js/cart.js?v=3'];
ob_start();
?>

<div class="container py-4">
    <h2 class="mb-4">Carrito de compras</h2>

    <div id="cartAlert" data-cart-alert class="alert alert-danger d-none" role="alert"></div>

    <div data-cart-content <?= !empty($items) ? '' : 'hidden' ?>>
        <div class="row">
            <div class="col-lg-8">
                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th></th>
                                <th>Producto</th>
                                <th>Precio</th>
                                <th>Cantidad</th>
                                <th>Subtotal</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                                <tr data-cart-row data-product-id="<?= (int) $item['product_id'] ?>">
                                    <td>
                                        <?php if ($item['image']): ?>
                                            <img src="<?= htmlspecialchars($item['image']) ?>" class="cart-img" alt="<?= htmlspecialchars($item['name']) ?>">
                                        <?php else: ?>
                                            <div class="cart-img d-flex align-items-center justify-content-center bg-light">
                                                <i class="bi bi-image text-muted"></i>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="/product/<?= $item['product_id'] ?>" class="text-decoration-none fw-semibold"><?= htmlspecialchars($item['name']) ?></a>
                                    </td>
                                    <td>$<?= number_format($item['price'], 2) ?></td>
                                    <td>
                                        <form method="POST" action="/cart/update" class="d-flex align-items-center gap-1" data-cart-update-form>
                                            <input type="hidden" name="product_id" value="<?= $item['product_id'] ?>">
                                            <div class="qty-stepper input-group" data-quantity-stepper data-min="1" data-max="<?= isset($item['stock']) ? max((int) $item['stock'], (int) $item['quantity']) : 9999 ?>">
                                                <button type="button" class="btn btn-outline-secondary" data-qty-dec aria-label="Disminuir cantidad">−</button>
                                                <input type="text" class="form-control qty-input" data-qty-input name="quantity" value="<?= (int) $item['quantity'] ?>" inputmode="numeric" readonly aria-label="Cantidad">
                                                <button type="button" class="btn btn-outline-secondary" data-qty-inc aria-label="Aumentar cantidad">+</button>
                                            </div>
                                        </form>
                                    </td>
                                    <td class="fw-bold" data-cart-subtotal>$<?= number_format($item['price'] * $item['quantity'], 2) ?></td>
                                    <td>
                                        <a href="/cart/remove/<?= $item['product_id'] ?>" class="btn btn-sm btn-outline-danger" data-confirm-title="Eliminar producto" data-confirm-message="¿Deseas eliminar este producto del carrito?" data-confirm-url="/cart/remove/<?= $item['product_id'] ?>">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="d-flex justify-content-between mt-3">
                    <a href="/shop" class="btn btn-outline-primary"><i class="bi bi-arrow-left"></i> Seguir comprando</a>
                    <a href="/cart/clear" class="btn btn-outline-danger" data-confirm-title="Vaciar carrito" data-confirm-message="¿Deseas eliminar todos los productos del carrito?" data-confirm-url="/cart/clear"><i class="bi bi-cart-x"></i> Vaciar carrito</a>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Resumen</h5>
                        <table class="table table-borderless mb-0">
                            <tr>
                                <td>Subtotal</td>
                                <td class="text-end" data-cart-summary-subtotal>$<?= number_format($subtotal, 2) ?></td>
                            </tr>
                            <tr>
                                <td>IVA (16%)</td>
                                <td class="text-end" data-cart-summary-iva>$<?= number_format($iva, 2) ?></td>
                            </tr>
                            <tr class="fw-bold fs-5">
                                <td>Total</td>
                                <td class="text-end" data-cart-summary-total>$<?= number_format($total, 2) ?></td>
                            </tr>
                        </table>
                        <hr>
                        <div class="d-grid">
                            <a href="/checkout" class="btn btn-primary">Continuar compra</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div data-cart-empty class="text-center py-5" <?= !empty($items) ? 'hidden' : '' ?>>
        <i class="bi bi-cart3 fs-1 text-muted"></i>
        <p class="mt-2 fs-5">Tu carrito está vacío.</p>
        <a href="/shop" class="btn btn-primary">Ir al catálogo</a>
    </div>
</div>

<div class="modal fade" id="confirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="confirmModalTitle"></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <p class="mb-0" id="confirmModalMessage"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="btnConfirmAction">Confirmar</button>
            </div>
        </div>
    </div>
</div>

<script>window.CART_CSRF_TOKEN = <?= json_encode($csrfToken ?? '') ?>;</script>

<?php
$content = ob_get_clean();
require $sharedViewsPath . '/Layouts/PublicLayout.php';

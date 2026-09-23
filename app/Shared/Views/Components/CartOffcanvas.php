<?php
// Mini Cart (Bootstrap Offcanvas). Contenido dinámico renderizado por
// /public/js/mini-cart.js consumiendo los mismos endpoints del carrito.
?>
<div class="offcanvas offcanvas-end mini-cart-offcanvas" tabindex="-1" id="miniCartOffcanvas" role="dialog" aria-modal="true" aria-labelledby="miniCartLabel">
    <div class="offcanvas-header border-bottom">
        <h5 class="offcanvas-title" id="miniCartLabel">
            <i class="bi bi-cart3 me-1" aria-hidden="true"></i> Productos en mi carrito
        </h5>
        <button type="button" class="btn-close" id="miniCartClose" data-bs-dismiss="offcanvas" aria-label="Cerrar carrito"></button>
    </div>
    <div class="offcanvas-body mini-cart-body d-flex flex-column">
        <div id="miniCartItems" class="mini-cart-items">
            <div id="miniCartSkeleton" class="mini-cart-skeleton" aria-hidden="true">
                <div class="skeleton-row">
                    <div class="mini-cart-img-wrap"><span class="placeholder w-100 h-100 rounded"></span></div>
                    <div class="flex-grow-1">
                        <span class="placeholder col-8 d-block mb-2"></span>
                        <span class="placeholder col-4 d-block mb-2"></span>
                        <span class="placeholder col-6"></span>
                    </div>
                </div>
                <div class="skeleton-row">
                    <div class="mini-cart-img-wrap"><span class="placeholder w-100 h-100 rounded"></span></div>
                    <div class="flex-grow-1">
                        <span class="placeholder col-8 d-block mb-2"></span>
                        <span class="placeholder col-4 d-block mb-2"></span>
                        <span class="placeholder col-6"></span>
                    </div>
                </div>
                <div class="skeleton-row">
                    <div class="mini-cart-img-wrap"><span class="placeholder w-100 h-100 rounded"></span></div>
                    <div class="flex-grow-1">
                        <span class="placeholder col-8 d-block mb-2"></span>
                        <span class="placeholder col-4 d-block mb-2"></span>
                        <span class="placeholder col-6"></span>
                    </div>
                </div>
            </div>
        </div>

        <div id="miniCartEmpty" class="mini-cart-empty text-center d-none flex-grow-1 d-flex flex-column align-items-center justify-content-center gap-2">
            <i class="bi bi-cart3 fs-1 text-muted" aria-hidden="true"></i>
            <p class="mt-2 fs-5 mb-0">Tu carrito está vacío.</p>
            <a href="/shop" class="btn btn-primary">Ver productos</a>
        </div>

        <div id="miniCartFooter" class="mini-cart-footer border-top p-3 d-none">
            <div class="d-flex justify-content-between align-items-center mb-1">
                <span class="text-muted">Subtotal para <strong id="miniCartCount" class="text-dark">0</strong> productos</span>
                <span class="fs-5 fw-bold" id="miniCartSubtotal">$0.00</span>
            </div>
            <div class="d-grid mt-2">
                <a href="/cart" class="btn btn-primary btn-lg fw-semibold"><i class="bi bi-cart-check me-1"></i>Ir al carrito</a>
            </div>
        </div>

        <div id="miniCartLive" class="visually-hidden" aria-live="polite" role="status"></div>
    </div>
</div>

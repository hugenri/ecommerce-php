<?php
$pageTitle = 'Pagar pedido - Tienda';
$pageActive = 'pago';
$viewCss = ['/public/css/checkout.css'];
$paymentMethod = (string) ($paymentMethod ?? 'conekta');
$viewJs = ($paymentMethod === 'paypal')
    ? ['/public/js/pago.js?v=9', '/public/js/pago/status-card.js?v=5', '/public/js/payment-page-paypal.js?v=9']
    : ['/public/js/pago.js?v=9', '/public/js/pago/status-card.js?v=5'];
ob_start();
?>

<div class="container py-5">
    <div class="row mb-4">
        <div class="col-12">
            <h2 class="mb-1"><i class="bi bi-credit-card"></i> Completar tu pago</h2>
            <p class="text-muted mb-0">Folio <strong><?= htmlspecialchars($sale->getSaleCode()) ?></strong> — revisa tu pedido antes de pagar.</p>
        </div>
    </div>

    <?php if ($sale->getPaymentStatus() === 'paid'): ?>
        <div class="alert alert-success">
            <i class="bi bi-check-circle-fill"></i> Este pedido ya fue pagado. Puedes ver el detalle en <a href="/account">Mis pedidos</a>.
        </div>
        <a href="/" class="btn btn-primary"><i class="bi bi-house"></i> Volver a la tienda</a>
    <?php elseif ($sale->getStatus() === 'cancelled'): ?>
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle"></i> Este pedido fue cancelado. No hay pago pendiente.
        </div>
    <?php else: ?>
        <div class="row g-4">
            <div class="col-12 col-md-7">
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Datos del pedido</h5>
                        <table class="table table-borderless mb-0">
                            <tr>
                                <td class="text-muted">Folio</td>
                                <td class="fw-bold"><?= htmlspecialchars($sale->getSaleCode()) ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Fecha</td>
                                <td><?= htmlspecialchars($sale->getSaleDate()?->format('d/m/Y H:i')) ?></td>
                            </tr>
                            <tr>
                                <td class="text-muted">Estado</td>
                                <td>
                                    <?php if ($sale->getPaymentStatus() === 'paid'): ?>
                                        <span class="badge bg-success"><i class="bi bi-check-circle-fill"></i> Pagado</span>
                                    <?php elseif ($sale->getStatus() === 'cancelled'): ?>
                                        <span class="badge bg-danger">Cancelado</span>
                                    <?php else: ?>
                                        <span id="pagoSaleStatusBadge" class="badge bg-warning text-dark">Pendiente de pago</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Productos</h5>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Producto</th>
                                        <th class="text-end">Precio</th>
                                        <th class="text-center">Cant.</th>
                                        <th class="text-end">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($details as $d): ?>
                                        <tr>
                                            <td><?= htmlspecialchars($d['product_name'] ?? 'Producto') ?></td>
                                            <td class="text-end">$<?= number_format((float) $d['discounted_unit_price'], 2) ?></td>
                                            <td class="text-center"><?= (int) $d['quantity'] ?></td>
                                            <td class="text-end">$<?= number_format((float) $d['subtotal'], 2) ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th colspan="3" class="text-end">Subtotal</th>
                                        <th class="text-end">$<?= number_format($sale->getSubtotal(), 2) ?></th>
                                    </tr>
                                    <tr>
                                        <th colspan="3" class="text-end">IVA (16%)</th>
                                        <th class="text-end">$<?= number_format($sale->getTax(), 2) ?></th>
                                    </tr>
                                    <tr class="fs-5">
                                        <th colspan="3" class="text-end">Total</th>
                                        <th class="text-end">$<?= number_format($sale->getTotal(), 2) ?></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-md-5">
                <div class="payment-sticky" style="position: sticky; top: 1rem;">
                    <?php if ($sale->getPaymentMethod() === 'paypal'): ?>
                        <div class="card">
                            <div class="card-body">
                                <h6 class="card-title">Método de pago</h6>
                                <div id="paypalHookMessage" class="alert alert-warning d-none" role="alert"></div>
                                <div class="mb-3">
                                    <div id="paypalButton" class="d-grid gap-2"></div>
                                </div>
                                <div id="pagoResult" hidden></div>
                            </div>
                        </div>
                    <?php else: ?>
                        <div class="card">
                            <div class="card-body">
                                <h6 class="card-title">Método de pago</h6>
                                <div id="conektaHookMessage" class="alert alert-warning d-none" role="alert"></div>
                                <div id="conektaIFrame"></div>
                                <div id="conektaPendingNote" class="alert alert-info d-none" role="alert">
                                    <i class="bi bi-hourglass-split"></i> Tu pago está pendiente de confirmación. Conserva la referencia que se muestra aquí y <a href="/account?section=orders">ver tu pedido</a>.
                                </div>
                                <div id="pagoResult" hidden></div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<template id="pagoResultTemplate">
    <div class="pago-status-card text-center py-2">
        <i class="bi pago-status-icon display-6" aria-hidden="true"></i>
        <h6 class="pago-status-title mt-2 mb-1"></h6>
        <p class="pago-status-text text-muted small mb-3"></p>
        <table class="table table-borderless table-sm text-start mb-3">
            <tr>
                <td class="text-muted">Método de pago</td>
                <td class="fw-bold pago-status-method"></td>
            </tr>
            <tr class="pago-status-row-reference" hidden>
                <td class="text-muted">Referencia</td>
                <td class="pago-status-reference"></td>
            </tr>
        </table>
        <a href="#" class="btn btn-primary w-100 pago-status-cta"><i class="bi bi-eye"></i> Ver mi pedido</a>
    </div>
</template>

<script>
    window.PAGO_SALE_ID = <?= json_encode($sale->getSaleId()) ?>;
    window.PAGO_SALE_CODE = <?= json_encode($sale->getSaleCode()) ?>;
    window.PAGO_SALE_PAID = <?= json_encode($sale->getPaymentStatus() === 'paid') ?>;
    window.PAGO_SALE_CANCELLED = <?= json_encode($sale->getStatus() === 'cancelled') ?>;
    window.PAGO_PAYMENT_METHOD = <?= json_encode($paymentMethod) ?>;
    window.PAGO_PAYMENT_TYPE = <?= json_encode($pagoPaymentMethod ?? $paymentMethod) ?>;
    window.CONEKTA_PUBLIC_KEY = <?= json_encode($conektaPublicKey ?? '') ?>;
    window.PAYPAL_CLIENT_ID = <?= json_encode($paypalClientId ?? '') ?>;
    window.PAYPAL_CURRENCY = <?= json_encode($paypalCurrency ?? 'MXN') ?>;
    window.CHECKOUT_CSRF_TOKEN = <?= json_encode($csrfToken ?? '') ?>;
    window.PAGO_PENDING_REFERENCE = <?= json_encode($pagoPendingReference ?? null) ?>;
    window.PAGO_CONEXTA_ORDER_ID = <?= json_encode($pagoConektaOrderId ?? null) ?>;
    window.PAGO_CHECKOUT_REQUEST_ID = <?= json_encode($pagoCheckoutRequestId ?? null) ?>;
</script>
<?php
$content = ob_get_clean();
require $sharedViewsPath . '/Layouts/PublicLayout.php';
<?php
$pageTitle = 'Pedido confirmado - Tienda';
$viewCss = ['/public/css/confirmation.css'];
ob_start();
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="text-center mb-5">
                <i class="bi bi-check-circle-fill success-icon"></i>
                <h2 class="mt-3">¡Pedido realizado correctamente!</h2>
                <p class="text-muted">Tu compra ha sido registrada. Pronto recibirás tu pedido.</p>
            </div>

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
                            <td><span class="badge bg-warning text-dark">Pendiente</span></td>
                        </tr>
                        <tr>
                            <td class="text-muted">Pago</td>
                            <td><span class="badge bg-success">Pagado</span></td>
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

            <div class="text-center">
                <a href="/" class="btn btn-primary btn-lg"><i class="bi bi-house"></i> Volver a la tienda</a>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require $sharedViewsPath . '/Layouts/PublicLayout.php';

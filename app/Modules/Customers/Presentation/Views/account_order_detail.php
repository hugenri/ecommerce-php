<div class="card mb-4">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0"><i class="bi bi-receipt"></i> Detalle del pedido</h5>
            <a href="/account?section=orders" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left"></i> Volver</a>
        </div>
        <hr>

        <div class="row">
            <div class="col-md-6">
                <table class="table table-borderless mb-0">
                    <tr><td class="text-muted">Folio</td><td class="fw-bold"><?= htmlspecialchars($order->getSaleCode()) ?></td></tr>
                    <tr><td class="text-muted">Fecha</td><td><?= htmlspecialchars($order->getSaleDate()?->format('d/m/Y H:i')) ?></td></tr>
                    <tr><td class="text-muted">Estado de entrega</td><td>
                        <?php if ($orderExpired): ?>
                            <span class="badge bg-secondary"><i class="bi bi-clock"></i> Expirado</span>
                        <?php endif; ?>
                        <span class="badge bg-warning text-dark"><?= htmlspecialchars(\App\Shared\Support\StatusTranslator::status($order->getStatus())) ?></span>
                    </td></tr>
                    <tr><td class="text-muted">Método de pago</td><td><?= htmlspecialchars(\App\Shared\Support\StatusTranslator::paymentMethod($order->getPaymentMethod())) ?></td></tr>
                    <tr><td class="text-muted">Pago</td><td><span class="badge bg-success"><?= htmlspecialchars(\App\Shared\Support\StatusTranslator::paymentStatus($order->getPaymentStatus())) ?></span></td></tr>
                    <?php if (!empty($paymentReference) && $order->getPaymentMethod() === 'conekta' && in_array($order->getPaymentStatus(), ['pending', 'paid'], true)): ?>
                        <tr>
                            <td class="text-muted">Referencia de pago</td>
                            <td class="fw-bold"><?= htmlspecialchars($paymentReference) ?></td>
                        </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-body">
        <h5 class="card-title">Productos</h5>
        <hr>
        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Producto</th>
                        <th>Precio</th>
                        <th>Dto.</th>
                        <th class="text-center">Cant.</th>
                        <th class="text-end">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($details as $d): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-2">
                                    <?php if (!empty($d['product_name'])): ?>
                                        <small class="fw-semibold"><?= htmlspecialchars($d['product_name']) ?></small>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>$<?= number_format((float) $d['unit_price'], 2) ?></td>
                            <td><?= (float) $d['discount_percentage'] > 0 ? '-' . (int) $d['discount_percentage'] . '%' : '—' ?></td>
                            <td class="text-center"><?= (int) $d['quantity'] ?></td>
                            <td class="text-end">$<?= number_format((float) $d['subtotal'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr><th colspan="4" class="text-end">Subtotal</th><th class="text-end">$<?= number_format($order->getSubtotal(), 2) ?></th></tr>
                    <tr><th colspan="4" class="text-end">IVA (16%)</th><th class="text-end">$<?= number_format($order->getTax(), 2) ?></th></tr>
                    <tr class="fs-5"><th colspan="4" class="text-end">Total</th><th class="text-end">$<?= number_format($order->getTotal(), 2) ?></th></tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<?php if ($orderCanComplete): ?>
    <div class="card mb-4">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="card-title mb-0"><i class="bi bi-credit-card"></i> Completa tu pago</h5>
                    <small class="text-muted d-block">Tu pedido aún está pendiente de pago.</small>
                </div>
                <a href="/pago/<?= htmlspecialchars($order->getSaleCode()) ?>" class="btn btn-primary">
                    <i class="bi bi-credit-card"></i> Completar pago
                </a>
            </div>
        </div>
    </div>
<?php elseif ($orderExpired): ?>
    <div class="alert alert-secondary">
        <i class="bi bi-clock"></i> La ventana de pago de este pedido expiró. Si ya realizaste tu pago, se confirmará automáticamente al procesarse.
    </div>
<?php endif; ?>

<div class="card">
    <div class="card-body">
        <h5 class="card-title"><i class="bi bi-truck"></i> Estado del envío</h5>
        <hr>
            <?php if ($delivery): ?>
            <?php
                $deliveryStatuses = [
                    'pending' => ['label' => \App\Shared\Support\StatusTranslator::deliveryStatus('pending'), 'icon' => 'bi-hourglass-split', 'color' => 'warning'],
                    'preparing' => ['label' => \App\Shared\Support\StatusTranslator::deliveryStatus('preparing'), 'icon' => 'bi-box-seam', 'color' => 'info'],
                    'shipped' => ['label' => \App\Shared\Support\StatusTranslator::deliveryStatus('shipped'), 'icon' => 'bi-truck', 'color' => 'primary'],
                    'delivered' => ['label' => \App\Shared\Support\StatusTranslator::deliveryStatus('delivered'), 'icon' => 'bi-check-circle', 'color' => 'success'],
                    'cancelled' => ['label' => \App\Shared\Support\StatusTranslator::deliveryStatus('cancelled'), 'icon' => 'bi-x-circle', 'color' => 'danger'],
                ];
                $ds = $deliveryStatuses[$delivery->getStatus()] ?? $deliveryStatuses['pending'];
            ?>
            <div class="d-flex align-items-center gap-3 mb-3">
                <i class="bi <?= $ds['icon'] ?> fs-1 text-<?= $ds['color'] ?>"></i>
                <div>
                    <h6 class="mb-1">Estado: <span class="badge bg-<?= $ds['color'] ?>"><?= $ds['label'] ?></span></h6>
                    <?php if ($delivery->getShippingDate()): ?>
                        <small class="text-muted d-block">Envío: <?= htmlspecialchars($delivery->getShippingDate()?->format('d/m/Y H:i')) ?></small>
                    <?php endif; ?>
                    <?php if ($delivery->getDeliveryDate()): ?>
                        <small class="text-muted d-block">Entrega: <?= htmlspecialchars($delivery->getDeliveryDate()?->format('d/m/Y H:i')) ?></small>
                    <?php endif; ?>
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center">
                <?php
                    $steps = ['pending', 'preparing', 'shipped', 'delivered'];
                    $currentIdx = array_search($delivery->getStatus(), $steps);
                    if ($delivery->getStatus() === 'cancelled') $currentIdx = -1;
                ?>
                <?php foreach ($steps as $i => $step): ?>
                    <?php $s = $deliveryStatuses[$step]; ?>
                    <div class="text-center <?= $i <= $currentIdx ? 'text-' . $s['color'] : 'text-muted' ?>" style="flex:1;">
                        <i class="bi <?= $s['icon'] ?> fs-3"></i>
                        <small class="d-block mt-1"><?= $s['label'] ?></small>
                    </div>
                    <?php if ($i < count($steps) - 1): ?>
                        <div class="flex-grow-1 mx-2" style="height:2px;background:<?= $i < $currentIdx ? '#198754' : '#dee2e6' ?>;"></div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-info mb-0">No hay información de envío disponible.</div>
        <?php endif; ?>
    </div>
</div>

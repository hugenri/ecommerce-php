<div class="card">
    <div class="card-body">
        <h5 class="card-title"><i class="bi bi-box"></i> Mis pedidos</h5>
        <hr>

        <?php if (!empty($orders)): ?>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Folio</th>
                            <th>Fecha</th>
                            <th>Total</th>
                            <th>Estado de entrega</th>
                            <th>Pago</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $o): ?>
                            <?php
                                $orderMethod = $orderPaymentMethods[$o->getSaleId()] ?? $o->getPaymentMethod();
                                $isCashOrSpei = in_array($orderMethod, ['cash', 'bank_transfer'], true);
                                $isExpired = in_array($o->getSaleId(), $expiredOrderIds ?? [], true);
                            ?>
                            <tr>
                                <td class="fw-bold"><?= htmlspecialchars($o->getSaleCode()) ?></td>
                                <td><?= htmlspecialchars($o->getSaleDate()?->format('d/m/Y H:i')) ?></td>
                                <td>$<?= number_format($o->getTotal(), 2) ?></td>
                                <td>
                                    <?php
                                        $statusBadges = [
                                            'pending' => 'bg-warning text-dark',
                                            'processing' => 'bg-info text-dark',
                                            'shipped' => 'bg-primary',
                                            'delivered' => 'bg-success',
                                            'cancelled' => 'bg-danger',
                                        ];
                                        $badge = $statusBadges[$o->getStatus()] ?? 'bg-secondary';
                                    ?>
                                    <?php if ($isExpired): ?>
                                        <span class="badge bg-secondary"><i class="bi bi-clock"></i> Expirado</span>
                                        <span class="badge <?= $badge ?>"><?= \App\Shared\Support\StatusTranslator::status($o->getStatus()) ?></span>
                                    <?php else: ?>
                                        <span class="badge <?= $badge ?>"><?= \App\Shared\Support\StatusTranslator::status($o->getStatus()) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge bg-secondary"><?= \App\Shared\Support\StatusTranslator::paymentMethod($o->getPaymentMethod()) ?></span></td>
                                <td>
                                    <a href="/account?section=order-detail&order_id=<?= $o->getSaleId() ?>" class="btn btn-outline-primary btn-sm">
                                        <i class="bi bi-eye"></i> Ver
                                    </a>
                                    <?php if (!$isCashOrSpei && $o->getPaymentStatus() === 'pending' && $o->getStatus() === 'pending'): ?>
                                        <a href="/pago/<?= htmlspecialchars($o->getSaleCode()) ?>" class="btn btn-primary btn-sm">
                                            <i class="bi bi-credit-card"></i> Completar pago
                                        </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if (($meta['last_page'] ?? 1) > 1): ?>
                <nav>
                    <ul class="pagination justify-content-center mt-3">
                        <?php for ($i = 1; $i <= $meta['last_page']; $i++): ?>
                            <li class="page-item <?= ($meta['current_page'] ?? 1) === $i ? 'active' : '' ?>">
                                <a class="page-link" href="/account?section=orders&page=<?= $i ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php else: ?>
            <div class="text-center py-4">
                <i class="bi bi-inbox fs-1 text-muted"></i>
                <p class="mt-2">No tienes pedidos aún.</p>
                <a href="/shop" class="btn btn-primary">Ir al catálogo</a>
            </div>
        <?php endif; ?>
    </div>
</div>

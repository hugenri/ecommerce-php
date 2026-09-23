<?php
$pageTitle = 'Checkout - Tienda';
$pageActive = 'checkout';
$viewCss = ['/public/css/checkout.css'];
$viewJs = ['/public/js/checkout.js?v=3', '/public/js/checkout-flow.js?v=3', '/public/js/checkout/confirm-payment-modal.js?v=2', '/public/js/paypal-checkout.js?v=5', '/public/js/conekta-checkout.js?v=3'];

$addrErrors = $_SESSION['checkout_errors'] ?? [];
$old = $_SESSION['checkout_old'] ?? [];
$hasAddrErrors = !empty($addrErrors);
unset($_SESSION['checkout_errors'], $_SESSION['checkout_old']);
ob_start();
?>

<div class="container py-4">
    <h2 class="mb-4">Checkout</h2>

    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show"><?= htmlspecialchars($error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success alert-dismissible fade show"><?= htmlspecialchars($success) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title"><i class="bi bi-person"></i> Datos del cliente</h5>
                    <p class="mb-1"><strong><?= htmlspecialchars($customer->getFullName()) ?></strong></p>
                    <p class="text-muted mb-0"><?= htmlspecialchars($customer->getEmail()) ?></p>
                </div>
            </div>

            <div class="card mb-4" id="addressSection">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0"><i class="bi bi-geo-alt"></i> Dirección de envío</h5>
                        <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addressModal">
                            <i class="bi bi-plus-lg"></i> Agregar dirección
                        </button>
                    </div>

                    <?php if (!empty($addresses)): ?>
                        <div class="row g-3 mt-2" id="addressList">
                            <?php foreach ($addresses as $addr): ?>
                                <div class="col-md-6">
                                    <div class="card address-card p-3 <?= $selectedAddress == $addr->getAddressId() ? 'selected' : '' ?>"
                                         onclick="selectAddress(this, <?= $addr->getAddressId() ?>)">
                                        <div class="form-check">
                                            <input class="form-check-input" type="radio" name="address_id"
                                                   value="<?= $addr->getAddressId() ?>"
                                                   id="addr_<?= $addr->getAddressId() ?>"
                                                   <?= $selectedAddress == $addr->getAddressId() ? 'checked' : '' ?>>
                                            <label class="form-check-label fw-semibold" for="addr_<?= $addr->getAddressId() ?>">
                                                <?= htmlspecialchars($addr->getAlias() ?: 'Dirección') ?>
                                            </label>
                                        </div>
                                        <small class="text-muted d-block mt-2">
                                            <?= htmlspecialchars($addr->getStreet()) ?> #<?= htmlspecialchars($addr->getNumber()) ?>,
                                            <?= htmlspecialchars($addr->getNeighborhood()) ?>,
                                            <?= htmlspecialchars($addr->getMunicipality()) ?>,
                                            <?= htmlspecialchars($addr->getState()) ?>
                                        </small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="mt-3">
                            <div class="alert alert-info mb-0">No tienes direcciones registradas. Agrega una para continuar.</div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card mb-4" id="paymentSection">
                <div class="card-body">
                    <h5 class="card-title"><i class="bi bi-credit-card"></i> Método de pago</h5>
                    <div class="row g-3 mt-2">
                        <?php foreach ($providers as $provider): ?>
                            <div class="col-md-4">
                                <div class="card payment-card p-3 text-center <?= $selectedPayment === $provider->id() ? 'selected' : '' ?>"
                                     onclick="selectPayment(this, '<?= $provider->id() ?>')">
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="radio" name="payment_method"
                                               value="<?= $provider->id() ?>" id="pay_<?= $provider->id() ?>"
                                               <?= $selectedPayment === $provider->id() ? 'checked' : '' ?>>
                                    </div>
                                    <i class="bi <?= $provider->icon() ?> fs-2 text-primary"></i>
                                    <label class="form-check-label mt-1" for="pay_<?= $provider->id() ?>"><?= htmlspecialchars($provider->name()) ?></label>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-body">
                    <h5 class="card-title">Estado del pedido</h5>
                    <div id="statusAddress" class="status-step <?= $selectedAddress ? 'done' : 'pending' ?>">
                        <i class="bi <?= $selectedAddress ? 'bi-check-circle-fill' : 'bi-hourglass-split' ?>"></i>
                        <span><?= $selectedAddress ? 'Dirección seleccionada' : 'Dirección pendiente' ?></span>
                    </div>
                    <div id="statusPayment" class="status-step <?= $selectedPayment ? 'done' : 'pending' ?>">
                        <i class="bi <?= $selectedPayment ? 'bi-check-circle-fill' : 'bi-hourglass-split' ?>"></i>
                        <span><?= $selectedPayment ? 'Método de pago seleccionado' : 'Método de pago pendiente' ?></span>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Resumen</h5>
                    <div class="mb-3">
                        <?php foreach ($items as $item): ?>
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <?php if ($item['image']): ?>
                                    <img src="<?= htmlspecialchars($item['image']) ?>" class="summary-img" alt="">
                                <?php else: ?>
                                    <div class="summary-img d-flex align-items-center justify-content-center bg-light">
                                        <i class="bi bi-image text-muted small"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="flex-grow-1">
                                    <small class="d-block text-truncate"><?= htmlspecialchars($item['name']) ?></small>
                                    <small class="text-muted"><?= $item['quantity'] ?> x $<?= number_format((float) ($item['discounted_price'] ?? $item['price']), 2) ?></small>
                                </div>
                                <small class="fw-bold">$<?= number_format((float) ($item['line_amount'] ?? $item['price'] * $item['quantity']), 2) ?></small>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <hr>
                    <table class="table table-borderless mb-0">
                        <tr>
                            <td>Subtotal</td>
                            <td class="text-end">$<?= number_format($subtotal, 2) ?></td>
                        </tr>
                        <tr>
                            <td>IVA (16%)</td>
                            <td class="text-end">$<?= number_format($iva, 2) ?></td>
                        </tr>
                        <tr class="fw-bold fs-5">
                            <td>Total</td>
                            <td class="text-end">$<?= number_format($total, 2) ?></td>
                        </tr>
                    </table>
                    <hr>
                    <input type="hidden" id="hiddenAddressId" value="<?= $selectedAddress ?: '' ?>">
                </div>
            </div>

            <div id="providerFlowContainer" class="mt-3"></div>
        </div>
    </div>
</div>

<!-- Modal: Nueva dirección -->
<div class="modal fade" id="addressModal" tabindex="-1" <?= $hasAddrErrors ? 'data-auto-open="1"' : '' ?>>
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="/checkout/address/new">
                <input type="hidden" name="_csrf_token" value="<?= htmlspecialchars($csrfToken ?? '') ?>">
                <div class="modal-header">
                    <h5 class="modal-title">Nueva dirección</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label for="street" class="form-label">Calle *</label>
                            <input type="text" class="form-control <?= isset($addrErrors['street']) ? 'is-invalid' : '' ?>" id="street" name="street" required value="<?= htmlspecialchars($old['street'] ?? '') ?>">
                            <?php if (isset($addrErrors['street'])): ?><div class="invalid-feedback"><?= implode(', ', $addrErrors['street']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="number" class="form-label">Número *</label>
                            <input type="text" class="form-control <?= isset($addrErrors['number']) ? 'is-invalid' : '' ?>" id="number" name="number" required value="<?= htmlspecialchars($old['number'] ?? '') ?>">
                            <?php if (isset($addrErrors['number'])): ?><div class="invalid-feedback"><?= implode(', ', $addrErrors['number']) ?></div><?php endif; ?>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="neighborhood" class="form-label">Colonia *</label>
                        <input type="text" class="form-control <?= isset($addrErrors['neighborhood']) ? 'is-invalid' : '' ?>" id="neighborhood" name="neighborhood" required value="<?= htmlspecialchars($old['neighborhood'] ?? '') ?>">
                        <?php if (isset($addrErrors['neighborhood'])): ?><div class="invalid-feedback"><?= implode(', ', $addrErrors['neighborhood']) ?></div><?php endif; ?>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="municipality" class="form-label">Municipio *</label>
                            <input type="text" class="form-control <?= isset($addrErrors['municipality']) ? 'is-invalid' : '' ?>" id="municipality" name="municipality" required value="<?= htmlspecialchars($old['municipality'] ?? '') ?>">
                            <?php if (isset($addrErrors['municipality'])): ?><div class="invalid-feedback"><?= implode(', ', $addrErrors['municipality']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="state" class="form-label">Estado *</label>
                            <input type="text" class="form-control <?= isset($addrErrors['state']) ? 'is-invalid' : '' ?>" id="state" name="state" required value="<?= htmlspecialchars($old['state'] ?? '') ?>">
                            <?php if (isset($addrErrors['state'])): ?><div class="invalid-feedback"><?= implode(', ', $addrErrors['state']) ?></div><?php endif; ?>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="zip_code" class="form-label">Código Postal *</label>
                            <input type="text" class="form-control <?= isset($addrErrors['zip_code']) ? 'is-invalid' : '' ?>" id="zip_code" name="zip_code" required maxlength="5" value="<?= htmlspecialchars($old['zip_code'] ?? '') ?>">
                            <?php if (isset($addrErrors['zip_code'])): ?><div class="invalid-feedback"><?= implode(', ', $addrErrors['zip_code']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="alias" class="form-label">Alias</label>
                            <input type="text" class="form-control" id="alias" name="alias" placeholder="Casa, Trabajo..." value="<?= htmlspecialchars($old['alias'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="reference" class="form-label">Referencia</label>
                        <textarea class="form-control" id="reference" name="reference" rows="2"><?= htmlspecialchars($old['reference'] ?? '') ?></textarea>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="is_default" name="is_default" value="1" <?= isset($old['is_default']) ? 'checked' : '' ?>>
                        <label class="form-check-label" for="is_default">Dirección predeterminada</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar dirección</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    window.CHECKOUT_PROVIDERS = <?= json_encode(array_map(fn($p) => [
        'id' => $p->id(),
        'name' => $p->name(),
        'icon' => $p->icon(),
        'type' => $p->type(),
        'confirmationMode' => $p->confirmationMode(),
        'requiresRedirect' => $p->requiresRedirect(),
        'createOrderEndpoint' => $p->createOrderEndpoint(),
        'captureEndpoint' => $p->captureEndpoint(),
    ], $providers)) ?>;
    window.CHECKOUT_CSRF_TOKEN = <?= json_encode($csrfToken ?? '') ?>;
    window.PAYPAL_CLIENT_ID = <?= json_encode($paypalClientId ?? '') ?>;
    window.PAYPAL_CURRENCY = <?= json_encode($paypalCurrency ?? 'MXN') ?>;
    window.CONEKTA_PUBLIC_KEY = <?= json_encode($conektaPublicKey ?? '') ?>;
    window.CHECKOUT_TOTAL = <?= json_encode($total ?? 0) ?>;
</script>
<?php
$content = ob_get_clean();
require $sharedViewsPath . '/Layouts/PublicLayout.php';

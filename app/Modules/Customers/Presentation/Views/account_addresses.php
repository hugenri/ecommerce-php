<div class="card mb-4">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0"><i class="bi bi-geo-alt"></i> Mis direcciones</h5>
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addressModal">
                <i class="bi bi-plus-lg"></i> Agregar dirección
            </button>
        </div>
        <hr>

        <?php if (!empty($addresses)): ?>
            <div class="row g-3">
                <?php foreach ($addresses as $addr): ?>
                    <div class="col-md-6">
                        <div class="card address-card p-3 <?= $addr->isDefault() ? 'default' : '' ?>">
                            <?php if ($addr->isDefault()): ?>
                                <span class="default-badge badge bg-success">Predeterminada</span>
                            <?php endif; ?>
                            <h6><?= htmlspecialchars($addr->getAlias() ?: 'Dirección') ?></h6>
                            <small class="text-muted d-block">
                                <?= htmlspecialchars($addr->getStreet()) ?> #<?= htmlspecialchars($addr->getNumber()) ?>,
                                <?= htmlspecialchars($addr->getNeighborhood()) ?>,
                                <?= htmlspecialchars($addr->getMunicipality()) ?>,
                                <?= htmlspecialchars($addr->getState()) ?>,
                                CP <?= htmlspecialchars($addr->getZipCode()) ?>
                            </small>
                            <?php if ($addr->getReference()): ?>
                                <small class="text-muted d-block mt-1">Ref: <?= htmlspecialchars($addr->getReference()) ?></small>
                            <?php endif; ?>
                            <div class="mt-2 d-flex gap-1">
                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="editAddress(<?= $addr->getAddressId() ?>)">
                                    <i class="bi bi-pencil"></i> Editar
                                </button>
                                <?php if (!$addr->isDefault()): ?>
                                    <form method="POST" action="/account/address/default/<?= $addr->getAddressId() ?>" class="d-inline">
                                        <button type="submit" class="btn btn-outline-success btn-sm"><i class="bi bi-check-circle"></i> Predeterminar</button>
                                    </form>
                                    <form method="POST" action="/account/address/delete/<?= $addr->getAddressId() ?>" class="d-inline" onsubmit="return confirm('¿Eliminar esta dirección?')">
                                        <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-info mb-0">No tienes direcciones registradas.</div>
        <?php endif; ?>
    </div>
</div>

<!-- New Address Modal -->
<div class="modal fade" id="addressModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="/account/address/new" id="newAddressForm" data-validate novalidate>
                <div class="modal-header">
                    <h5 class="modal-title">Nueva dirección</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="newAddressAlert" class="form-alert" role="alert" hidden></div>
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label for="street" class="form-label">Calle *</label>
                            <input type="text" class="form-control" id="street" name="street" required minlength="3" maxlength="80"
                                   aria-describedby="street-feedback">
                            <div id="street-feedback" class="field-feedback" role="alert" hidden
                                 data-mensaje="La calle es obligatoria."></div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="number" class="form-label">Número *</label>
                            <input type="text" class="form-control" id="number" name="number" required maxlength="15"
                                   aria-describedby="number-feedback">
                            <div id="number-feedback" class="field-feedback" role="alert" hidden
                                 data-mensaje="El número es obligatorio."></div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="neighborhood" class="form-label">Colonia *</label>
                        <input type="text" class="form-control" id="neighborhood" name="neighborhood" required minlength="3" maxlength="80"
                               aria-describedby="neighborhood-feedback">
                        <div id="neighborhood-feedback" class="field-feedback" role="alert" hidden
                             data-mensaje="La colonia es obligatoria."></div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="municipality" class="form-label">Municipio *</label>
                            <input type="text" class="form-control" id="municipality" name="municipality" required minlength="3" maxlength="80"
                                   aria-describedby="municipality-feedback">
                            <div id="municipality-feedback" class="field-feedback" role="alert" hidden
                                 data-mensaje="El municipio es obligatorio."></div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="state" class="form-label">Estado *</label>
                            <input type="text" class="form-control" id="state" name="state" required minlength="3" maxlength="80"
                                   aria-describedby="state-feedback">
                            <div id="state-feedback" class="field-feedback" role="alert" hidden
                                 data-mensaje="El estado es obligatorio."></div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="zip_code" class="form-label">Código postal *</label>
                            <input type="text" class="form-control" id="zip_code" name="zip_code" required maxlength="5"
                                   pattern="^[0-9]{5}$" aria-describedby="zip_code-feedback">
                            <div id="zip_code-feedback" class="field-feedback" role="alert" hidden
                                 data-mensaje="El código postal debe tener 5 dígitos."></div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="alias" class="form-label">Alias</label>
                            <input type="text" class="form-control" id="alias" name="alias" maxlength="50" placeholder="Casa, Trabajo...">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="reference" class="form-label">Referencia</label>
                        <textarea class="form-control" id="reference" name="reference" rows="2" maxlength="150"></textarea>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_default" value="1" id="newIsDefault">
                        <label class="form-check-label" for="newIsDefault">Dirección predeterminada</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Address Modal -->
<div class="modal fade" id="editAddressModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="" id="editAddressForm" data-validate novalidate>
                <div class="modal-header">
                    <h5 class="modal-title">Editar dirección</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="editAddressAlert" class="form-alert" role="alert" hidden></div>
                    <div id="editAddressBody"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary">Guardar cambios</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php
$pageTitle = 'Mi Perfil';
$pageActive = 'profile';
$topbarTitle = 'Mi Perfil';
$viewJs = [
    '/public/js/profile/api.js?v=2',
    '/public/js/profile/app.js?v=2',
];
$profile = $profile ?? [];
$userRole = $userRole ?? 'employee';
$name = $profile['name'] ?? '';
$email = $profile['email'] ?? '';
$phone = $profile['phone'] ?? '';

ob_start();
?>
<div class="row g-4 justify-content-center">
    <div class="col-12 col-lg-8">
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <h6 class="mb-0">
                    <i class="bi bi-person-circle me-1"></i> Mi Perfil
                </h6>
            </div>
            <div class="card-body p-0">
                <div class="list-group list-group-flush">

                    <div class="field-row" data-field="name">
                        <div class="read-state d-flex align-items-center py-3 px-3">
                            <div class="fs-5 text-primary pe-3"><i class="bi bi-person"></i></div>
                            <div class="flex-grow-1">
                                <div class="text-muted small">Nombre</div>
                                <div class="fw-medium" id="valueName"><?= htmlspecialchars($name) ?></div>
                            </div>
                            <div>
                                <button type="button" class="btn btn-outline-primary btn-sm" data-edit="name">
                                    <i class="bi bi-pencil me-1"></i>Editar
                                </button>
                            </div>
                        </div>
                        <div class="edit-state d-none py-3 px-3">
                            <div class="text-muted small mb-2">Nombre</div>
                            <div class="d-flex flex-wrap gap-2 align-items-start">
                                <input type="text" class="form-control" id="editName" maxlength="30"
                                       value="<?= htmlspecialchars($name) ?>" style="max-width: 260px;">
                                <button type="button" class="btn btn-success btn-sm" data-save="name">
                                    <i class="bi bi-check-lg me-1"></i>Guardar
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" data-cancel>
                                    Cancelar
                                </button>
                            </div>
                            <div class="invalid-feedback d-block d-none" id="errorName"></div>
                        </div>
                    </div>

                    <div class="field-row" data-field="email">
                        <div class="read-state d-flex align-items-center py-3 px-3">
                            <div class="fs-5 text-primary pe-3"><i class="bi bi-envelope"></i></div>
                            <div class="flex-grow-1">
                                <div class="text-muted small">Correo electrónico</div>
                                <div class="fw-medium" id="valueEmail"><?= htmlspecialchars($email) ?></div>
                            </div>
                            <div>
                                <button type="button" class="btn btn-outline-primary btn-sm" data-edit="email">
                                    <i class="bi bi-pencil me-1"></i>Editar
                                </button>
                            </div>
                        </div>
                        <div class="edit-state d-none py-3 px-3">
                            <div class="text-muted small mb-2">Correo electrónico</div>
                            <div class="d-flex flex-wrap gap-2 align-items-start">
                                <input type="email" class="form-control" id="editEmail" maxlength="255"
                                       value="<?= htmlspecialchars($email) ?>" style="max-width: 300px;">
                                <button type="button" class="btn btn-success btn-sm" data-save="email">
                                    <i class="bi bi-check-lg me-1"></i>Guardar
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" data-cancel>
                                    Cancelar
                                </button>
                            </div>
                            <div class="invalid-feedback d-block d-none" id="errorEmail"></div>
                        </div>
                    </div>

                    <div class="field-row" data-field="phone">
                        <div class="read-state d-flex align-items-center py-3 px-3">
                            <div class="fs-5 text-primary pe-3"><i class="bi bi-telephone"></i></div>
                            <div class="flex-grow-1">
                                <div class="text-muted small">Teléfono</div>
                                <div class="fw-medium" id="valuePhone">
                                    <?= htmlspecialchars($phone) ?: '<span class="text-muted fw-normal">No registrado</span>' ?>
                                </div>
                            </div>
                            <div>
                                <button type="button" class="btn btn-outline-primary btn-sm" data-edit="phone">
                                    <i class="bi bi-pencil me-1"></i>Editar
                                </button>
                            </div>
                        </div>
                        <div class="edit-state d-none py-3 px-3">
                            <div class="text-muted small mb-2">Teléfono</div>
                            <div class="d-flex flex-wrap gap-2 align-items-start">
                                <input type="text" class="form-control" id="editPhone" maxlength="10" inputmode="numeric"
                                       value="<?= htmlspecialchars($phone) ?>" style="max-width: 260px;">
                                <button type="button" class="btn btn-success btn-sm" data-save="phone">
                                    <i class="bi bi-check-lg me-1"></i>Guardar
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" data-cancel>
                                    Cancelar
                                </button>
                            </div>
                            <div class="invalid-feedback d-block d-none" id="errorPhone"></div>
                        </div>
                    </div>

                    <div class="field-row" data-field="password">
                        <div class="read-state d-flex align-items-center py-3 px-3">
                            <div class="fs-5 text-primary pe-3"><i class="bi bi-shield-lock"></i></div>
                            <div class="flex-grow-1">
                                <div class="text-muted small">Contraseña</div>
                                <div class="fw-medium">••••••••••</div>
                            </div>
                            <div>
                                <button type="button" class="btn btn-warning btn-sm" data-edit="password">
                                    <i class="bi bi-key me-1"></i>Cambiar contraseña
                                </button>
                            </div>
                        </div>
                        <div class="edit-state d-none py-3 px-3">
                            <div class="text-muted small mb-2">Cambiar contraseña</div>
                            <div class="mb-2" style="max-width: 360px;">
                                <label for="editPwCurrent" class="form-label small mb-1">Contraseña actual <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" id="editPwCurrent" autocomplete="current-password">
                            </div>
                            <div class="mb-2" style="max-width: 360px;">
                                <label for="editPwNew" class="form-label small mb-1">Nueva contraseña <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" id="editPwNew" autocomplete="new-password">
                            </div>
                            <div class="mb-3" style="max-width: 360px;">
                                <label for="editPwConfirm" class="form-label small mb-1">Confirmar contraseña <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" id="editPwConfirm" autocomplete="new-password">
                            </div>
                            <div class="d-flex flex-wrap gap-2 align-items-start">
                                <button type="button" class="btn btn-success btn-sm" data-save="password">
                                    <i class="bi bi-check-lg me-1"></i>Guardar
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" data-cancel>
                                    Cancelar
                                </button>
                            </div>
                            <div class="invalid-feedback d-block d-none" id="errorPassword"></div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();

if (($userRole ?? 'employee') === 'admin') {
    require $sharedViewsPath . '/Layouts/AdminLayout.php';
} else {
    require $sharedViewsPath . '/Layouts/EmployeeLayout.php';
}

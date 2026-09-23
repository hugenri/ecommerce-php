<div class="card">
    <div class="card-body">
        <div id="profileAlert" class="form-alert" role="alert" hidden></div>

        <h5 class="card-title"><i class="bi bi-person"></i> Mi perfil</h5>
        <hr>

        <!-- Nombre completo -->
        <div class="d-flex justify-content-between align-items-start py-2">
            <div>
                <label class="form-label mb-1 text-muted small text-uppercase">Nombre completo</label>
                <div class="fs-5 fw-semibold" id="displayFullName">
                    <?= htmlspecialchars(trim(($customer['first_name'] ?? '') . ' ' . ($customer['last_name_paternal'] ?? '') . ' ' . ($customer['last_name_maternal'] ?? ''))) ?>
                </div>
            </div>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#nameModal">
                <i class="bi bi-pencil me-1"></i> Editar
            </button>
        </div>
        <hr>

        <!-- Teléfono -->
        <div class="d-flex justify-content-between align-items-start py-2">
            <div>
                <label class="form-label mb-1 text-muted small text-uppercase">Teléfono</label>
                <div class="fs-5 fw-semibold" id="displayPhone">
                    <?= htmlspecialchars($customer['phone'] ?? '') ?: '—' ?>
                </div>
            </div>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#phoneModal">
                <i class="bi bi-pencil me-1"></i> Editar
            </button>
        </div>
        <hr>

        <!-- Correo electrónico -->
        <div class="py-2">
            <label class="form-label mb-1 text-muted small text-uppercase">Correo electrónico</label>
            <div class="fs-5 fw-semibold"><?= htmlspecialchars($customer['email'] ?? '') ?></div>
            <small class="text-muted">No puedes modificar el correo electrónico.</small>
        </div>
        <hr>

        <!-- Seguridad -->
        <div class="fw-semibold text-muted small text-uppercase mb-1"><i class="bi bi-shield-lock me-1"></i> Seguridad</div>
        <div class="d-flex justify-content-between align-items-start py-2">
            <div>
                <label class="form-label mb-1 text-muted small text-uppercase">Contraseña</label>
                <div class="fs-5 fw-semibold">••••••••••••</div>
            </div>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#passwordModal">
                <i class="bi bi-key me-1"></i> Cambiar contraseña
            </button>
        </div>
        <hr>
        <div class="row text-muted small mb-3">
            <div class="col-md-6">Registrado: <?= htmlspecialchars($customer['created_at'] ?? '—') ?></div>
            <div class="col-md-6 text-md-end">Último acceso: <?= htmlspecialchars($customer['last_login'] ?? '—') ?></div>
        </div>
        
    </div>
</div>

<!-- Modal: Cambiar contraseña -->
<div class="modal fade" id="passwordModal" tabindex="-1" aria-labelledby="passwordModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="passwordModalTitle"><i class="bi bi-shield-lock me-1"></i> Cambiar contraseña</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div id="passwordAlert" class="form-alert" role="alert" hidden></div>
                <form id="passwordForm" method="POST" action="/account/password" class="row g-3" data-validate novalidate>
                    <div class="col-12">
                        <label class="form-label" for="current_password">Contraseña actual *</label>
                        <input type="password" class="form-control" id="current_password" name="current_password"
                               required aria-describedby="current_password-feedback" autofocus>
                        <div id="current_password-feedback" class="field-feedback" role="alert" hidden
                             data-mensaje="Este campo es obligatorio."></div>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="new_password">Nueva contraseña *</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" required
                               pattern="^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^\w\d\s])[A-Za-z\d\W\S]{8,16}"
                               aria-describedby="new_password-feedback">
                        <div id="new_password-feedback" class="field-feedback" role="alert" hidden
                             data-mensaje="La contraseña no es válida."></div>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="confirm_password">Confirmar contraseña *</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required
                               pattern="^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^\w\d\s])[A-Za-z\d\W\S]{8,16}"
                               aria-describedby="confirm_password-feedback">
                        <div id="confirm_password-feedback" class="field-feedback" role="alert" hidden
                             data-mensaje="Las contraseñas no coinciden o no son válidas."></div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" form="passwordForm" class="btn btn-primary"><i class="bi bi-shield-lock me-1"></i> Actualizar contraseña</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Editar nombre completo -->
<div class="modal fade" id="nameModal" tabindex="-1" aria-labelledby="nameModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="nameModalTitle"><i class="bi bi-person-lines-fill me-1"></i> Editar nombre</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div id="nameFormAlert" class="form-alert" role="alert" hidden></div>
                <form id="nameForm" method="POST" action="/account/profile/name" class="row g-3" data-validate novalidate>
                    <div class="col-12">
                        <label class="form-label" for="nameFirstName">Nombre *</label>
                        <input type="text" class="form-control" id="nameFirstName" name="first_name" maxlength="100"
                               value="<?= htmlspecialchars($customer['first_name'] ?? '') ?>" required
                               aria-describedby="nameFirstName-feedback">
                        <div id="nameFirstName-feedback" class="field-feedback" role="alert" hidden
                             data-mensaje="Este campo es obligatorio."></div>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="nameLastNamePaternal">Apellido paterno *</label>
                        <input type="text" class="form-control" id="nameLastNamePaternal" name="last_name_paternal" maxlength="100"
                               value="<?= htmlspecialchars($customer['last_name_paternal'] ?? '') ?>" required
                               aria-describedby="nameLastNamePaternal-feedback">
                        <div id="nameLastNamePaternal-feedback" class="field-feedback" role="alert" hidden
                             data-mensaje="Este campo es obligatorio."></div>
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="nameLastNameMaternal">Apellido materno</label>
                        <input type="text" class="form-control" id="nameLastNameMaternal" name="last_name_maternal" maxlength="100"
                               value="<?= htmlspecialchars($customer['last_name_maternal'] ?? '') ?>"
                               aria-describedby="nameLastNameMaternal-feedback">
                        <div id="nameLastNameMaternal-feedback" class="field-feedback" role="alert" hidden
                             data-mensaje="El apellido materno no es válido."></div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" form="nameForm" class="btn btn-primary" id="btnSaveName">
                    <i class="bi bi-check-lg"></i> Guardar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Editar teléfono -->
<div class="modal fade" id="phoneModal" tabindex="-1" aria-labelledby="phoneModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="phoneModalTitle"><i class="bi bi-telephone me-1"></i> Editar teléfono</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <div id="phoneFormAlert" class="form-alert" role="alert" hidden></div>
                <form id="phoneForm" method="POST" action="/account/profile/phone" class="row g-3" data-validate novalidate>
                    <div class="col-12">
                        <label class="form-label" for="phoneInput">Teléfono</label>
                        <input type="text" class="form-control" id="phoneInput" name="phone" maxlength="20"
                               value="<?= htmlspecialchars($customer['phone'] ?? '') ?>" placeholder="Ej. 5512345678"
                               aria-describedby="phoneInput-feedback">
                        <div id="phoneInput-feedback" class="field-feedback" role="alert" hidden
                             data-mensaje="El teléfono no es válido."></div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" form="phoneForm" class="btn btn-primary" id="btnSavePhone">
                    <i class="bi bi-check-lg"></i> Guardar
                </button>
            </div>
        </div>
    </div>
</div>

<?php
$pageTitle = 'Admin - Gestion de Usuarios';
$pageActive = 'users';
$topbarTitle = 'Gestión de Usuarios';
$topbarActions = '<button class="btn btn-primary" id="btnCreateUser"><i class="bi bi-plus-lg me-1"></i> Nuevo Usuario</button>';
$viewCss = ['/public/css/action-menu.css?v=1', '/public/css/users.css?v=3'];
$viewJs = [
    '/public/js/users/api.js?v=9',
    '/public/js/shared/action-menu.js?v=2',
    '/public/js/users/ui.js?v=12',
    '/public/js/users/app.js?v=10',
];

ob_start();
?>
<div class="card shadow-sm mb-4">
    <div class="card-body py-3">
        <div class="row g-3 align-items-center">
            <div class="col-12 col-lg-4">
                <label class="form-label small text-muted" for="searchInput">Buscar</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control" id="searchInput" placeholder="Nombre, email o telefono...">
                    <button class="btn btn-outline-secondary" type="button" id="btnClearSearch">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <label class="form-label small text-muted" for="perPageSelect">Por página</label>
                <select class="form-select" id="perPageSelect">
                    <option value="5" selected>5</option>
                    <option value="10">10</option>
                    <option value="15">15</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                </select>
            </div>
            <div class="col-6 col-md-3 col-lg-auto ms-lg-auto d-flex gap-2 justify-content-end">
                <button class="btn btn-outline-primary" type="button" id="btnOpenFilters" data-bs-toggle="offcanvas" data-bs-target="#filterOffcanvas">
                    <i class="bi bi-funnel me-1"></i> Filtros
                    <span class="badge bg-primary rounded-pill d-none" id="filterBadge">0</span>
                </button>
                <div class="dropdown">
                    <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-download me-1"></i> Exportar
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <button type="button" class="dropdown-item" data-export="excel">
                                <i class="bi bi-file-earmark-excel me-2 text-success"></i> Excel (.xlsx)
                            </button>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
        <div class="d-flex flex-wrap mt-3 d-none" id="filterChips"></div>
    </div>
</div>

<div class="offcanvas offcanvas-end" tabindex="-1" id="filterOffcanvas" aria-labelledby="filterOffcanvasLabel">
    <div class="offcanvas-header border-bottom">
        <h5 class="offcanvas-title" id="filterOffcanvasLabel"><i class="bi bi-funnel me-1"></i> Filtros avanzados</h5>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
    </div>
    <div class="offcanvas-body">
        <div class="mb-3">
            <label class="form-label" for="filterRole">Rol</label>
            <select class="form-select" id="filterRole">
                <option value="">Todos</option>
                <option value="admin">Admin</option>
                <option value="employee">Empleado</option>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label" for="filterStatus">Estado</label>
            <select class="form-select" id="filterStatus">
                <option value="">Todos</option>
                <option value="1">Activos</option>
                <option value="0">Inactivos</option>
            </select>
        </div>
    </div>
    <div class="offcanvas-footer border-top p-3 d-flex gap-2">
        <button class="btn btn-outline-secondary flex-grow-1" type="button" id="btnClearFilters"><i class="bi bi-arrow-counterclockwise me-1"></i> Limpiar filtros</button>
        <button class="btn btn-primary flex-grow-1" type="button" id="btnApplyFilters"><i class="bi bi-check-lg me-1"></i> Aplicar filtros</button>
    </div>
</div>

<div class="card shadow-sm">
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th class="sortable" data-sort="user_id">ID <i class="bi bi-arrow-down-up"></i></th>
                    <th class="sortable" data-sort="name">Nombre <i class="bi bi-arrow-down-up"></i></th>
                    <th class="sortable" data-sort="email">Email <i class="bi bi-arrow-down-up"></i></th>
                    <th>Rol</th>
                    <th>Estado</th>
                    <th class="sortable" data-sort="created_at">Registro <i class="bi bi-arrow-down-up"></i></th>
                    <th class="text-center">Acciones</th>
                </tr>
            </thead>
            <tbody id="usersTableBody">
                <tr>
                    <td colspan="7" class="text-center py-4 text-muted">
                        <div class="spinner-border spinner-border-sm me-2" role="status"></div> Cargando usuarios...
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white d-flex justify-content-between align-items-center">
        <small class="text-muted" id="paginationInfo">Mostrando 0 de 0 usuarios</small>
        <nav>
            <ul class="pagination pagination-sm mb-0" id="paginationLinks"></ul>
        </nav>
    </div>
</div>

<!-- Modal: Crear / Editar Usuario -->
<div class="modal fade" id="userFormModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="userFormModalTitle">
                    <i class="bi bi-person-plus me-2"></i>Nuevo Usuario
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="/users" id="userForm" data-validate novalidate>
                <input type="hidden" id="formUserId" value="">
                <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="formName" class="form-label">Nombre <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" id="formName" name="name" required minlength="4" maxlength="30" pattern="^[A-Za-zÁÉÍÓÚáéíóúÑñ ]+$" aria-describedby="name-feedback">
                                <div id="name-feedback" class="field-feedback" role="alert" hidden data-mensaje="El nombre debe tener entre 4 y 30 caracteres y solo letras."></div>
                            </div>
                            <div class="col-md-6">
                                <label for="formEmail" class="form-label">Email <span class="text-danger">*</span></label>
                                <input type="email" class="form-control" id="formEmail" name="email" required maxlength="255" aria-describedby="email-feedback">
                                <div id="email-feedback" class="field-feedback" role="alert" hidden data-mensaje="El email es obligatorio."></div>
                            </div>
                            <div class="col-md-6" id="formPasswordGroup">
                                <label for="formPassword" class="form-label">Contraseña <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" id="formPassword" name="password" minlength="8" maxlength="16" pattern="^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^\w\d\s])[A-Za-z\d\W\S]{8,16}" required aria-describedby="password-feedback">
                                <div id="password-feedback" class="field-feedback" role="alert" hidden data-mensaje="La contraseña debe tener entre 8 y 16 caracteres, con mayúscula, minúscula, número y carácter especial."></div>
                            </div>
                            <div class="col-md-6" id="formPasswordConfirmGroup">
                                <label for="formPasswordConfirm" class="form-label">Confirmar contraseña <span class="text-danger">*</span></label>
                                <input type="password" class="form-control" id="formPasswordConfirm" name="password_confirmation" minlength="8" maxlength="16" pattern="^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^\w\d\s])[A-Za-z\d\W\S]{8,16}" required aria-describedby="password-confirmation-feedback">
                                <div id="password-confirmation-feedback" class="field-feedback" role="alert" hidden data-mensaje="La contraseña debe tener entre 8 y 16 caracteres, con mayúscula, minúscula, número y carácter especial."></div>
                            </div>
                            <div class="col-md-4">
                                <label for="formRole" class="form-label">Rol <span class="text-danger">*</span></label>
                                <select class="form-select" id="formRole" name="role" required aria-describedby="role-feedback">
                                    <option value="employee">Empleado</option>
                                    <option value="admin">Administrador</option>
                                </select>
                                <div id="role-feedback" class="field-feedback" role="alert" hidden data-mensaje="Seleccione un rol."></div>
                            </div>
                            <div class="col-md-4">
                                <label for="formPhone" class="form-label">Telefono</label>
                                <input type="text" class="form-control" id="formPhone" name="phone" pattern="^\d{10}$" minlength="10" inputmode="numeric" maxlength="10" aria-describedby="phone-feedback">
                                <div id="phone-feedback" class="field-feedback" role="alert" hidden data-mensaje="El teléfono debe tener exactamente 10 dígitos numéricos."></div>
                            </div>
                            <div class="col-md-4">
                                <label for="formIsActive" class="form-label">Estado</label>
                                <select class="form-select" id="formIsActive" name="is_active">
                                    <option value="1">Activo</option>
                                    <option value="0">Inactivo</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary" id="btnSaveUser">
                            <i class="bi bi-check-lg me-1"></i> Guardar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Ver Detalle Usuario -->
<div class="modal fade" id="userDetailModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-person me-2"></i>Detalle del Usuario
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="userDetailBody">
            </div>
        </div>
    </div>
</div>

<!-- Modal: Restablecer Contraseña -->
<div class="modal fade" id="resetPasswordModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-key me-2"></i>Restablecer Contraseña
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="" id="resetPasswordForm" data-validate novalidate>
                <input type="hidden" id="rsUserId" value="">
                <div class="modal-body">
                        <p class="text-muted mb-3">Restableciendo contraseña de: <strong id="rsUserName"></strong></p>
                        <div class="mb-3">
                            <label for="rsNewPassword" class="form-label">Nueva contraseña <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="rsNewPassword" name="new_password" required minlength="8" maxlength="16" pattern="^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^\w\d\s])[A-Za-z\d\W\S]{8,16}" aria-describedby="new-password-feedback">
                            <div id="new-password-feedback" class="field-feedback" role="alert" hidden data-mensaje="La contraseña debe tener entre 8 y 16 caracteres, con mayúscula, minúscula, número y carácter especial."></div>
                        </div>
                        <div class="mb-3">
                            <label for="rsConfirmPassword" class="form-label">Confirmar nueva contraseña <span class="text-danger">*</span></label>
                            <input type="password" class="form-control" id="rsConfirmPassword" name="new_password_confirmation" required minlength="8" maxlength="16" pattern="^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^\w\d\s])[A-Za-z\d\W\S]{8,16}" aria-describedby="new-password-confirmation-feedback">
                            <div id="new-password-confirmation-feedback" class="field-feedback" role="alert" hidden data-mensaje="La contraseña debe tener entre 8 y 16 caracteres, con mayúscula, minúscula, número y carácter especial."></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-warning" id="btnSaveResetPassword">
                            <i class="bi bi-check-lg me-1"></i> Restablecer
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Confirmar Eliminacion -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">
                    <i class="bi bi-exclamation-triangle me-1"></i> Confirmar
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <input type="hidden" id="deleteUserId" value="">
                <p class="mb-0">Estas seguro de eliminar al usuario</p>
                <p class="fw-bold mb-0" id="deleteUserName"></p>
            </div>
            <div class="modal-footer justify-content-center">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger btn-sm" id="btnConfirmDelete">
                    <i class="bi bi-trash me-1"></i> Eliminar
                </button>
            </div>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();

require $sharedViewsPath . '/Layouts/AdminLayout.php';
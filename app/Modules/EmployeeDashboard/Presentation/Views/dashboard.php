<?php
$pageTitle = 'Empleado - Dashboard';
$pageActive = 'employee-dashboard';
$topbarTitle = 'Mi Dashboard';
$viewJs = ['/public/js/employee-dashboard/app.js?v=2'];

ob_start();
?>
<div class="row g-3 mb-4">
    <div class="col-6 col-md-4 col-xl">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="rounded-circle bg-warning bg-opacity-25 p-3"><i class="bi bi-hourglass-split text-warning fs-4"></i></div>
                <div>
                    <div class="h3 mb-0 fw-bold" id="cardPending"><?= (int) $summary['pending'] ?></div>
                    <small class="text-muted">Entregas pendientes</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="rounded-circle bg-primary bg-opacity-25 p-3"><i class="bi bi-person-check text-primary fs-4"></i></div>
                <div>
                    <div class="h3 mb-0 fw-bold" id="cardAssigned"><?= (int) $summary['assigned'] ?></div>
                    <small class="text-muted">Entregas asignadas</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="rounded-circle bg-info bg-opacity-25 p-3"><i class="bi bi-box-seam text-info fs-4"></i></div>
                <div>
                    <div class="h3 mb-0 fw-bold" id="cardPreparing"><?= (int) $summary['preparing'] ?></div>
                    <small class="text-muted">En preparacion</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="rounded-circle bg-success bg-opacity-25 p-3"><i class="bi bi-send text-success fs-4"></i></div>
                <div>
                    <div class="h3 mb-0 fw-bold" id="cardShippedToday"><?= (int) $summary['shipped_today'] ?></div>
                    <small class="text-muted">Enviadas hoy</small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-xl">
        <div class="card shadow-sm border-0 h-100">
            <div class="card-body d-flex align-items-center gap-3">
                <div class="rounded-circle bg-secondary bg-opacity-25 p-3"><i class="bi bi-check2-circle text-secondary fs-4"></i></div>
                <div>
                    <div class="h3 mb-0 fw-bold" id="cardDeliveredToday"><?= (int) $summary['delivered_today'] ?></div>
                    <small class="text-muted">Completadas hoy</small>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h6 class="mb-0"><i class="bi bi-briefcase me-1"></i> Mi trabajo</h6>
        <div class="d-flex align-items-center gap-2">
            <button class="btn btn-sm btn-outline-primary" id="btnRefreshMine"><i class="bi bi-arrow-clockwise me-1"></i>Refrescar</button>
            <label class="form-label small text-muted mb-0">Por pagina</label>
            <select class="form-select form-select-sm" id="minePerPageSelect" style="width: auto;">
                <option value="5">5</option>
                <option value="10" selected>10</option>
                <option value="15">15</option>
                <option value="25">25</option>
                <option value="50">50</option>
            </select>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Folio</th>
                    <th>Cliente</th>
                    <th>Direccion</th>
                    <th>Estado</th>
                    <th>Fecha de compra</th>
                    <th style="width: 130px;">Acciones</th>
                </tr>
            </thead>
            <tbody id="myDeliveriesBody">
                <tr><td colspan="6" class="text-center text-muted py-4"><i class="bi bi-inbox me-2"></i>Cargando...</td></tr>
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white d-flex justify-content-between align-items-center">
        <small class="text-muted" id="minePaginationInfo">Mostrando 0 de 0</small>
        <nav>
            <ul class="pagination pagination-sm mb-0" id="minePagination"></ul>
        </nav>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h6 class="mb-0"><i class="bi bi-inbox me-1"></i> Pedidos pendientes</h6>
        <div class="d-flex align-items-center gap-2">
            <button class="btn btn-sm btn-outline-primary" id="btnRefreshPending"><i class="bi bi-arrow-clockwise me-1"></i>Refrescar</button>
            <label class="form-label small text-muted mb-0">Por pagina</label>
            <select class="form-select form-select-sm" id="pendingPerPageSelect" style="width: auto;">
                <option value="5">5</option>
                <option value="10" selected>10</option>
                <option value="15">15</option>
                <option value="25">25</option>
                <option value="50">50</option>
            </select>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th>Folio</th>
                    <th>Cliente</th>
                    <th>Direccion</th>
                    <th>Estado</th>
                    <th>Fecha de compra</th>
                    <th style="width: 160px;">Acciones</th>
                </tr>
            </thead>
            <tbody id="pendingBody">
                <tr><td colspan="6" class="text-center text-muted py-4"><i class="bi bi-inbox me-2"></i>Cargando...</td></tr>
            </tbody>
        </table>
    </div>
    <div class="card-footer bg-white d-flex justify-content-between align-items-center">
        <small class="text-muted" id="pendingPaginationInfo">Mostrando 0 de 0</small>
        <nav>
            <ul class="pagination pagination-sm mb-0" id="pendingPagination"></ul>
        </nav>
    </div>
</div>

<div class="modal fade" id="detailModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detalle de entrega</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="detailModalBody">
                <div class="text-center py-4"><i class="bi bi-hourglass-split me-2"></i>Cargando...</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();


require $sharedViewsPath . '/Layouts/EmployeeLayout.php';
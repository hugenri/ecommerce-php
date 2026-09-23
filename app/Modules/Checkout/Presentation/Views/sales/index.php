<?php
$pageTitle = 'Admin - Gestión de Ventas';
$pageActive = 'sales';
$topbarTitle = 'Gestión de Ventas';
$viewCss = ['/public/css/action-menu.css?v=1'];
$viewJs = ['/public/js/shared/action-menu.js?v=2', '/public/js/sales/app.js?v=5'];

ob_start();
?>
<div class="card shadow-sm mb-4">
    <div class="card-body py-3">
        <div class="row g-3 align-items-center">
            <div class="col-12 col-lg-4">
                <label class="form-label small text-muted" for="searchInput">Buscar</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control" id="searchInput" placeholder="Folio, cliente o email...">
                    <button class="btn btn-outline-secondary" type="button" id="btnClearSearch"><i class="bi bi-x-lg"></i></button>
                </div>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <label class="form-label small text-muted" for="perPageSelect">Por página</label>
                <select class="form-select" id="perPageSelect">
                    <option value="5">5</option>
                    <option value="10" selected>10</option>
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
            <label class="form-label" for="statusFilter">Estado de entrega</label>
            <select class="form-select" id="statusFilter">
                <option value="">Todos</option>
                <option value="pending">Pendiente</option>
                <option value="processing">En proceso</option>
                <option value="shipped">Enviado</option>
                <option value="delivered">Entregado</option>
                <option value="cancelled">Cancelado</option>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label" for="paymentFilter">Pago</label>
            <select class="form-select" id="paymentFilter">
                <option value="">Todos</option>
                <option value="pending">Pendiente</option>
                <option value="paid">Pagado</option>
                <option value="failed">Fallido</option>
                <option value="refunded">Reembolsado</option>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label" for="methodFilter">Método de pago</label>
            <select class="form-select" id="methodFilter">
                <option value="">Todos</option>
                <option value="cash">Efectivo</option>
                <option value="transfer">Transferencia</option>
                <option value="card">Tarjeta</option>
                <option value="paypal">PayPal</option>
                <option value="conekta">Conekta</option>
            </select>
        </div>
        <div class="row g-3">
            <div class="col-6">
                <label class="form-label" for="dateFrom">Fecha desde</label>
                <input type="date" class="form-control" id="dateFrom">
            </div>
            <div class="col-6">
                <label class="form-label" for="dateTo">Fecha hasta</label>
                <input type="date" class="form-control" id="dateTo">
            </div>
        </div>
    </div>
    <div class="offcanvas-footer border-top p-3 d-flex gap-2">
        <button class="btn btn-outline-secondary flex-grow-1" type="button" id="btnClearFilters"><i class="bi bi-arrow-counterclockwise me-1"></i> Limpiar filtros</button>
        <button class="btn btn-primary flex-grow-1" type="button" id="btnApplyFilters"><i class="bi bi-check-lg me-1"></i> Aplicar filtros</button>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0" id="mainTable">
                <thead class="table-light">
                    <tr>
                        <th data-sort="sale_code">Folio <i class="bi bi-arrow-down-up ms-1"></i></th>
                        <th data-sort="customer_name">Cliente</th>
                        <th data-sort="sale_date">Fecha</th>
                        <th data-sort="total" class="text-end">Total</th>
                        <th>Método de pago</th>
                        <th>Pago</th>
                        <th>Estado de entrega</th>
                        <th style="width: 120px;">Acciones</th>
                    </tr>
                </thead>
                <tbody id="tableBody">
                    <tr><td colspan="8" class="text-center text-muted py-4"><i class="bi bi-inbox me-2"></i>Cargando ventas...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<nav class="mt-3 d-flex justify-content-between align-items-center">
    <small class="text-muted" id="paginationInfo"></small>
    <ul class="pagination pagination-sm mb-0" id="pagination"></ul>
</nav>

<div class="modal fade" id="detailModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detalle de venta</h5>
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

<div class="modal fade" id="statusModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="statusModalTitle">Cambiar estado</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Nuevo estado</label>
                    <select class="form-select" id="statusSelect">
                        <option value="pending">Pendiente</option>
                        <option value="processing">En proceso</option>
                        <option value="shipped">Enviado</option>
                        <option value="delivered">Entregado</option>
                    </select>
                    <div id="status-feedback" class="field-feedback" role="alert" hidden
                        data-mensaje="Selecciona un estado válido."></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" id="btnConfirmStatus">Actualizar</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="confirmModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="confirmModalTitle">Confirmar</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="confirmModalBody">¿Estás seguro?</div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-danger" id="btnConfirmAction">Confirmar</button>
            </div>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();

require $sharedViewsPath . '/Layouts/AdminLayout.php';
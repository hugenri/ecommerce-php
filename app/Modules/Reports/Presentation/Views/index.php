<?php
$pageTitle = 'Admin - Reportes';
$pageActive = 'reports';
$topbarTitle = 'Reportes';
$viewCss = ['/public/css/reports.css?v=2'];
$viewJs = [
    '/public/js/reports/api.js?v=1',
    '/public/js/reports/ui.js?v=1',
    '/public/js/reports/app.js?v=5',
];

ob_start();
?>
<ul class="nav nav-tabs mb-4" id="reportsTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="sales-tab" data-bs-toggle="tab" data-bs-target="#salesPane" type="button" role="tab">
            <i class="bi bi-cart-check me-1"></i> Ventas
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="top-products-tab" data-bs-toggle="tab" data-bs-target="#topProductsPane" type="button" role="tab">
            <i class="bi bi-trophy me-1"></i> Productos mas vendidos
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="low-stock-tab" data-bs-toggle="tab" data-bs-target="#lowStockPane" type="button" role="tab">
            <i class="bi bi-exclamation-triangle me-1"></i> Stock bajo
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="movements-tab" data-bs-toggle="tab" data-bs-target="#movementsPane" type="button" role="tab">
            <i class="bi bi-clock-history me-1"></i> Movimientos
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="deliveries-tab" data-bs-toggle="tab" data-bs-target="#deliveriesPane" type="button" role="tab">
            <i class="bi bi-truck me-1"></i> Entregas
        </button>
    </li>
</ul>

<div class="card shadow-sm mb-4">
    <div class="card-body py-3">
        <div class="row g-3 align-items-center">
            <div class="col-6 col-md-3 col-lg-2">
                <label class="form-label small text-muted" for="reportsPerPageSelect">Por página</label>
                <select class="form-select" id="reportsPerPageSelect">
                    <option value="10" selected>10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                </select>
            </div>
            <div class="col-6 col-md-auto ms-lg-auto d-flex gap-2 justify-content-end">
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
        <div class="report-filter-group" data-filter-group="sales">
            <div class="mb-3">
                <label class="form-label" for="salesDateFrom">Fecha inicial</label>
                <input type="date" class="form-control" id="salesDateFrom">
            </div>
            <div class="mb-3">
                <label class="form-label" for="salesDateTo">Fecha final</label>
                <input type="date" class="form-control" id="salesDateTo">
            </div>
            <div class="mb-3">
                <label class="form-label" for="salesStatusSelect">Estado del pedido</label>
                <select class="form-select" id="salesStatusSelect">
                    <option value="">Todos</option>
                    <option value="pending">Pendiente</option>
                    <option value="processing">Procesando</option>
                    <option value="shipped">Enviado</option>
                    <option value="delivered">Entregado</option>
                    <option value="cancelled">Cancelado</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label" for="salesPaymentSelect">Estado del pago</label>
                <select class="form-select" id="salesPaymentSelect">
                    <option value="">Todos</option>
                    <option value="pending">Pendiente</option>
                    <option value="paid">Pagado</option>
                    <option value="failed">Fallido</option>
                    <option value="refunded">Reembolsado</option>
                </select>
            </div>
        </div>

        <div class="report-filter-group d-none" data-filter-group="topProducts">
            <div class="mb-3">
                <label class="form-label" for="topProductsDateFrom">Fecha inicial</label>
                <input type="date" class="form-control" id="topProductsDateFrom">
            </div>
            <div class="mb-3">
                <label class="form-label" for="topProductsDateTo">Fecha final</label>
                <input type="date" class="form-control" id="topProductsDateTo">
            </div>
        </div>

        <div class="report-filter-group d-none" data-filter-group="lowStock">
            <div class="mb-3">
                <label class="form-label" for="lowStockThreshold">Umbral mínimo</label>
                <input type="number" class="form-control" id="lowStockThreshold" value="10" min="0">
            </div>
        </div>

        <div class="report-filter-group d-none" data-filter-group="movements">
            <div class="mb-3">
                <label class="form-label" for="movementTypeSelect">Tipo</label>
                <select class="form-select" id="movementTypeSelect">
                    <option value="">Todos</option>
                    <option value="purchase">Entrada</option>
                    <option value="sale">Venta</option>
                    <option value="adjustment">Ajuste</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label" for="movementProductId">Producto ID</label>
                <input type="text" class="form-control" id="movementProductId" placeholder="Ej. 5">
            </div>
            <div class="mb-3">
                <label class="form-label" for="movementDateFrom">Desde</label>
                <input type="date" class="form-control" id="movementDateFrom">
            </div>
            <div class="mb-3">
                <label class="form-label" for="movementDateTo">Hasta</label>
                <input type="date" class="form-control" id="movementDateTo">
            </div>
        </div>

        <div class="report-filter-group d-none" data-filter-group="deliveries">
            <div class="mb-3">
                <label class="form-label" for="deliveryStatusSelect">Estado</label>
                <select class="form-select" id="deliveryStatusSelect">
                    <option value="">Todos</option>
                    <option value="pending">Pendiente</option>
                    <option value="preparing">En preparacion</option>
                    <option value="shipped">Enviada</option>
                    <option value="delivered">Entregada</option>
                    <option value="cancelled">Cancelada</option>
                </select>
            </div>
            <div class="mb-3">
                <label class="form-label" for="deliveryDateFrom">Fecha inicial</label>
                <input type="date" class="form-control" id="deliveryDateFrom">
            </div>
            <div class="mb-3">
                <label class="form-label" for="deliveryDateTo">Fecha final</label>
                <input type="date" class="form-control" id="deliveryDateTo">
            </div>
        </div>
    </div>
    <div class="offcanvas-footer border-top p-3 d-flex gap-2">
        <button class="btn btn-outline-secondary flex-grow-1" type="button" id="btnClearFilters"><i class="bi bi-arrow-counterclockwise me-1"></i> Limpiar filtros</button>
        <button class="btn btn-primary flex-grow-1" type="button" id="btnApplyFilters"><i class="bi bi-check-lg me-1"></i> Aplicar filtros</button>
    </div>
</div>

<div class="d-none">
    <select id="salesPerPageSelect">
        <option value="10" selected>10</option>
        <option value="25">25</option>
        <option value="50">50</option>
    </select>
    <select id="topProductsPerPageSelect">
        <option value="10" selected>10</option>
        <option value="25">25</option>
        <option value="50">50</option>
    </select>
    <select id="lowStockPerPageSelect">
        <option value="10" selected>10</option>
        <option value="25">25</option>
        <option value="50">50</option>
    </select>
    <select id="movementsPerPageSelect">
        <option value="10" selected>10</option>
        <option value="25">25</option>
        <option value="50">50</option>
    </select>
    <select id="deliveriesPerPageSelect">
        <option value="10" selected>10</option>
        <option value="25">25</option>
        <option value="50">50</option>
    </select>
</div>

<div class="tab-content" id="reportsTabsContent">

    <!-- ─── Ventas ─────────────────────────────── -->
    <div class="tab-pane fade show active" id="salesPane" role="tabpanel">
        <div class="row g-3 mb-4" id="salesSummaryCards">
            <div class="col-6 col-md-3">
                <div class="card summary-card shadow-sm">
                    <div class="card-body py-3">
                        <div class="summary-label">Ventas totales</div>
                        <div class="summary-value" id="salesTotalCount">0</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card summary-card shadow-sm">
                    <div class="card-body py-3">
                        <div class="summary-label">Total vendido</div>
                        <div class="summary-value" id="salesTotalAmount">$0.00</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Folio</th>
                            <th>Fecha</th>
                            <th>Cliente</th>
                            <th>Metodo de pago</th>
                            <th>Estado del pedido</th>
                            <th>Estado del pago</th>
                            <th class="text-end">Total</th>
                        </tr>
                    </thead>
                    <tbody id="salesTableBody">
                        <tr>
                            <td colspan="7" class="text-center py-4 text-muted">
                                <div class="spinner-border spinner-border-sm me-2" role="status"></div> Cargando reporte de ventas...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white d-flex justify-content-between align-items-center">
                <small class="text-muted" id="salesPaginationInfo">Mostrando 0 de 0 ventas</small>
                <nav>
                    <ul class="pagination pagination-sm mb-0" id="salesPaginationLinks"></ul>
                </nav>
            </div>
        </div>
    </div>

    <!-- ─── Productos mas vendidos ─────────────── -->
    <div class="tab-pane fade" id="topProductsPane" role="tabpanel">
        <div class="card shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Codigo</th>
                            <th>Producto</th>
                            <th class="text-center">Cantidad vendida</th>
                            <th class="text-end">Total vendido</th>
                        </tr>
                    </thead>
                    <tbody id="topProductsTableBody">
                        <tr>
                            <td colspan="4" class="text-center py-4 text-muted">
                                <div class="spinner-border spinner-border-sm me-2" role="status"></div> Cargando productos mas vendidos...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white d-flex justify-content-between align-items-center">
                <small class="text-muted" id="topProductsPaginationInfo">Mostrando 0 de 0 productos</small>
                <nav>
                    <ul class="pagination pagination-sm mb-0" id="topProductsPaginationLinks"></ul>
                </nav>
            </div>
        </div>
    </div>

    <!-- ─── Stock bajo ─────────────────────────── -->
    <div class="tab-pane fade" id="lowStockPane" role="tabpanel">
        <div class="card shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Codigo</th>
                            <th>Producto</th>
                            <th>Categoría</th>
                            <th class="text-center">Stock actual</th>
                            <th>Ultimo movimiento</th>
                        </tr>
                    </thead>
                    <tbody id="lowStockTableBody">
                        <tr>
                            <td colspan="5" class="text-center py-4 text-muted">
                                <div class="spinner-border spinner-border-sm me-2" role="status"></div> Cargando reporte de stock bajo...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white d-flex justify-content-between align-items-center">
                <small class="text-muted" id="lowStockPaginationInfo">Mostrando 0 de 0 productos</small>
                <nav>
                    <ul class="pagination pagination-sm mb-0" id="lowStockPaginationLinks"></ul>
                </nav>
            </div>
        </div>
    </div>

    <!-- ─── Movimientos de inventario ──────────── -->
    <div class="tab-pane fade" id="movementsPane" role="tabpanel">
        <div class="card shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Fecha</th>
                            <th>Producto</th>
                            <th>Tipo</th>
                            <th class="text-center">Cantidad</th>
                            <th class="text-center">Stock anterior</th>
                            <th class="text-center">Stock actual</th>
                            <th>Motivo</th>
                            <th>Usuario</th>
                        </tr>
                    </thead>
                    <tbody id="movementsTableBody">
                        <tr>
                            <td colspan="8" class="text-center py-4 text-muted">
                                <div class="spinner-border spinner-border-sm me-2" role="status"></div> Cargando movimientos de inventario...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white d-flex justify-content-between align-items-center">
                <small class="text-muted" id="movementsPaginationInfo">Mostrando 0 de 0 movimientos</small>
                <nav>
                    <ul class="pagination pagination-sm mb-0" id="movementsPaginationLinks"></ul>
                </nav>
            </div>
        </div>
    </div>

    <!-- ─── Entregas ───────────────────────────── -->
    <div class="tab-pane fade" id="deliveriesPane" role="tabpanel">
        <div class="row g-3 mb-4" id="deliveriesSummaryCards">
            <div class="col-6 col-md-2">
                <div class="card summary-card shadow-sm">
                    <div class="card-body py-3">
                        <div class="summary-label">Pendientes</div>
                        <div class="summary-value summary-value-warning" id="deliverySummaryPending">0</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="card summary-card shadow-sm">
                    <div class="card-body py-3">
                        <div class="summary-label">En proceso</div>
                        <div class="summary-value summary-value-info" id="deliverySummaryPreparing">0</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="card summary-card shadow-sm">
                    <div class="card-body py-3">
                        <div class="summary-label">Enviadas</div>
                        <div class="summary-value summary-value-primary" id="deliverySummaryShipped">0</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="card summary-card shadow-sm">
                    <div class="card-body py-3">
                        <div class="summary-label">Entregadas</div>
                        <div class="summary-value summary-value-success" id="deliverySummaryDelivered">0</div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="card summary-card shadow-sm">
                    <div class="card-body py-3">
                        <div class="summary-label">Canceladas</div>
                        <div class="summary-value summary-value-danger" id="deliverySummaryCancelled">0</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Pedido</th>
                            <th>Cliente</th>
                            <th>Empleado</th>
                            <th>Estado</th>
                            <th>Fecha de envio</th>
                            <th>Fecha de entrega</th>
                        </tr>
                    </thead>
                    <tbody id="deliveriesTableBody">
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">
                                <div class="spinner-border spinner-border-sm me-2" role="status"></div> Cargando reporte de entregas...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white d-flex justify-content-between align-items-center">
                <small class="text-muted" id="deliveriesPaginationInfo">Mostrando 0 de 0 entregas</small>
                <nav>
                    <ul class="pagination pagination-sm mb-0" id="deliveriesPaginationLinks"></ul>
                </nav>
            </div>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();

require $sharedViewsPath . '/Layouts/AdminLayout.php';
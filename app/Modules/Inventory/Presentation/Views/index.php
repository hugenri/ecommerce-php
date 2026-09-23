<?php
$pageTitle = 'Admin - Inventario';
$pageActive = 'inventory';
$topbarTitle = 'Gestión de Inventario';
$topbarActions = '<div class="d-flex gap-2"><button class="btn btn-primary" id="btnAddStock"><i class="bi bi-plus-lg me-1"></i> Agregar stock</button><button class="btn btn-warning" id="btnAdjustStock"><i class="bi bi-sliders me-1"></i> Ajustar inventario</button></div>';
$viewCss = ['/public/css/action-menu.css?v=1', '/public/css/inventory.css?v=2'];
$viewJs = [
    '/public/js/shared/action-menu.js?v=2',
    '/public/js/inventory/api.js?v=5',
    '/public/js/inventory/ui.js?v=5',
    '/public/js/inventory/app.js?v=9',
];

ob_start();
?>
<ul class="nav nav-tabs mb-4" id="inventoryTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="stock-tab" data-bs-toggle="tab" data-bs-target="#stockPane" type="button" role="tab">
            <i class="bi bi-boxes me-1"></i> Existencias
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="movements-tab" data-bs-toggle="tab" data-bs-target="#movementsPane" type="button" role="tab">
            <i class="bi bi-clock-history me-1"></i> Movimientos
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="lowstock-tab" data-bs-toggle="tab" data-bs-target="#lowstockPane" type="button" role="tab">
            <i class="bi bi-exclamation-triangle me-1"></i> Stock Bajo
        </button>
    </li>
</ul>

<div class="card shadow-sm mb-4">
    <div class="card-body py-3">
        <div class="row g-3 align-items-center">
            <div id="stockSearchGroup" class="col-12 col-lg-4">
                <label class="form-label small text-muted" for="stockSearchInput">Buscar producto</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-search"></i></span>
                    <input type="text" class="form-control" id="stockSearchInput" placeholder="Nombre o codigo...">
                    <button class="btn btn-outline-secondary" type="button" id="btnClearStockSearch"><i class="bi bi-x-lg"></i></button>
                </div>
            </div>
            <div class="col-6 col-md-3 col-lg-2">
                <label class="form-label small text-muted" for="inventoryPerPageSelect">Por página</label>
                <select class="form-select" id="inventoryPerPageSelect">
                    <option value="5">5</option>
                    <option value="10" selected>10</option>
                    <option value="15">15</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                    <option value="100">100</option>
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
        <div class="report-filter-group" data-filter-group="stock">
            <div class="alert alert-light border text-muted small mb-0">
                <i class="bi bi-info-circle me-1"></i> Este tab no tiene filtros adicionales. Usa la búsqueda de producto en la barra superior.
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
                <label class="form-label" for="movementUserId">Usuario ID</label>
                <input type="text" class="form-control" id="movementUserId" placeholder="Ej. 1">
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

        <div class="report-filter-group d-none" data-filter-group="lowstock">
            <div class="mb-3">
                <label class="form-label" for="lowStockThreshold">Umbral mínimo</label>
                <input type="number" class="form-control" id="lowStockThreshold" value="10" min="0">
            </div>
        </div>
    </div>
    <div class="offcanvas-footer border-top p-3 d-flex gap-2">
        <button class="btn btn-outline-secondary flex-grow-1" type="button" id="btnClearFilters"><i class="bi bi-arrow-counterclockwise me-1"></i> Limpiar filtros</button>
        <button class="btn btn-primary flex-grow-1" type="button" id="btnApplyFilters"><i class="bi bi-check-lg me-1"></i> Aplicar filtros</button>
    </div>
</div>

<div class="d-none">
    <select id="stockPerPageSelect">
        <option value="10" selected>10</option>
        <option value="25">25</option>
        <option value="50">50</option>
        <option value="100">100</option>
    </select>
    <select id="movementsPerPageSelect">
        <option value="5">5</option>
        <option value="10" selected>10</option>
        <option value="15">15</option>
        <option value="25">25</option>
        <option value="50">50</option>
    </select>
    <select id="lowStockPerPageSelect">
        <option value="10" selected>10</option>
        <option value="25">25</option>
        <option value="50">50</option>
    </select>
</div>

<div class="tab-content" id="inventoryTabsContent">

    <!-- ─── Existencias ─────────────────────────── -->
    <div class="tab-pane fade show active" id="stockPane" role="tabpanel">
        <div class="card shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="sortable" data-sort="product_code">Codigo <i class="bi bi-arrow-down-up"></i></th>
                            <th class="sortable" data-sort="name">Producto <i class="bi bi-arrow-down-up"></i></th>
                            <th class="sortable text-center" data-sort="stock">Stock <i class="bi bi-arrow-down-up"></i></th>
                            <th>Estado</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="stockTableBody">
                        <tr>
                            <td colspan="5" class="text-center py-4 text-muted">
                                <div class="spinner-border spinner-border-sm me-2" role="status"></div> Cargando inventario...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="card-footer bg-white d-flex justify-content-between align-items-center">
                <small class="text-muted" id="stockPaginationInfo">Mostrando 0 de 0 productos</small>
                <nav>
                    <ul class="pagination pagination-sm mb-0" id="stockPaginationLinks"></ul>
                </nav>
            </div>
        </div>
    </div>

    <!-- ─── Movimientos ─────────────────────────── -->
    <div class="tab-pane fade" id="movementsPane" role="tabpanel">
        <div class="card shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Fecha</th>
                            <th>Producto</th>
                            <th>Tipo</th>
                            <th class="text-center">Cantidad</th>
                            <th class="text-center">Antes</th>
                            <th class="text-center">Despues</th>
                            <th>Usuario</th>
                            <th>Motivo</th>
                        </tr>
                    </thead>
                    <tbody id="movementsTableBody">
                        <tr>
                            <td colspan="9" class="text-center py-4 text-muted">
                                <div class="spinner-border spinner-border-sm me-2" role="status"></div> Cargando movimientos...
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

    <!-- ─── Stock Bajo ──────────────────────────── -->
    <div class="tab-pane fade" id="lowstockPane" role="tabpanel">
        <div class="card shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Codigo</th>
                            <th>Producto</th>
                            <th>Categoría</th>
                            <th class="text-center">Stock actual</th>
                            <th>Último movimiento</th>
                            <th class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="lowStockTableBody">
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">
                                <div class="spinner-border spinner-border-sm me-2" role="status"></div> Cargando reporte...
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
</div>

<!-- Modal: Agregar stock -->
<div class="modal fade" id="addStockModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-plus-circle me-2"></i>Agregar stock
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="/inventory/add-stock" id="addStockForm" data-validate novalidate>
                <input type="hidden" id="addProductId" value="">
                <div class="modal-body">
                        <div class="mb-3">
                            <label for="addProductSearch" class="form-label">Producto <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="addProductSearch" name="product_id" placeholder="Buscar por nombre o codigo..." autocomplete="off" required aria-describedby="add-product-feedback">
                            <div id="addProductSearchList" class="mt-1"></div>
                            <div class="form-text" id="addProductSelected">Selecciona un producto para continuar.</div>
                            <div id="add-product-feedback" class="field-feedback" role="alert" hidden data-mensaje="Debe seleccionar un producto."></div>
                        </div>
                        <div class="mb-3">
                            <label for="addQuantity" class="form-label">Cantidad <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="addQuantity" name="quantity" step="1" min="1" required aria-describedby="add-quantity-feedback">
                            <div id="add-quantity-feedback" class="field-feedback" role="alert" hidden data-mensaje="La cantidad es obligatoria."></div>
                        </div>
                        <div class="mb-3">
                            <label for="addReason" class="form-label">Motivo (opcional)</label>
                            <input type="text" class="form-control" id="addReason" name="reason" maxlength="255" aria-describedby="add-reason-feedback">
                            <div id="add-reason-feedback" class="field-feedback" role="alert" hidden></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary" id="btnSaveAddStock">
                            <i class="bi bi-check-lg me-1"></i> Agregar stock
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Ajuste de Stock -->
<div class="modal fade" id="adjustStockModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-sliders me-2"></i>Ajustar inventario
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="/inventory/adjust" id="adjustStockForm" data-validate novalidate>
                <input type="hidden" id="adjustProductId" value="">
                <div class="modal-body">
                        <div class="mb-3">
                            <label for="adjustProductSearch" class="form-label">Producto <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="adjustProductSearch" name="product_id" placeholder="Buscar por nombre o codigo..." autocomplete="off" required aria-describedby="adjust-product-feedback">
                            <div id="adjustProductSearchList" class="mt-1"></div>
                            <div class="form-text" id="adjustProductSelected">Selecciona un producto para continuar.</div>
                            <div id="adjust-product-feedback" class="field-feedback" role="alert" hidden data-mensaje="Debe seleccionar un producto."></div>
                        </div>
                        <div class="mb-3">
                            <label for="adjustStockValue" class="form-label">Stock físico contado <span class="text-danger">*</span></label>
                            <input type="number" class="form-control" id="adjustStockValue" name="stock" step="1" min="0" required aria-describedby="adjust-stock-feedback">
                            <div id="adjust-stock-feedback" class="field-feedback" role="alert" hidden data-mensaje="El stock físico es obligatorio."></div>
                        </div>
                        <div class="mb-3">
                            <label for="adjustReason" class="form-label">Motivo del ajuste <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="adjustReason" name="reason" maxlength="255" required aria-describedby="adjust-reason-feedback">
                            <div id="adjust-reason-feedback" class="field-feedback" role="alert" hidden data-mensaje="El motivo del ajuste es obligatorio."></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-warning" id="btnSaveAdjustStock">
                            <i class="bi bi-check-lg me-1"></i> Ajustar inventario
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();

require $sharedViewsPath . '/Layouts/AdminLayout.php';
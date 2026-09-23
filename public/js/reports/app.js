document.addEventListener('DOMContentLoaded', () => {
    let salesPage = 1;
    let topProductsPage = 1;
    let lowStockPage = 1;
    let movementsPage = 1;
    let deliveriesPage = 1;

    const PER_PAGE_SELECTS = {
        sales: 'salesPerPageSelect',
        topProducts: 'topProductsPerPageSelect',
        lowStock: 'lowStockPerPageSelect',
        movements: 'movementsPerPageSelect',
        deliveries: 'deliveriesPerPageSelect',
    };

    const TAB_FILTERS = {
        sales: {
            date_from: 'salesDateFrom',
            date_to: 'salesDateTo',
            status: 'salesStatusSelect',
            payment_status: 'salesPaymentSelect',
        },
        topProducts: {
            date_from: 'topProductsDateFrom',
            date_to: 'topProductsDateTo',
        },
        lowStock: {
            threshold: 'lowStockThreshold',
        },
        movements: {
            movement_type: 'movementTypeSelect',
            product_id: 'movementProductId',
            date_from: 'movementDateFrom',
            date_to: 'movementDateTo',
        },
        deliveries: {
            status: 'deliveryStatusSelect',
            date_from: 'deliveryDateFrom',
            date_to: 'deliveryDateTo',
        },
    };

    const FILTER_CHIPS = {
        sales: {
            date_from: { label: 'Fecha inicial', inputId: 'salesDateFrom' },
            date_to: { label: 'Fecha final', inputId: 'salesDateTo' },
            status: { label: 'Estado del pedido', inputId: 'salesStatusSelect' },
            payment_status: { label: 'Estado del pago', inputId: 'salesPaymentSelect' },
        },
        topProducts: {
            date_from: { label: 'Fecha inicial', inputId: 'topProductsDateFrom' },
            date_to: { label: 'Fecha final', inputId: 'topProductsDateTo' },
        },
        lowStock: {
            threshold: { label: 'Umbral mínimo', inputId: 'lowStockThreshold' },
        },
        movements: {
            movement_type: { label: 'Tipo', inputId: 'movementTypeSelect' },
            product_id: { label: 'Producto ID', inputId: 'movementProductId' },
            date_from: { label: 'Desde', inputId: 'movementDateFrom' },
            date_to: { label: 'Hasta', inputId: 'movementDateTo' },
        },
        deliveries: {
            status: { label: 'Estado', inputId: 'deliveryStatusSelect' },
            date_from: { label: 'Fecha inicial', inputId: 'deliveryDateFrom' },
            date_to: { label: 'Fecha final', inputId: 'deliveryDateTo' },
        },
    };

    // ─── Ventas ─────────────────────────────────────────────

    const loadSales = async (page = 1) => {
        salesPage = page;
        ReportsUI.showLoading();

        const params = {
            page: page,
            per_page: document.getElementById('salesPerPageSelect').value,
            date_from: document.getElementById('salesDateFrom').value,
            date_to: document.getElementById('salesDateTo').value,
            status: document.getElementById('salesStatusSelect').value,
            payment_status: document.getElementById('salesPaymentSelect').value,
        };

        try {
            const { ok, json } = await ReportsAPI.getSales(params);
            if (ok && json.success) {
                ReportsUI.renderSalesTable(json.data || [], json.meta || {});
                ReportsUI.renderSalesSummary((json.meta || {}).summary || {});
            } else {
                ReportsUI.renderSalesTable([], {});
                ReportsUI.renderSalesSummary({});
                ReportsUI.showAlert('danger', json.message || 'Error al cargar el reporte de ventas.');
            }
        } catch (e) {
            ReportsUI.renderSalesTable([], {});
            ReportsUI.renderSalesSummary({});
            ReportsUI.showAlert('danger', 'Error de conexion con el servidor.');
        } finally {
            ReportsUI.hideLoading();
        }
    };

    // ─── Productos mas vendidos ─────────────────────────────

    const loadTopProducts = async (page = 1) => {
        topProductsPage = page;
        ReportsUI.showLoading();

        const params = {
            page: page,
            per_page: document.getElementById('topProductsPerPageSelect').value,
            date_from: document.getElementById('topProductsDateFrom').value,
            date_to: document.getElementById('topProductsDateTo').value,
        };

        try {
            const { ok, json } = await ReportsAPI.getTopProducts(params);
            if (ok && json.success) {
                ReportsUI.renderTopProductsTable(json.data || [], json.meta || {});
            } else {
                ReportsUI.renderTopProductsTable([], {});
                ReportsUI.showAlert('danger', json.message || 'Error al cargar los productos mas vendidos.');
            }
        } catch (e) {
            ReportsUI.renderTopProductsTable([], {});
            ReportsUI.showAlert('danger', 'Error de conexion con el servidor.');
        } finally {
            ReportsUI.hideLoading();
        }
    };

    // ─── Stock bajo ─────────────────────────────────────────

    const loadLowStock = async (page = 1) => {
        lowStockPage = page;
        ReportsUI.showLoading();

        const params = {
            page: page,
            per_page: document.getElementById('lowStockPerPageSelect').value,
            threshold: document.getElementById('lowStockThreshold').value || 10,
        };

        try {
            const { ok, json } = await ReportsAPI.getLowStock(params);
            if (ok && json.success) {
                ReportsUI.renderLowStockTable(json.data || [], json.meta || {});
            } else {
                ReportsUI.renderLowStockTable([], {});
                ReportsUI.showAlert('danger', json.message || 'Error al cargar el reporte de stock bajo.');
            }
        } catch (e) {
            ReportsUI.renderLowStockTable([], {});
            ReportsUI.showAlert('danger', 'Error de conexion con el servidor.');
        } finally {
            ReportsUI.hideLoading();
        }
    };

    // ─── Movimientos de inventario ──────────────────────────

    const loadMovements = async (page = 1) => {
        movementsPage = page;
        ReportsUI.showLoading();

        const params = {
            page: page,
            per_page: document.getElementById('movementsPerPageSelect').value,
            movement_type: document.getElementById('movementTypeSelect').value,
            product_id: document.getElementById('movementProductId').value.trim(),
            date_from: document.getElementById('movementDateFrom').value,
            date_to: document.getElementById('movementDateTo').value,
        };

        try {
            const { ok, json } = await ReportsAPI.getMovements(params);
            if (ok && json.success) {
                ReportsUI.renderMovementsTable(json.data || [], json.meta || {});
            } else {
                ReportsUI.renderMovementsTable([], {});
                ReportsUI.showAlert('danger', json.message || 'Error al cargar los movimientos.');
            }
        } catch (e) {
            ReportsUI.renderMovementsTable([], {});
            ReportsUI.showAlert('danger', 'Error de conexion con el servidor.');
        } finally {
            ReportsUI.hideLoading();
        }
    };

    // ─── Entregas ───────────────────────────────────────────

    const loadDeliveries = async (page = 1) => {
        deliveriesPage = page;
        ReportsUI.showLoading();

        const params = {
            page: page,
            per_page: document.getElementById('deliveriesPerPageSelect').value,
            status: document.getElementById('deliveryStatusSelect').value,
            date_from: document.getElementById('deliveryDateFrom').value,
            date_to: document.getElementById('deliveryDateTo').value,
        };

        try {
            const { ok, json } = await ReportsAPI.getDeliveries(params);
            if (ok && json.success) {
                ReportsUI.renderDeliveriesTable(json.data || [], json.meta || {});
                ReportsUI.renderDeliveriesSummary((json.meta || {}).summary || {});
            } else {
                ReportsUI.renderDeliveriesTable([], {});
                ReportsUI.renderDeliveriesSummary({});
                ReportsUI.showAlert('danger', json.message || 'Error al cargar el reporte de entregas.');
            }
        } catch (e) {
            ReportsUI.renderDeliveriesTable([], {});
            ReportsUI.renderDeliveriesSummary({});
            ReportsUI.showAlert('danger', 'Error de conexion con el servidor.');
        } finally {
            ReportsUI.hideLoading();
        }
    };

    // ─── Helpers de tab activo ──────────────────────────────

    const LOADERS = {
        sales: loadSales,
        topProducts: loadTopProducts,
        lowStock: loadLowStock,
        movements: loadMovements,
        deliveries: loadDeliveries,
    };

    const getActiveTabKey = () => {
        const pane = document.querySelector('#reportsTabsContent .tab-pane.active');
        if (!pane) return null;
        return pane.id.replace('Pane', '');
    };

    const switchFilterGroup = (key) => {
        document.querySelectorAll('.report-filter-group').forEach((group) => {
            group.classList.toggle('d-none', group.dataset.filterGroup !== key);
        });
    };

    const syncPerPageFromTab = (key) => {
        const bar = document.getElementById('reportsPerPageSelect');
        const perEl = document.getElementById(PER_PAGE_SELECTS[key]);
        if (bar && perEl) bar.value = perEl.value;
    };

    const buildFilters = (key) => {
        const filters = {};
        const mapping = TAB_FILTERS[key] || {};
        Object.keys(mapping).forEach((param) => {
            const input = document.getElementById(mapping[param]);
            if (!input) return;
            const value = param === 'product_id' ? input.value.trim() : input.value;
            if (value !== '') filters[param] = value;
        });
        return filters;
    };

    const renderFilterChips = () => {
        const key = getActiveTabKey();
        if (!key) return;
        FilterBar.renderChips({
            chipsId: 'filterChips',
            badgeId: 'filterBadge',
            chips: FilterBar.buildChips(buildFilters(key), FILTER_CHIPS[key] || {}),
            onRemove: () => refreshActiveTab(),
        });
    };

    const refreshActiveTab = () => {
        const key = getActiveTabKey();
        const loader = LOADERS[key];
        if (loader) loader(1);
        renderFilterChips();
    };

    const applyFilters = () => {
        FilterBar.closeOffcanvas('filterOffcanvas');
        refreshActiveTab();
    };

    const clearFilters = () => {
        const key = getActiveTabKey();
        if (!key) return;
        Object.keys(TAB_FILTERS[key]).forEach((param) => {
            const input = document.getElementById(TAB_FILTERS[key][param]);
            if (!input) return;
            input.value = param === 'threshold' ? '10' : '';
        });
        const perEl = document.getElementById(PER_PAGE_SELECTS[key]);
        if (perEl) perEl.value = '10';
        syncPerPageFromTab(key);
        FilterBar.closeOffcanvas('filterOffcanvas');
        refreshActiveTab();
    };

    // ─── Eventos de filtros ─────────────────────────────────

    document.getElementById('lowStockThreshold')?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') refreshActiveTab();
    });

    document.getElementById('reportsPerPageSelect')?.addEventListener('change', () => {
        const key = getActiveTabKey();
        if (!key) return;
        const perEl = document.getElementById(PER_PAGE_SELECTS[key]);
        if (perEl) perEl.value = document.getElementById('reportsPerPageSelect').value;
        refreshActiveTab();
    });

    document.getElementById('btnApplyFilters')?.addEventListener('click', applyFilters);
    document.getElementById('btnClearFilters')?.addEventListener('click', clearFilters);

    // ─── Paginación ─────────────────────────────────────────

    document.getElementById('salesPaginationLinks')?.addEventListener('click', (e) => {
        e.preventDefault();
        const link = e.target.closest('[data-page]');
        if (link && !link.closest('.disabled')) {
            loadSales(parseInt(link.dataset.page, 10));
        }
    });

    document.getElementById('topProductsPaginationLinks')?.addEventListener('click', (e) => {
        e.preventDefault();
        const link = e.target.closest('[data-page]');
        if (link && !link.closest('.disabled')) {
            loadTopProducts(parseInt(link.dataset.page, 10));
        }
    });

    document.getElementById('lowStockPaginationLinks')?.addEventListener('click', (e) => {
        e.preventDefault();
        const link = e.target.closest('[data-page]');
        if (link && !link.closest('.disabled')) {
            loadLowStock(parseInt(link.dataset.page, 10));
        }
    });

    document.getElementById('movementsPaginationLinks')?.addEventListener('click', (e) => {
        e.preventDefault();
        const link = e.target.closest('[data-page]');
        if (link && !link.closest('.disabled')) {
            loadMovements(parseInt(link.dataset.page, 10));
        }
    });

    document.getElementById('deliveriesPaginationLinks')?.addEventListener('click', (e) => {
        e.preventDefault();
        const link = e.target.closest('[data-page]');
        if (link && !link.closest('.disabled')) {
            loadDeliveries(parseInt(link.dataset.page, 10));
        }
    });

    // ─── Cambio de tab ──────────────────────────────────────

    document.getElementById('reportsTabs')?.addEventListener('shown.bs.tab', (e) => {
        const tab = e.target.closest('[data-bs-toggle="tab"]');
        if (!tab) return;
        const target = tab.getAttribute('data-bs-target');
        const map = {
            '#salesPane': 'sales',
            '#topProductsPane': 'topProducts',
            '#lowStockPane': 'lowStock',
            '#movementsPane': 'movements',
            '#deliveriesPane': 'deliveries',
        };
        const key = map[target];
        if (!key) return;
        syncPerPageFromTab(key);
        switchFilterGroup(key);
        const loader = LOADERS[key];
        if (loader) loader(1);
        renderFilterChips();
    });

    // ─── Inicializacion ─────────────────────────────────────

    syncPerPageFromTab('sales');
    renderFilterChips();
    loadSales(1);
});
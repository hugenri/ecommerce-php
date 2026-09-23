document.addEventListener('DOMContentLoaded', () => {
    const modals = {
        addStock: new bootstrap.Modal('#addStockModal'),
        adjustStock: new bootstrap.Modal('#adjustStockModal'),
    };

    const validarFormulario = (form) => {
        if (window.FormValidation && !window.FormValidation.formularioEsValido(form)) {
            form.querySelectorAll('input:not([type="hidden"]), select, textarea').forEach((input) => {
                if (input.required && !input.value) {
                    window.FormValidation.marcarError(input);
                } else if (input.value && !input.checkValidity()) {
                    window.FormValidation.marcarError(input);
                }
            });
            return false;
        }
        return true;
    };

    const recalcularEstadoBoton = (form) => {
        form.querySelectorAll('input:not([type="hidden"]), select, textarea').forEach((input) => {
            input.dispatchEvent(new Event('input', { bubbles: true }));
        });
    };

    const mostrarErroresBackend = (form, errors) => {
        if (window.FormValidation && errors && typeof errors === 'object'
            && !Array.isArray(errors) && Object.keys(errors).length > 0) {
            window.FormValidation.limpiarErrores(form);
            window.FormValidation.mostrarErrores(form, errors);
            return;
        }
        InventoryUI.showAlert('danger', 'No se pudo completar la operación.');
    };

    let stockPage = 1;
    let stockSort = 'name';
    let stockDir = 'ASC';
    let stockSearchTimeout = null;

    let movementsPage = 1;

    let lowStockPage = 1;

    const PER_PAGE_SELECTS = {
        stock: 'stockPerPageSelect',
        movements: 'movementsPerPageSelect',
        lowstock: 'lowStockPerPageSelect',
    };

    const TAB_FILTERS = {
        stock: {},
        movements: {
            movement_type: 'movementTypeSelect',
            product_id: 'movementProductId',
            user_id: 'movementUserId',
            date_from: 'movementDateFrom',
            date_to: 'movementDateTo',
        },
        lowstock: {
            threshold: 'lowStockThreshold',
        },
    };

    const FILTER_CHIPS = {
        stock: {},
        movements: {
            movement_type: { label: 'Tipo', inputId: 'movementTypeSelect' },
            product_id: { label: 'Producto ID', inputId: 'movementProductId' },
            user_id: { label: 'Usuario ID', inputId: 'movementUserId' },
            date_from: { label: 'Desde', inputId: 'movementDateFrom' },
            date_to: { label: 'Hasta', inputId: 'movementDateTo' },
        },
        lowstock: {
            threshold: { label: 'Umbral mínimo', inputId: 'lowStockThreshold' },
        },
    };

    // ─── Existencias ─────────────────────────────────────────

    const loadStock = async (page = 1) => {
        stockPage = page;
        InventoryUI.showLoading();

        const params = {
            page: page,
            per_page: document.getElementById('stockPerPageSelect').value,
            search: document.getElementById('stockSearchInput').value.trim(),
            sort_by: stockSort,
            sort_order: stockDir,
        };

        try {
            const { ok, json } = await InventoryAPI.getInventory(params);
            if (ok && json.success) {
                InventoryUI.renderStockTable(json.data || [], json.meta || {});
            } else {
                InventoryUI.renderStockTable([], {});
                InventoryUI.showAlert('danger', json.message || 'Error al cargar existencias.');
            }
        } catch (e) {
            InventoryUI.renderStockTable([], {});
            InventoryUI.showAlert('danger', 'Error de conexion con el servidor.');
        } finally {
            InventoryUI.hideLoading();
        }
    };

    // ─── Movimientos ─────────────────────────────────────────

    const loadMovements = async (page = 1) => {
        movementsPage = page;
        InventoryUI.showLoading();

        const params = {
            page: page,
            per_page: document.getElementById('movementsPerPageSelect').value,
            movement_type: document.getElementById('movementTypeSelect').value,
            product_id: document.getElementById('movementProductId').value.trim(),
            user_id: document.getElementById('movementUserId').value.trim(),
            date_from: document.getElementById('movementDateFrom').value,
            date_to: document.getElementById('movementDateTo').value,
        };

        try {
            const { ok, json } = await InventoryAPI.getMovements(params);
            if (ok && json.success) {
                InventoryUI.renderMovementsTable(json.data || [], json.meta || {});
            } else {
                InventoryUI.renderMovementsTable([], {});
                InventoryUI.showAlert('danger', json.message || 'Error al cargar movimientos.');
            }
        } catch (e) {
            InventoryUI.renderMovementsTable([], {});
            InventoryUI.showAlert('danger', 'Error de conexion con el servidor.');
        } finally {
            InventoryUI.hideLoading();
        }
    };

    // ─── Stock Bajo ──────────────────────────────────────────

    const loadLowStock = async (page = 1) => {
        lowStockPage = page;
        InventoryUI.showLoading();

        const params = {
            page: page,
            per_page: document.getElementById('lowStockPerPageSelect').value,
            threshold: document.getElementById('lowStockThreshold').value || 10,
        };

        try {
            const { ok, json } = await InventoryAPI.getLowStock(params);
            if (ok && json.success) {
                InventoryUI.renderLowStockTable(json.data || [], json.meta || {});
            } else {
                InventoryUI.renderLowStockTable([], {});
                InventoryUI.showAlert('danger', json.message || 'Error al cargar el reporte.');
            }
        } catch (e) {
            InventoryUI.renderLowStockTable([], {});
            InventoryUI.showAlert('danger', 'Error de conexion con el servidor.');
        } finally {
            InventoryUI.hideLoading();
        }
    };

    // ─── Helpers de tab activo ──────────────────────────────

    const LOADERS = {
        stock: loadStock,
        movements: loadMovements,
        lowstock: loadLowStock,
    };

    const getActiveTabKey = () => {
        const pane = document.querySelector('#inventoryTabsContent .tab-pane.active');
        if (!pane) return null;
        return pane.id.replace('Pane', '');
    };

    const switchFilterGroup = (key) => {
        document.querySelectorAll('.report-filter-group').forEach((group) => {
            group.classList.toggle('d-none', group.dataset.filterGroup !== key);
        });
    };

    const setStockSearchVisible = (visible) => {
        const group = document.getElementById('stockSearchGroup');
        if (group) group.classList.toggle('d-none', !visible);
    };

    const syncPerPageFromTab = (key) => {
        const bar = document.getElementById('inventoryPerPageSelect');
        const perEl = document.getElementById(PER_PAGE_SELECTS[key]);
        if (bar && perEl) bar.value = perEl.value;
    };

    const buildFilters = (key) => {
        const filters = {};
        const mapping = TAB_FILTERS[key] || {};
        Object.keys(mapping).forEach((param) => {
            const input = document.getElementById(mapping[param]);
            if (!input) return;
            const value = (param === 'product_id' || param === 'user_id')
                ? input.value.trim()
                : input.value;
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
        if (key === 'stock') {
            const input = document.getElementById('stockSearchInput');
            if (input) input.value = '';
            stockSort = 'name';
            stockDir = 'ASC';
            updateStockSortHeaders();
        } else if (key === 'movements') {
            ['movementTypeSelect', 'movementProductId', 'movementUserId', 'movementDateFrom', 'movementDateTo'].forEach((id) => {
                const el = document.getElementById(id);
                if (el) el.value = '';
            });
        } else if (key === 'lowstock') {
            const threshold = document.getElementById('lowStockThreshold');
            if (threshold) threshold.value = '10';
        }
        const perEl = document.getElementById(PER_PAGE_SELECTS[key]);
        if (perEl) perEl.value = '10';
        syncPerPageFromTab(key);
        FilterBar.closeOffcanvas('filterOffcanvas');
        refreshActiveTab();
    };

    const updateStockSortHeaders = () => {
        document.querySelectorAll('#stockPane .sortable').forEach(th => {
            const icon = th.querySelector('i');
            if (!icon) return;
            if (th.dataset.sort === stockSort) {
                icon.className = stockDir === 'ASC' ? 'bi bi-arrow-up' : 'bi bi-arrow-down';
            } else {
                icon.className = 'bi bi-arrow-down-up';
            }
        });
    };

    // ─── Eventos de filtros ─────────────────────────────────

    document.getElementById('stockSearchInput')?.addEventListener('input', () => {
        clearTimeout(stockSearchTimeout);
        stockSearchTimeout = setTimeout(() => refreshActiveTab(), 400);
    });

    document.getElementById('btnClearStockSearch')?.addEventListener('click', () => {
        document.getElementById('stockSearchInput').value = '';
        refreshActiveTab();
    });

    document.getElementById('lowStockThreshold')?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') refreshActiveTab();
    });

    document.getElementById('inventoryPerPageSelect')?.addEventListener('change', () => {
        const key = getActiveTabKey();
        if (!key) return;
        const perEl = document.getElementById(PER_PAGE_SELECTS[key]);
        if (perEl) perEl.value = document.getElementById('inventoryPerPageSelect').value;
        refreshActiveTab();
    });

    document.getElementById('btnApplyFilters')?.addEventListener('click', applyFilters);
    document.getElementById('btnClearFilters')?.addEventListener('click', clearFilters);

    // ─── Ordenamiento de existencias ────────────────────────

    document.querySelectorAll('#stockPane .sortable').forEach(th => {
        th.addEventListener('click', () => {
            const col = th.dataset.sort;
            if (stockSort === col) {
                stockDir = stockDir === 'ASC' ? 'DESC' : 'ASC';
            } else {
                stockSort = col;
                stockDir = 'ASC';
            }
            updateStockSortHeaders();
            loadStock(stockPage);
        });
    });

    // ─── Paginación ─────────────────────────────────────────

    document.getElementById('stockPaginationLinks')?.addEventListener('click', (e) => {
        e.preventDefault();
        const link = e.target.closest('[data-page]');
        if (link && !link.closest('.disabled')) {
            loadStock(parseInt(link.dataset.page, 10));
        }
    });

    document.getElementById('movementsPaginationLinks')?.addEventListener('click', (e) => {
        e.preventDefault();
        const link = e.target.closest('[data-page]');
        if (link && !link.closest('.disabled')) {
            loadMovements(parseInt(link.dataset.page, 10));
        }
    });

    document.getElementById('lowStockPaginationLinks')?.addEventListener('click', (e) => {
        e.preventDefault();
        const link = e.target.closest('[data-page]');
        if (link && !link.closest('.disabled')) {
            loadLowStock(parseInt(link.dataset.page, 10));
        }
    });

    // ─── Selector de producto (Entrada / Ajuste) ─────────────

    const selectProduct = (el, hiddenId, selectedTextId) => {
        return (e) => {
            const li = e.target.closest('[data-product-id]');
            if (!li) return;

            document.getElementById(hiddenId).value = li.dataset.productId;
            document.getElementById(el).value = li.dataset.productName;
            const info = document.getElementById(selectedTextId);
            if (info) {
                info.textContent = `Seleccionado: ${li.dataset.productName} (${li.dataset.productCode}) - Stock actual: ${li.dataset.productStock}`;
            }
            InventoryUI.clearProductPicker(el + 'List');
        };
    };

    const setupProductSearch = (inputId, listId, hiddenId, selectedTextId) => {
        const input = document.getElementById(inputId);
        const list = document.getElementById(listId);
        if (!input || !list) return;

        let timeout = null;
        input.addEventListener('input', () => {
            const q = input.value.trim();
            clearTimeout(timeout);
            if (q.length < 2) {
                InventoryUI.clearProductPicker(listId);
                return;
            }
            timeout = setTimeout(async () => {
                const { ok, json } = await InventoryAPI.searchProducts(q);
                if (ok && json.success) {
                    InventoryUI.renderProductPicker(listId, json.data || []);
                }
            }, 300);
        });

        list.addEventListener('click', selectProduct(inputId, hiddenId, selectedTextId));

        input.addEventListener('focus', () => {
            const q = input.value.trim();
            if (q.length >= 2) {
                input.dispatchEvent(new Event('input'));
            }
        });
    };

    // ─── Modales ─────────────────────────────────────────────

    const openAddStockModal = (productId = null, productName = '') => {
        const form = document.getElementById('addStockForm');
        form.reset();
        document.getElementById('addProductId').value = productId || '';
        document.getElementById('addProductSelected').textContent = productId
            ? `Seleccionado: ${productName}`
            : 'Selecciona un producto para continuar.';
        document.getElementById('addProductSearch').value = productId ? productName : '';
        InventoryUI.clearProductPicker('addProductSearchList');
        if (window.FormValidation) {
            window.FormValidation.limpiarErrores(form);
        }
        recalcularEstadoBoton(form);
        modals.addStock.show();
    };

    const openAdjustStockModal = (productId = null, productName = '') => {
        const form = document.getElementById('adjustStockForm');
        form.reset();
        document.getElementById('adjustProductId').value = productId || '';
        document.getElementById('adjustProductSelected').textContent = productId
            ? `Seleccionado: ${productName}`
            : 'Selecciona un producto para continuar.';
        document.getElementById('adjustProductSearch').value = productId ? productName : '';
        InventoryUI.clearProductPicker('adjustProductSearchList');
        if (window.FormValidation) {
            window.FormValidation.limpiarErrores(form);
        }
        recalcularEstadoBoton(form);
        modals.adjustStock.show();
    };

    const saveAddStock = async () => {
        const form = document.getElementById('addStockForm');

        if (!validarFormulario(form)) {
            return;
        }
        if (!document.getElementById('addProductId').value) {
            window.FormValidation.marcarError(document.getElementById('addProductSearch'));
            return;
        }

        const data = {
            product_id: document.getElementById('addProductId').value,
            quantity: document.getElementById('addQuantity').value,
            reason: document.getElementById('addReason').value.trim(),
        };

        try {
            const { ok, json } = await InventoryAPI.addStock(data);
            if (ok && json.success) {
                modals.addStock.hide();
                InventoryUI.showAlert('success', json.message);
                loadStock(stockPage);
            } else {
                if (json.errors) {
                    mostrarErroresBackend(form, json.errors);
                } else {
                    InventoryUI.showAlert('danger', json.message || 'Error al registrar la entrada.');
                }
            }
        } catch (e) {
            InventoryUI.showAlert('danger', 'Error de conexion con el servidor.');
        }
    };

    const saveAdjustStock = async () => {
        const form = document.getElementById('adjustStockForm');

        if (!validarFormulario(form)) {
            return;
        }
        if (!document.getElementById('adjustProductId').value) {
            window.FormValidation.marcarError(document.getElementById('adjustProductSearch'));
            return;
        }

        const data = {
            product_id: document.getElementById('adjustProductId').value,
            stock: document.getElementById('adjustStockValue').value,
            reason: document.getElementById('adjustReason').value.trim(),
        };

        try {
            const { ok, json } = await InventoryAPI.adjustStock(data);
            if (ok && json.success) {
                modals.adjustStock.hide();
                InventoryUI.showAlert('success', json.message);
                loadStock(stockPage);
            } else {
                if (json.errors) {
                    mostrarErroresBackend(form, json.errors);
                } else {
                    InventoryUI.showAlert('danger', json.message || 'Error al registrar el ajuste.');
                }
            }
        } catch (e) {
            InventoryUI.showAlert('danger', 'Error de conexion con el servidor.');
        }
    };

    document.getElementById('btnAddStock')?.addEventListener('click', () => openAddStockModal());
    document.getElementById('btnAdjustStock')?.addEventListener('click', () => openAdjustStockModal());
    document.getElementById('addStockForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        await saveAddStock();
    });
    document.getElementById('adjustStockForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        await saveAdjustStock();
    });

    const STOCK_MENU_ITEMS = [
        { action: 'add-stock', icon: 'bi-plus-circle', label: 'Agregar stock', className: 'text-success' },
        { action: 'adjust-stock', icon: 'bi-sliders', label: 'Ajustar inventario', className: 'text-warning' },
    ];

    document.getElementById('stockTableBody')?.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-action-menu-trigger]');
        if (!btn) return;

        const id = parseInt(btn.dataset.id, 10);
        if (!Number.isInteger(id) || id <= 0) {
            InventoryUI.showAlert('danger', 'No se pudo obtener el ID del producto. Recarga la pagina (Ctrl+F5) e intenta de nuevo.');
            return;
        }

        ActionMenu.open(btn, STOCK_MENU_ITEMS, {
            id,
            name: btn.dataset.name || '',
            onSelect: (item) => {
                if (item.action === 'add-stock') openAddStockModal(item.id, item.name);
                if (item.action === 'adjust-stock') openAdjustStockModal(item.id, item.name);
            },
        });
    });

    document.getElementById('lowStockTableBody')?.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-action-menu-trigger]');
        if (!btn) return;

        const id = parseInt(btn.dataset.id, 10);
        if (!Number.isInteger(id) || id <= 0) {
            InventoryUI.showAlert('danger', 'No se pudo obtener el ID del producto. Recarga la pagina (Ctrl+F5) e intenta de nuevo.');
            return;
        }

        ActionMenu.open(btn, STOCK_MENU_ITEMS, {
            id,
            name: btn.dataset.name || '',
            onSelect: (item) => {
                if (item.action === 'add-stock') openAddStockModal(item.id, item.name);
                if (item.action === 'adjust-stock') openAdjustStockModal(item.id, item.name);
            },
        });
    });

    setupProductSearch('addProductSearch', 'addProductSearchList', 'addProductId', 'addProductSelected');
    setupProductSearch('adjustProductSearch', 'adjustProductSearchList', 'adjustProductId', 'adjustProductSelected');

    // ─── Cambio de tab ──────────────────────────────────────

    document.getElementById('inventoryTabs')?.addEventListener('shown.bs.tab', (e) => {
        const tab = e.target.closest('[data-bs-toggle="tab"]');
        if (!tab) return;
        const target = tab.getAttribute('data-bs-target');
        const map = {
            '#stockPane': 'stock',
            '#movementsPane': 'movements',
            '#lowstockPane': 'lowstock',
        };
        const key = map[target];
        if (!key) return;
        syncPerPageFromTab(key);
        switchFilterGroup(key);
        setStockSearchVisible(key === 'stock');
        if (key === 'stock') {
            if (!document.getElementById('stockTableBody').hasChildNodes()) {
                loadStock(1);
            }
        } else {
            const loader = LOADERS[key];
            if (loader) loader(1);
        }
        renderFilterChips();
    });

    // ─── Inicializacion ─────────────────────────────────────

    setStockSearchVisible(true);
    syncPerPageFromTab('stock');
    renderFilterChips();
    loadStock(1);
});
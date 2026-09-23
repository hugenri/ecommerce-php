const InventoryUI = (() => {
    const showLoading = () => {
        document.getElementById('loadingOverlay')?.classList.add('active');
    };

    const hideLoading = () => {
        document.getElementById('loadingOverlay')?.classList.remove('active');
    };

    const showAlert = (type, message) => {
        const container = document.getElementById('alertContainer');
        if (!container) return;

        const id = 'alert-' + Date.now();
        const icon = type === 'success' ? 'check-circle' : type === 'danger' ? 'exclamation-circle' : 'info-circle';
        const html = `
            <div id="${id}" class="alert alert-${type} alert-dismissible fade show shadow-sm" role="alert">
                <i class="bi bi-${icon} me-2"></i>${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>`;
        container.insertAdjacentHTML('beforeend', html);

        setTimeout(() => {
            const el = document.getElementById(id);
            if (el) {
                const bsAlert = bootstrap.Alert.getOrCreateInstance(el);
                bsAlert.close();
            }
        }, 5000);
    };

    const formatDateTime = (dateStr) => {
        if (!dateStr) return '-';
        const d = new Date(dateStr);
        if (isNaN(d.getTime())) return '-';
        return d.toLocaleDateString('es-ES', {
            day: '2-digit', month: 'short', year: 'numeric',
            hour: '2-digit', minute: '2-digit',
        });
    };

    const escapeHtml = (str) => {
        if (str === null || str === undefined) return '';
        const div = document.createElement('div');
        div.appendChild(document.createTextNode(String(str)));
        return div.innerHTML;
    };

    const movementBadge = (type) => {
        return `<span class="badge badge-movement-${escapeHtml(type)}">${escapeHtml(StatusTranslator.movementType(type))}</span>`;
    };

    const quantityCell = (qty) => {
        const num = parseInt(qty, 10);
        if (num > 0) return `<span class="movement-quantity-positive">+${num}</span>`;
        if (num < 0) return `<span class="movement-quantity-negative">${num}</span>`;
        return '0';
    };

    const renderStockTable = (rows, meta) => {
        const tbody = document.getElementById('stockTableBody');
        if (!tbody) return;

        if (!rows || rows.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="5" class="text-center py-4 text-muted">
                        <i class="bi bi-inbox fs-1 d-block mb-2"></i> No se encontraron productos.
                    </td>
                </tr>`;
            updatePaginationInfo('stockPaginationInfo', 0, 0, 0);
            renderPaginationLinks('stockPaginationLinks', 0, 0);
            return;
        }

        tbody.innerHTML = rows.map(row => {
            const low = row.stock <= 10 && row.is_active;
            return `
                <tr data-product-id="${row.product_id}">
                    <td class="text-muted">${escapeHtml(row.product_code)}</td>
                    <td>
                        <div class="d-flex align-items-center">
                            <strong>${escapeHtml(row.name)}</strong>
                        </div>
                    </td>
                    <td class="text-center ${low ? 'stock-low' : 'stock-ok'}">${row.stock}</td>
                    <td><span class="badge ${row.is_active ? 'bg-success' : 'bg-secondary'}">${StatusTranslator.boolean(row.is_active)}</span></td>
                    <td>
                        <div class="d-flex justify-content-center table-actions">
                            <button type="button" class="btn btn-light border btn-sm" data-action-menu-trigger=""
                                data-id="${row.product_id}" data-name="${escapeHtml(row.name)}"
                                aria-label="Acciones de inventario" aria-expanded="false">
                                <i class="bi bi-three-dots-vertical"></i>
                            </button>
                        </div>
                    </td>
                </tr>`;
        }).join('');

        updatePaginationInfo('stockPaginationInfo', meta.current_page, meta.per_page, meta.total);
        renderPaginationLinks('stockPaginationLinks', meta.current_page, meta.total_pages);
    };

    const renderMovementsTable = (rows, meta) => {
        const tbody = document.getElementById('movementsTableBody');
        if (!tbody) return;

        if (!rows || rows.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="9" class="text-center py-4 text-muted">
                        <i class="bi bi-inbox fs-1 d-block mb-2"></i> No se encontraron movimientos.
                    </td>
                </tr>`;
            updatePaginationInfo('movementsPaginationInfo', 0, 0, 0);
            renderPaginationLinks('movementsPaginationLinks', 0, 0);
            return;
        }

        tbody.innerHTML = rows.map(m => `
            <tr data-movement-id="${m.movement_id}">
                <td class="text-muted">${m.movement_id}</td>
                <td class="text-muted small">${formatDateTime(m.created_at)}</td>
                <td>
                    <div>
                        <strong>${escapeHtml(m.product_name)}</strong>
                        <small class="d-block text-muted">${escapeHtml(m.product_code)}</small>
                    </div>
                </td>
                <td>${movementBadge(m.movement_type)}</td>
                <td class="text-center">${quantityCell(m.quantity)}</td>
                <td class="text-center text-muted">${m.previous_stock}</td>
                <td class="text-center">${m.current_stock}</td>
                <td class="text-muted small">${m.user_name ? escapeHtml(m.user_name) : 'Automático'}</td>
                <td class="text-muted small">${escapeHtml(m.reason) || '-'}</td>
            </tr>
        `).join('');

        updatePaginationInfo('movementsPaginationInfo', meta.current_page, meta.per_page, meta.total);
        renderPaginationLinks('movementsPaginationLinks', meta.current_page, meta.total_pages);
    };

    const renderLowStockTable = (rows, meta) => {
        const tbody = document.getElementById('lowStockTableBody');
        if (!tbody) return;

        if (!rows || rows.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="6" class="text-center py-4 text-muted">
                        <i class="bi bi-check-circle fs-1 d-block mb-2"></i> No hay productos por debajo del umbral.
                    </td>
                </tr>`;
            updatePaginationInfo('lowStockPaginationInfo', 0, 0, 0);
            renderPaginationLinks('lowStockPaginationLinks', 0, 0);
            return;
        }

        tbody.innerHTML = rows.map(row => `
            <tr data-product-id="${row.product_id}">
                <td class="text-muted">${escapeHtml(row.product_code)}</td>
                <td><strong>${escapeHtml(row.name)}</strong></td>
                <td class="text-muted small">${escapeHtml(row.category_name) || '-'}</td>
                <td class="text-center stock-low">${row.stock}</td>
                <td class="text-muted small">${formatDateTime(row.last_movement_at)}</td>
                <td>
                    <div class="d-flex justify-content-center table-actions">
                        <button type="button" class="btn btn-light border btn-sm" data-action-menu-trigger=""
                            data-id="${row.product_id}" data-name="${escapeHtml(row.name)}"
                            aria-label="Acciones de inventario" aria-expanded="false">
                            <i class="bi bi-three-dots-vertical"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `).join('');

        updatePaginationInfo('lowStockPaginationInfo', meta.current_page, meta.per_page, meta.total);
        renderPaginationLinks('lowStockPaginationLinks', meta.current_page, meta.total_pages);
    };

    const updatePaginationInfo = (elId, page, perPage, total) => {
        const info = document.getElementById(elId);
        if (!info) return;

        if (total === 0) {
            info.textContent = 'Sin resultados.';
            return;
        }

        const start = (page - 1) * perPage + 1;
        const end = Math.min(page * perPage, total);
        info.textContent = `Mostrando ${start} - ${end} de ${total}`;
    };

    const renderPaginationLinks = (elId, currentPage, totalPages) => {
        const container = document.getElementById(elId);
        if (!container) return;

        if (totalPages <= 1) {
            container.innerHTML = '';
            return;
        }

        let html = '';

        html += `<li class="page-item ${currentPage <= 1 ? 'disabled' : ''}">
            <a class="page-link" href="#" data-page="${currentPage - 1}">&laquo;</a></li>`;

        const maxVisible = 5;
        let startPage = Math.max(1, currentPage - Math.floor(maxVisible / 2));
        let endPage = Math.min(totalPages, startPage + maxVisible - 1);

        if (endPage - startPage < maxVisible - 1) {
            startPage = Math.max(1, endPage - maxVisible + 1);
        }

        if (startPage > 1) {
            html += `<li class="page-item"><a class="page-link" href="#" data-page="1">1</a></li>`;
            if (startPage > 2) html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
        }

        for (let i = startPage; i <= endPage; i++) {
            html += `<li class="page-item ${i === currentPage ? 'active' : ''}">
                <a class="page-link" href="#" data-page="${i}">${i}</a></li>`;
        }

        if (endPage < totalPages) {
            if (endPage < totalPages - 1) html += `<li class="page-item disabled"><span class="page-link">...</span></li>`;
            html += `<li class="page-item"><a class="page-link" href="#" data-page="${totalPages}">${totalPages}</a></li>`;
        }

        html += `<li class="page-item ${currentPage >= totalPages ? 'disabled' : ''}">
            <a class="page-link" href="#" data-page="${currentPage + 1}">&raquo;</a></li>`;

        container.innerHTML = html;
    };

    const renderProductPicker = (elId, products) => {
        const container = document.getElementById(elId);
        if (!container) return;

        if (!products || products.length === 0) {
            container.innerHTML = '';
            return;
        }

        container.innerHTML = `
            <ul class="product-picker-list">
                ${products.map(p => `
                    <li data-product-id="${p.product_id}"
                        data-product-name="${escapeHtml(p.name)}"
                        data-product-code="${escapeHtml(p.product_code)}"
                        data-product-stock="${p.stock}">
                        <strong>${escapeHtml(p.name)}</strong>
                        <small class="text-muted d-block">${escapeHtml(p.product_code)} - Stock: ${p.stock}</small>
                    </li>`).join('')}
            </ul>`;
    };

    const clearProductPicker = (elId) => {
        const container = document.getElementById(elId);
        if (container) container.innerHTML = '';
    };

    return {
        showLoading,
        hideLoading,
        showAlert,
        renderStockTable,
        renderMovementsTable,
        renderLowStockTable,
        renderPaginationLinks,
        renderProductPicker,
        clearProductPicker,
        escapeHtml,
    };
})();

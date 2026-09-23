const ReportsUI = (() => {
    const showLoading = () => {
        document.getElementById('loadingOverlay')?.classList.add('active');
    };
    const parseTotalPages = (meta) => {
        return meta?.total_pages ?? meta?.last_page ?? 0;
    };    const hideLoading = () => {
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

    const escapeHtml = (str) => {
        if (str === null || str === undefined) return '';
        const div = document.createElement('div');
        div.appendChild(document.createTextNode(String(str)));
        return div.innerHTML;
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

    const formatDate = (dateStr) => {
        if (!dateStr) return '-';
        const d = new Date(dateStr);
        if (isNaN(d.getTime())) return '-';
        return d.toLocaleDateString('es-ES', { day: '2-digit', month: 'short', year: 'numeric' });
    };

    const formatMoney = (value) => {
        const num = Number(value) || 0;
        return num.toLocaleString('es-MX', {
            style: 'currency',
            currency: 'MXN',
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        });
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

    const renderSalesTable = (rows, meta) => {
        const tbody = document.getElementById('salesTableBody');
        if (!tbody) return;

        if (!rows || rows.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="7" class="text-center py-4 text-muted">
                        <i class="bi bi-inbox fs-1 d-block mb-2"></i> No se encontraron ventas.
                    </td>
                </tr>`;
            updatePaginationInfo('salesPaginationInfo', 0, 0, 0);
            renderPaginationLinks('salesPaginationLinks', 0, 0);
            return;
        }

        tbody.innerHTML = rows.map(row => `
            <tr>
                <td class="text-muted">${escapeHtml(row.sale_code)}</td>
                <td class="text-muted small">${formatDateTime(row.sale_date)}</td>
                <td>${escapeHtml(row.customer_name)}</td>
                <td class="text-muted">${escapeHtml(row.payment_method)}</td>
                <td><span class="badge ${row.status_badge}">${escapeHtml(StatusTranslator.status(row.status))}</span></td>
                <td><span class="badge ${row.payment_badge}">${escapeHtml(StatusTranslator.paymentStatus(row.payment_status))}</span></td>
                <td class="text-end">${formatMoney(row.total)}</td>
            </tr>
        `).join('');

        updatePaginationInfo('salesPaginationInfo', meta.current_page, meta.per_page, meta.total);
        renderPaginationLinks('salesPaginationLinks', meta.current_page, parseTotalPages(meta));
    };

    const renderSalesSummary = (summary) => {
        document.getElementById('salesTotalCount').textContent = summary?.total_sales ?? 0;
        document.getElementById('salesTotalAmount').textContent = formatMoney(summary?.total_sold ?? 0);
    };

    const renderTopProductsTable = (rows, meta) => {
        const tbody = document.getElementById('topProductsTableBody');
        if (!tbody) return;

        if (!rows || rows.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="4" class="text-center py-4 text-muted">
                        <i class="bi bi-inbox fs-1 d-block mb-2"></i> No se encontraron productos.
                    </td>
                </tr>`;
            updatePaginationInfo('topProductsPaginationInfo', 0, 0, 0);
            renderPaginationLinks('topProductsPaginationLinks', 0, 0);
            return;
        }

        tbody.innerHTML = rows.map(row => `
            <tr>
                <td class="text-muted">${escapeHtml(row.product_code)}</td>
                <td><strong>${escapeHtml(row.product_name)}</strong></td>
                <td class="text-center">${row.quantity_sold}</td>
                <td class="text-end">${formatMoney(row.total_sold)}</td>
            </tr>
        `).join('');

        updatePaginationInfo('topProductsPaginationInfo', meta.current_page, meta.per_page, meta.total);
        renderPaginationLinks('topProductsPaginationLinks', meta.current_page, parseTotalPages(meta));
    };

    const renderLowStockTable = (rows, meta) => {
        const tbody = document.getElementById('lowStockTableBody');
        if (!tbody) return;

        if (!rows || rows.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="5" class="text-center py-4 text-muted">
                        <i class="bi bi-check-circle fs-1 d-block mb-2"></i> No hay productos por debajo del umbral.
                    </td>
                </tr>`;
            updatePaginationInfo('lowStockPaginationInfo', 0, 0, 0);
            renderPaginationLinks('lowStockPaginationLinks', 0, 0);
            return;
        }

        tbody.innerHTML = rows.map(row => `
            <tr>
                <td class="text-muted">${escapeHtml(row.product_code)}</td>
                <td><strong>${escapeHtml(row.name)}</strong></td>
                <td class="text-muted small">${escapeHtml(row.category_name) || '-'}</td>
                <td class="text-center stock-low">${row.stock}</td>
                <td class="text-muted small">${formatDateTime(row.last_movement_at)}</td>
            </tr>
        `).join('');

        updatePaginationInfo('lowStockPaginationInfo', meta.current_page, meta.per_page, meta.total);
        renderPaginationLinks('lowStockPaginationLinks', meta.current_page, parseTotalPages(meta));
    };

    const renderMovementsTable = (rows, meta) => {
        const tbody = document.getElementById('movementsTableBody');
        if (!tbody) return;

        if (!rows || rows.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="8" class="text-center py-4 text-muted">
                        <i class="bi bi-inbox fs-1 d-block mb-2"></i> No se encontraron movimientos.
                    </td>
                </tr>`;
            updatePaginationInfo('movementsPaginationInfo', 0, 0, 0);
            renderPaginationLinks('movementsPaginationLinks', 0, 0);
            return;
        }

        tbody.innerHTML = rows.map(m => `
            <tr>
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
                <td class="text-muted small">${escapeHtml(m.reason) || '-'}</td>
                <td class="text-muted small">${m.user_name ? escapeHtml(m.user_name) : 'Automático'}</td>
            </tr>
        `).join('');

        updatePaginationInfo('movementsPaginationInfo', meta.current_page, meta.per_page, meta.total);
        renderPaginationLinks('movementsPaginationLinks', meta.current_page, parseTotalPages(meta));
    };

    const renderDeliveriesTable = (rows, meta) => {
        const tbody = document.getElementById('deliveriesTableBody');
        if (!tbody) return;

        if (!rows || rows.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="6" class="text-center py-4 text-muted">
                        <i class="bi bi-inbox fs-1 d-block mb-2"></i> No se encontraron entregas.
                    </td>
                </tr>`;
            updatePaginationInfo('deliveriesPaginationInfo', 0, 0, 0);
            renderPaginationLinks('deliveriesPaginationLinks', 0, 0);
            return;
        }

        tbody.innerHTML = rows.map(row => `
            <tr>
                <td class="text-muted">${escapeHtml(row.sale_code)}</td>
                <td>${escapeHtml(row.customer_name)}</td>
                <td class="text-muted">${escapeHtml(row.employee_name)}</td>
                <td><span class="badge ${row.status_badge}">${escapeHtml(StatusTranslator.deliveryStatus(row.status))}</span></td>
                <td class="text-muted small">${formatDate(row.shipping_date)}</td>
                <td class="text-muted small">${formatDate(row.delivery_date)}</td>
            </tr>
        `).join('');

        updatePaginationInfo('deliveriesPaginationInfo', meta.current_page, meta.per_page, meta.total);
        renderPaginationLinks('deliveriesPaginationLinks', meta.current_page, parseTotalPages(meta));
    };

    const renderDeliveriesSummary = (summary) => {
        document.getElementById('deliverySummaryPending').textContent = summary?.pending ?? 0;
        document.getElementById('deliverySummaryPreparing').textContent = summary?.preparing ?? 0;
        document.getElementById('deliverySummaryShipped').textContent = summary?.shipped ?? 0;
        document.getElementById('deliverySummaryDelivered').textContent = summary?.delivered ?? 0;
        document.getElementById('deliverySummaryCancelled').textContent = summary?.cancelled ?? 0;
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

    return {
        showLoading,
        hideLoading,
        showAlert,
        renderSalesTable,
        renderSalesSummary,
        renderTopProductsTable,
        renderLowStockTable,
        renderMovementsTable,
        renderDeliveriesTable,
        renderDeliveriesSummary,
        renderPaginationLinks,
        escapeHtml,
    };
})();

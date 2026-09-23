const API = {
        async request(method, url, body = null) {
            const headers = {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': window.CSRF_TOKEN || '',
            };
            if (body && !(body instanceof FormData)) {
                headers['Content-Type'] = 'application/json';
                body = JSON.stringify(body);
            }
            const res = await fetch(url, { method, headers, body });
            return res.json();
        },
        getData(params = {}) {
            const qs = new URLSearchParams(params).toString();
            return this.request('GET', '/sales/data' + (qs ? '?' + qs : ''));
        },
        get(id) { return this.request('GET', '/sales/' + id); },
        updateStatus(id, status) { return this.request('PATCH', '/sales/' + id + '/status', { status }); },
        cancel(id) { return this.request('PATCH', '/sales/' + id + '/cancel'); },
    };

    let currentPage = 1;
    let currentFilters = {};
    let currentSortBy = 'sale_date';
    let currentSortDir = 'DESC';
    let pendingSaleId = null;

    function showAlert(message, type) {
        const container = document.getElementById('alertContainer');
        const alert = document.createElement('div');
        alert.className = 'alert alert-' + type + ' alert-dismissible fade show';
        alert.innerHTML = message + '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>';
        container.appendChild(alert);
        setTimeout(() => alert.remove(), 5000);
    }

    function showLoading(show) {
        document.getElementById('loadingOverlay').style.display = show ? 'flex' : 'none';
    }

    function primerError(errors) {
        const flat = Object.values(errors || {}).flat();
        return flat.find(Boolean) || '';
    }

    function limpiarErrorCampo(input) {
        if (!input) return;
        input.classList.remove('is-invalid');
        const fb = input.closest('.mb-3') ? input.closest('.mb-3').querySelector('.field-feedback') : null;
        if (fb) fb.hidden = true;
    }

    function mostrarErrorCampo(input, field, errors) {
        limpiarErrorCampo(input);
        const message = (errors && errors[field] && errors[field][0]) || primerError(errors);
        if (!message) {
            showAlert('Errores de validación.', 'danger');
            return;
        }
        if (!input) {
            showAlert(message, 'danger');
            return;
        }
        input.classList.add('is-invalid');
        const wrapper = input.closest('.mb-3') || input.parentElement;
        const fb = wrapper ? wrapper.querySelector('.field-feedback') : null;
        if (fb) {
            fb.textContent = message;
            fb.hidden = false;
        } else {
            showAlert(message, 'danger');
        }
        input.addEventListener('input', () => limpiarErrorCampo(input), { once: true });
        input.addEventListener('change', () => limpiarErrorCampo(input), { once: true });
    }

    function formatDateTime(val) {
        return val ? val : '<span class="text-muted">&mdash;</span>';
    }

    function renderTable(items) {
        const tbody = document.getElementById('tableBody');
        tbody.innerHTML = '';
        if (!items || items.length === 0) {
            const emptyRow = document.createElement('tr');
            emptyRow.innerHTML = '<td colspan="8" class="text-center text-muted py-4"><i class="bi bi-inbox me-2"></i>No hay ventas.</td>';
            tbody.appendChild(emptyRow);
            return;
        }

        items.forEach(s => {
            const tr = document.createElement('tr');

            const codeTd = document.createElement('td');
            codeTd.className = 'align-middle fw-medium';
            codeTd.textContent = s.sale_code;
            tr.appendChild(codeTd);

            const customerTd = document.createElement('td');
            customerTd.className = 'align-middle';
            customerTd.innerHTML = `<small>${s.customer_name}<br><span class="text-muted">${s.customer_email}</span></small>`;
            tr.appendChild(customerTd);

            const dateTd = document.createElement('td');
            dateTd.className = 'align-middle small';
            dateTd.textContent = formatDateTime(s.sale_date);
            tr.appendChild(dateTd);

            const totalTd = document.createElement('td');
            totalTd.className = 'align-middle text-end';
            totalTd.textContent = `$${s.total.toFixed(2)}`;
            tr.appendChild(totalTd);

            const paymentTd = document.createElement('td');
            paymentTd.className = 'align-middle';
            const paymentBadge = document.createElement('span');
            paymentBadge.className = 'badge bg-secondary';
            paymentBadge.textContent = StatusTranslator.paymentMethod(s.payment_method);
            paymentTd.appendChild(paymentBadge);
            tr.appendChild(paymentTd);

            const paymentStatusTd = document.createElement('td');
            paymentStatusTd.className = 'align-middle';
            const paymentStatusBadge = document.createElement('span');
            paymentStatusBadge.className = s.payment_badge || '';
            paymentStatusBadge.textContent = StatusTranslator.paymentStatus(s.payment_status);
            paymentStatusTd.appendChild(paymentStatusBadge);
            tr.appendChild(paymentStatusTd);

            const statusTd = document.createElement('td');
            statusTd.className = 'align-middle';
            const statusBadge = document.createElement('span');
            statusBadge.className = s.status_badge || '';
            statusBadge.textContent = StatusTranslator.status(s.status);
            statusTd.appendChild(statusBadge);
            tr.appendChild(statusTd);

            const actionsTd = document.createElement('td');
            actionsTd.className = 'align-middle';
            const wrap = document.createElement('div');
            wrap.className = 'd-flex justify-content-center table-actions';
            const kebab = document.createElement('button');
            kebab.type = 'button';
            kebab.className = 'btn btn-light border btn-sm';
            kebab.setAttribute('data-action-menu-trigger', '');
            kebab.setAttribute('aria-label', 'Acciones de la venta');
            kebab.setAttribute('aria-expanded', 'false');
            kebab.innerHTML = '<i class="bi bi-three-dots-vertical"></i>';
            kebab.addEventListener('click', () => {
                const items = [
                    { action: 'show', icon: 'bi-eye', label: 'Ver detalle', className: 'text-info' },
                    { action: 'status', icon: 'bi-arrow-repeat', label: 'Cambiar estado', className: 'text-primary' },
                ];
                if (s.status === 'pending' || s.status === 'processing') {
                    items.push({ divider: true });
                    items.push({ action: 'cancel', icon: 'bi-x-circle', label: 'Cancelar', className: 'text-danger' });
                }
                ActionMenu.open(kebab, items, {
                    id: s.sale_id,
                    name: s.sale_code,
                    onSelect: (item) => {
                        if (item.action === 'show') viewDetail(item.id);
                        else if (item.action === 'status') showStatusModal(item.id);
                        else if (item.action === 'cancel') confirmCancel(item.id);
                    },
                });
            });
            wrap.appendChild(kebab);
            actionsTd.appendChild(wrap);
            tr.appendChild(actionsTd);
            tbody.appendChild(tr);
        });
    }

    function renderPagination(meta) {
        const info = document.getElementById('paginationInfo');
        const ul = document.getElementById('pagination');
        if (!meta || meta.last_page <= 1) {
            info.textContent = meta ? 'Mostrando ' + meta.total + ' registro(s)' : '';
            ul.innerHTML = '';
            return;
        }
        const from = (meta.current_page - 1) * meta.per_page + 1;
        const to = Math.min(meta.current_page * meta.per_page, meta.total);
        info.textContent = 'Mostrando ' + from + '-' + to + ' de ' + meta.total;

        let html = '';
        html += '<li class="page-item ' + (meta.current_page <= 1 ? 'disabled' : '') + '"><a class="page-link" href="#" data-page="' + (meta.current_page - 1) + '">&laquo;</a></li>';
        for (let i = 1; i <= meta.last_page; i++) {
            html += '<li class="page-item ' + (meta.current_page === i ? 'active' : '') + '"><a class="page-link" href="#" data-page="' + i + '">' + i + '</a></li>';
        }
        html += '<li class="page-item ' + (meta.current_page >= meta.last_page ? 'disabled' : '') + '"><a class="page-link" href="#" data-page="' + (meta.current_page + 1) + '">&raquo;</a></li>';
        ul.innerHTML = html;
        ul.querySelectorAll('a.page-link').forEach(a => {
            a.addEventListener('click', function (e) {
                e.preventDefault();
                const page = parseInt(this.dataset.page);
                if (page > 0) loadPage(page);
            });
        });
    }

    async function loadPage(page = 1) {
        currentPage = page;
        showLoading(true);
        try {
            const params = {
                page,
                per_page: document.getElementById('perPageSelect').value,
                sort_by: currentSortBy,
                sort_order: currentSortDir,
                ...currentFilters,
            };
            if (params.search === '') delete params.search;
            const res = await API.getData(params);
            if (res.success) {
                renderTable(res.data);
                renderPagination(res.meta);
            } else {
                showAlert(res.message || 'Error al cargar datos.', 'danger');
            }
        } catch (e) {
            showAlert('Error de conexión.', 'danger');
        } finally {
            showLoading(false);
        }
    }

    const FILTER_CHIPS = {
        status: { label: 'Estado de entrega', inputId: 'statusFilter' },
        payment_status: { label: 'Pago', inputId: 'paymentFilter' },
        payment_method: { label: 'Método de pago', inputId: 'methodFilter' },
        date_from: { label: 'Fecha desde', inputId: 'dateFrom' },
        date_to: { label: 'Fecha hasta', inputId: 'dateTo' },
    };

    function renderFilterChips() {
        FilterBar.renderChips({
            chipsId: 'filterChips',
            badgeId: 'filterBadge',
            chips: FilterBar.buildChips(currentFilters, FILTER_CHIPS),
            onRemove: () => {
                applyFilters();
            },
        });
    }

    function applyFilters() {
        currentFilters = {};
        const search = document.getElementById('searchInput').value.trim();
        if (search) currentFilters.search = search;
        const status = document.getElementById('statusFilter').value;
        if (status) currentFilters.status = status;
        const paymentStatus = document.getElementById('paymentFilter').value;
        if (paymentStatus) currentFilters.payment_status = paymentStatus;
        const method = document.getElementById('methodFilter').value;
        if (method) currentFilters.payment_method = method;
        const dateFrom = document.getElementById('dateFrom').value;
        if (dateFrom) currentFilters.date_from = dateFrom;
        const dateTo = document.getElementById('dateTo').value;
        if (dateTo) currentFilters.date_to = dateTo;
        FilterBar.closeOffcanvas('filterOffcanvas');
        renderFilterChips();
        loadPage(1);
    }

    function clearFilters() {
        ['statusFilter', 'paymentFilter', 'methodFilter', 'dateFrom', 'dateTo'].forEach(id => {
            document.getElementById(id).value = '';
        });
        FilterBar.closeOffcanvas('filterOffcanvas');
        applyFilters();
    }

    async function viewDetail(id) {
        showLoading(true);
        try {
            const res = await API.get(id);
            if (!res.success) { showAlert(res.message || 'Error al cargar detalle.', 'danger'); return; }
            const d = res.data;
            const sale = d.sale;
            const details = d.details || [];
            const delivery = d.delivery;
            const address = d.address;

            let html = '<div class="row">';

            html += '<div class="col-md-6 mb-3">';
            html += '<table class="table table-sm table-borderless mb-0">';
            html += '<tr><td class="text-muted">Folio</td><td class="fw-bold">' + sale.sale_code + '</td></tr>';
            html += '<tr><td class="text-muted">Fecha</td><td>' + formatDateTime(sale.sale_date) + '</td></tr>';
            html += '<tr><td class="text-muted">Estado de entrega</td><td><span class="badge bg-warning text-dark">' + StatusTranslator.status(sale.status) + '</span></td></tr>';
            html += '<tr><td class="text-muted">Pago</td><td><span class="badge bg-success">' + StatusTranslator.paymentStatus(sale.payment_status) + '</span></td></tr>';
            html += '<tr><td class="text-muted">Método de pago</td><td>' + StatusTranslator.paymentMethod(sale.payment_method) + '</td></tr>';
            html += '</table></div>';

            html += '<div class="col-md-6 mb-3">';
            html += '<h6 class="text-muted">Dirección de entrega</h6>';
            if (address) {
                html += '<p class="mb-0 small">' + address.full_address + '</p>';
            } else {
                html += '<p class="text-muted small mb-0">No disponible</p>';
            }
            html += '</div></div>';

            html += '<h6 class="text-muted mt-3">Productos</h6>';
            html += '<div class="table-responsive"><table class="table table-sm"><thead><tr><th>Producto</th><th class="text-center">Cant.</th><th class="text-end">Precio</th><th class="text-end">Dto.</th><th class="text-end">Subtotal</th></tr></thead><tbody>';
            let subTotalCalc = 0;
            details.forEach(det => {
                html += '<tr><td>' + (det.product_name || 'Producto') + '</td><td class="text-center">' + det.quantity + '</td><td class="text-end">$' + det.unit_price.toFixed(2) + '</td>';
                if (det.discount_percentage > 0) {
                    html += '<td class="text-end">-' + det.discount_percentage + '%</td>';
                } else {
                    html += '<td class="text-end text-muted">&mdash;</td>';
                }
                html += '<td class="text-end">$' + det.subtotal.toFixed(2) + '</td></tr>';
            });
            html += '</tbody>';
            html += '<tfoot><tr><th colspan="4" class="text-end">Subtotal</th><th class="text-end">$' + sale.subtotal.toFixed(2) + '</th></tr>';
            html += '<tr><th colspan="4" class="text-end">IVA (16%)</th><th class="text-end">$' + sale.tax.toFixed(2) + '</th></tr>';
            html += '<tr class="fs-5"><th colspan="4" class="text-end">Total</th><th class="text-end">$' + sale.total.toFixed(2) + '</th></tr>';
            html += '</tfoot></table></div>';

            html += '<h6 class="text-muted mt-3">Entrega</h6>';
            if (delivery) {
                html += '<div class="col-md-6">';
                html += '<table class="table table-sm table-borderless mb-0">';
                html += '<tr><td class="text-muted">Estado de entrega</td><td><span class="badge bg-info">' + StatusTranslator.deliveryStatus(delivery.status) + '</span></td></tr>';
                html += '<tr><td class="text-muted">Fecha de envío</td><td>' + formatDateTime(delivery.shipping_date) + '</td></tr>';
                html += '<tr><td class="text-muted">Fecha de entrega</td><td>' + formatDateTime(delivery.delivery_date) + '</td></tr>';
                html += '</table></div>';
                html += '<div class="col-md-6"><p class="text-muted small mt-2 mb-0">La gestión de esta entrega se realiza en el módulo <a href="/deliveries" class="text-decoration-none">Entregas</a>.</p></div>';
            } else {
                html += '<p class="text-muted">No hay información de entrega.</p>';
            }

            document.getElementById('detailModalBody').innerHTML = html;
            var modal = new bootstrap.Modal(document.getElementById('detailModal'));
            modal.show();
        } catch (e) {
            showAlert('Error al cargar detalle.', 'danger');
        } finally {
            showLoading(false);
        }
    }

    function showStatusModal(id) {
        pendingSaleId = id;
        limpiarErrorCampo(document.getElementById('statusSelect'));
        document.getElementById('statusSelect').value = '';
        document.getElementById('statusModalTitle').textContent = 'Cambiar estado - Venta #' + id;
        var modal = new bootstrap.Modal(document.getElementById('statusModal'));
        modal.show();
    }

    document.getElementById('btnConfirmStatus').addEventListener('click', async function () {
        if (!pendingSaleId) return;
        const status = document.getElementById('statusSelect').value;
        if (!status) { showAlert('Selecciona un estado.', 'warning'); return; }
        showLoading(true);
        try {
            const res = await API.updateStatus(pendingSaleId, status);
            if (res.success) {
                showAlert('Estado actualizado.', 'success');
                bootstrap.Modal.getInstance(document.getElementById('statusModal')).hide();
                loadPage(currentPage);
            } else {
                mostrarErrorCampo(document.getElementById('statusSelect'), 'status', res.errors);
            }
        } catch (e) {
            showAlert('Error de conexión.', 'danger');
        } finally {
            showLoading(false);
            pendingSaleId = null;
        }
    });

    function confirmCancel(id) {
        pendingSaleId = id;
        document.getElementById('confirmModalTitle').textContent = 'Cancelar pedido';
        document.getElementById('confirmModalBody').innerHTML = '¿Estás seguro de cancelar este pedido? Se restaurará el stock de los productos.';
        document.getElementById('btnConfirmAction').className = 'btn btn-danger';
        document.getElementById('btnConfirmAction').textContent = 'Cancelar pedido';
        var modal = new bootstrap.Modal(document.getElementById('confirmModal'));
        modal.show();
    }

    document.getElementById('btnConfirmAction').addEventListener('click', async function () {
        if (!pendingSaleId) return;
        showLoading(true);
        try {
            const res = await API.cancel(pendingSaleId);
            if (res.success) {
                showAlert('Pedido cancelado y stock restaurado.', 'success');
                bootstrap.Modal.getInstance(document.getElementById('confirmModal')).hide();
                loadPage(currentPage);
            } else {
                showAlert(primerError(res.errors) || res.message || 'Error al cancelar.', 'danger');
            }
        } catch (e) {
            showAlert('Error de conexión.', 'danger');
        } finally {
            showLoading(false);
            pendingSaleId = null;
        }
    });

    document.getElementById('btnApplyFilters').addEventListener('click', applyFilters);
    document.getElementById('btnClearFilters').addEventListener('click', clearFilters);
    document.getElementById('perPageSelect').addEventListener('change', () => loadPage(1));
    document.getElementById('searchInput').addEventListener('keydown', e => { if (e.key === 'Enter') applyFilters(); });
    document.getElementById('btnClearSearch').addEventListener('click', () => { document.getElementById('searchInput').value = ''; applyFilters(); });

    document.querySelectorAll('#mainTable th[data-sort]').forEach(th => {
        th.style.cursor = 'pointer';
        th.addEventListener('click', function () {
            const sortBy = this.dataset.sort;
            if (currentSortBy === sortBy) {
                currentSortDir = currentSortDir === 'ASC' ? 'DESC' : 'ASC';
            } else {
                currentSortBy = sortBy;
                currentSortDir = 'DESC';
            }
            loadPage(1);
        });
    });

    loadPage(1);
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
            return this.request('GET', '/deliveries/data' + (qs ? '?' + qs : ''));
        },
        getPending(params = {}) {
            const qs = new URLSearchParams(params).toString();
            return this.request('GET', '/deliveries/pending' + (qs ? '?' + qs : ''));
        },
        get(id) { return this.request('GET', '/deliveries/' + id); },
        assignEmployee(id, userId) { return this.request('POST', '/deliveries/' + id + '/assign', { user_id: userId }); },
        updateStatus(id, status) { return this.request('PATCH', '/deliveries/' + id + '/status', { status }); },
        updateShippingDate(id, date) { return this.request('POST', '/deliveries/' + id + '/shipping-date', { shipping_date: date }); },
        updateDeliveryDate(id, date) { return this.request('POST', '/deliveries/' + id + '/delivery-date', { delivery_date: date }); },
    };

    let currentPage = 1;
    let listMode = 'all';
    let pendingDeliveryId = null;

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
        const wrapper = input.closest('.mb-2') || input.closest('.field-row') || input.parentElement.parentElement;
        const fb = wrapper ? wrapper.querySelector('.field-feedback') : null;
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
        const wrapper = input.closest('.mb-2') || input.parentElement.parentElement;
        let fb = wrapper ? wrapper.querySelector('.field-feedback') : null;
        if (!fb) {
            fb = document.createElement('div');
            fb.className = 'field-feedback';
            fb.setAttribute('role', 'alert');
            if (wrapper) wrapper.appendChild(fb);
        }
        fb.textContent = message;
        fb.hidden = false;
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
            emptyRow.innerHTML = '<td colspan="8" class="text-center text-muted py-4"><i class="bi bi-inbox me-2"></i>No hay entregas.</td>';
            tbody.appendChild(emptyRow);
            return;
        }

        const deliveryBadges = { pending: 'bg-warning text-dark', preparing: 'bg-info text-dark', shipped: 'bg-primary', delivered: 'bg-success', cancelled: 'bg-danger' };

        items.forEach(d => {
            const tr = document.createElement('tr');

            const codeTd = document.createElement('td');
            codeTd.className = 'align-middle fw-medium';
            codeTd.textContent = d.sale_code;
            tr.appendChild(codeTd);

            const customerTd = document.createElement('td');
            customerTd.className = 'align-middle';
            customerTd.innerHTML = `<small>${d.customer_name}<br><span class="text-muted">${d.customer_email}</span></small>`;
            tr.appendChild(customerTd);

            const empTd = document.createElement('td');
            empTd.className = 'align-middle';
            empTd.innerHTML = d.employee_name ? d.employee_name : '<span class="text-muted">No asignado</span>';
            tr.appendChild(empTd);

            const statusTd = document.createElement('td');
            statusTd.className = 'align-middle';
            const statusBadge = document.createElement('span');
            statusBadge.className = 'badge ' + (deliveryBadges[d.delivery_status] || 'bg-secondary');
            statusBadge.textContent = StatusTranslator.deliveryStatus(d.delivery_status);
            statusTd.appendChild(statusBadge);
            tr.appendChild(statusTd);

            const shipTd = document.createElement('td');
            shipTd.className = 'align-middle small';
            shipTd.innerHTML = formatDateTime(d.shipping_date);
            tr.appendChild(shipTd);

            const delTd = document.createElement('td');
            delTd.className = 'align-middle small';
            delTd.innerHTML = formatDateTime(d.delivery_date);
            tr.appendChild(delTd);

            const saleDateTd = document.createElement('td');
            saleDateTd.className = 'align-middle small';
            saleDateTd.innerHTML = formatDateTime(d.sale_date);
            tr.appendChild(saleDateTd);

            const actionsTd = document.createElement('td');
            actionsTd.className = 'align-middle';
            const wrap = document.createElement('div');
            wrap.className = 'd-flex justify-content-center table-actions';
            const kebab = document.createElement('button');
            kebab.type = 'button';
            kebab.className = 'btn btn-light border btn-sm';
            kebab.setAttribute('data-action-menu-trigger', '');
            kebab.setAttribute('aria-label', 'Acciones de la entrega');
            kebab.setAttribute('aria-expanded', 'false');
            kebab.innerHTML = '<i class="bi bi-three-dots-vertical"></i>';
            kebab.addEventListener('click', () => {
                ActionMenu.open(kebab, [
                    { action: 'show', icon: 'bi-eye', label: 'Ver detalle', className: 'text-info' },
                ], {
                    id: d.delivery_id,
                    name: d.sale_code,
                    onSelect: (item) => {
                        if (item.action === 'show') viewDetail(item.id);
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
            const params = { page, per_page: document.getElementById('perPageSelect').value };
            let res;
            if (listMode === 'pending') {
                res = await API.getPending(params);
            } else {
                const filters = getFilters();
                Object.assign(params, filters);
                res = await API.getData(params);
            }
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
        status: { label: 'Estado', inputId: 'statusFilter' },
        employee_id: { label: 'Empleado', inputId: 'employeeFilter' },
        date_from: { label: 'Fecha desde', inputId: 'dateFrom' },
        date_to: { label: 'Fecha hasta', inputId: 'dateTo' },
    };

    let lastFilters = {};

    function renderFilterChips() {
        FilterBar.renderChips({
            chipsId: 'filterChips',
            badgeId: 'filterBadge',
            chips: FilterBar.buildChips(lastFilters, FILTER_CHIPS),
            onRemove: () => {
                applyFilters();
            },
        });
    }

    function getFilters() {
        const filters = {};
        const search = document.getElementById('searchInput').value.trim();
        if (search) filters.search = search;
        const status = document.getElementById('statusFilter').value;
        if (status) filters.status = status;
        const employee = document.getElementById('employeeFilter').value;
        if (employee) filters.employee_id = employee;
        const dateFrom = document.getElementById('dateFrom').value;
        if (dateFrom) filters.date_from = dateFrom;
        const dateTo = document.getElementById('dateTo').value;
        if (dateTo) filters.date_to = dateTo;
        return filters;
    }

    function applyFilters() {
        if (listMode === 'pending') {
            setListMode('all');
        }
        lastFilters = getFilters();
        FilterBar.closeOffcanvas('filterOffcanvas');
        renderFilterChips();
        loadPage(1);
    }

    function clearFilters() {
        ['statusFilter', 'employeeFilter', 'dateFrom', 'dateTo'].forEach(id => {
            document.getElementById(id).value = '';
        });
        FilterBar.closeOffcanvas('filterOffcanvas');
        applyFilters();
    }

    function setListMode(mode) {
        listMode = mode;
        const btn = document.getElementById('btnPending');
        if (mode === 'pending') {
            btn.classList.add('btn-warning');
            btn.classList.remove('btn-outline-warning');
        } else {
            btn.classList.remove('btn-warning');
            btn.classList.add('btn-outline-warning');
        }
    }

    async function viewDetail(id) {
        showLoading(true);
        try {
            const res = await API.get(id);
            if (!res.success) { showAlert(res.message || 'Error al cargar detalle.', 'danger'); return; }
            const d = res.data;
            const delivery = d.delivery;
            const products = d.products || [];
            const employees = d.employees || [];

            const paymentBadges = { pending: 'bg-warning text-dark', paid: 'bg-success', failed: 'bg-danger', refunded: 'bg-info' };

            const address = delivery.address;
            const customer = delivery.customer;
            const employee = delivery.employee;

            let html = '<div class="row">';

            html += '<div class="col-md-6 mb-3">';
            html += '<table class="table table-sm table-borderless mb-0">';
            html += '<tr><td class="text-muted">Entrega</td><td class="fw-bold">#' + delivery.delivery_id + '</td></tr>';
            html += '<tr><td class="text-muted">Folio de venta</td><td class="fw-bold">' + delivery.sale_code + '</td></tr>';
            html += '<tr><td class="text-muted">Fecha de venta</td><td>' + formatDateTime(delivery.sale_date) + '</td></tr>';
            html += '<tr><td class="text-muted">Estado entrega</td><td><span class="badge bg-info">' + StatusTranslator.deliveryStatus(delivery.status) + '</span></td></tr>';
            html += '<tr><td class="text-muted">Pago</td><td><span class="badge ' + (paymentBadges[delivery.payment_status] || 'bg-secondary') + '">' + StatusTranslator.paymentStatus(delivery.payment_status) + '</span></td></tr>';
            html += '<tr><td class="text-muted">Total</td><td class="fw-bold">$' + delivery.total.toFixed(2) + '</td></tr>';
            html += '</table></div>';

            html += '<div class="col-md-6 mb-3">';
            html += '<h6 class="text-muted">Cliente</h6>';
            html += '<p class="mb-1">' + (customer.full_name || '&mdash;') + '</p>';
            html += '<p class="mb-1 small text-muted">' + customer.email + '</p>';
            html += '<p class="mb-0 small text-muted">' + (customer.phone || '') + '</p>';
            html += '<h6 class="text-muted mt-3">Dirección de envío</h6>';
            if (address) {
                const parts = [address.street, address.number, address.neighborhood, address.municipality, address.state, address.zip_code].filter(Boolean);
                html += '<p class="mb-0 small">' + parts.join(', ') + '</p>';
            } else {
                html += '<p class="text-muted small mb-0">No disponible</p>';
            }
            html += '</div></div>';

            html += '<h6 class="text-muted mt-3">Productos</h6>';
            html += '<div class="table-responsive"><table class="table table-sm"><thead><tr><th>Producto</th><th class="text-center">Cant.</th><th class="text-end">Precio</th><th class="text-end">Dto.</th><th class="text-end">Subtotal</th></tr></thead><tbody>';
            products.forEach(det => {
                html += '<tr><td>' + (det.product_name || 'Producto') + '</td><td class="text-center">' + det.quantity + '</td><td class="text-end">$' + det.unit_price.toFixed(2) + '</td>';
                if (det.discount_percentage > 0) {
                    html += '<td class="text-end">-' + det.discount_percentage + '%</td>';
                } else {
                    html += '<td class="text-end text-muted">&mdash;</td>';
                }
                html += '<td class="text-end">$' + det.subtotal.toFixed(2) + '</td></tr>';
            });
            html += '</tbody>';
            html += '<tfoot><tr><th colspan="4" class="text-end">Total</th><th class="text-end">$' + delivery.total.toFixed(2) + '</th></tr>';
            html += '</tfoot></table></div>';

            html += '<h6 class="text-muted mt-3">Administrar entrega</h6>';
            html += '<div class="row"><div class="col-md-6">';
            html += '<div class="mb-2"><label class="form-label small">Empleado responsable</label>';
            html += '<div class="input-group input-group-sm"><select class="form-select" id="empSelect_' + delivery.delivery_id + '">';
            html += '<option value="">Seleccionar...</option>';
            employees.forEach(e => {
                html += '<option value="' + e.user_id + '" ' + (delivery.user_id === e.user_id ? 'selected' : '') + '>' + e.name + '</option>';
            });
            html += '</select><button class="btn btn-primary" onclick="assignEmp(' + delivery.delivery_id + ')"><i class="bi bi-check"></i></button></div></div>';

            html += '<div class="mb-2"><label class="form-label small">Estado de entrega</label>';
            html += '<div class="input-group input-group-sm"><select class="form-select" id="delStatusSelect_' + delivery.delivery_id + '">';
            ['pending', 'preparing', 'shipped', 'delivered', 'cancelled'].forEach(st => {
                html += '<option value="' + st + '" ' + (delivery.status === st ? 'selected' : '') + '>' + StatusTranslator.deliveryStatus(st) + '</option>';
            });
            html += '</select><button class="btn btn-primary" onclick="updateDelStatus(' + delivery.delivery_id + ')"><i class="bi bi-check"></i></button></div></div>';
            html += '</div>';

            html += '<div class="col-md-6">';
            html += '<div class="mb-2"><label class="form-label small">Fecha de envío</label>';
            html += '<div class="input-group input-group-sm"><input type="datetime-local" class="form-control" id="shipDate_' + delivery.delivery_id + '" value="' + (delivery.shipping_date ? delivery.shipping_date.substring(0, 16) : '') + '">';
            html += '<button class="btn btn-primary" onclick="updateShipDate(' + delivery.delivery_id + ')"><i class="bi bi-check"></i></button></div></div>';

            html += '<div class="mb-2"><label class="form-label small">Fecha de entrega</label>';
            html += '<div class="input-group input-group-sm"><input type="datetime-local" class="form-control" id="delDate_' + delivery.delivery_id + '" value="' + (delivery.delivery_date ? delivery.delivery_date.substring(0, 16) : '') + '">';
            html += '<button class="btn btn-primary" onclick="updateDelDate(' + delivery.delivery_id + ')"><i class="bi bi-check"></i></button></div></div>';
            html += '</div></div>';

            document.getElementById('detailModalBody').innerHTML = html;
            var modal = new bootstrap.Modal(document.getElementById('detailModal'));
            modal.show();
        } catch (e) {
            showAlert('Error al cargar detalle.', 'danger');
        } finally {
            showLoading(false);
        }
    }

    async function assignEmp(id) {
        const userId = document.getElementById('empSelect_' + id).value;
        if (!userId) { showAlert('Selecciona un empleado.', 'warning'); return; }
        showLoading(true);
        try {
            const res = await API.assignEmployee(id, parseInt(userId));
            if (res.success) {
                showAlert('Empleado asignado.', 'success');
                loadPage(currentPage);
            } else {
                mostrarErrorCampo(document.getElementById('empSelect_' + id), 'user_id', res.errors);
            }
        } catch (e) {
            showAlert('Error de conexión.', 'danger');
        } finally {
            showLoading(false);
        }
    }

    async function updateDelStatus(id) {
        const status = document.getElementById('delStatusSelect_' + id).value;
        if (!status) { showAlert('Selecciona un estado.', 'warning'); return; }
        showLoading(true);
        try {
            const res = await API.updateStatus(id, status);
            if (res.success) {
                showAlert('Estado de entrega actualizado.', 'success');
                loadPage(currentPage);
            } else {
                mostrarErrorCampo(document.getElementById('delStatusSelect_' + id), 'status', res.errors);
            }
        } catch (e) {
            showAlert('Error de conexión.', 'danger');
        } finally {
            showLoading(false);
        }
    }

    async function updateShipDate(id) {
        const date = document.getElementById('shipDate_' + id).value;
        if (!date) { showAlert('Ingresa una fecha.', 'warning'); return; }
        showLoading(true);
        try {
            const res = await API.updateShippingDate(id, date);
            if (res.success) {
                showAlert('Fecha de envío registrada.', 'success');
                loadPage(currentPage);
            } else {
                mostrarErrorCampo(document.getElementById('shipDate_' + id), 'shipping_date', res.errors);
            }
        } catch (e) {
            showAlert('Error de conexión.', 'danger');
        } finally {
            showLoading(false);
        }
    }

    async function updateDelDate(id) {
        const date = document.getElementById('delDate_' + id).value;
        if (!date) { showAlert('Ingresa una fecha.', 'warning'); return; }
        showLoading(true);
        try {
            const res = await API.updateDeliveryDate(id, date);
            if (res.success) {
                showAlert('Fecha de entrega registrada.', 'success');
                loadPage(currentPage);
            } else {
                mostrarErrorCampo(document.getElementById('delDate_' + id), 'delivery_date', res.errors);
            }
        } catch (e) {
            showAlert('Error de conexión.', 'danger');
        } finally {
            showLoading(false);
        }
    }

    document.getElementById('btnApplyFilters').addEventListener('click', applyFilters);
    document.getElementById('btnClearFilters').addEventListener('click', clearFilters);
    document.getElementById('perPageSelect').addEventListener('change', () => loadPage(1));
    document.getElementById('searchInput').addEventListener('keydown', e => { if (e.key === 'Enter') applyFilters(); });
    document.getElementById('btnClearSearch').addEventListener('click', () => { document.getElementById('searchInput').value = ''; applyFilters(); });
    document.getElementById('btnPending').addEventListener('click', () => {
        setListMode(listMode === 'pending' ? 'all' : 'pending');
        loadPage(1);
    });

    loadPage(1);
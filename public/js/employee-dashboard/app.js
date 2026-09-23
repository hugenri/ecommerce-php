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
        myDeliveries(params = {}) {
            const qs = new URLSearchParams(params).toString();
            return this.request('GET', '/employee/my-deliveries' + (qs ? '?' + qs : ''));
        },
        pending(params = {}) {
            const qs = new URLSearchParams(params).toString();
            return this.request('GET', '/employee/pending' + (qs ? '?' + qs : ''));
        },
        get(id) { return this.request('GET', '/employee/' + id); },
        take(id) { return this.request('POST', '/employee/' + id + '/take'); },
        updateStatus(id, status) { return this.request('PATCH', '/employee/' + id + '/status', { status }); },
        updateShippingDate(id, date) { return this.request('POST', '/employee/' + id + '/shipping-date', { shipping_date: date }); },
        updateDeliveryDate(id, date) { return this.request('POST', '/employee/' + id + '/delivery-date', { delivery_date: date }); },
    };

    const statusBadges = { pending: 'bg-warning text-dark', preparing: 'bg-info text-dark', shipped: 'bg-primary', delivered: 'bg-success', cancelled: 'bg-danger' };

    let minePage = 1;
    let pendingPage = 1;

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
        const wrapper = input.closest('.mb-2') || input.parentElement.parentElement;
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

    function addressText(address) {
        if (!address) return '<span class="text-muted">Sin direccion</span>';
        const parts = [address.street, address.number, address.neighborhood, address.municipality, address.state, address.zip_code].filter(Boolean);
        return parts.length ? parts.join(', ') : '<span class="text-muted">Sin direccion</span>';
    }

    function createButton(classes, title, iconClass, onClick) {
        const b = document.createElement('button');
        b.type = 'button';
        b.className = classes;
        b.title = title;
        b.innerHTML = '<i class="' + iconClass + '"></i>';
        b.addEventListener('click', onClick);
        return b;
    }

    function renderDeliveryRows(tbody, items, { actions }) {
        tbody.innerHTML = '';
        if (!items || items.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4"><i class="bi bi-inbox me-2"></i>No hay entregas.</td></tr>';
            return;
        }
        items.forEach(d => {
            const tr = document.createElement('tr');
            tr.innerHTML = '<td class="align-middle fw-medium">' + d.sale_code + '</td>' +
                '<td class="align-middle"><small>' + d.customer_name + '</small></td>' +
                '<td class="align-middle small">' + addressText(d.address) + '</td>' +
                '<td class="align-middle"><span class="badge ' + (statusBadges[d.delivery_status] || 'bg-secondary') + '">' + StatusTranslator.deliveryStatus(d.delivery_status) + '</span></td>' +
                '<td class="align-middle small">' + (d.sale_date || '&mdash;') + '</td>';
            const actionsTd = document.createElement('td');
            actionsTd.className = 'align-middle';
            const group = document.createElement('div');
            group.className = 'btn-group btn-group-sm';
            group.appendChild(createButton('btn btn-outline-info', 'Ver detalle', 'bi bi-eye', () => viewDetail(d.delivery_id)));
            actions(group, d);
            actionsTd.appendChild(group);
            tr.appendChild(actionsTd);
            tbody.appendChild(tr);
        });
    }

    function renderPagination(ul, info, meta, onPage) {
        info.textContent = meta.total + ' registro(s)';
        if (!meta || meta.last_page <= 1) {
            ul.innerHTML = '';
            return;
        }
        let html = '<li class="page-item ' + (meta.current_page <= 1 ? 'disabled' : '') + '"><a class="page-link" href="#" data-page="' + (meta.current_page - 1) + '">&laquo;</a></li>';
        for (let i = 1; i <= meta.last_page; i++) {
            html += '<li class="page-item ' + (meta.current_page === i ? 'active' : '') + '"><a class="page-link" href="#" data-page="' + i + '">' + i + '</a></li>';
        }
        html += '<li class="page-item ' + (meta.current_page >= meta.last_page ? 'disabled' : '') + '"><a class="page-link" href="#" data-page="' + (meta.current_page + 1) + '">&raquo;</a></li>';
        ul.innerHTML = html;
        ul.querySelectorAll('a.page-link').forEach(a => {
            a.addEventListener('click', e => {
                e.preventDefault();
                const page = parseInt(a.dataset.page);
                if (page > 0) onPage(page);
            });
        });
    }

    async function loadMine(page = 1) {
        minePage = page;
        try {
            const res = await API.myDeliveries({ page, per_page: document.getElementById('minePerPageSelect').value });
            if (res.success) {
                renderDeliveryRows(document.getElementById('myDeliveriesBody'), res.data, {
                    actions: (group, d) => {
                        if (d.delivery_status !== 'delivered') {
                            group.appendChild(createButton('btn btn-outline-primary', 'Actualizar estado', 'bi bi-arrow-right-circle', () => openStatusModal(d.delivery_id, d.delivery_status)));
                        }
                    }
                });
                renderPagination(document.getElementById('minePagination'), document.getElementById('minePaginationInfo'), res.meta, loadMine);
            } else {
                showAlert(res.message || 'Error al cargar.', 'danger');
            }
        } catch (e) {
            showAlert('Error de conexion.', 'danger');
        }
    }

    async function loadPending(page = 1) {
        pendingPage = page;
        try {
            const res = await API.pending({ page, per_page: document.getElementById('pendingPerPageSelect').value });
            if (res.success) {
                renderDeliveryRows(document.getElementById('pendingBody'), res.data, {
                    actions: (group, d) => {
                        group.appendChild(createButton('btn btn-success', 'Tomar pedido', 'bi bi-hand-index-thumb', () => takeDelivery(d.delivery_id)));
                    }
                });
                renderPagination(document.getElementById('pendingPagination'), document.getElementById('pendingPaginationInfo'), res.meta, loadPending);
            } else {
                showAlert(res.message || 'Error al cargar.', 'danger');
            }
        } catch (e) {
            showAlert('Error de conexion.', 'danger');
        }
    }

    async function takeDelivery(id) {
        showLoading(true);
        try {
            const res = await API.take(id);
            if (res.success) {
                showAlert('Pedido tomado correctamente.', 'success');
                loadMine(minePage);
                loadPending(pendingPage);
            } else {
                showAlert(res.message || 'Error al tomar pedido.', 'danger');
            }
        } catch (e) {
            showAlert('Error de conexion.', 'danger');
        } finally {
            showLoading(false);
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
            const paymentBadges = { pending: 'bg-warning text-dark', paid: 'bg-success', failed: 'bg-danger', refunded: 'bg-info' };
            const address = delivery.address;

            let html = '<div class="row">';

            html += '<div class="col-md-6 mb-3">';
            html += '<h6 class="text-muted">Informacion del cliente</h6>';
            html += '<p class="mb-1">' + (delivery.customer.full_name || '&mdash;') + '</p>';
            html += '<p class="mb-0 small text-muted">' + (delivery.customer.phone || '') + '</p>';
            html += '<h6 class="text-muted mt-3">Direccion</h6>';
            if (address) {
                const partsInit = [address.street, address.number, address.neighborhood, address.municipality, address.state, address.zip_code].filter(Boolean);
                html += '<p class="mb-0 small">' + partsInit.join(', ') + '</p>';
                if (address.reference) html += '<p class="mb-0 small text-muted">Ref: ' + address.reference + '</p>';
            } else {
                html += '<p class="text-muted small mb-0">No disponible</p>';
            }
            html += '</div>';

            html += '<div class="col-md-6 mb-3">';
            html += '<table class="table table-sm table-borderless mb-0">';
            html += '<tr><td class="text-muted">Folio</td><td class="fw-bold">' + delivery.sale_code + '</td></tr>';
            html += '<tr><td class="text-muted">Fecha</td><td>' + (delivery.sale_date || '&mdash;') + '</td></tr>';
            html += '<tr><td class="text-muted">Metodo de pago</td><td>' + (delivery.payment_method || '&mdash;') + '</td></tr>';
            html += '<tr><td class="text-muted">Estado del pago</td><td><span class="badge ' + (paymentBadges[delivery.payment_status] || 'bg-secondary') + '">' + StatusTranslator.paymentStatus(delivery.payment_status) + '</span></td></tr>';
            html += '<tr><td class="text-muted">Estado entrega</td><td><span class="badge ' + (statusBadges[delivery.status] || 'bg-secondary') + '">' + StatusTranslator.deliveryStatus(delivery.status) + '</span></td></tr>';
            html += '<tr><td class="text-muted">Total</td><td class="fw-bold">$' + delivery.total.toFixed(2) + '</td></tr>';
            html += '</table></div></div>';

            html += '<h6 class="text-muted">Productos</h6>';
            html += '<div class="table-responsive"><table class="table table-sm"><thead><tr><th>Producto</th><th class="text-center">Cant.</th><th class="text-end">Subtotal</th></tr></thead><tbody>';
            products.forEach(p => {
                html += '<tr><td>' + (p.product_name || 'Producto') + '</td><td class="text-center">' + p.quantity + '</td><td class="text-end">$' + p.subtotal.toFixed(2) + '</td></tr>';
            });
            html += '</tbody><tfoot><tr><th colspan="2" class="text-end">Total</th><th class="text-end">$' + delivery.total.toFixed(2) + '</th></tr></tfoot></table></div>';

            html += '<h6 class="text-muted mt-3">Administrar entrega</h6>';
            html += '<div class="row"><div class="col-md-6">';
            html += '<div class="mb-2"><label class="form-label small">Fecha de envio</label>';
            html += '<div class="input-group input-group-sm"><input type="datetime-local" class="form-control" id="shipDate_' + delivery.delivery_id + '" value="' + (delivery.shipping_date ? delivery.shipping_date.substring(0, 16) : '') + '">';
            html += '<button class="btn btn-primary" onclick="updateShipDate(' + delivery.delivery_id + ')"><i class="bi bi-check"></i></button></div></div>';
            html += '<div class="mb-2"><label class="form-label small">Fecha de entrega</label>';
            html += '<div class="input-group input-group-sm"><input type="datetime-local" class="form-control" id="delDate_' + delivery.delivery_id + '" value="' + (delivery.delivery_date ? delivery.delivery_date.substring(0, 16) : '') + '">';
            html += '<button class="btn btn-primary" onclick="updateDelDate(' + delivery.delivery_id + ')"><i class="bi bi-check"></i></button></div></div>';
            html += '</div></div>';

            document.getElementById('detailModalBody').innerHTML = html;
            new bootstrap.Modal(document.getElementById('detailModal')).show();
        } catch (e) {
            showAlert('Error al cargar detalle.', 'danger');
        } finally {
            showLoading(false);
        }
    }

    function openStatusModal(id, current) {
        const options = ['pending', 'preparing', 'shipped', 'delivered'];
        const html = '<p class="text-muted">Avanzar al siguiente estado:</p>' +
            '<select class="form-select" id="statusSelect_' + id + '">' +
            options.map(s => '<option value="' + s + '" ' + (s === current ? 'selected' : '') + '>' + StatusTranslator.deliveryStatus(s) + '</option>').join('') +
            '</select>' +
            '<button class="btn btn-primary w-100 mt-3" onclick="updateDelStatus(' + id + ')"><i class="bi bi-check me-1"></i>Actualizar</button>';
        document.getElementById('detailModalBody').innerHTML = html;
        new bootstrap.Modal(document.getElementById('detailModal')).show();
    }

    async function updateDelStatus(id) {
        const status = document.getElementById('statusSelect_' + id).value;
        showLoading(true);
        try {
            const res = await API.updateStatus(id, status);
            if (res.success) {
                showAlert('Estado actualizado.', 'success');
                bootstrap.Modal.getInstance(document.getElementById('detailModal')).hide();
                loadMine(minePage);
            } else {
                mostrarErrorCampo(document.getElementById('statusSelect_' + id), 'status', res.errors);
            }
        } catch (e) {
            showAlert('Error de conexion.', 'danger');
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
                showAlert('Fecha de envio registrada.', 'success');
                loadMine(minePage);
            } else {
                mostrarErrorCampo(document.getElementById('shipDate_' + id), 'shipping_date', res.errors);
            }
        } catch (e) {
            showAlert('Error de conexion.', 'danger');
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
                loadMine(minePage);
            } else {
                mostrarErrorCampo(document.getElementById('delDate_' + id), 'delivery_date', res.errors);
            }
        } catch (e) {
            showAlert('Error de conexion.', 'danger');
        } finally {
            showLoading(false);
        }
    }

    document.getElementById('btnRefreshMine').addEventListener('click', () => loadMine(minePage));
    document.getElementById('btnRefreshPending').addEventListener('click', () => loadPending(pendingPage));
    document.getElementById('minePerPageSelect').addEventListener('change', () => loadMine(1));
    document.getElementById('pendingPerPageSelect').addEventListener('change', () => loadPending(1));

    loadMine(1);
    loadPending(1);
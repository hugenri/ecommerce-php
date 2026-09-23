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
            return this.request('GET', '/customers/data' + (qs ? '?' + qs : ''));
        },
        get(id) { return this.request('GET', '/customers/' + id); },
        activate(id) { return this.request('PATCH', '/customers/' + id + '/activate'); },
        deactivate(id) { return this.request('PATCH', '/customers/' + id + '/deactivate'); },
    };

    let currentPage = 1;
    let currentFilters = {};
    let pendingAction = null;

    function showAlert(message, type = 'success') {
        const container = document.getElementById('alertContainer');
        const alert = document.createElement('div');
        alert.className = `alert alert-${type} alert-dismissible fade show`;
        alert.innerHTML = `${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
        container.appendChild(alert);
        setTimeout(() => alert.remove(), 4000);
    }

    function showLoading(show) {
        document.getElementById('loadingOverlay').style.display = show ? 'flex' : 'none';
    }

    function formatDateTime(val) {
        if (!val) return '<span class="text-muted">—</span>';
        return val;
    }

    function renderTable(items) {
        const tbody = document.getElementById('tableBody');
        tbody.innerHTML = '';
        if (!items || items.length === 0) {
            const emptyRow = document.createElement('tr');
            emptyRow.innerHTML = '<td colspan="7" class="text-center text-muted py-4"><i class="bi bi-inbox me-2"></i>No hay clientes.</td>';
            tbody.appendChild(emptyRow);
            return;
        }

        items.forEach(c => {
            const fullName = c.full_name || `${c.first_name || ''} ${c.last_name_paternal || ''}${c.last_name_maternal ? ' ' + c.last_name_maternal : ''}`.trim();
            const tr = document.createElement('tr');

            const nameTd = document.createElement('td');
            nameTd.className = 'align-middle fw-medium';
            nameTd.textContent = fullName;
            tr.appendChild(nameTd);

            const emailTd = document.createElement('td');
            emailTd.className = 'align-middle';
            emailTd.textContent = c.email;
            tr.appendChild(emailTd);

            const phoneTd = document.createElement('td');
            phoneTd.className = 'align-middle';
            phoneTd.innerHTML = c.phone ? c.phone : '<span class="text-muted">—</span>';
            tr.appendChild(phoneTd);

            const badgeTd = document.createElement('td');
            badgeTd.className = 'align-middle';
            const badge = document.createElement('span');
            badge.className = c.is_active ? 'badge bg-success' : 'badge bg-secondary';
            badge.textContent = StatusTranslator.boolean(c.is_active);
            badgeTd.appendChild(badge);
            tr.appendChild(badgeTd);

            const createdTd = document.createElement('td');
            createdTd.className = 'align-middle small';
            createdTd.innerHTML = formatDateTime(c.created_at);
            tr.appendChild(createdTd);

            const loginTd = document.createElement('td');
            loginTd.className = 'align-middle small';
            loginTd.innerHTML = formatDateTime(c.last_login);
            tr.appendChild(loginTd);

            const actionsTd = document.createElement('td');
            actionsTd.className = 'align-middle';
            const wrap = document.createElement('div');
            wrap.className = 'd-flex justify-content-center table-actions';
            const kebab = document.createElement('button');
            kebab.type = 'button';
            kebab.className = 'btn btn-light border btn-sm';
            kebab.setAttribute('data-action-menu-trigger', '');
            kebab.setAttribute('aria-label', 'Acciones del cliente');
            kebab.setAttribute('aria-expanded', 'false');
            kebab.innerHTML = '<i class="bi bi-three-dots-vertical"></i>';
            kebab.addEventListener('click', () => {
                const items = [
                    { action: 'show', icon: 'bi-eye', label: 'Ver detalle', className: 'text-info' },
                    c.is_active
                        ? { action: 'deactivate', icon: 'bi-pause-circle', label: 'Desactivar', className: 'text-warning' }
                        : { action: 'activate', icon: 'bi-play-circle', label: 'Activar', className: 'text-success' },
                ];
                ActionMenu.open(kebab, items, {
                    id: c.customer_id,
                    name: fullName,
                    onSelect: (item) => {
                        if (item.action === 'show') {
                            viewDetail(item.id);
                        } else if (item.action === 'deactivate') {
                            confirmAction(
                                'Desactivar cliente',
                                `¿Está seguro de desactivar el cliente <strong>${item.name}</strong>?`,
                                'Desactivar',
                                'btn-warning',
                                async () => {
                                    const r = await API.deactivate(item.id);
                                    if (r.success) {
                                        showAlert(r.message, 'success');
                                        bootstrap.Modal.getInstance(document.getElementById('confirmModal')).hide();
                                        loadPage(currentPage);
                                    } else {
                                        showAlert(r.message || 'Error.', 'danger');
                                    }
                                }
                            );
                        } else if (item.action === 'activate') {
                            confirmAction(
                                'Activar cliente',
                                `¿Está seguro de activar el cliente <strong>${item.name}</strong>?`,
                                'Activar',
                                'btn-success',
                                async () => {
                                    const r = await API.activate(item.id);
                                    if (r.success) {
                                        showAlert(r.message, 'success');
                                        bootstrap.Modal.getInstance(document.getElementById('confirmModal')).hide();
                                        loadPage(currentPage);
                                    } else {
                                        showAlert(r.message || 'Error.', 'danger');
                                    }
                                }
                            );
                        }
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
        const pag = document.getElementById('pagination');
        if (!meta || meta.total <= 0) {
            info.textContent = '';
            pag.innerHTML = '';
            return;
        }
        info.textContent = `Mostrando ${meta.from || 0} - ${meta.to || 0} de ${meta.total} clientes`;
        const total = meta.last_page || 1;
        const current = meta.current_page || 1;
        let html = '';
        html += `<li class="page-item ${current <= 1 ? 'disabled' : ''}"><a class="page-link" href="#" data-page="${current - 1}">&laquo;</a></li>`;
        for (let i = 1; i <= total; i++) {
            if (i === 1 || i === total || (i >= current - 2 && i <= current + 2)) {
                html += `<li class="page-item ${i === current ? 'active' : ''}"><a class="page-link" href="#" data-page="${i}">${i}</a></li>`;
            } else if (i === current - 3 || i === current + 3) {
                html += `<li class="page-item disabled"><a class="page-link">...</a></li>`;
            }
        }
        html += `<li class="page-item ${current >= total ? 'disabled' : ''}"><a class="page-link" href="#" data-page="${current + 1}">&raquo;</a></li>`;
        pag.innerHTML = html;
        pag.querySelectorAll('[data-page]').forEach(el => {
            el.addEventListener('click', e => {
                e.preventDefault();
                loadPage(parseInt(el.dataset.page));
            });
        });
    }

    async function loadPage(page = 1) {
        currentPage = page;
        showLoading(true);
        try {
            const params = { page, per_page: document.getElementById('perPageSelect').value, ...currentFilters };
            const res = await API.getData(params);
            if (res.success) {
                renderTable(res.data);
                renderPagination(res.meta);
            } else {
                showAlert(res.message || 'Error al cargar clientes.', 'danger');
            }
        } catch (e) {
            showAlert('Error de conexión.', 'danger');
        } finally {
            showLoading(false);
        }
    }

    const FILTER_CHIPS = {
        active: { label: 'Estado', inputId: 'statusFilter' },
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
        if (status !== '') currentFilters.active = status;
        FilterBar.closeOffcanvas('filterOffcanvas');
        renderFilterChips();
        loadPage(1);
    }

    function clearFilters() {
        document.getElementById('statusFilter').value = '';
        FilterBar.closeOffcanvas('filterOffcanvas');
        applyFilters();
    }

    function confirmAction(title, body, btnText, btnClass, actionFn) {
        document.getElementById('confirmModalTitle').textContent = title;
        document.getElementById('confirmModalBody').innerHTML = body;
        const btn = document.getElementById('btnConfirmAction');
        btn.textContent = btnText;
        btn.className = 'btn ' + btnClass;
        pendingAction = actionFn;
        new bootstrap.Modal(document.getElementById('confirmModal')).show();
    }

    document.getElementById('btnConfirmAction').addEventListener('click', async () => {
        if (!pendingAction) return;
        showLoading(true);
        try {
            await pendingAction();
        } catch (e) {
            showAlert('Error de conexión.', 'danger');
        } finally {
            showLoading(false);
            pendingAction = null;
        }
    });

    async function viewDetail(id) {
        const body = document.getElementById('detailModalBody');
        body.innerHTML = '<div class="text-center py-4"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Cargando...</span></div></div>';
        document.getElementById('detailModalTitle').textContent = 'Detalle del Cliente';
        new bootstrap.Modal(document.getElementById('detailModal')).show();

        try {
            const res = await API.get(id);
            if (res.success && res.data) {
                const c = res.data;
                const badge = `<span class="badge ${c.is_active ? 'bg-success' : 'bg-secondary'}">${StatusTranslator.boolean(c.is_active)}</span>`;
                const fullName = c.full_name || c.first_name + ' ' + (c.last_name_paternal || '') + (c.last_name_maternal ? ' ' + c.last_name_maternal : '');
                body.innerHTML = `
                    <div class="text-center mb-4">
                        <div class="bg-light rounded-circle d-inline-flex align-items-center justify-content-center" style="width: 80px; height: 80px;">
                            <i class="bi bi-person fs-1 text-secondary"></i>
                        </div>
                        <h5 class="mt-2">${fullName}</h5>
                        <div>${badge}</div>
                    </div>
                    <table class="table table-sm">
                        <tr><th class="text-muted w-25">Email</th><td>${c.email}</td></tr>
                        <tr><th class="text-muted">Teléfono</th><td>${c.phone || '<span class="text-muted">—</span>'}</td></tr>
                        <tr><th class="text-muted">Nombre</th><td>${c.first_name} ${c.last_name_paternal}${c.last_name_maternal ? ' ' + c.last_name_maternal : ''}</td></tr>
                        <tr><th class="text-muted">Registro</th><td>${c.created_at || '<span class="text-muted">—</span>'}</td></tr>
                        <tr><th class="text-muted">Último acceso</th><td>${c.last_login || '<span class="text-muted">—</span>'}</td></tr>
                        <tr><th class="text-muted">Actualización</th><td>${c.updated_at || '<span class="text-muted">—</span>'}</td></tr>
                    </table>
                `;
            } else {
                body.innerHTML = '<div class="alert alert-danger">Cliente no encontrado.</div>';
            }
        } catch (e) {
            body.innerHTML = '<div class="alert alert-danger">Error de conexión.</div>';
        }
    }

    document.getElementById('btnApplyFilters').addEventListener('click', applyFilters);
    document.getElementById('btnClearFilters').addEventListener('click', clearFilters);
    document.getElementById('perPageSelect').addEventListener('change', () => loadPage(1));
    document.getElementById('searchInput').addEventListener('keydown', e => { if (e.key === 'Enter') applyFilters(); });
    document.getElementById('btnClearSearch').addEventListener('click', () => {
        document.getElementById('searchInput').value = '';
        applyFilters();
    });

    loadPage(1);
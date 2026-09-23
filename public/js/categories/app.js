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
            return this.request('GET', '/categories/data' + (qs ? '?' + qs : ''));
        },
        get(id) { return this.request('GET', '/categories/' + id); },
        create(data) { return this.request('POST', '/categories', data); },
        update(id, data) { return this.request('PUT', '/categories/' + id, data); },
        delete(id) { return this.request('DELETE', '/categories/' + id); },
        activate(id) { return this.request('PATCH', '/categories/' + id + '/activate'); },
        deactivate(id) { return this.request('PATCH', '/categories/' + id + '/deactivate'); }
    };

    let currentPage = 1;
    let currentFilters = {};
    let pendingAction = null;
    let categoriesCache = [];

    const categoryForm = document.getElementById('categoryForm');

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

    function renderTable(categories) {
        const tbody = document.getElementById('categoriesBody');
        tbody.innerHTML = '';
        if (!categories || categories.length === 0) {
            const emptyRow = document.createElement('tr');
            emptyRow.innerHTML = '<td colspan="6" class="text-center text-muted py-4"><i class="bi bi-inbox me-2"></i>No hay categorías.</td>';
            tbody.appendChild(emptyRow);
            return;
        }

        categories.forEach(c => {
            const tr = document.createElement('tr');

            const imgTd = document.createElement('td');
            imgTd.className = 'align-middle';
            if (c.image) {
                const img = document.createElement('img');
                img.src = c.image;
                img.className = 'category-image me-2';
                img.alt = '';
                imgTd.appendChild(img);
            } else {
                const icon = document.createElement('i');
                icon.className = 'bi bi-card-image text-muted fs-4';
                imgTd.appendChild(icon);
            }
            tr.appendChild(imgTd);

            const nameTd = document.createElement('td');
            nameTd.className = 'align-middle fw-medium';
            nameTd.textContent = c.name;
            tr.appendChild(nameTd);

            const descTd = document.createElement('td');
            descTd.className = 'align-middle text-truncate';
            descTd.style.maxWidth = '200px';
            descTd.textContent = c.description ? c.description.substring(0, 60) + (c.description.length > 60 ? '...' : '') : '—';
            tr.appendChild(descTd);

            const badgeTd = document.createElement('td');
            badgeTd.className = 'align-middle';
            const badge = document.createElement('span');
            badge.className = c.is_active ? 'badge bg-success category-badge' : 'badge bg-secondary category-badge';
            badge.textContent = StatusTranslator.boolean(c.is_active);
            badgeTd.appendChild(badge);
            tr.appendChild(badgeTd);

            const dateTd = document.createElement('td');
            dateTd.className = 'align-middle text-muted small';
            dateTd.textContent = c.created_at || '—';
            tr.appendChild(dateTd);

            const actionsTd = document.createElement('td');
            actionsTd.className = 'align-middle';
            const wrap = document.createElement('div');
            wrap.className = 'd-flex justify-content-center table-actions';
            const kebab = document.createElement('button');
            kebab.type = 'button';
            kebab.className = 'btn btn-light border btn-sm';
            kebab.setAttribute('data-category-menu', c.category_id);
            kebab.setAttribute('data-action-menu-trigger', '');
            kebab.setAttribute('aria-label', 'Acciones de categoría');
            kebab.setAttribute('aria-expanded', 'false');
            kebab.innerHTML = '<i class="bi bi-three-dots-vertical"></i>';
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
        info.textContent = `Mostrando ${meta.from || 0} - ${meta.to || 0} de ${meta.total} categorías`;
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
                categoriesCache = Array.isArray(res.data) ? res.data : [];
                renderTable(res.data);
                renderPagination(res.meta);
            } else {
                showAlert(res.message || 'Error al cargar categorías.', 'danger');
            }
        } catch (e) {
            showAlert('Error de conexión.', 'danger');
        } finally {
            showLoading(false);
        }
    }

    const FILTER_CHIPS = {
        status: { label: 'Estado', inputId: 'statusFilter' },
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

    function showFormValidationError(res, form) {
        if (res.errors && typeof res.errors === 'object' && !Array.isArray(res.errors)
            && Object.keys(res.errors).length > 0) {
            window.FormValidation.limpiarErrores(form);
            window.FormValidation.mostrarErrores(form, res.errors);
            return;
        }
        showAlert(res.message || 'Error al guardar.', 'danger');
    }

    function recalcularEstadoBoton() {
        categoryForm.querySelectorAll('input:not([type="hidden"]), select, textarea').forEach((input) => {
            input.dispatchEvent(new Event('input', { bubbles: true }));
        });
    }

    async function editCategory(id) {
        showLoading(true);
        try {
            const res = await API.get(id);
            if (res.success && res.data) {
                document.getElementById('modalTitle').textContent = 'Editar Categoría';
                document.getElementById('categoryId').value = res.data.category_id;
                document.getElementById('nameInput').value = res.data.name;
                document.getElementById('descriptionInput').value = res.data.description;
                document.getElementById('imageInput').value = res.data.image;
                if (window.FormValidation) {
                    window.FormValidation.limpiarErrores(categoryForm);
                }
                recalcularEstadoBoton();
                new bootstrap.Modal(document.getElementById('categoryModal')).show();
            } else {
                showAlert('Categoría no encontrada.', 'danger');
            }
        } catch (e) {
            showAlert('Error de conexión.', 'danger');
        } finally {
            showLoading(false);
        }
    }

    document.getElementById('btnCreateCategory').addEventListener('click', () => {
        document.getElementById('modalTitle').textContent = 'Nueva Categoría';
        categoryForm.reset();
        document.getElementById('categoryId').value = '';
        if (window.FormValidation) {
            window.FormValidation.limpiarErrores(categoryForm);
        }
        recalcularEstadoBoton();
        new bootstrap.Modal(document.getElementById('categoryModal')).show();
    });

    categoryForm.addEventListener('submit', async (e) => {
        e.preventDefault();

        if (window.FormValidation && !window.FormValidation.formularioEsValido(categoryForm)) {
            categoryForm.querySelectorAll('input:not([type="hidden"]), select, textarea').forEach((input) => {
                if (input.required && !input.value) {
                    window.FormValidation.marcarError(input);
                } else if (input.value && !input.checkValidity()) {
                    window.FormValidation.marcarError(input);
                }
            });
            return;
        }

        const id = document.getElementById('categoryId').value;
        const data = {
            name: document.getElementById('nameInput').value.trim(),
            description: document.getElementById('descriptionInput').value.trim(),
            image: document.getElementById('imageInput').value.trim() || null,
        };
        if (id) {
            confirmAction('Guardar cambios', '¿Está seguro de guardar los cambios en esta categoría?', 'Guardar', 'btn-primary', async () => {
                showLoading(true);
                try {
                    const res = await API.update(id, data);
                    if (res.success) {
                        showAlert(res.message, 'success');
                        bootstrap.Modal.getInstance(document.getElementById('confirmModal')).hide();
                        bootstrap.Modal.getInstance(document.getElementById('categoryModal')).hide();
                        loadPage(currentPage);
                    } else {
                        showFormValidationError(res, categoryForm);
                    }
                } finally {
                    showLoading(false);
                }
            });
            return;
        }
        showLoading(true);
        try {
            const res = await API.create(data);
            if (res.success) {
                showAlert(res.message, 'success');
                bootstrap.Modal.getInstance(document.getElementById('categoryModal')).hide();
                loadPage(currentPage);
            } else {
                showFormValidationError(res, categoryForm);
            }
        } catch (e) {
            showAlert('Error de conexión.', 'danger');
        } finally {
            showLoading(false);
        }
    });

    document.getElementById('btnApplyFilters').addEventListener('click', applyFilters);
    document.getElementById('btnClearFilters').addEventListener('click', clearFilters);
    document.getElementById('perPageSelect').addEventListener('change', () => loadPage(1));
    document.getElementById('searchInput').addEventListener('keydown', e => { if (e.key === 'Enter') applyFilters(); });
    document.getElementById('btnClearSearch').addEventListener('click', () => {
        document.getElementById('searchInput').value = '';
        applyFilters();
    });

    function requestToggleCategory(category) {
        confirmAction(
            category.is_active ? 'Desactivar categoría' : 'Activar categoría',
            `¿Está seguro de ${category.is_active ? 'desactivar' : 'activar'} la categoría <strong>${category.name}</strong>?`,
            category.is_active ? 'Desactivar' : 'Activar',
            category.is_active ? 'btn-warning' : 'btn-success',
            async () => {
                const r = category.is_active ? await API.deactivate(category.category_id) : await API.activate(category.category_id);
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

    function requestDeleteCategory(category) {
        confirmAction(
            'Eliminar categoría',
            `¿Está seguro de eliminar la categoría <strong>${category.name}</strong>?`,
            'Eliminar',
            'btn-danger',
            async () => {
                const r = await API.delete(category.category_id);
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

    function handleCategoryAction(item) {
        const category = categoriesCache.find((row) => String(row.category_id) === String(item.id));
        if (!category) return;
        if (item.action === 'edit') {
            editCategory(category.category_id);
        } else if (item.action === 'toggle') {
            requestToggleCategory(category);
        } else if (item.action === 'delete') {
            requestDeleteCategory(category);
        }
    }

    document.addEventListener('click', (e) => {
        const trigger = e.target.closest('[data-category-menu]');
        if (!trigger) return;
        const category = categoriesCache.find((row) => String(row.category_id) === String(trigger.dataset.categoryMenu));
        if (!category) return;
        ActionMenu.open(trigger, [
            { action: 'edit', icon: 'bi-pencil', label: 'Editar', className: 'text-primary' },
            category.is_active
                ? { action: 'toggle', icon: 'bi-pause-circle', label: 'Desactivar', className: 'text-warning' }
                : { action: 'toggle', icon: 'bi-play-circle', label: 'Activar', className: 'text-success' },
            { divider: true },
            { action: 'delete', icon: 'bi-trash', label: 'Eliminar', className: 'text-danger' },
        ], {
            id: category.category_id,
            name: category.name,
            onSelect: handleCategoryAction,
        });
    });

    loadPage(1);
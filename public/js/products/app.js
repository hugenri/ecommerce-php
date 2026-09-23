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
            return this.request('GET', '/products/data' + (qs ? '?' + qs : ''));
        },
        getCategories() { return this.request('GET', '/categories/data?per_page=1000'); },
        getSubcategories(params = {}) {
            const qs = new URLSearchParams(params).toString();
            return this.request('GET', '/subcategories/data' + (qs ? '?' + qs : ''));
        },
        get(id) { return this.request('GET', '/products/' + id); },
        create(data) { return this.request('POST', '/products', data); },
        update(id, data) { return this.request('PUT', '/products/' + id, data); },
        delete(id) { return this.request('DELETE', '/products/' + id); },
        activate(id) { return this.request('PATCH', '/products/' + id + '/activate'); },
        deactivate(id) { return this.request('PATCH', '/products/' + id + '/deactivate'); },
        updatePrice(id, price) { return this.request('POST', '/products/' + id + '/price', { price }); },
        updateDiscount(id, discount) { return this.request('POST', '/products/' + id + '/discount', { discount }); },
        addImage(id, image) { return this.request('POST', '/products/' + id + '/images', { image }); },
        removeImage(productId, imageId) { return this.request('DELETE', '/products/' + productId + '/images/' + imageId); },
    };

    let currentPage = 1;
    let currentFilters = {};
    let pendingAction = null;
    let categoriesCache = [];
    let subcategoriesCache = [];
    let currentImageProductId = null;
    let productsCache = [];

    const formEl = document.getElementById('form');
    const priceFormEl = document.getElementById('priceForm');
    const discountFormEl = document.getElementById('discountForm');

    function showFormValidationError(form, res) {
        if (res.errors && typeof res.errors === 'object' && !Array.isArray(res.errors)
            && Object.keys(res.errors).length > 0) {
            window.FormValidation.limpiarErrores(form);
            window.FormValidation.mostrarErrores(form, res.errors);
            return;
        }
        showAlert(res.message || 'Error al guardar.', 'danger');
    }

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

    function getCategoryName(id) {
        const c = categoriesCache.find(c => c.category_id === id);
        return c ? c.name : 'ID ' + id;
    }

    function getSubcategoryName(id) {
        const s = subcategoriesCache.find(s => s.subcategory_id === id);
        return s ? s.name : 'ID ' + id;
    }

    function formatPrice(val) {
        if (val === null || val === undefined) return '—';
        return '$' + parseFloat(val).toFixed(2);
    }

    function formatDiscount(val) {
        if (val === null || val === undefined || val == 0) return '—';
        return parseFloat(val).toFixed(1) + '%';
    }

    async function loadCategories() {
        const res = await API.getCategories();
        if (res.success && res.data) {
            categoriesCache = res.data;
            const sel = document.getElementById('categoryFilter');
            const sel2 = document.getElementById('categoryIdInput');
            res.data.forEach(c => {
                const opt1 = document.createElement('option');
                opt1.value = c.category_id; opt1.textContent = c.name;
                sel.appendChild(opt1);
                const opt2 = document.createElement('option');
                opt2.value = c.category_id; opt2.textContent = c.name;
                sel2.appendChild(opt2);
            });
        }
    }

    async function loadSubcategories(categoryId = null) {
        const params = { per_page: 1000 };
        if (categoryId) params.category_id = categoryId;
        const res = await API.getSubcategories(params);
        if (res.success && res.data) {
            subcategoriesCache = res.data;
            const selects = ['subcategoryFilter', 'subcategoryIdInput'];
            selects.forEach(id => {
                const sel = document.getElementById(id);
                const currentVal = sel.value;
                sel.innerHTML = '<option value="">' + (id === 'subcategoryFilter' ? 'Todas' : 'Seleccione una subcategoría') + '</option>';
                res.data.forEach(s => {
                    const opt = document.createElement('option');
                    opt.value = s.subcategory_id;
                    opt.textContent = s.name + (s.category_name ? ' (' + s.category_name + ')' : '');
                    sel.appendChild(opt);
                });
                sel.value = currentVal;
            });
        }
    }

    document.getElementById('categoryFilter').addEventListener('change', function() {
        loadSubcategories(this.value || null);
    });

    document.getElementById('categoryIdInput').addEventListener('change', function() {
        loadSubcategories(this.value || null);
        document.getElementById('subcategoryIdInput').value = '';
    });

    function renderTable(items) {
        const tbody = document.getElementById('tableBody');
        tbody.innerHTML = '';
        if (!items || items.length === 0) {
            const emptyRow = document.createElement('tr');
            emptyRow.innerHTML = '<td colspan="9" class="text-center text-muted py-4"><i class="bi bi-inbox me-2"></i>No hay productos.</td>';
            tbody.appendChild(emptyRow);
            return;
        }

        items.forEach(p => {
            const tr = document.createElement('tr');

            const thumbTd = document.createElement('td');
            thumbTd.className = 'align-middle';
            if (p.image) {
                const img = document.createElement('img');
                img.src = p.image;
                img.className = 'rounded';
                img.style.width = '48px';
                img.style.height = '48px';
                img.style.objectFit = 'cover';
                img.style.cursor = 'pointer';
                img.alt = 'Imagen';
                img.addEventListener('click', () => viewImages(p.product_id));
                img.onerror = function () {
                    this.src = 'data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><rect fill="%23e9ecef" width="48" height="48"/><text x="24" y="24" text-anchor="middle" dy=".3em" fill="%236c757d" font-size="16">&#x1f5bc;</text></svg>';
                };
                thumbTd.appendChild(img);
            } else {
                const placeholder = document.createElement('div');
                placeholder.className = 'rounded bg-light d-flex align-items-center justify-content-center text-muted';
                placeholder.style.width = '48px';
                placeholder.style.height = '48px';
                placeholder.style.cursor = 'pointer';
                placeholder.style.fontSize = '20px';
                placeholder.title = 'Ver imágenes';
                placeholder.innerHTML = '<i class="bi bi-image"></i>';
                placeholder.addEventListener('click', () => viewImages(p.product_id));
                thumbTd.appendChild(placeholder);
            }
            tr.appendChild(thumbTd);

            const codeTd = document.createElement('td');
            codeTd.className = 'align-middle';
            const codeEl = document.createElement('code');
            codeEl.textContent = p.product_code;
            codeTd.appendChild(codeEl);
            tr.appendChild(codeTd);

            const nameTd = document.createElement('td');
            nameTd.className = 'align-middle fw-medium';
            nameTd.textContent = p.name;
            tr.appendChild(nameTd);

            const subcategoryTd = document.createElement('td');
            subcategoryTd.className = 'align-middle small';
            subcategoryTd.textContent = getSubcategoryName(p.subcategory_id);
            tr.appendChild(subcategoryTd);

            const priceTd = document.createElement('td');
            priceTd.className = 'align-middle';
            priceTd.textContent = formatPrice(p.price);
            tr.appendChild(priceTd);

            const discountTd = document.createElement('td');
            discountTd.className = 'align-middle';
            discountTd.textContent = formatDiscount(p.discount);
            tr.appendChild(discountTd);

            const stockTd = document.createElement('td');
            stockTd.className = 'align-middle text-center';
            const stockBadge = document.createElement('span');
            stockBadge.className = p.stock === 0
                ? 'badge bg-danger'
                : (p.stock <= 10 ? 'badge bg-warning text-dark' : 'badge bg-success');
            stockBadge.textContent = p.stock === 0 ? 'Agotado' : (p.stock <= 10 ? 'Bajo' : 'Disponible');
            stockBadge.title = `Stock actual: ${p.stock}`;
            stockTd.appendChild(stockBadge);
            tr.appendChild(stockTd);

            const statusTd = document.createElement('td');
            statusTd.className = 'align-middle';
            const badge = document.createElement('span');
            badge.className = p.is_active ? 'badge bg-success' : 'badge bg-secondary';
            badge.textContent = StatusTranslator.boolean(p.is_active);
            statusTd.appendChild(badge);
            tr.appendChild(statusTd);

            const actionsTd = document.createElement('td');
            actionsTd.className = 'align-middle';
            const wrap = document.createElement('div');
            wrap.className = 'd-flex justify-content-center table-actions';
            const kebab = document.createElement('button');
            kebab.type = 'button';
            kebab.className = 'btn btn-light border btn-sm';
            kebab.setAttribute('data-product-menu', p.product_id);
            kebab.setAttribute('data-action-menu-trigger', '');
            kebab.setAttribute('aria-label', 'Acciones de producto');
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
        info.textContent = `Mostrando ${meta.from || 0} - ${meta.to || 0} de ${meta.total} productos`;
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
            const data = await API.getData(params);
            if (data.success) {
                productsCache = Array.isArray(data.data) ? data.data : [];
                renderTable(data.data);
                renderPagination(data.meta);
            } else {
                showAlert(data.message || 'Error al cargar productos.', 'danger');
            }
        } catch (e) {
            showAlert('Error de conexión.', 'danger');
        } finally {
            showLoading(false);
        }
    }

    const FILTER_CHIPS = {
        category_id: { label: 'Categoría', inputId: 'categoryFilter' },
        subcategory_id: { label: 'Subcategoría', inputId: 'subcategoryFilter' },
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
        const cat = document.getElementById('categoryFilter').value;
        if (cat) currentFilters.category_id = cat;
        const sub = document.getElementById('subcategoryFilter').value;
        if (sub) currentFilters.subcategory_id = sub;
        const status = document.getElementById('statusFilter').value;
        if (status) currentFilters.status = status;
        FilterBar.closeOffcanvas('filterOffcanvas');
        renderFilterChips();
        loadPage(1);
    }

    function clearFilters() {
        document.getElementById('categoryFilter').value = '';
        document.getElementById('subcategoryFilter').value = '';
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

    async function editRecord(id) {
        showLoading(true);
        try {
            const data = await API.get(id);
            if (data.success && data.data) {
                const p = data.data;
                document.getElementById('modalTitle').textContent = 'Editar Producto';
                document.getElementById('recordId').value = p.product_id;
                document.getElementById('categoryIdInput').value = p.subcategory_id ? '' : '';
                await loadSubcategories();
                document.getElementById('subcategoryIdInput').value = p.subcategory_id;
                document.getElementById('productCodeInput').value = p.product_code;
                document.getElementById('nameInput').value = p.name;
                document.getElementById('descriptionInput').value = p.description || '';
                document.getElementById('imageInput').value = p.image || '';
                if (window.FormValidation) {
                    window.FormValidation.limpiarErrores(formEl);
                }
                recalcularEstadoBoton(formEl);
                new bootstrap.Modal(document.getElementById('formModal')).show();
            } else {
                showAlert('Producto no encontrado.', 'danger');
            }
        } catch (e) {
            showAlert('Error de conexión.', 'danger');
        } finally {
            showLoading(false);
        }
    }

    function recalcularEstadoBoton(form) {
        form.querySelectorAll('input:not([type="hidden"]), select, textarea').forEach((input) => {
            input.dispatchEvent(new Event('input', { bubbles: true }));
        });
    }

    document.getElementById('btnCreate').addEventListener('click', () => {
        document.getElementById('modalTitle').textContent = 'Nuevo Producto';
        formEl.reset();
        document.getElementById('recordId').value = '';
        if (window.FormValidation) {
            window.FormValidation.limpiarErrores(formEl);
        }
        recalcularEstadoBoton(formEl);
        new bootstrap.Modal(document.getElementById('formModal')).show();
    });

    formEl.addEventListener('submit', async (e) => {
        e.preventDefault();

        if (window.FormValidation && !window.FormValidation.formularioEsValido(formEl)) {
            formEl.querySelectorAll('input:not([type="hidden"]), select, textarea').forEach((input) => {
                if (input.required && !input.value) {
                    window.FormValidation.marcarError(input);
                } else if (input.value && !input.checkValidity()) {
                    window.FormValidation.marcarError(input);
                }
            });
            return;
        }

        const id = document.getElementById('recordId').value;
        const data = {
            subcategory_id: parseInt(document.getElementById('subcategoryIdInput').value),
            product_code: document.getElementById('productCodeInput').value.trim(),
            name: document.getElementById('nameInput').value.trim(),
            description: document.getElementById('descriptionInput').value.trim() || null,
            image: document.getElementById('imageInput').value.trim() || null,
        };

        const submitFn = async () => {
            showLoading(true);
            try {
                const res = id ? await API.update(id, data) : await API.create(data);
                if (res.success) {
                    showAlert(res.message, 'success');
                    bootstrap.Modal.getInstance(document.getElementById('formModal')).hide();
                    if (id) bootstrap.Modal.getInstance(document.getElementById('confirmModal'))?.hide();
                    loadPage(currentPage);
                } else {
                    showFormValidationError(formEl, res);
                }
            } finally {
                showLoading(false);
            }
        };

        if (id) {
            confirmAction('Guardar cambios', '¿Está seguro de guardar los cambios en este producto?', 'Guardar', 'btn-primary', submitFn);
        } else {
            await submitFn();
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

    function openPriceModal(id, currentPrice) {
        document.getElementById('priceProductId').value = id;
        document.getElementById('priceValueInput').value = currentPrice ?? '';
        if (window.FormValidation) {
            window.FormValidation.limpiarErrores(priceFormEl);
        }
        new bootstrap.Modal(document.getElementById('priceModal')).show();
    }

    priceFormEl.addEventListener('submit', async (e) => {
        e.preventDefault();

        if (window.FormValidation && !window.FormValidation.formularioEsValido(priceFormEl)) {
            priceFormEl.querySelectorAll('input:not([type="hidden"]), select, textarea').forEach((input) => {
                if (input.required && !input.value) {
                    window.FormValidation.marcarError(input);
                } else if (input.value && !input.checkValidity()) {
                    window.FormValidation.marcarError(input);
                }
            });
            return;
        }

        const id = document.getElementById('priceProductId').value;
        const price = parseFloat(document.getElementById('priceValueInput').value);
        showLoading(true);
        try {
            const data = await API.updatePrice(id, price);
            if (data.success) {
                showAlert(data.message, 'success');
                bootstrap.Modal.getInstance(document.getElementById('priceModal')).hide();
                loadPage(currentPage);
            } else {
                showFormValidationError(priceFormEl, data);
            }
        } finally {
            showLoading(false);
        }
    });

    function openDiscountModal(id, currentDiscount) {
        document.getElementById('discountProductId').value = id;
        document.getElementById('discountValueInput').value = currentDiscount ?? 0;
        if (window.FormValidation) {
            window.FormValidation.limpiarErrores(discountFormEl);
        }
        new bootstrap.Modal(document.getElementById('discountModal')).show();
    }

    discountFormEl.addEventListener('submit', async (e) => {
        e.preventDefault();

        if (window.FormValidation && !window.FormValidation.formularioEsValido(discountFormEl)) {
            discountFormEl.querySelectorAll('input:not([type="hidden"]), select, textarea').forEach((input) => {
                if (input.required && !input.value) {
                    window.FormValidation.marcarError(input);
                } else if (input.value && !input.checkValidity()) {
                    window.FormValidation.marcarError(input);
                }
            });
            return;
        }

        const id = document.getElementById('discountProductId').value;
        const discount = parseFloat(document.getElementById('discountValueInput').value);
        showLoading(true);
        try {
            const data = await API.updateDiscount(id, discount);
            if (data.success) {
                showAlert(data.message, 'success');
                bootstrap.Modal.getInstance(document.getElementById('discountModal')).hide();
                loadPage(currentPage);
            } else {
                showFormValidationError(discountFormEl, data);
            }
        } finally {
            showLoading(false);
        }
    });

    let viewerInstance = null;

    async function viewImages(productId) {
        showLoading(true);
        try {
            const data = await API.get(productId);
            if (data.success && data.data) {
                const p = data.data;
                const images = data.data.images || [];
                const allImages = [];
                if (p.image) allImages.push({ image: p.image, label: 'Principal' });
                images.forEach((img, i) => allImages.push({ image: img.image, label: 'Imagen ' + (i + 1) }));

                if (allImages.length === 0) {
                    showAlert('Este producto no tiene imágenes.', 'warning');
                    return;
                }

                document.getElementById('viewerModalTitle').textContent = 'Imágenes: ' + p.name;

                const indicators = document.getElementById('carouselIndicators');
                const inner = document.getElementById('carouselInner');
                indicators.innerHTML = '';
                inner.innerHTML = '';

                allImages.forEach((img, i) => {
                    const indicator = document.createElement('button');
                    indicator.type = 'button';
                    indicator.dataset.bsTarget = '#viewerCarousel';
                    indicator.dataset.bsSlideTo = i;
                    indicator.setAttribute('aria-label', 'Slide ' + (i + 1));
                    if (i === 0) indicator.classList.add('active');
                    indicators.appendChild(indicator);

                    const div = document.createElement('div');
                    div.className = 'carousel-item' + (i === 0 ? ' active' : '');
                    div.innerHTML = `<img src="${img.image}" class="d-block w-100" style="max-height: 70vh; object-fit: contain;" alt="${img.label}" onerror="this.parentElement.innerHTML='<div class=\\'d-flex align-items-center justify-content-center text-muted\\' style=\\'height: 400px\\'><i class=\\'bi bi-image fs-1 me-2\\'></i>Imagen no disponible</div>'"><div class="text-center text-white mt-2 pb-2"><small>${img.label}</small></div>`;
                    inner.appendChild(div);
                });

                document.getElementById('viewerCounter').textContent = (allImages.length === 1) ? '1 imagen' : allImages.length + ' imágenes';

                if (viewerInstance) viewerInstance.dispose();
                viewerInstance = new bootstrap.Modal(document.getElementById('viewerModal'));
                viewerInstance.show();
            } else {
                showAlert('Producto no encontrado.', 'danger');
            }
        } finally {
            showLoading(false);
        }
    }

    async function manageImages(productId) {
        currentImageProductId = productId;
        document.getElementById('newImageUrl').value = '';
        showLoading(true);
        try {
            const data = await API.get(productId);
            if (data.success && data.data) {
                const images = data.data.images || [];
                renderImages(images);
                new bootstrap.Modal(document.getElementById('imageModal')).show();
            } else {
                showAlert('Error al cargar imágenes.', 'danger');
            }
        } finally {
            showLoading(false);
        }
    }

    function renderImages(images) {
        const container = document.getElementById('imagesList');
        if (!images.length) {
            container.innerHTML = '<p class="text-muted text-center small">Sin imágenes adicionales.</p>';
            return;
        }
        container.innerHTML = images.map(img => `
            <div class="col-6 col-md-4">
                <div class="card card-sm position-relative">
                    <img src="${img.image}" class="card-img-top" alt="Imagen" style="height: 80px; object-fit: cover;" onerror="this.style.display='none'">
                    <div class="card-body p-1 text-center">
                        <small class="text-muted">#${img.sort_order}</small>
                        <button class="btn btn-sm btn-outline-danger mt-1" onclick="removeImage(${currentImageProductId}, ${img.image_id})" title="Eliminar"><i class="bi bi-x"></i></button>
                    </div>
                </div>
            </div>
        `).join('');
    }

    async function removeImage(productId, imageId) {
        showLoading(true);
        try {
            const data = await API.removeImage(productId, imageId);
            if (data.success) {
                showAlert(data.message, 'success');
                const next = await API.get(productId);
                if (next.success) renderImages(next.data.images || []);
            } else {
                showAlert(data.message || 'Error.', 'danger');
            }
        } finally {
            showLoading(false);
        }
    }

    document.getElementById('btnAddImage').addEventListener('click', async () => {
        const url = document.getElementById('newImageUrl').value.trim();
        if (!url) { showAlert('Ingrese una URL de imagen.', 'warning'); return; }
        if (!currentImageProductId) return;
        showLoading(true);
        try {
            const data = await API.addImage(currentImageProductId, url);
            if (data.success) {
                showAlert(data.message, 'success');
                document.getElementById('newImageUrl').value = '';
                const next = await API.get(currentImageProductId);
                if (next.success) renderImages(next.data.images || []);
            } else {
                showAlert(data.message || 'Error.', 'danger');
            }
        } finally {
            showLoading(false);
        }
    });

    function requestToggleProduct(p) {
        const title = p.is_active ? 'Desactivar producto' : 'Activar producto';
        const body = `¿Está seguro de ${p.is_active ? 'desactivar' : 'activar'} el producto <strong>${p.name}</strong>?`;
        const btnText = p.is_active ? 'Desactivar' : 'Activar';
        const btnClass = p.is_active ? 'btn-warning' : 'btn-success';
        confirmAction(title, body, btnText, btnClass, async () => {
            const r = p.is_active ? await API.deactivate(p.product_id) : await API.activate(p.product_id);
            if (r.success) {
                showAlert(r.message, 'success');
                bootstrap.Modal.getInstance(document.getElementById('confirmModal')).hide();
                loadPage(currentPage);
            } else {
                showAlert(r.message || 'Error.', 'danger');
            }
        });
    }

    function requestDeleteProduct(p) {
        confirmAction('Eliminar producto', `¿Está seguro de eliminar el producto <strong>${p.name}</strong>?`, 'Eliminar', 'btn-danger', async () => {
            const r = await API.delete(p.product_id);
            if (r.success) {
                showAlert(r.message, 'success');
                bootstrap.Modal.getInstance(document.getElementById('confirmModal')).hide();
                loadPage(currentPage);
            } else {
                showAlert(r.message || 'Error.', 'danger');
            }
        });
    }

    function handleProductAction(item) {
        const p = productsCache.find((row) => String(row.product_id) === String(item.id));
        if (!p) return;
        if (item.action === 'edit') {
            editRecord(p.product_id);
        } else if (item.action === 'toggle') {
            requestToggleProduct(p);
        } else if (item.action === 'view-images') {
            viewImages(p.product_id);
        } else if (item.action === 'manage-images') {
            manageImages(p.product_id);
        } else if (item.action === 'price') {
            openPriceModal(p.product_id, p.price ?? null);
        } else if (item.action === 'discount') {
            openDiscountModal(p.product_id, p.discount ?? null);
        } else if (item.action === 'delete') {
            requestDeleteProduct(p);
        }
    }

    document.addEventListener('click', (e) => {
        const trigger = e.target.closest('[data-product-menu]');
        if (!trigger) return;
        const p = productsCache.find((row) => String(row.product_id) === String(trigger.dataset.productMenu));
        if (!p) return;
        ActionMenu.open(trigger, [
            { action: 'edit', icon: 'bi-pencil', label: 'Editar', className: 'text-primary' },
            p.is_active
                ? { action: 'toggle', icon: 'bi-pause-circle', label: 'Desactivar', className: 'text-warning' }
                : { action: 'toggle', icon: 'bi-play-circle', label: 'Activar', className: 'text-success' },
            { divider: true },
            { action: 'view-images', icon: 'bi-images', label: 'Ver imágenes', className: 'text-info' },
            { action: 'manage-images', icon: 'bi-pencil-square', label: 'Gestionar imágenes', className: 'text-secondary' },
            { divider: true },
            { action: 'price', icon: 'bi-currency-dollar', label: 'Cambiar precio', className: 'text-secondary' },
            { action: 'discount', icon: 'bi-percent', label: 'Cambiar descuento', className: 'text-secondary' },
            { divider: true },
            { action: 'delete', icon: 'bi-trash', label: 'Eliminar', className: 'text-danger' },
        ], {
            id: p.product_id,
            name: p.name,
            onSelect: handleProductAction,
        });
    });

    loadCategories().then(() => loadSubcategories()).then(() => loadPage(1));
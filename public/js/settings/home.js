document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('bannerForm');
    if (!form) return;
    const tbody = document.getElementById('bannersTableBody');
    const titleEl = document.getElementById('bannerFormTitle');
    const cancelBtn = document.getElementById('btnCancelBannerEdit');
    const alertContainer = document.getElementById('alertContainer');

    let banners = Array.isArray(window.HOME_BANNERS) ? window.HOME_BANNERS : [];

    const escapeHtml = (str) => {
        if (str === null || str === undefined) return '';
        const div = document.createElement('div');
        div.appendChild(document.createTextNode(String(str)));
        return div.innerHTML;
    };

    const showAlert = (type, message) => {
        if (!alertContainer) return;
        const alert = document.createElement('div');
        alert.className = `alert alert-${type} alert-dismissible fade show`;
        alert.innerHTML = `${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
        alertContainer.appendChild(alert);
        setTimeout(() => alert.remove(), 4000);
    };

    const recomputarBoton = () => {
        form.querySelectorAll('input:not([type="hidden"]), select, textarea').forEach((input) => {
            input.dispatchEvent(new Event('input', { bubbles: true }));
        });
    };

    const renderBanners = () => {
        if (!tbody) return;
        if (!banners.length) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-4"><i class="bi bi-inbox me-2"></i>No hay banners.</td></tr>';
            return;
        }
        tbody.innerHTML = banners.map((b) => `
            <tr>
                <td class="align-middle">${escapeHtml(b.sort_order)}</td>
                <td class="align-middle"><img src="${escapeHtml(b.image)}" alt="Banner" style="width:80px;height:45px;object-fit:cover;border-radius:4px;"></td>
                <td class="align-middle">${escapeHtml(b.title) || '<span class="text-muted">—</span>'}</td>
                <td class="align-middle">${b.is_active ? '<span class="badge bg-success">Activo</span>' : '<span class="badge bg-secondary">Inactivo</span>'}</td>
                <td class="align-middle">
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-primary" data-banner-edit="${escapeHtml(b.banner_id)}" title="Editar"><i class="bi bi-pencil"></i></button>
                        <button type="button" class="btn btn-outline-danger" data-banner-delete="${escapeHtml(b.banner_id)}" title="Eliminar"><i class="bi bi-trash"></i></button>
                    </div>
                </td>
            </tr>`).join('');
    };

    const resetForm = () => {
        form.reset();
        document.getElementById('bannerFormId').value = '';
        form.action = '/admin/settings/banners';
        titleEl.textContent = 'Nuevo banner';
        cancelBtn.hidden = true;
        const submitBtn = form.querySelector('button[type="submit"]');
        submitBtn.innerHTML = '<i class="bi bi-check-lg me-1"></i> Crear banner';
        if (window.FormValidation) {
            window.FormValidation.limpiarErrores(form);
        }
        recomputarBoton();
    };

    const fillEditForm = (banner) => {
        document.getElementById('bannerFormId').value = banner.banner_id;
        form.action = '/admin/settings/banners/' + banner.banner_id;
        document.getElementById('bannerImageInput').value = banner.image || '';
        document.getElementById('bannerSortOrderInput').value = banner.sort_order || 1;
        document.getElementById('bannerIsActiveInput').checked = !!banner.is_active;
        document.getElementById('bannerTitleInput').value = banner.title || '';
        document.getElementById('bannerSubtitleInput').value = banner.subtitle || '';
        titleEl.textContent = 'Editar banner';
        cancelBtn.hidden = false;
        const submitBtn = form.querySelector('button[type="submit"]');
        submitBtn.innerHTML = '<i class="bi bi-check-lg me-1"></i> Guardar cambios';
        if (window.FormValidation) {
            window.FormValidation.limpiarErrores(form);
        }
        recomputarBoton();
    };

    const deleteBanner = async (id) => {
        try {
            const res = await fetch('/admin/settings/banners/' + id + '/delete', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': window.CSRF_TOKEN || '',
                },
            });
            const json = await res.json();
            if (json.success) {
                banners = json.data && Array.isArray(json.data.banners) ? json.data.banners : [];
                renderBanners();
                showAlert('success', json.message || 'Banner eliminado.');
            } else {
                showAlert('danger', json.message || 'Error al eliminar el banner.');
            }
        } catch (err) {
            showAlert('danger', 'Error de conexión.');
        }
    };

    tbody.addEventListener('click', (e) => {
        const editBtn = e.target.closest('[data-banner-edit]');
        if (editBtn) {
            const banner = banners.find((b) => b.banner_id === parseInt(editBtn.dataset.bannerEdit, 10));
            if (banner) fillEditForm(banner);
            return;
        }
        const delBtn = e.target.closest('[data-banner-delete]');
        if (delBtn && confirm('¿Eliminar este banner?')) {
            deleteBanner(parseInt(delBtn.dataset.bannerDelete, 10));
        }
    });

    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        if (window.FormValidation && !window.FormValidation.formularioEsValido(form)) {
            form.querySelectorAll('input:not([type="hidden"]), select, textarea').forEach((input) => {
                if (input.required && !input.value) {
                    window.FormValidation.marcarError(input);
                } else if (input.value && !input.checkValidity()) {
                    window.FormValidation.marcarError(input);
                }
            });
            return;
        }

        const id = document.getElementById('bannerFormId').value;
        const data = {
            image: document.getElementById('bannerImageInput').value.trim(),
            sort_order: document.getElementById('bannerSortOrderInput').value,
        };
        if (document.getElementById('bannerIsActiveInput').checked) data.is_active = '1';
        const title = document.getElementById('bannerTitleInput').value.trim();
        const subtitle = document.getElementById('bannerSubtitleInput').value.trim();
        if (title) data.title = title;
        if (subtitle) data.subtitle = subtitle;

        const btn = form.querySelector('button[type="submit"]');
        btn.disabled = true;
        try {
            const res = await fetch(form.action, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': window.CSRF_TOKEN || '',
                },
                body: JSON.stringify(data),
            });
            const json = await res.json();
            if (json.success) {
                banners = json.data && Array.isArray(json.data.banners) ? json.data.banners : [];
                renderBanners();
                showAlert('success', json.message || 'Banner guardado.');
                resetForm();
            } else if (json.errors && typeof json.errors === 'object'
                && !Array.isArray(json.errors) && Object.keys(json.errors).length > 0) {
                window.FormValidation.limpiarErrores(form);
                window.FormValidation.mostrarErrores(form, json.errors);
            } else {
                showAlert('danger', json.message || 'Error al guardar el banner.');
            }
        } catch (err) {
            showAlert('danger', 'Error de conexión.');
        } finally {
            btn.disabled = false;
        }
    });

    document.getElementById('btnNewBanner')?.addEventListener('click', resetForm);
    document.getElementById('btnCancelBannerEdit')?.addEventListener('click', resetForm);

    renderBanners();
});
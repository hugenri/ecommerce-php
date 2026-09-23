const UserUI = (() => {
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

    const formatDate = (dateStr) => {
        if (!dateStr) return '-';
        const d = new Date(dateStr);
        if (isNaN(d.getTime())) return '-';
        return d.toLocaleDateString('es-ES', { day: '2-digit', month: 'short', year: 'numeric' });
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
        if (!str) return '';
        const div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    };

    const renderUsersTable = (users, meta) => {
        UserActionMenu.setUsers(users || []);
        const tbody = document.getElementById('usersTableBody');
        if (!tbody) return;

        if (!users || users.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="7" class="text-center py-4 text-muted">
                        <i class="bi bi-inbox fs-1 d-block mb-2"></i> No se encontraron usuarios.
                    </td>
                </tr>`;
            updatePaginationInfo(0, 0, 0);
            renderPaginationLinks(0, 0);
            return;
        }

        const html = users.map(user => `
            <tr data-user-id="${user.user_id}">
                <td class="text-muted">${user.user_id}</td>
                <td>
                    <div class="d-flex align-items-center">
                        <img src="${escapeHtml(user.avatar_url)}" alt="" class="avatar-sm me-2">
                        <strong>${escapeHtml(user.name)}</strong>
                    </div>
                </td>
                <td>
                    ${escapeHtml(user.email)}
                </td>
                <td><span class="badge badge-role-${user.role}">${escapeHtml(StatusTranslator.role(user.role))}</span></td>
                <td><span class="badge ${user.is_active ? 'bg-success' : 'bg-secondary'}">${StatusTranslator.boolean(user.is_active)}</span></td>
                <td class="text-muted small">${formatDate(user.created_at)}</td>
                <td>
                    <div class="d-flex justify-content-center table-actions">
                        <button class="btn btn-light border btn-sm" type="button" data-user-menu="${user.user_id}" data-action-menu-trigger aria-label="Acciones de usuario" aria-expanded="false">
                            <i class="bi bi-three-dots-vertical"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `).join('');

        tbody.innerHTML = html;
        updatePaginationInfo(meta.current_page, meta.per_page, meta.total);
        renderPaginationLinks(meta.current_page, meta.total_pages);
    };

    const updatePaginationInfo = (page, perPage, total) => {
        const info = document.getElementById('paginationInfo');
        if (!info) return;

        if (total === 0) {
            info.textContent = 'No se encontraron usuarios.';
            return;
        }

        const start = (page - 1) * perPage + 1;
        const end = Math.min(page * perPage, total);
        info.textContent = `Mostrando ${start} - ${end} de ${total} usuarios`;
    };

    const renderPaginationLinks = (currentPage, totalPages) => {
        const container = document.getElementById('paginationLinks');
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

    const renderDetail = (user) => {
        const body = document.getElementById('userDetailBody');
        if (!body || !user) return;

        body.innerHTML = `
            <div class="text-center mb-4">
                <img src="${escapeHtml(user.avatar_url)}" alt="${escapeHtml(user.name)}" class="rounded-circle mb-2" style="width:80px;height:80px;object-fit:cover;">
                <h5 class="mb-0">${escapeHtml(user.name)}</h5>
                <small class="text-muted">${escapeHtml(user.email)}</small>
            </div>
            <div class="row g-3">
                <div class="col-6">
                    <div class="detail-label">Rol</div>
                    <div class="detail-value"><span class="badge badge-role-${user.role}">${escapeHtml(StatusTranslator.role(user.role))}</span></div>
                </div>
                <div class="col-6">
                    <div class="detail-label">Estado</div>
                    <div class="detail-value"><span class="badge ${user.is_active ? 'bg-success' : 'bg-secondary'}">${StatusTranslator.boolean(user.is_active)}</span></div>
                </div>
                <div class="col-6">
                    <div class="detail-label">Telefono</div>
                    <div class="detail-value">${escapeHtml(user.phone) || '-'}</div>
                </div>
                <div class="col-6">
                    <div class="detail-label">Ultimo acceso</div>
                    <div class="detail-value">${formatDateTime(user.last_login)}</div>
                </div>
                <div class="col-6">
                    <div class="detail-label">Registro</div>
                    <div class="detail-value">${formatDateTime(user.created_at)}</div>
                </div>
            </div>`;
    };

    const fillForm = (user) => {
        document.getElementById('formUserId').value = user.user_id;
        document.getElementById('formName').value = user.name;
        document.getElementById('formEmail').value = user.email;
        document.getElementById('formRole').value = user.role;
        document.getElementById('formPhone').value = user.phone || '';
        document.getElementById('formIsActive').value = user.is_active ? '1' : '0';
    };

    const clearForm = () => {
        document.getElementById('userForm').reset();
        document.getElementById('formUserId').value = '';
        if (window.FormValidation) {
            window.FormValidation.limpiarErrores(document.getElementById('userForm'));
        }
    };

    const showFormErrors = (errors) => {
        const form = document.getElementById('userForm');
        if (window.FormValidation) {
            window.FormValidation.limpiarErrores(form);
            window.FormValidation.mostrarErrores(form, errors);
        }
    };

    const clearResetPasswordForm = () => {
        document.getElementById('resetPasswordForm').reset();
        document.getElementById('rsUserId').value = '';
        if (window.FormValidation) {
            window.FormValidation.limpiarErrores(document.getElementById('resetPasswordForm'));
        }
    };

    const showResetPasswordErrors = (errors) => {
        const form = document.getElementById('resetPasswordForm');
        if (window.FormValidation) {
            window.FormValidation.limpiarErrores(form);
            window.FormValidation.mostrarErrores(form, errors);
        }
    };

    return {
        showLoading,
        hideLoading,
        showAlert,
        renderUsersTable,
        renderDetail,
        fillForm,
        clearForm,
        showFormErrors,
        clearResetPasswordForm,
        showResetPasswordErrors,
        escapeHtml,
    };
})();

const UserActionMenu = (() => {
    let users = [];

    const setUsers = (list) => {
        users = Array.isArray(list) ? list : [];
    };

    const findUser = (id) => users.find((user) => String(user.user_id) === String(id));

    document.addEventListener('click', (e) => {
        const trigger = e.target.closest('[data-user-menu]');
        if (!trigger) return;
        const user = findUser(trigger.dataset.userMenu);
        if (user) {
            ActionMenu.open(trigger, [
                { action: 'show', icon: 'bi-eye', label: 'Ver detalle', className: 'text-info' },
                { action: 'edit', icon: 'bi-pencil', label: 'Editar', className: 'text-primary' },
                { action: 'reset-password', icon: 'bi-key', label: 'Restablecer contraseña', className: 'text-warning' },
                user.is_active
                    ? { action: 'toggle', icon: 'bi-pause-circle', label: 'Desactivar', className: 'text-secondary' }
                    : { action: 'toggle', icon: 'bi-play-circle', label: 'Activar', className: 'text-success' },
                { divider: true },
                { action: 'delete', icon: 'bi-trash', label: 'Eliminar', className: 'text-danger' },
            ], { id: user.user_id, name: user.name });
        }
    });

    return { setUsers };
})();

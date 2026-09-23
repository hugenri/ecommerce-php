document.addEventListener('DOMContentLoaded', () => {
    let currentPage = 1;
    let currentSort = 'user_id';
    let currentDir = 'ASC';
    let searchTimeout = null;

    const modals = {
        userForm: new bootstrap.Modal('#userFormModal'),
        userDetail: new bootstrap.Modal('#userDetailModal'),
        resetPassword: new bootstrap.Modal('#resetPasswordModal'),
        delete: new bootstrap.Modal('#deleteModal'),
    };

    const validarFormulario = (form) => {
        if (window.FormValidation && !window.FormValidation.formularioEsValido(form)) {
            form.querySelectorAll('input:not([type="hidden"]), select, textarea').forEach((input) => {
                if (input.required && !input.value) {
                    window.FormValidation.marcarError(input);
                } else if (input.value && !input.checkValidity()) {
                    window.FormValidation.marcarError(input);
                }
            });
            return false;
        }
        return true;
    };

    const recalcularEstadoBoton = (form) => {
        form.querySelectorAll('input:not([type="hidden"]), select, textarea').forEach((input) => {
            input.dispatchEvent(new Event('input', { bubbles: true }));
        });
    };

    const FILTER_CHIPS = {
        role: { label: 'Rol', inputId: 'filterRole' },
        is_active: { label: 'Estado', inputId: 'filterStatus' },
    };

    let currentFilters = {};

    const renderFilterChips = () => {
        FilterBar.renderChips({
            chipsId: 'filterChips',
            badgeId: 'filterBadge',
            chips: FilterBar.buildChips(currentFilters, FILTER_CHIPS),
            onRemove: () => {
                loadUsers(1);
            },
        });
    };

    const fetchFilters = () => {
        currentFilters = {};
        const role = document.getElementById('filterRole').value;
        const status = document.getElementById('filterStatus').value;
        if (role) currentFilters.role = role;
        if (status !== '') currentFilters.is_active = status;
        return currentFilters;
    };

    const loadUsers = async (page = 1) => {
        currentPage = page;
        UserUI.showLoading();

        const params = {
            page: page,
            per_page: document.getElementById('perPageSelect').value,
            search: document.getElementById('searchInput').value.trim(),
            sort_by: currentSort,
            sort_dir: currentDir,
        };

        Object.assign(params, fetchFilters());

        try {
            const { ok, json } = await UserAPI.get(params);
            if (ok && json.success) {
                UserUI.renderUsersTable(json.data || [], json.meta || {});
            } else {
                UserUI.renderUsersTable([], {});
                UserUI.showAlert('danger', json.message || 'Error al cargar usuarios.');
            }
        } catch (e) {
            UserUI.renderUsersTable([], {});
            UserUI.showAlert('danger', 'Error de conexion con el servidor.');
        } finally {
            UserUI.hideLoading();
            renderFilterChips();
        }
    };

    const applyFilters = () => {
        fetchFilters();
        FilterBar.closeOffcanvas('filterOffcanvas');
        loadUsers(1);
    };

    const clearFilters = () => {
        document.getElementById('filterRole').value = '';
        document.getElementById('filterStatus').value = '';
        FilterBar.closeOffcanvas('filterOffcanvas');
        loadUsers(1);
    };

    const openCreateModal = () => {
        UserUI.clearForm();
        document.getElementById('userFormModalTitle').innerHTML = '<i class="bi bi-person-plus me-2"></i>Nuevo Usuario';
        document.getElementById('formPasswordGroup').style.display = '';
        document.getElementById('formPasswordConfirmGroup').style.display = '';
        document.getElementById('formPassword').required = true;
        document.getElementById('formPasswordConfirm').required = true;
        recalcularEstadoBoton(document.getElementById('userForm'));
        modals.userForm.show();
    };

    const openEditModal = async (id) => {
        UserUI.showLoading();
        try {
            const { ok, json } = await UserAPI.show(id);
            if (ok && json.success) {
                UserUI.clearForm();
                document.getElementById('userFormModalTitle').innerHTML = '<i class="bi bi-pencil me-2"></i>Editar Usuario';
                document.getElementById('formPasswordGroup').style.display = 'none';
                document.getElementById('formPasswordConfirmGroup').style.display = 'none';
                document.getElementById('formPassword').required = false;
                document.getElementById('formPasswordConfirm').required = false;
                UserUI.fillForm(json.data);
                recalcularEstadoBoton(document.getElementById('userForm'));
                modals.userForm.show();
            } else {
                UserUI.showAlert('danger', json.message || 'Error al cargar usuario.');
            }
        } catch (e) {
            UserUI.showAlert('danger', 'Error de conexion.');
        } finally {
            UserUI.hideLoading();
        }
    };

    const saveUser = async () => {
        const id = document.getElementById('formUserId').value;
        const isEdit = id !== '';

        if (!validarFormulario(document.getElementById('userForm'))) {
            return;
        }

        const data = {
            name: document.getElementById('formName').value.trim(),
            email: document.getElementById('formEmail').value.trim(),
            role: document.getElementById('formRole').value,
            is_active: document.getElementById('formIsActive').value,
            phone: document.getElementById('formPhone').value.trim(),
        };

        if (!isEdit) {
            const password = document.getElementById('formPassword').value;
            if (password !== document.getElementById('formPasswordConfirm').value) {
                window.FormValidation.marcarError(
                    document.getElementById('formPasswordConfirm'),
                    'Las contraseñas no coinciden.'
                );
                return;
            }
            data.password = password;
        }

        try {
            const { ok, json } = isEdit
                ? await UserAPI.put(id, data)
                : await UserAPI.post(data);

            if (ok && json.success) {
                modals.userForm.hide();
                UserUI.showAlert('success', json.message);
                loadUsers(isEdit ? currentPage : 1);
            } else {
                if (json.errors) {
                    UserUI.showFormErrors(json.errors);
                } else {
                    UserUI.showAlert('danger', json.message || 'Error al guardar.');
                }
            }
        } catch (e) {
            UserUI.showAlert('danger', 'Error de conexion con el servidor.');
        }
    };

    const openDetailModal = async (id) => {
        UserUI.showLoading();
        try {
            const { ok, json } = await UserAPI.show(id);
            if (ok && json.success) {
                UserUI.renderDetail(json.data);
                modals.userDetail.show();
            } else {
                UserUI.showAlert('danger', json.message || 'Error al cargar usuario.');
            }
        } catch (e) {
            UserUI.showAlert('danger', 'Error de conexion.');
        } finally {
            UserUI.hideLoading();
        }
    };

    const openDeleteModal = (id, name) => {
        document.getElementById('deleteUserId').value = id;
        document.getElementById('deleteUserName').textContent = name;
        modals.delete.show();
    };

    const confirmDelete = async () => {
        const id = document.getElementById('deleteUserId').value;
        try {
            const { ok, json } = await UserAPI.del(id);
            if (!ok || !json.success) {
                throw new Error((json && json.message) || 'Error al eliminar.');
            }
            modals.delete.hide();
            UserUI.showAlert('success', json.message);
            loadUsers(currentPage);
        } catch (error) {
            modals.delete.hide();
            if (error instanceof TypeError) {
                UserUI.showAlert('danger', 'Error de conexión.');
            } else {
                UserUI.showAlert('danger', error.message || 'Error al eliminar.');
            }
        }
    };

    const toggleActive = async (id) => {
        try {
            const { ok, json } = await UserAPI.patch(id, {});
            if (!ok || !json.success) {
                throw new Error((json && json.message) || 'Error al cambiar estado.');
            }
            UserUI.showAlert('success', json.message);
            loadUsers(currentPage);
        } catch (error) {
            if (error instanceof TypeError) {
                UserUI.showAlert('danger', 'Error de conexión.');
            } else {
                UserUI.showAlert('danger', error.message || 'Error al cambiar estado.');
            }
        }
    };

    const openResetPasswordModal = (id, name) => {
        UserUI.clearResetPasswordForm();
        document.getElementById('rsUserId').value = id;
        document.getElementById('rsUserName').textContent = name;
        recalcularEstadoBoton(document.getElementById('resetPasswordForm'));
        modals.resetPassword.show();
    };

    const saveResetPassword = async () => {
        const id = document.getElementById('rsUserId').value;

        if (!validarFormulario(document.getElementById('resetPasswordForm'))) {
            return;
        }

        const newPassword = document.getElementById('rsNewPassword').value;
        if (newPassword !== document.getElementById('rsConfirmPassword').value) {
            window.FormValidation.marcarError(
                document.getElementById('rsConfirmPassword'),
                'Las contraseñas no coinciden.'
            );
            return;
        }

        const data = {
            new_password: newPassword,
            new_password_confirmation: document.getElementById('rsConfirmPassword').value,
        };

        try {
            const { ok, json } = await UserAPI.resetPassword(id, data);
            if (ok && json.success) {
                modals.resetPassword.hide();
                UserUI.showAlert('success', json.message);
            } else {
                if (json.errors) {
                    UserUI.showResetPasswordErrors(json.errors);
                } else {
                    UserUI.showAlert('danger', json.message || 'Error al restablecer contraseña.');
                }
            }
        } catch (e) {
            UserUI.showAlert('danger', 'Error de conexion.');
        }
    };

    document.getElementById('btnCreateUser')?.addEventListener('click', openCreateModal);
    document.getElementById('userForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        await saveUser();
    });
    document.getElementById('btnConfirmDelete')?.addEventListener('click', confirmDelete);
    document.getElementById('resetPasswordForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        await saveResetPassword();
    });

    document.getElementById('searchInput')?.addEventListener('input', () => {
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => loadUsers(1), 400);
    });

    document.getElementById('btnClearSearch')?.addEventListener('click', () => {
        document.getElementById('searchInput').value = '';
        loadUsers(1);
    });

    document.getElementById('perPageSelect')?.addEventListener('change', () => loadUsers(1));

    document.getElementById('btnApplyFilters')?.addEventListener('click', applyFilters);
    document.getElementById('btnClearFilters')?.addEventListener('click', clearFilters);

    document.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-action]');
        if (!btn) return;

        const action = btn.dataset.action;
        const id = parseInt(btn.dataset.id, 10);
        if (!Number.isInteger(id) || id <= 0) {
            UserUI.showAlert('danger', 'No se pudo obtener el ID del usuario. Recarga la pagina (Ctrl+F5) e intenta de nuevo.');
            return;
        }
        const row = btn.closest('tr');
        const name = row?.querySelector('strong')?.textContent || btn.dataset.name || '';

        switch (action) {
            case 'show': openDetailModal(id); break;
            case 'edit': openEditModal(id); break;
            case 'reset-password': openResetPasswordModal(id, name); break;
            case 'toggle': toggleActive(id); break;
            case 'delete': openDeleteModal(id, name); break;
        }
    });

    document.getElementById('paginationLinks')?.addEventListener('click', (e) => {
        e.preventDefault();
        const link = e.target.closest('[data-page]');
        if (link && !link.closest('.disabled')) {
            loadUsers(parseInt(link.dataset.page, 10));
        }
    });

    document.querySelectorAll('.sortable').forEach(th => {
        th.addEventListener('click', () => {
            const col = th.dataset.sort;
            if (currentSort === col) {
                currentDir = currentDir === 'ASC' ? 'DESC' : 'ASC';
            } else {
                currentSort = col;
                currentDir = 'ASC';
            }
            updateSortHeaders();
            loadUsers(currentPage);
        });
    });

    const updateSortHeaders = () => {
        document.querySelectorAll('.sortable').forEach(th => {
            const icon = th.querySelector('i');
            if (!icon) return;
            if (th.dataset.sort === currentSort) {
                icon.className = currentDir === 'ASC' ? 'bi bi-arrow-up' : 'bi bi-arrow-down';
            } else {
                icon.className = 'bi bi-arrow-down-up';
            }
        });
    };

    loadUsers(1);
});

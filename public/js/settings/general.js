document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('generalForm');
    if (!form) return;

    const alertContainer = document.getElementById('alertContainer');

    const showAlert = (type, message) => {
        if (!alertContainer) return;
        const alert = document.createElement('div');
        alert.className = `alert alert-${type} alert-dismissible fade show`;
        alert.innerHTML = `${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
        alertContainer.appendChild(alert);
        setTimeout(() => alert.remove(), 4000);
    };

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

        const data = {};
        form.querySelectorAll('input, select, textarea').forEach((el) => {
            if (el.name) data[el.name] = el.value;
        });

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
                window.FormValidation.limpiarErrores(form);
                showAlert('success', json.message || 'Configuración actualizada.');
            } else if (json.errors && typeof json.errors === 'object'
                && !Array.isArray(json.errors) && Object.keys(json.errors).length > 0) {
                window.FormValidation.limpiarErrores(form);
                window.FormValidation.mostrarErrores(form, json.errors);
            } else {
                showAlert('danger', json.message || 'Error al guardar.');
            }
        } catch (err) {
            showAlert('danger', 'Error de conexión.');
        } finally {
            btn.disabled = false;
        }
    });
});
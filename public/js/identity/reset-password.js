document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('resetForm');
    if (!form) return;

    const btn = form.querySelector('button[type="submit"]');
    const alertBox = document.getElementById('alertBox');

    const showGeneralError = (json) => {
        alertBox.textContent = json.message || 'Error desconocido';
        alertBox.className = 'form-alert form-alert-error';
        alertBox.hidden = false;
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

        const newPassword = form.password.value;
        if (newPassword !== form.password_confirmation.value) {
            window.FormValidation.marcarError(
                form.password_confirmation,
                'Las contraseñas no coinciden.'
            );
            return;
        }

        alertBox.hidden = true;
        alertBox.textContent = '';
        window.FormValidation.limpiarErrores(form);

        const data = {
            token: form.token.value,
            password: newPassword,
            password_confirmation: form.password_confirmation.value,
        };
        btn.disabled = true;
        try {
            const res = await fetch(form.action, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data),
            });
            const json = await res.json();
            if (json.success) {
                alertBox.textContent = json.message || 'Tu contraseña ha sido actualizada.';
                alertBox.className = 'form-alert form-alert-success';
                alertBox.hidden = false;
                if (json.data && json.data.redirect) {
                    setTimeout(() => {
                        window.location.href = json.data.redirect;
                    }, 1500);
                }
            } else if (json.errors && typeof json.errors === 'object'
                && !Array.isArray(json.errors) && Object.keys(json.errors).length > 0) {
                window.FormValidation.limpiarErrores(form);
                window.FormValidation.mostrarErrores(form, json.errors);
            } else {
                showGeneralError(json);
            }
        } catch (err) {
            alertBox.textContent = 'No fue posible conectar con el servidor.';
            alertBox.className = 'form-alert form-alert-error';
            alertBox.hidden = false;
        } finally {
            btn.disabled = false;
        }
    });
});
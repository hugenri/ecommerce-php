document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('logoutForm');
    if (!form) return;

    const btn = document.getElementById('logoutBtn');

    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        btn.disabled = true;
        try {
            const res = await fetch('/access/logout', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': window.CSRF_TOKEN || '',
                },
            });
            const json = await res.json();
            if (json.success) {
                window.location.href = (json.data && json.data.redirect) ? json.data.redirect : '/access/login';
                return;
            }
            alertContainerShow(json.message || 'No fue posible cerrar sesión.', 'danger');
        } catch (err) {
            alertContainerShow('No fue posible conectar con el servidor.', 'danger');
        } finally {
            btn.disabled = false;
        }
    });

    function alertContainerShow(message, type) {
        const container = document.getElementById('alertContainer');
        if (!container) return;
        const alert = document.createElement('div');
        alert.className = `alert alert-${type} alert-dismissible fade show`;
        alert.setAttribute('role', 'alert');
        alert.innerHTML = `${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
        container.appendChild(alert);
        setTimeout(() => alert.remove(), 4000);
    }
});
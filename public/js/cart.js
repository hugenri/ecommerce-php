(function () {
    'use strict';

    const modalEl = document.getElementById('confirmModal');
    if (!modalEl) return;

    const modal = new bootstrap.Modal(modalEl);
    const btnConfirm = document.getElementById('btnConfirmAction');

    const alertEl = document.querySelector('[data-cart-alert]');
    const contentEl = document.querySelector('[data-cart-content]');
    const emptyEl = document.querySelector('[data-cart-empty]');
    const summarySubtotal = document.querySelector('[data-cart-summary-subtotal]');
    const summaryIva = document.querySelector('[data-cart-summary-iva]');
    const summaryTotal = document.querySelector('[data-cart-summary-total]');

    let pendingConfirm = null;
    let processing = false;
    const busyRows = {};

    function money(value) {
        return '$' + Number(value).toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function showMessage(message) {
        if (!alertEl) return;
        alertEl.textContent = message;
        alertEl.classList.remove('d-none');
    }

    function hideMessage() {
        if (!alertEl) return;
        alertEl.classList.add('d-none');
        alertEl.textContent = '';
    }

    // ── Modal de confirmación (Eliminar / Vaciar carrito) ──

    function showConfirmModal(options) {
        document.getElementById('confirmModalTitle').textContent = options.title || 'Confirmar acción';
        document.getElementById('confirmModalMessage').textContent = options.message || '';
        btnConfirm.textContent = options.confirmBtnText || 'Confirmar';
        btnConfirm.disabled = false;
        pendingConfirm = options.onConfirm || null;
        modal.show();
    }

    btnConfirm.addEventListener('click', async () => {
        if (!pendingConfirm || processing) return;
        processing = true;
        btnConfirm.disabled = true;
        btnConfirm.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Procesando...';
        try {
            await pendingConfirm();
        } finally {
            processing = false;
            btnConfirm.disabled = false;
            btnConfirm.textContent = 'Confirmar';
            pendingConfirm = null;
            modal.hide();
        }
    });

    modalEl.addEventListener('hidden.bs.modal', () => {
        pendingConfirm = null;
    });

    // ── Comunicación HTTP (fetch + JSON + X-Requested-With) ──

    async function handleResponse(res) {
        let json = null;
        try {
            json = await res.json();
        } catch (error) {
            json = null;
        }

        if (!res.ok || !json || !json.success) {
            const message = json && json.message
                ? json.message
                : 'Ocurrió un error inesperado. Inténtalo de nuevo.';
            showMessage(message);
            return null;
        }

        hideMessage();
        return json.data;
    }

    async function postUpdate(productId, quantity) {
        const data = new FormData();
        data.append('product_id', String(productId));
        data.append('quantity', String(quantity));

        const res = await fetch('/cart/update', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: data
        });
        return handleResponse(res);
    }

    async function getAction(url) {
        const res = await fetch(url, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': window.CART_CSRF_TOKEN || ''
            }
        });
        return handleResponse(res);
    }

    // ── Estado de carga por producto ──

    function setRowBusy(row, busy) {
        if (!row) return;
        const dec = row.querySelector('[data-qty-dec]');
        const inc = row.querySelector('[data-qty-inc]');
        const removeLink = row.querySelector('[data-confirm-url]');

        if (busy) {
            if (dec) dec.disabled = true;
            if (inc) inc.disabled = true;
            if (removeLink) removeLink.classList.add('disabled');
            return;
        }

        if (window.QuantityStepper) {
            window.QuantityStepper.sync(row);
        }
        if (removeLink) removeLink.classList.remove('disabled');
    }

    function rowByProductId(productId) {
        return document.querySelector('[data-cart-row][data-product-id="' + String(productId) + '"]');
    }

    // ── Actualizar cantidad ──

    document.addEventListener('qtychange', async (e) => {
        const source = e.target && typeof e.target.closest === 'function'
            ? e.target.closest('[data-cart-row]')
            : null;
        if (!source) return;

        const productId = parseInt(source.getAttribute('data-product-id'), 10);
        const value = parseInt(e.detail && e.detail.value, 10);
        if (!Number.isFinite(productId) || !Number.isFinite(value)) return;

        const key = String(productId);
        if (busyRows[key]) return;
        busyRows[key] = true;

        setRowBusy(source, true);
        hideMessage();

        try {
            const payload = await postUpdate(productId, value);
            if (payload) {
                render(payload);
            }
        } catch (error) {
            showMessage('No fue posible conectar con el servidor.');
        } finally {
            delete busyRows[key];
            setRowBusy(rowByProductId(productId), false);
        }
    });

    // ── Renderizado con la fuente de datos del backend (cartPayload) ──

    function render(payload) {
        const items = payload && Array.isArray(payload.items) ? payload.items : [];
        const counts = {};

        items.forEach((item) => {
            const id = String(item.product_id);
            counts[id] = true;

            const row = rowByProductId(id);
            if (!row) return;

            const input = row.querySelector('[data-qty-input]');
            const subtotal = row.querySelector('[data-cart-subtotal]');
            const stepper = row.querySelector('[data-quantity-stepper]');
            if (input) input.value = String(item.quantity);
            if (subtotal) subtotal.textContent = money(item.subtotal);
            if (stepper) {
                stepper.setAttribute(
                    'data-max',
                    String(Math.max(Number(item.stock) || 0, Number(item.quantity) || 0))
                );
            }
            if (window.QuantityStepper) {
                window.QuantityStepper.sync(row);
            }
        });

        document.querySelectorAll('[data-cart-row]').forEach((row) => {
            if (!counts[String(row.getAttribute('data-product-id'))]) {
                row.parentNode.removeChild(row);
            }
        });

        if (contentEl) contentEl.hidden = items.length === 0;
        if (emptyEl) emptyEl.hidden = items.length > 0;

        if (summarySubtotal) summarySubtotal.textContent = money(payload.subtotal || 0);
        if (summaryIva) summaryIva.textContent = money(payload.iva || 0);
        if (summaryTotal) summaryTotal.textContent = money(payload.total || 0);

        if (window.CartCounter && typeof window.CartCounter.set === 'function') {
            window.CartCounter.set(payload.count || 0);
        }
    }

    // ── Prevenir envió tradicional del formulario de actualización ──

    document.addEventListener('submit', (e) => {
        const form = e.target;
        if (typeof form.matches !== 'function') return;
        if (form.matches('[data-cart-update-form]')) {
            e.preventDefault();
        }
    });

    // ── Delegación: acciones con confirmación (Eliminar / Vaciar) ──

    document.addEventListener('click', (e) => {
        const link = e.target && typeof e.target.closest === 'function'
            ? e.target.closest('[data-confirm-url]')
            : null;
        if (!link) return;
        if (link.classList.contains('disabled')) return;
        e.preventDefault();

        showConfirmModal({
            title: link.getAttribute('data-confirm-title') || 'Confirmar acción',
            message: link.getAttribute('data-confirm-message') || '¿Deseas continuar?',
            onConfirm: async () => {
                const payload = await getAction(link.getAttribute('data-confirm-url'));

                if (payload) {
                    render(payload);
                }
            }
        });
    });
})();
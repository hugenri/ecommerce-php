/**
 * Tarjeta de estado de pago para /pago/{sale_code}.
 *
 * Renderiza en la tarjeta "Método de pago" el resultado de la confirmación
 * contra el backend (paypal o conekta) SIN navegar a otra página: oculta el
 * botón/componente embebido y muestra una confirmación mínima (ícono + título
 * + método de pago + referencia OXXO/SPEI) y un botón "Ver mi pedido".
 *
 * El Folio y el Estado de la venta NO se repiten aquí: viven únicamente en la
 * tarjeta izquierda "Datos del pedido". Este componente solo actualiza su
 * badge de estado vía DOM (sin recarga).
 *
 * El HTML se clona del <template id="pagoResultTemplate"> declarado en la
 * vista (los componentes se definen en el servidor, no se construye HTML
 * desde JavaScript).
 */
(function () {
    'use strict';

    const ORDERS_URL = '/account?section=orders';

    const template = document.getElementById('pagoResultTemplate');
    const resultSlot = document.getElementById('pagoResult');
    const statusBadge = document.getElementById('pagoSaleStatusBadge');

    function hidePaypalButton() {
        const slot = document.getElementById('paypalButton');
        if (slot) {
            slot.innerHTML = '';
            slot.remove();
        }
    }

    function hideConektaFrame() {
        const slot = document.getElementById('conektaIFrame');
        if (slot) {
            slot.innerHTML = '';
            slot.remove();
        }
    }

    function updateStatusBadge(status) {
        if (!statusBadge) {
            return;
        }
        if (status === 'paid') {
            statusBadge.className = 'badge bg-success';
            statusBadge.textContent = 'Pagado';
        } else if (status === 'pending') {
            statusBadge.className = 'badge bg-warning text-dark';
            statusBadge.textContent = 'Pendiente de pago';
        } else if (status === 'failed') {
            statusBadge.className = 'badge bg-danger';
            statusBadge.textContent = 'Rechazado';
        } else {
            statusBadge.className = 'badge bg-secondary';
            statusBadge.textContent = status || '';
        }
    }

    function paymentMethodLabel(method) {
        switch ((method || '').toLowerCase()) {
            case 'paypal':
                return 'PayPal';
            case 'card':
                return 'Tarjeta de crédito/débito';
            case 'cash':
                return 'Efectivo (OXXO / 7-Eleven)';
            case 'bank_transfer':
                return 'Transferencia SPEI';
            default:
                return method || '—';
        }
    }

    function renderStatus(type, reference, paymentMethod) {
        if (!template || !resultSlot) {
            return;
        }

        const clone = template.content.cloneNode(true);
        const icon = clone.querySelector('.pago-status-icon');
        const title = clone.querySelector('.pago-status-title');
        const text = clone.querySelector('.pago-status-text');
        const methodValue = clone.querySelector('.pago-status-method');
        const referenceRow = clone.querySelector('.pago-status-row-reference');
        const referenceValue = clone.querySelector('.pago-status-reference');
        const cta = clone.querySelector('.pago-status-cta');

        cta.href = ORDERS_URL;

        if (methodValue) {
            methodValue.textContent = paymentMethodLabel(paymentMethod);
        }

        if (type === 'success') {
            icon.classList.add('bi-check-circle-fill', 'text-success');
            title.textContent = 'Pago recibido';
            text.textContent = 'Tu pago fue procesado correctamente.';
            referenceRow.hidden = true;
            updateStatusBadge('paid');
        } else if (type === 'pending') {
            icon.classList.add('bi-clock-fill', 'text-warning');
            title.textContent = 'Pago pendiente';
            text.textContent = 'Realiza tu pago con la referencia indicada.';
            referenceRow.hidden = false;
            referenceValue.textContent = reference || '';
            updateStatusBadge('pending');
        } else if (type === 'failed') {
            icon.classList.add('bi-x-circle-fill', 'text-danger');
            title.textContent = 'Pago rechazado';
            text.textContent = 'No se pudo confirmar tu pago. Intenta de nuevo o usa otro método de pago.';
            referenceRow.hidden = true;
            updateStatusBadge('failed');
        }

        resultSlot.innerHTML = '';
        resultSlot.appendChild(clone);
        resultSlot.hidden = false;
    }

    window.PagoStatusCard = {
        hidePaypalButton: hidePaypalButton,
        hideConektaFrame: hideConektaFrame,
        renderSuccess: function (options) {
            renderStatus('success', null, options.paymentMethod);
        },
        renderPending: function (options) {
            renderStatus('pending', options.reference, options.paymentMethod);
        },
        renderFailed: function (options) {
            renderStatus('failed', null, options.paymentMethod);
        }
    };
})();
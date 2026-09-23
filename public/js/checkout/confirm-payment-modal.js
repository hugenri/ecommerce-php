/**
 * Modal de confirmación de pedido compartido entre los flujos de pago.
 *
 * Se usa tanto en Conekta como en PayPal: muestra la dirección de envío
 * seleccionada, el método de pago y el total del pedido, con los botones
 * [Cancelar] y [Continuar al pago]. Solo al confirmar se ejecuta el callback
 * onConfirm que aporta cada flujo.
 *
 * No contiene lógica de negocio: es puramente de confirmación UX. El total se
 * lee de window.CHECKOUT_TOTAL (informativo); el backend re-valida todo.
 *
 * Uso:
 *   window.ConfirmPaymentModal.open({
 *       paymentMethodLabel: 'PayPal',
 *       onConfirm: async function () { ... }
 *   });
 */
(function () {
    'use strict';

    const MODAL_ID = 'confirmPaymentModal';
    const ADDRESS_ID = 'confirmPaymentAddress';
    const PAYMENT_ID = 'confirmPaymentMethod';
    const TOTAL_ID = 'confirmPaymentTotal';

    let modalElement = null;
    let modalInstance = null;

    function getSelectedAddressId() {
        const addressId = document.getElementById('hiddenAddressId').value;
        if (!addressId) {
            throw new Error('Selecciona una dirección de envío para continuar.');
        }
        return addressId;
    }

    function getSelectedAddressText() {
        const card = document.querySelector('.address-card.selected');
        if (!card) {
            return '';
        }
        const alias = card.querySelector('.form-check-label');
        const detail = card.querySelector('small');
        const aliasText = alias ? alias.textContent.trim() : 'Dirección';
        const detailText = detail ? detail.textContent.trim() : '';
        return aliasText + (detailText ? ' — ' + detailText : '');
    }

    function formatTotal() {
        const total = typeof window.CHECKOUT_TOTAL === 'number' && !Number.isNaN(window.CHECKOUT_TOTAL)
            ? window.CHECKOUT_TOTAL
            : 0;
        return '$' + total.toFixed(2);
    }

    function buildModalMarkup() {
        return '' +
            '<div class="modal fade" id="' + MODAL_ID + '" tabindex="-1" aria-hidden="true">' +
            '<div class="modal-dialog">' +
            '<div class="modal-content">' +
            '<div class="modal-header">' +
            '<h5 class="modal-title"><i class="bi bi-credit-card"></i> Confirmar pedido</h5>' +
            '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>' +
            '</div>' +
            '<div class="modal-body">' +
            '<p class="mb-2">Confirma los datos antes de continuar al pago.</p>' +
            '<div class="row">' +
            '<div class="col-12 mb-2"><strong>Dirección de envío</strong><div id="' + ADDRESS_ID + '" class="text-muted"></div></div>' +
            '<div class="col-12 mb-2"><strong>Método de pago</strong><div id="' + PAYMENT_ID + '"></div></div>' +
            '<div class="col-12"><strong>Total</strong> <span id="' + TOTAL_ID + '" class="fs-5 fw-bold"></span></div>' +
            '</div>' +
            '</div>' +
            '<div class="modal-footer">' +
            '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>' +
            '<button type="button" class="btn btn-primary" id="confirmPaymentBtn">' +
            '<i class="bi bi-credit-card"></i> Continuar al pago</button>' +
            '</div>' +
            '</div>' +
            '</div>' +
            '</div>';
    }

    function ensureModal() {
        modalElement = document.getElementById(MODAL_ID);
        if (modalElement) {
            return modalElement;
        }
        const mount = document.getElementById('providerFlowContainer');
        const host = mount || document.body;
        host.insertAdjacentHTML('beforeend', buildModalMarkup());
        return document.getElementById(MODAL_ID);
    }

    function open(options) {
        // ensureModal() DEBE ejecutarse primero: crea el HTML del modal
        // (incluido el botón de confirmar) la primera vez. Buscar el botón
        // antes dejaría el onclick sin asignar en la primera apertura.
        const element = ensureModal();
        modalInstance = bootstrap.Modal.getOrCreateInstance(element);

        const button = document.getElementById('confirmPaymentBtn');
        if (!button) {
            console.error('confirmPaymentBtn no encontrado.');
            return;
        }

        button.disabled = false;
        if (options && typeof options.onConfirm === 'function') {
            // Asignación directa (no addEventListener) para evitar acumular
            // listeners duplicados cada vez que se reabre el modal.
            button.onclick = async function () {
                button.disabled = true;
                try {
                    await options.onConfirm();
                    // Solo se cierra el modal si la confirmación terminó bien.
                    hide();
                } catch (error) {
                    // En caso de error se mantiene el modal abierto y se
                    // rehabilita el botón para que el usuario pueda reintentar.
                    button.disabled = false;
                }
            };
        } else {
            button.onclick = null;
        }

        const addressElement = document.getElementById(ADDRESS_ID);
        const paymentElement = document.getElementById(PAYMENT_ID);
        const totalElement = document.getElementById(TOTAL_ID);
        if (addressElement) {
            addressElement.textContent = getSelectedAddressText();
        }
        if (paymentElement) {
            paymentElement.textContent = (options && options.paymentMethodLabel) ? options.paymentMethodLabel : '';
        }
        if (totalElement) {
            totalElement.textContent = formatTotal();
        }

        modalInstance.show();
    }

    function hide() {
        if (modalInstance) {
            modalInstance.hide();
        }
    }

    // API compartida accesible desde conekta-checkout.js y paypal-checkout.js.
    window.ConfirmPaymentModal = {
        open: open,
        hide: hide,
        getSelectedAddressId: getSelectedAddressId
    };
})();
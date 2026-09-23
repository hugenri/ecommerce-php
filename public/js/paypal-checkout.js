/**
 * Hook de PayPal para el orquestador de checkouts (CHECKOUT_FLOW).
 *
 * En /checkout el cliente elige la dirección y pulsa "Pagar pedido": se abre
 * el modal de confirmación compartido (ConfirmPaymentModal) con la dirección,
 * el método y el total. Solo al pulsar "Continuar al pago" el backend persiste
 * la venta como pending (sin reservar stock) y nos redirige a /pago/{sale_code},
 * donde inicia el componente embebido de PayPal leyendo de la venta persistida.
 * El stock se descuenta al confirmar en /pago (captura síncrona), nunca antes.
 *
 * El total mostrado es informativo (UX); el backend re-valida todo en
 * PlaceOrderUseCase antes de persistir.
 */
(function () {
    'use strict';

    function setMessage(message) {
        const messageElement = document.getElementById('paypalHookMessage');
        if (messageElement) {
            messageElement.textContent = message;
        }
    }

    function renderPaypal(container, api) {
        const wrapper = document.createElement('div');
        wrapper.className = 'card';
        wrapper.innerHTML =
            '<div class="card-body">' +
            '<h6 class="card-title">Pagar pedido</h6>' +
            '<p class="small text-muted">Registramos tu pedido y te llevamos a completar el pago de forma segura.</p>' +
            '<button type="button" class="btn btn-primary w-100" id="paypalPlaceOrderBtn">' +
            '<i class="bi bi-paypal"></i> Pagar pedido</button>' +
            '<div class="small text-muted mt-2" id="paypalHookMessage"></div>' +
            '</div>';
        container.innerHTML = '';
        container.appendChild(wrapper);

        const button = document.getElementById('paypalPlaceOrderBtn');

        button.addEventListener('click', function () {
            setMessage('');
            try {
                window.ConfirmPaymentModal.getSelectedAddressId();
                window.ConfirmPaymentModal.open({
                    paymentMethodLabel: 'PayPal',
                    onConfirm: async function () {
                        const addressId = window.ConfirmPaymentModal.getSelectedAddressId();
                        const data = await api.apiPost('/checkout/paypal/place-order', { address_id: addressId });
                        window.location.href = data.redirect_url;
                    }
                });
            } catch (error) {
                setMessage(error.message);
            }
        });
    }

    function registerHook() {
        if (!window.CHECKOUT_FLOW) {
            return;
        }
        window.CHECKOUT_FLOW.register('paypal', {
            render: function (container, provider, api) {
                renderPaypal(container, api);
            }
        });
    }

    registerHook();
})();
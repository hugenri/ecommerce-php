/**
 * Flujo de pago de Conekta para la página /pago/{sale_code}.
 *
 * A diferencia del checkout (que lee el carrito), aquí la venta ya está
 * persistida en la base de datos y se identifica por su Folio (sale_code).
 *
 * Crea la orden técnica de Conekta a partir de los detalles de la venta
 * persistida (endpoint /pago/{sale_code}/create-order), renderiza el
 * componente embebido (Conekta.js) dentro de nuestro propio dominio y, al
 * finalizar, confirma contra el backend (que re-consulta Conekta antes de
 * promover la venta). La lógica de negocio vive en el backend.
 */
(function () {
    'use strict';

    let settled = false;

    const api = (function () {
        async function post(url, payload) {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': window.CHECKOUT_CSRF_TOKEN || ''
                },
                body: JSON.stringify(payload)
            });
            const json = await response.json();
            if (!response.ok || !json || json.success !== true) {
                throw new Error((json && json.message) || 'Error en la solicitud.');
            }
            return json.data;
        }

        return { post: post };
    })();

    function setMessage(message, type) {
        const messageElement = document.getElementById('conektaHookMessage');
        if (!messageElement) {
            return;
        }
        const variant = (type === 'warning' || type === 'danger') ? type : 'danger';
        messageElement.textContent = message || '';
        messageElement.className = (message ? 'alert alert-' + variant : 'alert d-none');
    }

    function showPendingNote() {
        const note = document.getElementById('conektaPendingNote');
        if (note) {
            note.classList.remove('d-none');
        }
    }

    function loadConektaSdk(onLoaded, onError) {
        if (window.ConektaCheckoutComponents) {
            onLoaded();
            return;
        }

        const script = document.createElement('script');
        script.src = 'https://pay.conekta.com/v1.0/js/conekta-checkout.min.js';
        script.async = true;
        script.crossOrigin = 'anonymous';
        script.onload = onLoaded;
        script.onerror = onError;
        document.head.appendChild(script);
    }

    async function createOrder() {
        return await api.post('/pago/' + window.PAGO_SALE_CODE + '/create-order', {});
    }

    async function confirmPayment(orderId) {
        return await api.post('/pago/' + window.PAGO_SALE_CODE + '/confirm', { order_id: orderId });
    }

    function renderComponent(orderId, checkoutRequestId) {
        const config = {
            locale: 'es',
            publicKey: window.CONEKTA_PUBLIC_KEY || '',
            targetIFrame: '#conektaIFrame',
            checkoutRequestId: checkoutRequestId
        };

        const options = {
            backgroundMode: 'lightMode',
            inputType: 'minimalMode',
            autoResize: true
        };

        const callbacks = {
            onFinalizePayment: async function (order) {
                if (settled) {
                    return;
                }
                setMessage('');
                try {
                    const data = await confirmPayment(order.id || orderId);
                    if (data.status === 'paid') {
                        settled = true;
                        window.PagoStatusCard.hideConektaFrame();
                        window.PagoStatusCard.renderSuccess({
                            paymentMethod: data.payment_method
                        });
                        return;
                    }
                    showPendingNote();
                } catch (error) {
                    if (!settled) {
                        setMessage(error.message, 'danger');
                    }
                }
            },
            onErrorPayment: function (error) {
                if (settled) {
                    return;
                }
                setMessage((error && error.message) ? error.message : error, 'danger');
            }
        };

        window.ConektaCheckoutComponents.Integration({
            config: config,
            callbacks: callbacks,
            options: options
        });
    }

    async function initialize() {
        try {
            const data = await createOrder();
            loadConektaSdk(
                function () {
                    renderComponent(data.order_id, data.checkout_request_id);
                },
                function () {
                    setMessage('No se pudo cargar el componente de pago. Intenta de nuevo más tarde.', 'danger');
                }
            );
        } catch (error) {
            setMessage(error.message, 'danger');
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        if (window.PAGO_PAYMENT_METHOD && window.PAGO_PAYMENT_METHOD !== 'conekta') {
            return;
        }
        if (window.PAGO_SALE_PAID || window.PAGO_SALE_CANCELLED) {
            return;
        }
        if (window.PAGO_PENDING_REFERENCE) {
            if (window.PAGO_CHECKOUT_REQUEST_ID) {
                showPendingNote();
                loadConektaSdk(
                    function () {
                        renderComponent(window.PAGO_CONEXTA_ORDER_ID, window.PAGO_CHECKOUT_REQUEST_ID);
                    },
                    function () {
                        window.PagoStatusCard.hideConektaFrame();
                        window.PagoStatusCard.renderPending({
                            reference: window.PAGO_PENDING_REFERENCE,
                            paymentMethod: window.PAGO_PAYMENT_TYPE
                        });
                    }
                );
                return;
            }
            window.PagoStatusCard.hideConektaFrame();
            window.PagoStatusCard.renderPending({
                reference: window.PAGO_PENDING_REFERENCE,
                paymentMethod: window.PAGO_PAYMENT_TYPE
            });
            return;
        }
        initialize();
    });
})();
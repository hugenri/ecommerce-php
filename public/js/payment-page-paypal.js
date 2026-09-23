/**
 * Flujo de pago de PayPal para la página /pago/{sale_code}.
 *
 * A diferencia del checkout (que leyó el carrito para registrar la venta
 * como pending), aquí la venta ya está persistida y se identifica por su
 * Folio (sale_code). Lanzo únicamente cuando PAGO_PAYMENT_METHOD === 'paypal'.
 *
 * Crea la orden técnica de PayPal a partir de los detalles de la venta
 * persistida (endpoint /pago/{sale_code}/create-order), renderiza el botón
 * del SDK de PayPal y, al aprobar, confirma contra el backend (que
 * re-consulta PayPal antes de promover la venta y descontar el stock). La
 * lógica de negocio vive en el backend.
 */
(function () {
    'use strict';

    let renderedComponent = null;
    let settled = false;

    function config() {
        return {
            saleCode: window.PAGO_SALE_CODE || '',
            clientId: window.PAYPAL_CLIENT_ID || '',
            currency: window.PAYPAL_CURRENCY || 'MXN'
        };
    }

    function setMessage(message, type) {
        const messageElement = document.getElementById('paypalHookMessage');
        if (!messageElement) {
            return;
        }
        const variant = (type === 'warning' || type === 'danger') ? type : 'warning';
        messageElement.textContent = message || '';
        messageElement.className = (message ? 'alert alert-' + variant : 'alert d-none');
    }

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

    async function createOrder() {
        return await api.post('/pago/' + config().saleCode + '/create-order', {});
    }

    async function confirmPayment(orderId) {
        return await api.post('/pago/' + config().saleCode + '/confirm', { order_id: orderId });
    }

    function loadPaypalSdk(onLoaded, onError) {
        if (window.paypal && window.paypal.Buttons) {
            onLoaded();
            return;
        }

        const c = config();
        const script = document.createElement('script');
        script.src = 'https://www.paypal.com/sdk/js?client-id='
            + encodeURIComponent(c.clientId)
            + '&currency=' + encodeURIComponent(c.currency)
            + '&intent=capture'
            + '&disable-funding=card';
        script.async = true;
        script.onload = onLoaded;
        script.onerror = onError;
        // Los handlers quedan asignados antes de montar el script, para que
        // onload no pueda dispararse sin listener (p. ej. con caché).
        document.head.appendChild(script);
    }

    function renderButton(orderId) {
        if (window.PAGO_SALE_PAID || window.PAGO_SALE_CANCELLED) {
            return;
        }

        const buttonSlot = document.getElementById('paypalButton');

        function destroyButton() {
            if (renderedComponent && typeof renderedComponent.close === 'function') {
                try {
                    renderedComponent.close();
                } catch (error) {
                    // El SDK ya lo quitó; se continúa removiendo el contenedor.
                }
                renderedComponent = null;
            }
            if (buttonSlot) {
                buttonSlot.innerHTML = '';
                buttonSlot.remove();
            }
        }

        const component = window.paypal.Buttons({
            style: {
                layout: 'vertical',
                color: 'gold',
                shape: 'rect',
                label: 'paypal'
            },
            createOrder: async function () {
                if (settled) {
                    throw new Error('El pago ya fue procesado.');
                }
                const data = await createOrder();
                return data.order_id;
            },
            onApprove: async function (paypalData) {
                if (settled) {
                    return;
                }
                setMessage('');
                try {
                    const data = await confirmPayment(paypalData.orderID);
                    destroyButton();
                    if (data.status === 'paid') {
                        settled = true;
                        window.PagoStatusCard.renderSuccess({
                            paymentMethod: data.payment_method
                        });
                    } else if (data.status === 'pending') {
                        window.PagoStatusCard.renderPending({
                            reference: data.reference || '',
                            paymentMethod: data.payment_method
                        });
                    } else {
                        window.PagoStatusCard.renderFailed({
                            paymentMethod: data.payment_method
                        });
                    }
                } catch (error) {
                    if (settled) {
                        return;
                    }
                    setMessage(error.message, 'danger');
                }
            },
            onCancel: function () {
                if (settled) {
                    return;
                }
                setMessage('Pago de PayPal cancelado.', 'warning');
            },
            onError: function () {
                if (settled) {
                    return;
                }
                setMessage('No se pudo completar el pago con PayPal. Intenta de nuevo.', 'danger');
            }
        });

        try {
            const promise = component.render(buttonSlot);
            if (promise && typeof promise.then === 'function') {
                promise.then(function (rendered) {
                    if (settled) {
                        if (rendered && typeof rendered.close === 'function') {
                            try {
                                rendered.close();
                            } catch (error) {
                                // Sin efecto: el contenedor ya fue removido.
                            }
                        }
                        return;
                    }
                    renderedComponent = rendered || null;
                });
            }
        } catch (error) {
            if (!settled) {
                setMessage('No se pudo mostrar el botón de PayPal.', 'danger');
            }
        }
    }

    function initialize() {
        try {
            loadPaypalSdk(
                function () {
                    renderButton(null);
                },
                function () {
                    setMessage('No se pudo cargar el botón de PayPal. Intenta de nuevo más tarde.', 'danger');
                }
            );
        } catch (error) {
            setMessage(error.message, 'danger');
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        if (!window.PAGO_PAYMENT_METHOD || window.PAGO_PAYMENT_METHOD !== 'paypal') {
            return;
        }
        if (window.PAGO_SALE_PAID || window.PAGO_SALE_CANCELLED) {
            return;
        }
        initialize();
    });
})();
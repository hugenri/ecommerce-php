/**
 * Orquestador genérico del flujo de pago en el checkout.
 *
 * Lee el catálogo de proveedores (window.CHECKOUT_PROVIDERS) y decide el flujo
 * a partir de sus atributos, sin conocer proveedores específicos:
 *   - gateway + redirect   -> flujo de redirección por defecto
 *   - gateway + SDK propio -> delega en el hook registrado por ese proveedor
 *
 * Un nuevo proveedor externo se agrega al catálogo; si necesita SDK propio,
 * registra un hook con CHECKOUT_FLOW.register(providerId, { render }).
 */
(function () {
    'use strict';

    const hooks = {};

    function providers() {
        return window.CHECKOUT_PROVIDERS || [];
    }

    function findProvider(id) {
        for (let i = 0; i < providers().length; i++) {
            if (providers()[i].id === id) {
                return providers()[i];
            }
        }
        return null;
    }

    async function apiPost(url, payload) {
        const res = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': window.CHECKOUT_CSRF_TOKEN || ''
            },
            body: JSON.stringify(payload)
        });
        const json = await res.json();
        if (!res.ok || !json || json.success !== true) {
            throw new Error((json && json.message) || 'Error en la solicitud.');
        }
        return json.data;
    }

    function flowContainer() {
        return document.getElementById('providerFlowContainer');
    }

    function renderRedirect(provider) {
        const container = flowContainer();
        if (!container) {
            return;
        }
        container.classList.remove('d-none');
        container.innerHTML =
            '<div class="card"><div class="card-body">' +
            '<button type="button" class="btn btn-primary w-100" id="providerRedirectBtn">' +
            'Pagar con ' + provider.name + '</button>' +
            '<div class="small text-muted mt-2" id="providerRedirectMessage"></div>' +
            '</div></div>';

        const btn = document.getElementById('providerRedirectBtn');
        const message = document.getElementById('providerRedirectMessage');

        btn.addEventListener('click', async function () {
            btn.disabled = true;
            message.textContent = 'Redirigiendo al pago...';
            try {
                const addressId = document.getElementById('hiddenAddressId').value;
                if (!addressId) {
                    throw new Error('Selecciona una dirección de envío para continuar.');
                }
                const data = await apiPost(provider.createOrderEndpoint, { address_id: addressId });
                window.location.href = data.checkout_url;
            } catch (error) {
                message.textContent = error.message;
                btn.disabled = false;
            }
        });
    }

    function renderHook(provider) {
        const hook = hooks[provider.id];
        const container = flowContainer();
        if (!hook || !container) {
            return;
        }
        container.classList.remove('d-none');
        hook.render(container, provider, { apiPost: apiPost });
    }

    function activate(id) {
        const provider = findProvider(id);
        const container = flowContainer();
        if (!provider || !container) {
            return;
        }

        if (provider.requiresRedirect && hooks[provider.id] === undefined) {
            renderRedirect(provider);
            return;
        }

        renderHook(provider);
    }

    function register(providerId, hook) {
        hooks[providerId] = hook;
    }

    window.CHECKOUT_FLOW = {
        activate: activate,
        register: register,
        findProvider: findProvider,
        apiPost: apiPost
    };
})();

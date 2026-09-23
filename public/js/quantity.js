/**
 * Quantity Stepper — componente reutilizable.
 *
 * Único archivo JavaScript del stepper para todo el proyecto (ficha del
 * producto, carrito, mini cart y futuras pantallas).
 *
 * Se localiza exclusivamente mediante atributos data-*:
 *   [data-quantity-stepper]  contenedor (con data-min / data-max)
 *   [data-qty-input]         input de cantidad (único origen de verdad)
 *   [data-qty-dec]           botón disminuir
 *   [data-qty-inc]           botón aumentar
 *
 * Los botones solo modifican el valor del input. Usa delegación de eventos,
 * por lo que también funciona con filas renderizadas dinámicamente (mini cart)
 * sin necesidad de reinicializar.
 */
(function (global) {
    'use strict';

    var MIN_DEFAULT = 1;
    var MAX_DEFAULT = 99999;

    function toInt(value, fallback) {
        var n = parseInt(value, 10);
        return isNaN(n) ? fallback : n;
    }

    function bounds(root) {
        var min = toInt(root.getAttribute('data-min'), MIN_DEFAULT);
        var max = toInt(root.getAttribute('data-max'), MAX_DEFAULT);
        if (max < min) {
            max = min;
        }
        return { min: min, max: max };
    }

    function sync(root) {
        var input = root.querySelector('[data-qty-input]');
        var dec = root.querySelector('[data-qty-dec]');
        var inc = root.querySelector('[data-qty-inc]');
        if (!input || !dec || !inc) {
            return;
        }

        var b = bounds(root);
        var current = toInt(input.value, b.min);
        dec.disabled = current <= b.min;
        inc.disabled = current >= b.max;
    }

    function change(root, delta) {
        var input = root.querySelector('[data-qty-input]');
        if (!input) {
            return;
        }

        var b = bounds(root);
        var current = toInt(input.value, b.min);
        var next = Math.max(b.min, Math.min(b.max, current + delta));
        if (next === current) {
            return;
        }

        input.value = String(next);
        sync(root);

        if (root.hasAttribute('data-submit')) {
            var form = root.closest('form');
            if (form) {
                form.submit();
            }
        }

        root.dispatchEvent(new CustomEvent('qtychange', {
            bubbles: true,
            detail: { value: next }
        }));
    }

    document.addEventListener('click', function (e) {
        var target = e.target;
        if (!target || typeof target.closest !== 'function') {
            return;
        }

        var dec = target.closest('[data-qty-dec]');
        var inc = target.closest('[data-qty-inc]');
        if (!dec && !inc) {
            return;
        }

        var root = (dec || inc).closest('[data-quantity-stepper]');
        if (!root) {
            return;
        }

        e.preventDefault();
        change(root, dec ? -1 : 1);
    });

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('[data-quantity-stepper]').forEach(function (root) {
            sync(root);
        });
    });

    global.QuantityStepper = {
        sync: sync
    };
})(window);

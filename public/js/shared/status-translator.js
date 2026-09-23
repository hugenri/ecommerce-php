/**
 * Traductor centralizado de estados ENUM (mismo diccionario que
 * app/Shared/Support/StatusTranslator.php en el lado PHP).
 *
 * Convierte los valores técnicos almacenados en la base de datos a
 * textos amigables en español. Si un valor no existe en el diccionario,
 * devuelve exactamente el valor recibido. Nunca lanza excepciones.
 */
(function (root) {
    'use strict';

    var MAP = {
        active: 'Activo',
        inactive: 'Inactivo',

        pending: 'Pendiente',
        processing: 'En proceso',
        preparing: 'Preparando',
        shipped: 'Enviado',
        delivered: 'Entregado',
        cancelled: 'Cancelado',

        paid: 'Pagado',
        failed: 'Fallido',
        refunded: 'Reembolsado',

        purchase: 'Compra',
        sale: 'Venta',
        adjustment: 'Ajuste',

        admin: 'Administrador',
        employee: 'Empleado'
    };

    var PAYMENT_METHODS = {
        cash: 'Efectivo',
        transfer: 'Transferencia',
        card: 'Tarjeta',
        paypal: 'PayPal',
        conekta: 'Conekta'
    };

    function translate(value) {
        if (value === null || typeof value === 'undefined' || value === '') {
            return value !== null && typeof value !== 'undefined' ? value : '';
        }
        return Object.prototype.hasOwnProperty.call(MAP, value) ? MAP[value] : value;
    }

    function translateNullable(value) {
        if (value === null || typeof value === 'undefined' || value === '') {
            return '';
        }
        return translate(value);
    }

    function paymentMethod(value) {
        if (value === null || typeof value === 'undefined' || value === '') {
            return '';
        }
        return Object.prototype.hasOwnProperty.call(PAYMENT_METHODS, value) ? PAYMENT_METHODS[value] : value;
    }

    function boolean(value) {
        return value && value !== '0' && value !== false
            ? MAP.active
            : MAP.inactive;
    }

    var StatusTranslator = {
        translate: translate,
        translateNullable: translateNullable,
        status: translate,
        paymentStatus: translate,
        deliveryStatus: translate,
        movementType: translate,
        role: translate,
        paymentMethod: paymentMethod,
        boolean: boolean
    };

    root.StatusTranslator = StatusTranslator;
}(typeof window !== 'undefined' ? window : this));
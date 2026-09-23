<?php

declare(strict_types=1);

namespace App\Shared\Support;

/**
 * Traduce los valores técnicos (ENUM) almacenados en la base de datos
 * a textos amigables en español. Pertenece exclusivamente a la capa
 * de presentación (Views/Serializers/Helpers).
 *
 * Clase estática sin dependencias. Nunca lanza excepciones: si un valor
 * no existe en el diccionario, se devuelve exactamente el valor recibido.
 */
final class StatusTranslator
{
    private const MAP = [

        'active' => 'Activo',
        'inactive' => 'Inactivo',

        'pending' => 'Pendiente',
        'processing' => 'En proceso',
        'preparing' => 'Preparando',
        'shipped' => 'Enviado',
        'delivered' => 'Entregado',
        'cancelled' => 'Cancelado',

        'paid' => 'Pagado',
        'failed' => 'Fallido',
        'refunded' => 'Reembolsado',

        'purchase' => 'Compra',
        'sale' => 'Venta',
        'adjustment' => 'Ajuste',

        'admin' => 'Administrador',
        'employee' => 'Empleado',
    ];

    /**
     * Métodos de pago (NO son estados; se mantienen separados de paymentStatus).
     */
    private const PAYMENT_METHODS = [
        'cash' => 'Efectivo',
        'transfer' => 'Transferencia',
        'card' => 'Tarjeta',
        'paypal' => 'PayPal',
        'conekta' => 'Conekta',
    ];

    public static function translate(string $value): string
    {
        return self::MAP[$value] ?? $value;
    }

    public static function translateNullable(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return self::translate($value);
    }

    public static function status(string $value): string
    {
        return self::translate($value);
    }

    public static function paymentStatus(string $value): string
    {
        return self::translate($value);
    }

    public static function deliveryStatus(string $value): string
    {
        return self::translate($value);
    }

    public static function movementType(string $value): string
    {
        return self::translate($value);
    }

    public static function role(string $value): string
    {
        return self::translate($value);
    }

    public static function paymentMethod(string $value): string
    {
        return self::PAYMENT_METHODS[$value] ?? $value;
    }

    public static function boolean(bool $value): string
    {
        return $value ? self::MAP['active'] : self::MAP['inactive'];
    }
}
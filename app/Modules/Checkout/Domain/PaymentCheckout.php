<?php

declare(strict_types=1);

namespace App\Modules\Checkout\Domain;

/**
 * Resultado de la creación de una orden con checkout embebido (Integration).
 *
 * Proporciona el identificador de la orden del proveedor y el
 * checkoutRequestId necesario para inicializar el componente embebido de
 * Conekta.js en el navegador del cliente, dentro de nuestro propio dominio.
 */
final class PaymentCheckout
{
    public function __construct(
        private readonly string $orderId,
        private readonly string $checkoutRequestId,
    ) {}

    public function orderId(): string
    {
        return $this->orderId;
    }

    public function checkoutRequestId(): string
    {
        return $this->checkoutRequestId;
    }
}

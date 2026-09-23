<?php

declare(strict_types=1);

namespace App\Modules\Checkout\Domain;

/**
 * Ítem del desglose de una orden de pago enviada al proveedor.
 */
final class PaymentOrderItem
{
    public function __construct(
        private readonly string $name,
        private readonly int $quantity,
        private readonly float $unitAmount,
    ) {}

    public function getName(): string
    {
        return $this->name;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getUnitAmount(): float
    {
        return $this->unitAmount;
    }
}
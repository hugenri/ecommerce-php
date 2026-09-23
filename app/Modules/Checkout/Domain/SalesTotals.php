<?php

declare(strict_types=1);

namespace App\Modules\Checkout\Domain;

/**
 * Totales de una venta según la regla de negocio SAT:
 * SubTotal, IVA y Total a 2 decimales.
 */
final class SalesTotals
{
    public function __construct(
        private readonly float $subtotal,
        private readonly float $tax,
        private readonly float $total,
    ) {}

    public function getSubtotal(): float
    {
        return $this->subtotal;
    }

    public function getTax(): float
    {
        return $this->tax;
    }

    public function getTotal(): float
    {
        return $this->total;
    }
}
<?php

declare(strict_types=1);

namespace App\Modules\Checkout\Domain;

/**
 * Regla de negocio SAT para importes (Guía de llenado CFDI 4.0):
 * redondeo a 2 decimales por concepto/línea antes de sumar.
 *
 * Fórmula:
 *  precio unitario con descuento = round(precio × (1 − descuento/100), 2)
 *  importe de línea              = round(precio unitario con descuento × cantidad, 2)
 *  SubTotal                      = Σ importes de línea
 *  IVA                           = round(SubTotal × TAX_RATE, 2)
 *  Total                         = SubTotal + IVA
 */
final class SalePriceCalculator
{
    public const TAX_RATE = 0.16;

    private const DECIMALS = 2;

    public function discountedUnitPrice(float $unitPrice, float $discountPct): float
    {
        return round($unitPrice * (1 - $discountPct / 100), self::DECIMALS);
    }

    public function lineAmount(float $unitPrice, float $discountPct, int $quantity): float
    {
        return round($this->discountedUnitPrice($unitPrice, $discountPct) * $quantity, self::DECIMALS);
    }

    public function totals(float $subtotal): SalesTotals
    {
        $tax = round($subtotal * self::TAX_RATE, self::DECIMALS);

        return new SalesTotals(
            subtotal: $subtotal,
            tax: $tax,
            total: round($subtotal + $tax, self::DECIMALS),
        );
    }
}
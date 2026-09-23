<?php

declare(strict_types=1);

namespace App\Modules\Checkout\Domain;

/**
 * Orden de pago enviada al proveedor para crear una transacción pendiente.
 */
final class PaymentOrder
{
    /**
     * @param array<int, PaymentOrderItem> $items
     * @param array<string, mixed>         $metadata
     */
    public function __construct(
        private readonly float $amount,
        private readonly string $currency,
        private readonly string $description,
        private readonly string $reference,
        private readonly array $items = [],
        private readonly float $subtotal = 0.0,
        private readonly float $tax = 0.0,
        private readonly ?BuyerInfo $buyer = null,
        private readonly array $metadata = [],
    ) {}

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getReference(): string
    {
        return $this->reference;
    }

    /**
     * @return array<int, PaymentOrderItem>
     */
    public function getItems(): array
    {
        return $this->items;
    }

    public function getSubtotal(): float
    {
        return $this->subtotal;
    }

    public function getTax(): float
    {
        return $this->tax;
    }

    public function getBuyer(): ?BuyerInfo
    {
        return $this->buyer;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }
}
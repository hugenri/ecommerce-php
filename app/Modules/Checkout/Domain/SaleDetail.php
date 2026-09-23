<?php

declare(strict_types=1);

namespace App\Modules\Checkout\Domain;

class SaleDetail
{
    public function __construct(
        private readonly ?int $detailId,
        private int $saleId,
        private int $productId,
        private int $quantity,
        private float $unitPrice,
        private float $discountPercentage = 0.0,
        private float $discountedUnitPrice = 0.0,
        private ?float $subtotal = null,
    ) {}

    public function getDetailId(): ?int { return $this->detailId; }
    public function getSaleId(): int { return $this->saleId; }
    public function getProductId(): int { return $this->productId; }
    public function getQuantity(): int { return $this->quantity; }
    public function getUnitPrice(): float { return $this->unitPrice; }
    public function getDiscountPercentage(): float { return $this->discountPercentage; }
    public function getDiscountedUnitPrice(): float { return $this->discountedUnitPrice; }
    public function getSubtotal(): ?float { return $this->subtotal; }

    public function withSaleId(int $saleId): self
    {
        return $this->cloneWith(['saleId' => $saleId]);
    }

    private function cloneWith(array $overrides): self
    {
        return new self(
            detailId: $overrides['detailId'] ?? $this->detailId,
            saleId: $overrides['saleId'] ?? $this->saleId,
            productId: $overrides['productId'] ?? $this->productId,
            quantity: $overrides['quantity'] ?? $this->quantity,
            unitPrice: $overrides['unitPrice'] ?? $this->unitPrice,
            discountPercentage: $overrides['discountPercentage'] ?? $this->discountPercentage,
            discountedUnitPrice: $overrides['discountedUnitPrice'] ?? $this->discountedUnitPrice,
            subtotal: $overrides['subtotal'] ?? $this->subtotal,
        );
    }
}

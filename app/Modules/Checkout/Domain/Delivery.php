<?php

declare(strict_types=1);

namespace App\Modules\Checkout\Domain;

class Delivery
{
    public function __construct(
        private readonly ?int $deliveryId,
        private int $saleId,
        private ?int $userId = null,
        private ?\DateTimeImmutable $shippingDate = null,
        private ?\DateTimeImmutable $deliveryDate = null,
        private string $status = 'pending',
    ) {}

    public function getDeliveryId(): ?int { return $this->deliveryId; }
    public function getSaleId(): int { return $this->saleId; }
    public function getUserId(): ?int { return $this->userId; }
    public function getShippingDate(): ?\DateTimeImmutable { return $this->shippingDate; }
    public function getDeliveryDate(): ?\DateTimeImmutable { return $this->deliveryDate; }
    public function getStatus(): string { return $this->status; }

    public function withSaleId(int $saleId): self
    {
        return $this->cloneWith(['saleId' => $saleId]);
    }

    private function cloneWith(array $overrides): self
    {
        return new self(
            deliveryId: $overrides['deliveryId'] ?? $this->deliveryId,
            saleId: $overrides['saleId'] ?? $this->saleId,
            userId: $overrides['userId'] ?? $this->userId,
            shippingDate: $overrides['shippingDate'] ?? $this->shippingDate,
            deliveryDate: $overrides['deliveryDate'] ?? $this->deliveryDate,
            status: $overrides['status'] ?? $this->status,
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Domain;

class InventoryMovement
{
    public const TYPE_PURCHASE = 'purchase';
    public const TYPE_SALE = 'sale';
    public const TYPE_ADJUSTMENT = 'adjustment';

    public const ORIGIN_PURCHASE = 'purchase';
    public const ORIGIN_SALE = 'sale';
    public const ORIGIN_ADJUSTMENT = 'adjustment';

    public function __construct(
        private readonly ?int $movementId,
        private readonly int $productId,
        private readonly string $movementType,
        private readonly int $quantity,
        private readonly int $previousStock,
        private readonly int $currentStock,
        private readonly ?string $reason = null,
        private readonly ?int $userId = null,
        private readonly string $originType = self::ORIGIN_PURCHASE,
        private readonly ?int $originId = null,
        private readonly ?\DateTimeImmutable $createdAt = null,
    ) {}

    public function getMovementId(): ?int { return $this->movementId; }
    public function getProductId(): int { return $this->productId; }
    public function getMovementType(): string { return $this->movementType; }
    public function getQuantity(): int { return $this->quantity; }
    public function getPreviousStock(): int { return $this->previousStock; }
    public function getCurrentStock(): int { return $this->currentStock; }
    public function getReason(): ?string { return $this->reason; }
    public function getUserId(): ?int { return $this->userId; }
    public function getOriginType(): string { return $this->originType; }
    public function getOriginId(): ?int { return $this->originId; }
    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }

    public function withMovementId(int $movementId): self
    {
        return new self(
            movementId: $movementId,
            productId: $this->productId,
            movementType: $this->movementType,
            quantity: $this->quantity,
            previousStock: $this->previousStock,
            currentStock: $this->currentStock,
            reason: $this->reason,
            userId: $this->userId,
            originType: $this->originType,
            originId: $this->originId,
            createdAt: $this->createdAt,
        );
    }
}

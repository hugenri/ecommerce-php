<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\UseCases;

use App\Modules\Inventory\Domain\InventoryMovement;
use App\Modules\Inventory\Domain\InventoryRepositoryInterface;

class AddStockUseCase
{
    public function __construct(
        private InventoryRepositoryInterface $inventoryRepository,
    ) {}

    public function execute(int $productId, int $quantity, int $userId, ?string $reason = null): InventoryMovement
    {
        if ($quantity <= 0) {
            throw new \DomainException('La cantidad de entrada debe ser mayor a cero.');
        }

        return $this->inventoryRepository->transaction(function () use ($productId, $quantity, $userId, $reason) {
            $previous = $this->inventoryRepository->currentStock($productId);
            if ($previous === null) {
                throw new \DomainException('El producto no existe.');
            }

            $current = $previous + $quantity;

            $this->inventoryRepository->updateProductStock($productId, $current);

            return $this->inventoryRepository->recordMovement(new InventoryMovement(
                movementId: null,
                productId: $productId,
                movementType: InventoryMovement::TYPE_PURCHASE,
                quantity: $quantity,
                previousStock: $previous,
                currentStock: $current,
                reason: $reason,
                userId: $userId,
                originType: InventoryMovement::ORIGIN_PURCHASE,
                originId: null,
                createdAt: new \DateTimeImmutable(),
            ));
        });
    }
}

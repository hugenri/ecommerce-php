<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\UseCases;

use App\Modules\Inventory\Domain\InventoryMovement;
use App\Modules\Inventory\Domain\InventoryRepositoryInterface;

class AdjustStockUseCase
{
    public function __construct(
        private InventoryRepositoryInterface $inventoryRepository,
    ) {}

    /**
     * Corrige el stock de un producto a un valor absoluto (stock corregido).
     */
    public function execute(int $productId, int $correctedStock, int $userId, string $reason): InventoryMovement
    {
        if ($correctedStock < 0) {
            throw new \DomainException('El stock no puede ser negativo.');
        }

        $reason = trim($reason);
        if ($reason === '') {
            throw new \DomainException('El motivo del ajuste es obligatorio.');
        }

        return $this->inventoryRepository->transaction(function () use ($productId, $correctedStock, $userId, $reason) {
            $previous = $this->inventoryRepository->currentStock($productId);
            if ($previous === null) {
                throw new \DomainException('El producto no existe.');
            }

            $this->inventoryRepository->updateProductStock($productId, $correctedStock);

            return $this->inventoryRepository->recordMovement(new InventoryMovement(
                movementId: null,
                productId: $productId,
                movementType: InventoryMovement::TYPE_ADJUSTMENT,
                quantity: $correctedStock - $previous,
                previousStock: $previous,
                currentStock: $correctedStock,
                reason: $reason,
                userId: $userId,
                originType: InventoryMovement::ORIGIN_ADJUSTMENT,
                originId: null,
                createdAt: new \DateTimeImmutable(),
            ));
        });
    }
}

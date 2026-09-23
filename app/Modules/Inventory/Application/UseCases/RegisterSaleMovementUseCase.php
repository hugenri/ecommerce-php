<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\UseCases;

use App\Modules\Inventory\Domain\InventoryMovement;
use App\Modules\Inventory\Domain\InventoryRepositoryInterface;

/**
 * Registra movimientos de inventario originados por el flujo de ventas.
 *
 * Este caso de uso es el único punto por el que Sales/Checkout puede
 * afectar el stock: la venta descuenta y la cancelación restaura, y en
 * ambos casos se registra un movimiento auditado dentro de la misma
 * transacción en la que se actualiza products.stock.
 */
class RegisterSaleMovementUseCase
{
    public function __construct(
        private InventoryRepositoryInterface $inventoryRepository,
    ) {}

    /**
     * Descuenta stock por una venta confirmada.
     *
     * @param int  $productId id del producto
     * @param int  $quantity  cantidad vendida (positiva)
     * @param int  $saleId    id de la venta que origina el movimiento
     * @param ?int $userId    usuario interno (NULL para checkout de cliente)
     */
    public function deductStock(
        int $productId,
        int $quantity,
        int $saleId,
        ?int $userId = null,
        ?string $reason = null
    ): InventoryMovement {
        if ($quantity <= 0) {
            throw new \DomainException('La cantidad de salida debe ser mayor a cero.');
        }

        if ($reason === null || trim($reason) === '') {
            $reason = 'Venta #' . $saleId;
        }

        return $this->inventoryRepository->transaction(function () use ($productId, $quantity, $saleId, $userId, $reason) {
            $previous = $this->inventoryRepository->currentStock($productId);
            if ($previous === null) {
                throw new \DomainException('El producto no existe.');
            }

            $current = $previous - $quantity;
            if ($current < 0) {
                throw new \DomainException('No hay stock suficiente para la venta.');
            }

            $this->inventoryRepository->updateProductStock($productId, $current);

            return $this->inventoryRepository->recordMovement(new InventoryMovement(
                movementId: null,
                productId: $productId,
                movementType: InventoryMovement::TYPE_SALE,
                quantity: -$quantity,
                previousStock: $previous,
                currentStock: $current,
                reason: $reason,
                userId: $userId,
                originType: InventoryMovement::ORIGIN_SALE,
                originId: $saleId,
                createdAt: new \DateTimeImmutable(),
            ));
        });
    }

    /**
     * Restaura el stock de un producto al cancelar una venta.
     *
     * @param int    $productId id del producto
     * @param int    $quantity  cantidad a restaurar (positiva)
     * @param int    $saleId    id de la venta cancelada
     * @param ?int   $userId    usuario que cancela (admin/empleado)
     * @param string $reason    motivo del movimiento
     */
    public function restoreStock(
        int $productId,
        int $quantity,
        int $saleId,
        ?int $userId = null,
        string $reason = ''
    ): InventoryMovement {
        if ($quantity <= 0) {
            throw new \DomainException('La cantidad a restaurar debe ser mayor a cero.');
        }

        $reason = trim($reason);
        if ($reason === '') {
            throw new \DomainException('El motivo de la restauración es obligatorio.');
        }

        return $this->inventoryRepository->transaction(function () use ($productId, $quantity, $saleId, $userId, $reason) {
            $previous = $this->inventoryRepository->currentStock($productId);
            if ($previous === null) {
                throw new \DomainException('El producto no existe.');
            }

            $current = $previous + $quantity;

            $this->inventoryRepository->updateProductStock($productId, $current);

            return $this->inventoryRepository->recordMovement(new InventoryMovement(
                movementId: null,
                productId: $productId,
                movementType: InventoryMovement::TYPE_ADJUSTMENT,
                quantity: $quantity,
                previousStock: $previous,
                currentStock: $current,
                reason: $reason,
                userId: $userId,
                originType: InventoryMovement::ORIGIN_SALE,
                originId: $saleId,
                createdAt: new \DateTimeImmutable(),
            ));
        });
    }
}

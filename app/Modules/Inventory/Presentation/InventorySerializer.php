<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Presentation;

use App\Modules\Inventory\Domain\InventoryMovement;
use App\Shared\Support\StatusTranslator;

class InventorySerializer
{
    public function stockLevel(array $row): array
    {
        return [
            'product_id' => (int) ($row['product_id'] ?? 0),
            'product_code' => $row['product_code'] ?? '',
            'name' => $row['name'] ?? '',
            'stock' => (int) ($row['stock'] ?? 0),
            'status' => $row['status'] ?? 'active',
            'is_active' => ($row['status'] ?? 'active') === 'active',
            'image' => $row['image'] ?? '',
            'category_name' => $row['category_name'] ?? '',
            'last_movement_at' => $row['last_movement_at'] ?? '',
        ];
    }

    public function movement(array $row): array
    {
        return [
            'movement_id' => (int) ($row['movement_id'] ?? 0),
            'product_id' => (int) ($row['product_id'] ?? 0),
            'product_code' => $row['product_code'] ?? '',
            'product_name' => $row['product_name'] ?? '',
            'movement_type' => $row['movement_type'] ?? '',
            'quantity' => (int) ($row['quantity'] ?? 0),
            'previous_stock' => (int) ($row['previous_stock'] ?? 0),
            'current_stock' => (int) ($row['current_stock'] ?? 0),
            'reason' => $this->resolveReason($row),
            'user_id' => isset($row['user_id']) ? (int) $row['user_id'] : null,
            'user_name' => $row['user_name'] ?? '',
            'origin_type' => $row['origin_type'] ?? '',
            'origin_id' => isset($row['origin_id']) ? (int) $row['origin_id'] : null,
            'created_at' => $row['created_at'] ?? '',
        ];
    }

    /**
     * Devuelve un motivo legible para el movimiento.
     *
     * Prioridad:
     * 1. El motivo capturado (purchase/adjustment) si no está vacío.
     * 2. Para ventas (movement_type = sale): "Venta {sale_code}".
     * 3. Para restauraciones por cancelación (adjustment con origin sale):
     *    "Cancelación de venta {sale_code}".
     * 4. Texto por defecto según el tipo, si no hay código de venta.
     */
    private function resolveReason(array $row): string
    {
        $reason = trim((string) ($row['reason'] ?? ''));
        if ($reason !== '') {
            return $reason;
        }

        $movementType = $row['movement_type'] ?? '';
        $originType = $row['origin_type'] ?? '';
        $originId = isset($row['origin_id']) ? (int) $row['origin_id'] : null;
        $saleCode = trim((string) ($row['sale_code'] ?? ''));

        $saleRef = $saleCode !== '' ? $saleCode : ('#' . ($originId ?? ''));

        if ($movementType === 'sale') {
            return StatusTranslator::movementType('sale') . ' ' . $saleRef;
        }

        if ($movementType === 'adjustment' && $originType === 'sale') {
            return 'Cancelación de ' . StatusTranslator::movementType('sale') . ' ' . $saleRef;
        }

        $label = StatusTranslator::movementType($movementType);
        return $label !== '' ? $label : 'Movimiento de inventario';
    }

    public function movementToArray(InventoryMovement $movement): array
    {
        $movementType = $movement->getMovementType();
        $originType = $movement->getOriginType();
        $originId = $movement->getOriginId();
        $reason = trim((string) ($movement->getReason() ?? ''));

        if ($reason === '') {
            $saleRef = '#' . ($originId ?? '');
            if ($movementType === 'sale') {
                $reason = StatusTranslator::movementType('sale') . ' ' . $saleRef;
            } elseif ($movementType === 'adjustment' && $originType === 'sale') {
                $reason = 'Cancelación de ' . StatusTranslator::movementType('sale') . ' ' . $saleRef;
            } else {
                $label = StatusTranslator::movementType($movementType);
                $reason = $label !== '' ? $label : 'Movimiento de inventario';
            }
        }

        return [
            'movement_id' => $movement->getMovementId(),
            'product_id' => $movement->getProductId(),
            'movement_type' => $movementType,
            'quantity' => $movement->getQuantity(),
            'previous_stock' => $movement->getPreviousStock(),
            'current_stock' => $movement->getCurrentStock(),
            'reason' => $reason,
            'user_id' => $movement->getUserId(),
            'origin_type' => $originType,
            'origin_id' => $originId,
            'created_at' => $movement->getCreatedAt()?->format('Y-m-d H:i:s'),
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Domain;

interface InventoryRepositoryInterface
{
    /**
     * Existencias actuales de todos los productos.
     *
     * @return array{data: array, meta: array}
     */
    public function stockLevels(
        int $page = 1,
        int $perPage = 10,
        string $search = '',
        string $sortBy = 'name',
        string $sortDir = 'ASC'
    ): array;

    /**
     * Historial de movimientos de inventario con filtros opcionales.
     * Filtros soportados: product_id, movement_type, user_id, date_from, date_to.
     *
     * @return array{data: array, meta: array}
     */
    public function movements(
        int $page = 1,
        int $perPage = 10,
        array $filters = []
    ): array;

    /**
     * Reporte de productos por debajo del umbral de stock.
     *
     * @return array{data: array, meta: array}
     */
    public function lowStock(int $threshold, int $page = 1, int $perPage = 10): array;

    /**
     * Stock actual de un producto, o null si no existe.
     */
    public function currentStock(int $productId): ?int;

    /**
     * true si ya existe al menos un movimiento de tipo venta (origin_type='sale')
     * asociado al sale_id dado; permite descuadrar stock de una venta una sola vez.
     */
    public function hasSaleMovement(int $saleId): bool;

    /**
     * Actualiza el stock actual de un producto.
     */
    public function updateProductStock(int $productId, int $stock): void;

    /**
     * Registra un movimiento de inventario.
     */
    public function recordMovement(InventoryMovement $movement): InventoryMovement;

    /**
     * Ejecuta una operación dentro de una transacción.
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public function transaction(callable $callback): mixed;
}

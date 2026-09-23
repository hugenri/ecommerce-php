<?php

declare(strict_types=1);

namespace App\Modules\Products\Domain;

interface ProductRepositoryInterface
{
    public function create(Product $product): Product;

    public function update(Product $product): Product;

    public function delete(int $id): bool;

    public function findById(int $id): ?Product;

    /**
     * Varios productos por id (incluye inactivos; sin regla de visibilidad).
     * Resultado indexado por product_id.
     *
     * @param int[] $ids
     * @return array<int, Product>
     */
    public function findByIds(array $ids): array;

    public function findByCode(string $code): ?Product;

    public function existsByCode(string $code, int $exceptId = 0): bool;

    public function hasSales(int $productId): bool;

    /** @return array{data: array, meta: array} */
    public function paginate(
        int $page = 1,
        int $perPage = 10,
        string $search = '',
        string $sortBy = 'products.name',
        string $sortDir = 'ASC',
        array $filters = []
    ): array;

    /** @return Product[] */
    public function search(string $query, int $limit = 10): array;

    /**
     * Sugerencias de búsqueda del catálogo público. Coincidencias por nombre,
     * aplicando la regla única de visibilidad y priorizando las coincidencias
     * que comienzan por el término.
     *
     * @return Product[]
     */
    public function searchSuggestions(string $query, int $limit = 8): array;

    /**
     * Listado paginado del catálogo público (/shop). Aplica la regla única
     * de visibilidad (producto activo + registro en inventory_movements).
     *
     * @return array{data: array, meta: array}
     */
    public function paginateCatalog(
        int $page = 1,
        int $perPage = 10,
        string $search = '',
        string $sortBy = 'products.name',
        string $sortDir = 'ASC',
        array $filters = []
    ): array;

    /**
     * Producto del catálogo público por id. Devuelve null si no cumple la
     * regla única de visibilidad (aunque exista como producto interno).
     */
    public function findVisible(int $id): ?Product;

    /**
     * Productos recientes del catálogo público (activos + registro en
     * inventory_movements; el stock no interviene).
     *
     * @return Product[]
     */
    public function recentProducts(int $limit = 8): array;

    /**
     * Productos con descuento del catálogo público (activos, discount > 0 y
     * registro en inventory_movements).
     *
     * @return Product[]
     */
    public function saleProducts(int $limit = 8): array;

    public function activate(int $id): ?Product;

    public function deactivate(int $id): ?Product;

    public function updatePrice(int $id, float $price): ?Product;

    public function updateDiscount(int $id, float $discount): ?Product;

    public function updateStock(int $id, int $stock): ?Product;

    public function changeMainImage(int $id, string $image): ?Product;

    // ──── Product images ───────────────────────────────

    /** @return ProductImage[] */
    public function getImages(int $productId): array;

    public function addImage(int $productId, string $image): ProductImage;

    public function removeImage(int $imageId): bool;

    public function reorderImages(int $productId, array $imageIds): void;
}

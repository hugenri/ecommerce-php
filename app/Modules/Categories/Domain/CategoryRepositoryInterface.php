<?php

declare(strict_types=1);

namespace App\Modules\Categories\Domain;

interface CategoryRepositoryInterface
{
    public function create(Category $category): Category;

    public function update(Category $category): Category;

    public function delete(int $id): bool;

    public function findById(int $id): ?Category;

    public function findByName(string $name): ?Category;

    public function existsByName(string $name, int $exceptId = 0): bool;

    public function hasSubcategories(int $categoryId): bool;

    /**
     * Categorías del catálogo público: activas y con al menos un producto
     * visible (subcategoría activa + producto que cumple la regla única de
     * visibilidad del catálogo).
     *
     * @return Category[]
     */
    public function catalogCategories(): array;

    /** @return array{data: array, meta: array} */
    public function paginate(
        int $page = 1,
        int $perPage = 10,
        string $search = '',
        string $sortBy = 'name',
        string $sortDir = 'ASC',
        array $filters = []
    ): array;

    public function activate(int $id): ?Category;

    public function deactivate(int $id): ?Category;
}

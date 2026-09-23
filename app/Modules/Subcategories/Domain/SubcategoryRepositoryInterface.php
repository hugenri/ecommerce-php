<?php

declare(strict_types=1);

namespace App\Modules\Subcategories\Domain;

interface SubcategoryRepositoryInterface
{
    public function create(Subcategory $subcategory): Subcategory;

    public function update(Subcategory $subcategory): Subcategory;

    public function delete(int $id): bool;

    public function findById(int $id): ?Subcategory;

    public function findByName(string $name, int $categoryId): ?Subcategory;

    public function existsByName(string $name, int $categoryId, int $exceptId = 0): bool;

    public function existsInCategory(int $categoryId): bool;

    public function hasProducts(int $subcategoryId): bool;

    /** @return array{data: array, meta: array} */
    public function paginate(
        int $page = 1,
        int $perPage = 10,
        string $search = '',
        string $sortBy = 'name',
        string $sortDir = 'ASC',
        array $filters = []
    ): array;

    /** @return Subcategory[] */
    public function findByCategory(int $categoryId): array;

    /**
     * Todas las subcategorías activas del catálogo público, sin filtrar por
     * categoría: necesario para poblar el árbol completo del navbar.
     *
     * @return Subcategory[]
     */
    public function catalogSubcategories(): array;

    public function activate(int $id): ?Subcategory;

    public function deactivate(int $id): ?Subcategory;
}

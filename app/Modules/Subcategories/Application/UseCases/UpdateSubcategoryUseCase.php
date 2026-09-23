<?php

declare(strict_types=1);

namespace App\Modules\Subcategories\Application\UseCases;

use App\Modules\Subcategories\Domain\Subcategory;
use App\Modules\Subcategories\Domain\SubcategoryRepositoryInterface;
use App\Modules\Categories\Domain\CategoryRepositoryInterface;

class UpdateSubcategoryUseCase
{
    public function __construct(
        private SubcategoryRepositoryInterface $subcategoryRepository,
        private CategoryRepositoryInterface $categoryRepository,
    ) {}

    public function execute(int $id, array $data): ?Subcategory
    {
        $subcategory = $this->subcategoryRepository->findById($id);

        if (!$subcategory) {
            return null;
        }

        $categoryId = isset($data['category_id']) ? (int) $data['category_id'] : $subcategory->getCategoryId();
        $name = trim($data['name'] ?? $subcategory->getName());

        if ($categoryId !== $subcategory->getCategoryId() && !$this->categoryRepository->findById($categoryId)) {
            throw new \DomainException('La categoría especificada no existe.');
        }

        if ($name !== $subcategory->getName() || $categoryId !== $subcategory->getCategoryId()) {
            if ($this->subcategoryRepository->existsByName($name, $categoryId, $id)) {
                throw new \DomainException('Ya existe una subcategoría con ese nombre en esta categoría.');
            }
        }

        $subcategory = $subcategory->updateInfo(
            categoryId: $categoryId,
            name: $name,
            description: isset($data['description']) ? trim($data['description']) : $subcategory->getDescription(),
        );

        return $this->subcategoryRepository->update($subcategory);
    }
}

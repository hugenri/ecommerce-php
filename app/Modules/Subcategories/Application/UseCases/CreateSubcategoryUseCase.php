<?php

declare(strict_types=1);

namespace App\Modules\Subcategories\Application\UseCases;

use App\Modules\Subcategories\Domain\Subcategory;
use App\Modules\Subcategories\Domain\SubcategoryRepositoryInterface;
use App\Modules\Categories\Domain\CategoryRepositoryInterface;

class CreateSubcategoryUseCase
{
    public function __construct(
        private SubcategoryRepositoryInterface $subcategoryRepository,
        private CategoryRepositoryInterface $categoryRepository,
    ) {}

    public function execute(array $data): Subcategory
    {
        $categoryId = (int) ($data['category_id'] ?? 0);
        $name = trim($data['name'] ?? '');

        if (!$this->categoryRepository->findById($categoryId)) {
            throw new \DomainException('La categoría especificada no existe.');
        }

        if ($this->subcategoryRepository->existsByName($name, $categoryId)) {
            throw new \DomainException('Ya existe una subcategoría con ese nombre en esta categoría.');
        }

        $subcategory = new Subcategory(
            subcategoryId: null,
            categoryId: $categoryId,
            name: $name,
            description: isset($data['description']) ? trim($data['description']) : null,
            status: 'active',
        );

        return $this->subcategoryRepository->create($subcategory);
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Subcategories\Application\UseCases;

use App\Modules\Subcategories\Domain\SubcategoryRepositoryInterface;

class ListSubcategoriesByCategoryUseCase
{
    public function __construct(
        private SubcategoryRepositoryInterface $subcategoryRepository,
    ) {}

    public function execute(int $categoryId): array
    {
        return $this->subcategoryRepository->findByCategory($categoryId);
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Categories\Application\UseCases;

use App\Modules\Categories\Domain\CategoryRepositoryInterface;

class DeleteCategoryUseCase
{
    public function __construct(
        private CategoryRepositoryInterface $categoryRepository
    ) {}

    public function execute(int $id): bool
    {
        $category = $this->categoryRepository->findById($id);

        if (!$category) {
            return false;
        }

        if ($this->categoryRepository->hasSubcategories($id)) {
            throw new \DomainException('No se puede eliminar una categoría con subcategorías asociadas.');
        }

        return $this->categoryRepository->delete($id);
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Subcategories\Application\UseCases;

use App\Modules\Subcategories\Domain\SubcategoryRepositoryInterface;

class DeleteSubcategoryUseCase
{
    public function __construct(
        private SubcategoryRepositoryInterface $subcategoryRepository,
    ) {}

    public function execute(int $id): bool
    {
        $subcategory = $this->subcategoryRepository->findById($id);

        if (!$subcategory) {
            return false;
        }

        if ($this->subcategoryRepository->hasProducts($id)) {
            throw new \DomainException('No se puede eliminar una subcategoría con productos asociados.');
        }

        return $this->subcategoryRepository->delete($id);
    }
}

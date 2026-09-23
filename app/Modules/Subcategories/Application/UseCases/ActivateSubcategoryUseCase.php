<?php

declare(strict_types=1);

namespace App\Modules\Subcategories\Application\UseCases;

use App\Modules\Subcategories\Domain\Subcategory;
use App\Modules\Subcategories\Domain\SubcategoryRepositoryInterface;

class ActivateSubcategoryUseCase
{
    public function __construct(
        private SubcategoryRepositoryInterface $subcategoryRepository,
    ) {}

    public function execute(int $id): ?Subcategory
    {
        return $this->subcategoryRepository->activate($id);
    }
}

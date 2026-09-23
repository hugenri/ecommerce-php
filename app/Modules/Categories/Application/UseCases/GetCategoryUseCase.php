<?php

declare(strict_types=1);

namespace App\Modules\Categories\Application\UseCases;

use App\Modules\Categories\Domain\Category;
use App\Modules\Categories\Domain\CategoryRepositoryInterface;

class GetCategoryUseCase
{
    public function __construct(
        private CategoryRepositoryInterface $categoryRepository
    ) {}

    public function execute(int $id): ?Category
    {
        return $this->categoryRepository->findById($id);
    }
}

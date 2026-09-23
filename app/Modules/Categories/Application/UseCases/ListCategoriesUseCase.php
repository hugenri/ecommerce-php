<?php

declare(strict_types=1);

namespace App\Modules\Categories\Application\UseCases;

use App\Modules\Categories\Domain\CategoryRepositoryInterface;

class ListCategoriesUseCase
{
    public function __construct(
        private CategoryRepositoryInterface $categoryRepository
    ) {}

    public function execute(
        int $page = 1,
        int $perPage = 10,
        string $search = '',
        string $sortBy = 'name',
        string $sortDir = 'ASC',
        array $filters = []
    ): array {
        return $this->categoryRepository->paginate($page, $perPage, $search, $sortBy, $sortDir, $filters);
    }
}

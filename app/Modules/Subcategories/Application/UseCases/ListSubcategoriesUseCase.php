<?php

declare(strict_types=1);

namespace App\Modules\Subcategories\Application\UseCases;

use App\Modules\Subcategories\Domain\SubcategoryRepositoryInterface;

class ListSubcategoriesUseCase
{
    public function __construct(
        private SubcategoryRepositoryInterface $subcategoryRepository,
    ) {}

    public function execute(
        int $page = 1,
        int $perPage = 10,
        string $search = '',
        string $sortBy = 'subcategories.name',
        string $sortDir = 'ASC',
        array $filters = []
    ): array {
        return $this->subcategoryRepository->paginate($page, $perPage, $search, $sortBy, $sortDir, $filters);
    }
}

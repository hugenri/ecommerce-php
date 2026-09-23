<?php

declare(strict_types=1);

namespace App\Modules\Products\Application\UseCases;

use App\Modules\Products\Domain\ProductRepositoryInterface;

class ListProductsUseCase
{
    public function __construct(
        private ProductRepositoryInterface $productRepository,
    ) {}

    public function execute(
        int $page = 1,
        int $perPage = 10,
        string $search = '',
        string $sortBy = 'products.name',
        string $sortDir = 'ASC',
        array $filters = []
    ): array {
        return $this->productRepository->paginate($page, $perPage, $search, $sortBy, $sortDir, $filters);
    }
}

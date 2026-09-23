<?php

declare(strict_types=1);

namespace App\Modules\Products\Application\UseCases;

use App\Modules\Products\Domain\ProductRepositoryInterface;

class SearchProductsUseCase
{
    public function __construct(
        private ProductRepositoryInterface $productRepository,
    ) {}

    public function execute(string $query, int $limit = 10): array
    {
        return $this->productRepository->search($query, $limit);
    }
}

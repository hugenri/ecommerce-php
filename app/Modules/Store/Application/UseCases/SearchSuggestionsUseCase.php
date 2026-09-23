<?php

declare(strict_types=1);

namespace App\Modules\Store\Application\UseCases;

use App\Modules\Products\Domain\Product;
use App\Modules\Products\Domain\ProductRepositoryInterface;

class SearchSuggestionsUseCase
{
    public function __construct(
        private ProductRepositoryInterface $productRepository,
    ) {}

    /** @return Product[] */
    public function execute(string $query, int $limit = 8): array
    {
        return $this->productRepository->searchSuggestions($query, $limit);
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Products\Application\UseCases;

use App\Modules\Products\Domain\ProductRepositoryInterface;

class ReorderProductImagesUseCase
{
    public function __construct(
        private ProductRepositoryInterface $productRepository,
    ) {}

    public function execute(int $productId, array $imageIds): void
    {
        $this->productRepository->reorderImages($productId, $imageIds);
    }
}

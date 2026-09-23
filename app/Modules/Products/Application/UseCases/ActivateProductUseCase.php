<?php

declare(strict_types=1);

namespace App\Modules\Products\Application\UseCases;

use App\Modules\Products\Domain\Product;
use App\Modules\Products\Domain\ProductRepositoryInterface;

class ActivateProductUseCase
{
    public function __construct(
        private ProductRepositoryInterface $productRepository,
    ) {}

    public function execute(int $id): ?Product
    {
        return $this->productRepository->activate($id);
    }
}

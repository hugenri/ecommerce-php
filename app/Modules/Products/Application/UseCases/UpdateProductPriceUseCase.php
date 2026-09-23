<?php

declare(strict_types=1);

namespace App\Modules\Products\Application\UseCases;

use App\Modules\Products\Domain\Product;
use App\Modules\Products\Domain\ProductRepositoryInterface;

class UpdateProductPriceUseCase
{
    public function __construct(
        private ProductRepositoryInterface $productRepository,
    ) {}

    public function execute(int $id, float $price): ?Product
    {
        if ($price < 0) {
            throw new \DomainException('El precio no puede ser negativo.');
        }

        return $this->productRepository->updatePrice($id, $price);
    }
}

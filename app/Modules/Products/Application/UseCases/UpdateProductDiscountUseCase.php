<?php

declare(strict_types=1);

namespace App\Modules\Products\Application\UseCases;

use App\Modules\Products\Domain\Product;
use App\Modules\Products\Domain\ProductRepositoryInterface;

class UpdateProductDiscountUseCase
{
    public function __construct(
        private ProductRepositoryInterface $productRepository,
    ) {}

    public function execute(int $id, float $discount): ?Product
    {
        if ($discount < 0 || $discount > 100) {
            throw new \DomainException('El descuento debe estar entre 0 y 100.');
        }

        return $this->productRepository->updateDiscount($id, $discount);
    }
}

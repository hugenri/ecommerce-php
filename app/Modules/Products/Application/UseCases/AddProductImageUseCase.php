<?php

declare(strict_types=1);

namespace App\Modules\Products\Application\UseCases;

use App\Modules\Products\Domain\ProductImage;
use App\Modules\Products\Domain\ProductRepositoryInterface;

class AddProductImageUseCase
{
    public function __construct(
        private ProductRepositoryInterface $productRepository,
    ) {}

    public function execute(int $productId, string $image): ProductImage
    {
        $product = $this->productRepository->findById($productId);
        if (!$product) {
            throw new \DomainException('Producto no encontrado.');
        }

        return $this->productRepository->addImage($productId, $image);
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Products\Application\UseCases;

use App\Modules\Products\Domain\ProductRepositoryInterface;

class RemoveProductImageUseCase
{
    public function __construct(
        private ProductRepositoryInterface $productRepository,
    ) {}

    public function execute(int $imageId): bool
    {
        return $this->productRepository->removeImage($imageId);
    }
}

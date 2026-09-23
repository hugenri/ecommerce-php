<?php

declare(strict_types=1);

namespace App\Modules\Products\Application\UseCases;

use App\Modules\Products\Domain\ProductRepositoryInterface;

class DeleteProductUseCase
{
    public function __construct(
        private ProductRepositoryInterface $productRepository,
    ) {}

    public function execute(int $id): bool
    {
        $product = $this->productRepository->findById($id);
        if (!$product) return false;

        if ($this->productRepository->hasSales($id)) {
            throw new \DomainException('No se puede eliminar un producto con ventas registradas.');
        }

        return $this->productRepository->delete($id);
    }
}

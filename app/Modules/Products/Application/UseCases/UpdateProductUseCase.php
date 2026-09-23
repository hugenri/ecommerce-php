<?php

declare(strict_types=1);

namespace App\Modules\Products\Application\UseCases;

use App\Modules\Products\Domain\Product;
use App\Modules\Products\Domain\ProductRepositoryInterface;
use App\Modules\Subcategories\Domain\SubcategoryRepositoryInterface;

class UpdateProductUseCase
{
    public function __construct(
        private ProductRepositoryInterface $productRepository,
        private SubcategoryRepositoryInterface $subcategoryRepository,
    ) {}

    public function execute(int $id, array $data): ?Product
    {
        $product = $this->productRepository->findById($id);
        if (!$product) return null;

        $subcategoryId = isset($data['subcategory_id'])
            ? (int) $data['subcategory_id']
            : $product->getSubcategoryId();

        if ($subcategoryId !== $product->getSubcategoryId()
            && !$this->subcategoryRepository->findById($subcategoryId)) {
            throw new \DomainException('La subcategoría especificada no existe.');
        }

        $name = trim($data['name'] ?? $product->getName());

        $product = $product->updateInfo(
            subcategoryId: $subcategoryId,
            name: $name,
            description: isset($data['description'])
                ? trim($data['description'])
                : $product->getDescription(),
        );

        return $this->productRepository->update($product);
    }
}

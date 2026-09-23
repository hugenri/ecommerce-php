<?php

declare(strict_types=1);

namespace App\Modules\Products\Application\UseCases;

use App\Modules\Products\Domain\Product;
use App\Modules\Products\Domain\ProductRepositoryInterface;
use App\Modules\Subcategories\Domain\SubcategoryRepositoryInterface;

class CreateProductUseCase
{
    public function __construct(
        private ProductRepositoryInterface $productRepository,
        private SubcategoryRepositoryInterface $subcategoryRepository,
    ) {}

    public function execute(array $data): Product
    {
        $subcategoryId = (int) ($data['subcategory_id'] ?? 0);

        if (!$this->subcategoryRepository->findById($subcategoryId)) {
            throw new \DomainException('La subcategoría especificada no existe.');
        }

        $code = trim($data['product_code'] ?? '');
        if ($this->productRepository->existsByCode($code)) {
            throw new \DomainException('El código de producto ya existe.');
        }

        $product = new Product(
            productId: null,
            subcategoryId: $subcategoryId,
            productCode: $code,
            name: trim($data['name'] ?? ''),
            description: isset($data['description']) ? trim($data['description']) : null,
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
        );

        return $this->productRepository->create($product);
    }
}

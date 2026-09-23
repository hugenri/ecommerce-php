<?php

declare(strict_types=1);

namespace App\Modules\Products\Presentation;

use App\Modules\Products\Domain\Product;
use App\Modules\Products\Domain\ProductImage;

class ProductSerializer
{
    public function toArray(Product $product): array
    {
        return [
            'product_id' => $product->getProductId(),
            'subcategory_id' => $product->getSubcategoryId(),
            'product_code' => $product->getProductCode(),
            'name' => $product->getName(),
            'description' => $product->getDescription() ?? '',
            'image' => $product->getImage() ?? '',
            'stock' => $product->getStock(),
            'price' => $product->getPrice(),
            'discount' => $product->getDiscount(),
            'status' => $product->getStatus(),
            'is_active' => $product->isActive(),
            'created_at' => $product->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updated_at' => $product->getUpdatedAt()?->format('Y-m-d H:i:s'),
        ];
    }

    public function imageToArray(ProductImage $image): array
    {
        return [
            'image_id' => $image->getImageId(),
            'product_id' => $image->getProductId(),
            'image' => $image->getImage(),
            'sort_order' => $image->getSortOrder(),
        ];
    }
}

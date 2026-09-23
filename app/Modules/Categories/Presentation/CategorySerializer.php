<?php

declare(strict_types=1);

namespace App\Modules\Categories\Presentation;

use App\Modules\Categories\Domain\Category;

class CategorySerializer
{
    public function toArray(Category $category): array
    {
        return [
            'category_id' => $category->getCategoryId(),
            'name' => $category->getName(),
            'description' => $category->getDescription() ?? '',
            'image' => $category->getImage() ?? '',
            'status' => $category->getStatus(),
            'is_active' => $category->isActive(),
            'created_at' => $category->getCreatedAt()?->format('Y-m-d H:i:s'),
            'updated_at' => $category->getUpdatedAt()?->format('Y-m-d H:i:s'),
        ];
    }
}

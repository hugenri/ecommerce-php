<?php

declare(strict_types=1);

namespace App\Modules\Subcategories\Presentation;

use App\Modules\Subcategories\Domain\Subcategory;

class SubcategorySerializer
{
    public function toArray(Subcategory $subcategory): array
    {
        return [
            'subcategory_id' => $subcategory->getSubcategoryId(),
            'category_id' => $subcategory->getCategoryId(),
            'name' => $subcategory->getName(),
            'description' => $subcategory->getDescription() ?? '',
            'status' => $subcategory->getStatus(),
            'is_active' => $subcategory->isActive(),
        ];
    }
}

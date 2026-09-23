<?php

declare(strict_types=1);

namespace App\Modules\Products\Domain;

class ProductImage
{
    public function __construct(
        private readonly ?int $imageId,
        private int $productId,
        private string $image,
        private int $sortOrder = 0,
    ) {}

    public function getImageId(): ?int { return $this->imageId; }
    public function getProductId(): int { return $this->productId; }
    public function getImage(): string { return $this->image; }
    public function getSortOrder(): int { return $this->sortOrder; }

    public function withImageId(int $imageId): self
    {
        return $this->cloneWith(['imageId' => $imageId]);
    }

    public function withSortOrder(int $sortOrder): self
    {
        return $this->cloneWith(['sortOrder' => $sortOrder]);
    }

    private function cloneWith(array $overrides): self
    {
        return new self(
            imageId: $overrides['imageId'] ?? $this->imageId,
            productId: $overrides['productId'] ?? $this->productId,
            image: $overrides['image'] ?? $this->image,
            sortOrder: $overrides['sortOrder'] ?? $this->sortOrder,
        );
    }
}

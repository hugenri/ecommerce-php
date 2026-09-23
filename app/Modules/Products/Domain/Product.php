<?php

declare(strict_types=1);

namespace App\Modules\Products\Domain;

class Product
{
    public function __construct(
        private readonly ?int $productId,
        private int $subcategoryId,
        private string $productCode,
        private string $name,
        private ?string $description = null,
        private ?string $image = null,
        private int $stock = 0,
        private ?float $price = null,
        private float $discount = 0.00,
        private string $status = 'active',
        private ?\DateTimeImmutable $createdAt = null,
        private ?\DateTimeImmutable $updatedAt = null,
    ) {}

    public function getProductId(): ?int { return $this->productId; }
    public function getSubcategoryId(): int { return $this->subcategoryId; }
    public function getProductCode(): string { return $this->productCode; }
    public function getName(): string { return $this->name; }
    public function getDescription(): ?string { return $this->description; }
    public function getImage(): ?string { return $this->image; }
    public function getStock(): int { return $this->stock; }
    public function getPrice(): ?float { return $this->price; }
    public function getDiscount(): float { return $this->discount; }
    public function getStatus(): string { return $this->status; }
    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function withProductId(int $productId): self
    {
        return $this->cloneWith(['productId' => $productId]);
    }

    public function withUpdatedTimestamp(): self
    {
        return $this->cloneWith(['updatedAt' => new \DateTimeImmutable()]);
    }

    public function activate(): self
    {
        return $this->cloneWith([
            'status' => 'active',
            'updatedAt' => new \DateTimeImmutable(),
        ]);
    }

    public function deactivate(): self
    {
        return $this->cloneWith([
            'status' => 'inactive',
            'updatedAt' => new \DateTimeImmutable(),
        ]);
    }

    public function updateInfo(
        int $subcategoryId,
        string $name,
        ?string $description = null
    ): self {
        return $this->cloneWith([
            'subcategoryId' => $subcategoryId,
            'name' => $name,
            'description' => $description,
            'updatedAt' => new \DateTimeImmutable(),
        ]);
    }

    public function changeMainImage(?string $image): self
    {
        return $this->cloneWith([
            'image' => $image,
            'updatedAt' => new \DateTimeImmutable(),
        ]);
    }

    public function updatePrice(float $price): self
    {
        return $this->cloneWith([
            'price' => $price,
            'updatedAt' => new \DateTimeImmutable(),
        ]);
    }

    public function updateDiscount(float $discount): self
    {
        return $this->cloneWith([
            'discount' => $discount,
            'updatedAt' => new \DateTimeImmutable(),
        ]);
    }

    public function updateStock(int $stock): self
    {
        return $this->cloneWith([
            'stock' => $stock,
            'updatedAt' => new \DateTimeImmutable(),
        ]);
    }

    private function cloneWith(array $overrides): self
    {
        return new self(
            productId: $overrides['productId'] ?? $this->productId,
            subcategoryId: $overrides['subcategoryId'] ?? $this->subcategoryId,
            productCode: $overrides['productCode'] ?? $this->productCode,
            name: $overrides['name'] ?? $this->name,
            description: $overrides['description'] ?? $this->description,
            image: $overrides['image'] ?? $this->image,
            stock: $overrides['stock'] ?? $this->stock,
            price: $overrides['price'] ?? $this->price,
            discount: $overrides['discount'] ?? $this->discount,
            status: $overrides['status'] ?? $this->status,
            createdAt: $overrides['createdAt'] ?? $this->createdAt,
            updatedAt: $overrides['updatedAt'] ?? $this->updatedAt,
        );
    }
}

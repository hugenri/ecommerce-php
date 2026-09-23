<?php

declare(strict_types=1);

namespace App\Modules\Subcategories\Domain;

class Subcategory
{
    public function __construct(
        private readonly ?int $subcategoryId,
        private int $categoryId,
        private string $name,
        private ?string $description = null,
        private string $status = 'active',
    ) {}

    public function getSubcategoryId(): ?int { return $this->subcategoryId; }
    public function getCategoryId(): int { return $this->categoryId; }
    public function getName(): string { return $this->name; }
    public function getDescription(): ?string { return $this->description; }
    public function getStatus(): string { return $this->status; }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function withSubcategoryId(int $subcategoryId): self
    {
        return $this->cloneWith(['subcategoryId' => $subcategoryId]);
    }

    public function activate(): self
    {
        return $this->cloneWith(['status' => 'active']);
    }

    public function deactivate(): self
    {
        return $this->cloneWith(['status' => 'inactive']);
    }

    public function updateInfo(int $categoryId, string $name, ?string $description = null): self
    {
        return $this->cloneWith([
            'categoryId' => $categoryId,
            'name' => $name,
            'description' => $description,
        ]);
    }

    private function cloneWith(array $overrides): self
    {
        return new self(
            subcategoryId: $overrides['subcategoryId'] ?? $this->subcategoryId,
            categoryId: $overrides['categoryId'] ?? $this->categoryId,
            name: $overrides['name'] ?? $this->name,
            description: $overrides['description'] ?? $this->description,
            status: $overrides['status'] ?? $this->status,
        );
    }
}

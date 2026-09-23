<?php

declare(strict_types=1);

namespace App\Modules\Categories\Domain;

class Category
{
    public function __construct(
        private readonly ?int $categoryId,
        private string $name,
        private ?string $description = null,
        private ?string $image = null,
        private string $status = 'active',
        private ?\DateTimeImmutable $createdAt = null,
        private ?\DateTimeImmutable $updatedAt = null,
    ) {}

    public function getCategoryId(): ?int { return $this->categoryId; }
    public function getName(): string { return $this->name; }
    public function getDescription(): ?string { return $this->description; }
    public function getImage(): ?string { return $this->image; }
    public function getStatus(): string { return $this->status; }
    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function withCategoryId(int $categoryId): self
    {
        return $this->cloneWith(['categoryId' => $categoryId]);
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

    public function updateInfo(string $name, ?string $description = null, ?string $image = null): self
    {
        return $this->cloneWith([
            'name' => $name,
            'description' => $description,
            'image' => $image,
            'updatedAt' => new \DateTimeImmutable(),
        ]);
    }

    private function cloneWith(array $overrides): self
    {
        return new self(
            categoryId: $overrides['categoryId'] ?? $this->categoryId,
            name: $overrides['name'] ?? $this->name,
            description: $overrides['description'] ?? $this->description,
            image: $overrides['image'] ?? $this->image,
            status: $overrides['status'] ?? $this->status,
            createdAt: $overrides['createdAt'] ?? $this->createdAt,
            updatedAt: $overrides['updatedAt'] ?? $this->updatedAt,
        );
    }
}

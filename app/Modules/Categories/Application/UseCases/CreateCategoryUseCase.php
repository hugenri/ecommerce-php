<?php

declare(strict_types=1);

namespace App\Modules\Categories\Application\UseCases;

use App\Modules\Categories\Domain\Category;
use App\Modules\Categories\Domain\CategoryRepositoryInterface;

class CreateCategoryUseCase
{
    public function __construct(
        private CategoryRepositoryInterface $categoryRepository
    ) {}

    public function execute(array $data): Category
    {
        $name = trim($data['name']);

        if ($this->categoryRepository->existsByName($name)) {
            throw new \DomainException('El nombre de la categoría ya existe.');
        }

        $category = new Category(
            categoryId: null,
            name: $name,
            description: isset($data['description']) ? trim($data['description']) : null,
            image: $data['image'] ?? null,
            status: 'active',
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
        );

        return $this->categoryRepository->create($category);
    }
}

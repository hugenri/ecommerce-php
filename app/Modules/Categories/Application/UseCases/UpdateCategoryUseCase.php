<?php

declare(strict_types=1);

namespace App\Modules\Categories\Application\UseCases;

use App\Modules\Categories\Domain\Category;
use App\Modules\Categories\Domain\CategoryRepositoryInterface;

class UpdateCategoryUseCase
{
    public function __construct(
        private CategoryRepositoryInterface $categoryRepository
    ) {}

    public function execute(int $id, array $data): ?Category
    {
        $category = $this->categoryRepository->findById($id);

        if (!$category) {
            return null;
        }

        $name = trim($data['name'] ?? $category->getName());
        if ($name !== $category->getName() && $this->categoryRepository->existsByName($name, $id)) {
            throw new \DomainException('El nombre de la categoría ya existe.');
        }

        $category = $category->updateInfo(
            name: $name,
            description: isset($data['description']) ? trim($data['description']) : $category->getDescription(),
            image: $data['image'] ?? $category->getImage(),
        );

        return $this->categoryRepository->update($category);
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\UseCases;

use App\Modules\Inventory\Domain\InventoryRepositoryInterface;

class ListInventoryUseCase
{
    public function __construct(
        private InventoryRepositoryInterface $inventoryRepository,
    ) {}

    public function execute(
        int $page = 1,
        int $perPage = 10,
        string $search = '',
        string $sortBy = 'name',
        string $sortDir = 'ASC'
    ): array {
        return $this->inventoryRepository->stockLevels(
            page: $page,
            perPage: $perPage,
            search: $search,
            sortBy: $sortBy,
            sortDir: $sortDir,
        );
    }
}

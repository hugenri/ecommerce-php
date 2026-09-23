<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\UseCases;

use App\Modules\Inventory\Domain\InventoryRepositoryInterface;

class ListInventoryMovementsUseCase
{
    public function __construct(
        private InventoryRepositoryInterface $inventoryRepository,
    ) {}

    public function execute(int $page = 1, int $perPage = 10, array $filters = []): array
    {
        return $this->inventoryRepository->movements(
            page: $page,
            perPage: $perPage,
            filters: $filters,
        );
    }
}

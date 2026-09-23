<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Application\UseCases;

use App\Modules\Inventory\Domain\InventoryRepositoryInterface;

class LowStockReportUseCase
{
    public function __construct(
        private InventoryRepositoryInterface $inventoryRepository,
    ) {}

    public function execute(int $threshold = 10, int $page = 1, int $perPage = 10): array
    {
        if ($threshold < 0) {
            $threshold = 0;
        }

        return $this->inventoryRepository->lowStock(
            threshold: $threshold,
            page: $page,
            perPage: $perPage,
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Reports\Application;

use App\Modules\Inventory\Application\UseCases\LowStockReportUseCase as InventoryLowStockReportUseCase;

class LowStockReportUseCase
{
    public function __construct(
        private InventoryLowStockReportUseCase $lowStockReport,
    ) {}

    public function execute(int $threshold = 10, int $page = 1, int $perPage = 10): array
    {
        return $this->lowStockReport->execute($threshold, $page, $perPage);
    }
}

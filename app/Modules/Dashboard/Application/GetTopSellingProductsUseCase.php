<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Application;

use App\Modules\Dashboard\Domain\DashboardRepositoryInterface;

class GetTopSellingProductsUseCase
{
    public function __construct(
        private DashboardRepositoryInterface $dashboardRepository,
    ) {}

    public function execute(int $limit = 10): array
    {
        return $this->dashboardRepository->getTopSellingProducts($limit);
    }
}

<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Application;

use App\Modules\Dashboard\Domain\DashboardRepositoryInterface;

class GetDashboardSummaryUseCase
{
    public function __construct(
        private DashboardRepositoryInterface $dashboardRepository,
    ) {}

    public function execute(): array
    {
        return $this->dashboardRepository->getSummary();
    }
}

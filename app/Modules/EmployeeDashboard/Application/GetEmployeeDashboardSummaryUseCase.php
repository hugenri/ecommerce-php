<?php

declare(strict_types=1);

namespace App\Modules\EmployeeDashboard\Application;

use App\Modules\EmployeeDashboard\Domain\EmployeeDashboardRepositoryInterface;

class GetEmployeeDashboardSummaryUseCase
{
    public function __construct(
        private EmployeeDashboardRepositoryInterface $repository,
    ) {}

    /** @return array{ pending: int, assigned: int, preparing: int, shipped_today: int, delivered_today: int } */
    public function execute(int $userId): array
    {
        return $this->repository->getSummary($userId);
    }
}

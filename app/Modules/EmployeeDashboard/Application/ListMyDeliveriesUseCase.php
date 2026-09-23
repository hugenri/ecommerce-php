<?php

declare(strict_types=1);

namespace App\Modules\EmployeeDashboard\Application;

use App\Modules\EmployeeDashboard\Domain\EmployeeDashboardRepositoryInterface;

class ListMyDeliveriesUseCase
{
    public function __construct(
        private EmployeeDashboardRepositoryInterface $repository,
    ) {}

    /** @return array{data: array, meta: array} */
    public function execute(int $userId, int $page = 1, int $perPage = 10): array
    {
        return $this->repository->findMyDeliveries($userId, $page, $perPage);
    }
}

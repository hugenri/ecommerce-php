<?php

declare(strict_types=1);

namespace App\Modules\EmployeeDashboard\Application;

use App\Modules\EmployeeDashboard\Domain\EmployeeDashboardRepositoryInterface;

class ListPendingDeliveriesUseCase
{
    public function __construct(
        private EmployeeDashboardRepositoryInterface $repository,
    ) {}

    /** @return array{data: array, meta: array} */
    public function execute(int $page = 1, int $perPage = 10): array
    {
        return $this->repository->findUnassigned($page, $perPage);
    }
}

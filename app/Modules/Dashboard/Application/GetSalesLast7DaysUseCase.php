<?php

declare(strict_types=1);

namespace App\Modules\Dashboard\Application;

use App\Modules\Dashboard\Domain\DashboardRepositoryInterface;

class GetSalesLast7DaysUseCase
{
    public function __construct(
        private DashboardRepositoryInterface $dashboardRepository,
    ) {}

    public function execute(): array
    {
        $rows = $this->dashboardRepository->getSalesLast7Days();

        $byDate = array_column($rows, null, 'sale_date');

        $series = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = (new \DateTimeImmutable())->modify("-{$i} days")->format('Y-m-d');
            $series[] = $byDate[$date] ?? [
                'sale_date' => $date,
                'orders' => 0,
                'total' => 0.0,
            ];
        }

        return $series;
    }
}
